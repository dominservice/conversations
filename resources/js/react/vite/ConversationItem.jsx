import React from 'react';
import './ConversationItem.css';

const ConversationItem = ({
  conversation,
  active = false,
  currentUserId = null,
  onClick
}) => {
  // Helper functions
  const getInitials = (name) => {
    if (!name) return '?';
    
    return name
      .split(' ')
      .map(word => word.charAt(0))
      .join('')
      .toUpperCase()
      .substring(0, 2);
  };
  
  const formatTime = (timestamp) => {
    if (!timestamp) return '';
    
    const date = new Date(timestamp);
    const now = new Date();
    const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) {
      // Today, show time
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } else if (diffDays === 1) {
      // Yesterday
      return 'Yesterday';
    } else if (diffDays < 7) {
      // This week, show day name
      return date.toLocaleDateString([], { weekday: 'short' });
    } else {
      // Older, show date
      return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
  };
  
  const getLastMessagePreview = () => {
    const lastMessage = conversation.lastMessage;
    
    if (!lastMessage) {
      return 'No messages yet';
    }
    
    if (lastMessage.messageType === 'attachment') {
      return '📎 Attachment';
    }
    
    // Truncate long messages
    const maxLength = 30;
    if (lastMessage.content && lastMessage.content.length > maxLength) {
      return lastMessage.content.substring(0, maxLength) + '...';
    }
    
    return lastMessage.content || '';
  };
  
  // Computed properties
  const isGroup = conversation.users && conversation.users.length > 2;
  
  const otherParticipant = (() => {
    if (!conversation.users || conversation.users.length === 0) {
      return null;
    }
    
    if (currentUserId) {
      return conversation.users.find(user => user.id !== currentUserId);
    }
    
    // If currentUserId is not provided, just return the first user
    return conversation.users[0];
  })();
  
  const isSelfSender = (() => {
    if (!conversation.lastMessage || !conversation.lastMessage.sender) {
      return false;
    }
    
    return conversation.lastMessage.sender.id === currentUserId;
  })();
  
  return (
    <div 
      className={`conversation-item ${active ? 'conversation-item--active' : ''} ${conversation.hasUnread ? 'conversation-item--unread' : ''}`}
      onClick={() => onClick(conversation)}
    >
      <div className="conversation-item__avatar">
        {isGroup ? (
          <div className="conversation-item__avatar-group">
            <span>{getInitials(conversation.title)}</span>
          </div>
        ) : (
          <div className="conversation-item__avatar-user">
            <span>{getInitials(otherParticipant?.name)}</span>
          </div>
        )}
      </div>
      <div className="conversation-item__content">
        <div className="conversation-item__header">
          <div className="conversation-item__title">
            {isGroup ? conversation.title : (otherParticipant?.name || 'Unknown User')}
          </div>
          <div className="conversation-item__time">
            {formatTime(conversation.lastMessage?.createdAt)}
          </div>
        </div>
        <div className="conversation-item__body">
          <div className="conversation-item__message">
            {conversation.lastMessage?.sender && (
              <span className="conversation-item__sender">
                {isSelfSender ? 'You: ' : ''}
              </span>
            )}
            <span className="conversation-item__text">
              {getLastMessagePreview()}
            </span>
          </div>
          {conversation.unreadCount > 0 && (
            <div className="conversation-item__badge">
              {conversation.unreadCount > 99 ? '99+' : conversation.unreadCount}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default ConversationItem;