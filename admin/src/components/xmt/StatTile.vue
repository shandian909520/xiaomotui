<template>
  <div class="xmt-stat-tile" :style="{ '--tile-color': color }">
    <div class="tile-icon" :style="{ background: tint }">
      <el-icon :size="22" :color="color">
        <component :is="icon" />
      </el-icon>
    </div>
    <div class="tile-content">
      <span class="tile-label">{{ title }}</span>
      <strong class="tile-value">{{ formattedValue }}</strong>
      <span v-if="trend" class="tile-trend" :class="trendClass">
        <el-icon :size="12"><component :is="trendIcon" /></el-icon>
        {{ trendText }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowUp, ArrowDown, Minus } from '@element-plus/icons-vue'

const props = defineProps({
  title: String,
  value: [Number, String],
  icon: String,
  color: {
    type: String,
    default: '#7b50ff'
  },
  trend: String,
  trendValue: [Number, String],
  unit: String
})

const tint = computed(() => {
  const c = props.color
  return c + '18'
})

const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return props.value.toLocaleString()
  }
  return props.value
})

const trendIcon = computed(() => {
  if (props.trend === 'up') return ArrowUp
  if (props.trend === 'down') return ArrowDown
  return Minus
})

const trendClass = computed(() => `trend-${props.trend || 'flat'}`)

const trendText = computed(() => {
  if (!props.trendValue) return ''
  const sign = props.trend === 'up' ? '+' : props.trend === 'down' ? '-' : ''
  return `${sign}${props.trendValue}%`
})
</script>

<style lang="scss" scoped>
.xmt-stat-tile {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 20px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(91, 66, 138, 0.06);

  .tile-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .tile-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;

    .tile-label {
      font-size: 13px;
      color: #746b80;
    }

    .tile-value {
      font-size: 24px;
      font-weight: 700;
      color: #181224;
    }

    .tile-trend {
      display: flex;
      align-items: center;
      gap: 2px;
      font-size: 12px;

      &.trend-up {
        color: #20d482;
      }

      &.trend-down {
        color: #ff9860;
      }

      &.trend-flat {
        color: #746b80;
      }
    }
  }
}
</style>