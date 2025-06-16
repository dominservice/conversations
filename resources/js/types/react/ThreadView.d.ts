import React from 'react';
import { Message } from './MessageList';
import { MessageData } from './MessageInput';

/**
 * Props for the ThreadView component
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
  
  /**
   * Callback when the thread view is closed
   */
  onClose?: () => void;
  
  /**
   * Callback when a reaction is clicked
   */
  onReactionClick?: (message: Message, reaction?: string) => void;
  
  /**
   * Callback when a message is sent
   */
  onSend?: (messageData: MessageData) => void;
  
  /**
   * Callback when the user is typing
   */
  onTyping?: () => void;
}

/**
 * ThreadView component
 */
declare const ThreadView: React.FC<ThreadViewProps>;

export default ThreadView;