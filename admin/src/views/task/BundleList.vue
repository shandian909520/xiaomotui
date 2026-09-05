<template>
  <div class="task-bundle-list">
    <!-- 搜索栏 -->
    <el-card class="search-card">
      <div class="search-bar">
        <el-input
          v-model="searchName"
          placeholder="任务包名称"
          clearable
          style="width: 220px"
          @keyup.enter="handleSearch"
          @clear="handleSearch"
        />
        <el-select v-model="searchStatus" placeholder="状态" clearable style="width: 140px" @change="handleSearch">
          <el-option label="启用" value="1" />
          <el-option label="停用" value="0" />
        </el-select>
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="handleReset">重置</el-button>
        <div class="spacer"></div>
        <el-button type="primary" @click="handleCreate">
          <el-icon><Plus /></el-icon>
          新建任务包
        </el-button>
      </div>
    </el-card>

    <!-- 列表 -->
    <el-card class="table-card">
      <el-table :data="bundleList" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="bundleName" label="任务包名称" min-width="140" show-overflow-tooltip />
        <el-table-column prop="title" label="标题" min-width="140" show-overflow-tooltip />
        <el-table-column label="绑定设备" width="110" align="center">
          <template #default="{ row }">{{ row.deviceId || '-' }}</template>
        </el-table-column>
        <el-table-column prop="actionCount" label="动作数" width="90" align="center" />
        <el-table-column label="完成规则" width="110" align="center">
          <template #default="{ row }">
            {{ completionRuleText(row.completionRule, row.completionCount) }}
          </template>
        </el-table-column>
        <el-table-column label="奖励类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="rewardTagType(row.rewardType)" size="small">{{ rewardTypeText(row.rewardType) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 || row.status === '1' ? 'success' : 'info'">
              {{ row.status === 1 || row.status === '1' ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="创建时间" width="170" />
        <el-table-column label="操作" width="230" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button
              size="small"
              :type="isActive(row) ? 'warning' : 'success'"
              link
              @click="handleToggleStatus(row)"
            >
              {{ isActive(row) ? '停用' : '启用' }}
            </el-button>
            <el-button size="small" link @click="handleCopy(row)">复制</el-button>
            <el-button size="small" type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadList"
        @current-change="loadList"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useRouter } from 'vue-router'
import { getBundleList, getBundleDetail, createBundle, updateBundle, deleteBundle } from '@/api/task'
import { normalizePagination } from '@/utils/responseHelper'

const router = useRouter()

const loading = ref(false)
const bundleList = ref([])
const searchName = ref('')
const searchStatus = ref('')
const pagination = reactive({ page: 1, limit: 10, total: 0 })

const isActive = (row) => row.status === 1 || row.status === '1'

const completionRuleText = (rule, count) => {
  const map = { ALL: '全部完成', ANY: '任一完成', COUNT: `完成 ${count ?? '-'} 个` }
  return map[rule] || rule || '-'
}

const rewardTypeText = (type) => {
  const map = { none: '无奖励', redpacket: '红包', coupon: '优惠券', points: '积分' }
  return map[type] || type || '-'
}

const rewardTagType = (type) => {
  const map = { none: 'info', redpacket: 'danger', coupon: 'warning', points: 'success' }
  return map[type] || 'info'
}

const loadList = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, limit: pagination.limit }
    if (searchName.value) params.bundle_name = searchName.value
    if (searchStatus.value !== '') params.status = searchStatus.value
    const res = await getBundleList(params)
    const { list, total } = normalizePagination(res)
    bundleList.value = list
    pagination.total = total
  } catch (err) {
    console.error('获取任务包列表失败:', err)
    bundleList.value = []
    pagination.total = 0
    ElMessage.error('获取任务包列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.page = 1
  loadList()
}

const handleReset = () => {
  searchName.value = ''
  searchStatus.value = ''
  pagination.page = 1
  loadList()
}

const handleCreate = () => {
  router.push('/task/edit')
}

const handleEdit = (row) => {
  router.push(`/task/edit/${row.id}`)
}

const handleToggleStatus = async (row) => {
  const action = isActive(row) ? '停用' : '启用'
  try {
    await ElMessageBox.confirm(`确定${action}任务包 "${row.bundleName}" 吗？`, '提示', { type: 'warning' })
    const newStatus = isActive(row) ? 0 : 1
    // 更新走全量覆盖：拉详情后仅改 status 提交
    const res = await getBundleDetail(row.id)
    const { bundle, actions } = normalizeDetailPayload(res)
    if (!bundle || !bundle.id) {
      ElMessage.error('获取任务包详情失败')
      return
    }
    await updateBundle(row.id, buildSubmitPayload(bundle, actions, { status: newStatus }))
    ElMessage.success('状态已更新')
    loadList()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('切换状态失败:', err)
      ElMessage.error('操作失败')
    }
  }
}

