<template>
  <view class="schedule-picker-wrapper">
    <view class="section-header">
      <text class="section-icon">⏰</text>
      <text class="section-title">定时发布</text>
    </view>

    <view class="schedule-toggle">
      <view class="toggle-info">
        <text class="toggle-title">启用定时发布</text>
        <text class="toggle-tip">在指定时间自动发布内容</text>
      </view>
      <switch
        :checked="isScheduled"
        @change="handleToggle"
        color="#6366f1"
      />
    </view>

    <view class="schedule-picker" v-if="isScheduled">
      <view class="picker-item">
        <view class="picker-label">
          <text class="label-icon">📅</text>
          <text class="label-text">发布日期</text>
        </view>
        <picker
          mode="date"
          :value="scheduleDate"
          :start="minDate"
          @change="handleDateChange"
        >
          <view class="picker-value">
            {{ scheduleDate || '选择日期' }}
          </view>
        </picker>
      </view>

      <view class="picker-item">
        <view class="picker-label">
          <text class="label-icon">🕐</text>
          <text class="label-text">发布时间</text>
        </view>
        <picker
          mode="time"
          :value="scheduleTime"
          @change="handleTimeChange"
        >
          <view class="picker-value">
            {{ scheduleTime || '选择时间' }}
          </view>
        </picker>
      </view>

      <view class="schedule-preview" v-if="scheduleDate && scheduleTime">
        <text class="preview-icon">⏰</text>
        <text class="preview-text">将在 {{ formatScheduleTime() }} 自动发布</text>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  name: 'SchedulePicker',

  props: {
    isScheduled: {
      type: Boolean,
      default: false
    },
    scheduleDate: {
      type: String,
      default: ''
    },
    scheduleTime: {
      type: String,
      default: ''
    }
  },

  computed: {
    minDate() {
      const today = new Date()
      const year = today.getFullYear()
      const month = String(today.getMonth() + 1).padStart(2, '0')
      const day = String(today.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  },

  methods: {
    handleToggle(e) {
      this.$emit('toggle', e.detail.value)
    },

    handleDateChange(e) {
      this.$emit('change', {
        date: e.detail.value,
        time: this.scheduleTime
      })
    },

    handleTimeChange(e) {
      this.$emit('change', {
        date: this.scheduleDate,
        time: e.detail.value
      })
    },

    formatScheduleTime() {
      if (!this.scheduleDate || !this.scheduleTime) return ''

      const dateObj = new Date(`${this.scheduleDate} ${this.scheduleTime}`)
      const now = new Date()

      // 判断是今天、明天还是具体日期
      const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
      const targetDate = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate())
      const diffDays = Math.floor((targetDate - today) / (1000 * 60 * 60 * 24))

      let dateStr = ''
      if (diffDays === 0) {
        dateStr = '今天'
      } else if (diffDays === 1) {
        dateStr = '明天'
      } else {
        dateStr = this.scheduleDate
      }

      return `${dateStr} ${this.scheduleTime}`
    }
  }
}
</script>

<style lang="scss" scoped>
.schedule-picker-wrapper {
  .section-header {
    display: flex;
    align-items: center;
    gap: 12rpx;
    margin-bottom: 30rpx;

    .section-icon {
      font-size: 20px;
    }

    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
    }
  }
}

.schedule-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx;
  background: #f9fafb;
  border-radius: 12rpx;

  .toggle-info {
    flex: 1;

    .toggle-title {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #374151;
      margin-bottom: 8rpx;
    }

    .toggle-tip {
      font-size: 12px;
      color: #9ca3af;
    }
  }
}

.schedule-picker {
  margin-top: 30rpx;
  display: flex;
  flex-direction: column;
  gap: 20rpx;

  .picker-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24rpx;
    background: #f9fafb;
    border-radius: 12rpx;

    .picker-label {
      display: flex;
      align-items: center;
      gap: 12rpx;

      .label-icon {
        font-size: 18px;
      }

      .label-text {
        font-size: 14px;
        font-weight: 500;
        color: #374151;
      }
    }

    .picker-value {
      font-size: 14px;
      color: #6366f1;
      font-weight: 500;
    }
  }

  .schedule-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12rpx;
    padding: 24rpx;
    background: #fef3c7;
    border-radius: 12rpx;
    margin-top: 10rpx;

    .preview-icon {
      font-size: 18px;
    }

    .preview-text {
      font-size: 14px;
      color: #92400e;
      font-weight: 500;
    }
  }
}
</style>
