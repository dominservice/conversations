import React from 'react';
import { Message, Reaction } from './MessageList';

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
 * Props for the MessageItem component
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
  
  /**
   * Callback when a reaction is clicked
   */
  onReactionClick?: (message: Message, reaction?: string) => void;
  
  /**
   * Callback when a thread is clicked
   */
  onThreadClick?: (message: Message) => void;
  
  /**
   * Callback when a message is clicked
   */
  onMessageClick?: (message: Message) => void;
}

/**
 * MessageItem component
 */
declare const MessageItem: React.FC<MessageItemProps>;

export default MessageItem;