<template>
  <div class="platform-accounts">
    <div class="page-header">
      <div class="header-left">
        <h2>平台账号</h2>
        <p class="subtitle">管理已授权的社交媒体平台账号</p>
      </div>
      <div class="header-right">
        <el-dropdown @command="handleAuthorize">
          <el-button type="primary" icon="Plus">
            授权新账号
            <el-icon class="el-icon--right"><ArrowDown /></el-icon>
          </el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="douyin">抖音</el-dropdown-item>
              <el-dropdown-item command="kuaishou">快手</el-dropdown-item>
              <el-dropdown-item command="xiaohongshu">小红书</el-dropdown-item>
              <el-dropdown-item command="weibo">微博</el-dropdown-item>
              <el-dropdown-item command="bilibili">B站</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
    </div>

    <div class="filter-bar">
      <div class="filter-left">
        <el-radio-group v-model="listQuery.status" @change="handleFilterChange">
          <el-radio-button label="">全部</el-radio-button>
          <el-radio-button label="ACTIVE">正常</el-radio-button>
          <el-radio-button label="EXPIRED">已过期</el-radio-button>
          <el-radio-button label="DISABLED">已禁用</el-radio-button>
        </el-radio-group>
      </div>
      <div class="filter-right">
        <el-select
          v-model="listQuery.platform"
          placeholder="平台筛选"
          clearable
          style="width: 140px"
          @change="handleFilterChange"
        >
          <el-option label="抖音" value="douyin" />
          <el-option label="快手" value="kuaishou" />
          <el-option label="小红书" value="xiaohongshu" />
          <el-option label="微博" value="weibo" />
          <el-option label="B站" value="bilibili" />
        </el-select>
        <el-button style="margin-left: 12px" @click="getList">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
      </div>
    </div>

    <div v-loading="loading" class="account-grid">
      <div
        v-for="item in accountList"
        :key="item.id"
        class="account-card"
      >
        <div class="card-header">
          <div class="user-info">
            <el-avatar :size="48" :src="item.avatar">
              <el-icon :size="24"><User /></el-icon>
            </el-avatar>
            <div class="user-detail">
              <div class="nickname">{{ item.platform_name || item.nickname || '-' }}</div>
              <div class="platform">
                <el-tag :type="getPlatformTagType(item.platform)" size="small">
                  {{ getPlatformName(item.platform) }}
                </el-tag>
              </div>
            </div>
          </div>
          <el-tag :type="getAccountStatusType(item)" size="small">
            {{ getAccountStatusLabel(item) }}
          </el-tag>
        </div>

        <div class="card-body">
          <div class="info-row">
            <span class="info-label">平台UID</span>
            <span class="info-value">{{ item.platform_uid || item.open_id || '-' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">授权时间</span>
            <span class="info-value">{{ formatTime(item.create_time || item.authorized_at) }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">过期时间</span>
            <span class="info-value" :class="{ 'expired': isExpired(item) }">
              {{ formatTime(item.expires_time || item.expires_at_formatted) }}
            </span>
          </div>
          <div v-if="item.follower_count !== undefined" class="info-row">
            <span class="info-label">粉丝数</span>
            <span class="info-value">{{ formatNumber(item.follower_count) }}</span>
          </div>
        </div>

        <div class="card-actions">
          <el-button
            type="primary"
            size="small"
            plain
            :disabled="isDisabled(item)"
            @click="handleRefreshToken(item)"
          >
            <el-icon><RefreshRight /></el-icon>
            刷新Token
          </el-button>
          <el-button
            type="danger"
            size="small"
            plain
            @click="handleDelete(item)"
          >
            <el-icon><Delete /></el-icon>
            解绑
          </el-button>
        </div>
      </div>

      <div v-if="!loading && accountList.length === 0" class="empty-state">
        <el-empty description="暂无授权账号">
          <el-button type="primary" @click="handleAuthorize('douyin')">授权抖音账号</el-button>
        </el-empty>
      </div>
    </div>

    <el-dialog
      v-model="authDialogVisible"
      title="平台授权"
      width="500px"
      destroy-on-close
    >
      <div v-if="authInfo" class="auth-content">
        <el-alert
          :title="`${authInfo.platform_name} 授权提示`"
          :description="authInfo.tips"
          type="info"
          show-icon
          :closable="false"
          style="margin-bottom: 20px"
        />
        <div class="auth-url-box">
          <p>请点击下方按钮前往平台授权页面：</p>
          <el-link :href="authInfo.auth_url" target="_blank" type="primary" :underline="false">
            <el-button type="primary" size="large">
              前往 {{ authInfo.platform_name }} 授权
            </el-button>
          </el-link>
        </div>
      </div>
      <div v-else-if="authLoading" style="text-align: center; padding: 40px 0">
        <el-icon class="is-loading" :size="32"><Loading /></el-icon>
        <p style="color: #909399; margin-top: 12px">正在获取授权链接...</p>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowDown, Delete, RefreshRight, User, Refresh, Loading } from '@element-plus/icons-vue'
import {
  getPlatformAccounts,
  deletePlatformAccount,
  refreshAccountToken,
  getPlatformAuthUrl
} from '@/api/publish'

const loading = ref(false)
const accountList = ref([])

const listQuery = reactive({
  platform: '',
  status: ''
})

const authDialogVisible = ref(false)
const authLoading = ref(false)
const authInfo = ref(null)

const getPlatformName = (key) => {
  if (!key) return '-'
  const k = key.toUpperCase()
  const map = {
    DOUYIN: '抖音',
    KUAISHOU: '快手',
    XIAOHONGSHU: '小红书',
    WEIBO: '微博',
    BILIBILI: 'B站'
  }
  return map[k] || map[key] || key
}

const getPlatformTagType = (key) => {
  if (!key) return 'info'
  const k = key.toUpperCase()
  const map = {
    DOUYIN: 'danger',
    KUAISHOU: 'warning',
    XIAOHONGSHU: '',
    WEIBO: 'info',
    BILIBILI: 'success'
  }
  return map[k] || 'info'
}

const getAccountStatusType = (item) => {
  if (item.status === 'ACTIVE' || item.status === 'valid' || item.status === 1) {
    return isExpired(item) ? 'warning' : 'success'
  }
  if (item.status === 'EXPIRED' || item.status === 'expired') return 'warning'
  return 'danger'
}

const getAccountStatusLabel = (item) => {
  if (item.status === 'ACTIVE' || item.status === 'valid' || item.status === 1) {
    return isExpired(item) ? '已过期' : '正常'
  }
  if (item.status === 'EXPIRED' || item.status === 'expired') return '已过期'
  if (item.status === 'DISABLED' || item.status === 'invalid' || item.status === 0) return '已禁用'
  return item.status || '未知'
}

const isExpired = (item) => {
  const expireTime = item.expires_time || item.expires_at_formatted || item.expires_at
  if (!expireTime) return false
  if (typeof expireTime === 'number') return expireTime * 1000 < Date.now()
  return new Date(expireTime).getTime() < Date.now()
}

const isDisabled = (item) => {
  return item.status === 'DISABLED' || item.status === 'invalid' || item.status === 0
}

const formatTime = (time) => {
  if (!time) return '-'
  if (typeof time === 'number') {
    return new Date(time * 1000).toLocaleString('zh-CN', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    })
  }
  const d = new Date(time)
  if (isNaN(d.getTime())) return time
  return d.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatNumber = (num) => {
  if (!num && num !== 0) return '-'
  if (num >= 10000) return (num / 10000).toFixed(1) + '万'
  return num.toLocaleString()
}

const getList = async () => {
  loading.value = true
  try {
    const params = { ...listQuery }
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })
    const res = await getPlatformAccounts(params)
    if (res) {
      if (res.accounts) {
        accountList.value = res.accounts
      } else if (res.list) {
        accountList.value = res.list
      } else if (Array.isArray(res)) {
        accountList.value = res
      }
    }
  } catch {
    ElMessage.error('获取平台账号列表失败')
  } finally {
    loading.value = false
  }
}

