import { TypingUser } from './MessageList';

/**
 * TypingIndicator component props
 */
export interface TypingIndicatorProps {
  /**
   * Array of users who are currently typing
   */
  users: TypingUser[];
  
  /**
   * Maximum number of users to display by name
   */
  maxDisplayed?: number;
}

/**
 * TypingIndicator component
 */
declare const TypingIndicator: {
  props: TypingIndicatorProps;
  
  computed: {
    /**
     * Generate the typing text based on who is typing
     */
    typingText: string;
  };
  
  methods: {
    /**
     * Get initials from a name
     */
    getInitials(name: string | undefined): string;
  };
};

export default TypingIndicator;