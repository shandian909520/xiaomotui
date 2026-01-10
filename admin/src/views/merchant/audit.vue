<template>
  <div class="merchant-audit-container">
    <!-- 页面标题 -->
    <div class="page-header">
      <h1>商家审核</h1>
      <div class="header-actions">
        <el-button @click="loadMerchants" :loading="loading" icon="Refresh">刷新</el-button>
      </div>
    </div>

    <!-- 搜索和筛选栏 -->
    <div class="filter-bar">
      <el-form :inline="true" :model="filterForm" class="filter-form">
        <el-form-item label="审核状态">
          <el-select v-model="filterForm.status" placeholder="全部状态" @change="handleFilterChange" clearable>
            <el-option label="待审核" :value="2"></el-option>
            <el-option label="已通过" :value="1"></el-option>
            <el-option label="已拒绝" :value="0"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="搜索">
          <el-input
            v-model="filterForm.keyword"
            placeholder="商家名称或联系电话"
            clearable
            @clear="handleFilterChange"
            @keyup.enter="handleFilterChange"
            style="width: 240px">
            <template #append>
              <el-button icon="Search" @click="handleFilterChange"></el-button>
            </template>
          </el-input>
        </el-form-item>
      </el-form>
    </div>

    <!-- 主内容区域 -->
    <div class="content-wrapper">
      <!-- 左侧商家列表 -->
      <div class="merchant-list">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>商家列表 (共 {{ total }} 条)</span>
            </div>
          </template>

          <div v-loading="loading" class="list-content">
            <el-empty v-if="!loading && merchants.length === 0" description="暂无数据"></el-empty>

            <div
              v-for="merchant in merchants"
              :key="merchant.id"
              class="merchant-item"
              :class="{ active: selectedMerchant?.id === merchant.id }"
              @click="selectMerchant(merchant)">
              <div class="merchant-item-header">
                <div class="merchant-name">
                  <span class="name">{{ merchant.name }}</span>
                  <el-tag :type="getStatusTagType(merchant.status)" size="small">
                    {{ getStatusText(merchant.status) }}
                  </el-tag>
                </div>
                <div class="merchant-category">{{ merchant.category || '未分类' }}</div>
              </div>
              <div class="merchant-item-body">
                <div class="info-item">
                  <el-icon><Location /></el-icon>
                  <span>{{ merchant.address || '暂无地址' }}</span>
                </div>
                <div class="info-item">
                  <el-icon><Phone /></el-icon>
                  <span>{{ merchant.phone || '暂无电话' }}</span>
                </div>
                <div class="info-item">
                  <el-icon><Clock /></el-icon>
                  <span>{{ formatDate(merchant.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- 分页 -->
          <div class="pagination-wrapper" v-if="total > 0">
            <el-pagination
              v-model:current-page="pagination.page"
              v-model:page-size="pagination.limit"
              :page-sizes="[10, 20, 50, 100]"
              :total="total"
              layout="total, sizes, prev, pager, next, jumper"
              @size-change="handleSizeChange"
              @current-change="handlePageChange">
            </el-pagination>
          </div>
        </el-card>
      </div>

      <!-- 右侧商家详情 -->
      <div class="merchant-detail">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>商家详情</span>
            </div>
          </template>

          <el-empty v-if="!selectedMerchant" description="请选择商家查看详情"></el-empty>

          <div v-else class="detail-content">
            <!-- Logo -->
            <div class="detail-section" v-if="selectedMerchant.logo">
              <div class="section-title">商家Logo</div>
              <div class="logo-preview">
                <el-image
                  :src="selectedMerchant.logo"
                  fit="contain"
                  :preview-src-list="[selectedMerchant.logo]"
                  style="width: 150px; height: 150px; border-radius: 8px;">
                  <template #error>
                    <div class="image-error">
                      <el-icon><Picture /></el-icon>
                      <span>加载失败</span>
                    </div>
                  </template>
                </el-image>
              </div>
            </div>

            <!-- 基本信息 -->
            <div class="detail-section">
              <div class="section-title">基本信息</div>
              <div class="info-grid">
                <div class="info-row">
                  <label>商家名称：</label>
                  <span>{{ selectedMerchant.name }}</span>
                </div>
                <div class="info-row">
                  <label>商家类别：</label>
                  <span>{{ selectedMerchant.category || '未分类' }}</span>
                </div>
                <div class="info-row">
                  <label>联系电话：</label>
                  <span>{{ selectedMerchant.phone || '暂无' }}</span>
                </div>
                <div class="info-row">
                  <label>商家地址：</label>
                  <span>{{ selectedMerchant.address || '暂无' }}</span>
                </div>
                <div class="info-row">
                  <label>审核状态：</label>
                  <el-tag :type="getStatusTagType(selectedMerchant.status)">
                    {{ getStatusText(selectedMerchant.status) }}
                  </el-tag>
                </div>
                <div class="info-row">
                  <label>申请时间：</label>
                  <span>{{ formatDate(selectedMerchant.created_at) }}</span>
                </div>
              </div>
            </div>

            <!-- 商家描述 -->
            <div class="detail-section" v-if="selectedMerchant.description">
              <div class="section-title">商家描述</div>
              <div class="description-content">
                {{ selectedMerchant.description }}
              </div>
            </div>

            <!-- 营业时间 -->
            <div class="detail-section" v-if="selectedMerchant.business_hours">
              <div class="section-title">营业时间</div>
              <div class="info-content">
                {{ selectedMerchant.business_hours }}
              </div>
            </div>

            <!-- 地理位置 -->
            <div class="detail-section" v-if="selectedMerchant.latitude && selectedMerchant.longitude">
              <div class="section-title">地理位置</div>
              <div class="info-grid">
                <div class="info-row">
                  <label>纬度：</label>
                  <span>{{ selectedMerchant.latitude }}</span>
                </div>
                <div class="info-row">
                  <label>经度：</label>
                  <span>{{ selectedMerchant.longitude }}</span>
                </div>
              </div>
            </div>

            <!-- 审核操作 -->
            <div class="detail-section" v-if="selectedMerchant.status === 2">
              <div class="section-title">审核操作</div>
              <div class="action-buttons">
                <el-button
                  type="success"
                  :loading="actionLoading"
                  @click="handleApprove">
                  <el-icon><Check /></el-icon>
                  <span>通过审核</span>
                </el-button>
                <el-button
                  type="danger"
                  :loading="actionLoading"
                  @click="handleReject">
                  <el-icon><Close /></el-icon>
                  <span>拒绝审核</span>
                </el-button>
              </div>
            </div>

            <!-- 拒绝原因（如果已拒绝） -->
            <div class="detail-section" v-if="selectedMerchant.status === 0 && selectedMerchant.reject_reason">
              <div class="section-title">拒绝原因</div>
              <div class="reject-reason">
                {{ selectedMerchant.reject_reason }}
              </div>
            </div>
          </div>
        </el-card>
      </div>
    </div>

    <!-- 拒绝原因对话框 -->
    <el-dialog
      v-model="rejectDialogVisible"
      title="拒绝审核"
      width="500px"
      :close-on-click-modal="false">
      <el-form :model="rejectForm" :rules="rejectRules" ref="rejectFormRef">
        <el-form-item label="拒绝原因" prop="reason">
          <el-input
            v-model="rejectForm.reason"
            type="textarea"
            :rows="4"
            placeholder="请输入拒绝原因"
            maxlength="200"
            show-word-limit>
          </el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rejectDialogVisible = false">取消</el-button>
        <el-button type="danger" :loading="actionLoading" @click="confirmReject">确认拒绝</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Location,
  Phone,
  Clock,
  Picture,
  Check,
  Close,
  Refresh,
  Search
} from '@element-plus/icons-vue'
import request from '@/utils/request'

// 状态数据
const loading = ref(false)
const actionLoading = ref(false)
const merchants = ref([])
const selectedMerchant = ref(null)
const total = ref(0)
const rejectDialogVisible = ref(false)
const rejectFormRef = ref(null)

// 筛选表单
const filterForm = reactive({
  status: 2, // 默认显示待审核
  keyword: ''
})

// 分页
const pagination = reactive({
  page: 1,
  limit: 20
})

// 拒绝表单
const rejectForm = reactive({
  reason: ''
})

// 拒绝表单验证规则
const rejectRules = {
  reason: [
    { required: true, message: '请输入拒绝原因', trigger: 'blur' },
    { min: 5, max: 200, message: '拒绝原因长度在 5 到 200 个字符', trigger: 'blur' }
  ]
}

// 加载商家列表
const loadMerchants = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      limit: pagination.limit
    }

    // 添加状态筛选
    if (filterForm.status !== undefined && filterForm.status !== null && filterForm.status !== '') {
      params.status = filterForm.status
    }

    // 添加关键词搜索
    if (filterForm.keyword) {
      params.keyword = filterForm.keyword
    }

    const response = await request.get('/merchant/list', { params })

    merchants.value = response.list || []
    total.value = response.total || 0

    // 如果当前选中的商家不在新列表中，清除选中
    if (selectedMerchant.value) {
      const exists = merchants.value.find(m => m.id === selectedMerchant.value.id)
      if (!exists) {
        selectedMerchant.value = null
      }
    }
  } catch (error) {
    console.error('加载商家列表失败:', error)
    ElMessage.error('加载商家列表失败')
  } finally {
    loading.value = false
  }
}

