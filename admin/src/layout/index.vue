<template>
  <div class="layout-container">
    <Header />
    <div class="layout-body">
      <Sidebar />
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
.layout-container {
  height: 100vh;
  display: flex;
  flex-direction: column;
}

.layout-body {
  flex: 1;
  display: flex;
  overflow: hidden;
}
</style>
