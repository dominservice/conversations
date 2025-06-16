# GraphQL API for Laravel Conversations

This package provides a GraphQL API for the Laravel Conversations package, allowing you to use GraphQL alongside the traditional REST API.

## Installation

The GraphQL API is included in the package, but requires the [Lighthouse PHP](https://lighthouse-php.com/) package to be installed in your Laravel application.

```bash
composer require nuwave/lighthouse
```

After installing Lighthouse, publish its configuration:

```bash
php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider" --tag=config
```

Then, publish the GraphQL schema files from the Conversations package:

```bash
php artisan vendor:publish --provider="Dominservice\Conversations\ConversationsServiceProvider" --tag=conversations-graphql
```

This will create a `graphql/conversations` directory in your application with the schema files for the Conversations package.

## Configuration

The GraphQL API uses the same authentication and authorization mechanisms as the REST API. Make sure you have configured the `conversations.php` config file correctly.

## Schema

The GraphQL schema includes the following types:

- `Conversation`: Represents a conversation between users
- `Message`: Represents a message in a conversation
- `Attachment`: Represents an attachment to a message
- `Reaction`: Represents a reaction to a message
- `MessageStatus`: Represents the status of a message for a user
- `ConversationType`: Represents the type of a conversation
- `ConversationRelation`: Represents a relation between a conversation and another model
- `ReactionSummary`: Represents a summary of reactions to a message

## Queries

The GraphQL API provides the following queries:

### Conversations

```graphql
# Get a list of conversations for the authenticated user
query GetConversations($relationType: String, $relationId: ID) {
  conversations(relationType: $relationType, relationId: $relationId) {
    uuid
    title
    unreadCount
    hasUnread
    createdAt
    updatedAt
    users {
      id
      name
    }
    lastMessage {
      id
      content
      createdAt
      sender {
        id
        name
      }
    }
  }
}

# Get a specific conversation by UUID
query GetConversation($uuid: ID!) {
  conversation(uuid: $uuid) {
    uuid
    title
    unreadCount
    hasUnread
    createdAt
    updatedAt
    users {
      id
      name
    }
    messages {
      id
      content
      createdAt
      sender {
        id
        name
      }
    }
  }
}
```

### Messages

```graphql
# Get messages in a conversation
query GetMessages($uuid: ID!, $order: String, $limit: Int, $start: Int) {
  messages(uuid: $uuid, order: $order, limit: $limit, start: $start) {
    id
    content
    messageType
    createdAt
    sender {
      id
      name
    }
    hasAttachments
    attachments {
      id
      filename
      url
      type
      humanSize
    }
    hasReactions
    reactions {
      id
      reaction
      user {
        id
        name
      }
    }
    isReply
    parent {
      id
      content
    }
  }
}

# Get unread messages in a conversation
query GetUnreadMessages($uuid: ID!, $order: String, $limit: Int, $start: Int) {
  unreadMessages(uuid: $uuid, order: $order, limit: $limit, start: $start) {
    id
    content
    createdAt
    sender {
      id
      name
    }
  }
}

# Get users who have read a specific message
query GetMessageReadBy($uuid: ID!, $messageId: ID!) {
  messageReadBy(uuid: $uuid, messageId: $messageId) {
    id
    name
  }
}

# Get all messages in a thread
query GetThread($uuid: ID!, $messageId: ID!) {
  thread(uuid: $uuid, messageId: $messageId) {
    id
    content
    createdAt
    sender {
      id
      name
    }
  }
}

# Get all reactions for a message
query GetMessageReactions($uuid: ID!, $messageId: ID!) {
  messageReactions(uuid: $uuid, messageId: $messageId) {
    id
    reaction
    user {
      id
      name
    }
  }
}

# Check if a message is editable
query IsMessageEditable($uuid: ID!, $messageId: ID!) {
  isMessageEditable(uuid: $uuid, messageId: $messageId)
}
```

### Attachments

```graphql
# Get attachments for a message
query GetAttachments($uuid: ID!, $messageId: ID!) {
  attachments(uuid: $uuid, messageId: $messageId) {
    id
    filename
    originalFilename
    mimeType
    extension
    type
    size
    humanSize
    url
    isImage
    thumbnailUrl
  }
}
```

## Mutations

The GraphQL API provides the following mutations:

### Conversations

```graphql
# Create a new conversation
mutation CreateConversation($users: [ID!]!, $content: String, $relationType: String, $relationId: ID) {
  createConversation(users: $users, content: $content, relationType: $relationType, relationId: $relationId) {
    uuid
    title
    createdAt
  }
}

# Delete a conversation
mutation DeleteConversation($uuid: ID!) {
  deleteConversation(uuid: $uuid)
}
```

### Messages

```graphql
# Send a message to a conversation
mutation SendMessage($uuid: ID!, $content: String, $parentId: ID) {
  sendMessage(uuid: $uuid, content: $content, parentId: $parentId) {
    id
    content
    createdAt
  }
}

# Send a message with attachments to a conversation
mutation SendMessageWithAttachments($uuid: ID!, $content: String, $parentId: ID, $attachments: [Upload!]!) {
  sendMessageWithAttachments(uuid: $uuid, content: $content, parentId: $parentId, attachments: $attachments) {
    id
    content
    createdAt
    attachments {
      id
      filename
      url
    }
  }
}

# Mark a message as read
mutation MarkMessageAsRead($uuid: ID!, $messageId: ID!) {
  markMessageAsRead(uuid: $uuid, messageId: $messageId)
}

# Mark a message as unread
mutation MarkMessageAsUnread($uuid: ID!, $messageId: ID!) {
  markMessageAsUnread(uuid: $uuid, messageId: $messageId)
}

# Delete a message
mutation DeleteMessage($uuid: ID!, $messageId: ID!) {
  deleteMessage(uuid: $uuid, messageId: $messageId)
}

# Edit a message
mutation EditMessage($uuid: ID!, $messageId: ID!, $content: String!) {
  editMessage(uuid: $uuid, messageId: $messageId, content: $content) {
    id
    content
    editedAt
    hasBeenEdited
  }
}

# Reply to a message
mutation ReplyToMessage($uuid: ID!, $messageId: ID!, $content: String!) {
  replyToMessage(uuid: $uuid, messageId: $messageId, content: $content) {
    id
    content
    createdAt
    parent {
      id
      content
    }
  }
}

# Add a reaction to a message
mutation AddReaction($uuid: ID!, $messageId: ID!, $reaction: String!) {
  addReaction(uuid: $uuid, messageId: $messageId, reaction: $reaction) {
    id
    reaction
    user {
      id
      name
    }
  }
}

# Remove a reaction from a message
mutation RemoveReaction($uuid: ID!, $messageId: ID!, $reaction: String!) {
  removeReaction(uuid: $uuid, messageId: $messageId, reaction: $reaction)
}

# Broadcast that the user is typing
mutation Typing($uuid: ID!, $userName: String) {
  typing(uuid: $uuid, userName: $userName)
}
```

## Client Setup

To use the GraphQL API in your frontend application, you'll need a GraphQL client. Here are some examples using popular GraphQL clients:

### Apollo Client (JavaScript/TypeScript)

```javascript
import { ApolloClient, InMemoryCache, createHttpLink } from '@apollo/client';
import { setContext } from '@apollo/client/link/context';

// Create an HTTP link
const httpLink = createHttpLink({
  uri: '/graphql',
});

// Add authentication headers
const authLink = setContext((_, { headers }) => {
  // Get the authentication token from local storage if it exists
  const token = localStorage.getItem('token');
  
  // Return the headers to the context so httpLink can read them
  return {
    headers: {
      ...headers,
      authorization: token ? `Bearer ${token}` : "",
    }
  }
});

// Create the Apollo Client
const client = new ApolloClient({
  link: authLink.concat(httpLink),
  cache: new InMemoryCache()
});

// Example query
import { gql } from '@apollo/client';

const GET_CONVERSATIONS = gql`
  query GetConversations {
    conversations {
      uuid
      title
      unreadCount
      hasUnread
      createdAt
      updatedAt
      users {
        id
        name
      }
      lastMessage {
        id
        content
        createdAt
        sender {
          id
          name
        }
      }
    }
  }
`;

// Use the query in a component
import { useQuery } from '@apollo/client';

function ConversationsList() {
  const { loading, error, data } = useQuery(GET_CONVERSATIONS);

  if (loading) return <p>Loading...</p>;
  if (error) return <p>Error :(</p>;

  return (
    <div>
      {data.conversations.map(conversation => (
        <div key={conversation.uuid}>
          <h3>{conversation.title}</h3>
          <p>Unread: {conversation.unreadCount}</p>
          {conversation.lastMessage && (
            <p>
              Last message: {conversation.lastMessage.content} by {conversation.lastMessage.sender.name}
            </p>
          )}
        </div>
      ))}
    </div>
  );
}
```

### Laravel Inertia.js with Vue

If you're using Inertia.js with Vue, you can use the `@inertiajs/inertia` package to make GraphQL requests:

```javascript
import { Inertia } from '@inertiajs/inertia';

// Example function to make a GraphQL request
async function fetchConversations() {
  try {
    const response = await Inertia.post('/graphql', {
      query: `
        query GetConversations {
          conversations {
            uuid
            title
            unreadCount
            hasUnread
            createdAt
            updatedAt
            users {
              id
              name
            }
            lastMessage {
              id
              content
              createdAt
              sender {
                id
                name
              }
            }
          }
        }
      `
    });
    
    return response.data.conversations;
  } catch (error) {
    console.error('Error fetching conversations:', error);
    return [];
  }
}
```

## Conclusion

The GraphQL API provides a flexible and powerful way to interact with the Conversations package. It allows you to request exactly the data you need and perform multiple operations in a single request.

For more information about GraphQL and Lighthouse, refer to the [Lighthouse documentation](https://lighthouse-php.com/).