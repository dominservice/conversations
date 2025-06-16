import React from 'react';

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
 * Props for the ConversationList component
 */
export interface ConversationListProps {
  /**
   * Array of conversation objects
   */
  conversations?: Conversation[];
  
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
  
  /**
   * Callback when a conversation is selected
   */
  onSelectConversation: (conversation: Conversation) => void;
}

/**
 * ConversationList component
 */
declare const ConversationList: React.FC<ConversationListProps>;

export default ConversationList;