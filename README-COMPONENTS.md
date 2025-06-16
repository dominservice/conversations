# Frontend Components for Laravel Conversations

This package provides ready-to-use frontend components for common chat UI elements, available for both Vue.js and React. These components are designed to work seamlessly with the Laravel Conversations package and can be easily integrated into your application.

## Available Components

The following components are available for both Vue.js and React:

1. **ConversationList** - Displays a list of conversations
2. **ConversationItem** - Displays a single conversation in the list
3. **MessageList** - Displays messages in a conversation
4. **MessageItem** - Displays a single message with reactions, attachments
5. **MessageInput** - Input for sending messages with attachments
6. **ReactionPicker** - UI for adding reactions to messages
7. **TypingIndicator** - Shows who is typing
8. **ThreadView** - Displays threaded replies

## Directory Structure

The components are organized in the following directory structure:

```
resources/
├── js/
│   ├── vue/
│   │   ├── vite/           # Vue components for Vite
│   │   └── laravel-mix/    # Vue components for Laravel Mix
│   ├── react/
│   │   ├── vite/           # React components for Vite
│   │   └── laravel-mix/    # React components for Laravel Mix
│   └── types/
│       ├── vue/            # TypeScript definitions for Vue components
│       └── react/          # TypeScript definitions for React components
```

## TypeScript Support

This package provides TypeScript definitions for all Vue and React components, making it easier to use them in TypeScript projects.

### Publishing TypeScript Definitions

To use the TypeScript definitions in your application, you need to publish them first:

```bash
php artisan vendor:publish --tag=conversations-typescript
```

This will copy all the TypeScript definitions to your application's `resources/js/vendor/conversations/types` directory.

### Using TypeScript Definitions

#### Vue.js with TypeScript

```typescript
<script lang="ts">
import { defineComponent } from 'vue';
import ConversationList from '@/vendor/conversations/vue/vite/ConversationList.vue';
import type { Conversation } from '@/vendor/conversations/types/vue/ConversationList';

export default defineComponent({
  components: {
    ConversationList
  },
  data() {
    return {
      conversations: [] as Conversation[],
      activeConversationId: null as string | null
    };
  },
  methods: {
    handleSelectConversation(conversation: Conversation) {
      this.activeConversationId = conversation.uuid;
    }
  }
});
</script>
```

#### React with TypeScript

```tsx
import React, { useState } from 'react';
import ConversationList from '@/vendor/conversations/react/vite/ConversationList';
import type { Conversation } from '@/vendor/conversations/types/react/ConversationList';

function ChatSidebar() {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [activeConversationId, setActiveConversationId] = useState<string | null>(null);

  const handleSelectConversation = (conversation: Conversation) => {
    setActiveConversationId(conversation.uuid);
  };

  return (
    <ConversationList
      conversations={conversations}
      activeConversationId={activeConversationId}
      onSelectConversation={handleSelectConversation}
    />
  );
}
```

## Installation

### 1. Publish the Components

To use these components in your application, you need to publish them first:

```bash
php artisan vendor:publish --tag=conversations-components
```

This will copy all the components to your application's `resources/js/vendor/conversations` directory.

### 2. Import the Components

#### Vue.js with Vite

```javascript
// Import individual components
import ConversationList from '@/vendor/conversations/vue/vite/ConversationList.vue';
import MessageInput from '@/vendor/conversations/vue/vite/MessageInput.vue';

// Register components
export default {
  components: {
    ConversationList,
    MessageInput
  }
}
```

#### Vue.js with Laravel Mix

```javascript
// Import individual components
import ConversationList from './vendor/conversations/vue/laravel-mix/ConversationList.vue';
import MessageInput from './vendor/conversations/vue/laravel-mix/MessageInput.vue';

// Register components
export default {
  components: {
    ConversationList,
    MessageInput
  }
}
```

#### React with Vite

```jsx
// Import individual components
import ConversationList from '@/vendor/conversations/react/vite/ConversationList';
import MessageInput from '@/vendor/conversations/react/vite/MessageInput';

// Import CSS
import '@/vendor/conversations/react/vite/ConversationList.css';
import '@/vendor/conversations/react/vite/MessageInput.css';

// Use components
function ChatApp() {
  return (
    <div className="chat-app">
      <ConversationList conversations={conversations} />
      <MessageInput onSend={handleSend} />
    </div>
  );
}
```

#### React with Laravel Mix

```jsx
// Import individual components
import ConversationList from './vendor/conversations/react/laravel-mix/ConversationList';
import MessageInput from './vendor/conversations/react/laravel-mix/MessageInput';

// Import CSS
import './vendor/conversations/react/laravel-mix/ConversationList.css';
import './vendor/conversations/react/laravel-mix/MessageInput.css';

// Use components
function ChatApp() {
  return (
    <div className="chat-app">
      <ConversationList conversations={conversations} />
      <MessageInput onSend={handleSend} />
    </div>
  );
}
```

