<template>
  <div class="video-library">
    <PageHeader title="视频库" subtitle="管理视频成品库，查看使用统计" />

    <!-- 搜索和操作栏 -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="搜索库名称" clearable @keyup.enter="handleSearch" />
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker
            v-model="searchForm.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 260px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
      <div class="actions">
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>
          新增成片库
        </el-button>
      </div>
    </el-card>

    <!-- 视频库列表 -->
    <el-card class="table-card">
      <el-table :data="libraryList" v-loading="loading" stripe>
        <el-table-column prop="name" label="库名称" min-width="160" />
        <el-table-column prop="totalCount" label="总视频数" width="100" align="center">
          <template #default="{ row }">
            {{ row.totalCount || row.total_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="已使用" width="100" align="center">
          <template #default="{ row }">
            {{ row.usedCount || row.used_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="剩余" width="100" align="center">
          <template #default="{ row }">
            {{ row.remainCount || row.remaining_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="最多使用次数" width="120" align="center">
          <template #default="{ row }">
            {{ row.maxCount || row.max_use_count || 0 }}
          </template>
        </el-table-column>
        <el-table-column label="使用比例" width="200">
          <template #default="{ row }">
            <el-progress
              :percentage="getUsedPercentage(row)"
              :color="getProgressColor(row)"
              :stroke-width="14"
              :text-inside="true"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" @click="handleDetail(row)">详情</el-button>
            <el-button size="small" @click="handleSetEmail(row)">预警</el-button>
            <el-button size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadList"
        @current-change="loadList"
      />
    </el-card>

    <!-- 新增/编辑库弹窗 -->
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px" @close="formRef?.resetFields()">
      <el-form :model="libraryForm" :rules="formRules" ref="formRef" label-width="120px">
        <el-form-item label="库名称" prop="name">
          <el-input v-model="libraryForm.name" placeholder="请输入库名称" />
        </el-form-item>
        <el-form-item label="最多使用次数" prop="maxCount">
          <el-input-number v-model="libraryForm.maxCount" :min="1" :max="999999" />
        </el-form-item>
        <el-form-item label="预警提示邮箱">
          <el-input v-model="libraryForm.warningEmail" placeholder="请输入预警邮箱" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- 设置预警邮箱弹窗 -->
    <el-dialog v-model="emailDialogVisible" title="设置预警邮箱" width="400px">
      <el-form label-width="80px">
        <el-form-item label="邮箱">
          <el-input v-model="warningEmail" placeholder="请输入预警邮箱" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="emailDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveEmail" :loading="emailSaving">确定</el-button>
      </template>
    </el-dialog>

    <!-- 库详情弹窗 -->
    <el-dialog v-model="detailVisible" :title="`${currentLibrary.name} - 视频详情`" width="900px" top="5vh">
      <div class="detail-header">
        <div class="detail-stats">
          <span>总视频: {{ currentLibrary.videoCount || 0 }}</span>
          <span>已使用: {{ currentLibrary.usedCount || 0 }}</span>
          <span>剩余: {{ currentLibrary.remainCount || 0 }}</span>
        </div>
        <div class="detail-actions">
          <el-button type="primary" size="small" @click="handleAddLocalVideo">添加本地视频</el-button>
          <el-button size="small" @click="handleImportVideo">导入视频</el-button>
        </div>
      </div>

      <div class="video-grid" v-loading="detailLoading">
        <div v-for="video in videoList" :key="video.id" class="video-card">
          <div class="video-cover">
            <img v-if="video.cover || video.thumbnailUrl" :src="video.cover || video.thumbnailUrl" alt="封面" />
            <div v-else class="video-cover-placeholder">
              <el-icon :size="32"><VideoPlay /></el-icon>
            </div>
            <div class="video-duration" v-if="video.duration">{{ video.duration }}s</div>
          </div>
          <div class="video-info">
            <div class="video-title">{{ video.title }}</div>
            <div class="video-meta">使用次数: {{ video.useCount || 0 }}</div>
            <div class="video-card-actions">
              <el-button size="small" type="danger" text @click="handleDeleteVideo(video)">删除</el-button>
            </div>
          </div>
        </div>
      </div>

      <EmptyPanel v-if="videoList.length === 0 && !detailLoading" message="暂无视频，请添加" />
    </el-dialog>

    <!-- 添加本地视频弹窗 -->
    <el-dialog v-model="addVideoVisible" title="添加本地视频" width="500px">
      <el-form label-width="80px">
        <el-form-item label="视频标题">
          <el-input v-model="addVideoForm.title" placeholder="请输入视频标题" />
        </el-form-item>
        <el-form-item label="选择视频">
          <el-upload
            ref="videoUploadRef"
            :auto-upload="false"
            accept="video/*"
            :limit="1"
            :on-change="handleVideoChange"
          >
            <el-button type="primary">选择文件</el-button>
          </el-upload>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addVideoVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAddVideo" :loading="addVideoSaving">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, VideoPlay } from '@element-plus/icons-vue'
import { PageHeader, EmptyPanel } from '@/components/xmt'
import {
  getVideoLibraryList, createVideoLibrary, updateVideoLibrary,
  deleteVideoLibrary, getVideoLibraryDetail, addLocalVideo, importVideo,
  setLibraryWarningEmail, deleteLibraryItem
} from '@/api/index'
import { normalizePagination, normalizeListPayload } from '@/utils/responseHelper'

const loading = ref(false)
const libraryList = ref([])
const searchForm = reactive({ name: '', dateRange: null })
const pagination = reactive({ page: 1, limit: 10, total: 0 })

const dialogVisible = ref(false)
const dialogTitle = ref('新增成片库')
const isEdit = ref(false)
const formRef = ref(null)
const submitting = ref(false)
const libraryForm = reactive({ id: null, name: '', maxCount: 100, warningEmail: '' })
const formRules = {
  name: [{ required: true, message: '请输入库名称', trigger: 'blur' }],
  maxCount: [{ required: true, message: '请输入最多使用次数', trigger: 'blur' }]
}

const emailDialogVisible = ref(false)
const warningEmail = ref('')
const currentEmailId = ref(null)
const emailSaving = ref(false)

const detailVisible = ref(false)
const detailLoading = ref(false)
const currentLibrary = reactive({ id: null, name: '', videoCount: 0, usedCount: 0, remainCount: 0 })
const videoList = ref([])

const addVideoVisible = ref(false)
const addVideoForm = reactive({ title: '', file: null })
const addVideoSaving = ref(false)
const videoUploadRef = ref(null)


const loadList = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, limit: pagination.limit }
    if (searchForm.name) params.name = searchForm.name
    if (searchForm.dateRange?.length === 2) {
      params.startDate = searchForm.dateRange[0]
      params.endDate = searchForm.dateRange[1]
    }
    const res = await getVideoLibraryList(params)
    const { list, total } = normalizePagination(res)
    libraryList.value = list
    pagination.total = total
  } catch (err) {
    console.error('获取视频库列表失败:', err)
    libraryList.value = []
    pagination.total = 0
    ElMessage.error('获取视频库列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const getUsedPercentage = (row) => {
  const maxCount = row.maxCount || row.max_use_count || 0
  const usedCount = row.usedCount || row.used_count || 0
  if (!maxCount) return 0
  return Math.min(Math.round((usedCount / maxCount) * 100), 100)
}

const getProgressColor = (row) => {
  const p = getUsedPercentage(row)
  if (p >= 90) return '#F56C6C'
  if (p >= 70) return '#E6A23C'
  return '#7b50ff'
}

const handleSearch = () => {
  pagination.page = 1
  loadList()
}

const handleReset = () => {
  searchForm.name = ''
  searchForm.dateRange = null
  handleSearch()
}

const handleAdd = () => {
  dialogTitle.value = '新增成片库'
  isEdit.value = false
  Object.assign(libraryForm, { id: null, name: '', maxCount: 100, warningEmail: '' })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  dialogTitle.value = '编辑成片库'
  isEdit.value = true
  Object.assign(libraryForm, {
    id: row.id,
    name: row.name,
    maxCount: row.maxCount || row.max_use_count || 100,
    warningEmail: row.warningEmail || row.warning_email || ''
  })
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()
  submitting.value = true
  try {
    const data = { name: libraryForm.name, maxCount: libraryForm.maxCount, warningEmail: libraryForm.warningEmail }
    if (isEdit.value) {
      await updateVideoLibrary(libraryForm.id, data)
      ElMessage.success('更新成功')
    } else {
      await createVideoLibrary(data)
      ElMessage.success('新增成功')
    }
    dialogVisible.value = false
    loadList()
  } catch (err) {
    console.error('提交失败:', err)
    ElMessage.error(err.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除 "${row.name}" 吗？`, '提示', { type: 'warning' })
    await deleteVideoLibrary(row.id)
    ElMessage.success('删除成功')
    loadList()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('删除失败:', err)
      ElMessage.error(err.message || '删除失败')
    }
  }
}

const handleSetEmail = (row) => {
  currentEmailId.value = row.id
  warningEmail.value = row.warningEmail || ''
  emailDialogVisible.value = true
}

const handleSaveEmail = async () => {
  if (!warningEmail.value) {
    ElMessage.warning('请输入邮箱')
    return
  }
  emailSaving.value = true
  try {
    await setLibraryWarningEmail(currentEmailId.value, warningEmail.value)
    ElMessage.success('设置成功')
    emailDialogVisible.value = false
    loadList()
  } catch (err) {
    console.error('设置预警邮箱失败:', err)
    ElMessage.error(err.message || '设置失败')
  } finally {
    emailSaving.value = false
  }
}

const handleDetail = async (row) => {
  Object.assign(currentLibrary, {
    id: row.id,
    name: row.name,
    videoCount: row.totalCount || row.total_count || 0,
    usedCount: row.usedCount || row.used_count || 0,
    remainCount: row.remainCount || row.remaining_count || 0
  })
  detailVisible.value = true
  detailLoading.value = true
  try {
    const res = await getVideoLibraryDetail(row.id)
    videoList.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取视频详情失败:', err)
    videoList.value = []
    ElMessage.error('获取视频详情失败，请稍后重试')
  } finally {
    detailLoading.value = false
  }
}

const handleAddLocalVideo = () => {
  addVideoForm.title = ''
  addVideoForm.file = null
  addVideoVisible.value = true
}

const handleVideoChange = (file) => {
  addVideoForm.file = file.raw
}

const submitAddVideo = async () => {
  if (!addVideoForm.title) {
    ElMessage.warning('请输入视频标题')
    return
  }
  addVideoSaving.value = true
  try {
    await addLocalVideo(currentLibrary.id, { title: addVideoForm.title, file: addVideoForm.file })
    ElMessage.success('添加成功')
    addVideoVisible.value = false
    handleDetail(currentLibrary)
  } catch (err) {
    console.error('添加视频失败:', err)
    ElMessage.error(err.message || '添加失败')
  } finally {
    addVideoSaving.value = false
  }
}

const handleImportVideo = async () => {
  try {
    await importVideo(currentLibrary.id, {})
    ElMessage.success('导入成功')
    handleDetail(currentLibrary)
  } catch (err) {
    console.error('导入视频失败:', err)
    ElMessage.error(err.message || '导入失败')
  }
}

const handleDeleteVideo = async (video) => {
  try {
    await ElMessageBox.confirm(`确定删除视频 "${video.title}" 吗？`, '提示', { type: 'warning' })
    await deleteLibraryItem(video.id)
    ElMessage.success('删除成功')
    handleDetail(currentLibrary)
  } catch (err) {
    if (err !== 'cancel') {
      console.error('删除视频失败:', err)
      ElMessage.error(err.message || '删除失败')
    }
  }
}

onMounted(() => {
  loadList()
})
</script>

<style scoped lang="scss">
.video-library {
  padding: 20px;
}
.search-card {
  margin-bottom: 20px;
  .actions {
    margin-top: 10px;
    display: flex;
    gap: 10px;
  }
}
.table-card {
  :deep(.el-pagination) {
    margin-top: 20px;
    justify-content: flex-end;
  }
}
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #eee;
  .detail-stats {
    display: flex;
    gap: 24px;
    font-size: 14px;
    color: #606266;
  }
}
.video-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.video-card {
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #ebeef5;
  &:hover {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  }
}
.video-cover {
  position: relative;
  aspect-ratio: 16/9;
  background: #f5f7fa;
  .video-cover-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c0c4cc;
  }
  img { width: 100%; height: 100%; object-fit: cover; }
  .video-duration {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
  }
}
.video-info { padding: 12px; }
.video-title { font-size: 14px; font-weight: 500; color: #303133; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.video-meta { font-size: 12px; color: #909399; margin-bottom: 8px; }
.video-card-actions { display: flex; gap: 4px; }
</style>
