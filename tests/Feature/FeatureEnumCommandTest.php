<?php

namespace Chargebee\Cashier\Tests\Feature;

use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Tests\Fixtures\FeatureActionsFixture;
use Illuminate\Support\Facades\File;

class FeatureEnumCommandTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Mock the Chargebee client

        File::shouldReceive('exists')->andReturn(false);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->never();
    }

    private function createChargebeeFeatureMock(FeatureActionsFixture $fixture)
    {
        $client = Cashier::$chargebeeClient;
        $spy = \Mockery::mock($client)->makePartial();
        $spy->shouldReceive('feature')->andReturn($fixture);
        Cashier::$chargebeeClient = $spy;
    }

    public function test_generate_feature_enum_should_create_enum_file_with_cases_and_values(): void
    {
        $capturedPath = null;
        $capturedPhp = null;
        $this->createChargebeeFeatureMock(new FeatureActionsFixture);
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPath, &$capturedPhp) {
                $capturedPath = $path;
                $capturedPhp = $php;

                return true;
            });
        $this->artisan('cashier:generate-feature-enum', [
            '--class' => 'FeaturesMap',
            '--namespace' => 'App\\Models',
            '--path' => 'app/Models',
            '--force' => true,
        ])->assertExitCode(0);

        // Add assertions to verify the captured content
        $this->assertNotNull($capturedPath);
        $this->assertNotNull($capturedPhp);

        $expectedPhp = <<<'PHP'
<?php

/**
 * THIS IS A GENERATED FILE. DO NOT EDIT THIS MANUALLY!
 * Run `php artisan cashier:generate-feature-enum` to regenerate this file.
 * ANY CHANGES MADE TO THIS FILE MAY BE OVERWRITTEN.
 */

declare(strict_types=1);

namespace App\Models;

use Chargebee\Cashier\Contracts\FeatureEnumContract;

enum FeaturesMap: string implements FeatureEnumContract
{
    case FREE_TRIAL = 'feature_free_trial';
    case PRIORITY_SUPPORT = 'feature_priority_support';

    public static function values(): array
    {
        return array(
            0 => 'feature_free_trial',
            1 => 'feature_priority_support',
        );
    }

    public function id(): string
    {
        return $this->value;
    }

    /**
     * @param array<string> $featureIds
     * @return array<FeatureEnumContract>
     */
    public static function fromArray(array $featureIds): array
    {
        return array_map(fn (string $featureId) => self::from($featureId), $featureIds);
    }
}
PHP;

        // Verify the file path
        $expectedPath = base_path('app/Models/FeaturesMap.php');
        $this->assertEquals($expectedPath, $capturedPath);

        // Verify the generated PHP contains expected elements
        $this->assertEquals($expectedPhp, $capturedPhp);
    }

    public function test_should_overwrite_existing_file_when_force_option_is_used(): void
    {
        $this->createChargebeeFeatureMock(new FeatureActionsFixture);
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('put')->once()->andReturn(true);

        $this->artisan('cashier:generate-feature-enum', [
            '--class' => 'FeaturesMap',
            '--namespace' => 'App\\Models',
            '--path' => 'app/Models',
            '--force' => true,
        ])->assertExitCode(0);
    }

    public function test_should_create_directory_when_it_does_not_exist(): void
    {
        $this->createChargebeeFeatureMock(new FeatureActionsFixture);
        File::shouldReceive('isDirectory')->andReturn(false);
        File::shouldReceive('makeDirectory')
            ->with(base_path('app/Models'), 0755, true)
            ->andReturn(true);
        File::shouldReceive('put')->once()->andReturn(true);

        $this->artisan('cashier:generate-feature-enum', [
            '--class' => 'FeaturesMap',
            '--namespace' => 'App\\Models',
            '--path' => 'app/Models',
            '--force' => true,
        ])->assertExitCode(0);
    }

    public function test_should_use_default_options_when_none_provided(): void
    {
        $this->createChargebeeFeatureMock(new FeatureActionsFixture);
        $capturedPath = null;
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPath) {
                $capturedPath = $path;

                return true;
            });

        $this->artisan('cashier:generate-feature-enum', ['--force' => true])
            ->assertExitCode(0);

        $expectedPath = base_path('app/Models/Feature.php');
        $this->assertEquals($expectedPath, $capturedPath);
    }

    public function test_should_handle_custom_class_and_namespace(): void
    {
        $this->createChargebeeFeatureMock(new FeatureActionsFixture);
        $capturedPath = null;
        $capturedPhp = null;
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPath, &$capturedPhp) {
                $capturedPath = $path;
                $capturedPhp = $php;

                return true;
            });

        $this->artisan('cashier:generate-feature-enum', [
            '--class' => 'CustomFeatures',
            '--namespace' => 'App\\Enums',
            '--path' => 'app/Enums',
            '--force' => true,
        ])->assertExitCode(0);

        $expectedPath = base_path('app/Enums/CustomFeatures.php');
        $expectedPhp = <<<'PHP'
<?php

