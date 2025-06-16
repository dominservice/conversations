import { Message } from './MessageList';
import { MessageData } from './MessageInput';

/**
 * ThreadView component props
 */
export interface ThreadViewProps {
  /**
   * The parent message of the thread
   */
  parentMessage: Message;
  
  /**
   * Array of reply messages in the thread
   */
  replies?: Message[];
  
  /**
   * Current user ID
   */
  currentUserId?: string | number | null;
  
  /**
   * Whether the thread is loading
   */
  loading?: boolean;
  
  /**
   * Whether the input is disabled
   */
  inputDisabled?: boolean;
  
  /**
   * Whether a message is currently being sent
   */
  sending?: boolean;
}

/**
 * ThreadView component events
 */
export interface ThreadViewEvents {
  /**
   * Emitted when the thread view is closed
   */
  'close': () => void;
  
  /**
   * Emitted when a reaction is clicked
   */
  'reaction-click': (message: Message, reaction?: string) => void;
  
  /**
   * Emitted when a message is sent
   */
  'send': (messageData: MessageData) => void;
  
  /**
   * Emitted when the user is typing
   */
  'typing': () => void;
}

/**
 * ThreadView component
 */
declare const ThreadView: {
  props: ThreadViewProps;
  emits: ThreadViewEvents;
  
  computed: {
    /**
     * Get the number of replies
     */
    replyCount: number;
  };
  
  methods: {
    /**
     * Handle reaction click
     */
    handleReactionClick(message: Message, reaction?: string): void;
    
    /**
     * Handle send message
     */
    handleSend(messageData: MessageData): void;
    
    /**
     * Handle typing event
     */
    handleTyping(): void;
    
    /**
     * Scroll to the bottom of the messages container
     */
    scrollToBottom(): void;
  };
};

export default ThreadView;