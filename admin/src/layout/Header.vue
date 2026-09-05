<template>
  <header class="layout-header">
    <div class="header-container">
      <div class="header-left">
        <TaskCenterPanel />
      </div>
      <div class="header-right">
        <NotificationBell />
        <el-dropdown trigger="click" @command="handleCommand">
          <div class="user-info">
            <img class="user-avatar" src="https://pyp-xmt.oss-cn-beijing.aliyuncs.com/static/image/pyp/xiaomotui3.png" />
            <span class="user-name">{{ username }}</span>
            <el-tag :type="versionTagType" size="small" class="version-badge">{{ versionLabel }}</el-tag>
            <el-icon class="arrow-icon"><ArrowDown /></el-icon>
          </div>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="changePassword">
                <el-icon><Lock /></el-icon>修改密码
              </el-dropdown-item>
              <el-dropdown-item command="cardActivation">
                <el-icon><Key /></el-icon>卡密激活
              </el-dropdown-item>
              <el-dropdown-item command="switchVersion">
                <el-icon><Switch /></el-icon>{{ switchVersionLabel }}
              </el-dropdown-item>
              <el-dropdown-item command="benefits">
                <el-icon><Coin /></el-icon>权益信息
              </el-dropdown-item>
              <el-dropdown-item command="logout" divided>
                <el-icon><SwitchButton /></el-icon>退出登录
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
    </div>

    <CardActivation ref="cardActivationRef" @activated="handleCardActivated" />

    <el-dialog
      v-model="benefitsDialogVisible"
      title="权益信息"
      width="420px"
      :close-on-click-modal="true"
      destroy-on-close
    >
      <BenefitsPanel ref="benefitsPanelRef" />
    </el-dialog>
  </header>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowDown, Lock, Key, Switch, Coin, SwitchButton } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { accountApi } from '@/api/index.js'
import { removeToken } from '@/utils/request'
import { useUserStore } from '@/stores/user'
import CardActivation from '@/views/account/CardActivation.vue'
import BenefitsPanel from '@/views/account/BenefitsPanel.vue'
import NotificationBell from '@/layout/NotificationBell.vue'
import TaskCenterPanel from '@/layout/TaskCenterPanel.vue'

const router = useRouter()
const username = ref(localStorage.getItem('username') || '管理员')
const cardActivationRef = ref(null)
const benefitsPanelRef = ref(null)
const benefitsDialogVisible = ref(false)

const currentVersion = computed(() => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    return user.version || 'basic'
  } catch (e) {
    return 'basic'
  }
})

const switchVersionLabel = computed(() => {
  const v = currentVersion.value
  if (v === 'chain') return '切换为基础版'
  if (v === 'standard') return '切换为连锁版'
  return '切换为连锁版'
})

const versionMap = { basic: '基础版', standard: '标准版', chain: '连锁版' }
const versionTagTypeMap = { basic: 'info', standard: 'warning', chain: 'danger' }
const versionLabel = computed(() => versionMap[currentVersion.value] || '基础版')
const versionTagType = computed(() => versionTagTypeMap[currentVersion.value] || 'info')

const handleCommand = (command) => {
  switch (command) {
    case 'changePassword':
      router.push('/account/change-password')
      break
    case 'cardActivation':
      cardActivationRef.value?.open()
      break
    case 'switchVersion':
      handleSwitchVersion()
      break
    case 'benefits':
      benefitsDialogVisible.value = true
      break
    case 'logout':
      useUserStore().logout()
      ElMessage.success('已退出登录')
      router.push('/login')
      break
  }
}

const handleSwitchVersion = async () => {
  const target = currentVersion.value === 'chain' ? 'basic' : 'chain'
  const targetLabel = target === 'chain' ? '连锁版' : '基础版'

  try {
    await ElMessageBox.confirm(
      `切换为${targetLabel}后，部分功能权限将发生变化。确认要切换吗？`,
      '版本切换确认',
      {
        type: 'warning',
        confirmButtonText: '确认切换',
        cancelButtonText: '取消'
      }
    )

    await accountApi.switchVersion({ target_version: target })
    ElMessage.success(`已切换为${targetLabel}`)

    try {
      const user = JSON.parse(localStorage.getItem('user') || '{}')
      user.version = target
      localStorage.setItem('user', JSON.stringify(user))
    } catch (e) {}

    window.location.reload()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '切换失败')
    }
  }
}

const handleCardActivated = () => {
  ElMessage.success('权益已更新')
  benefitsPanelRef.value?.loadBenefits()
}
</script>

<style lang="scss" scoped>
.layout-header {
  height: 50px;
  flex-shrink: 0;
}

.header-container {
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  background: transparent;
}

.header-left {
  display: flex;
  align-items: center;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 14px;
  color: #333;

  .user-avatar {
    width: 25px;
    height: 25px;
    border-radius: 50%;
    object-fit: cover;
  }

  .user-name {
    font-weight: 500;
    color: #1d2129;
  }

  .version-badge {
    font-size: 10px;
    height: 18px;
    line-height: 16px;
    padding: 0 6px;
    border-radius: 9px;
  }

  .arrow-icon {
    font-size: 12px;
    color: #999;
  }
}
</style>
