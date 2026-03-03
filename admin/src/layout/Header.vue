<template>
  <div class="header-container">
    <div class="header-left">
      <el-icon class="menu-icon" @click="toggleSidebar">
        <Fold v-if="appStore.sidebarOpened" />
        <Expand v-else />
      </el-icon>
      <h1 class="title">小魔推管理后台</h1>
    </div>

    <div class="header-right">
      <el-dropdown @command="handleCommand">
        <div class="user-info">
          <el-avatar :size="32" icon="User" />
          <span class="username">{{ userStore.username || '管理员' }}</span>
        </div>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="profile">个人中心</el-dropdown-item>
            <el-dropdown-item command="settings">系统设置</el-dropdown-item>
            <el-dropdown-item divided command="logout">退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </div>
</template>

<script setup>
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'
import { useRouter } from 'vue-router'
import { ElMessageBox } from 'element-plus'

const userStore = useUserStore()
const appStore = useAppStore()
const router = useRouter()

const toggleSidebar = () => {
  appStore.toggleSidebar()
}

const handleCommand = (command) => {
  switch (command) {
    case 'profile':
      router.push('/profile')
      break
    case 'settings':
      router.push('/settings')
      break
    case 'logout':
      handleLogout()
      break
  }
}

const handleLogout = () => {
  ElMessageBox.confirm('确定要退出登录吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    userStore.logout()
    router.push('/login')
  })
}
</script>

<style lang="scss" scoped>
.header-container {
  height: 60px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
  background: #fff;
  border-bottom: 1px solid #e8e8e8;

  .header-left {
    display: flex;
    align-items: center;
    gap: 16px;

    .menu-icon {
      font-size: 20px;
      cursor: pointer;
      transition: all 0.3s;

      &:hover {
        color: var(--el-color-primary);
      }
    }

    .title {
      font-size: 18px;
      font-weight: 600;
      margin: 0;
      color: #333;
    }
  }

  .header-right {
    .user-info {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;

      .username {
        font-size: 14px;
        color: #666;
      }
    }
  }
}
</style>
