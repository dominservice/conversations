/**
 * Represents a user in the conversation
 */
export interface User {
  id: string | number;
  name: string;
  email?: string;
}

/**
 * Represents a message in the conversation
 */
export interface Message {
  id: string | number;
  content?: string;
  messageType?: string;
  createdAt: string;
  sender: User;
}

/**
 * Represents a conversation
 */
export interface Conversation {
  uuid: string;
  title?: string;
  unreadCount: number;
  hasUnread: boolean;
  createdAt: string;
  updatedAt: string;
  users: User[];
  lastMessage?: Message;
}

/**
 * ConversationList component props
 */
export interface ConversationListProps {
  /**
   * Array of conversation objects
   */
  conversations: Conversation[];

  /**
   * ID of the currently active conversation
   */
  activeConversationId?: string | null;

  /**
   * Whether the conversations are currently loading
   */
  loading?: boolean;

  /**
   * Text to display while loading
   */
  loadingText?: string;

  /**
   * Text to display when there are no conversations
   */
  emptyText?: string;
}

/**
 * ConversationList component events
 */
export interface ConversationListEvents {
  /**
   * Emitted when a conversation is selected
   */
  'select-conversation': (conversation: Conversation) => void;
}

/**
 * ConversationList component
 */
declare const ConversationList: {
  props: ConversationListProps;
  emits: ConversationListEvents;

  /**
   * Select a conversation
   */
  selectConversation(conversation: Conversation): void;
};

export default ConversationList;
