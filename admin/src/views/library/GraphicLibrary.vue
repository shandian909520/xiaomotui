<template>
  <div class="graphic-library">
    <PageHeader title="图文库" subtitle="管理图文、图片、文案内容库" />

    <el-tabs v-model="activeTab" type="border-card">
      <!-- 图文库标签页 -->
      <el-tab-pane label="图文库" name="graphic">
        <div class="tab-toolbar">
          <el-input v-model="graphicSearch" placeholder="搜索图文库名称" clearable style="width: 250px" @keyup.enter="loadGraphicList">
            <template #append>
              <el-button @click="loadGraphicList">搜索</el-button>
            </template>
          </el-input>
          <el-button type="primary" @click="handleAddGraphic">
            <el-icon><Plus /></el-icon>
            新增图文库
          </el-button>
        </div>

        <el-table :data="graphicList" v-loading="graphicLoading" stripe>
          <el-table-column prop="name" label="库名称" min-width="160" />
          <el-table-column label="内容数量" width="100" align="center">
            <template #default="{ row }">{{ row.contentCount || row.total_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="已使用" width="80" align="center">
            <template #default="{ row }">{{ row.usedCount || row.used_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="剩余" width="80" align="center">
            <template #default="{ row }">{{ row.remainCount || row.remaining_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="最多使用次数" width="120" align="center">
            <template #default="{ row }">{{ row.maxCount || row.max_use_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="操作" width="260" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="primary" @click="handleGraphicDetail(row)">查看详情</el-button>
              <el-button size="small" @click="handleAddGraphicContent(row)">添加内容</el-button>
              <el-button size="small" @click="handleEditGraphic(row)">编辑</el-button>
              <el-button size="small" type="danger" @click="handleDeleteGraphic(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>

        <el-pagination
          v-model:current-page="graphicPagination.page"
          v-model:page-size="graphicPagination.limit"
          :total="graphicPagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadGraphicList"
          @current-change="loadGraphicList"
        />
      </el-tab-pane>

      <!-- 图片库标签页 -->
      <el-tab-pane label="图片库" name="image">
        <div class="tab-toolbar">
          <el-input v-model="imageSearch" placeholder="搜索图片库名称" clearable style="width: 250px" @keyup.enter="loadImageList">
            <template #append>
              <el-button @click="loadImageList">搜索</el-button>
            </template>
          </el-input>
          <el-button type="primary" @click="handleAddImage">
            <el-icon><Plus /></el-icon>
            新增图片库
          </el-button>
        </div>

        <el-table :data="imageList" v-loading="imageLoading" stripe>
          <el-table-column prop="name" label="库名称" min-width="160" />
          <el-table-column label="图片数量" width="100" align="center">
            <template #default="{ row }">{{ row.imageCount || row.total_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="最多使用次数" width="120" align="center">
            <template #default="{ row }">{{ row.maxCount || row.max_use_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="已使用" width="80" align="center">
            <template #default="{ row }">{{ row.usedCount || row.used_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="剩余" width="80" align="center">
            <template #default="{ row }">{{ row.remainCount || row.remaining_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="使用比例" width="180">
            <template #default="{ row }">
              <el-progress
                :percentage="getImageUsedPercentage(row)"
                :color="getProgressColor(row)"
                :stroke-width="14"
                :text-inside="true"
              />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="220" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="primary" @click="handleImageDetail(row)">查看详情</el-button>
              <el-button size="small" @click="handleAddImageToLib(row)">添加图片</el-button>
              <el-button size="small" @click="handleEditImageLib(row)">编辑</el-button>
              <el-button size="small" type="danger" @click="handleDeleteImageLib(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>

        <el-pagination
          v-model:current-page="imagePagination.page"
          v-model:page-size="imagePagination.limit"
          :total="imagePagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadImageList"
          @current-change="loadImageList"
        />
      </el-tab-pane>

      <!-- 文案库标签页 -->
      <el-tab-pane label="文案库" name="text">
        <div class="tab-toolbar">
          <el-input v-model="textSearch" placeholder="搜索文案库名称" clearable style="width: 250px" @keyup.enter="loadTextList">
            <template #append>
              <el-button @click="loadTextList">搜索</el-button>
            </template>
          </el-input>
          <el-button type="primary" @click="handleAddText">
            <el-icon><Plus /></el-icon>
            新增文案库
          </el-button>
        </div>

        <el-table :data="textList" v-loading="textLoading" stripe>
          <el-table-column prop="name" label="库名称" min-width="160" />
          <el-table-column label="文案条数" width="100" align="center">
            <template #default="{ row }">{{ row.textCount || row.total_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="最多使用次数" width="120" align="center">
            <template #default="{ row }">{{ row.maxCount || row.max_use_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="已使用" width="80" align="center">
            <template #default="{ row }">{{ row.usedCount || row.used_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="剩余" width="80" align="center">
            <template #default="{ row }">{{ row.remainCount || row.remaining_count || 0 }}</template>
          </el-table-column>
          <el-table-column label="操作" width="220" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="primary" @click="handleTextDetail(row)">查看详情</el-button>
              <el-button size="small" @click="handleAddTextToLib(row)">添加文案</el-button>
              <el-button size="small" @click="handleEditTextLib(row)">编辑</el-button>
              <el-button size="small" type="danger" @click="handleDeleteTextLib(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>

        <el-pagination
          v-model:current-page="textPagination.page"
          v-model:page-size="textPagination.limit"
          :total="textPagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadTextList"
          @current-change="loadTextList"
        />
      </el-tab-pane>
    </el-tabs>

    <!-- 图文库 - 新增/编辑弹窗 -->
    <el-dialog v-model="graphicDialogVisible" :title="graphicDialogTitle" width="500px" @close="graphicFormRef?.resetFields()">
      <el-form :model="graphicForm" :rules="graphicFormRules" ref="graphicFormRef" label-width="100px">
        <el-form-item label="库名称" prop="name">
          <el-input v-model="graphicForm.name" placeholder="请输入库名称" />
        </el-form-item>
        <el-form-item label="最多使用次数" prop="maxCount">
          <el-input-number v-model="graphicForm.maxCount" :min="1" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="graphicDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitGraphicForm" :loading="graphicSubmitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- 图文库 - 添加内容弹窗 -->
    <el-dialog v-model="addContentVisible" title="添加图文内容" width="600px">
      <div class="content-pair-list">
        <div v-for="(pair, index) in contentPairs" :key="index" class="content-pair">
          <el-upload
            :auto-upload="false"
            accept="image/*"
            :limit="1"
            :on-change="(file) => pair.imageFile = file.raw"
            class="pair-upload"
          >
            <el-button size="small">选择图片</el-button>
            <span v-if="pair.imageFile" class="file-name">{{ pair.imageFile.name }}</span>
          </el-upload>
          <el-input v-model="pair.text" type="textarea" :rows="2" placeholder="输入文案" class="pair-text" />
          <el-button size="small" type="danger" text @click="contentPairs.splice(index, 1)">删除</el-button>
        </div>
      </div>
      <el-button type="primary" text @click="contentPairs.push({ imageFile: null, text: '' })">+ 添加一组</el-button>
      <template #footer>
        <el-button @click="addContentVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAddContent" :loading="addContentSaving">确定</el-button>
      </template>
    </el-dialog>

    <!-- 图文库 - 详情弹窗 -->
    <el-dialog v-model="graphicDetailVisible" :title="`${currentGraphic.name} - 详情`" width="800px" top="5vh">
      <div class="detail-stats-bar">
        <span>内容数量: {{ currentGraphic.contentCount }}</span>
        <span>已使用: {{ currentGraphic.usedCount }}</span>
      </div>
      <el-table :data="graphicDetailList" v-loading="graphicDetailLoading" stripe>
        <el-table-column label="图片" width="80">
          <template #default="{ row }">
            <el-avatar v-if="row.cover" :src="row.cover" :size="40" shape="square" />
            <el-avatar v-else :size="40" shape="square">图</el-avatar>
          </template>
        </el-table-column>
        <el-table-column prop="text" label="文案" min-width="200" show-overflow-tooltip />
        <el-table-column prop="useCount" label="使用次数" width="100" align="center" />
        <el-table-column label="操作" width="80">
          <template #default="{ row }">
            <el-button size="small" type="danger" text @click="handleDeleteContentItem(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <!-- 图片库 - 新增/编辑弹窗 -->
    <el-dialog v-model="imageDialogVisible" :title="imageDialogTitle" width="500px" @close="imageFormRef?.resetFields()">
      <el-form :model="imageForm" :rules="imageFormRules" ref="imageFormRef" label-width="100px">
        <el-form-item label="库名称" prop="name">
          <el-input v-model="imageForm.name" placeholder="请输入库名称" />
        </el-form-item>
        <el-form-item label="最多使用次数" prop="maxCount">
          <el-input-number v-model="imageForm.maxCount" :min="1" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="imageDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitImageForm" :loading="imageSubmitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- 图片库 - 详情弹窗 -->
    <el-dialog v-model="imageDetailVisible" :title="`${currentImageLib.name} - 图片详情`" width="800px" top="5vh">
      <div class="image-detail-grid" v-loading="imageDetailLoading">
        <div v-for="img in imageDetailList" :key="img.id" class="image-detail-card">
          <div class="image-detail-cover">
            <img v-if="img.url" :src="img.url" alt="" />
            <div v-else class="img-placeholder">图片</div>
          </div>
          <div class="image-detail-info">
            <span>使用: {{ img.useCount || 0 }}</span>
            <el-button size="small" type="danger" text @click="handleDeleteImageItem(img)">删除</el-button>
          </div>
        </div>
      </div>
      <EmptyPanel v-if="imageDetailList.length === 0 && !imageDetailLoading" message="暂无图片" />
    </el-dialog>

    <!-- 图片库 - 添加图片弹窗 -->
    <el-dialog v-model="addImageVisible" title="添加图片" width="500px">
      <el-upload
        :auto-upload="false"
        accept="image/*"
        multiple
        :on-change="handleImageFileChange"
        :file-list="addImageFiles"
      >
        <el-button type="primary">选择图片</el-button>
      </el-upload>
      <template #footer>
        <el-button @click="addImageVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAddImage" :loading="addImageSaving">确定</el-button>
      </template>
    </el-dialog>

    <!-- 文案库 - 新增/编辑弹窗 -->
    <el-dialog v-model="textDialogVisible" :title="textDialogTitle" width="500px" @close="textFormRef?.resetFields()">
      <el-form :model="textForm" :rules="textFormRules" ref="textFormRef" label-width="100px">
        <el-form-item label="库名称" prop="name">
          <el-input v-model="textForm.name" placeholder="请输入库名称" />
        </el-form-item>
        <el-form-item label="最多使用次数" prop="maxCount">
          <el-input-number v-model="textForm.maxCount" :min="1" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="textDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitTextForm" :loading="textSubmitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- 文案库 - 详情弹窗 -->
    <el-dialog v-model="textDetailVisible" :title="`${currentTextLib.name} - 文案详情`" width="700px" top="5vh">
      <div class="text-detail-list" v-loading="textDetailLoading">
        <div v-for="(item, index) in textDetailList" :key="item.id" class="text-detail-item">
          <span class="text-index">{{ index + 1 }}.</span>
          <span class="text-content">{{ item.content }}</span>
          <el-button size="small" type="danger" text @click="handleDeleteTextItem(item)">删除</el-button>
        </div>
      </div>
      <EmptyPanel v-if="textDetailList.length === 0 && !textDetailLoading" message="暂无文案" />
    </el-dialog>

    <!-- 文案库 - 添加文案弹窗 -->
    <el-dialog v-model="addTextVisible" title="添加文案" width="500px">
      <el-input v-model="addTextContent" type="textarea" :rows="4" placeholder="请输入文案内容，每行一条" />
      <template #footer>
        <el-button @click="addTextVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAddText" :loading="addTextSaving">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { PageHeader, EmptyPanel } from '@/components/xmt'
