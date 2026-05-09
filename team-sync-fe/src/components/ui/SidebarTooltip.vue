<script setup>
defineProps({
  text: {
    type: String,
    required: true
  },
  position: {
    type: String,
    default: 'right'
  }
})
</script>

<template>
  <div 
    class="sidebar-tooltip"
    :class="[`sidebar-tooltip--${position}`]"
  >
    {{ text }}
  </div>
</template>

<style scoped>
.sidebar-tooltip {
  position: absolute;
  background: #0C1C3C;
  color: #fff;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
  z-index: 100;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.15s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.sidebar-tooltip--right {
  left: calc(100% + 12px);
  top: 50%;
  transform: translateY(-50%);
}

.sidebar-tooltip--right::before {
  content: '';
  position: absolute;
  left: -4px;
  top: 50%;
  transform: translateY(-50%);
  border: 5px solid transparent;
  border-right-color: #0C1C3C;
}

.sidebar-tooltip--left {
  right: calc(100% + 12px);
  top: 50%;
  transform: translateY(-50%);
}

.sidebar-tooltip--left::before {
  content: '';
  position: absolute;
  right: -4px;
  top: 50%;
  transform: translateY(-50%);
  border: 5px solid transparent;
  border-left-color: #0C1C3C;
}

.sidebar-tooltip--top {
  bottom: calc(100% + 12px);
  left: 50%;
  transform: translateX(-50%);
}

.sidebar-tooltip--top::before {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #0C1C3C;
}

.sidebar-tooltip--bottom {
  top: calc(100% + 12px);
  left: 50%;
  transform: translateX(-50%);
}

.sidebar-tooltip--bottom::before {
  content: '';
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-bottom-color: #0C1C3C;
}
</style>