<?php

namespace Dominservice\Conversations\Tests\Unit;

use Dominservice\Conversations\Conversations;
use Dominservice\Conversations\Models\Eloquent\Conversation;
use Dominservice\Conversations\Models\Eloquent\ConversationMessage;
use Dominservice\Conversations\Tests\Models\User;
use Dominservice\Conversations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class ConversationsTest extends TestCase
{
    use RefreshDatabase;

    protected $conversations;
    protected $user1;
    protected $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conversations = new Conversations();
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
    public function it_can_create_a_conversation()
    {
        // Mock Auth user for this specific test
        Auth::shouldReceive('user')->andReturn($this->user1);

        $users = [$this->user1->id, $this->user2->id];
        $conversationId = $this->conversations->create($users);

        $this->assertNotFalse($conversationId);
        $this->assertDatabaseHas('conversations', ['uuid' => $conversationId]);

        // Check if users are associated with the conversation
        $this->assertDatabaseHas('conversation_users', [
            'conversation_uuid' => $conversationId,
            'user_id' => $this->user1->id
        ]);

        $this->assertDatabaseHas('conversation_users', [
            'conversation_uuid' => $conversationId,
            'user_id' => $this->user2->id
        ]);
    }

    /** @test */
    public function it_can_add_a_message_to_a_conversation()
    {
        // Mock Auth user for this specific test
        Auth::shouldReceive('user')->andReturn($this->user1);

        $users = [$this->user1->id, $this->user2->id];
        $conversationId = $this->conversations->create($users);

        $content = 'Test message content';
        $messageId = $this->conversations->addMessage($conversationId, $content);

        $this->assertNotFalse($messageId);
        $this->assertDatabaseHas('conversation_messages', [
            'id' => $messageId,
            'conversation_uuid' => $conversationId,
            'content' => $content
        ]);

        // Check if message statuses are created for both users
        $this->assertDatabaseHas('conversation_message_statuses', [
            'message_id' => $messageId,
            'user_id' => $this->user1->id,
            'status' => Conversations::READ // Sender's message is marked as read
        ]);

        $this->assertDatabaseHas('conversation_message_statuses', [
            'message_id' => $messageId,
            'user_id' => $this->user2->id,
            'status' => Conversations::UNREAD // Recipient's message is marked as unread
        ]);
    }

    /** @test */
    public function it_can_get_conversations_for_a_user()
    {
        // Mock Auth user for this specific test
        Auth::shouldReceive('user')->andReturn($this->user1);

        $users = [$this->user1->id, $this->user2->id];
        $conversationId = $this->conversations->create($users);

        $content = 'Test message content';
        $this->conversations->addMessage($conversationId, $content);

        $conversations = $this->conversations->getConversations($this->user1->id);

        $this->assertNotEmpty($conversations);
        $this->assertEquals(1, count($conversations));
        $this->assertEquals($conversationId, $conversations->first()->uuid);
    }

    /** @test */
    public function it_can_get_messages_for_a_conversation()
    {
        // Mock Auth user for this specific test
        Auth::shouldReceive('user')->andReturn($this->user1);

        $users = [$this->user1->id, $this->user2->id];
        $conversationId = $this->conversations->create($users);

        $content1 = 'Test message 1';
        $content2 = 'Test message 2';
        $this->conversations->addMessage($conversationId, $content1);
        $this->conversations->addMessage($conversationId, $content2);

        $messages = $this->conversations->getMessages($conversationId, $this->user1->id);

        $this->assertNotEmpty($messages);
        $this->assertEquals(2, count($messages));
        $this->assertEquals($content1, $messages[0]->content);
        $this->assertEquals($content2, $messages[1]->content);
    }

    /** @test */
    public function it_can_mark_messages_as_read()
    {
        // Mock Auth user for this specific test
        Auth::shouldReceive('user')->andReturn($this->user1);

        $users = [$this->user1->id, $this->user2->id];
        $conversationId = $this->conversations->create($users);

        // User 1 sends a message to User 2
        $content = 'Test message from User 1';
        $messageId = $this->conversations->addMessage($conversationId, $content);

        // Switch to User 2
        Auth::shouldReceive('user')->andReturn($this->user2);

        // Mark the message as read
        $this->conversations->markAsRead($conversationId, $messageId, $this->user2->id);

        // Check if the message is marked as read for User 2
        $this->assertDatabaseHas('conversation_message_statuses', [
            'message_id' => $messageId,
            'user_id' => $this->user2->id,
            'status' => Conversations::READ
        ]);
    }
}
