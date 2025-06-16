import React from 'react';

/**
 * Represents an emoji category
 */
export interface EmojiCategory {
  icon: string;
  emojis: string[];
}

/**
 * Props for the ReactionPicker component
 */
export interface ReactionPickerProps {
  /**
   * Whether the picker is open
   */
  isOpen?: boolean;
  
  /**
   * Array of selected emojis
   */
  selected?: string[];
  
  /**
   * Whether to show emoji categories
   */
  showCategories?: boolean;
  
  /**
   * Callback when an emoji is selected
   */
  onSelect?: (emoji: string) => void;
}

/**
 * ReactionPicker component
 */
declare const ReactionPicker: React.FC<ReactionPickerProps>;

export default ReactionPicker;