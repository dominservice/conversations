<template>
  <div class="reaction-picker" :class="{ 'reaction-picker--open': isOpen }">
    <div v-if="isOpen" class="reaction-picker__container">
      <div class="reaction-picker__emojis">
        <button 
          v-for="emoji in emojis" 
          :key="emoji"
          class="reaction-picker__emoji"
          :class="{ 'reaction-picker__emoji--selected': isSelected(emoji) }"
          @click="selectEmoji(emoji)"
        >
          {{ emoji }}
        </button>
      </div>
      
      <div v-if="showCategories" class="reaction-picker__categories">
        <button 
          v-for="(category, index) in categories" 
          :key="index"
          class="reaction-picker__category"
          :class="{ 'reaction-picker__category--active': currentCategory === index }"
          @click="setCategory(index)"
        >
          {{ category.icon }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ReactionPicker',
  props: {
    /**
     * Whether the picker is open
     */
    isOpen: {
      type: Boolean,
      default: false
    },
    /**
     * Array of selected emojis
     */
    selected: {
      type: Array,
      default: () => []
    },
    /**
     * Whether to show emoji categories
     */
    showCategories: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      currentCategory: 0,
      categories: [
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
      ],
      // Common reactions for quick access
      commonEmojis: ['👍', '❤️', '😂', '🎉', '😍', '🔥', '👏', '🙏', '🤔', '😢', '😡', '👎']
    };
  },
  computed: {
    /**
     * Get emojis for the current category
     */
    emojis() {
      // If we're showing categories, use the current category's emojis
      if (this.showCategories) {
        return this.categories[this.currentCategory].emojis;
      }
      
      // Otherwise, just show common emojis
      return this.commonEmojis;
    }
  },
  methods: {
    /**
     * Check if an emoji is selected
     */
    isSelected(emoji) {
      return this.selected.includes(emoji);
    },
    /**
     * Select an emoji
     */
    selectEmoji(emoji) {
      this.$emit('select', emoji);
    },
    /**
     * Set the current category
     */
    setCategory(index) {
      this.currentCategory = index;
    }
  }
};
</script>

<style scoped>
.reaction-picker {
  position: relative;
}

.reaction-picker__container {
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  padding: 8px;
  margin-bottom: 8px;
  z-index: 10;
  width: 280px;
}

.reaction-picker__emojis {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 4px;
  margin-bottom: 8px;
}

.reaction-picker__emoji {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  background: none;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.reaction-picker__emoji:hover {
  background-color: #f0f0f0;
}

.reaction-picker__emoji--selected {
  background-color: #e3f2fd;
  border: 1px solid #3490dc;
}

.reaction-picker__categories {
  display: flex;
  justify-content: space-around;
  border-top: 1px solid #e0e0e0;
  padding-top: 8px;
}

.reaction-picker__category {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  background: none;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.reaction-picker__category:hover {
  background-color: #f0f0f0;
}

.reaction-picker__category--active {
  background-color: #e3f2fd;
  border: 1px solid #3490dc;
}
</style>