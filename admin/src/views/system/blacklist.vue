<template>
  <div class="blacklist-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">IP黑名单</h1>
      </div>
      <div class="header-actions">
        <el-button :icon="Plus" type="primary" @click="showAddDialog">添加IP</el-button>
        <el-button :icon="Upload" @click="showBatchDialog">批量导入</el-button>
        <el-button :icon="Download" @click="handleExport">导出</el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="8" :sm="8">
        <el-card shadow="hover" class="stat-mini">
          <div class="stat-value">{{ overview.total || 0 }}</div>
          <div class="stat-label">总数</div>
        </el-card>
      </el-col>
      <el-col :xs="8" :sm="8">
        <el-card shadow="hover" class="stat-mini active">
          <div class="stat-value">{{ overview.active || 0 }}</div>
          <div class="stat-label">活跃</div>
        </el-card>
      </el-col>
      <el-col :xs="8" :sm="8">
        <el-card shadow="hover" class="stat-mini inactive">
          <div class="stat-value">{{ overview.inactive || 0 }}</div>
          <div class="stat-label">已过期</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 搜索和筛选 -->
    <el-card shadow="never" style="margin-bottom: 20px;">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="IP搜索">
          <el-input v-model="filterForm.ip" placeholder="输入IP地址" clearable style="width: 200px;" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部" clearable>
            <el-option label="活跃" value="active" />
            <el-option label="已过期" value="inactive" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="loadList">查询</el-button>
          <el-button :icon="Refresh" @click="handleRefresh">刷新</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- IP列表 -->
    <el-card shadow="hover">
      <el-table :data="blacklist" v-loading="loading" stripe @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="ip" label="IP地址" min-width="150" />
        <el-table-column prop="reason" label="封禁原因" min-width="200" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'danger' : 'info'" size="small">
              {{ row.status === 'active' ? '活跃' : '已过期' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="duration" label="时长" width="120">
          <template #default="{ row }">{{ row.permanent ? '永久' : row.duration + '分钟' }}</template>
        </el-table-column>
        <el-table-column prop="block_count" label="封禁次数" width="100" />
        <el-table-column prop="create_time" label="封禁时间" width="170" />
        <el-table-column prop="expire_time" label="过期时间" width="170">
          <template #default="{ row }">{{ row.permanent ? '永久' : row.expire_time }}</template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="viewStats(row)">统计</el-button>
            <el-popconfirm title="确定移除此IP？" @confirm="handleRemove(row)">
              <template #reference>
                <el-button link type="danger">移除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.page_size"
          :total="pagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadList"
          @current-change="loadList"
        />
        <el-button type="danger" :disabled="selectedRows.length === 0" @click="handleBatchRemove">
          批量移除 ({{ selectedRows.length }})
        </el-button>
      </div>
    </el-card>

    <!-- 添加IP对话框 -->
    <el-dialog v-model="addDialogVisible" title="添加IP到黑名单" width="500px">
      <el-form :model="addForm" label-width="100px">
        <el-form-item label="IP地址" required>
          <el-input v-model="addForm.ip" placeholder="例如: 192.168.1.1" />
        </el-form-item>
        <el-form-item label="封禁原因">
          <el-input v-model="addForm.reason" placeholder="请输入封禁原因" />
        </el-form-item>
        <el-form-item label="封禁时长">
          <el-input-number v-model="addForm.duration" :min="1" :disabled="addForm.permanent" />
          <span style="margin-left: 8px">分钟</span>
        </el-form-item>
        <el-form-item label="永久封禁">
          <el-switch v-model="addForm.permanent" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleAdd">确定</el-button>
      </template>
    </el-dialog>

    <!-- 批量导入对话框 -->
    <el-dialog v-model="batchDialogVisible" title="批量导入IP" width="500px">
      <el-form :model="batchForm" label-width="100px">
        <el-form-item label="IP列表" required>
          <el-input
            v-model="batchForm.ips_text"
            type="textarea"
            :rows="6"
            placeholder="每行一个IP地址"
          />
        </el-form-item>
        <el-form-item label="封禁原因">
          <el-input v-model="batchForm.reason" placeholder="批量封禁原因" />
        </el-form-item>
        <el-form-item label="封禁时长">
          <el-input-number v-model="batchForm.duration" :min="1" />
          <span style="margin-left: 8px">分钟</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="batchDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleBatchAdd">导入</el-button>
      </template>
    </el-dialog>

    <!-- IP统计对话框 -->
    <el-dialog v-model="statsDialogVisible" title="IP访问统计" width="500px">
      <el-descriptions :column="1" border v-if="currentStats">
        <el-descriptions-item label="IP地址">{{ currentStats.ip }}</el-descriptions-item>
        <el-descriptions-item label="总请求次数">{{ currentStats.total_requests || 0 }}</el-descriptions-item>
        <el-descriptions-item label="封禁次数">{{ currentStats.block_count || 0 }}</el-descriptions-item>
        <el-descriptions-item label="最近访问">{{ currentStats.last_access || '-' }}</el-descriptions-item>
        <el-descriptions-item label="是否被封">
          <el-tag :type="currentStats.is_blocked ? 'danger' : 'success'">
            {{ currentStats.is_blocked ? '是' : '否' }}
          </el-tag>
        </el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Upload, Download, Search, Refresh } from '@element-plus/icons-vue'
import {
  getBlacklistList,
  getBlacklistOverview,
  addBlacklist,
  batchAddBlacklist,
  removeBlacklist,
  batchRemoveBlacklist,
  exportBlacklist,
  getIpStats
} from '@/api/blacklist'

const loading = ref(false)
const blacklist = ref([])
const overview = ref({ total: 0, active: 0, inactive: 0 })
const selectedRows = ref([])
const addDialogVisible = ref(false)
const batchDialogVisible = ref(false)
const statsDialogVisible = ref(false)
const currentStats = ref(null)

const filterForm = reactive({ ip: '', status: '' })
const pagination = reactive({ page: 1, page_size: 20, total: 0 })

const addForm = reactive({ ip: '', reason: '手动封禁', duration: 1440, permanent: false })
const batchForm = reactive({ ips_text: '', reason: '批量封禁', duration: 1440 })

const loadOverview = async () => {
  try {
    const res = await getBlacklistOverview()
    overview.value = res || { total: 0, active: 0, inactive: 0 }
  } catch (e) {
    console.error('加载黑名单概览失败:', e)
  }
}

const loadList = async () => {
  loading.value = true
  try {
    const res = await getBlacklistList({
      page: pagination.page,
      page_size: pagination.page_size,
      ip: filterForm.ip || undefined,
      status: filterForm.status || undefined
    })
    const data = res || {}
    blacklist.value = data.list || []
    pagination.total = data.total || 0
  } catch (e) {
    blacklist.value = []
  } finally {
    loading.value = false
  }
}

const handleRefresh = () => {
  loadOverview()
  loadList()
}

const handleSelectionChange = (rows) => {
  selectedRows.value = rows
}

const showAddDialog = () => {
  addForm.ip = ''
  addForm.reason = '手动封禁'
  addForm.duration = 1440
  addForm.permanent = false
  addDialogVisible.value = true
}

const IP_REGEX = /^(\d{1,3}\.){3}\d{1,3}$/

const isValidIp = (ip) => IP_REGEX.test(ip) && ip.split('.').every(n => { const v = parseInt(n, 10); return v >= 0 && v <= 255 })

const handleAdd = async () => {
  if (!addForm.ip) {
    ElMessage.warning('请输入IP地址')
    return
  }
  if (!isValidIp(addForm.ip)) {
    ElMessage.warning('IP地址格式不正确')
    return
  }
  try {
    await addBlacklist({
      ip: addForm.ip,
      reason: addForm.reason,
      duration: addForm.permanent ? 0 : addForm.duration,
      permanent: addForm.permanent
    })
    ElMessage.success('添加成功')
    addDialogVisible.value = false
    loadList()
    loadOverview()
  } catch (e) {
    ElMessage.error('添加失败')
  }
}

const showBatchDialog = () => {
  batchForm.ips_text = ''
  batchForm.reason = '批量封禁'
  batchDialogVisible.value = true
}

const handleBatchAdd = async () => {
  const ips = batchForm.ips_text.split('\n').map(ip => ip.trim()).filter(Boolean)
  if (ips.length === 0) {
    ElMessage.warning('请输入IP地址')
    return
  }
  if (ips.length > 1000) {
    ElMessage.warning('单次最多导入1000条IP')
    return
  }
  const invalidIps = ips.filter(ip => !isValidIp(ip))
  if (invalidIps.length > 0) {
    ElMessage.warning(`IP格式不正确: ${invalidIps.slice(0, 3).join(', ')}${invalidIps.length > 3 ? '...' : ''}`)
    return
  }
  try {
    await batchAddBlacklist({ ips, reason: batchForm.reason, duration: batchForm.duration })
    ElMessage.success('批量导入成功')
    batchDialogVisible.value = false
    loadList()
    loadOverview()
  } catch (e) {
    ElMessage.error('批量导入失败')
  }
}

const handleRemove = async (row) => {
  try {
    await removeBlacklist({ ip: row.ip })
    ElMessage.success('移除成功')
    loadList()
    loadOverview()
  } catch (e) {
    ElMessage.error('移除失败')
  }
}

const handleBatchRemove = async () => {
  try {
    await ElMessageBox.confirm(`确定要批量移除 ${selectedRows.value.length} 个IP吗？`, '提示', { type: 'warning' })
    const ips = selectedRows.value.map(row => row.ip)
    await batchRemoveBlacklist({ ips })
    ElMessage.success('批量移除成功')
    loadList()
    loadOverview()
  } catch { /* cancel */ }
}

const handleExport = async () => {
  try {
    const res = await exportBlacklist({ status: filterForm.status || undefined })
    const data = res || {}
    const jsonStr = JSON.stringify(data.list || [], null, 2)
    const blob = new Blob([jsonStr], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `ip_blacklist_${new Date().toISOString().slice(0, 10)}.json`
    link.click()
    URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  } catch (e) {
    ElMessage.error('导出失败')
  }
}

const viewStats = async (row) => {
  try {
    const res = await getIpStats({ ip: row.ip })
    currentStats.value = { ip: row.ip, ...(res || {}) }
    statsDialogVisible.value = true
  } catch (e) {
    ElMessage.error('获取统计失败')
  }
}

onMounted(() => {
  loadOverview()
  loadList()
})
</script>

<style lang="scss" scoped>
.blacklist-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100vh;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;

    .header-left {
      .page-title {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        color: #303133;
      }
    }

    .header-actions {
      display: flex;
      gap: 12px;
    }
  }

  .stats-row {
    margin-bottom: 20px;

    .stat-mini {
      text-align: center;
      padding: 12px;

      .stat-value {
        font-size: 32px;
        font-weight: 600;
        color: #303133;
      }

      .stat-label {
        font-size: 14px;
        color: #909399;
        margin-top: 4px;
      }

      &.active .stat-value {
        color: #F56C6C;
      }

      &.inactive .stat-value {
        color: #909399;
      }
    }
  }

  .pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
  }
}

@media (max-width: 768px) {
  .blacklist-container {
    padding: 12px;

    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 12px;
    }

    .pagination-wrapper {
      flex-direction: column;
      gap: 12px;
    }
  }
}
</style>
