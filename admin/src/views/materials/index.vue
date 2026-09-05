<template>
  <div class="materials-page">
    <!-- 左右分栏布局 -->
    <div class="materials-layout">
      <!-- 左侧文件夹树 -->
      <div class="folder-sidebar">
        <div class="sidebar-header">
          <span class="sidebar-title">素材文件夹</span>
          <el-button type="primary" size="small" circle @click="handleAddFolder">
            <el-icon><Plus /></el-icon>
          </el-button>
        </div>
        <el-tree
          ref="folderTreeRef"
          :data="folderTree"
          node-key="id"
          highlight-current
          default-expand-all
          :expand-on-click-node="false"
          @node-click="handleFolderClick"
          @node-contextmenu="handleFolderRightClick"
        >
          <template #default="{ node, data }">
            <div class="folder-node" @dblclick="handleFolderDblClick(node, data)">
              <el-icon><Folder /></el-icon>
              <span v-if="!node.editing" class="folder-name">{{ node.label }}</span>
              <el-input
                v-else
                v-model="editingFolderName"
                size="small"
                style="width: 120px"
                @blur="handleFolderRenameConfirm(node, data)"
                @keyup.enter="handleFolderRenameConfirm(node, data)"
                @click.stop
              />
            </div>
          </template>
        </el-tree>
      </div>

      <!-- 右侧素材列表区域 -->
      <div class="material-content">
        <!-- 顶部标签页 -->
        <el-tabs v-model="activeTab" @tab-change="handleTabChange">
          <el-tab-pane label="全部" name="all" />
          <el-tab-pane label="视频" name="video" />
          <el-tab-pane label="图片" name="image" />
          <el-tab-pane label="背景音频" name="bgm" />
          <el-tab-pane label="旁白配音" name="voiceover" />
          <el-tab-pane label="拍摄指南要求" name="guide" />
          <el-tab-pane label="回收站" name="trash" />
        </el-tabs>

        <!-- 回收站视图 -->
        <template v-if="activeTab === 'trash'">
          <div class="filter-section">
            <div class="filter-row">
              <el-input
                v-model="trashSearch"
                placeholder="搜索素材名称"
                clearable
                style="width: 240px"
                @keyup.enter="loadTrashList"
              >
                <template #prefix><el-icon><Search /></el-icon></template>
              </el-input>
              <div class="filter-actions">
                <el-button v-if="selectedTrashIds.length > 0" type="success" @click="handleBatchRestore">
                  批量恢复 ({{ selectedTrashIds.length }})
                </el-button>
                <el-button v-if="selectedTrashIds.length > 0" type="danger" @click="handleBatchPermanentDelete">
                  批量彻底删除 ({{ selectedTrashIds.length }})
                </el-button>
              </div>
            </div>
          </div>
          <div v-loading="trashLoading" class="table-wrapper">
            <el-table :data="trashList" @selection-change="handleTrashSelectionChange">
              <el-table-column type="selection" width="50" />
              <el-table-column label="素材名称" prop="title" min-width="200" />
              <el-table-column label="类型" width="100">
                <template #default="{ row }">
                  <el-tag :type="getTypeTagType(row.type)" size="small">{{ getTypeLabel(row.type) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="大小" prop="size" width="100" />
              <el-table-column label="删除时间" prop="deletedAt" width="160" />
              <el-table-column label="操作" width="160" fixed="right">
                <template #default="{ row }">
                  <el-button size="small" type="success" link @click="handleRestore(row)">恢复</el-button>
                  <el-button size="small" type="danger" link @click="handlePermanentDelete(row)">彻底删除</el-button>
                </template>
              </el-table-column>
            </el-table>
            <el-empty v-if="!trashLoading && trashList.length === 0" description="回收站为空" />
          </div>
        </template>

        <!-- 普通素材视图 -->
        <template v-else>
          <!-- 筛选区域 -->
          <div class="filter-section">
            <div class="filter-row">
              <el-input
                v-model="searchName"
                placeholder="搜索素材名称"
                clearable
                style="width: 240px"
                @keyup.enter="handleSearch"
              >
                <template #prefix><el-icon><Search /></el-icon></template>
              </el-input>
              <el-select v-model="filterStatus" placeholder="使用状态" clearable style="width: 140px" @change="handleSearch">
                <el-option label="已使用" value="used" />
                <el-option label="未使用" value="unused" />
              </el-select>
              <el-switch
                v-model="onlyAiVideo"
                active-text="只看AI视频"
                @change="handleSearch"
              />
            </div>
            <div class="filter-actions">
              <el-button type="primary" @click="handleUpload">
                <el-icon><Upload /></el-icon>
                上传{{ uploadTypeLabel }}
              </el-button>
              <span class="upload-hint">{{ uploadHintText }}</span>
            </div>
          </div>

          <!-- 批量操作栏 -->
          <div v-if="selectedIds.length > 0" class="batch-bar">
            <el-checkbox v-model="isAllSelected" @change="handleToggleAll">全选</el-checkbox>
            <span class="batch-count">已选择 {{ selectedIds.length }} 项</span>
            <el-button size="small" @click="moveDialogVisible = true">
              <el-icon><FolderAdd /></el-icon>
              移动到文件夹
            </el-button>
            <el-button size="small" type="danger" @click="handleSoftDelete">
              <el-icon><Delete /></el-icon>
              删除
            </el-button>
            <el-button size="small" @click="selectedIds = []">取消选择</el-button>
          </div>

          <!-- 素材网格 -->
          <div v-loading="loading" class="card-grid">
            <div
              v-for="item in materials"
              :key="item.id"
              class="material-card"
              :class="{ selected: selectedIds.includes(item.id) }"
            >
              <div class="card-cover" @click="handlePreview(item)">
                <!-- 视频 -->
                <template v-if="item.type === 'video'">
                  <video v-if="item.url" :src="item.url" />
                  <div v-else class="cover-placeholder video-placeholder">
                    <el-icon><VideoPlay /></el-icon>
                  </div>
                  <div v-if="item.duration" class="video-duration">
                    {{ formatDuration(item.duration) }}
                  </div>
                  <div class="video-play-icon">
                    <el-icon><VideoPlay /></el-icon>
                  </div>
                </template>
                <!-- 图片 -->
                <template v-else-if="item.type === 'image'">
                  <img v-if="item.url || item.cover" :src="item.url || item.cover" :alt="item.title" />
                  <div v-else class="cover-placeholder image-placeholder">
                    <el-icon><Picture /></el-icon>
                  </div>
                </template>
                <!-- 音频 -->
                <template v-else-if="item.type === 'bgm' || item.type === 'voiceover'">
                  <div class="cover-placeholder audio-placeholder">
                    <el-icon><Headset /></el-icon>
                    <span>{{ item.duration ? formatDuration(item.duration) : '' }}</span>
                  </div>
                </template>
                <!-- 拍摄指南 -->
                <template v-else>
                  <div class="cover-placeholder guide-placeholder">
                    <el-icon><Document /></el-icon>
                  </div>
                </template>
                <!-- 勾选框 -->
                <div class="select-indicator" :class="{ active: selectedIds.includes(item.id) }" @click.stop="handleSelectItem(item)">
                  <el-icon><Check /></el-icon>
                </div>
              </div>
              <div class="card-info">
                <p class="card-title" :title="item.title">{{ item.title }}</p>
                <div class="card-meta">
                  <span class="meta-size">{{ item.size }}</span>
                  <span v-if="item.duration && item.type === 'video'" class="meta-duration">{{ formatDuration(item.duration) }}</span>
                  <span class="meta-date">{{ item.createdAt }}</span>
                </div>
                <div class="card-quick">
                  <el-button
                    size="small"
                    type="warning"
                    plain
                    round
                    @click.stop="handleAiCopy(item)"
                  >
                    ⚡ AI 文案
                  </el-button>
                  <el-button size="small" plain round @click.stop="handlePreview(item)">
                    预览
                  </el-button>
                </div>
              </div>
            </div>

            <el-empty v-if="!loading && materials.length === 0" description="暂无素材" class="empty-state" />
          </div>

          <!-- 分页 -->
          <div v-if="total > 0" class="pagination">
            <el-pagination
              v-model:current-page="currentPage"
              v-model:page-size="pageSize"
              :total="total"
              :page-sizes="[12, 24, 48, 96]"
              layout="total, sizes, prev, pager, next, jumper"
              @size-change="handleSizeChange"
              @current-change="handlePageChange"
            />
          </div>
        </template>
      </div>
    </div>

    <!-- 上传弹窗 -->
    <el-dialog v-model="uploadVisible" title="上传素材" width="500px">
      <el-upload
        ref="uploadRef"
        class="upload-area"
        drag
        action="#"
        :auto-upload="false"
        :on-change="handleFileChange"
        :on-remove="handleFileRemove"
        :file-list="fileList"
        multiple
        accept=".mov,.mp4,.3gp,video/*"
      >
        <el-icon class="upload-icon"><Upload /></el-icon>
        <div class="upload-text">将文件拖到此处，或<em>点击上传</em></div>
        <template #tip>
          <div class="upload-tip">支持 .mov、.mp4、.3gp 格式，单文件最大 300MB</div>
        </template>
      </el-upload>
      <template #footer>
        <el-button @click="uploadVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitUpload">上传</el-button>
      </template>
    </el-dialog>

    <!-- 预览弹窗 -->
    <el-dialog v-model="previewVisible" :title="previewItem?.title" width="800px">
      <div class="preview-content">
        <img v-if="previewItem?.type === 'image'" :src="previewItem?.url || previewItem?.cover" />
        <video v-else-if="previewItem?.type === 'video'" :src="previewItem?.url" controls />
        <div v-else-if="previewItem?.type === 'bgm' || previewItem?.type === 'voiceover'" class="audio-preview">
          <el-icon :size="64"><Headset /></el-icon>
        </div>
      </div>
      <el-descriptions :column="2" border class="preview-info">
        <el-descriptions-item label="类型">{{ getTypeLabel(previewItem?.type) }}</el-descriptions-item>
        <el-descriptions-item label="大小">{{ previewItem?.size }}</el-descriptions-item>
        <el-descriptions-item label="时长">
          {{ (previewItem?.type === 'video' || previewItem?.type === 'bgm' || previewItem?.type === 'voiceover') && previewItem?.duration ? formatDuration(previewItem?.duration) : '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ previewItem?.createdAt }}</el-descriptions-item>
      </el-descriptions>
    </el-dialog>

    <!-- 移动到文件夹弹窗 -->
    <el-dialog v-model="moveDialogVisible" title="移动到文件夹" width="400px">
      <el-tree
        ref="moveFolderTreeRef"
        :data="folderTree"
        node-key="id"
        highlight-current
        default-expand-all
        :expand-on-click-node="false"
        @node-click="handleMoveFolderSelect"
      />
      <template #footer>
        <el-button @click="moveDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleMoveConfirm" :disabled="!moveTargetFolderId">确定</el-button>
      </template>
    </el-dialog>

    <!-- 文件夹右键菜单 -->
    <teleport to="body">
      <div
        v-if="contextMenuVisible"
        class="context-menu"
        :style="{ left: contextMenuLeft + 'px', top: contextMenuTop + 'px' }"
      >
        <div class="context-menu-item" @click="handleContextMenuRename">重命名</div>
        <div class="context-menu-item danger" @click="handleContextMenuDelete">删除</div>
      </div>
    </teleport>

    <!-- AI 文案生成弹窗 -->
    <el-dialog
      v-model="aiCopyVisible"
      :title="`AI 文案 - ${aiCopyTarget?.title || ''}`"
      width="560px"
      destroy-on-close
    >
      <p class="ai-copy-source">
        素材：<strong>{{ aiCopyTarget?.title }}</strong>
        <el-tag size="small" type="warning" effect="plain" style="margin-left:8px">
          {{ getTypeLabel(aiCopyTarget?.type) }}
        </el-tag>
      </p>
      <el-input
        v-model="aiCopyHint"
        type="textarea"
        :rows="3"
        placeholder="（可选）补充卖点/风格/受众，用于指导 AI 生成方向…"
        style="margin-bottom: 12px"
      />
      <div class="ai-copy-result">
        <CopywriterCompare
          v-if="candidates.length"
          :candidates="candidates"
          v-model="aiPickIdx"
          @apply="onAiApply"
        />
        <el-empty v-else description="点击下方按钮开始生成 5 套候选文案" :image-size="80" />
      </div>
      <template #footer>
        <el-button @click="aiCopyVisible = false">关闭</el-button>
        <el-button
          type="primary"
          :loading="aiGenerating"
          @click="runAiCopyGenerate"
        >
          {{ candidates.length ? '重新生成' : '生成 5 套候选' }}
        </el-button>
        <el-button v-if="candidates.length" type="success" @click="onAiConfirm">
          使用选中的
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Upload, Folder, Plus, Search, Check, VideoPlay,
  Picture, Headset, Document, FolderAdd, Delete
} from '@element-plus/icons-vue'
import {
  getMaterialList,
  softDeleteMaterial,
  moveMaterial,
  getMaterialTrash,
  restoreMaterial,
  permanentDeleteMaterial,
  getMaterialFolders,
  createMaterialFolder,
  renameMaterialFolder,
  deleteMaterialFolder,
  uploadMaterial
} from '@/api/materials'
import { normalizeListPayload, normalizePagination } from '@/utils/responseHelper'
import { generateContent } from '@/api/ai'
import CopywriterCompare from '@/components/CopywriterCompare.vue'

// AI 文案生成
const aiCopyVisible = ref(false)
const aiCopyTarget = ref(null)
const aiCopyHint = ref('')
const aiGenerating = ref(false)
const candidates = ref([])
const aiPickIdx = ref(-1)

const handleAiCopy = (item) => {
  aiCopyTarget.value = item
  aiCopyHint.value = ''
  candidates.value = []
  aiPickIdx.value = -1
  aiCopyVisible.value = true
}

const runAiCopyGenerate = async () => {
  if (!aiCopyTarget.value) return
  aiGenerating.value = true
  try {
    const res = await generateContent({
      taskType: aiCopyTarget.value.type === 'video' ? 'video_script' : 'notes',
      description: `${aiCopyTarget.value.title || ''}\n${aiCopyHint.value || ''}`.trim(),
      sourceMaterialId: aiCopyTarget.value.id
    })
    const variants = res?.variants || res?.candidates || res?.data?.variants
    if (Array.isArray(variants) && variants.length) {
      candidates.value = variants.map(v => (typeof v === 'string' ? { text: v, score: 0 } : v))
    } else {
      const raw = res?.content || res?.data?.content
      candidates.value = raw ? [1, 2, 3, 4, 5].map(i => ({ text: raw, score: 80 - i * 3 })) : []
    }
    aiPickIdx.value = candidates.value.length ? 0 : -1
    if (!candidates.value.length) ElMessage.warning('生成内容为空')
  } catch (err) {
    console.error(err)
    ElMessage.error('生成失败，请稍后重试')
  } finally {
    aiGenerating.value = false
  }
}

const onAiApply = (c) => {
  if (c?.text) {
    navigator.clipboard?.writeText(c.text).then(() => ElMessage.success('已复制到剪贴板'))
  }
}

const onAiConfirm = () => {
  const picked = aiPickIdx.value >= 0 ? candidates.value[aiPickIdx.value] : null
  if (!picked) {
    ElMessage.warning('请先在候选中选择一个方案')
    return
  }
  ElMessage.success(`已选用方案 ${aiPickIdx.value + 1}（可在 AI 文案工作台进一步编辑）`)
  aiCopyVisible.value = false
}

// ======================== 文件夹树 ========================
const folderTreeRef = ref(null)
const folderTree = ref([
  { id: 0, label: '全部素材', children: [] }
])
const currentFolderId = ref(0)
const editingFolderName = ref('')
const contextMenuVisible = ref(false)
const contextMenuLeft = ref(0)
const contextMenuTop = ref(0)
const contextMenuNode = ref(null)
const contextMenuData = ref(null)

// 加载文件夹列表
const loadFolders = async () => {
  try {
    const res = await getMaterialFolders()
    const list = Array.isArray(res) ? res : (res?.list || res?.data?.list || [])
    folderTree.value = [
      {
        id: 0,
        label: '全部素材',
        children: list.map(f => ({ id: f.id, label: f.name, ...f }))
      }
    ]
  } catch (e) {
    console.error('加载文件夹失败:', e)
    folderTree.value = [{ id: 0, label: '全部素材', children: [] }]
  }
}

// 点击文件夹
const handleFolderClick = (data) => {
  currentFolderId.value = data.id
  currentPage.value = 1
  selectedIds.value = []
  getList()
}

// 新增文件夹
const handleAddFolder = async () => {
  try {
    const { value } = await ElMessageBox.prompt('请输入文件夹名称', '新增文件夹', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      inputPattern: /\S+/,
      inputErrorMessage: '名称不能为空'
    })
    await createMaterialFolder({ name: value, parentId: currentFolderId.value || 0 })
    ElMessage.success('创建成功')
    loadFolders()
  } catch (e) {
    if (e !== 'cancel') {
      console.error('创建文件夹失败:', e)
      ElMessage.error('创建失败')
    }
  }
}

// 双击重命名
const handleFolderDblClick = (node, data) => {
  if (data.id === 0) return
  editingFolderName.value = node.label
  node.editing = true
}

// 确认重命名
const handleFolderRenameConfirm = async (node, data) => {
  if (!editingFolderName.value.trim()) {
    node.editing = false
    return
  }
  try {
    await renameMaterialFolder({ id: data.id, name: editingFolderName.value.trim() })
    node.label = editingFolderName.value.trim()
    ElMessage.success('重命名成功')
  } catch (e) {
    console.error('重命名失败:', e)
    ElMessage.error('重命名失败')
  }
  node.editing = false
}

// 右键菜单
const handleFolderRightClick = (event, data, node) => {
  if (data.id === 0) return
  event.preventDefault()
  contextMenuNode.value = node
  contextMenuData.value = data
  contextMenuLeft.value = event.clientX
  contextMenuTop.value = event.clientY
  contextMenuVisible.value = true
}

const closeContextMenu = () => {
  contextMenuVisible.value = false
}

const handleContextMenuRename = async () => {
  closeContextMenu()
  const node = contextMenuNode.value
  const data = contextMenuData.value
  try {
    const { value } = await ElMessageBox.prompt('请输入新名称', '重命名文件夹', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      inputValue: node.label,
      inputPattern: /\S+/,
      inputErrorMessage: '名称不能为空'
    })
    await renameMaterialFolder({ id: data.id, name: value })
    node.label = value
    ElMessage.success('重命名成功')
  } catch (e) {
    if (e !== 'cancel') {
      console.error('重命名失败:', e)
      ElMessage.error('重命名失败')
    }
  }
}

const handleContextMenuDelete = async () => {
  closeContextMenu()
  const data = contextMenuData.value
  try {
    await ElMessageBox.confirm(`确定删除文件夹"${data.label}"吗？`, '确认删除', {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    })
    await deleteMaterialFolder({ id: data.id })
    ElMessage.success('删除成功')
    loadFolders()
    if (currentFolderId.value === data.id) {
      currentFolderId.value = 0
      getList()
    }
  } catch (e) {
    if (e !== 'cancel') {
      console.error('删除文件夹失败:', e)
      ElMessage.error('删除失败')
    }
  }
}

// ======================== 素材列表 ========================
const activeTab = ref('all')
const searchName = ref('')
const filterStatus = ref('')
const onlyAiVideo = ref(false)
const loading = ref(false)
const materials = ref([])
const selectedIds = ref([])
const currentPage = ref(1)
const pageSize = ref(12)
const total = ref(0)

const isAllSelected = computed({
  get: () => materials.value.length > 0 && materials.value.every(m => selectedIds.value.includes(m.id)),
  set: () => {}
})

// 根据当前Tab动态生成上传按钮文本
const uploadTypeLabel = computed(() => {
  const labels = {
    all: '素材', video: '视频', image: '图片',
    bgm: '音频', voiceover: '配音', guide: '指南'
  }
  return labels[activeTab.value] || '素材'
})

const uploadHintText = computed(() => {
  const hints = {
    all: '支持常见媒体文件格式',
    video: '支持 .mov、.mp4、.3gp，单文件最大300M',
    image: '支持 .jpg、.png、.gif、.webp，单文件最大50M',
    bgm: '支持 .mp3、.wav、.aac，单文件最大50M',
    voiceover: '支持 .mp3、.wav、.aac，单文件最大50M',
    guide: '支持 .pdf、.doc、.docx、.txt'
  }
  return hints[activeTab.value] || ''
})

const getList = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      pageSize: pageSize.value,
    }
    if (activeTab.value !== 'all') {
      params.type = activeTab.value
    }
    if (currentFolderId.value > 0) {
      params.folderId = currentFolderId.value
    }
    if (searchName.value) {
      params.name = searchName.value
    }
    if (filterStatus.value) {
      params.status = filterStatus.value
    }
    if (onlyAiVideo.value) {
      params.aiVideo = 1
    }
    const res = await getMaterialList(params)
    const { list, total: t } = normalizePagination(res)
    materials.value = list
    total.value = t
  } catch (e) {
    console.error('获取素材列表失败:', e)
    materials.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

const handleTabChange = () => {
  currentPage.value = 1
  selectedIds.value = []
  if (activeTab.value === 'trash') {
    loadTrashList()
  } else {
    getList()
  }
}

const handleSearch = () => {
  currentPage.value = 1
  getList()
}

const handleSelectItem = (item) => {
  const idx = selectedIds.value.indexOf(item.id)
  if (idx > -1) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(item.id)
  }
}

