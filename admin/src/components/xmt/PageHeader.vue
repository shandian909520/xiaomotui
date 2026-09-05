<template>
  <div class="xmt-page-header">
    <div class="header-left">
      <h1 v-if="title">{{ title }}</h1>
      <span v-if="subtitle" class="subtitle">{{ subtitle }}</span>
    </div>
    <div v-if="$slots.extra || extra" class="header-right">
      <slot name="extra">{{ extra }}</slot>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  subtitle: String,
  extra: String
})
</script>

<style lang="scss" scoped>
.xmt-page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 28px;
  background: #fff;
  border-radius: 16px;
  margin-bottom: 16px;

  .header-left {
    display: flex;
    flex-direction: column;
    gap: 4px;

    h1 {
      margin: 0;
      font-size: 22px;
      font-weight: 700;
      color: #181224;
    }

    .subtitle {
      font-size: 13px;
      color: #746b80;
    }
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 12px;
  }
}
</style>