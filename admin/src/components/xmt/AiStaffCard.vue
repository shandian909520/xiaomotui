<template>
  <div class="xmt-ai-staff-card">
    <span v-if="hot" class="staff-hot">HOT</span>
    <div class="staff-avatar">{{ avatar }}</div>
    <strong>{{ name }}</strong>
    <span class="staff-role">{{ role }}</span>
    <el-button size="small" plain round @click="$emit('action')">
      {{ buttonText || '安排工作' }}
    </el-button>
  </div>
</template>

<script setup>
defineProps({
  name: String,
  role: String,
  avatar: String,
  hot: Boolean,
  buttonText: String
})

defineEmits(['action'])
</script>

<style lang="scss" scoped>
.xmt-ai-staff-card {
  position: relative;
  min-height: 180px;
  padding: 14px 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  border: 1px solid #ece8f4;
  border-radius: 14px;
  background: #fff;

  .staff-hot {
    position: absolute;
    top: 0;
    right: 10px;
    padding: 1px 7px;
    border-radius: 0 0 8px 8px;
    color: #fff;
    background: #ff244b;
    font-size: 11px;
    font-weight: 800;
  }

  .staff-avatar {
    width: 58px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    border-radius: 50%;
    color: #fff;
    background: linear-gradient(135deg, #2fb8c6, #f3d3c8);
    font-size: 24px;
    font-weight: 800;
  }

  strong {
    font-size: 14px;
    color: #181224;
  }

  .staff-role {
    margin: 8px 0 18px;
    color: #7c7288;
    font-size: 12px;
  }
}
</style>