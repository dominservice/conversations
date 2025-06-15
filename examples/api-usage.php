<?php

// This file demonstrates how to use the Conversations API endpoints from a client application

// Example 1: Using JavaScript/Fetch API to interact with the Conversations API

/*
// Configuration
const API_BASE_URL = 'https://your-laravel-app.com/api/conversations';
const API_TOKEN = 'your-api-token'; // Get this from your authentication system

// Helper function for API requests
async function apiRequest(endpoint, method = 'GET', data = null) {
    const url = `${API_BASE_URL}${endpoint}`;
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${API_TOKEN}`
        }
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    const response = await fetch(url, options);
    
    if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'API request failed');
    }
    
    return response.json();
}

// Get all conversations
async function getConversations() {
    try {
        const result = await apiRequest('');
        console.log('Conversations:', result.data);
        return result.data;
    } catch (error) {
        console.error('Error fetching conversations:', error);
    }
}

// Get a specific conversation
async function getConversation(conversationId) {
    try {
        const result = await apiRequest(`/${conversationId}`);
        console.log('Conversation details:', result.data);
        return result.data;
    } catch (error) {
        console.error(`Error fetching conversation ${conversationId}:`, error);
    }
}

// Create a new conversation
async function createConversation(userIds, initialMessage) {
    try {
        const data = {
            users: userIds,
            content: initialMessage
        };
        
        const result = await apiRequest('', 'POST', data);
        console.log('Conversation created:', result.data);
        return result.data;
    } catch (error) {
        console.error('Error creating conversation:', error);
    }
}

// Get messages in a conversation
async function getMessages(conversationId) {
    try {
        const result = await apiRequest(`/${conversationId}/messages`);
        console.log('Messages:', result.data);
        return result.data;
    } catch (error) {
        console.error(`Error fetching messages for conversation ${conversationId}:`, error);
    }
}

// Send a message to a conversation
async function sendMessage(conversationId, content) {
    try {
        const data = { message: content };
        const result = await apiRequest(`/${conversationId}/messages`, 'POST', data);
        console.log('Message sent:', result.data);
        return result.data;
    } catch (error) {
        console.error(`Error sending message to conversation ${conversationId}:`, error);
    }
}

// Mark a message as read
async function markMessageAsRead(conversationId, messageId) {
    try {
        const result = await apiRequest(`/${conversationId}/messages/${messageId}/read`, 'POST');
        console.log('Message marked as read:', result);
        return result;
    } catch (error) {
        console.error(`Error marking message ${messageId} as read:`, error);
    }
}

// Delete a message
async function deleteMessage(conversationId, messageId) {
    try {
        const result = await apiRequest(`/${conversationId}/messages/${messageId}`, 'DELETE');
        console.log('Message deleted:', result);
        return result;
    } catch (error) {
        console.error(`Error deleting message ${messageId}:`, error);
    }
}

// Delete a conversation
async function deleteConversation(conversationId) {
    try {
        const result = await apiRequest(`/${conversationId}`, 'DELETE');
        console.log('Conversation deleted:', result);
        return result;
    } catch (error) {
        console.error(`Error deleting conversation ${conversationId}:`, error);
    }
}

// Send typing indicator
async function sendTypingIndicator(conversationId, userName) {
    try {
        const data = { user_name: userName };
        const result = await apiRequest(`/${conversationId}/typing`, 'POST', data);
        console.log('Typing indicator sent:', result);
        return result;
    } catch (error) {
        console.error(`Error sending typing indicator for conversation ${conversationId}:`, error);
    }
}

// Usage examples
async function exampleUsage() {
    // Get all conversations
    const conversations = await getConversations();
    
    // Create a new conversation with two users
    const newConversation = await createConversation([2, 3], 'Hello, this is a new conversation!');
    
    if (newConversation) {
        const conversationId = newConversation.uuid;
        
        // Get conversation details
        await getConversation(conversationId);
        
        // Send a message
        await sendMessage(conversationId, 'This is a follow-up message.');
        
        // Get messages in the conversation
        const messages = await getMessages(conversationId);
        
        // Mark the first message as read
        if (messages && messages.length > 0) {
            await markMessageAsRead(conversationId, messages[0].message_id);
        }
        
        // Send typing indicator
        await sendTypingIndicator(conversationId, 'John Doe');
        
        // Delete the conversation
        // await deleteConversation(conversationId);
    }
}

// Call the example usage function
// exampleUsage();
*/

// Example 2: Using PHP/Guzzle to interact with the Conversations API

/*
<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ConversationsApiClient
{
    private $client;
    private $baseUrl;
    private $apiToken;
    
    public function __construct($baseUrl, $apiToken)
    {
        $this->baseUrl = $baseUrl;
        $this->apiToken = $apiToken;
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiToken
            ]
        ]);
    }
    
    public function getConversations()
    {
        try {
            $response = $this->client->get('');
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function getConversation($conversationId)
    {
        try {
            $response = $this->client->get("/{$conversationId}");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function createConversation(array $userIds, $initialMessage)
    {
        try {
            $response = $this->client->post('', [
                'json' => [
                    'users' => $userIds,
                    'content' => $initialMessage
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function getMessages($conversationId)
    {
        try {
            $response = $this->client->get("/{$conversationId}/messages");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function sendMessage($conversationId, $content)
    {
        try {
            $response = $this->client->post("/{$conversationId}/messages", [
                'json' => [
                    'message' => $content
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function markMessageAsRead($conversationId, $messageId)
    {
        try {
            $response = $this->client->post("/{$conversationId}/messages/{$messageId}/read");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function deleteMessage($conversationId, $messageId)
    {
        try {
            $response = $this->client->delete("/{$conversationId}/messages/{$messageId}");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function deleteConversation($conversationId)
    {
        try {
            $response = $this->client->delete("/{$conversationId}");
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    public function sendTypingIndicator($conversationId, $userName)
    {
        try {
            $response = $this->client->post("/{$conversationId}/typing", [
                'json' => [
                    'user_name' => $userName
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $this->handleException($e);
        }
    }
    
    private function handleException(RequestException $e)
    {
        if ($e->hasResponse()) {
            $response = json_decode($e->getResponse()->getBody(), true);
            $message = $response['message'] ?? $e->getMessage();
            throw new \Exception($message, $e->getCode());
        }
        
        throw $e;
    }
}

// Usage example
$apiClient = new ConversationsApiClient(
    'https://your-laravel-app.com/api/conversations',
    'your-api-token'
);

// Get all conversations
$conversations = $apiClient->getConversations();
print_r($conversations);

// Create a new conversation
$newConversation = $apiClient->createConversation([2, 3], 'Hello from PHP client!');
print_r($newConversation);

if ($newConversation) {
    $conversationId = $newConversation['data']['uuid'];
    
    // Send a message
    $message = $apiClient->sendMessage($conversationId, 'This is a follow-up message from PHP.');
    print_r($message);
    
    // Get messages
    $messages = $apiClient->getMessages($conversationId);
    print_r($messages);
    
    // Mark message as read
    if (!empty($messages['data'])) {
        $messageId = $messages['data'][0]['message_id'];
        $result = $apiClient->markMessageAsRead($conversationId, $messageId);
        print_r($result);
    }
}
*/