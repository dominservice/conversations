<template>
  <div class="message-list" ref="messageList">
    <div v-if="loading && messages.length === 0" class="message-list__loading">
      <div class="message-list__loading-spinner"></div>
      <div class="message-list__loading-text">{{ loadingText }}</div>
    </div>
    <div v-else-if="messages.length === 0" class="message-list__empty">
      {{ emptyText }}
    </div>
    <div v-else class="message-list__container">
      <div v-if="loadingMore" class="message-list__loading-more">
        <div class="message-list__loading-spinner"></div>
      </div>
      
      <div v-for="(group, index) in messageGroups" :key="index" class="message-list__group">
        <div class="message-list__date-divider">
          <span class="message-list__date-text">{{ group.date }}</span>
        </div>
        
        <message-item
          v-for="message in group.messages"
          :key="message.id"
          :message="message"
          :current-user-id="currentUserId"
          :show-avatar="shouldShowAvatar(message)"
          :consecutive="isConsecutiveMessage(message)"
          @reaction-click="handleReactionClick"
          @thread-click="handleThreadClick"
          @message-click="handleMessageClick"
        />
      </div>
      
      <div v-if="typingUsers.length > 0" class="message-list__typing">
        <typing-indicator :users="typingUsers" />
      </div>
    </div>
  </div>
</template>

<script>
import MessageItem from './MessageItem.vue';
import TypingIndicator from './TypingIndicator.vue';

export default {
  name: 'MessageList',
  components: {
    MessageItem,
    TypingIndicator
  },
  props: {
    /**
     * Array of message objects
     */
    messages: {
      type: Array,
      default: () => []
    },
    /**
     * Current user ID
     */
    currentUserId: {
      type: [String, Number],
      default: null
    },
    /**
     * Whether messages are currently loading
     */
    loading: {
      type: Boolean,
      default: false
    },
    /**
     * Whether more messages are being loaded (for infinite scroll)
     */
    loadingMore: {
      type: Boolean,
      default: false
    },
    /**
     * Text to display while loading
     */
    loadingText: {
      type: String,
      default: 'Loading messages...'
    },
    /**
     * Text to display when there are no messages
     */
    emptyText: {
      type: String,
      default: 'No messages yet'
    },
    /**
     * Array of users who are currently typing
     */
    typingUsers: {
      type: Array,
      default: () => []
    },
    /**
     * Whether to automatically scroll to the bottom on new messages
     */
    autoScroll: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      lastMessageSenderId: null,
      lastMessageTimestamp: null,
      scrolledToBottom: true
    };
  },
  computed: {
    /**
     * Group messages by date
     */
    messageGroups() {
      const groups = [];
      let currentDate = null;
      let currentGroup = null;
      
      this.messages.forEach(message => {
        const messageDate = new Date(message.createdAt);
        const dateString = this.formatMessageDate(messageDate);
        
        if (dateString !== currentDate) {
          currentDate = dateString;
          currentGroup = {
            date: dateString,
            messages: []
          };
          groups.push(currentGroup);
        }
        
        currentGroup.messages.push(message);
      });
      
      return groups;
    }
  },
  watch: {
    /**
     * Watch for changes in messages to scroll to bottom if needed
     */
    messages: {
      handler() {
        if (this.autoScroll && this.scrolledToBottom) {
          this.$nextTick(() => {
            this.scrollToBottom();
          });
        }
      },
      deep: true
    }
  },
  mounted() {
    // Initial scroll to bottom
    this.$nextTick(() => {
      this.scrollToBottom();
    });
    
    // Add scroll event listener to detect when user scrolls away from bottom
    const messageList = this.$refs.messageList;
    if (messageList) {
      messageList.addEventListener('scroll', this.handleScroll);
    }
  },
  beforeUnmount() {
    // Remove scroll event listener
    const messageList = this.$refs.messageList;
    if (messageList) {
      messageList.removeEventListener('scroll', this.handleScroll);
    }
  },
  methods: {
    /**
     * Format message date for display
     */
    formatMessageDate(date) {
      const today = new Date();
      const yesterday = new Date(today);
      yesterday.setDate(yesterday.getDate() - 1);
      
      if (date.toDateString() === today.toDateString()) {
        return 'Today';
      } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Yesterday';
      } else {
        return date.toLocaleDateString(undefined, {
          weekday: 'long',
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        });
      }
    },
    /**
     * Determine if avatar should be shown for this message
     */
    shouldShowAvatar(message) {
      // Always show avatar for the first message in a group
      if (this.lastMessageSenderId !== message.sender.id) {
        this.lastMessageSenderId = message.sender.id;
        this.lastMessageTimestamp = message.createdAt;
        return true;
      }
      
      // Show avatar if messages are more than 5 minutes apart
      const currentTime = new Date(message.createdAt).getTime();
      const lastTime = new Date(this.lastMessageTimestamp).getTime();
      const timeDiff = (currentTime - lastTime) / (1000 * 60); // difference in minutes
      
      this.lastMessageTimestamp = message.createdAt;
      
      return timeDiff > 5;
    },
    /**
     * Determine if this message is consecutive (from same sender with small time gap)
     */
    isConsecutiveMessage(message) {
      return this.lastMessageSenderId === message.sender.id;
    },
    /**
     * Handle reaction click event
     */
    handleReactionClick(message, reaction) {
      this.$emit('reaction-click', message, reaction);
    },
    /**
     * Handle thread click event
     */
    handleThreadClick(message) {
      this.$emit('thread-click', message);
    },
    /**
     * Handle message click event
     */
    handleMessageClick(message) {
      this.$emit('message-click', message);
    },
    /**
     * Scroll to the bottom of the message list
     */
    scrollToBottom() {
      const messageList = this.$refs.messageList;
      if (messageList) {
        messageList.scrollTop = messageList.scrollHeight;
      }
    },
    /**
     * Handle scroll event to detect when user scrolls away from bottom
     */
    handleScroll() {
      const messageList = this.$refs.messageList;
      if (messageList) {
        const { scrollTop, scrollHeight, clientHeight } = messageList;
        const atBottom = scrollHeight - scrollTop - clientHeight < 50;
        
        this.scrolledToBottom = atBottom;
        
        // Emit scroll to top event for infinite loading
        if (scrollTop < 50 && !this.loading && !this.loadingMore) {
          this.$emit('load-more');
        }
      }
    }
  }
};
</script>

<style scoped>
.message-list {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow-y: auto;
  padding: 16px;
  background-color: #fff;
}

.message-list__loading,
.message-list__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #666;
  text-align: center;
}

.message-list__loading-spinner {
  width: 30px;
  height: 30px;
  border: 3px solid rgba(0, 0, 0, 0.1);
  border-radius: 50%;
  border-top-color: #3490dc;
  animation: spin 1s ease-in-out infinite;
  margin-bottom: 10px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.message-list__container {
  display: flex;
  flex-direction: column;
  min-height: 0;
  flex: 1;
}

.message-list__loading-more {
  display: flex;
  justify-content: center;
  padding: 10px 0;
}

.message-list__group {
  margin-bottom: 16px;
}

.message-list__date-divider {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 16px 0;
  position: relative;
}

.message-list__date-divider::before {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  height: 1px;
  background-color: #e0e0e0;
  z-index: 1;
}

.message-list__date-text {
  background-color: #fff;
  padding: 0 10px;
  font-size: 0.8rem;
  color: #666;
  position: relative;
  z-index: 2;
}

.message-list__typing {
  margin-top: 8px;
}
</style>