<template>
  <div class="xmt-metric-group-card" :style="{ '--accent': color, '--tint': tint }">
    <div class="group-header">
      <span class="group-icon">{{ icon }}</span>
      <strong>{{ title }}</strong>
    </div>
    <div class="group-items">
      <div v-for="item in items" :key="item.label" class="group-item">
        <strong>{{ item.value }}</strong>
        <span>{{ item.label }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: String,
  icon: {
    type: String,
    default: '▣'
  },
  color: {
    type: String,
    default: '#7b50ff'
  },
  items: {
    type: Array,
    default: () => []
  }
})

const tint = computed(() => props.color + '18')
</script>

<style lang="scss" scoped>
.xmt-metric-group-card {
  min-height: 174px;
  padding: 14px 10px;
  border-radius: 13px;
  background: var(--tint);

  .group-header {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #181224;

    .group-icon {
      width: 20px;
      height: 20px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      color: #fff;
      background: var(--accent);
      font-size: 11px;
    }

    strong {
      font-size: 15px;
    }
  }

  .group-items {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
    margin-top: 13px;

    .group-item {
      height: 48px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.78);

      strong {
        font-size: 15px;
        color: #181224;
      }

      span {
        margin-top: 3px;
        font-size: 12px;
        color: #1c1728;
      }
    }
  }
}
</style>