import {
  getGraphicLibraryList, createGraphicLibrary, getGraphicLibraryDetail,
  addGraphicContent, updateGraphicLibrary, deleteGraphicLibrary,
  getImageLibraryList, createImageLibrary, getImageLibraryDetail,
  addImage, updateImageLibrary, deleteImageLibrary,
  getTextLibraryList, createTextLibrary, getTextLibraryDetail,
  addText, updateTextLibrary, deleteTextLibrary,
  deleteLibraryItem
} from '@/api/index'
import { normalizePagination, normalizeListPayload } from '@/utils/responseHelper'

const activeTab = ref('graphic')

// ========== 图文库 ==========
const graphicSearch = ref('')
const graphicLoading = ref(false)
const graphicList = ref([])
const graphicPagination = reactive({ page: 1, limit: 10, total: 0 })
const graphicDialogVisible = ref(false)
const graphicDialogTitle = ref('新增图文库')
const isEditGraphic = ref(false)
const graphicFormRef = ref(null)
const graphicSubmitting = ref(false)
const graphicForm = reactive({ id: null, name: '', maxCount: 100 })
const graphicFormRules = {
  name: [{ required: true, message: '请输入库名称', trigger: 'blur' }],
  maxCount: [{ required: true, message: '请输入最多使用次数', trigger: 'blur' }]
}
const addContentVisible = ref(false)
const addContentSaving = ref(false)
const contentPairs = reactive([{ imageFile: null, text: '' }])
const currentGraphicId = ref(null)
const graphicDetailVisible = ref(false)
const graphicDetailLoading = ref(false)
const graphicDetailList = ref([])
const currentGraphic = reactive({ name: '', contentCount: 0, usedCount: 0 })

