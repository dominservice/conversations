<?php

namespace Dominservice\Conversations\Tests\Unit;

use Dominservice\Conversations\Hooks\HookManager;
use Dominservice\Conversations\Facade\ConversationsHooks;
use Dominservice\Conversations\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class HooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear all hooks before each test
        Config::set('conversations.hooks', []);
    }

    public function testHookManagerCanExecuteHooks()
    {
        $executed = false;

        Config::set('conversations.hooks.test_hook', [
            function () use (&$executed) {
                $executed = true;
            }
        ]);

        app(HookManager::class)->execute('test_hook');

        $this->assertTrue($executed);
    }

    public function testHookManagerCanExecuteMultipleHooks()
    {
        $count = 0;

        Config::set('conversations.hooks.test_hook', [
            function () use (&$count) {
                $count++;
            },
            function () use (&$count) {
                $count++;
            }
        ]);

        app(HookManager::class)->execute('test_hook');

        $this->assertEquals(2, $count);
    }

    public function testHookManagerCanPassParameters()
    {
        $receivedParams = null;

        Config::set('conversations.hooks.test_hook', [
            function ($params) use (&$receivedParams) {
                $receivedParams = $params;
            }
        ]);

        $params = ['key' => 'value'];
        app(HookManager::class)->execute('test_hook', [$params]);

        $this->assertEquals($params, $receivedParams);
    }

    public function testHookManagerCanRegisterHooks()
    {
        $executed = false;

        app(HookManager::class)->register('test_hook', function () use (&$executed) {
            $executed = true;
        });

        app(HookManager::class)->execute('test_hook');

        $this->assertTrue($executed);
    }

    public function testHookManagerCanRegisterMultipleHooks()
    {
        $count = 0;

        app(HookManager::class)->registerMany('test_hook', [
            function () use (&$count) {
                $count++;
            },
            function () use (&$count) {
                $count++;
            }
        ]);

        app(HookManager::class)->execute('test_hook');

        $this->assertEquals(2, $count);
    }

    public function testHookManagerCanClearHooks()
    {
        $executed = false;

        app(HookManager::class)->register('test_hook', function () use (&$executed) {
            $executed = true;
        });

        app(HookManager::class)->clear('test_hook');
        app(HookManager::class)->execute('test_hook');

        $this->assertFalse($executed);
    }

    public function testHookManagerCanClearAllHooks()
    {
        $executed1 = false;
        $executed2 = false;

        app(HookManager::class)->register('test_hook1', function () use (&$executed1) {
            $executed1 = true;
        });

        app(HookManager::class)->register('test_hook2', function () use (&$executed2) {
            $executed2 = true;
        });

        app(HookManager::class)->clearAll();

        app(HookManager::class)->execute('test_hook1');
        app(HookManager::class)->execute('test_hook2');

        $this->assertFalse($executed1);
        $this->assertFalse($executed2);
    }

    public function testFacadeWorks()
    {
        $executed = false;

        ConversationsHooks::register('test_hook', function () use (&$executed) {
            $executed = true;
        });

        ConversationsHooks::execute('test_hook');

        $this->assertTrue($executed);
    }

    public function testHookCanAbortOperation()
    {
        $secondHookExecuted = false;

        ConversationsHooks::register('test_hook', function () {
            return false; // Abort
        });

        ConversationsHooks::register('test_hook', function () use (&$secondHookExecuted) {
            $secondHookExecuted = true;
        });

        $result = ConversationsHooks::execute('test_hook');

        $this->assertFalse($result);
        $this->assertFalse($secondHookExecuted);
    }

    public function testHookCanExecuteClassMethod()
    {
        // Mock a class with a method
        $mock = \Mockery::mock('TestHookClass');
        $mock->shouldReceive('handleHook')->once()->andReturn(true);

        // Bind the mock to the container
        $this->app->instance('TestHookClass', $mock);

        // Register the hook with a class@method string
        Config::set('conversations.hooks.test_hook', [
            'TestHookClass@handleHook'
        ]);

        app(HookManager::class)->execute('test_hook');

        // Mockery will assert that the method was called
        \Mockery::close();
    }
}
