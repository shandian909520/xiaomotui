<template>
  <div class="shell">
    <Sidebar />
    <div class="shell-main">
      <Header />
      <Main />
    </div>
  </div>
</template>

<script setup>
import Header from './Header.vue'
import Sidebar from './Sidebar.vue'
import Main from './Main.vue'
</script>

<style lang="scss" scoped>
.shell {
  min-width: 1280px;
  height: 100vh;
  display: flex;
  overflow: hidden;
  background: #f8f8f8;
}

.shell-main {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
</style>