const loadGraphicList = async () => {
  graphicLoading.value = true
  try {
    const params = { page: graphicPagination.page, limit: graphicPagination.limit }
    if (graphicSearch.value) params.name = graphicSearch.value
    const res = await getGraphicLibraryList(params)
    const { list, total } = normalizePagination(res)
    graphicList.value = list
    graphicPagination.total = total
  } catch (err) {
    console.error('获取图文库失败:', err)
    graphicList.value = []
    graphicPagination.total = 0
    ElMessage.error('获取图文库失败，请稍后重试')
  } finally {
    graphicLoading.value = false
  }
}

const handleAddGraphic = () => {
  graphicDialogTitle.value = '新增图文库'
  isEditGraphic.value = false
  Object.assign(graphicForm, { id: null, name: '', maxCount: 100 })
  graphicDialogVisible.value = true
}

const handleEditGraphic = (row) => {
  graphicDialogTitle.value = '编辑图文库'
  isEditGraphic.value = true
  Object.assign(graphicForm, {
    id: row.id,
    name: row.name,
    maxCount: row.maxCount || row.max_use_count || 100
  })
  graphicDialogVisible.value = true
}

const submitGraphicForm = async () => {
  if (!graphicFormRef.value) return
  await graphicFormRef.value.validate()
  graphicSubmitting.value = true
  try {
    const data = { name: graphicForm.name, maxCount: graphicForm.maxCount }
    if (isEditGraphic.value) {
      await updateGraphicLibrary(graphicForm.id, data)
      ElMessage.success('更新成功')
    } else {
      await createGraphicLibrary(data)
      ElMessage.success('新增成功')
    }
    graphicDialogVisible.value = false
    loadGraphicList()
  } catch (err) {
    ElMessage.error(err.message || '操作失败')
  } finally {
    graphicSubmitting.value = false
  }
}