/**
 * THIS IS A GENERATED FILE. DO NOT EDIT THIS MANUALLY!
 * Run `php artisan cashier:generate-feature-enum` to regenerate this file.
 * ANY CHANGES MADE TO THIS FILE MAY BE OVERWRITTEN.
 */

declare(strict_types=1);

namespace App\Enums;

use Chargebee\Cashier\Contracts\FeatureEnumContract;

enum CustomFeatures: string implements FeatureEnumContract
{
    case FREE_TRIAL = 'feature_free_trial';
    case PRIORITY_SUPPORT = 'feature_priority_support';

    public static function values(): array
    {
        return array(
            0 => 'feature_free_trial',
            1 => 'feature_priority_support',
        );
    }

    public function id(): string
    {
        return $this->value;
    }

    /**
     * @param array<string> $featureIds
     * @return array<FeatureEnumContract>
     */
    public static function fromArray(array $featureIds): array
    {
        return array_map(fn (string $featureId) => self::from($featureId), $featureIds);
    }
}
PHP;
        $this->assertEquals($expectedPath, $capturedPath);
        $this->assertEquals($expectedPhp, $capturedPhp);
    }

    public function test_should_handle_namespace_with_trailing_backslash(): void
    {
        $this->createChargebeeFeatureMock(new FeatureActionsFixture);
        $capturedPhp = null;
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPhp) {
                $capturedPhp = $php;

                return true;
            });

        $this->artisan('cashier:generate-feature-enum', [
            '--namespace' => 'App\\Models\\',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertStringContainsString('namespace App\\Models;', $capturedPhp);
    }

    public function test_should_handle_path_with_trailing_slash(): void
    {
        $this->createChargebeeFeatureMock(new FeatureActionsFixture);
        $capturedPath = null;
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPath) {
                $capturedPath = $path;

                return true;
            });

        $this->artisan('cashier:generate-feature-enum', [
            '--path' => 'app/Models/',
            '--force' => true,
        ])->assertExitCode(0);

        $expectedPath = base_path('app/Models/Feature.php');
        $this->assertEquals($expectedPath, $capturedPath);
    }

    public function test_should_skip_features_with_invalid_names(): void
    {
        $this->createChargebeeFeatureMock(new FeatureActionsFixture(FeatureActionsFixture::$featureListWithInvalidEnumName));
        $capturedPhp = null;
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPhp) {
                $capturedPhp = $php;

                return true;
            });

        $this->artisan('cashier:generate-feature-enum', ['--force' => true])
            ->assertExitCode(0);
        $expectedPhp = <<<'PHP'
<?php

/**
 * THIS IS A GENERATED FILE. DO NOT EDIT THIS MANUALLY!
 * Run `php artisan cashier:generate-feature-enum` to regenerate this file.
 * ANY CHANGES MADE TO THIS FILE MAY BE OVERWRITTEN.
 */

declare(strict_types=1);

namespace App\Models;

use Chargebee\Cashier\Contracts\FeatureEnumContract;

enum Feature: string implements FeatureEnumContract
{
    case FREE_TRIAL = 'feature_free_trial';

    public static function values(): array
    {
        return array(
            0 => 'feature_free_trial',
        );
    }

    public function id(): string
    {
        return $this->value;
    }

    /**
     * @param array<string> $featureIds
     * @return array<FeatureEnumContract>
     */
    public static function fromArray(array $featureIds): array
    {
        return array_map(fn (string $featureId) => self::from($featureId), $featureIds);
    }
}
PHP;
        // Should only contain the valid feature
        $this->assertEquals($expectedPhp, $capturedPhp);
    }

    public function test_should_escape_special_characters_in_values(): void
    {
        $this->createChargebeeFeatureMock(new FeatureActionsFixture(FeatureActionsFixture::$featureListWithSpecialCharacterInName));
        $capturedPhp = null;
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPhp) {
                $capturedPhp = $php;

                return true;
            });
        $this->artisan('cashier:generate-feature-enum', ['--force' => true])
            ->assertExitCode(0);
        $expectedPhp = <<<'PHP'
<?php

/**
 * THIS IS A GENERATED FILE. DO NOT EDIT THIS MANUALLY!
 * Run `php artisan cashier:generate-feature-enum` to regenerate this file.
 * ANY CHANGES MADE TO THIS FILE MAY BE OVERWRITTEN.
 */

declare(strict_types=1);

namespace App\Models;

use Chargebee\Cashier\Contracts\FeatureEnumContract;

enum Feature: string implements FeatureEnumContract
{
    case FREE_TRIAL = 'feature_free_trial';
    case PRIORITY_SUPPORT = 'feature_priority_support';

    public static function values(): array
    {
        return array(
            0 => 'feature_free_trial',
            1 => 'feature_priority_support',
        );
    }

    public function id(): string
    {
        return $this->value;
    }

    /**
     * @param array<string> $featureIds
     * @return array<FeatureEnumContract>
     */
    public static function fromArray(array $featureIds): array
    {
        return array_map(fn (string $featureId) => self::from($featureId), $featureIds);
    }
}
PHP;
        $this->assertEquals($capturedPhp, $expectedPhp);
    }
}