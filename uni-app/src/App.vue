<template>
  <view id="app">
    <!-- uni-app 会自动将页面渲染到这里 -->
  </view>
</template>

<script>
export default {
  onLaunch: function() {
    console.log('App Launch')
  },
  onShow: function() {
    console.log('App Show')
  },
  onHide: function() {
    console.log('App Hide')
  }
}
</script>

<style>
/* 简化的全局样式 */
page {
  background-color: #f8f9fa;
  font-size: 14px;
  line-height: 1.6;
  color: #333333;
}

#app {
  min-height: 100vh;
}
</style>