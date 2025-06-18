<?php

namespace Dominservice\Conversations\Tests;

use Dominservice\Conversations\ConversationsServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Run the migrations
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadMigrationStubs();

        // Create test users
        $this->createTestUsers();
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadMigrationStubs();
    }

    /**
     * Load migration stubs as actual migrations.
     *
     * @return void
     */
    protected function loadMigrationStubs()
    {
        // Get all migration stubs from the package
        $migrationStubs = glob(__DIR__ . '/../database/migrations/*.stub');

        // Create a temporary directory for migrations if it doesn't exist
        $tempMigrationsDir = __DIR__ . '/temp_migrations';
        if (!is_dir($tempMigrationsDir)) {
            mkdir($tempMigrationsDir, 0755, true);
        } else {
            // Clean up old migration files
            $oldFiles = glob($tempMigrationsDir . '/*.php');
            foreach ($oldFiles as $file) {
                unlink($file);
            }
        }

        // Process each stub file with proper timestamps to ensure correct order
        $timestamp = date('Y_m_d_His');
        $i = 0;

        // First create the base tables
        foreach (['create_conversations_tables', 'create_conversation_types_table', 'create_conversation_relations_table'] as $baseTable) {
            foreach ($migrationStubs as $stub) {
                if (strpos($stub, $baseTable) !== false) {
                    $filename = basename($stub, '.stub');
                    $incrementedTimestamp = date('Y_m_d_Hi') . str_pad($i++, 2, '0', STR_PAD_LEFT);
                    $targetFile = $tempMigrationsDir . '/' . $incrementedTimestamp . '_' . $filename . '.php';

                    // Read the stub content
                    $content = file_get_contents($stub);

                    // Write to the target file
                    file_put_contents($targetFile, $content);
                }
            }
        }

        // Then add columns and updates
        foreach ($migrationStubs as $stub) {
            if (strpos($stub, 'create_conversations_tables') === false && 
                strpos($stub, 'create_conversation_types_table') === false && 
                strpos($stub, 'create_conversation_relations_table') === false) {

                $filename = basename($stub, '.stub');
                $incrementedTimestamp = date('Y_m_d_Hi') . str_pad($i++, 2, '0', STR_PAD_LEFT);
                $targetFile = $tempMigrationsDir . '/' . $incrementedTimestamp . '_' . $filename . '.php';

                // Read the stub content
                $content = file_get_contents($stub);

                // Write to the target file
                file_put_contents($targetFile, $content);
            }
        }

        // Load the migrations from the temporary directory
        $this->loadMigrationsFrom($tempMigrationsDir);
    }

    /**
     * Create test users for the tests.
     */
    protected function createTestUsers()
    {
        // Create test users
        \Dominservice\Conversations\Tests\Models\User::create([
            'name' => 'Test User 1',
            'email' => 'test1@example.com',
            'password' => bcrypt('password'),
        ]);

        \Dominservice\Conversations\Tests\Models\User::create([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /**
     * Create a mock user for testing.
     *
     * @param int $id
     * @return \Mockery\MockInterface
     */
    protected function createMockUser($id = 1)
    {
        $user = \Mockery::mock('Dominservice\Conversations\Tests\Models\User');
        $user->shouldReceive('getKeyName')->andReturn('id');
        $user->shouldReceive('getKeyType')->andReturn('int');
        $user->shouldReceive('setAttribute')->andReturnSelf();
        $user->shouldReceive('__get')->with('id')->andReturn($id);
        $user->shouldReceive('__isset')->with('id')->andReturn(true);
        $user->shouldReceive('getAttribute')->with('id')->andReturn($id);
        $user->shouldReceive('getAttribute')->andReturn(null);
        $user->id = $id;

        return $user;
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ConversationsServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Set up conversations config
        $app['config']->set('conversations.user_model', \Dominservice\Conversations\Tests\Models\User::class);
        $app['config']->set('conversations.user_primary_key_type', 'int');
        $app['config']->set('conversations.user_primary_key', 'id');

        // Set API middleware to web only for testing
        $app['config']->set('conversations.api.middleware', ['web']);

        // Set up translatable config
        $app['config']->set('translatable.locales', ['en', 'es', 'fr']);
        $app['config']->set('translatable.locale', 'en');
        $app['config']->set('translatable.fallback_locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');

        // Set up auth config for API tests
        $app['config']->set('auth.guards.api', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => \Dominservice\Conversations\Tests\Models\User::class,
        ]);

        // Define a login route for testing
        \Illuminate\Support\Facades\Route::get('login', function () {
            return 'login';
        })->name('login');
    }
}
