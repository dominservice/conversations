<?php

/**
 * Conversations
 *
 * This package will allow you to add a full user messaging system
 * into your Laravel application.
 *
 * @package   Dominservice\Conversations
 * @author    DSO-IT Mateusz Domin <biuro@dso.biz.pl>
 * @copyright (c) 2021 DSO-IT Mateusz Domin
 * @license   MIT
 * @version   1.0.0
 */

namespace Dominservice\Conversations;


use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

/**
 * Class ConversationsServiceProvider
 * @package Dominservice\Conversations
 */
class ConversationsServiceProvider extends ServiceProvider
{

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

    private  $lpMigration = 0;

    public function boot(Filesystem $filesystem) {
        $this->publishes([
            __DIR__ . '/../config/conversations.php' => config_path('conversations.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_conversations_tables.php.stub' => $this->getMigrationFileName($filesystem, 'create_conversations_tables'),
            __DIR__.'/../database/migrations/create_conversation_relations_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_conversation_relations_table'),
            __DIR__.'/../database/migrations/create_conversation_types_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_conversation_types_table'),
            __DIR__.'/../database/migrations/column_add_conversation_table.php.stub' => $this->getMigrationFileName($filesystem, 'column_add_conversation_table'),
            __DIR__.'/../database/migrations/column_add_conversation_message_statuses_table.php.stub' => $this->getMigrationFileName($filesystem, 'column_add_conversation_message_statuses_table'),
            __DIR__.'/../database/migrations/column_add_conversation_relations_table.php.stub' => $this->getMigrationFileName($filesystem, 'column_add_conversation_relations_table'),
        ], 'migrations');
    }

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
        $this->mergeConfigFrom(
            __DIR__.'/../config/conversations.php',
            'conversations'
        );

        // Register the BroadcastManager as a singleton
        $this->app->singleton('conversations.broadcasting', function ($app) {
            return new Broadcasting\BroadcastManager($app, $app->make(\Illuminate\Broadcasting\BroadcastManager::class));
        });

        // Register the Conversations class as a singleton
        $this->app->singleton('conversations', function ($app) {
            return new Conversations($app->make('conversations.broadcasting'));
        });
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @param Filesystem $filesystem
     * @return string
     */
    protected function getMigrationFileName(Filesystem $filesystem, $name): string
    {
        $this->lpMigration++;
        $timestamp = date('Y_m_d_Hi'.str_pad($this->lpMigration, 2, "0", STR_PAD_LEFT));

        return Collection::make($this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem, $name) {
                return $filesystem->glob($path.'*'.$name.'.php');
            })->push($this->app->databasePath()."/migrations/{$timestamp}_{$name}.php")
            ->first();
    }

}
