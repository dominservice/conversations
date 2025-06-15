<?php

namespace Dominservice\Conversations\Tests\Feature;

use Dominservice\Conversations\Conversations;
use Dominservice\Conversations\Facade\Conversations as ConversationsFacade;
use Dominservice\Conversations\Tests\Models\User;
use Dominservice\Conversations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class FacadeTest extends TestCase
{
    use RefreshDatabase;

    protected $user1;
    protected $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = $this->createMockUser(1);
        $this->user2 = $this->createMockUser(2);

        // Set up Auth facade mock
        $this->mockAuth();
    }

    /**
     * Mock the Auth facade
     */
    protected function mockAuth()
    {
        Auth::shouldReceive('user')
            ->andReturn($this->user1);

        Auth::shouldReceive('check')
            ->andReturn(true);
    }

    /** @test */
    public function it_can_create_conversation_using_facade()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = ConversationsFacade::create($users);

        $this->assertNotFalse($conversationId);
        $this->assertDatabaseHas('conversations', ['uuid' => $conversationId]);
    }

    /** @test */
    public function it_can_add_message_using_facade()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = ConversationsFacade::create($users);

        $content = 'Test message content';
        $messageId = ConversationsFacade::addMessage($conversationId, $content);

        $this->assertNotFalse($messageId);
        $this->assertDatabaseHas('conversation_messages', [
            'id' => $messageId,
            'conversation_uuid' => $conversationId,
            'content' => $content
        ]);
    }

    /** @test */
    public function it_can_get_conversations_using_facade()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = ConversationsFacade::create($users);

        $content = 'Test message content';
        ConversationsFacade::addMessage($conversationId, $content);

        $conversations = ConversationsFacade::getConversations($this->user1->id);

        $this->assertNotEmpty($conversations);
        $this->assertEquals(1, count($conversations));
        $this->assertEquals($conversationId, $conversations->first()->uuid);
    }

    /** @test */
    public function it_can_mark_messages_as_read_using_facade()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = ConversationsFacade::create($users);

        // User 1 sends a message to User 2
        $content = 'Test message from User 1';
        $messageId = ConversationsFacade::addMessage($conversationId, $content);

        // Switch to User 2
        Auth::shouldReceive('user')->andReturn($this->user2);

        // Mark the message as read
        ConversationsFacade::markAsRead($conversationId, $messageId, $this->user2->id);

        // Check if the message is marked as read for User 2
        $this->assertDatabaseHas('conversation_message_statuses', [
            'message_id' => $messageId,
            'user_id' => $this->user2->id,
            'status' => Conversations::READ
        ]);
    }
}