const handleToggleAll = (val) => {
  if (val) {
    selectedIds.value = materials.value.map(m => m.id)
  } else {
    selectedIds.value = []
  }
}

const handlePageChange = (page) => {
  currentPage.value = page
  getList()
}

const handleSizeChange = (size) => {
  pageSize.value = size
  currentPage.value = 1
  getList()
}

// ======================== 预览 ========================
const previewVisible = ref(false)
const previewItem = ref(null)

const handlePreview = (item) => {
  previewItem.value = item
  previewVisible.value = true
}

// ======================== 上传 ========================
const uploadVisible = ref(false)
const uploadRef = ref(null)
const fileList = ref([])

const handleUpload = () => {
  fileList.value = []
  uploadVisible.value = true
}

const handleFileChange = (file, files) => {
  fileList.value = files
}

const handleFileRemove = (file, files) => {
  fileList.value = files
}

const handleSubmitUpload = async () => {
  if (fileList.value.length === 0) {
    ElMessage.warning('请选择要上传的文件')
    return
  }
  try {
    const formData = new FormData()
    fileList.value.forEach(file => {
      formData.append('files', file.raw)
    })
    if (currentFolderId.value > 0) {
      formData.append('folderId', currentFolderId.value)
    }
    await uploadMaterial(formData)
    ElMessage.success('上传成功')
    uploadVisible.value = false
    getList()
  } catch (e) {
    console.error('上传失败:', e)
    ElMessage.error(e.message || '上传失败')
  }
}

