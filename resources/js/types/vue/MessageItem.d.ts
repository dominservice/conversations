import { Message, Attachment, Reaction } from './MessageList';

/**
 * Represents a grouped reaction with count
 */
export interface GroupedReaction {
  emoji: string;
  count: number;
  users: {
    id: string | number;
    name: string;
  }[];
}

/**
 * MessageItem component props
 */
export interface MessageItemProps {
  /**
   * Message object
   */
  message: Message;
  
  /**
   * Current user ID
   */
  currentUserId?: string | number | null;
  
  /**
   * Whether to show the avatar
   */
  showAvatar?: boolean;
  
  /**
   * Whether this message is consecutive (from same sender)
   */
  consecutive?: boolean;
}

/**
 * MessageItem component events
 */
export interface MessageItemEvents {
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
}

/**
 * MessageItem component
 */
declare const MessageItem: {
  props: MessageItemProps;
  emits: MessageItemEvents;
  
  computed: {
    /**
     * Whether this message was sent by the current user
     */
    isSelf: boolean;
    
    /**
     * Whether this message has attachments
     */
    hasAttachments: boolean;
    
    /**
     * Whether this message has reactions
     */
    hasReactions: boolean;
    
    /**
     * Whether this message has replies
     */
    hasReplies: boolean;
    
    /**
     * Whether this message is a reply to another message
     */
    isThreadReply: boolean;
    
    /**
     * Group reactions by emoji with count
     */
    groupedReactions: GroupedReaction[];
  };
  
  methods: {
    /**
     * Get initials from a name
     */
    getInitials(name: string | undefined): string;
    
    /**
     * Format the timestamp
     */
    formatTime(timestamp: string | undefined): string;
    
    /**
     * Check if the current user has reacted with a specific emoji
     */
    userHasReaction(emoji: string): boolean;
    
    /**
     * Open an attachment
     */
    openAttachment(attachment: Attachment): void;
    
    /**
     * Get the name of the user this message is replying to
     */
    getReplyToName(): string;
  };
};

export default MessageItem;