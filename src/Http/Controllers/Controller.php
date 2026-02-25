<?php

namespace Dominservice\Conversations\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller as BaseController;
use Throwable;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Resolve current authenticated user primary key.
     *
     * @return mixed
     */
    protected function resolveCurrentUserId()
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return $user->{$user->getKeyName()};
    }

    /**
     * Execute configured integration callback.
     *
     * @param string $configPath
     * @param array<string, mixed> $context
     * @param mixed $default
     * @return mixed
     */
    protected function executeIntegrationCallback(string $configPath, array $context = [], $default = null)
    {
        $callback = config($configPath);
        if (empty($callback)) {
            return $default;
        }

        try {
            if (is_string($callback) && class_exists($callback) && !str_contains($callback, '@') && !str_contains($callback, '::')) {
                $instance = app($callback);
                if (is_callable($instance)) {
                    return $instance(...array_values($context));
                }
            }

            return app()->call($callback, $context);
        } catch (Throwable $e) {
            Log::warning('Conversations integration callback failed.', [
                'config_path' => $configPath,
                'error' => $e->getMessage(),
            ]);

            return $default;
        }
    }

    /**
     * @param string $event
     * @param array<string, mixed> $payload
     * @return void
     */
    protected function dispatchBusinessNotification(string $event, array $payload = []): void
    {
        if (!(bool) config('conversations.integrations.business_notifications.enabled', false)) {
            return;
        }

        $this->executeIntegrationCallback(
            'conversations.integrations.business_notifications.dispatcher',
            [
                'event' => $event,
                'payload' => $payload,
            ]
        );
    }
}