// ======================== 软删除 ========================
const handleSoftDelete = async () => {
  try {
    await ElMessageBox.confirm(
      `确定删除选中的 ${selectedIds.value.length} 个素材吗？素材将移入回收站。`,
      '确认删除',
      { type: 'warning', confirmButtonText: '确定', cancelButtonText: '取消' }
    )
    await softDeleteMaterial({ ids: selectedIds.value })
    ElMessage.success('删除成功')
    selectedIds.value = []
    getList()
  } catch (e) {
    if (e !== 'cancel') {
      console.error('删除失败:', e)
      ElMessage.error('删除失败')
    }
  }
}

// ======================== 移动到文件夹 ========================
const moveDialogVisible = ref(false)
const moveFolderTreeRef = ref(null)
const moveTargetFolderId = ref(null)

const handleMoveFolderSelect = (data) => {
  moveTargetFolderId.value = data.id
}

const handleMoveConfirm = async () => {
  if (!moveTargetFolderId.value && moveTargetFolderId.value !== 0) return
  try {
    await moveMaterial({ ids: selectedIds.value, folderId: moveTargetFolderId.value })
    ElMessage.success('移动成功')
    moveDialogVisible.value = false
    selectedIds.value = []
    getList()
  } catch (e) {
    console.error('移动失败:', e)
    ElMessage.error('移动失败')
  }
}

