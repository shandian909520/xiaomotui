<template>
  <main class="main">
    <router-view v-slot="{ Component }">
      <transition name="page-fade" mode="out-in">
        <component :is="Component" />
      </transition>
    </router-view>
  </main>
</template>

<style lang="scss" scoped>
.main {
  flex: 1;
  overflow-y: auto;
  padding: 12px 16px 22px;
  background: #f8f3ff;
}

.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.18s ease;
}

.page-fade-enter-from,
.page-fade-leave-to {
  opacity: 0;
}
</style>
