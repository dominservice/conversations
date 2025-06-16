<template>
  <div class="conversation-list">
    <div v-if="loading" class="conversation-list__loading">
      <div class="conversation-list__loading-spinner"></div>
      <div class="conversation-list__loading-text">{{ loadingText }}</div>
    </div>
    <div v-else-if="conversations.length === 0" class="conversation-list__empty">
      {{ emptyText }}
    </div>
    <div v-else class="conversation-list__items">
      <conversation-item
        v-for="conversation in conversations"
        :key="conversation.uuid"
        :conversation="conversation"
        :active="activeConversationId === conversation.uuid"
        @click="selectConversation(conversation)"
      />
    </div>
  </div>
</template>

<script>
import ConversationItem from './ConversationItem.vue';

export default {
  name: 'ConversationList',
  components: {
    ConversationItem
  },
  props: {
    /**
     * Array of conversation objects
     */
    conversations: {
      type: Array,
      default: () => []
    },
    /**
     * ID of the currently active conversation
     */
    activeConversationId: {
      type: String,
      default: null
    },
    /**
     * Whether the conversations are currently loading
     */
    loading: {
      type: Boolean,
      default: false
    },
    /**
     * Text to display while loading
     */
    loadingText: {
      type: String,
      default: 'Loading conversations...'
    },
    /**
     * Text to display when there are no conversations
     */
    emptyText: {
      type: String,
      default: 'No conversations found'
    }
  },
  methods: {
    /**
     * Emit an event when a conversation is selected
     */
    selectConversation(conversation) {
      this.$emit('select-conversation', conversation);
    }
  }
};
</script>

<style scoped>
.conversation-list {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow-y: auto;
  background-color: #f5f5f5;
  border-right: 1px solid #e0e0e0;
}

.conversation-list__loading,
.conversation-list__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding: 20px;
  color: #666;
  text-align: center;
}

.conversation-list__loading-spinner {
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

.conversation-list__items {
  display: flex;
  flex-direction: column;
}
</style>