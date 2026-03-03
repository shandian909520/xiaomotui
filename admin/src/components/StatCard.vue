<template>
  <el-card class="stat-card" shadow="hover" :body-style="{ padding: '20px' }">
    <div class="stat-card-content">
      <!-- 图标和标题 -->
      <div class="stat-header">
        <div class="stat-icon" :style="{ backgroundColor: iconColor }">
          <el-icon :size="24">
            <component :is="icon" />
          </el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-title">{{ title }}</div>
          <div class="stat-value">{{ formattedValue }}</div>
        </div>
      </div>

      <!-- 趋势指示器 -->
      <div class="stat-footer">
        <div class="stat-trend" :class="trendClass">
          <el-icon :size="16">
            <component :is="trendIcon" />
          </el-icon>
          <span>{{ trendText }}</span>
        </div>
        <div class="stat-description">{{ description }}</div>
      </div>
    </div>
  </el-card>
</template>

<script setup>
import { computed } from 'vue'
import {
  TrendCharts,
  ArrowUp,
  ArrowDown,
  Minus
} from '@element-plus/icons-vue'

const props = defineProps({
  // 卡片标题
  title: {
    type: String,
    required: true
  },
  // 数值
  value: {
    type: [Number, String],
    required: true
  },
  // 图标名称
  icon: {
    type: [String, Object],
    default: 'TrendCharts'
  },
  // 图标背景色
  iconColor: {
    type: String,
    default: '#409EFF'
  },
  // 趋势方向: 'up' | 'down' | 'flat'
  trend: {
    type: String,
    default: 'flat',
    validator: (value) => ['up', 'down', 'flat'].includes(value)
  },
  // 趋势百分比
  trendPercent: {
    type: [Number, String],
    default: 0
  },
  // 描述文字
  description: {
    type: String,
    default: '较上周期'
  },
  // 数值格式化函数
  formatter: {
    type: Function,
    default: null
  },
  // 单位
  unit: {
    type: String,
    default: ''
  }
})

// 格式化数值
const formattedValue = computed(() => {
  if (props.formatter) {
    return props.formatter(props.value)
  }

  // 如果是数字，添加千分位分隔符
  if (typeof props.value === 'number') {
    const formatted = props.value.toLocaleString()
    return props.unit ? `${formatted} ${props.unit}` : formatted
  }

  return props.unit ? `${props.value} ${props.unit}` : props.value
})

// 趋势图标
const trendIcon = computed(() => {
  switch (props.trend) {
    case 'up':
      return ArrowUp
    case 'down':
      return ArrowDown
    default:
      return Minus
  }
})

// 趋势样式类
const trendClass = computed(() => {
  return `trend-${props.trend}`
})

// 趋势文字
const trendText = computed(() => {
  const percent = Math.abs(Number(props.trendPercent))
  const sign = props.trend === 'up' ? '+' : props.trend === 'down' ? '-' : ''
  return `${sign}${percent}%`
})
</script>

<style lang="scss" scoped>
.stat-card {
  border-radius: 8px;
  transition: all 0.3s ease;

  &:hover {
    transform: translateY(-4px);
  }

  .stat-card-content {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .stat-header {
    display: flex;
    align-items: center;
    gap: 16px;

    .stat-icon {
      width: 56px;
      height: 56px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      flex-shrink: 0;
    }

    .stat-info {
      flex: 1;
      min-width: 0;

      .stat-title {
        font-size: 14px;
        color: #909399;
        margin-bottom: 8px;
      }

      .stat-value {
        font-size: 28px;
        font-weight: 600;
        color: #303133;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
    }
  }

  .stat-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 12px;
    border-top: 1px solid #EBEEF5;

    .stat-trend {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 14px;
      font-weight: 500;

      &.trend-up {
        color: #67C23A;
      }

      &.trend-down {
        color: #F56C6C;
      }

      &.trend-flat {
        color: #909399;
      }
    }

    .stat-description {
      font-size: 12px;
      color: #C0C4CC;
    }
  }
}

// 响应式设计
@media (max-width: 768px) {
  .stat-card {
    .stat-header {
      .stat-icon {
        width: 48px;
        height: 48px;
      }

      .stat-info {
        .stat-value {
          font-size: 24px;
        }
      }
    }
  }
}
</style>
