<template>
  <div class="sidebar-container" :class="{ 'is-collapse': !appStore.sidebarOpened }">
    <el-menu
      :default-active="activeMenu"
      :collapse="!appStore.sidebarOpened"
      :collapse-transition="false"
      router
    >
      <el-menu-item index="/dashboard">
        <el-icon><DataAnalysis /></el-icon>
        <template #title>仪表盘</template>
      </el-menu-item>

      <el-menu-item index="/devices">
        <el-icon><Monitor /></el-icon>
        <template #title>设备管理</template>
      </el-menu-item>

      <el-menu-item index="/statistics">
        <el-icon><DataLine /></el-icon>
        <template #title>数据统计</template>
      </el-menu-item>

      <el-sub-menu index="nfc">
        <template #title>
          <el-icon><Postcard /></el-icon>
          <span>NFC管理</span>
        </template>
        <el-menu-item index="/nfc/triggers">触发记录</el-menu-item>
      </el-sub-menu>

      <el-sub-menu index="content">
        <template #title>
          <el-icon><Document /></el-icon>
          <span>内容管理</span>
        </template>
        <el-menu-item index="/content/creation">AI创作</el-menu-item>
        <el-menu-item index="/content/tasks">生成任务</el-menu-item>
        <el-menu-item index="/content/templates">模板管理</el-menu-item>
        <el-menu-item index="/video-library">
          <el-icon><VideoPlay /></el-icon>
          <template #title>视频库</template>
        </el-menu-item>
      </el-sub-menu>

      <el-sub-menu index="coupon">
        <template #title>
          <el-icon><Ticket /></el-icon>
          <span>券码管理</span>
        </template>
        <el-menu-item index="/coupon/list">券码列表</el-menu-item>
        <el-menu-item index="/coupon/users">用户领取</el-menu-item>
      </el-sub-menu>

      <el-sub-menu index="promo">
        <template #title>
          <el-icon><Promotion /></el-icon>
          <span>推广管理</span>
        </template>
        <el-menu-item index="/promo/material">素材库</el-menu-item>
        <el-menu-item index="/promo/template">视频模板</el-menu-item>
        <el-menu-item index="/promo/variant">视频变体</el-menu-item>
        <el-menu-item index="/promo/campaign">推广活动</el-menu-item>
        <el-menu-item index="/promo/stats">推广统计</el-menu-item>
      </el-sub-menu>

      <el-sub-menu index="merchant">
        <template #title>
          <el-icon><Shop /></el-icon>
          <span>商户管理</span>
        </template>
        <el-menu-item index="/merchant/list">商户列表</el-menu-item>
      </el-sub-menu>

      <el-sub-menu index="system">
        <template #title>
          <el-icon><Setting /></el-icon>
          <span>系统管理</span>
        </template>
        <el-menu-item index="/system/users">用户管理</el-menu-item>
        <el-menu-item index="/system/settings">系统设置</el-menu-item>
        <el-menu-item index="/system/logs">操作日志</el-menu-item>
      </el-sub-menu>
    </el-menu>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'

const route = useRoute()
const appStore = useAppStore()

const activeMenu = computed(() => route.path)
</script>

<style lang="scss" scoped>
.sidebar-container {
  width: 200px;
  background: #fff;
  border-right: 1px solid #e8e8e8;
  transition: width 0.3s;
  overflow: hidden;

  &.is-collapse {
    width: 64px;
  }

  .el-menu {
    border-right: none;
    height: 100%;
  }
}
</style>