const handleDeleteGraphic = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除 "${row.name}" 吗？`, '提示', { type: 'warning' })
    await deleteGraphicLibrary(row.id)
    ElMessage.success('删除成功')
    loadGraphicList()
  } catch (err) {
    if (err !== 'cancel') ElMessage.error(err.message || '删除失败')
  }
}

const handleGraphicDetail = async (row) => {
  Object.assign(currentGraphic, {
    name: row.name,
    contentCount: row.contentCount || row.total_count || 0,
    usedCount: row.usedCount || row.used_count || 0
  })
  graphicDetailVisible.value = true
  graphicDetailLoading.value = true
  try {
    const res = await getGraphicLibraryDetail(row.id)
    graphicDetailList.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取详情失败:', err)
    graphicDetailList.value = []
  } finally {
    graphicDetailLoading.value = false
  }
}

const handleAddGraphicContent = (row) => {
  currentGraphicId.value = row.id
  contentPairs.splice(0, contentPairs.length, { imageFile: null, text: '' })
  addContentVisible.value = true
}

const submitAddContent = async () => {
  addContentSaving.value = true
  try {
    await addGraphicContent(currentGraphicId.value, { items: contentPairs })
    ElMessage.success('添加成功')
    addContentVisible.value = false
    loadGraphicList()
  } catch (err) {
    ElMessage.error(err.message || '添加失败')
  } finally {
    addContentSaving.value = false
  }
}

const handleDeleteContentItem = async (item) => {
  try {
    await ElMessageBox.confirm('确定删除该内容吗？', '提示', { type: 'warning' })
    await deleteLibraryItem(item.id)
    ElMessage.success('删除成功')
  } catch (err) {
    if (err !== 'cancel') ElMessage.error(err.message || '删除失败')
  }
}

// ========== 图片库 ==========
const imageSearch = ref('')
const imageLoading = ref(false)
const imageList = ref([])
const imagePagination = reactive({ page: 1, limit: 10, total: 0 })
const imageDialogVisible = ref(false)
const imageDialogTitle = ref('新增图片库')
const isEditImage = ref(false)
const imageFormRef = ref(null)
const imageSubmitting = ref(false)
const imageForm = reactive({ id: null, name: '', maxCount: 100 })
const imageFormRules = {
  name: [{ required: true, message: '请输入库名称', trigger: 'blur' }],
  maxCount: [{ required: true, message: '请输入最多使用次数', trigger: 'blur' }]
}
const imageDetailVisible = ref(false)
const imageDetailLoading = ref(false)
const imageDetailList = ref([])
const currentImageLib = reactive({ name: '' })
const addImageVisible = ref(false)
const addImageSaving = ref(false)
const addImageFiles = ref([])
const currentImageLibId = ref(null)

const loadImageList = async () => {
  imageLoading.value = true
  try {
    const params = { page: imagePagination.page, limit: imagePagination.limit }
    if (imageSearch.value) params.name = imageSearch.value
    const res = await getImageLibraryList(params)
    const { list, total } = normalizePagination(res)
    imageList.value = list
    imagePagination.total = total
  } catch (err) {
    console.error('获取图片库失败:', err)
    imageList.value = []
    imagePagination.total = 0
    ElMessage.error('获取图片库失败，请稍后重试')
  } finally {
    imageLoading.value = false
  }
}

const handleAddImage = () => {
  imageDialogTitle.value = '新增图片库'
  isEditImage.value = false
  Object.assign(imageForm, { id: null, name: '', maxCount: 100 })
  imageDialogVisible.value = true
}

const handleEditImageLib = (row) => {
  imageDialogTitle.value = '编辑图片库'
  isEditImage.value = true
  Object.assign(imageForm, {
    id: row.id,
    name: row.name,
    maxCount: row.maxCount || row.max_use_count || 100
  })
  imageDialogVisible.value = true
}

const submitImageForm = async () => {
  if (!imageFormRef.value) return
  await imageFormRef.value.validate()
  imageSubmitting.value = true
  try {
    const data = { name: imageForm.name, maxCount: imageForm.maxCount }
    if (isEditImage.value) {
      await updateImageLibrary(imageForm.id, data)
      ElMessage.success('更新成功')
    } else {
      await createImageLibrary(data)
      ElMessage.success('新增成功')
    }
    imageDialogVisible.value = false
    loadImageList()
  } catch (err) {
    ElMessage.error(err.message || '操作失败')
  } finally {
    imageSubmitting.value = false
  }
}

const handleDeleteImageLib = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除 "${row.name}" 吗？`, '提示', { type: 'warning' })
    await deleteImageLibrary(row.id)
    ElMessage.success('删除成功')
    loadImageList()
  } catch (err) {
    if (err !== 'cancel') ElMessage.error(err.message || '删除失败')
  }
}

