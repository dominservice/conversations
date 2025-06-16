import React, { useState } from 'react';
import ConversationItem from './ConversationItem';
import './ConversationList.css';

const ConversationList = ({
  conversations = [],
  activeConversationId = null,
  loading = false,
  loadingText = 'Loading conversations...',
  emptyText = 'No conversations found',
  onSelectConversation
}) => {
  return (
    <div className="conversation-list">
      {loading ? (
        <div className="conversation-list__loading">
          <div className="conversation-list__loading-spinner"></div>
          <div className="conversation-list__loading-text">{loadingText}</div>
        </div>
      ) : conversations.length === 0 ? (
        <div className="conversation-list__empty">
          {emptyText}
        </div>
      ) : (
        <div className="conversation-list__items">
          {conversations.map(conversation => (
            <ConversationItem
              key={conversation.uuid}
              conversation={conversation}
              active={activeConversationId === conversation.uuid}
              onClick={() => onSelectConversation(conversation)}
            />
          ))}
        </div>
      )}
    </div>
  );
};

export default ConversationList;