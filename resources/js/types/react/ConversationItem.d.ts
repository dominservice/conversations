import React from 'react';
import { Conversation } from './ConversationList';

/**
 * Props for the ConversationItem component
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
  
  /**
   * Callback when the conversation item is clicked
   */
  onClick: (conversation: Conversation) => void;
}

/**
 * ConversationItem component
 */
declare const ConversationItem: React.FC<ConversationItemProps>;

export default ConversationItem;