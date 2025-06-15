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
 * @version   3.0.0
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
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/conversations.php' => config_path('conversations.php'),
        ], 'config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/create_conversations_tables.php.stub' => $this->getMigrationFileName($filesystem, 'create_conversations_tables'),
            __DIR__.'/../database/migrations/create_conversation_relations_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_conversation_relations_table'),
            __DIR__.'/../database/migrations/create_conversation_types_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_conversation_types_table'),
            __DIR__.'/../database/migrations/column_add_conversation_table.php.stub' => $this->getMigrationFileName($filesystem, 'column_add_conversation_table'),
            __DIR__.'/../database/migrations/column_add_conversation_message_statuses_table.php.stub' => $this->getMigrationFileName($filesystem, 'column_add_conversation_message_statuses_table'),
            __DIR__.'/../database/migrations/column_add_conversation_relations_table.php.stub' => $this->getMigrationFileName($filesystem, 'column_add_conversation_relations_table'),
            __DIR__.'/../database/migrations/add_attachment_support_to_conversation_messages_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_attachment_support_to_conversation_messages_table'),
            __DIR__.'/../database/migrations/create_conversation_type_translations_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_conversation_type_translations_table'),
        ], 'migrations');

        // Publish translations
        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/conversations'),
        ], 'translations');

        // Publish routes
        $this->publishes([
            __DIR__ . '/Http/routes.php' => base_path('routes/conversation-api.php'),
        ], 'routes');

        // Load translations
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'conversations');
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

        // Register the HookManager
        $this->app->singleton(Hooks\HookManager::class, function ($app) {
            return new Hooks\HookManager();
        });

        // Register the Conversations class as a singleton
        $this->app->singleton('conversations', function ($app) {
            return new Conversations($app->make('conversations.broadcasting'));
        });

        // Register the AttachmentService
        $this->app->singleton('Dominservice\Conversations\Services\AttachmentService', function ($app) {
            return new Services\AttachmentService();
        });

        // Register API routes if enabled
        if (config('conversations.api.enabled', true)) {
            $this->registerRoutes();
        }

        // Register required packages
        $this->registerRequiredPackages();
	}

    /**
     * Register the package routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        // Check if the routes have been published
        $publishedRoutesPath = base_path('routes/conversation-api.php');

        if (file_exists($publishedRoutesPath)) {
            // If published routes exist, load them
            $this->loadRoutesFrom($publishedRoutesPath);
        } else {
            // Otherwise, load the package routes
            $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        }
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

    /**
     * Register required packages for attachment handling.
     *
     * @return void
     */
    protected function registerRequiredPackages()
    {
        // Check if Intervention/Image is installed
        if (!class_exists('Intervention\Image\Facades\Image')) {
            // We can't install packages programmatically, but we can show a warning
            if (PHP_SAPI === 'cli') {
                $this->warn('The Intervention/Image package is required for image optimization.');
                $this->warn('Please install it using: composer require intervention/image');
            } else {
                // Log a warning
                \Log::warning('The Intervention/Image package is required for image optimization in the Conversations package.');
            }
        }
    }

}