// ======================== 回收站 ========================
const trashList = ref([])
const trashLoading = ref(false)
const trashSearch = ref('')
const selectedTrashIds = ref([])

const loadTrashList = async () => {
  trashLoading.value = true
  try {
    const params = {}
    if (trashSearch.value) params.name = trashSearch.value
    const res = await getMaterialTrash(params)
    trashList.value = normalizeListPayload(res)
  } catch (e) {
    console.error('获取回收站列表失败:', e)
    trashList.value = []
  } finally {
    trashLoading.value = false
  }
}

const handleTrashSelectionChange = (selection) => {
  selectedTrashIds.value = selection.map(item => item.id)
}

const handleRestore = async (row) => {
  try {
    await restoreMaterial({ ids: [row.id] })
    ElMessage.success('恢复成功')
    loadTrashList()
  } catch (e) {
    console.error('恢复失败:', e)
    ElMessage.error('恢复失败')
  }
}

const handlePermanentDelete = async (row) => {
  try {
    await ElMessageBox.confirm('彻底删除后无法恢复，确定继续吗？', '确认彻底删除', {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    })
    await permanentDeleteMaterial({ ids: [row.id] })
    ElMessage.success('删除成功')
    loadTrashList()
  } catch (e) {
    if (e !== 'cancel') {
      console.error('彻底删除失败:', e)
      ElMessage.error('删除失败')
    }
  }
}

