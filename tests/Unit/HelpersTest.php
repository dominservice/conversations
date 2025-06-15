<?php

namespace Dominservice\Conversations\Tests\Unit;

use Dominservice\Conversations\Conversations;
use Dominservice\Conversations\Tests\Models\User;
use Dominservice\Conversations\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class HelpersTest extends TestCase
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
    public function it_can_create_conversation_using_helper()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = conversation_create($users);

        $this->assertNotFalse($conversationId);
        $this->assertDatabaseHas('conversations', ['uuid' => $conversationId]);
    }

    /** @test */
    public function it_can_add_message_using_helper()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = conversation_create($users);

        $content = 'Test message content';
        $messageId = conversation_add_message($conversationId, $content);

        $this->assertNotFalse($messageId);
        $this->assertDatabaseHas('conversation_messages', [
            'id' => $messageId,
            'conversation_uuid' => $conversationId,
            'content' => $content
        ]);
    }

    /** @test */
    public function it_can_add_message_or_create_conversation_using_helper()
    {
        $users = [$this->user1->id, $this->user2->id];
        $content = 'Test message content';

        $messageId = conversation_add_or_create($users, $content);

        $this->assertNotFalse($messageId);
        $this->assertDatabaseHas('conversation_messages', [
            'id' => $messageId,
            'content' => $content
        ]);
    }

    /** @test */
    public function it_can_check_if_user_exists_in_conversation_using_helper()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = conversation_create($users);

        $exists = conversation_user_exists($conversationId, $this->user1->id);

        $this->assertTrue($exists);
    }

    /** @test */
    public function it_can_get_conversation_id_between_users_using_helper()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = conversation_create($users);

        $foundId = conversation_id_between($users);

        $this->assertEquals($conversationId, $foundId);
    }

    /** @test */
    public function it_can_get_unread_count_using_helper()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = conversation_create($users);

        // User 1 sends a message to User 2
        $content = 'Test message from User 1';
        conversation_add_message($conversationId, $content);

        // Switch to User 2 to check unread count
        Auth::shouldReceive('user')->andReturn($this->user2);

        $unreadCount = conversation_unread_count($this->user2->id);

        $this->assertEquals(1, $unreadCount);
    }

    /** @test */
    public function it_can_get_conversation_unread_count_using_helper()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = conversation_create($users);

        // User 1 sends a message to User 2
        $content = 'Test message from User 1';
        conversation_add_message($conversationId, $content);

        // Switch to User 2 to check unread count
        Auth::shouldReceive('user')->andReturn($this->user2);

        $unreadCount = conversation_unread_count_per_id($conversationId, $this->user2->id);

        $this->assertEquals(1, $unreadCount);
    }

    /** @test */
    public function it_can_mark_message_as_read_using_helper()
    {
        $users = [$this->user1->id, $this->user2->id];
        $conversationId = conversation_create($users);

        // User 1 sends a message to User 2
        $content = 'Test message from User 1';
        $messageId = conversation_add_message($conversationId, $content);

        // Switch to User 2
        Auth::shouldReceive('user')->andReturn($this->user2);

        // Mark the message as read
        conversation_mark_as_read($conversationId, $messageId, $this->user2->id);

        // Check if the message is marked as read for User 2
        $this->assertDatabaseHas('conversation_message_statuses', [
            'message_id' => $messageId,
            'user_id' => $this->user2->id,
            'status' => Conversations::READ
        ]);
    }
}
