import React, { useState, useRef, useEffect } from 'react';
import MessageItem from './MessageItem';
import MessageInput from './MessageInput';
import './ThreadView.css';

const ThreadView = ({
  parentMessage,
  replies = [],
  currentUserId = null,
  loading = false,
  inputDisabled = false,
  sending = false,
  onClose,
  onReactionClick,
  onSend,
  onTyping
}) => {
  const messagesContainerRef = useRef(null);
  
  // Computed property equivalent
  const replyCount = replies.length;
  
  // Handle reaction click
  const handleReactionClick = (message, reaction) => {
    onReactionClick && onReactionClick(message, reaction);
  };
  
  // Handle send message
  const handleSend = (messageData) => {
    // Add parent message ID to the message data
    const data = {
      ...messageData,
      parentId: parentMessage.id
    };
    
    onSend && onSend(data);
  };
  
  // Handle typing event
  const handleTyping = () => {
    onTyping && onTyping();
  };
  
  // Scroll to the bottom of the messages container
  const scrollToBottom = () => {
    const container = messagesContainerRef.current;
    if (container) {
      container.scrollTop = container.scrollHeight;
    }
  };
  
  // Scroll to bottom when replies change
  useEffect(() => {
    scrollToBottom();
  }, [replies]);
  
  // Scroll to bottom on mount
  useEffect(() => {
    scrollToBottom();
  }, []);
  
  return (
    <div className="thread-view">
      {/* Thread header */}
      <div className="thread-view__header">
        <h3 className="thread-view__title">Thread</h3>
        <button className="thread-view__close" onClick={onClose}>×</button>
      </div>
      
      {/* Parent message */}
      <div className="thread-view__parent">
        <MessageItem
          message={parentMessage}
          currentUserId={currentUserId}
          showAvatar={true}
          consecutive={false}
          onReactionClick={handleReactionClick}
        />
      </div>
      
      {/* Thread count */}
      <div className="thread-view__count">
        {replyCount} {replyCount === 1 ? 'reply' : 'replies'}
      </div>
      
      {/* Thread messages */}
      <div className="thread-view__messages" ref={messagesContainerRef}>
        {loading ? (
          <div className="thread-view__loading">
            <div className="thread-view__loading-spinner"></div>
            <div className="thread-view__loading-text">Loading replies...</div>
          </div>
        ) : replies.length === 0 ? (
          <div className="thread-view__empty">
            No replies yet
          </div>
        ) : (
          <div className="thread-view__replies">
            {replies.map(message => (
              <MessageItem
                key={message.id}
                message={message}
                currentUserId={currentUserId}
                showAvatar={true}
                consecutive={false}
                onReactionClick={handleReactionClick}
              />
            ))}
          </div>
        )}
      </div>
      
      {/* Reply input */}
      <div className="thread-view__input">
        <MessageInput
          placeholder="Reply to thread..."
          emitTyping={true}
          disabled={inputDisabled}
          sending={sending}
          onSend={handleSend}
          onTyping={handleTyping}
        />
      </div>
    </div>
  );
};

export default ThreadView;