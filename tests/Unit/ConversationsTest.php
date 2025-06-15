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

    /** @test */
    public function it_can_get_users_who_read_a_message()
    {
        // Mock Auth user for this specific test
        Auth::shouldReceive('user')->andReturn($this->user1);

        // Create a third user
        $user3 = $this->createMockUser(3);

        // Create a conversation with all three users
        $users = [$this->user1->id, $this->user2->id, $user3->id];
        $conversationId = $this->conversations->create($users);

        // User 1 sends a message
        $content = 'Test message from User 1';
        $messageId = $this->conversations->addMessage($conversationId, $content);

        // User 2 reads the message
        $this->conversations->markAsRead($conversationId, $messageId, $this->user2->id);

        // User 3 reads the message
        $this->conversations->markAsRead($conversationId, $messageId, $user3->id);

        // Get users who read the message
        $readBy = $this->conversations->getMessageReadBy($messageId);

        // Check if the result contains User 2 and User 3 (but not User 1 who is the sender)
        $this->assertEquals(2, $readBy->count());
        $this->assertTrue($readBy->contains(function ($user) {
            return $user->id === $this->user2->id;
        }));
        $this->assertTrue($readBy->contains(function ($user) {
            return $user->id === 3; // User 3's ID
        }));
        $this->assertFalse($readBy->contains(function ($user) {
            return $user->id === $this->user1->id; // Sender should not be included
        }));
    }

    /** @test */
    public function it_can_get_conversation_read_by()
    {
        // Mock Auth user for this specific test
        Auth::shouldReceive('user')->andReturn($this->user1);

        // Create a third user
        $user3 = $this->createMockUser(3);

        // Create a conversation with all three users
        $users = [$this->user1->id, $this->user2->id, $user3->id];
        $conversationId = $this->conversations->create($users);

        // User 1 sends two messages
        $content1 = 'Test message 1 from User 1';
        $messageId1 = $this->conversations->addMessage($conversationId, $content1);

        $content2 = 'Test message 2 from User 1';
        $messageId2 = $this->conversations->addMessage($conversationId, $content2);

        // User 2 reads both messages
        $this->conversations->markAsRead($conversationId, $messageId1, $this->user2->id);
        $this->conversations->markAsRead($conversationId, $messageId2, $this->user2->id);

        // User 3 reads only the first message
        $this->conversations->markAsRead($conversationId, $messageId1, $user3->id);

        // Get conversation read by information
        $messagesWithReadStatus = $this->conversations->getConversationReadBy($conversationId);

        // Check if the result contains both messages with correct read status
        $this->assertEquals(2, $messagesWithReadStatus->count());

        // Find message 1 in the results
        $message1 = $messagesWithReadStatus->firstWhere('id', $messageId1);
        $this->assertNotNull($message1);
        $this->assertEquals(2, $message1['read_count']); // Both User 2 and User 3 read it
        $this->assertEquals(2, count($message1['read_by']));

        // Find message 2 in the results
        $message2 = $messagesWithReadStatus->firstWhere('id', $messageId2);
        $this->assertNotNull($message2);
        $this->assertEquals(1, $message2['read_count']); // Only User 2 read it
        $this->assertEquals(1, count($message2['read_by']));
    }
}