const handleFilterChange = () => {
  getList()
}

const handleAuthorize = async (platform) => {
  authInfo.value = null
  authDialogVisible.value = true
  authLoading.value = true
  try {
    const res = await getPlatformAuthUrl(platform)
    if (res) {
      authInfo.value = res
    }
  } catch (e) {
    ElMessage.error(e.message || '获取授权链接失败')
    authDialogVisible.value = false
  } finally {
    authLoading.value = false
  }
}

const handleRefreshToken = async (item) => {
  try {
    await ElMessageBox.confirm(
      `确定要刷新 ${item.platform_name || item.nickname || '该账号'} 的授权令牌吗？`,
      '刷新确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'info'
      }
    )
    await refreshAccountToken(item.id)
    ElMessage.success('令牌刷新成功')
    getList()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '刷新令牌失败')
    }
  }
}

const handleDelete = async (item) => {
  try {
    await ElMessageBox.confirm(
      `确定要解绑 ${item.platform_name || item.nickname || '该账号'} 吗？解绑后需要重新授权才能使用。`,
      '解绑确认',
      {
        confirmButtonText: '确定解绑',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    await deletePlatformAccount(item.id)
    ElMessage.success('账号已解绑')
    getList()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '解绑失败')
    }
  }
}

onMounted(() => {
  getList()
})
</script>

<style scoped lang="scss">
.platform-accounts {
  padding: 20px;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;

    .header-left {
      h2 {
        font-size: 24px;
        font-weight: 600;
        margin: 0 0 8px 0;
        color: #303133;
      }

      .subtitle {
        font-size: 14px;
        color: #909399;
        margin: 0;
      }
    }
  }

  .filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 16px 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);

    .filter-left {
      flex: 1;
    }

    .filter-right {
      display: flex;
      align-items: center;
    }
  }

  .account-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 20px;
    min-height: 300px;

    .account-card {
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      transition: all 0.3s;

      &:hover {
        box-shadow: 0 4px 20px rgba(99, 102, 241, 0.15);
        transform: translateY(-4px);
      }

      .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid #f0f0f0;

        .user-info {
          display: flex;
          align-items: center;
          gap: 12px;

          .user-detail {
            .nickname {
              font-size: 15px;
              font-weight: 600;
              color: #303133;
              margin-bottom: 4px;
            }
          }
        }
      }

      .card-body {
        padding: 16px;

        .info-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 6px 0;
          font-size: 13px;

          .info-label {
            color: #909399;
          }

          .info-value {
            color: #303133;
            font-weight: 500;

            &.expired {
              color: #e6a23c;
            }
          }
        }
      }

      .card-actions {
        display: flex;
        gap: 8px;
        padding: 0 16px 16px;
      }
    }

    .empty-state {
      grid-column: 1 / -1;
      padding: 60px 0;
    }
  }

  .auth-content {
    .auth-url-box {
      text-align: center;
      padding: 20px 0;

      p {
        color: #606266;
        margin-bottom: 16px;
      }
    }
  }
}
</style>