const handleBatchRestore = async () => {
  try {
    await restoreMaterial({ ids: selectedTrashIds.value })
    ElMessage.success('恢复成功')
    selectedTrashIds.value = []
    loadTrashList()
  } catch (e) {
    console.error('批量恢复失败:', e)
    ElMessage.error('恢复失败')
  }
}

const handleBatchPermanentDelete = async () => {
  try {
    await ElMessageBox.confirm(`彻底删除 ${selectedTrashIds.value.length} 个素材后无法恢复，确定继续吗？`, '确认彻底删除', {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消'
    })
    await permanentDeleteMaterial({ ids: selectedTrashIds.value })
    ElMessage.success('删除成功')
    selectedTrashIds.value = []
    loadTrashList()
  } catch (e) {
    if (e !== 'cancel') {
      console.error('批量彻底删除失败:', e)
      ElMessage.error('删除失败')
    }
  }
}

// ======================== 工具函数 ========================
const getTypeLabel = (type) => {
  const labels = { all: '全部', image: '图片', video: '视频', bgm: '背景音频', voiceover: '旁白配音', guide: '拍摄指南' }
  return labels[type] || type || '-'
}

const getTypeTagType = (type) => {
  const types = { image: 'info', video: 'warning', bgm: 'success', voiceover: '', guide: 'danger' }
  return types[type] || 'info'
}

