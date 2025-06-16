<template>
  <div class="typing-indicator" v-if="users.length > 0">
    <div class="typing-indicator__avatar" v-if="users.length === 1">
      <div class="typing-indicator__avatar-image">
        <span>{{ getInitials(users[0].name) }}</span>
      </div>
    </div>
    <div class="typing-indicator__content">
      <div class="typing-indicator__text">
        {{ typingText }}
      </div>
      <div class="typing-indicator__dots">
        <span class="typing-indicator__dot"></span>
        <span class="typing-indicator__dot"></span>
        <span class="typing-indicator__dot"></span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TypingIndicator',
  props: {
    /**
     * Array of users who are currently typing
     */
    users: {
      type: Array,
      default: () => []
    },
    /**
     * Maximum number of users to display by name
     */
    maxDisplayed: {
      type: Number,
      default: 2
    }
  },
  computed: {
    /**
     * Generate the typing text based on who is typing
     */
    typingText() {
      if (this.users.length === 0) {
        return '';
      }
      
      if (this.users.length === 1) {
        return `${this.users[0].name} is typing`;
      }
      
      if (this.users.length <= this.maxDisplayed) {
        const names = this.users.map(user => user.name);
        const lastUser = names.pop();
        return `${names.join(', ')} and ${lastUser} are typing`;
      }
      
      return `${this.users.length} people are typing`;
    }
  },
  methods: {
    /**
     * Get initials from a name
     */
    getInitials(name) {
      if (!name) return '?';
      
      return name
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .substring(0, 2);
    }
  }
};
</script>

<style scoped>
.typing-indicator {
  display: flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 16px;
  background-color: #f5f5f5;
  max-width: 80%;
}

.typing-indicator__avatar {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  margin-right: 8px;
}

.typing-indicator__avatar-image {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background-color: #3490dc;
  color: white;
  font-weight: bold;
  font-size: 0.7rem;
}

.typing-indicator__content {
  display: flex;
  align-items: center;
}

.typing-indicator__text {
  font-size: 0.8rem;
  color: #666;
  margin-right: 8px;
}

.typing-indicator__dots {
  display: flex;
  align-items: center;
}

.typing-indicator__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #999;
  margin: 0 1px;
  animation: typing-animation 1.5s infinite ease-in-out;
}

.typing-indicator__dot:nth-child(1) {
  animation-delay: 0s;
}

.typing-indicator__dot:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-indicator__dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typing-animation {
  0%, 60%, 100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-4px);
  }
}
</style>