<template>
  <div 
    class="message-item" 
    :class="{
      'message-item--self': isSelf,
      'message-item--consecutive': consecutive && !isThreadReply,
      'message-item--thread-reply': isThreadReply
    }"
    @click="$emit('message-click', message)"
  >
    <div v-if="showAvatar && !isSelf" class="message-item__avatar">
      <div class="message-item__avatar-image">
        <span>{{ getInitials(message.sender.name) }}</span>
      </div>
    </div>
    <div v-else-if="!isSelf" class="message-item__avatar message-item__avatar--placeholder"></div>
    
    <div class="message-item__content">
      <div v-if="showAvatar && !consecutive && !isSelf" class="message-item__sender">
        {{ message.sender.name }}
      </div>
      
      <div v-if="isThreadReply" class="message-item__reply-info">
        <span>Replying to {{ getReplyToName() }}</span>
      </div>
      
      <div 
        class="message-item__bubble" 
        :class="{ 'message-item__bubble--self': isSelf }"
      >
        <!-- Text content -->
        <div v-if="message.content" class="message-item__text">
          {{ message.content }}
        </div>
        
        <!-- Attachments -->
        <div v-if="hasAttachments" class="message-item__attachments">
          <div 
            v-for="attachment in message.attachments" 
            :key="attachment.id" 
            class="message-item__attachment"
            :class="`message-item__attachment--${attachment.type}`"
          >
            <!-- Image attachment -->
            <img 
              v-if="attachment.isImage" 
              :src="attachment.url" 
              :alt="attachment.originalFilename"
              class="message-item__attachment-image"
              @click.stop="openAttachment(attachment)"
            />
            
            <!-- File attachment -->
            <div v-else class="message-item__attachment-file" @click.stop="openAttachment(attachment)">
              <div class="message-item__attachment-icon">
                <span v-if="attachment.type === 'document'">📄</span>
                <span v-else-if="attachment.type === 'audio'">🎵</span>
                <span v-else-if="attachment.type === 'video'">🎬</span>
                <span v-else>📎</span>
              </div>
              <div class="message-item__attachment-info">
                <div class="message-item__attachment-filename">{{ attachment.originalFilename }}</div>
                <div class="message-item__attachment-size">{{ attachment.humanSize }}</div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Edited indicator -->
        <div v-if="message.hasBeenEdited" class="message-item__edited">
          (edited)
        </div>
      </div>
      
      <!-- Message actions -->
      <div class="message-item__actions">
        <button 
          class="message-item__action message-item__action--reaction"
          @click.stop="$emit('reaction-click', message)"
        >
          😀
        </button>
        <button 
          v-if="!isThreadReply"
          class="message-item__action message-item__action--thread"
          @click.stop="$emit('thread-click', message)"
        >
          💬
        </button>
      </div>
      
      <!-- Reactions -->
      <div v-if="hasReactions" class="message-item__reactions">
        <div 
          v-for="(reaction, index) in groupedReactions" 
          :key="index"
          class="message-item__reaction"
          :class="{ 'message-item__reaction--selected': userHasReaction(reaction.emoji) }"
          @click.stop="$emit('reaction-click', message, reaction.emoji)"
        >
          <span class="message-item__reaction-emoji">{{ reaction.emoji }}</span>
          <span class="message-item__reaction-count">{{ reaction.count }}</span>
        </div>
      </div>
      
      <!-- Thread indicator -->
      <div v-if="hasReplies" class="message-item__thread-indicator" @click.stop="$emit('thread-click', message)">
        {{ message.replies.length }} {{ message.replies.length === 1 ? 'reply' : 'replies' }}
      </div>
      
      <!-- Timestamp -->
      <div class="message-item__time" :class="{ 'message-item__time--self': isSelf }">
        {{ formatTime(message.createdAt) }}
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MessageItem',
  props: {
    /**
     * Message object
     */
    message: {
      type: Object,
      required: true
    },
    /**
     * Current user ID
     */
    currentUserId: {
      type: [String, Number],
      default: null
    },
    /**
     * Whether to show the avatar
     */
    showAvatar: {
      type: Boolean,
      default: true
    },
    /**
     * Whether this message is consecutive (from same sender)
     */
    consecutive: {
      type: Boolean,
      default: false
    }
  },
  computed: {
    /**
     * Whether this message was sent by the current user
     */
    isSelf() {
      return this.message.sender && this.message.sender.id === this.currentUserId;
    },
    /**
     * Whether this message has attachments
     */
    hasAttachments() {
      return this.message.attachments && this.message.attachments.length > 0;
    },
    /**
     * Whether this message has reactions
     */
    hasReactions() {
      return this.message.reactions && this.message.reactions.length > 0;
    },
    /**
     * Whether this message has replies
     */
    hasReplies() {
      return this.message.replies && this.message.replies.length > 0;
    },
    /**
     * Whether this message is a reply to another message
     */
    isThreadReply() {
      return this.message.isReply;
    },
    /**
     * Group reactions by emoji with count
     */
    groupedReactions() {
      if (!this.hasReactions) return [];
      
      const grouped = {};
      
      this.message.reactions.forEach(reaction => {
        if (!grouped[reaction.reaction]) {
          grouped[reaction.reaction] = {
            emoji: reaction.reaction,
            count: 0,
            users: []
          };
        }
        
        grouped[reaction.reaction].count++;
        grouped[reaction.reaction].users.push(reaction.user);
      });
      
      return Object.values(grouped);
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
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
    /**
     * Check if the current user has reacted with a specific emoji
     */
    userHasReaction(emoji) {
      if (!this.hasReactions || !this.currentUserId) return false;
      
      return this.message.reactions.some(
        reaction => reaction.reaction === emoji && reaction.user.id === this.currentUserId
      );
    },
    /**
     * Open an attachment
     */
    openAttachment(attachment) {
      window.open(attachment.url, '_blank');
    },
    /**
     * Get the name of the user this message is replying to
     */
    getReplyToName() {
      if (!this.isThreadReply || !this.message.parent || !this.message.parent.sender) {
        return 'a message';
      }
      
      return this.message.parent.sender.name;
    }
  }
};
</script>

