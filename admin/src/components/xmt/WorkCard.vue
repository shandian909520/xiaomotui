<template>
  <div class="xmt-work-card" :style="{ background: background || defaultBg }">
    <span v-if="badge" class="work-badge">{{ badge }}</span>
    <span class="work-title">{{ title }}</span>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  background: String,
  badge: String
})

const defaultBg = 'linear-gradient(135deg, #6a3518, #d9a44f)'
</script>

<style lang="scss" scoped>
.xmt-work-card {
  height: 120px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 14px;
  border-radius: 8px;
  color: #fff;
  font-weight: 800;
  box-shadow: inset 0 -45px 60px rgba(0, 0, 0, 0.28);
  position: relative;

  .work-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.3);
    font-size: 11px;
  }

  .work-title {
    font-size: 14px;
  }
}
</style>