## Component Usage

### ConversationList

The ConversationList component displays a list of conversations.

#### Vue.js

```vue
<template>
  <conversation-list
    :conversations="conversations"
    :active-conversation-id="activeConversationId"
    :loading="loading"
    @select-conversation="handleSelectConversation"
  />
</template>

<script>
import ConversationList from '@/vendor/conversations/vue/vite/ConversationList.vue';

export default {
  components: {
    ConversationList
  },
  data() {
    return {
      conversations: [],
      activeConversationId: null,
      loading: false
    };
  },
  methods: {
    handleSelectConversation(conversation) {
      this.activeConversationId = conversation.uuid;
      // Load messages for the selected conversation
    }
  }
};
</script>
```

#### React

```jsx
import { useState, useEffect } from 'react';
import ConversationList from '@/vendor/conversations/react/vite/ConversationList';
import '@/vendor/conversations/react/vite/ConversationList.css';

function ChatSidebar() {
  const [conversations, setConversations] = useState([]);
  const [activeConversationId, setActiveConversationId] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    // Load conversations
    setLoading(true);
    fetch('/api/conversations')
      .then(response => response.json())
      .then(data => {
        setConversations(data.data);
        setLoading(false);
      });
  }, []);

  const handleSelectConversation = (conversation) => {
    setActiveConversationId(conversation.uuid);
    // Load messages for the selected conversation
  };

  return (
    <ConversationList
      conversations={conversations}
      activeConversationId={activeConversationId}
      loading={loading}
      onSelectConversation={handleSelectConversation}
    />
  );
}
```

### MessageInput

The MessageInput component provides a text input for typing messages, with support for file attachments, emoji picker, and typing indicators.

#### Vue.js

```vue
<template>
  <message-input
    :placeholder="'Type a message...'"
    :emit-typing="true"
    :disabled="sending"
    :sending="sending"
    @send="handleSend"
    @typing="handleTyping"
  />
</template>

<script>
import MessageInput from '@/vendor/conversations/vue/vite/MessageInput.vue';

export default {
  components: {
    MessageInput
  },
  data() {
    return {
      sending: false
    };
  },
  methods: {
    handleSend(messageData) {
      this.sending = true;
      // Send the message to the server
      fetch(`/api/conversations/${this.conversationId}/messages`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          content: messageData.content,
          parent_id: messageData.replyToId
        })
      })
      .then(response => response.json())
      .then(data => {
        this.sending = false;
        // Handle success
      })
      .catch(error => {
        this.sending = false;
        // Handle error
      });
    },
    handleTyping() {
      // Broadcast typing event
      fetch(`/api/conversations/${this.conversationId}/typing`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          user_name: this.userName
        })
      });
    }
  }
};
</script>
```

#### React

```jsx
import { useState } from 'react';
import MessageInput from '@/vendor/conversations/react/vite/MessageInput';
import '@/vendor/conversations/react/vite/MessageInput.css';

function ChatInput({ conversationId, userName }) {
  const [sending, setSending] = useState(false);

  const handleSend = (messageData) => {
    setSending(true);
    // Send the message to the server
    fetch(`/api/conversations/${conversationId}/messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        content: messageData.content,
        parent_id: messageData.replyToId
      })
    })
    .then(response => response.json())
    .then(data => {
      setSending(false);
      // Handle success
    })
    .catch(error => {
      setSending(false);
      // Handle error
    });
  };

  const handleTyping = () => {
    // Broadcast typing event
    fetch(`/api/conversations/${conversationId}/typing`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        user_name: userName
      })
    });
  };

  return (
    <MessageInput
      placeholder="Type a message..."
      emitTyping={true}
      disabled={sending}
      sending={sending}
      onSend={handleSend}
      onTyping={handleTyping}
    />
  );
}
```

## Complete Chat Application Example

For a complete example of how to build a chat application using these components, please refer to the [Examples & Usage Guide](README-EXAMPLES.md).

## Customization

### Styling

All components come with default styling that matches the Laravel Conversations package design. You can customize the styling by overriding the CSS classes or by creating your own components based on these ones.

### Theming

The components use a consistent color scheme that can be easily customized. The main colors used are:

- Primary color: `#3490dc` (blue)
- Success color: `#38c172` (green)
- Background color: `#f5f5f5` (light gray)
- Border color: `#e0e0e0` (gray)
- Text color: `#666` (dark gray)

You can override these colors in your CSS to match your application's theme.

## Browser Compatibility

The components are compatible with all modern browsers, including:

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## License

These components are open-sourced software licensed under the [MIT license](LICENSE).