const handleImageDetail = async (row) => {
  currentImageLib.name = row.name
  imageDetailVisible.value = true
  imageDetailLoading.value = true
  try {
    const res = await getImageLibraryDetail(row.id)
    imageDetailList.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取图片详情失败:', err)
    imageDetailList.value = []
  } finally {
    imageDetailLoading.value = false
  }
}

const handleAddImageToLib = (row) => {
  currentImageLibId.value = row.id
  addImageFiles.value = []
  addImageVisible.value = true
}

const handleImageFileChange = (file) => {
  addImageFiles.value.push(file)
}

const submitAddImage = async () => {
  addImageSaving.value = true
  try {
    await addImage(currentImageLibId.value, { files: addImageFiles.value })
    ElMessage.success('添加成功')
    addImageVisible.value = false
    loadImageList()
  } catch (err) {
    ElMessage.error(err.message || '添加失败')
  } finally {
    addImageSaving.value = false
  }
}

const handleDeleteImageItem = async (item) => {
  try {
    await ElMessageBox.confirm('确定删除该图片吗？', '提示', { type: 'warning' })
    await deleteLibraryItem(item.id)
    ElMessage.success('删除成功')
  } catch (err) {
    if (err !== 'cancel') ElMessage.error(err.message || '删除失败')
  }
}

