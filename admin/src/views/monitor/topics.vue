<template>
  <div class="monitor-topics">
    <!-- 顶部标题 -->
    <div class="page-header">
      <div class="header-content">
        <h2>话题监控</h2>
        <p class="subtitle">监控热门话题趋势和数据</p>
      </div>
      <div class="header-tip">数据每日凌晨3点更新</div>
    </div>

    <!-- 平台切换 + 搜索 -->
    <el-card class="filter-card">
      <div class="filter-row">
        <el-radio-group v-model="platform" @change="handleSearch">
          <el-radio-button label="抖音" value="douyin" />
          <el-radio-button label="快手" value="kuaishou" />
        </el-radio-group>
        <el-input
          v-model="searchKeyword"
          placeholder="输入话题关键词搜索"
          clearable
          style="width: 280px; margin-left: 16px"
          @keyup.enter="handleSearch"
        >
          <template #append>
            <el-button @click="handleSearch">搜索</el-button>
          </template>
        </el-input>
        <el-button type="primary" style="margin-left: 12px" @click="handleAddMonitor">
          <el-icon><Plus /></el-icon>
          添加监控
        </el-button>
      </div>
    </el-card>

    <!-- 监控列表 -->
    <el-card class="table-card">
      <el-table :data="monitorList" v-loading="loading" stripe>
        <el-table-column prop="keyword" label="话题关键词" min-width="160">
          <template #default="{ row }">#{{ row.keyword }}#</template>
        </el-table-column>
        <el-table-column label="平台" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.platform === 'douyin' ? 'danger' : 'warning'">
              {{ row.platform === 'douyin' ? '抖音' : '快手' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="totalPlayCount" label="总播放量" width="120" align="center">
          <template #default="{ row }">{{ formatNumber(row.totalPlayCount) }}</template>
        </el-table-column>
        <el-table-column prop="totalPostCount" label="总投稿量" width="120" align="center">
          <template #default="{ row }">{{ formatNumber(row.totalPostCount) }}</template>
        </el-table-column>
        <el-table-column prop="yesterdayPlayCount" label="昨日新增播放" width="130" align="center">
          <template #default="{ row }">{{ formatNumber(row.yesterdayPlayCount) }}</template>
        </el-table-column>
        <el-table-column prop="yesterdayPostCount" label="昨日新增投稿" width="130" align="center">
          <template #default="{ row }">{{ formatNumber(row.yesterdayPostCount) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'">
              {{ row.status === 'active' ? '监控中' : '已取消' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="handleViewDetail(row)">查看详情</el-button>
            <el-button v-if="row.status === 'active'" size="small" type="danger" link @click="handleCancelMonitor(row)">取消监控</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 添加监控弹窗 -->
    <el-dialog v-model="addDialogVisible" title="添加话题监控" width="500px" @close="resetAddForm">
      <el-form :model="addForm" :rules="addRules" ref="addFormRef" label-width="100px">
        <el-form-item label="选择平台" prop="platform">
          <el-radio-group v-model="addForm.platform">
            <el-radio label="douyin">抖音</el-radio>
            <el-radio label="kuaishou">快手</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="话题关键词" prop="keyword">
          <el-input v-model="addForm.keyword" placeholder="请输入话题关键词" />
        </el-form-item>
        <el-form-item label="话题链接">
          <el-input v-model="addForm.link" placeholder="请输入话题链接（可选）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleAddSubmit" :loading="addSubmitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailDialogVisible" title="话题监控详情" width="750px">
      <div v-if="currentTopic" v-loading="detailLoading">
        <el-descriptions :column="2" border class="detail-desc">
          <el-descriptions-item label="话题关键词">#{{ currentTopic.keyword }}#</el-descriptions-item>
          <el-descriptions-item label="平台">
            <el-tag :type="currentTopic.platform === 'douyin' ? 'danger' : 'warning'">
              {{ currentTopic.platform === 'douyin' ? '抖音' : '快手' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="总播放量">{{ formatNumber(currentTopic.totalPlayCount) }}</el-descriptions-item>
          <el-descriptions-item label="总投稿量">{{ formatNumber(currentTopic.totalPostCount) }}</el-descriptions-item>
          <el-descriptions-item label="昨日新增播放">{{ formatNumber(currentTopic.yesterdayPlayCount) }}</el-descriptions-item>
          <el-descriptions-item label="昨日新增投稿">{{ formatNumber(currentTopic.yesterdayPostCount) }}</el-descriptions-item>
        </el-descriptions>

        <!-- 近30天每日趋势 -->
        <div class="trend-section">
          <h4>近30天每日趋势</h4>
          <div class="trend-chart">
            <div v-for="(item, index) in dailyTrend" :key="index" class="trend-bar-group">
              <div class="trend-bar play-bar" :style="{ height: getBarHeight(item.playCount, maxPlay) + 'px' }">
                <span class="bar-tooltip">{{ formatNumber(item.playCount) }}</span>
              </div>
              <div class="trend-bar post-bar" :style="{ height: getBarHeight(item.postCount, maxPost) + 'px' }">
                <span class="bar-tooltip">{{ formatNumber(item.postCount) }}</span>
              </div>
              <span class="bar-date">{{ item.date.slice(5) }}</span>
            </div>
          </div>
          <div class="trend-legend">
            <span class="legend-item"><i class="legend-dot play"></i>播放量</span>
            <span class="legend-item"><i class="legend-dot post"></i>投稿量</span>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="detailDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getTopicMonitorList,
  addTopicMonitor,
  getTopicMonitorDetail,
  cancelTopicMonitor,
  getTopicMonitorDailyTrend
} from '@/api/index'
import { normalizeListPayload, snakeToCamel } from '@/utils/responseHelper'

const loading = ref(false)
const addSubmitting = ref(false)
const detailLoading = ref(false)
const addDialogVisible = ref(false)
const detailDialogVisible = ref(false)

const platform = ref('douyin')
const searchKeyword = ref('')
const monitorList = ref([])
const currentTopic = ref(null)
const dailyTrend = ref([])

const addForm = reactive({ platform: 'douyin', keyword: '', link: '' })
const addFormRef = ref(null)
const addRules = {
  platform: [{ required: true, message: '请选择平台', trigger: 'change' }],
  keyword: [{ required: true, message: '请输入话题关键词', trigger: 'blur' }]
}


const formatNumber = (num) => {
  if (!num) return '0'
  if (num >= 10000) return (num / 10000).toFixed(1) + '万'
  return num.toLocaleString()
}

const maxPlay = computed(() => Math.max(...dailyTrend.value.map(d => d.playCount), 1))
const maxPost = computed(() => Math.max(...dailyTrend.value.map(d => d.postCount), 1))

const getBarHeight = (value, max) => {
  return Math.max(Math.round((value / max) * 120), 4)
}

const handleSearch = () => {
  loadMonitorList()
}

const loadMonitorList = async () => {
  loading.value = true
  try {
    const params = { platform: platform.value }
    if (searchKeyword.value) params.keyword = searchKeyword.value
    const res = await getTopicMonitorList(params)
    const rawList = normalizeListPayload(res)
    // 映射后端 TopicMonitor 字段到前端期望的字段
    monitorList.value = rawList.map(item => ({
      id: item.id,
      keyword: item.keyword || '',
      platform: item.platform || 'douyin',
      totalPlayCount: item.totalPlayCount || 0,
      totalPostCount: item.totalPostCount || 0,
      yesterdayPlayCount: item.yesterdayPlayCount || 0,
      yesterdayPostCount: item.yesterdayPostCount || 0,
      // 后端 status: 1=监控中, 0=已取消
      status: item.status === 1 ? 'active' : 'cancelled'
    }))
  } catch (err) {
    console.error('获取监控列表失败:', err)
    monitorList.value = []
    ElMessage.error('获取监控列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const handleAddMonitor = () => {
  Object.assign(addForm, { platform: platform.value, keyword: '', link: '' })
  addDialogVisible.value = true
}

const resetAddForm = () => {
  addFormRef.value?.resetFields()
}

const handleAddSubmit = async () => {
  if (!addFormRef.value) return
  await addFormRef.value.validate()
  addSubmitting.value = true
  try {
    await addTopicMonitor({ ...addForm })
    ElMessage.success('添加监控成功')
    addDialogVisible.value = false
    loadMonitorList()
  } catch (err) {
    console.error('添加监控失败:', err)
    ElMessage.error(err.message || '添加失败')
  } finally {
    addSubmitting.value = false
  }
}

const handleCancelMonitor = async (row) => {
  try {
    await ElMessageBox.confirm(`确定取消监控话题 "#${row.keyword}#" 吗？`, '提示', { type: 'warning' })
    await cancelTopicMonitor({ id: row.id })
    ElMessage.success('已取消监控')
    loadMonitorList()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('取消监控失败:', err)
      ElMessage.error('操作失败')
    }
  }
}

const handleViewDetail = async (row) => {
  currentTopic.value = { ...row }
  detailDialogVisible.value = true
  detailLoading.value = true
  try {
    const res = await getTopicMonitorDetail(row.id)
    const data = res && typeof res === 'object' ? snakeToCamel(res) : {}
    // 确保详情数据中的字段名匹配
    const mapped = {
      ...data,
      totalPlayCount: data.totalPlayCount || 0,
      totalPostCount: data.totalPostCount || 0,
      yesterdayPlayCount: data.yesterdayPlayCount || 0,
      yesterdayPostCount: data.yesterdayPostCount || 0,
      status: data.status === 1 ? 'active' : 'cancelled'
    }
    Object.assign(currentTopic.value, mapped)
  } catch (err) {
    console.error('获取详情失败:', err)
  } finally {
    detailLoading.value = false
  }
  // 加载趋势数据
  try {
    const res = await getTopicMonitorDailyTrend({ id: row.id })
    dailyTrend.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取趋势数据失败:', err)
    dailyTrend.value = []
    ElMessage.error('获取趋势数据失败，请稍后重试')
  }
}

onMounted(() => {
  loadMonitorList()
})
</script>

<style scoped lang="scss">
.monitor-topics {
  padding: 20px;

  .page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 28px 32px;
    margin-bottom: 20px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;

    h2 {
      font-size: 28px;
      font-weight: 700;
      margin: 0 0 6px;
    }

    .subtitle {
      font-size: 14px;
      opacity: 0.85;
      margin: 0;
    }

    .header-tip {
      font-size: 12px;
      opacity: 0.75;
      background: rgba(255, 255, 255, 0.15);
      padding: 6px 12px;
      border-radius: 6px;
    }
  }

  .filter-card {
    margin-bottom: 20px;

    .filter-row {
      display: flex;
      align-items: center;
    }
  }

  .detail-desc {
    margin-bottom: 20px;
  }

  .trend-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ebeef5;

    h4 {
      font-size: 15px;
      font-weight: 600;
      margin: 0 0 16px;
      color: #303133;
    }
  }

  .trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 160px;
    padding: 0 4px;
    overflow-x: auto;
  }

  .trend-bar-group {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 16px;
    position: relative;

    .trend-bar {
      width: 8px;
      border-radius: 2px 2px 0 0;
      margin-bottom: 4px;
      position: relative;
      cursor: pointer;

      &.play-bar { background: #409eff; }
      &.post-bar { background: #e6a23c; }

      .bar-tooltip {
        display: none;
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #303133;
        color: #fff;
        font-size: 11px;
        padding: 2px 6px;
        border-radius: 4px;
        white-space: nowrap;
        margin-bottom: 4px;
      }

      &:hover .bar-tooltip {
        display: block;
      }
    }

    .bar-date {
      font-size: 10px;
      color: #c0c4cc;
      white-space: nowrap;
    }
  }

  .trend-legend {
    display: flex;
    gap: 20px;
    margin-top: 12px;
    justify-content: center;

    .legend-item {
      font-size: 12px;
      color: #606266;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .legend-dot {
      width: 10px;
      height: 10px;
      border-radius: 2px;
      display: inline-block;

      &.play { background: #409eff; }
      &.post { background: #e6a23c; }
    }
  }
}
</style>
