<template>
  <div 
    class="conversation-item" 
    :class="{ 'conversation-item--active': active, 'conversation-item--unread': conversation.hasUnread }"
    @click="$emit('click', conversation)"
  >
    <div class="conversation-item__avatar">
      <div v-if="isGroup" class="conversation-item__avatar-group">
        <span>{{ getInitials(conversation.title) }}</span>
      </div>
      <div v-else class="conversation-item__avatar-user">
        <span>{{ getInitials(otherParticipant?.name) }}</span>
      </div>
    </div>
    <div class="conversation-item__content">
      <div class="conversation-item__header">
        <div class="conversation-item__title">
          {{ isGroup ? conversation.title : otherParticipant?.name || 'Unknown User' }}
        </div>
        <div class="conversation-item__time">
          {{ formatTime(conversation.lastMessage?.createdAt) }}
        </div>
      </div>
      <div class="conversation-item__body">
        <div class="conversation-item__message">
          <span v-if="conversation.lastMessage?.sender" class="conversation-item__sender">
            {{ isSelfSender ? 'You: ' : '' }}
          </span>
          <span class="conversation-item__text">
            {{ getLastMessagePreview() }}
          </span>
        </div>
        <div v-if="conversation.unreadCount > 0" class="conversation-item__badge">
          {{ conversation.unreadCount > 99 ? '99+' : conversation.unreadCount }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ConversationItem',
  props: {
    /**
     * Conversation object
     */
    conversation: {
      type: Object,
      required: true
    },
    /**
     * Whether this conversation is currently active
     */
    active: {
      type: Boolean,
      default: false
    },
    /**
     * Current user ID to determine if the sender is the current user
     */
    currentUserId: {
      type: [String, Number],
      default: null
    }
  },
  computed: {
    /**
     * Determine if the conversation is a group conversation
     */
    isGroup() {
      return this.conversation.users && this.conversation.users.length > 2;
    },
    /**
     * Get the other participant in a one-on-one conversation
     */
    otherParticipant() {
      if (!this.conversation.users || this.conversation.users.length === 0) {
        return null;
      }
      
      if (this.currentUserId) {
        return this.conversation.users.find(user => user.id !== this.currentUserId);
      }
      
      // If currentUserId is not provided, just return the first user
      return this.conversation.users[0];
    },
    /**
     * Determine if the sender of the last message is the current user
     */
    isSelfSender() {
      if (!this.conversation.lastMessage || !this.conversation.lastMessage.sender) {
        return false;
      }
      
      return this.conversation.lastMessage.sender.id === this.currentUserId;
    }
  },
  methods: {
    /**
     * Get initials from a name
     */
    getInitials(name) {
      if (!name) return '?';
      
      return name
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .substring(0, 2);
    },
    /**
     * Format the timestamp
     */
    formatTime(timestamp) {
      if (!timestamp) return '';
      
      const date = new Date(timestamp);
      const now = new Date();
      const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
      
      if (diffDays === 0) {
        // Today, show time
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      } else if (diffDays === 1) {
        // Yesterday
        return 'Yesterday';
      } else if (diffDays < 7) {
        // This week, show day name
        return date.toLocaleDateString([], { weekday: 'short' });
      } else {
        // Older, show date
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
      }
    },
    /**
     * Get a preview of the last message
     */
    getLastMessagePreview() {
      const lastMessage = this.conversation.lastMessage;
      
      if (!lastMessage) {
        return 'No messages yet';
      }
      
      if (lastMessage.messageType === 'attachment') {
        return '📎 Attachment';
      }
      
      // Truncate long messages
      const maxLength = 30;
      if (lastMessage.content && lastMessage.content.length > maxLength) {
        return lastMessage.content.substring(0, maxLength) + '...';
      }
      
      return lastMessage.content || '';
    }
  }
};
</script>

<style scoped>
.conversation-item {
  display: flex;
  padding: 12px;
  border-bottom: 1px solid #e0e0e0;
  cursor: pointer;
  transition: background-color 0.2s;
}

.conversation-item:hover {
  background-color: #eaeaea;
}

.conversation-item--active {
  background-color: #e3f2fd;
}

.conversation-item--unread {
  background-color: #f0f7ff;
}

.conversation-item__avatar {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  margin-right: 12px;
}

.conversation-item__avatar-user,
.conversation-item__avatar-group {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background-color: #3490dc;
  color: white;
  font-weight: bold;
}

.conversation-item__avatar-group {
  background-color: #38c172;
}

.conversation-item__content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.conversation-item__header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}

.conversation-item__title {
  font-weight: bold;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation-item__time {
  flex-shrink: 0;
  font-size: 0.8rem;
  color: #666;
  margin-left: 8px;
}

.conversation-item__body {
  display: flex;
  justify-content: space-between;
}

.conversation-item__message {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #666;
}

.conversation-item__sender {
  font-weight: 500;
}

.conversation-item__badge {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  border-radius: 10px;
  background-color: #3490dc;
  color: white;
  font-size: 0.75rem;
  padding: 0 6px;
  margin-left: 8px;
}
</style>