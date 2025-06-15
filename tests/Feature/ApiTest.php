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

    public function testGetMessageReadBy()
    {
        // Create a conversation with a third user
        $thirdUser = User::create([
            'name' => 'Third User',
            'email' => 'third@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id, $thirdUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        // Get the message ID
        $messagesResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages");

        $messageId = $messagesResponse->json('data.0.message_id');

        // Mark the message as read by other users
        $this->actingAs($this->otherUser)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId}/read");

        $this->actingAs($thirdUser)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId}/read");

        // Get the read by information
        $readByResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages/{$messageId}/read-by");

        $readByResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'message_id',
                    'read_by',
                    'read_count'
                ]
            ]);

        // Verify that both users are in the read by list
        $this->assertEquals(2, $readByResponse->json('data.read_count'));
        $this->assertCount(2, $readByResponse->json('data.read_by'));
    }

    public function testGetConversationReadBy()
    {
        // Create a conversation with a third user
        $thirdUser = User::create([
            'name' => 'Third User',
            'email' => 'third@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id, $thirdUser->id],
                'content' => 'First message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        // Add a second message
        $this->actingAs($this->user)
            ->post("/api/conversations/{$conversationUuid}/messages", [
                'content' => 'Second message',
            ]);

        // Get the message IDs
        $messagesResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages");

        $messageId1 = $messagesResponse->json('data.0.message_id');
        $messageId2 = $messagesResponse->json('data.1.message_id');

        // Mark the first message as read by both users
        $this->actingAs($this->otherUser)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId1}/read");

        $this->actingAs($thirdUser)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId1}/read");

        // Mark the second message as read by only the second user
        $this->actingAs($this->otherUser)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId2}/read");

        // Get the conversation read by information
        $readByResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/read-by");

        $readByResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'content',
                        'sender_id',
                        'created_at',
                        'read_by',
                        'read_count'
                    ]
                ]
            ]);

        // Find the messages in the response
        $messages = $readByResponse->json('data');
        $message1 = null;
        $message2 = null;

        foreach ($messages as $message) {
            if ($message['id'] == $messageId1) {
                $message1 = $message;
            } elseif ($message['id'] == $messageId2) {
                $message2 = $message;
            }
        }

        // Verify the read counts
        $this->assertNotNull($message1);
        $this->assertNotNull($message2);
        $this->assertEquals(2, $message1['read_count']); // Both users read the first message
        $this->assertEquals(1, $message2['read_count']); // Only one user read the second message
    }

    public function testGetMessageReactions()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        // Get the message ID
        $messagesResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages");

        $messageId = $messagesResponse->json('data.0.message_id');

        // Add reactions to the message
        $this->actingAs($this->user)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId}/reactions", [
                'reaction' => '👍',
            ]);

        $this->actingAs($this->otherUser)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId}/reactions", [
                'reaction' => '❤️',
            ]);

        // Get the reactions for the message
        $reactionsResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages/{$messageId}/reactions");

        $reactionsResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'message_id',
                    'reactions',
                    'summary'
                ]
            ]);

        // Verify the reactions data
        $this->assertEquals($messageId, $reactionsResponse->json('data.message_id'));
        $this->assertCount(2, $reactionsResponse->json('data.reactions')); // Two reactions
        $this->assertCount(2, $reactionsResponse->json('data.summary')); // Two different emoji

        // Verify the summary contains the correct counts
        $summary = collect($reactionsResponse->json('data.summary'));
        $thumbsUp = $summary->firstWhere('reaction', '👍');
        $heart = $summary->firstWhere('reaction', '❤️');

        $this->assertEquals(1, $thumbsUp['count']); // One thumbs up
        $this->assertEquals(1, $heart['count']); // One heart
    }

    public function testAddReaction()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        // Get the message ID
        $messagesResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages");

        $messageId = $messagesResponse->json('data.0.message_id');

        // Add a reaction to the message
        $reactionResponse = $this->actingAs($this->user)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId}/reactions", [
                'reaction' => '👍',
            ]);

        $reactionResponse->assertStatus(201)
            ->assertJsonStructure([
                'data',
                'message'
            ]);

        // Verify the reaction was added to the database
        $this->assertDatabaseHas('conversation_message_reactions', [
            'message_id' => $messageId,
            'user_id' => $this->user->id,
            'reaction' => '👍'
        ]);
    }

    public function testRemoveReaction()
    {
        // Create a conversation first
        $response = $this->actingAs($this->user)
            ->post('/api/conversations', [
                'users' => [$this->otherUser->id],
                'content' => 'Hello, this is a test message',
            ]);

        $conversationUuid = $response->json('data.uuid');

        // Get the message ID
        $messagesResponse = $this->actingAs($this->user)
            ->get("/api/conversations/{$conversationUuid}/messages");

        $messageId = $messagesResponse->json('data.0.message_id');

        // Add a reaction to the message
        $this->actingAs($this->user)
            ->post("/api/conversations/{$conversationUuid}/messages/{$messageId}/reactions", [
                'reaction' => '👍',
            ]);

        // Verify the reaction was added
        $this->assertDatabaseHas('conversation_message_reactions', [
            'message_id' => $messageId,
            'user_id' => $this->user->id,
            'reaction' => '👍'
        ]);

        // Remove the reaction
        $removeResponse = $this->actingAs($this->user)
            ->delete("/api/conversations/{$conversationUuid}/messages/{$messageId}/reactions/👍");

        $removeResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Reaction removed successfully'
            ]);

        // Verify the reaction was removed from the database
        $this->assertDatabaseMissing('conversation_message_reactions', [
            'message_id' => $messageId,
            'user_id' => $this->user->id,
            'reaction' => '👍'
        ]);
    }
}
