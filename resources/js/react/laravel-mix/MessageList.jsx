import React, { useState, useRef, useEffect } from 'react';
import MessageItem from './MessageItem';
import TypingIndicator from './TypingIndicator';
import './MessageList.css';

const MessageList = ({
  messages = [],
  currentUserId = null,
  loading = false,
  loadingMore = false,
  loadingText = 'Loading messages...',
  emptyText = 'No messages yet',
  typingUsers = [],
  autoScroll = true,
  onReactionClick,
  onThreadClick,
  onMessageClick,
  onLoadMore
}) => {
  const messageListRef = useRef(null);
  const [scrolledToBottom, setScrolledToBottom] = useState(true);
  const [lastMessageSenderId, setLastMessageSenderId] = useState(null);
  const [lastMessageTimestamp, setLastMessageTimestamp] = useState(null);

  // Group messages by date
  const messageGroups = (() => {
    const groups = [];
    let currentDate = null;
    let currentGroup = null;

    messages.forEach(message => {
      const messageDate = new Date(message.createdAt);
      const dateString = formatMessageDate(messageDate);

      if (dateString !== currentDate) {
        currentDate = dateString;
        currentGroup = {
          date: dateString,
          messages: []
        };
        groups.push(currentGroup);
      }

      currentGroup.messages.push(message);
    });

    return groups;
  })();

  // Format message date for display
  const formatMessageDate = (date) => {
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
      return 'Today';
    } else if (date.toDateString() === yesterday.toDateString()) {
      return 'Yesterday';
    } else {
      return date.toLocaleDateString(undefined, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }
  };

  // Determine if avatar should be shown for this message
  const shouldShowAvatar = (message) => {
    // Always show avatar for the first message in a group
    if (lastMessageSenderId !== message.sender.id) {
      setLastMessageSenderId(message.sender.id);
      setLastMessageTimestamp(message.createdAt);
      return true;
    }

    // Show avatar if messages are more than 5 minutes apart
    const currentTime = new Date(message.createdAt).getTime();
    const lastTime = new Date(lastMessageTimestamp).getTime();
    const timeDiff = (currentTime - lastTime) / (1000 * 60); // difference in minutes

    setLastMessageTimestamp(message.createdAt);

    return timeDiff > 5;
  };

  // Determine if this message is consecutive (from same sender with small time gap)
  const isConsecutiveMessage = (message) => {
    return lastMessageSenderId === message.sender.id;
  };

  // Scroll to the bottom of the message list
  const scrollToBottom = () => {
    if (messageListRef.current) {
      messageListRef.current.scrollTop = messageListRef.current.scrollHeight;
    }
  };

  // Handle scroll event to detect when user scrolls away from bottom
  const handleScroll = () => {
    if (messageListRef.current) {
      const { scrollTop, scrollHeight, clientHeight } = messageListRef.current;
      const atBottom = scrollHeight - scrollTop - clientHeight < 50;

      setScrolledToBottom(atBottom);

      // Emit scroll to top event for infinite loading
      if (scrollTop < 50 && !loading && !loadingMore) {
        onLoadMore && onLoadMore();
      }
    }
  };

  // Scroll to bottom when messages change
  useEffect(() => {
    if (autoScroll && scrolledToBottom) {
      scrollToBottom();
    }
  }, [messages, autoScroll, scrolledToBottom]);

  // Set up scroll event listener
  useEffect(() => {
    const messageList = messageListRef.current;
    if (messageList) {
      messageList.addEventListener('scroll', handleScroll);
    }

    // Initial scroll to bottom
    scrollToBottom();

    return () => {
      if (messageList) {
        messageList.removeEventListener('scroll', handleScroll);
      }
    };
  }, []);

  return (
    <div className="message-list" ref={messageListRef}>
      {loading && messages.length === 0 ? (
        <div className="message-list__loading">
          <div className="message-list__loading-spinner"></div>
          <div className="message-list__loading-text">{loadingText}</div>
        </div>
      ) : messages.length === 0 ? (
        <div className="message-list__empty">
          {emptyText}
        </div>
      ) : (
        <div className="message-list__container">
          {loadingMore && (
            <div className="message-list__loading-more">
              <div className="message-list__loading-spinner"></div>
            </div>
          )}

          {messageGroups.map((group, index) => (
            <div key={index} className="message-list__group">
              <div className="message-list__date-divider">
                <span className="message-list__date-text">{group.date}</span>
              </div>

              {group.messages.map(message => (
                <MessageItem
                  key={message.id}
                  message={message}
                  currentUserId={currentUserId}
                  showAvatar={shouldShowAvatar(message)}
                  consecutive={isConsecutiveMessage(message)}
                  onReactionClick={onReactionClick}
                  onThreadClick={onThreadClick}
                  onMessageClick={onMessageClick}
                />
              ))}
            </div>
          ))}

          {typingUsers.length > 0 && (
            <div className="message-list__typing">
              <TypingIndicator users={typingUsers} />
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default MessageList;
