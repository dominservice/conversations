<?php

namespace Dominservice\Conversations\GraphQL;

use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\Filesystem;

class GraphQLServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Filesystem $filesystem)
    {
        // Publish GraphQL schema files
        $this->publishes([
            __DIR__ . '/schema/' => base_path('graphql/conversations'),
        ], 'conversations-graphql');

        // Check if Lighthouse is installed
        if (class_exists('Nuwave\Lighthouse\LighthouseServiceProvider')) {
            // Register schema with Lighthouse
            $this->registerSchema($filesystem);
        }
    }

    /**
     * Register the GraphQL schema with Lighthouse.
     *
     * @param Filesystem $filesystem
     * @return void
     */
    protected function registerSchema(Filesystem $filesystem)
    {
        // Check if the schema directory exists in the application
        $schemaPath = base_path('graphql/conversations');
        
        if (!$filesystem->isDirectory($schemaPath)) {
            // Create the directory if it doesn't exist
            $filesystem->makeDirectory($schemaPath, 0755, true, true);
            
            // Copy the schema files to the application
            $filesystem->copyDirectory(__DIR__ . '/schema', $schemaPath);
        }
        
        // Add import to the main schema.graphql file if it exists
        $mainSchemaPath = base_path('graphql/schema.graphql');
        
        if ($filesystem->exists($mainSchemaPath)) {
            $content = $filesystem->get($mainSchemaPath);
            
            // Check if the import already exists
            if (strpos($content, '#import conversations/schema.graphql') === false) {
                // Add the import to the end of the file
                $content .= PHP_EOL . PHP_EOL . '#import conversations/schema.graphql';
                $filesystem->put($mainSchemaPath, $content);
            }
        }
    }
}