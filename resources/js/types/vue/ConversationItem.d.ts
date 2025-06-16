import { User, Conversation, Message } from './ConversationList';

/**
 * ConversationItem component props
 */
export interface ConversationItemProps {
  /**
   * Conversation object
   */
  conversation: Conversation;
  
  /**
   * Whether this conversation is currently active
   */
  active?: boolean;
  
  /**
   * Current user ID to determine if the sender is the current user
   */
  currentUserId?: string | number | null;
}

/**
 * ConversationItem component events
 */
export interface ConversationItemEvents {
  /**
   * Emitted when the conversation item is clicked
   */
  'click': (conversation: Conversation) => void;
}

/**
 * ConversationItem component
 */
declare const ConversationItem: {
  props: ConversationItemProps;
  emits: ConversationItemEvents;
  
  computed: {
    /**
     * Determine if the conversation is a group conversation
     */
    isGroup: boolean;
    
    /**
     * Get the other participant in a one-on-one conversation
     */
    otherParticipant: User | null;
    
    /**
     * Determine if the sender of the last message is the current user
     */
    isSelfSender: boolean;
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
     * Get a preview of the last message
     */
    getLastMessagePreview(): string;
  };
};

export default ConversationItem;