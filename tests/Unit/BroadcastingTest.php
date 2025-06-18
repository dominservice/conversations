<?php

namespace Dominservice\Conversations\Tests\Unit;

use Dominservice\Conversations\Broadcasting\BroadcastManager;
use Dominservice\Conversations\Broadcasting\Drivers\NullDriver;
use Dominservice\Conversations\Conversations;
use Dominservice\Conversations\Events\MessageSent;
use Dominservice\Conversations\Events\UserTyping;
use Dominservice\Conversations\Facade\ConversationsBroadcasting;
use Dominservice\Conversations\Models\Eloquent\ConversationMessage;
use Dominservice\Conversations\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Mockery;

class BroadcastingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable broadcasting for tests
        Config::set('conversations.broadcasting.enabled', true);
        Config::set('conversations.broadcasting.driver', 'null');
    }

    public function testBroadcastManagerIsRegistered()
    {
        $this->assertInstanceOf(
            BroadcastManager::class,
            $this->app->make('conversations.broadcasting')
        );
    }

    public function testBroadcastManagerUsesConfiguredDriver()
    {
        $manager = $this->app->make('conversations.broadcasting');
        $this->assertInstanceOf(NullDriver::class, $manager->driver());
    }

    public function testFacadeWorks()
    {
        // Explicitly set the configuration before calling the method
        Config::set('conversations.broadcasting.enabled', true);
        $this->assertTrue(ConversationsBroadcasting::enabled());
    }

    public function testBroadcastingIsDisabledWhenConfigured()
    {
        Config::set('conversations.broadcasting.enabled', false);
        $this->assertFalse(ConversationsBroadcasting::enabled());
    }

    public function testConversationsClassUsesBroadcastManager()
    {
        $mockBroadcastManager = Mockery::mock(BroadcastManager::class);
        $mockBroadcastManager->shouldReceive('enabled')->andReturn(true);
        $mockBroadcastManager->shouldReceive('broadcast')->once();

        $conversations = new Conversations($mockBroadcastManager);

        // Create a mock message
        $message = new ConversationMessage();
        $message->id = 1;
        $message->conversation_uuid = 'test-uuid';
        $message->content = 'Test message';

        // Call the method that should broadcast an event
        $conversations->broadcastUserTyping('test-uuid');

        // Verify that the broadcast method was called on the mock
        Mockery::close();
    }

    public function testUserTypingEventIsBroadcast()
    {
        $mockBroadcastManager = Mockery::mock(BroadcastManager::class);
        $mockBroadcastManager->shouldReceive('enabled')->andReturn(true);
        $mockBroadcastManager->shouldReceive('broadcast')
            ->once()
            ->with(Mockery::type(UserTyping::class));

        $conversations = new Conversations($mockBroadcastManager);
        $conversations->broadcastUserTyping('test-uuid', 'user-123', 'Test User');

        Mockery::close();
    }
}
