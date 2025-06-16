import React from 'react';
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
  humanSize?: string;
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
 * Props for the MessageList component
 */
export interface MessageListProps {
  /**
   * Array of message objects
   */
  messages?: Message[];
  
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
  
  /**
   * Callback when the user scrolls to the top and more messages should be loaded
   */
  onLoadMore?: () => void;
}

/**
 * MessageList component
 */
declare const MessageList: React.FC<MessageListProps>;

export default MessageList;