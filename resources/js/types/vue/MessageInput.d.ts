import { Message } from './MessageList';

/**
 * Represents a file attachment in the input
 */
export interface InputAttachment {
  file: File;
  id: string;
}

/**
 * Represents message data to be sent
 */
export interface MessageData {
  content: string;
  attachments: File[];
  replyToId: string | number | null;
}

/**
 * MessageInput component props
 */
export interface MessageInputProps {
  /**
   * Placeholder text for the input
   */
  placeholder?: string;
  
  /**
   * Maximum length of the message
   */
  maxLength?: number;
  
  /**
   * Whether to emit typing events
   */
  emitTyping?: boolean;
  
  /**
   * Delay between typing events in milliseconds
   */
  typingDelay?: number;
  
  /**
   * Message to reply to
   */
  replyToMessage?: Message | null;
  
  /**
   * Accepted file types for attachments
   */
  acceptedFileTypes?: string;
  
  /**
   * Maximum file size in bytes
   */
  maxFileSize?: number;
  
  /**
   * Maximum number of attachments
   */
  maxAttachments?: number;
  
  /**
   * Whether the input is disabled
   */
  disabled?: boolean;
  
  /**
   * Whether a message is currently being sent
   */
  sending?: boolean;
}

/**
 * MessageInput component events
 */
export interface MessageInputEvents {
  /**
   * Emitted when a message is sent
   */
  'send': (messageData: MessageData) => void;
  
  /**
   * Emitted when the user is typing
   */
  'typing': () => void;
  
  /**
   * Emitted when the input is focused
   */
  'focus': () => void;
  
  /**
   * Emitted when the input loses focus
   */
  'blur': () => void;
  
  /**
   * Emitted when the reply is cancelled
   */
  'cancel-reply': () => void;
  
  /**
   * Emitted when an error occurs
   */
  'error': (message: string) => void;
}

/**
 * MessageInput component
 */
declare const MessageInput: {
  props: MessageInputProps;
  emits: MessageInputEvents;
  
  data: {
    message: string;
    attachments: InputAttachment[];
    showEmojiPicker: boolean;
    typingTimeout: number | null;
    isTyping: boolean;
    commonEmojis: string[];
  };
  
  computed: {
    /**
     * Whether the send button should be enabled
     */
    canSend: boolean;
  };
  
  methods: {
    /**
     * Handle Enter key press
     */
    handleEnterKey(event: KeyboardEvent): void;
    
    /**
     * Handle input event
     */
    handleInput(): void;
    
    /**
     * Handle focus event
     */
    handleFocus(): void;
    
    /**
     * Handle blur event
     */
    handleBlur(): void;
    
    /**
     * Auto-resize the textarea based on content
     */
    autoResizeTextarea(): void;
    
    /**
     * Send the message
     */
    sendMessage(): void;
    
    /**
     * Toggle the emoji picker
     */
    toggleEmojiPicker(): void;
    
    /**
     * Add an emoji to the message
     */
    addEmoji(emoji: string): void;
    
    /**
     * Handle file input change
     */
    handleFileInput(event: Event): void;
    
    /**
     * Remove an attachment
     */
    removeAttachment(index: number): void;
    
    /**
     * Check if a file is an image
     */
    isImageFile(file: File): boolean;
    
    /**
     * Get a preview URL for a file
     */
    getFilePreview(file: File): string | null;
    
    /**
     * Get an icon for a file type
     */
    getFileIcon(file: File): string;
    
    /**
     * Format file size for display
     */
    formatFileSize(bytes: number): string;
    
    /**
     * Get a preview of the reply message
     */
    getReplyPreview(): string;
  };
};

export default MessageInput;