import React from 'react';
import './MessageItem.css';

const MessageItem = ({
  message,
  currentUserId = null,
  showAvatar = true,
  consecutive = false,
  onReactionClick,
  onThreadClick,
  onMessageClick
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
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  };
  
  const openAttachment = (attachment) => {
    window.open(attachment.url, '_blank');
  };
  
  const getReplyToName = () => {
    if (!isThreadReply || !message.parent || !message.parent.sender) {
      return 'a message';
    }
    
    return message.parent.sender.name;
  };
  
  const userHasReaction = (emoji) => {
    if (!hasReactions || !currentUserId) return false;
    
    return message.reactions.some(
      reaction => reaction.reaction === emoji && reaction.user.id === currentUserId
    );
  };
  
  // Computed properties
  const isSelf = message.sender && message.sender.id === currentUserId;
  const hasAttachments = message.attachments && message.attachments.length > 0;
  const hasReactions = message.reactions && message.reactions.length > 0;
  const hasReplies = message.replies && message.replies.length > 0;
  const isThreadReply = message.isReply;
  
  // Group reactions by emoji with count
  const groupedReactions = (() => {
    if (!hasReactions) return [];
    
    const grouped = {};
    
    message.reactions.forEach(reaction => {
      if (!grouped[reaction.reaction]) {
        grouped[reaction.reaction] = {
          emoji: reaction.reaction,
          count: 0,
          users: []
        };
      }
      
      grouped[reaction.reaction].count++;
      grouped[reaction.reaction].users.push(reaction.user);
    });
    
    return Object.values(grouped);
  })();
  
  return (
    <div 
      className={`message-item ${isSelf ? 'message-item--self' : ''} ${consecutive && !isThreadReply ? 'message-item--consecutive' : ''} ${isThreadReply ? 'message-item--thread-reply' : ''}`}
      onClick={() => onMessageClick && onMessageClick(message)}
    >
      {showAvatar && !isSelf ? (
        <div className="message-item__avatar">
          <div className="message-item__avatar-image">
            <span>{getInitials(message.sender.name)}</span>
          </div>
        </div>
      ) : !isSelf ? (
        <div className="message-item__avatar message-item__avatar--placeholder"></div>
      ) : null}
      
      <div className="message-item__content">
        {showAvatar && !consecutive && !isSelf && (
          <div className="message-item__sender">
            {message.sender.name}
          </div>
        )}
        
        {isThreadReply && (
          <div className="message-item__reply-info">
            <span>Replying to {getReplyToName()}</span>
          </div>
        )}
        
        <div className={`message-item__bubble ${isSelf ? 'message-item__bubble--self' : ''}`}>
          {/* Text content */}
          {message.content && (
            <div className="message-item__text">
              {message.content}
            </div>
          )}
          
          {/* Attachments */}
          {hasAttachments && (
            <div className="message-item__attachments">
              {message.attachments.map(attachment => (
                <div 
                  key={attachment.id}
                  className={`message-item__attachment message-item__attachment--${attachment.type}`}
                >
                  {attachment.isImage ? (
                    <img 
                      src={attachment.url}
                      alt={attachment.originalFilename}
                      className="message-item__attachment-image"
                      onClick={(e) => {
                        e.stopPropagation();
                        openAttachment(attachment);
                      }}
                    />
                  ) : (
                    <div 
                      className="message-item__attachment-file"
                      onClick={(e) => {
                        e.stopPropagation();
                        openAttachment(attachment);
                      }}
                    >
                      <div className="message-item__attachment-icon">
                        {attachment.type === 'document' ? (
                          <span>📄</span>
                        ) : attachment.type === 'audio' ? (
                          <span>🎵</span>
                        ) : attachment.type === 'video' ? (
                          <span>🎬</span>
                        ) : (
                          <span>📎</span>
                        )}
                      </div>
                      <div className="message-item__attachment-info">
                        <div className="message-item__attachment-filename">{attachment.originalFilename}</div>
                        <div className="message-item__attachment-size">{attachment.humanSize}</div>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
          
          {/* Edited indicator */}
          {message.hasBeenEdited && (
            <div className="message-item__edited">
              (edited)
            </div>
          )}
        </div>
        
        {/* Message actions */}
        <div className="message-item__actions">
          <button 
            className="message-item__action message-item__action--reaction"
            onClick={(e) => {
              e.stopPropagation();
              onReactionClick && onReactionClick(message);
            }}
          >
            😀
          </button>
          {!isThreadReply && (
            <button 
              className="message-item__action message-item__action--thread"
              onClick={(e) => {
                e.stopPropagation();
                onThreadClick && onThreadClick(message);
              }}
            >
              💬
            </button>
          )}
        </div>
        
        {/* Reactions */}
        {hasReactions && (
          <div className="message-item__reactions">
            {groupedReactions.map((reaction, index) => (
              <div 
                key={index}
                className={`message-item__reaction ${userHasReaction(reaction.emoji) ? 'message-item__reaction--selected' : ''}`}
                onClick={(e) => {
                  e.stopPropagation();
                  onReactionClick && onReactionClick(message, reaction.emoji);
                }}
              >
                <span className="message-item__reaction-emoji">{reaction.emoji}</span>
                <span className="message-item__reaction-count">{reaction.count}</span>
              </div>
            ))}
          </div>
        )}
        
        {/* Thread indicator */}
        {hasReplies && (
          <div 
            className="message-item__thread-indicator"
            onClick={(e) => {
              e.stopPropagation();
              onThreadClick && onThreadClick(message);
            }}
          >
            {message.replies.length} {message.replies.length === 1 ? 'reply' : 'replies'}
          </div>
        )}
        
        {/* Timestamp */}
        <div className={`message-item__time ${isSelf ? 'message-item__time--self' : ''}`}>
          {formatTime(message.createdAt)}
        </div>
      </div>
    </div>
  );
};

export default MessageItem;