// ========== 文案库 ==========
const textSearch = ref('')
const textLoading = ref(false)
const textList = ref([])
const textPagination = reactive({ page: 1, limit: 10, total: 0 })
const textDialogVisible = ref(false)
const textDialogTitle = ref('新增文案库')
const isEditText = ref(false)
const textFormRef = ref(null)
const textSubmitting = ref(false)
const textForm = reactive({ id: null, name: '', maxCount: 100 })
const textFormRules = {
  name: [{ required: true, message: '请输入库名称', trigger: 'blur' }],
  maxCount: [{ required: true, message: '请输入最多使用次数', trigger: 'blur' }]
}
const textDetailVisible = ref(false)
const textDetailLoading = ref(false)
const textDetailList = ref([])
const currentTextLib = reactive({ name: '' })
const addTextVisible = ref(false)
const addTextContent = ref('')
const addTextSaving = ref(false)
const currentTextLibId = ref(null)

const loadTextList = async () => {
  textLoading.value = true
  try {
    const params = { page: textPagination.page, limit: textPagination.limit }
    if (textSearch.value) params.name = textSearch.value
    const res = await getTextLibraryList(params)
    const { list, total } = normalizePagination(res)
    textList.value = list
    textPagination.total = total
  } catch (err) {
    console.error('获取文案库失败:', err)
    textList.value = []
    textPagination.total = 0
    ElMessage.error('获取文案库失败，请稍后重试')
  } finally {
    textLoading.value = false
  }
}

const handleAddText = () => {
  textDialogTitle.value = '新增文案库'
  isEditText.value = false
  Object.assign(textForm, { id: null, name: '', maxCount: 100 })
  textDialogVisible.value = true
}

const handleEditTextLib = (row) => {
  textDialogTitle.value = '编辑文案库'
  isEditText.value = true
  Object.assign(textForm, {
    id: row.id,
    name: row.name,
    maxCount: row.maxCount || row.max_use_count || 100
  })
  textDialogVisible.value = true
}

