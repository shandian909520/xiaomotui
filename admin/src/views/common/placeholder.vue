<template>
  <div class="placeholder panel">
    <div>
      <p>{{ route.meta.title }}</p>
      <h1>{{ route.meta.title }}</h1>
      <span>该模块已接入小魔推同款后台框架，可继续按线上页面逐页补齐。</span>
    </div>
    <el-button type="primary" round @click="router.push('/home')">返回首页</el-button>
  </div>
</template>

<script setup>
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
</script>

<style lang="scss" scoped>
.placeholder {
  min-height: calc(100vh - 84px);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 48px;
  border-radius: 18px;
  background: #fff;
}

p {
  margin: 0 0 8px;
  color: #8b61ff;
  font-weight: 700;
}

h1 {
  margin: 0 0 12px;
  color: #181224;
  font-size: 34px;
}

span {
  color: #746b80;
}
</style>
