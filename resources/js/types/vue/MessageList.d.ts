import { User } from './ConversationList';

/**
 * Represents a message in a conversation
 */
export interface Message {
  id: string | number;
  content?: string;
  messageType?: string;
  createdAt: string;
  sender: User;
  parent?: Message;
  replies?: Message[];
  attachments?: Attachment[];
  reactions?: Reaction[];
  hasBeenEdited?: boolean;
  isReply?: boolean;
  hasReplies?: boolean;
  hasAttachments?: boolean;
  hasReactions?: boolean;
}

/**
 * Represents an attachment to a message
 */
export interface Attachment {
  id: string | number;
  filename: string;
  originalFilename: string;
  mimeType: string;
  extension: string;
  type: string;
  size: number;
  url: string;
  isImage?: boolean;
  thumbnailUrl?: string;
}

/**
 * Represents a reaction to a message
 */
export interface Reaction {
  id: string | number;
  reaction: string;
  user: User;
}

/**
 * Represents a user who is typing
 */
export interface TypingUser {
  id: string | number;
  name: string;
}

/**
 * Represents a group of messages by date
 */
export interface MessageGroup {
  date: string;
  messages: Message[];
}

/**
 * MessageList component props
 */
export interface MessageListProps {
  /**
   * Array of message objects
   */
  messages: Message[];
  
  /**
   * Current user ID
   */
  currentUserId?: string | number | null;
  
  /**
   * Whether messages are currently loading
   */
  loading?: boolean;
  
  /**
   * Whether more messages are being loaded (for infinite scroll)
   */
  loadingMore?: boolean;
  
  /**
   * Text to display while loading
   */
  loadingText?: string;
  
  /**
   * Text to display when there are no messages
   */
  emptyText?: string;
  
  /**
   * Array of users who are currently typing
   */
  typingUsers?: TypingUser[];
  
  /**
   * Whether to automatically scroll to the bottom on new messages
   */
  autoScroll?: boolean;
}

/**
 * MessageList component events
 */
export interface MessageListEvents {
  /**
   * Emitted when a reaction is clicked
   */
  'reaction-click': (message: Message, reaction?: string) => void;
  
  /**
   * Emitted when a thread is clicked
   */
  'thread-click': (message: Message) => void;
  
  /**
   * Emitted when a message is clicked
   */
  'message-click': (message: Message) => void;
  
  /**
   * Emitted when the user scrolls to the top and more messages should be loaded
   */
  'load-more': () => void;
}

/**
 * MessageList component
 */
declare const MessageList: {
  props: MessageListProps;
  emits: MessageListEvents;
  
  data: {
    lastMessageSenderId: string | number | null;
    lastMessageTimestamp: string | null;
    scrolledToBottom: boolean;
  };
  
  computed: {
    /**
     * Group messages by date
     */
    messageGroups: MessageGroup[];
  };
  
  methods: {
    /**
     * Format message date for display
     */
    formatMessageDate(date: Date): string;
    
    /**
     * Determine if avatar should be shown for this message
     */
    shouldShowAvatar(message: Message): boolean;
    
    /**
     * Determine if this message is consecutive (from same sender with small time gap)
     */
    isConsecutiveMessage(message: Message): boolean;
    
    /**
     * Handle reaction click event
     */
    handleReactionClick(message: Message, reaction?: string): void;
    
    /**
     * Handle thread click event
     */
    handleThreadClick(message: Message): void;
    
    /**
     * Handle message click event
     */
    handleMessageClick(message: Message): void;
    
    /**
     * Scroll to the bottom of the message list
     */
    scrollToBottom(): void;
    
    /**
     * Handle scroll event to detect when user scrolls away from bottom
     */
    handleScroll(): void;
  };
};

export default MessageList;