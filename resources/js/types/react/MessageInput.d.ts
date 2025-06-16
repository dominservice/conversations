import React from 'react';
import { Message } from './MessageList';

/**
 * Represents a file attachment in the input
 */
export interface InputAttachment {
  file: File;
  id: string;
}

/**
 * Represents message data to be sent
 */
export interface MessageData {
  content: string;
  attachments: File[];
  replyToId: string | number | null;
}

/**
 * Props for the MessageInput component
 */
export interface MessageInputProps {
  /**
   * Placeholder text for the input
   */
  placeholder?: string;
  
  /**
   * Maximum length of the message
   */
  maxLength?: number;
  
  /**
   * Whether to emit typing events
   */
  emitTyping?: boolean;
  
  /**
   * Delay between typing events in milliseconds
   */
  typingDelay?: number;
  
  /**
   * Message to reply to
   */
  replyToMessage?: Message | null;
  
  /**
   * Accepted file types for attachments
   */
  acceptedFileTypes?: string;
  
  /**
   * Maximum file size in bytes
   */
  maxFileSize?: number;
  
  /**
   * Maximum number of attachments
   */
  maxAttachments?: number;
  
  /**
   * Whether the input is disabled
   */
  disabled?: boolean;
  
  /**
   * Whether a message is currently being sent
   */
  sending?: boolean;
  
  /**
   * Callback when a message is sent
   */
  onSend?: (messageData: MessageData) => void;
  
  /**
   * Callback when the user is typing
   */
  onTyping?: () => void;
  
  /**
   * Callback when the input is focused
   */
  onFocus?: () => void;
  
  /**
   * Callback when the input loses focus
   */
  onBlur?: () => void;
  
  /**
   * Callback when the reply is cancelled
   */
  onCancelReply?: () => void;
  
  /**
   * Callback when an error occurs
   */
  onError?: (message: string) => void;
}

/**
 * MessageInput component
 */
declare const MessageInput: React.FC<MessageInputProps>;

export default MessageInput;