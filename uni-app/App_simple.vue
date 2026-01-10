<script>
export default {
  onLaunch: function() {
    console.log('App Launch - Simple Version')
  },
  onShow: function() {
    console.log('App Show')
  },
  onHide: function() {
    console.log('App Hide')
  }
}
</script>

<template>
  <view>
    <text>应用已启动</text>
  </view>
</template>

<style>
page {
  background-color: #f8f9fa;
  font-size: 14px;
  line-height: 1.6;
  color: #333333;
}
</style>