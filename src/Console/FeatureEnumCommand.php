<?php

namespace Chargebee\Cashier\Console;

use Illuminate\Console\Command;
use Chargebee\Cashier\Cashier;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'cashier:generate-feature-enum')]
class FeatureEnumCommand extends Command
{
    protected $signature = 'cashier:generate-feature-enum
        {--class=FeaturesMap : The enum class name}
        {--namespace=App\\Models : The namespace for the enum}
        {--path=app/Models : Directory to save the enum file}
        {--force : Overwrite if the file already exists}';

    protected $description = 'Generate a PHP enum of Chargebee features and save it in app/Models';

    public function handle(): int
    {
        $class = $this->option('class');
        $namespace = rtrim($this->option('namespace'), '\\');
        $path = rtrim($this->option('path'), '/');
        $filePath = base_path("{$path}/{$class}.php");

        try {
            $this->components->info("Fetching features from Chargebee…");
            $result = Cashier::chargebee()->feature()->all();

            $cases = [];
            foreach ($result->list as $featureList) {
                $feature = $featureList->feature;

                $caseName = $this->toEnumCase($feature->name);
                if ($caseName === '') {
                    $this->warn("Skipping feature with name that cannot be mapped to php enum feature name: '{$feature->name}' \n");
                    continue;
                }
                $caseValue = $feature->id ?? $feature->name;

                if (isset($cases[$caseName])) {
                    // Avoid duplicate keys
                    $caseName .= '_' . substr(md5($caseValue), 0, 6);
                }
                $cases[$caseName] = $caseValue;
            }

            if (empty($cases)) {
                $this->error('No features found.');
                return self::FAILURE;
            }

            $php = $this->renderEnum($namespace, $class, $cases);

            if (File::exists($filePath) && !$this->option('force')) {
                $this->error("File already exists at {$filePath}. Use --force to overwrite.");
                return self::FAILURE;
            }

            if (!File::isDirectory(base_path($path))) {
                File::makeDirectory(base_path($path), 0755, true);
            }

            File::put($filePath, $php);
            $this->components->info("✅ Enum generated at {$filePath}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function toEnumCase(string $name): string
    {
        $underscored = preg_replace('/[^a-zA-Z0-9]+/', '_', $name);
        $underscored = preg_replace('/^[0-9]+/', '', $underscored);
        return strtoupper(trim($underscored, '_'));
    }

    protected function renderEnum(string $namespace, string $class, array $cases): string
    {
        $casesBlock = collect($cases)
            ->map(fn ($val, $name) => "    case {$name} = '" . addslashes($val) . "';")
            ->implode("\n");

        $arrayExport = var_export(array_values($cases), true);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

enum {$class}: string
{
{$casesBlock}

    public static function values(): array
    {
        return {$arrayExport};
    }
}

PHP;
    }
}
