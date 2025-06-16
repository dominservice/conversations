import React from 'react';
import { TypingUser } from './MessageList';

/**
 * Props for the TypingIndicator component
 */
export interface TypingIndicatorProps {
  /**
   * Array of users who are currently typing
   */
  users?: TypingUser[];
  
  /**
   * Maximum number of users to display by name
   */
  maxDisplayed?: number;
}

/**
 * TypingIndicator component
 */
declare const TypingIndicator: React.FC<TypingIndicatorProps>;

export default TypingIndicator;