// 选择商家
const selectMerchant = async (merchant) => {
  try {
    // 加载详细信息
    const response = await request.get(`/merchant/${merchant.id}`)
    selectedMerchant.value = response
  } catch (error) {
    console.error('加载商家详情失败:', error)
    ElMessage.error('加载商家详情失败')
  }
}

// 审核通过
const handleApprove = async () => {
  try {
    await ElMessageBox.confirm(
      `确认通过商家"${selectedMerchant.value.name}"的审核吗？`,
      '确认操作',
      {
        confirmButtonText: '确认通过',
        cancelButtonText: '取消',
        type: 'success'
      }
    )

    actionLoading.value = true
    await request.post(`/merchant/${selectedMerchant.value.id}/approve`)

    ElMessage.success('审核通过成功')

    // 刷新列表
    await loadMerchants()

    // 清除选中
    selectedMerchant.value = null
  } catch (error) {
    if (error !== 'cancel') {
      console.error('审核通过失败:', error)
      ElMessage.error('审核通过失败')
    }
  } finally {
    actionLoading.value = false
  }
}

// 打开拒绝对话框
const handleReject = () => {
  rejectForm.reason = ''
  rejectDialogVisible.value = true
}

// 确认拒绝
const confirmReject = async () => {
  if (!rejectFormRef.value) return

  try {
    await rejectFormRef.value.validate()

    actionLoading.value = true
    await request.post(`/merchant/${selectedMerchant.value.id}/reject`, {
      reason: rejectForm.reason
    })

    ElMessage.success('已拒绝该商家')
    rejectDialogVisible.value = false

    // 刷新列表
    await loadMerchants()

    // 清除选中
    selectedMerchant.value = null
  } catch (error) {
    if (error !== 'cancel') {
      console.error('拒绝审核失败:', error)
      ElMessage.error('拒绝审核失败')
    }
  } finally {
    actionLoading.value = false
  }
}

