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
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

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
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
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
    }
}