const formatDuration = (seconds) => {
  if (!seconds) return '-'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return m > 0 ? `${m}分${s}秒` : `${s}秒`
}

// ======================== 全局点击关闭右键菜单 ========================
const handleGlobalClick = () => {
  if (contextMenuVisible.value) closeContextMenu()
}

// ======================== 初始化 ========================
onMounted(() => {
  loadFolders()
  getList()
  document.addEventListener('click', handleGlobalClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleGlobalClick)
})
</script>

<style lang="scss" scoped>
.materials-page {
  padding: 20px;
  height: 100%;
  box-sizing: border-box;
}

.materials-layout {
  display: flex;
  gap: 20px;
  height: 100%;
}

// 左侧文件夹树
.folder-sidebar {
  width: 200px;
  min-width: 200px;
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);

  .sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;

    .sidebar-title {
      font-size: 15px;
      font-weight: 600;
      color: #303133;
    }
  }

  .folder-node {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    font-size: 14px;

    .el-icon {
      color: #e6a23c;
    }

    .folder-name {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  }
}

// 右侧内容区
.material-content {
  flex: 1;
  min-width: 0;
  background: #fff;
  border-radius: 8px;
  padding: 16px 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);

  :deep(.el-tabs__header) {
    margin-bottom: 16px;
  }
}

