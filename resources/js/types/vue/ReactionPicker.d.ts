/**
 * Represents an emoji category
 */
export interface EmojiCategory {
  icon: string;
  emojis: string[];
}

/**
 * ReactionPicker component props
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
}

/**
 * ReactionPicker component events
 */
export interface ReactionPickerEvents {
  /**
   * Emitted when an emoji is selected
   */
  'select': (emoji: string) => void;
}

/**
 * ReactionPicker component
 */
declare const ReactionPicker: {
  props: ReactionPickerProps;
  emits: ReactionPickerEvents;
  
  data: {
    currentCategory: number;
    categories: EmojiCategory[];
    commonEmojis: string[];
  };
  
  computed: {
    /**
     * Get emojis for the current category
     */
    emojis: string[];
  };
  
  methods: {
    /**
     * Check if an emoji is selected
     */
    isSelected(emoji: string): boolean;
    
    /**
     * Select an emoji
     */
    selectEmoji(emoji: string): void;
    
    /**
     * Set the current category
     */
    setCategory(index: number): void;
  };
};

export default ReactionPicker;