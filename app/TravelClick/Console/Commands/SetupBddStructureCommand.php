<?php

namespace App\TravelClick\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupBddStructureCommand extends Command
{
    protected $signature = 'travelclick:setup-bdd
                          {--force : Force overwrite existing files}
                          {--with-samples : Include sample feature files}';

    protected $description = 'Set up BDD testing structure for TravelClick integration';

    protected array $directories = [
        'tests/Behat',
        'tests/Behat/features',
        'tests/Behat/features/outbound',
        'tests/Behat/features/inbound',
        'tests/Behat/contexts',
        'tests/Behat/fixtures',
        'tests/Behat/fixtures/xml_samples',
        'tests/Behat/fixtures/xml_samples/inventory',
        'tests/Behat/fixtures/xml_samples/rates',
        'tests/Behat/fixtures/xml_samples/reservations',
        'tests/Behat/fixtures/xml_samples/groups',
        'tests/Behat/fixtures/responses',
        'tests/Behat/support',
        'storage/testing',
        'storage/testing/wiremock',
        'storage/testing/logs',
    ];

    public function handle(): int
    {
        $this->info('Setting up BDD structure for TravelClick integration...');

        if (!$this->createDirectories()) {
            return Command::FAILURE;
        }

        if (!$this->createBehatConfiguration()) {
            return Command::FAILURE;
        }

        if (!$this->createBootstrapFile()) {
            return Command::FAILURE;
        }

        if (!$this->createBasicContexts()) {
            return Command::FAILURE;
        }

        if ($this->option('with-samples')) {
            $this->createSampleFeatures();
        }

        $this->createMakefile();
        $this->createDockerCompose();

        $this->newLine();
        $this->info('✅ BDD structure created successfully!');
        $this->info('Next steps:');
        $this->line('1. Run: php artisan travelclick:import-samples');
        $this->line('2. Run: make setup (to install WireMock)');
        $this->line('3. Run: make test-all (to run BDD tests)');

        return Command::SUCCESS;
    }

    protected function createDirectories(): bool
    {
        foreach ($this->directories as $directory) {
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
                $this->info("Created directory: {$directory}");
            }
        }

        return true;
    }

    protected function createBehatConfiguration(): bool
    {
        $configPath = 'behat.yml';

        if (File::exists($configPath) && !$this->option('force')) {
            $this->warn("Behat configuration already exists. Use --force to overwrite.");
            return true;
        }

        $config = $this->getBehatConfiguration();
        File::put($configPath, $config);
        $this->info("Created: {$configPath}");

        return true;
    }

    protected function createBootstrapFile(): bool
    {
        $bootstrapPath = 'tests/Behat/bootstrap.php';

        if (File::exists($bootstrapPath) && !$this->option('force')) {
            $this->warn("Bootstrap file already exists. Use --force to overwrite.");
            return true;
        }

        $bootstrap = $this->getBootstrapContent();
        File::put($bootstrapPath, $bootstrap);
        $this->info("Created: {$bootstrapPath}");

        return true;
    }

    protected function createBasicContexts(): bool
    {
        $this->info('Basic context creation will be handled by separate commands.');
        $this->line('Run: php artisan travelclick:generate-contexts');

        return true;
    }

    protected function createSampleFeatures(): void
    {
        $this->info('Sample feature creation will be handled by separate commands.');
        $this->line('Run: php artisan travelclick:generate-features');
    }

    protected function createMakefile(): void
    {
        $this->info('Makefile creation will be handled by separate commands.');
        $this->line('Run: php artisan travelclick:generate-makefile');
    }

    protected function createDockerCompose(): void
    {
        $this->info('Docker compose creation will be handled by separate commands.');
        $this->line('Run: php artisan travelclick:generate-docker');
    }

    protected function getBehatConfiguration(): string
    {
        return <<<'YAML'
default:
  suites:
    travelclick_outbound:
      paths:
        - '%paths.base%/tests/Behat/features/outbound'
      contexts:
        - Tests\\Behat\\Contexts\\TravelClickOutboundContext
        - Tests\\Behat\\Contexts\\DatabaseContext
        - Tests\\Behat\\Contexts\\WireMockContext
      filters:
        tags: '@outbound'

    travelclick_inbound:
      paths:
        - '%paths.base%/tests/Behat/features/inbound'
      contexts:
        - Tests\\Behat\\Contexts\\TravelClickInboundContext
        - Tests\\Behat\\Contexts\\DatabaseContext
        - Tests\\Behat\\Contexts\\WireMockContext
      filters:
        tags: '@inbound'

  extensions:
    FriendsOfBehat\\SymfonyExtension:
      bootstrap: tests/Behat/bootstrap.php
      kernel:
        class: App\\Http\\Kernel
        path: app/Http/Kernel.php
        environment: testing
        debug: true

  formatters:
    pretty:
      verbose: true
      paths: false
      snippets: false
YAML;
    }

    protected function getBootstrapContent(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

require_once __DIR__.'/../../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
*/

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__, 2)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Set Testing Environment
|--------------------------------------------------------------------------
*/

$app->detectEnvironment(function () {
    return 'testing';
});

/*
|--------------------------------------------------------------------------
| Bootstrap The Application
|--------------------------------------------------------------------------
*/

$app->make(Kernel::class)->bootstrap();

return $app;
PHP;
    }
}
