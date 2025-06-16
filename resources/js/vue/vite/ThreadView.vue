<template>
  <div class="thread-view">
    <!-- Thread header -->
    <div class="thread-view__header">
      <h3 class="thread-view__title">Thread</h3>
      <button class="thread-view__close" @click="$emit('close')">×</button>
    </div>
    
    <!-- Parent message -->
    <div class="thread-view__parent">
      <message-item
        :message="parentMessage"
        :current-user-id="currentUserId"
        :show-avatar="true"
        :consecutive="false"
        @reaction-click="handleReactionClick"
      />
    </div>
    
    <!-- Thread count -->
    <div class="thread-view__count">
      {{ replyCount }} {{ replyCount === 1 ? 'reply' : 'replies' }}
    </div>
    
    <!-- Thread messages -->
    <div class="thread-view__messages" ref="messagesContainer">
      <div v-if="loading" class="thread-view__loading">
        <div class="thread-view__loading-spinner"></div>
        <div class="thread-view__loading-text">Loading replies...</div>
      </div>
      
      <div v-else-if="replies.length === 0" class="thread-view__empty">
        No replies yet
      </div>
      
      <div v-else class="thread-view__replies">
        <message-item
          v-for="message in replies"
          :key="message.id"
          :message="message"
          :current-user-id="currentUserId"
          :show-avatar="true"
          :consecutive="false"
          @reaction-click="handleReactionClick"
        />
      </div>
    </div>
    
    <!-- Reply input -->
    <div class="thread-view__input">
      <message-input
        :placeholder="'Reply to thread...'"
        :emit-typing="true"
        :disabled="inputDisabled"
        :sending="sending"
        @send="handleSend"
        @typing="handleTyping"
      />
    </div>
  </div>
</template>

<script>
import MessageItem from './MessageItem.vue';
import MessageInput from './MessageInput.vue';

export default {
  name: 'ThreadView',
  components: {
    MessageItem,
    MessageInput
  },
  props: {
    /**
     * The parent message of the thread
     */
    parentMessage: {
      type: Object,
      required: true
    },
    /**
     * Array of reply messages in the thread
     */
    replies: {
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
     * Whether the thread is loading
     */
    loading: {
      type: Boolean,
      default: false
    },
    /**
     * Whether the input is disabled
     */
    inputDisabled: {
      type: Boolean,
      default: false
    },
    /**
     * Whether a message is currently being sent
     */
    sending: {
      type: Boolean,
      default: false
    }
  },
  computed: {
    /**
     * Get the number of replies
     */
    replyCount() {
      return this.replies.length;
    }
  },
  watch: {
    /**
     * Watch for changes in replies to scroll to bottom
     */
    replies: {
      handler() {
        this.$nextTick(() => {
          this.scrollToBottom();
        });
      },
      deep: true
    }
  },
  methods: {
    /**
     * Handle reaction click
     */
    handleReactionClick(message, reaction) {
      this.$emit('reaction-click', message, reaction);
    },
    /**
     * Handle send message
     */
    handleSend(messageData) {
      // Add parent message ID to the message data
      const data = {
        ...messageData,
        parentId: this.parentMessage.id
      };
      
      this.$emit('send', data);
    },
    /**
     * Handle typing event
     */
    handleTyping() {
      this.$emit('typing');
    },
    /**
     * Scroll to the bottom of the messages container
     */
    scrollToBottom() {
      const container = this.$refs.messagesContainer;
      if (container) {
        container.scrollTop = container.scrollHeight;
      }
    }
  },
  mounted() {
    this.scrollToBottom();
  }
};
</script>

<style scoped>
.thread-view {
  display: flex;
  flex-direction: column;
  height: 100%;
  background-color: #fff;
  border-left: 1px solid #e0e0e0;
}

.thread-view__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  border-bottom: 1px solid #e0e0e0;
}

.thread-view__title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
}

.thread-view__close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #666;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.thread-view__parent {
  padding: 16px;
  border-bottom: 1px solid #e0e0e0;
}

.thread-view__count {
  padding: 8px 16px;
  font-size: 0.9rem;
  color: #666;
  border-bottom: 1px solid #e0e0e0;
}

.thread-view__messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.thread-view__loading,
.thread-view__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #666;
  text-align: center;
}

.thread-view__loading-spinner {
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

.thread-view__replies {
  display: flex;
  flex-direction: column;
}

.thread-view__input {
  border-top: 1px solid #e0e0e0;
}
</style>