const submitTextForm = async () => {
  if (!textFormRef.value) return
  await textFormRef.value.validate()
  textSubmitting.value = true
  try {
    const data = { name: textForm.name, maxCount: textForm.maxCount }
    if (isEditText.value) {
      await updateTextLibrary(textForm.id, data)
      ElMessage.success('更新成功')
    } else {
      await createTextLibrary(data)
      ElMessage.success('新增成功')
    }
    textDialogVisible.value = false
    loadTextList()
  } catch (err) {
    ElMessage.error(err.message || '操作失败')
  } finally {
    textSubmitting.value = false
  }
}

const handleDeleteTextLib = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除 "${row.name}" 吗？`, '提示', { type: 'warning' })
    await deleteTextLibrary(row.id)
    ElMessage.success('删除成功')
    loadTextList()
  } catch (err) {
    if (err !== 'cancel') ElMessage.error(err.message || '删除失败')
  }
}

const handleTextDetail = async (row) => {
  currentTextLib.name = row.name
  textDetailVisible.value = true
  textDetailLoading.value = true
  try {
    const res = await getTextLibraryDetail(row.id)
    textDetailList.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取文案详情失败:', err)
    textDetailList.value = []
  } finally {
    textDetailLoading.value = false
  }
}

const handleAddTextToLib = (row) => {
  currentTextLibId.value = row.id
  addTextContent.value = ''
  addTextVisible.value = true
}

const submitAddText = async () => {
  if (!addTextContent.value.trim()) {
    ElMessage.warning('请输入文案内容')
    return
  }
  addTextSaving.value = true
  try {
    await addText(currentTextLibId.value, { content: addTextContent.value })
    ElMessage.success('添加成功')
    addTextVisible.value = false
    loadTextList()
  } catch (err) {
    ElMessage.error(err.message || '添加失败')
  } finally {
    addTextSaving.value = false
  }
}

const handleDeleteTextItem = async (item) => {
  try {
    await ElMessageBox.confirm('确定删除该文案吗？', '提示', { type: 'warning' })
    await deleteLibraryItem(item.id)
    ElMessage.success('删除成功')
  } catch (err) {
    if (err !== 'cancel') ElMessage.error(err.message || '删除失败')
  }
}

// ========== 通用 ==========
const getImageUsedPercentage = (row) => {
  const maxCount = row.maxCount || row.max_use_count || 0
  const usedCount = row.usedCount || row.used_count || 0
  if (!maxCount) return 0
  return Math.min(Math.round((usedCount / maxCount) * 100), 100)
}

const getProgressColor = (row) => {
  const p = getImageUsedPercentage(row)
  if (p >= 90) return '#F56C6C'
  if (p >= 70) return '#E6A23C'
  return '#7b50ff'
}

onMounted(() => {
  loadGraphicList()
  loadImageList()
  loadTextList()
})
</script>

<style scoped lang="scss">
.graphic-library {
  padding: 20px;
}
.tab-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
:deep(.el-pagination) {
  margin-top: 16px;
  justify-content: flex-end;
}
.content-pair-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.content-pair {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  .pair-upload {
    flex-shrink: 0;
  }
  .pair-text {
    flex: 1;
  }
  .file-name {
    font-size: 12px;
    color: #909399;
    margin-left: 8px;
  }
}
.detail-stats-bar {
  display: flex;
  gap: 24px;
  font-size: 14px;
  color: #606266;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #eee;
}
.image-detail-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.image-detail-card {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  overflow: hidden;
}
.image-detail-cover {
  aspect-ratio: 1;
  background: #f5f7fa;
  img { width: 100%; height: 100%; object-fit: cover; }
  .img-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c0c4cc;
    font-size: 14px;
  }
}
.image-detail-info {
  padding: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #909399;
}
.text-detail-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.text-detail-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  background: #fafafa;
  border-radius: 6px;
  &:hover { background: #f0f0f0; }
  .text-index { color: #909399; font-size: 12px; flex-shrink: 0; }
  .text-content { flex: 1; font-size: 14px; color: #303133; line-height: 1.5; }
}
</style>
