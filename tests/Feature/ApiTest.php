<?php

namespace Dominservice\Conversations\Tests\Feature;

use Dominservice\Conversations\Tests\TestCase;
use Dominservice\Conversations\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        // Enable API
        Config::set('conversations.api.enabled', true);

        // Use web middleware for testing instead of api
        Config::set('conversations.api.middleware', ['web', 'auth']);
    }

    public function testGetConversations()
    {
        $this->actingAs($this->user)
            ->get('/api/conversations')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function testCreateConversation()
    {
        $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['uuid', 'owner_uuid', 'created_at', 'updated_at'],
                'message'
            ]);
    }

    public function testGetConversation()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['uuid', 'owner_uuid', 'created_at', 'updated_at', 'users']
            ]);
    }

    public function testGetMessages()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function testAddMessage()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        $this->actingAs($this->user)
            ->post("/api/conversations/{$conversationUuid}/messages", [
                'content' => 'This is a reply',
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data',
                'message'
            ]);
    }

    public function testMarkMessageAsRead()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        // Get the message
        $messagesResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages");

        $messageId = $messagesResponse->json('data.0.message_id');

        $this->actingAs($this->user)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId}/read")
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Message marked as read'
            ]);
    }

    public function testUnauthorizedAccess()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        // Create a third user who is not part of the conversation
        $thirdUser = User::create([
            'name' => 'Third User',
            'email' => 'third@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($thirdUser)
            ->get("/api/conversations/{$conversationUuid}")
            ->assertStatus(403);
    }

    public function testDeleteConversation()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        $this->actingAs($this->user)
            ->delete("/api/conversations/{$conversationUuid}")
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Conversation deleted successfully'
            ]);
    }

    public function testTypingIndicator()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        $this->actingAs($this->user)
            ->post("/api/conversations/{$conversationUuid}/typing", [
                'user_name' => 'Test User',
            ])
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Typing indicator sent'
            ]);
    }

    public function testAddMessageWithAttachment()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        // Create a fake file for testing
        $file = \Illuminate\Http\UploadedFile::fake()->image('test-image.jpg', 100, 100);

        // Add a message with an attachment
        $messageResponse = $this->actingAs($this->user)
            ->post("/api/conversations/{$conversationUuid}/messages", [
                'content' => 'Check out this image!',
                'attachments' => [$file],
            ]);

        $messageResponse->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'conversation_uuid', 'content', 'message_type', 'attachments'],
                'message'
            ]);

        // Verify the message type is 'attachment'
        $this->assertEquals('attachment', $messageResponse->json('data.message_type'));

        // Get the message ID
        $messageId = $messageResponse->json('data.id');

        // Get the attachments for the message
        $attachmentsResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages/{$messageId}/attachments");

        $attachmentsResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'message_id', 'filename', 'original_filename', 
                        'mime_type', 'extension', 'type', 'size', 'path', 
                        'is_optimized', 'is_scanned', 'is_safe'
                    ]
                ]
            ]);

        // Verify the attachment type is 'image'
        $this->assertEquals('image', $attachmentsResponse->json('data.0.type'));
    }
}
