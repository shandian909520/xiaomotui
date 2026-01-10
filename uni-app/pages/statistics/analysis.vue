<template>
  <view class="page-container">
    <view class="page-content">
      <text class="page-title">数据分析</text>
      <text class="page-desc">功能开发中...</text>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {}
  },
  onLoad(options) {
    console.log('数据分析页面加载:', options)
  }
}
</script>

<style scoped>
.page-container {
  min-height: 100vh;
  background: #f8f9fa;
  padding: 40rpx;
}
.page-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 120rpx 0;
}
.page-title {
  font-size: 48rpx;
  font-weight: bold;
  color: #333;
  margin-bottom: 30rpx;
}
.page-desc {
  font-size: 28rpx;
  color: #999;
}
</style>
