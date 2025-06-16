import React, { useState, useRef, useEffect } from 'react';
import './MessageInput.css';

const MessageInput = ({
  placeholder = 'Type a message...',
  maxLength = 2000,
  emitTyping = true,
  typingDelay = 2000,
  replyToMessage = null,
  acceptedFileTypes = 'image/*,audio/*,video/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain',
  maxFileSize = 10 * 1024 * 1024, // 10MB
  maxAttachments = 5,
  disabled = false,
  sending = false,
  onSend,
  onTyping,
  onFocus,
  onBlur,
  onCancelReply,
  onError
}) => {
  const [message, setMessage] = useState('');
  const [attachments, setAttachments] = useState([]);
  const [showEmojiPicker, setShowEmojiPicker] = useState(false);
  const [isTyping, setIsTyping] = useState(false);
  const typingTimeoutRef = useRef(null);
  const textareaRef = useRef(null);
  const fileInputRef = useRef(null);
  
  const commonEmojis = ['😀', '😂', '😊', '😍', '🙂', '😎', '😢', '😡', '👍', '👎', '❤️', '🔥', '🎉', '🤔', '👏', '🙏'];
  
  // Computed property equivalent
  const canSend = !disabled && !sending && (message.trim().length > 0 || attachments.length > 0);
  
  // Auto-resize textarea
  useEffect(() => {
    autoResizeTextarea();
  }, [message]);
  
  // Clean up typing timeout on unmount
  useEffect(() => {
    return () => {
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, []);
  
  const autoResizeTextarea = () => {
    const textarea = textareaRef.current;
    if (!textarea) return;
    
    // Reset height to auto to get the correct scrollHeight
    textarea.style.height = 'auto';
    
    // Set the height to the scrollHeight
    const newHeight = Math.min(textarea.scrollHeight, 150); // Max height of 150px
    textarea.style.height = `${newHeight}px`;
  };
  
  const handleEnterKey = (event) => {
    // Send message on Enter, but allow Shift+Enter for new line
    if (!event.shiftKey && canSend) {
      event.preventDefault();
      sendMessage();
    } else if (event.shiftKey) {
      // Insert a new line is handled by the textarea
    }
  };
  
  const handleInput = () => {
    // Emit typing event
    if (emitTyping && !isTyping) {
      setIsTyping(true);
      onTyping && onTyping();
      
      // Reset typing status after delay
      clearTimeout(typingTimeoutRef.current);
      typingTimeoutRef.current = setTimeout(() => {
        setIsTyping(false);
      }, typingDelay);
    }
  };
  
  const handleFocus = () => {
    onFocus && onFocus();
  };
  
  const handleBlur = () => {
    onBlur && onBlur();
  };
  
  const sendMessage = () => {
    if (!canSend) return;
    
    const messageData = {
      content: message.trim(),
      attachments: attachments.map(a => a.file),
      replyToId: replyToMessage ? replyToMessage.id : null
    };
    
    onSend && onSend(messageData);
    
    // Clear the input
    setMessage('');
    setAttachments([]);
    setShowEmojiPicker(false);
    
    // Reset textarea height
    setTimeout(() => {
      autoResizeTextarea();
    }, 0);
  };
  
  const toggleEmojiPicker = () => {
    setShowEmojiPicker(!showEmojiPicker);
  };
  
  const addEmoji = (emoji) => {
    const textarea = textareaRef.current;
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    
    const newMessage = message.substring(0, start) + emoji + message.substring(end);
    setMessage(newMessage);
    
    // Move cursor after the inserted emoji
    setTimeout(() => {
      textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
      textarea.focus();
    }, 0);
    
    setShowEmojiPicker(false);
  };
  
  const handleFileInput = (event) => {
    const files = Array.from(event.target.files);
    
    if (files.length === 0) return;
    
    // Check if adding these files would exceed the maximum
    if (attachments.length + files.length > maxAttachments) {
      onError && onError(`You can only attach up to ${maxAttachments} files`);
      return;
    }
    
    // Process each file
    const newAttachments = [...attachments];
    
    files.forEach(file => {
      // Check file size
      if (file.size > maxFileSize) {
        onError && onError(`File ${file.name} exceeds the maximum size of ${formatFileSize(maxFileSize)}`);
        return;
      }
      
      newAttachments.push({
        file,
        id: Date.now() + Math.random().toString(36).substring(2, 9)
      });
    });
    
    setAttachments(newAttachments);
    
    // Reset the file input
    event.target.value = '';
  };
  
  const removeAttachment = (index) => {
    const newAttachments = [...attachments];
    newAttachments.splice(index, 1);
    setAttachments(newAttachments);
  };
  
  const isImageFile = (file) => {
    return file.type.startsWith('image/');
  };
  
  const getFilePreview = (file) => {
    if (isImageFile(file)) {
      return URL.createObjectURL(file);
    }
    return null;
  };
  
  const getFileIcon = (file) => {
    const type = file.type;
    
    if (type.startsWith('image/')) return '🖼️';
    if (type.startsWith('audio/')) return '🎵';
    if (type.startsWith('video/')) return '🎬';
    if (type.includes('pdf')) return '📄';
    if (type.includes('word')) return '📝';
    if (type.includes('excel') || type.includes('spreadsheet')) return '📊';
    if (type.includes('text/')) return '📃';
    
    return '📎';
  };
  
  const formatFileSize = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex++;
    }
    
    return `${size.toFixed(1)} ${units[unitIndex]}`;
  };
  
  const getReplyPreview = () => {
    if (!replyToMessage) return '';
    
    if (replyToMessage.messageType === 'attachment') {
      return '📎 Attachment';
    }
    
    const content = replyToMessage.content || '';
    if (content.length > 30) {
      return content.substring(0, 30) + '...';
    }
    
    return content;
  };
  
  return (
    <div className="message-input">
      {/* Reply indicator */}
      {replyToMessage && (
        <div className="message-input__reply">
          <div className="message-input__reply-content">
            <span className="message-input__reply-label">Replying to {replyToMessage.sender.name}</span>
            <span className="message-input__reply-text">{getReplyPreview()}</span>
          </div>
          <button className="message-input__reply-close" onClick={onCancelReply}>×</button>
        </div>
      )}
      
      {/* Attachment preview */}
      {attachments.length > 0 && (
        <div className="message-input__attachments">
          {attachments.map((attachment, index) => (
            <div key={attachment.id} className="message-input__attachment">
              <div className="message-input__attachment-preview">
                {isImageFile(attachment.file) ? (
                  <img
                    src={getFilePreview(attachment.file)}
                    className="message-input__attachment-image"
                    alt="Attachment preview"
                  />
                ) : (
                  <div className="message-input__attachment-file">
                    <span className="message-input__attachment-icon">
                      {getFileIcon(attachment.file)}
                    </span>
                    <span className="message-input__attachment-name">{attachment.file.name}</span>
                  </div>
                )}
              </div>
              <button
                className="message-input__attachment-remove"
                onClick={() => removeAttachment(index)}
              >
                ×
              </button>
            </div>
          ))}
        </div>
      )}
      
      {/* Input area */}
      <div className="message-input__container">
        {/* Emoji button */}
        <button
          className="message-input__button message-input__button--emoji"
          onClick={toggleEmojiPicker}
        >
          😀
        </button>
        
        {/* Attachment button */}
        <label className="message-input__button message-input__button--attachment">
          <input
            type="file"
            ref={fileInputRef}
            multiple
            onChange={handleFileInput}
            accept={acceptedFileTypes}
            className="message-input__file-input"
          />
          📎
        </label>
        
        {/* Text input */}
        <textarea
          ref={textareaRef}
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          className="message-input__textarea"
          placeholder={placeholder}
          onKeyDown={handleEnterKey}
          onInput={handleInput}
          onFocus={handleFocus}
          onBlur={handleBlur}
        ></textarea>
        
        {/* Send button */}
        <button
          className="message-input__button message-input__button--send"
          disabled={!canSend}
          onClick={sendMessage}
        >
          {sending ? <span>⏳</span> : <span>📤</span>}
        </button>
      </div>
      
      {/* Emoji picker (simplified version) */}
      {showEmojiPicker && (
        <div className="message-input__emoji-picker">
          <div className="message-input__emoji-container">
            {commonEmojis.map(emoji => (
              <button
                key={emoji}
                className="message-input__emoji"
                onClick={() => addEmoji(emoji)}
              >
                {emoji}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default MessageInput;