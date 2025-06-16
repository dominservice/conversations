import React, { useState } from 'react';
import './ReactionPicker.css';

const ReactionPicker = ({
  isOpen = false,
  selected = [],
  showCategories = true,
  onSelect
}) => {
  const [currentCategory, setCurrentCategory] = useState(0);
  
  const categories = [
    {
      icon: '😀',
      emojis: ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘']
    },
    {
      icon: '👍',
      emojis: ['👍', '👎', '👌', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '✋', '🤚', '👋', '👏', '🙌']
    },
    {
      icon: '❤️',
      emojis: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟']
    },
    {
      icon: '🎉',
      emojis: ['🎉', '🎊', '🎈', '🎂', '🎁', '🎄', '🎀', '🎗️', '🎟️', '🎫', '🎖️', '🏆', '🥇', '🥈', '🥉', '⚽', '🏀']
    },
    {
      icon: '🔥',
      emojis: ['🔥', '✨', '🌟', '💫', '⭐', '🌈', '☀️', '🌤️', '⛅', '🌥️', '☁️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '❄️']
    }
  ];
  
  // Common reactions for quick access
  const commonEmojis = ['👍', '❤️', '😂', '🎉', '😍', '🔥', '👏', '🙏', '🤔', '😢', '😡', '👎'];
  
  // Get emojis for the current category
  const emojis = showCategories ? categories[currentCategory].emojis : commonEmojis;
  
  // Check if an emoji is selected
  const isSelected = (emoji) => {
    return selected.includes(emoji);
  };
  
  // Select an emoji
  const selectEmoji = (emoji) => {
    onSelect && onSelect(emoji);
  };
  
  // Set the current category
  const handleSetCategory = (index) => {
    setCurrentCategory(index);
  };
  
  if (!isOpen) {
    return <div className="reaction-picker"></div>;
  }
  
  return (
    <div className="reaction-picker reaction-picker--open">
      <div className="reaction-picker__container">
        <div className="reaction-picker__emojis">
          {emojis.map(emoji => (
            <button
              key={emoji}
              className={`reaction-picker__emoji ${isSelected(emoji) ? 'reaction-picker__emoji--selected' : ''}`}
              onClick={() => selectEmoji(emoji)}
            >
              {emoji}
            </button>
          ))}
        </div>
        
        {showCategories && (
          <div className="reaction-picker__categories">
            {categories.map((category, index) => (
              <button
                key={index}
                className={`reaction-picker__category ${currentCategory === index ? 'reaction-picker__category--active' : ''}`}
                onClick={() => handleSetCategory(index)}
              >
                {category.icon}
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default ReactionPicker;