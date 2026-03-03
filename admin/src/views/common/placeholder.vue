<template>
  <div style="padding:30px;">
    <el-alert :closable="false" title="功能开发中" type="info">
      <template #default>
        {{ pageName }} 模块正在开发中，敬请期待...
      </template>
    </el-alert>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const pageName = computed(() => route.meta.title || '该')
</script>
