<template>
  <div class="message-input">
    <!-- Reply indicator -->
    <div v-if="replyToMessage" class="message-input__reply">
      <div class="message-input__reply-content">
        <span class="message-input__reply-label">Replying to {{ replyToMessage.sender.name }}</span>
        <span class="message-input__reply-text">{{ getReplyPreview() }}</span>
      </div>
      <button class="message-input__reply-close" @click="$emit('cancel-reply')">×</button>
    </div>
    
    <!-- Attachment preview -->
    <div v-if="attachments.length > 0" class="message-input__attachments">
      <div 
        v-for="(attachment, index) in attachments" 
        :key="index" 
        class="message-input__attachment"
      >
        <div class="message-input__attachment-preview">
          <img 
            v-if="isImageFile(attachment.file)" 
            :src="getFilePreview(attachment.file)" 
            class="message-input__attachment-image" 
            alt="Attachment preview"
          />
          <div v-else class="message-input__attachment-file">
            <span class="message-input__attachment-icon">
              {{ getFileIcon(attachment.file) }}
            </span>
            <span class="message-input__attachment-name">{{ attachment.file.name }}</span>
          </div>
        </div>
        <button 
          class="message-input__attachment-remove" 
          @click="removeAttachment(index)"
        >
          ×
        </button>
      </div>
    </div>
    
    <!-- Input area -->
    <div class="message-input__container">
      <!-- Emoji button -->
      <button 
        class="message-input__button message-input__button--emoji" 
        @click="toggleEmojiPicker"
      >
        😀
      </button>
      
      <!-- Attachment button -->
      <label class="message-input__button message-input__button--attachment">
        <input 
          type="file" 
          ref="fileInput"
          multiple
          @change="handleFileInput"
          :accept="acceptedFileTypes"
          class="message-input__file-input"
        />
        📎
      </label>
      
      <!-- Text input -->
      <textarea
        ref="textarea"
        v-model="message"
        class="message-input__textarea"
        :placeholder="placeholder"
        @keydown.enter.prevent="handleEnterKey"
        @input="handleInput"
        @focus="handleFocus"
        @blur="handleBlur"
      ></textarea>
      
      <!-- Send button -->
      <button 
        class="message-input__button message-input__button--send" 
        :disabled="!canSend"
        @click="sendMessage"
      >
        <span v-if="sending">⏳</span>
        <span v-else>📤</span>
      </button>
    </div>
    
    <!-- Emoji picker (simplified version) -->
    <div v-if="showEmojiPicker" class="message-input__emoji-picker">
      <div class="message-input__emoji-container">
        <button 
          v-for="emoji in commonEmojis" 
          :key="emoji"
          class="message-input__emoji"
          @click="addEmoji(emoji)"
        >
          {{ emoji }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MessageInput',
  props: {
    /**
     * Placeholder text for the input
     */
    placeholder: {
      type: String,
      default: 'Type a message...'
    },
    /**
     * Maximum length of the message
     */
    maxLength: {
      type: Number,
      default: 2000
    },
    /**
     * Whether to emit typing events
     */
    emitTyping: {
      type: Boolean,
      default: true
    },
    /**
     * Delay between typing events in milliseconds
     */
    typingDelay: {
      type: Number,
      default: 2000
    },
    /**
     * Message to reply to
     */
    replyToMessage: {
      type: Object,
      default: null
    },
    /**
     * Accepted file types for attachments
     */
    acceptedFileTypes: {
      type: String,
      default: 'image/*,audio/*,video/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain'
    },
    /**
     * Maximum file size in bytes
     */
    maxFileSize: {
      type: Number,
      default: 10 * 1024 * 1024 // 10MB
    },
    /**
     * Maximum number of attachments
     */
    maxAttachments: {
      type: Number,
      default: 5
    },
    /**
     * Whether the input is disabled
     */
    disabled: {
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
  data() {
    return {
      message: '',
      attachments: [],
      showEmojiPicker: false,
      typingTimeout: null,
      isTyping: false,
      commonEmojis: ['😀', '😂', '😊', '😍', '🙂', '😎', '😢', '😡', '👍', '👎', '❤️', '🔥', '🎉', '🤔', '👏', '🙏']
    };
  },
  computed: {
    /**
     * Whether the send button should be enabled
     */
    canSend() {
      return !this.disabled && !this.sending && (this.message.trim().length > 0 || this.attachments.length > 0);
    }
  },
  methods: {
    /**
     * Handle Enter key press
     */
    handleEnterKey(event) {
      // Send message on Enter, but allow Shift+Enter for new line
      if (!event.shiftKey && this.canSend) {
        this.sendMessage();
      } else if (event.shiftKey) {
        // Insert a new line
        const textarea = this.$refs.textarea;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        
        this.message = this.message.substring(0, start) + '\n' + this.message.substring(end);
        
        // Move cursor to the right position
        this.$nextTick(() => {
          textarea.selectionStart = textarea.selectionEnd = start + 1;
        });
      }
    },
    /**
     * Handle input event
     */
    handleInput() {
      this.autoResizeTextarea();
      
      // Emit typing event
      if (this.emitTyping && !this.isTyping) {
        this.isTyping = true;
        this.$emit('typing');
        
        // Reset typing status after delay
        clearTimeout(this.typingTimeout);
        this.typingTimeout = setTimeout(() => {
          this.isTyping = false;
        }, this.typingDelay);
      }
    },
    /**
     * Handle focus event
     */
    handleFocus() {
      this.$emit('focus');
    },
    /**
     * Handle blur event
     */
    handleBlur() {
      this.$emit('blur');
    },
    /**
     * Auto-resize the textarea based on content
     */
    autoResizeTextarea() {
      const textarea = this.$refs.textarea;
      if (!textarea) return;
      
      // Reset height to auto to get the correct scrollHeight
      textarea.style.height = 'auto';
      
      // Set the height to the scrollHeight
      const newHeight = Math.min(textarea.scrollHeight, 150); // Max height of 150px
      textarea.style.height = `${newHeight}px`;
    },
    /**
     * Send the message
     */
    sendMessage() {
      if (!this.canSend) return;
      
      const messageData = {
        content: this.message.trim(),
        attachments: this.attachments.map(a => a.file),
        replyToId: this.replyToMessage ? this.replyToMessage.id : null
      };
      
      this.$emit('send', messageData);
      
      // Clear the input
      this.message = '';
      this.attachments = [];
      this.showEmojiPicker = false;
      
      // Reset textarea height
      this.$nextTick(() => {
        this.autoResizeTextarea();
      });
    },
    /**
     * Toggle the emoji picker
     */
    toggleEmojiPicker() {
      this.showEmojiPicker = !this.showEmojiPicker;
    },
    /**
     * Add an emoji to the message
     */
    addEmoji(emoji) {
      const textarea = this.$refs.textarea;
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      
      this.message = this.message.substring(0, start) + emoji + this.message.substring(end);
      
      // Move cursor after the inserted emoji
      this.$nextTick(() => {
        textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
        textarea.focus();
      });
      
      this.showEmojiPicker = false;
    },
    /**
     * Handle file input change
     */
    handleFileInput(event) {
      const files = Array.from(event.target.files);
      
      if (files.length === 0) return;
      
      // Check if adding these files would exceed the maximum
      if (this.attachments.length + files.length > this.maxAttachments) {
        this.$emit('error', `You can only attach up to ${this.maxAttachments} files`);
        return;
      }
      
      // Process each file
      files.forEach(file => {
        // Check file size
        if (file.size > this.maxFileSize) {
          this.$emit('error', `File ${file.name} exceeds the maximum size of ${this.formatFileSize(this.maxFileSize)}`);
          return;
        }
        
        this.attachments.push({
          file,
          id: Date.now() + Math.random().toString(36).substring(2, 9)
        });
      });
      
      // Reset the file input
      event.target.value = '';
    },
    /**
     * Remove an attachment
     */
    removeAttachment(index) {
      this.attachments.splice(index, 1);
    },
    /**
     * Check if a file is an image
     */
    isImageFile(file) {
      return file.type.startsWith('image/');
    },
    /**
     * Get a preview URL for a file
     */
    getFilePreview(file) {
      if (this.isImageFile(file)) {
        return URL.createObjectURL(file);
      }
      return null;
    },
    /**
     * Get an icon for a file type
     */
    getFileIcon(file) {
      const type = file.type;
      
      if (type.startsWith('image/')) return '🖼️';
      if (type.startsWith('audio/')) return '🎵';
      if (type.startsWith('video/')) return '🎬';
      if (type.includes('pdf')) return '📄';
      if (type.includes('word')) return '📝';
      if (type.includes('excel') || type.includes('spreadsheet')) return '📊';
      if (type.includes('text/')) return '📃';
      
      return '📎';
    },
    /**
     * Format file size for display
     */
    formatFileSize(bytes) {
      const units = ['B', 'KB', 'MB', 'GB'];
      let size = bytes;
      let unitIndex = 0;
      
      while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
      }
      
      return `${size.toFixed(1)} ${units[unitIndex]}`;
    },
    /**
     * Get a preview of the reply message
     */
    getReplyPreview() {
      if (!this.replyToMessage) return '';
      
      if (this.replyToMessage.messageType === 'attachment') {
        return '📎 Attachment';
      }
      
      const content = this.replyToMessage.content || '';
      if (content.length > 30) {
        return content.substring(0, 30) + '...';
      }
      
      return content;
    }
  },
  mounted() {
    this.autoResizeTextarea();
  },
  beforeUnmount() {
    clearTimeout(this.typingTimeout);
  }
};
</script>

<style scoped>
.message-input {
  display: flex;
  flex-direction: column;
  background-color: #fff;
  border-top: 1px solid #e0e0e0;
  padding: 10px;
  position: relative;
}

.message-input__reply {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background-color: #f5f5f5;
  border-radius: 8px;
  margin-bottom: 8px;
}

.message-input__reply-content {
  flex: 1;
  min-width: 0;
}

.message-input__reply-label {
  font-weight: 500;
  font-size: 0.8rem;
  color: #3490dc;
  margin-right: 4px;
}

.message-input__reply-text {
  font-size: 0.8rem;
  color: #666;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.message-input__reply-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  color: #999;
  cursor: pointer;
  padding: 0 0 0 8px;
}

.message-input__attachments {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 8px;
}

.message-input__attachment {
  position: relative;
  width: 80px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
  background-color: #f5f5f5;
}

.message-input__attachment-preview {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.message-input__attachment-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.message-input__attachment-file {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  padding: 4px;
}

.message-input__attachment-icon {
  font-size: 1.5rem;
  margin-bottom: 4px;
}

.message-input__attachment-name {
  font-size: 0.7rem;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  width: 100%;
}

.message-input__attachment-remove {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background-color: rgba(0, 0, 0, 0.5);
  color: white;
  border: none;
  font-size: 1rem;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.message-input__container {
  display: flex;
  align-items: flex-end;
  background-color: #f5f5f5;
  border-radius: 24px;
  padding: 8px 12px;
}

.message-input__button {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background-color: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 1.2rem;
  padding: 0;
  margin: 0 4px;
}

.message-input__button:hover {
  background-color: #e0e0e0;
}

.message-input__button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.message-input__file-input {
  display: none;
}

.message-input__textarea {
  flex: 1;
  min-height: 24px;
  max-height: 150px;
  padding: 6px 8px;
  border: none;
  background-color: transparent;
  resize: none;
  font-family: inherit;
  font-size: 0.95rem;
  line-height: 1.4;
  outline: none;
}

.message-input__emoji-picker {
  position: absolute;
  bottom: 100%;
  right: 10px;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  padding: 8px;
  margin-bottom: 8px;
  z-index: 10;
}

.message-input__emoji-container {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 4px;
}

.message-input__emoji {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  background: none;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.message-input__emoji:hover {
  background-color: #f0f0f0;
}
</style>