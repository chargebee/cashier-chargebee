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
        $client = Cashier::$chargebeeClient;
        $spy = \Mockery::mock($client)->makePartial();
        $spy->shouldReceive('feature')->andReturn(new FeatureActionsFixture());
        Cashier::$chargebeeClient = $spy;

        File::shouldReceive('exists')->andReturn(false);
        File::shouldReceive('isDirectory')->andReturn(true);
        File::shouldReceive('makeDirectory')->never();
    }

    public function test_generate_feature_enum_should_create_enum_file_with_cases_and_values(): void
    {
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
            '--class' => 'FeaturesMap',
            '--namespace' => 'App\\Models',
            '--path' => 'app/Models',
            '--force' => true,
        ])->assertExitCode(0);

        // Add assertions to verify the captured content
        $this->assertNotNull($capturedPath);
        $this->assertNotNull($capturedPhp);

        // Verify the file path
        $expectedPath = base_path('app/Models/FeaturesMap.php');
        $this->assertEquals($expectedPath, $capturedPath);

        // Verify the generated PHP contains expected elements
        $this->assertStringContainsString('namespace App\\Models;', $capturedPhp);
        $this->assertStringContainsString('enum FeaturesMap: string', $capturedPhp);
        $this->assertStringContainsString('public static function values(): array', $capturedPhp);
        $this->assertStringContainsString("case FREE_TRIAL = 'feature_free_trial';", $capturedPhp);
        $this->assertStringContainsString("case PRIORITY_SUPPORT = 'feature_priority_support';", $capturedPhp);
    }
    public function test_should_overwrite_existing_file_when_force_option_is_used(): void
    {
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
        $capturedPath = null;
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPath) {
                $capturedPath = $path;
                return true;
            });

        $this->artisan('cashier:generate-feature-enum', ['--force' => true])
            ->assertExitCode(0);

        $expectedPath = base_path('app/Models/FeaturesMap.php');
        $this->assertEquals($expectedPath, $capturedPath);
    }

    public function test_should_handle_custom_class_and_namespace(): void
    {
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
        $this->assertEquals($expectedPath, $capturedPath);
        $this->assertStringContainsString('namespace App\\Enums;', $capturedPhp);
        $this->assertStringContainsString('enum CustomFeatures: string', $capturedPhp);
    }

    public function test_should_handle_namespace_with_trailing_backslash(): void
    {
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

        $expectedPath = base_path('app/Models/FeaturesMap.php');
        $this->assertEquals($expectedPath, $capturedPath);
    }


    public function test_should_skip_features_with_invalid_names(): void
    {
        $capturedPhp = null;
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPhp) {
                $capturedPhp = $php;
                return true;
            });

        $this->artisan('cashier:generate-feature-enum', ['--force' => true])
            ->assertExitCode(0);

        // Should only contain the valid feature
        $this->assertStringContainsString("case FREE_TRIAL = 'feature_free_trial';", $capturedPhp);
        $this->assertStringNotContainsString('12121212', $capturedPhp);
    }


    // even though this method is here chargebee itself doesn't allow duplicate feature name as of now.
    public function test_should_handle_duplicate_case_names(): void
    {
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPhp) {
                $capturedPhp = $php;
                return true;
            });

        $this->artisan('cashier:generate-feature-enum', ['--force' => true])
            ->assertExitCode(0);

        // Should contain both features with different case names
        $this->assertStringContainsString("case PRIORITY_SUPPORT = 'feature_priority_support';", $capturedPhp);
        // The second one should have a hash suffix to avoid duplication
        $this->assertMatchesRegularExpression("/case PRIORITY_SUPPORT_[a-f0-9]{6} = 'check_check';/", $capturedPhp);
    }
    public function test_should_escape_special_characters_in_values(): void
    {
        $capturedPhp = null;
        File::shouldReceive('put')
            ->once()
            ->andReturnUsing(function ($path, $php) use (&$capturedPhp) {
                $capturedPhp = $php;
                return true;
            });

        $this->artisan('cashier:generate-feature-enum', ['--force' => true])
            ->assertExitCode(0);

        // Should properly escape special characters
        $this->assertStringContainsString("case MYNAME_ISCASHIER = 'some-uuid';", $capturedPhp);
    }
}