<style scoped>
.message-item {
  display: flex;
  margin-bottom: 8px;
  position: relative;
}

.message-item--self {
  flex-direction: row-reverse;
}

.message-item--consecutive {
  margin-top: 2px;
}

.message-item--thread-reply {
  margin-left: 24px;
  border-left: 2px solid #e0e0e0;
  padding-left: 8px;
}

.message-item__avatar {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  margin-right: 8px;
}

.message-item--self .message-item__avatar {
  margin-right: 0;
  margin-left: 8px;
}

.message-item__avatar--placeholder {
  width: 36px;
}

.message-item__avatar-image {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background-color: #3490dc;
  color: white;
  font-weight: bold;
  font-size: 0.8rem;
}

.message-item__content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.message-item__sender {
  font-weight: bold;
  margin-bottom: 4px;
  font-size: 0.9rem;
}

.message-item__reply-info {
  font-size: 0.8rem;
  color: #666;
  margin-bottom: 4px;
  font-style: italic;
}

.message-item__bubble {
  align-self: flex-start;
  max-width: 80%;
  padding: 8px 12px;
  border-radius: 18px;
  background-color: #f1f0f0;
  position: relative;
}

.message-item__bubble--self {
  align-self: flex-end;
  background-color: #dcf8c6;
}

.message-item__text {
  word-wrap: break-word;
  white-space: pre-wrap;
}

.message-item__edited {
  font-size: 0.7rem;
  color: #888;
  margin-top: 2px;
  font-style: italic;
}

.message-item__attachments {
  margin-top: 4px;
}

.message-item__attachment {
  margin-top: 4px;
  border-radius: 8px;
  overflow: hidden;
}

.message-item__attachment-image {
  max-width: 100%;
  max-height: 200px;
  object-fit: contain;
  cursor: pointer;
}

.message-item__attachment-file {
  display: flex;
  align-items: center;
  padding: 8px;
  background-color: rgba(0, 0, 0, 0.05);
  border-radius: 8px;
  cursor: pointer;
}

.message-item__attachment-icon {
  font-size: 1.5rem;
  margin-right: 8px;
}

.message-item__attachment-info {
  flex: 1;
  min-width: 0;
}

.message-item__attachment-filename {
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.message-item__attachment-size {
  font-size: 0.8rem;
  color: #666;
}

.message-item__actions {
  display: none;
  position: absolute;
  top: -16px;
  right: 0;
  background-color: white;
  border-radius: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  padding: 2px;
}

.message-item--self .message-item__actions {
  right: auto;
  left: 0;
}

.message-item:hover .message-item__actions {
  display: flex;
}

.message-item__action {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: none;
  background-color: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  padding: 0;
  margin: 0 2px;
}

.message-item__action:hover {
  background-color: #f0f0f0;
}

.message-item__reactions {
  display: flex;
  flex-wrap: wrap;
  margin-top: 4px;
}

.message-item__reaction {
  display: flex;
  align-items: center;
  background-color: #f0f0f0;
  border-radius: 12px;
  padding: 2px 6px;
  margin-right: 4px;
  margin-bottom: 4px;
  cursor: pointer;
  font-size: 0.9rem;
}

.message-item__reaction--selected {
  background-color: #e3f2fd;
  border: 1px solid #3490dc;
}

.message-item__reaction-emoji {
  margin-right: 4px;
}

.message-item__reaction-count {
  font-size: 0.8rem;
}

.message-item__thread-indicator {
  font-size: 0.8rem;
  color: #3490dc;
  margin-top: 4px;
  cursor: pointer;
}

.message-item__thread-indicator:hover {
  text-decoration: underline;
}

.message-item__time {
  font-size: 0.7rem;
  color: #888;
  margin-top: 2px;
  align-self: flex-start;
}

.message-item__time--self {
  align-self: flex-end;
}
</style>