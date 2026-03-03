<template>
  <div class="system-users">
    <!-- 搜索筛选 -->
    <el-card shadow="never">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input v-model="queryParams.keyword" placeholder="昵称/手机号" clearable />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable>
            <el-option label="正常" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="会员等级">
          <el-select v-model="queryParams.member_level" placeholder="全部" clearable>
            <el-option label="基础会员" value="BASIC" />
            <el-option label="VIP会员" value="VIP" />
            <el-option label="高级会员" value="PREMIUM" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 数据表格 -->
    <el-card shadow="never" style="margin-top: 16px">
      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="nickname" label="昵称" min-width="100" show-overflow-tooltip />
        <el-table-column prop="phone" label="手机号" width="130">
          <template #default="{ row }">{{ row.phone || '-' }}</template>
        </el-table-column>
        <el-table-column prop="member_level" label="会员等级" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getLevelType(row.member_level)" size="small">
              {{ getLevelText(row.member_level) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="points" label="积分" width="80" align="center" />
        <el-table-column prop="status" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="row.status === 1"
              @change="(val) => handleStatusChange(row, val)"
              :loading="row._statusLoading"
            />
          </template>
        </el-table-column>
        <el-table-column prop="last_login_time" label="最后登录" width="170">
          <template #default="{ row }">{{ row.last_login_time || '-' }}</template>
        </el-table-column>
        <el-table-column prop="create_time" label="注册时间" width="170" />
        <el-table-column label="操作" width="80" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="showDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="queryParams.page"
          v-model:page-size="queryParams.page_size"
          :total="total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="fetchData"
          @current-change="fetchData"
        />
      </div>
    </el-card>

    <!-- 用户详情弹窗 -->
    <el-dialog v-model="detailVisible" title="用户详情" width="500px">
      <el-descriptions :column="1" border v-if="currentRow">
        <el-descriptions-item label="ID">{{ currentRow.id }}</el-descriptions-item>
        <el-descriptions-item label="昵称">{{ currentRow.nickname }}</el-descriptions-item>
        <el-descriptions-item label="手机号">{{ currentRow.phone || '-' }}</el-descriptions-item>
        <el-descriptions-item label="会员等级">{{ getLevelText(currentRow.member_level) }}</el-descriptions-item>
        <el-descriptions-item label="积分">{{ currentRow.points }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="currentRow.status === 1 ? 'success' : 'danger'" size="small">
            {{ currentRow.status === 1 ? '正常' : '禁用' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="最后登录">{{ currentRow.last_login_time || '-' }}</el-descriptions-item>
        <el-descriptions-item label="注册时间">{{ currentRow.create_time }}</el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { systemApi } from '@/api/system'
import { ElMessage, ElMessageBox } from 'element-plus'

const loading = ref(false)
const tableData = ref([])
const total = ref(0)
const detailVisible = ref(false)
const currentRow = ref(null)

const queryParams = reactive({
  page: 1,
  page_size: 20,
  keyword: '',
  status: '',
  member_level: ''
})

const levelMap = { BASIC: '基础会员', VIP: 'VIP会员', PREMIUM: '高级会员' }
const levelTypeMap = { BASIC: 'info', VIP: 'warning', PREMIUM: 'danger' }

const getLevelText = (l) => levelMap[l] || l || '未知'
const getLevelType = (l) => levelTypeMap[l] || ''

const handleSearch = () => {
  queryParams.page = 1
  fetchData()
}

const handleReset = () => {
  Object.assign(queryParams, { keyword: '', status: '', member_level: '', page: 1 })
  fetchData()
}

const showDetail = (row) => {
  currentRow.value = row
  detailVisible.value = true
}

const handleStatusChange = async (row, val) => {
  const newStatus = val ? 1 : 0
  const action = newStatus === 1 ? '启用' : '禁用'
  try {
    await ElMessageBox.confirm(`确定要${action}该用户吗？`, '提示', { type: 'warning' })
    row._statusLoading = true
    const res = await systemApi.updateUserStatus(row.id, newStatus)
    if (res.code === 200) {
      row.status = newStatus
      ElMessage.success(`用户已${action}`)
    }
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('操作失败')
  } finally {
    row._statusLoading = false
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await systemApi.getUsers(queryParams)
    if (res.code === 200) {
      tableData.value = (res.data?.list || []).map(item => ({ ...item, _statusLoading: false }))
      total.value = res.data?.total || 0
    }
  } catch (e) {
    ElMessage.error('获取用户列表失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchData())
</script>

<style scoped>
.system-users { padding: 20px; }
.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>