// 筛选区域
.filter-section {
  margin-bottom: 16px;

  .filter-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .filter-actions {
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 12px;

    .upload-hint {
      font-size: 12px;
      color: #909399;
    }
  }
}

// 批量操作栏
.batch-bar {
  background: rgba(255, 107, 53, 0.06);
  border: 1px solid rgba(255, 107, 53, 0.2);
  padding: 10px 16px;
  border-radius: 12px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 12px;

  .batch-count {
    color: #FF6B35;
    font-size: 14px;
  }
}

// 素材卡片网格
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
  min-height: 200px;

  .material-card {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #ebeef5;
    transition: all 0.3s;

    &:hover {
      border-color: #c0c4cc;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    &.selected {
      border-color: #409eff;
    }

    .card-cover {
      position: relative;
      aspect-ratio: 16 / 10;
      overflow: hidden;
      background: #f5f7fa;
      cursor: pointer;

      img, video {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .cover-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: #c0c4cc;

        .el-icon {
          font-size: 36px;
        }

        span {
          font-size: 12px;
          color: #909399;
        }
      }

      .video-placeholder {
        background: linear-gradient(135deg, #2c3e50, #3498db);
        color: rgba(255, 255, 255, 0.6);
      }

      .image-placeholder {
        background: linear-gradient(135deg, #f5f7fa, #e4e7ed);
      }

      .audio-placeholder {
        background: linear-gradient(135deg, #1a1a2e, #16213e);
        color: rgba(255, 255, 255, 0.6);

        span {
          color: rgba(255, 255, 255, 0.5);
        }
      }

      .guide-placeholder {
        background: linear-gradient(135deg, #fff3e0, #ffe0b2);
        color: #e6a23c;
      }

      .video-duration {
        position: absolute;
        bottom: 6px;
        right: 6px;
        background: rgba(0, 0, 0, 0.65);
        color: #fff;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
      }

      .video-play-icon {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s;

        .el-icon {
          font-size: 20px;
          color: #fff;
        }
      }

      &:hover .video-play-icon {
        opacity: 1;
      }

      .select-indicator {
        position: absolute;
        top: 8px;
        left: 8px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.25);
        border: 2px solid #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0;
        transition: all 0.3s;

        .el-icon {
          font-size: 12px;
          color: #fff;
        }

        &.active {
          background: #409eff;
          opacity: 1;
        }
      }

      &:hover .select-indicator {
        opacity: 1;
      }
    }

    .card-info {
      padding: 10px 12px;

      .card-title {
        margin: 0 0 6px;
        font-size: 14px;
        font-weight: 500;
        color: #303133;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .card-meta {
        display: flex;
        gap: 8px;
        font-size: 12px;
        color: #909399;
        flex-wrap: wrap;
      }
    }
  }

  .empty-state {
    grid-column: 1 / -1;
  }
}

// 表格包装
.table-wrapper {
  margin-top: 12px;
}

// 分页
.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: center;
}

// 预览弹窗
.preview-content {
  background: #000;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: center;

  img, video {
    width: 100%;
    display: block;
    max-height: 400px;
    object-fit: contain;
  }

  .audio-preview {
    padding: 40px;
    color: #fff;
  }
}

// 上传弹窗
.upload-area {
  :deep(.el-upload) {
    width: 100%;
  }

  :deep(.el-upload-dragger) {
    width: 100%;
    padding: 40px 20px;
  }

  .upload-icon {
    font-size: 48px;
    color: #c0c4cc;
    margin-bottom: 16px;
  }

  .upload-text {
    font-size: 14px;
    color: #606266;

    em {
      color: #409eff;
      font-style: normal;
    }
  }

  .upload-tip {
    font-size: 12px;
    color: #909399;
    margin-top: 8px;
  }
}

// 右键菜单
.context-menu {
  position: fixed;
  z-index: 3000;
  background: #fff;
  border-radius: 6px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  padding: 4px 0;
  min-width: 120px;

  .context-menu-item {
    padding: 8px 16px;
    font-size: 14px;
    color: #606266;
    cursor: pointer;
    transition: background 0.2s;

    &:hover {
      background: #f5f7fa;
    }

    &.danger {
      color: #f56c6c;

      &:hover {
        background: #fef0f0;
      }
    }
  }
}

.card-quick {
  display: flex;
  gap: 6px;
  margin-top: 10px;
  flex-wrap: wrap;
}

.ai-copy-source {
  margin: 0 0 12px;
  font-size: 13px;
  color: #606266;
}

.ai-copy-result {
  margin-top: 8px;
}
</style>
