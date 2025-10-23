<?php

namespace Chargebee\Cashier\Console;

use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Feature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'cashier:generate-feature-enum')]
class FeatureEnumCommand extends Command
{
    protected $signature = 'cashier:generate-feature-enum
        {--class=Feature : The enum class name}
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
            $this->components->info('Fetching features from Chargebee…');
            $nextPage = null;
            $cases = [];
            do {
                $result = Cashier::chargebee()->feature()->all([
                    'offset' => $nextPage,
                ]);
                $nextPage = $result->next_offset;
                foreach ($result->list as $featureList) {
                    $feature = $featureList->feature;

                    $caseName = $this->toEnumCase($feature->name);
                    if ($caseName === '') {
                        $this->warn("Skipping feature with name that cannot be mapped to php enum feature name: '{$feature->name}' \n");

                        continue;
                    }
                    $caseValue = $feature->id;
                    if (isset($cases[$caseName])) {
                        // Avoid duplicate keys
                        $caseName .= '_'.substr(md5($caseValue), 0, 6);
                    }
                    Feature::updateOrCreate(
                        ['chargebee_id' => $feature->id],
                        ['json_data' => $feature->toArray()]
                    );
                    $cases[$caseName] = $caseValue;
                }
            } while ($nextPage);

            if (empty($cases)) {
                $this->error('No features found.');

                return self::FAILURE;
            }

            $php = $this->renderEnum($namespace, $class, $cases);

            if (File::exists($filePath) && ! $this->option('force')) {
                $this->error("File already exists at {$filePath}. Use --force to overwrite.");

                return self::FAILURE;
            }

            if (! File::isDirectory(base_path($path))) {
                File::makeDirectory(base_path($path), 0755, true);
            }

            File::put($filePath, $php);
            $this->components->info("✅ Enum generated at {$filePath}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: '.$e->getMessage());

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
            ->map(fn ($val, $name) => "    case {$name} = '".addslashes($val)."';")
            ->implode("\n");

        // Format the array values with proper indentation
        $arrayValues = [];
        $index = 0;
        foreach ($cases as $value) {
            $arrayValues[] = "            {$index} => '".addslashes($value)."',";
            $index++;
        }
        $arrayValuesBlock = implode("\n", $arrayValues);

        return <<<PHP
<?php

/**
 * THIS IS A GENERATED FILE. DO NOT EDIT THIS MANUALLY!
 * Run `php artisan cashier:generate-feature-enum` to regenerate this file.
 * ANY CHANGES MADE TO THIS FILE MAY BE OVERWRITTEN.
 */

declare(strict_types=1);

namespace {$namespace};

use Chargebee\Cashier\Contracts\FeatureEnumContract;

enum {$class}: string implements FeatureEnumContract
{
{$casesBlock}

    public static function values(): array
    {
        return array(
{$arrayValuesBlock}
        );
    }

    public function id(): string
    {
        return \$this->value;
    }

    /**
     * @param array<string> \$featureIds
     * @return array<FeatureEnumContract>
     */
    public static function fromArray(array \$featureIds): array
    {
        return array_map(fn (string \$featureId) => self::from(\$featureId), \$featureIds);
    }
}
PHP;
    }
}