// 筛选变化
const handleFilterChange = () => {
  pagination.page = 1
  loadMerchants()
}

// 分页变化
const handlePageChange = (page) => {
  pagination.page = page
  loadMerchants()
}

const handleSizeChange = (size) => {
  pagination.limit = size
  pagination.page = 1
  loadMerchants()
}

// 获取状态文本
const getStatusText = (status) => {
  const statusMap = {
    0: '已拒绝',
    1: '已通过',
    2: '待审核'
  }
  return statusMap[status] || '未知'
}

// 获取状态标签类型
const getStatusTagType = (status) => {
  const typeMap = {
    0: 'danger',
    1: 'success',
    2: 'warning'
  }
  return typeMap[status] || 'info'
}

// 格式化日期
const formatDate = (dateStr) => {
  if (!dateStr) return '暂无'
  const date = new Date(dateStr)
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day} ${hours}:${minutes}`
}

// 页面加载时获取商家列表
onMounted(() => {
  loadMerchants()
})
</script>

<style lang="scss" scoped>
.merchant-audit-container {
  padding: 20px;
  min-height: 100vh;
  background: #f5f7fa;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);

  h1 {
    margin: 0;
    font-size: 24px;
    color: #333;
  }

  .header-actions {
    display: flex;
    gap: 10px;
  }
}

.filter-bar {
  margin-bottom: 20px;
  padding: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);

  .filter-form {
    margin: 0;

    .el-form-item {
      margin-bottom: 0;
    }
  }
}

.content-wrapper {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  align-items: start;
}

.merchant-list {
  .list-content {
    min-height: 400px;
    max-height: calc(100vh - 400px);
    overflow-y: auto;
  }

  .merchant-item {
    padding: 15px;
    margin-bottom: 10px;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;

    &:hover {
      border-color: #409eff;
      background: #f5f7fa;
    }

    &.active {
      border-color: #409eff;
      background: #ecf5ff;
    }

    &:last-child {
      margin-bottom: 0;
    }

    .merchant-item-header {
      margin-bottom: 10px;

      .merchant-name {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;

        .name {
          font-size: 16px;
          font-weight: 600;
          color: #333;
        }
      }

      .merchant-category {
        font-size: 14px;
        color: #909399;
      }
    }

    .merchant-item-body {
      .info-item {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 5px;
        font-size: 13px;
        color: #606266;

        &:last-child {
          margin-bottom: 0;
        }

        .el-icon {
          font-size: 14px;
          color: #909399;
        }
      }
    }
  }

  .pagination-wrapper {
    margin-top: 20px;
    display: flex;
    justify-content: center;
  }
}

.merchant-detail {
  .detail-content {
    .detail-section {
      margin-bottom: 25px;

      &:last-child {
        margin-bottom: 0;
      }

      .section-title {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e4e7ed;
      }

      .logo-preview {
        display: flex;
        justify-content: center;
        padding: 20px;
        background: #f5f7fa;
        border-radius: 8px;

        .image-error {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 10px;
          color: #909399;

          .el-icon {
            font-size: 48px;
          }
        }
      }

      .info-grid {
        display: grid;
        gap: 15px;

        .info-row {
          display: grid;
          grid-template-columns: 100px 1fr;
          align-items: center;

          label {
            font-weight: 500;
            color: #606266;
          }

          span {
            color: #333;
          }
        }
      }

      .description-content,
      .info-content {
        padding: 15px;
        background: #f5f7fa;
        border-radius: 6px;
        line-height: 1.6;
        color: #333;
      }

      .reject-reason {
        padding: 15px;
        background: #fef0f0;
        border-radius: 6px;
        color: #f56c6c;
        line-height: 1.6;
      }

      .action-buttons {
        display: flex;
        gap: 15px;

        .el-button {
          flex: 1;
          height: 44px;
          font-size: 16px;
        }
      }
    }
  }
}

.card-header {
  font-size: 16px;
  font-weight: 600;
  color: #333;
}

// 响应式布局
@media (max-width: 1400px) {
  .content-wrapper {
    grid-template-columns: 1fr;

    .merchant-detail {
      position: sticky;
      top: 20px;
    }
  }
}
</style>