const normalizeDetailPayload = (res) => {
  let bundle = {}
  let actions = []
  if (res && typeof res === 'object') {
    bundle = res.bundle || res.data?.bundle || {}
    actions = res.actions || res.data?.actions || []
  }
  return { bundle, actions }
}

const buildSubmitPayload = (bundle, actions, overrides = {}) => {
  return {
    bundle_name: bundle.bundleName ?? bundle.bundle_name,
    title: bundle.title,
    subtitle: bundle.subtitle ?? bundle.sub_title,
    cover: bundle.cover,
    completion_rule: bundle.completionRule ?? bundle.completion_rule,
    completion_count: bundle.completionCount ?? bundle.completion_count,
    reward_type: bundle.rewardType ?? bundle.reward_type,
    reward_config: (bundle.rewardConfig ?? bundle.reward_config) || {},
    lander_config: (bundle.landerConfig ?? bundle.lander_config) || {},
    expire_hours: bundle.expireHours ?? bundle.expire_hours,
    status: bundle.status,
    device_id: bundle.deviceId ?? bundle.device_id,
    actions: (actions || []).map(a => ({
      plugin_key: a.pluginKey ?? a.plugin_key,
      action_name: a.actionName ?? a.action_name,
      action_icon: a.actionIcon ?? a.action_icon,
      action_config: (a.actionConfig ?? a.action_config) || {},
      sort_order: a.sortOrder ?? a.sort_order ?? 0,
      required: a.required ?? 1
    })),
    ...overrides
  }
}

const handleCopy = async (row) => {
  try {
    const res = await getBundleDetail(row.id)
    const { bundle, actions } = normalizeDetailPayload(res)
    if (!bundle || !bundle.id) {
      ElMessage.error('获取任务包详情失败')
      return
    }
    const payload = buildSubmitPayload(bundle, actions, {
      bundle_name: `${(bundle.bundleName ?? bundle.bundle_name) || ''}（副本）`,
      status: 0
    })
    await createBundle(payload)
    ElMessage.success('复制成功（已创建停用状态的副本）')
    loadList()
  } catch (err) {
    console.error('复制失败:', err)
    ElMessage.error('复制失败，请稍后重试')
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除任务包 "${row.bundleName}" 吗？删除后不可恢复！`, '警告', {
      type: 'error',
      confirmButtonText: '删除',
      confirmButtonClass: 'el-button--danger'
    })
    await deleteBundle(row.id)
    ElMessage.success('删除成功')
    loadList()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('删除失败:', err)
      ElMessage.error('删除失败，请稍后重试')
    }
  }
}

onMounted(() => {
  loadList()
})
</script>

<style scoped lang="scss">
.task-bundle-list {
  padding: 20px;

  .search-card {
    margin-bottom: 20px;

    .search-bar {
      display: flex;
      align-items: center;
      gap: 10px;

      .spacer {
        flex: 1;
      }
    }
  }

  .table-card {
    :deep(.el-pagination) {
      margin-top: 20px;
      justify-content: flex-end;
    }
  }
}
</style>
