import React from 'react';
import './TypingIndicator.css';

const TypingIndicator = ({
  users = [],
  maxDisplayed = 2
}) => {
  // Don't render if no users are typing
  if (users.length === 0) return null;

  // Get initials from a name
  const getInitials = (name) => {
    if (!name) return '?';
    
    return name
      .split(' ')
      .map(word => word.charAt(0))
      .join('')
      .toUpperCase()
      .substring(0, 2);
  };

  // Generate the typing text based on who is typing
  const typingText = (() => {
    if (users.length === 0) {
      return '';
    }
    
    if (users.length === 1) {
      return `${users[0].name} is typing`;
    }
    
    if (users.length <= maxDisplayed) {
      const names = users.map(user => user.name);
      const lastUser = names.pop();
      return `${names.join(', ')} and ${lastUser} are typing`;
    }
    
    return `${users.length} people are typing`;
  })();

  return (
    <div className="typing-indicator">
      {users.length === 1 && (
        <div className="typing-indicator__avatar">
          <div className="typing-indicator__avatar-image">
            <span>{getInitials(users[0].name)}</span>
          </div>
        </div>
      )}
      <div className="typing-indicator__content">
        <div className="typing-indicator__text">
          {typingText}
        </div>
        <div className="typing-indicator__dots">
          <span className="typing-indicator__dot"></span>
          <span className="typing-indicator__dot"></span>
          <span className="typing-indicator__dot"></span>
        </div>
      </div>
    </div>
  );
};

export default TypingIndicator;