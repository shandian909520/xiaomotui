<template>
  <div class="stores-container">
    <!-- 搜索和操作栏 -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="门店名称">
          <el-input v-model="searchForm.name" placeholder="请输入门店名称" clearable @keyup.enter="handleSearch" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 150px">
            <el-option label="启用" value="enabled" />
            <el-option label="禁用" value="disabled" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <div class="actions">
        <el-button type="primary" @click="handleAdd">
          <el-icon><Plus /></el-icon>
          新增门店
        </el-button>
        <el-button @click="handleBatchImport">
          <el-icon><Upload /></el-icon>
          批量导入
        </el-button>
        <el-tooltip content="功能即将上线，敬请期待" placement="top">
          <span>
            <el-button disabled>
              <el-icon><Bell /></el-icon>
              数据通知
            </el-button>
          </span>
        </el-tooltip>
      </div>
    </el-card>

    <!-- 门店列表表格 -->
    <el-card class="table-card">
      <el-table
        :data="storeList"
        v-loading="loading"
        stripe
      >
        <el-table-column prop="name" label="门店名称" min-width="150" />
        <el-table-column label="Logo" width="80">
          <template #default="{ row }">
            <el-avatar v-if="row.logo" :src="row.logo" :size="40" />
            <el-avatar v-else :size="40">
              <el-icon><Shop /></el-icon>
            </el-avatar>
          </template>
        </el-table-column>
        <el-table-column prop="address" label="地址" min-width="200" show-overflow-tooltip />
        <el-table-column prop="manager" label="负责人" width="100" />
        <el-table-column prop="phone" label="电话" width="130" />
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'enabled' ? 'success' : 'info'">
              {{ row.status === 'enabled' ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="NFC配置" width="100">
          <template #default="{ row }">
            <el-tag :type="row.nfcConfigured ? 'success' : 'warning'">
              {{ row.nfcConfigured ? '已配置' : '未配置' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="套餐权益" width="160">
          <template #default="{ row }">
            <div class="package-info">
              <span class="package-item">素材: {{ row.materialCount }}</span>
              <span class="package-item">视频: {{ row.videoCount }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="创建时间" width="120" />
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" @click="handleStoreDetail(row)">详情</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
      />
    </el-card>

    <!-- 新增/编辑门店对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form :model="storeForm" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="门店名称" prop="name">
          <el-input v-model="storeForm.name" placeholder="请输入门店名称" />
        </el-form-item>
        <el-form-item label="门店Logo">
          <el-input v-model="storeForm.logo" placeholder="请输入Logo URL" />
        </el-form-item>
        <el-form-item label="地址" prop="address">
          <el-input v-model="storeForm.address" placeholder="请输入门店地址" />
        </el-form-item>
        <el-form-item label="负责人" prop="manager">
          <el-input v-model="storeForm.manager" placeholder="请输入负责人姓名" />
        </el-form-item>
        <el-form-item label="电话" prop="phone">
          <el-input v-model="storeForm.phone" placeholder="请输入联系电话" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="storeForm.status">
            <el-radio label="enabled">启用</el-radio>
            <el-radio label="disabled">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="NFC配置">
          <el-switch v-model="storeForm.nfcConfigured" />
        </el-form-item>
        <el-form-item label="素材数量">
          <el-input-number v-model="storeForm.materialCount" :min="0" />
        </el-form-item>
        <el-form-item label="视频数量">
          <el-input-number v-model="storeForm.videoCount" :min="0" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- 门店详情弹窗（增强版） -->
    <el-dialog v-model="detailDialogVisible" :title="`${detailStore.name || ''} - 门店详情`" width="800px" top="5vh">
      <el-tabs v-model="detailTab" v-loading="detailLoading">
        <el-tab-pane label="基本信息" name="basic">
          <el-descriptions :column="2" border v-if="detailStore.id">
            <el-descriptions-item label="门店名称">{{ detailStore.name }}</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="detailStore.status === 'enabled' ? 'success' : 'info'">
                {{ detailStore.status === 'enabled' ? '启用' : '禁用' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="地址" :span="2">{{ detailStore.address }}</el-descriptions-item>
            <el-descriptions-item label="负责人">{{ detailStore.manager }}</el-descriptions-item>
            <el-descriptions-item label="电话">{{ detailStore.phone }}</el-descriptions-item>
            <el-descriptions-item label="素材数">{{ detailStore.materialCount }}</el-descriptions-item>
            <el-descriptions-item label="视频数">{{ detailStore.videoCount }}</el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>

        <el-tab-pane label="服务设施" name="facilities">
          <el-form label-width="100px">
            <el-form-item label="服务设施">
              <el-checkbox-group v-model="facilityForm.facilities">
                <el-checkbox label="wifi">WiFi</el-checkbox>
                <el-checkbox label="parking">停车场</el-checkbox>
                <el-checkbox label="private_room">包厢</el-checkbox>
                <el-checkbox label="outdoor">露天位</el-checkbox>
                <el-checkbox label="smoking">吸烟区</el-checkbox>
                <el-checkbox label="child_seat">儿童座椅</el-checkbox>
                <el-checkbox label="accessible">无障碍设施</el-checkbox>
                <el-checkbox label="charging">充电宝</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleSaveFacilities" :loading="detailSaving">保存</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="POI信息" name="poi">
          <el-form :model="poiForm" label-width="100px">
            <el-form-item label="POI ID">
              <el-input v-model="poiForm.poiId" placeholder="请输入POI ID" />
            </el-form-item>
            <el-form-item label="POI名称">
              <el-input v-model="poiForm.poiName" placeholder="请输入POI名称" />
            </el-form-item>
            <el-form-item label="平台">
              <el-checkbox-group v-model="poiForm.platforms">
                <el-checkbox label="douyin">抖音</el-checkbox>
                <el-checkbox label="kuaishou">快手</el-checkbox>
                <el-checkbox label="meituan">美团</el-checkbox>
                <el-checkbox label="dianping">大众点评</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleSavePoi" :loading="detailSaving">保存</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="装修配置" name="decoration">
          <el-form :model="decorationForm" label-width="100px">
            <el-form-item label="主题色">
              <el-color-picker v-model="decorationForm.themeColor" />
            </el-form-item>
            <el-form-item label="背景图片">
              <el-input v-model="decorationForm.backgroundImage" placeholder="背景图片URL" />
            </el-form-item>
            <el-form-item label="自定义样式">
              <el-input v-model="decorationForm.customCss" type="textarea" :rows="6" placeholder="自定义CSS样式（JSON格式）" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleSaveDecoration" :loading="detailSaving">保存</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="二维码" name="qrcode">
          <div class="qrcode-section">
            <div v-if="qrcodeUrl" class="qrcode-preview">
              <img :src="qrcodeUrl" alt="门店二维码" style="max-width: 200px" />
            </div>
            <el-empty v-else description="暂无二维码" :image-size="80" />
            <div class="qrcode-actions">
              <el-button type="primary" @click="handleGenerateQrCode" :loading="detailSaving">生成二维码</el-button>
              <el-button v-if="qrcodeUrl" @click="handleDownloadQrCode">下载二维码</el-button>
            </div>
          </div>
        </el-tab-pane>

        <el-tab-pane label="NFC配置" name="nfc">
          <el-form label-width="100px">
            <el-form-item label="NFC路径">
              <el-input v-model="nfcPath" readonly>
                <template #append>
                  <el-button @click="handleCopyNfcPath">复制</el-button>
                </template>
              </el-input>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleLoadNfcPath" :loading="detailSaving">获取NFC路径</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="桌贴状态" name="tableSticker">
          <el-form label-width="100px">
            <el-form-item label="桌贴功能">
              <el-switch
                v-model="tableStickerEnabled"
                active-text="启用"
                inactive-text="禁用"
                @change="handleToggleTableSticker"
              />
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>
    </el-dialog>

    <!-- 批量导入弹窗 -->
    <el-dialog v-model="importDialogVisible" title="批量导入" width="600px" @close="resetImportForm">
      <el-form :model="importForm" label-width="100px">
        <el-form-item label="导入类型">
          <el-radio-group v-model="importForm.type">
            <el-radio label="store">门店数据</el-radio>
            <el-radio label="poi">POI数据</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="上传文件">
          <el-upload
            ref="importUploadRef"
            :auto-upload="false"
            :limit="1"
            accept=".json,.csv,.xlsx"
            :on-change="handleImportFileChange"
          >
            <el-button>选择文件</el-button>
            <template #tip>
              <div class="upload-tip">支持 JSON / CSV / XLSX 格式</div>
            </template>
          </el-upload>
        </el-form-item>
        <el-form-item label="JSON数据">
          <el-input v-model="importForm.jsonData" type="textarea" :rows="6" placeholder="或直接粘贴JSON数据" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="importDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleImportSubmit" :loading="importSubmitting">导入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Shop, Upload, Bell } from '@element-plus/icons-vue'
import { getStores, getStoreDetail, createStore, updateStore, deleteStore } from '@/api/stores'
import {
  getStoreManageDetail,
  updateStoreManage,
  batchImportStores,
  batchImportPoi,
  getStoreQrCode,
  getStoreNfcPath,
  updateStoreDecoration,
  toggleTableSticker
} from '@/api/index'
import { normalizePagination } from '@/utils/responseHelper'


const searchForm = reactive({ name: '', status: '' })
const storeList = ref([])
const loading = ref(false)
const pagination = reactive({ page: 1, limit: 10, total: 0 })

// 编辑弹窗
const dialogVisible = ref(false)
const dialogTitle = ref('新增门店')
const isEdit = ref(false)
const formRef = ref(null)
const submitting = ref(false)
const storeForm = reactive({
  id: null, name: '', logo: '', address: '', manager: '', phone: '',
  status: 'enabled', nfcConfigured: false, materialCount: 0, videoCount: 0
})
const rules = {
  name: [{ required: true, message: '请输入门店名称', trigger: 'blur' }],
  address: [{ required: true, message: '请输入门店地址', trigger: 'blur' }],
  manager: [{ required: true, message: '请输入负责人', trigger: 'blur' }],
  phone: [{ required: true, message: '请输入联系电话', trigger: 'blur' }, { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号', trigger: 'blur' }]
}

// 详情弹窗
const detailDialogVisible = ref(false)
const detailLoading = ref(false)
const detailSaving = ref(false)
const detailTab = ref('basic')
const detailStore = ref({})
const facilityForm = reactive({ facilities: [] })
const poiForm = reactive({ poiId: '', poiName: '', platforms: [] })
const decorationForm = reactive({ themeColor: '#409eff', backgroundImage: '', customCss: '' })
const qrcodeUrl = ref('')
const nfcPath = ref('')
const tableStickerEnabled = ref(false)

// 导入弹窗
const importDialogVisible = ref(false)
const importSubmitting = ref(false)
const importForm = reactive({ type: 'store', jsonData: '', file: null })
const importUploadRef = ref(null)

const loadStores = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, page_size: pagination.limit }
    if (searchForm.name) params.keyword = searchForm.name
    if (searchForm.status) params.status = searchForm.status === 'enabled' ? 1 : 0
    const res = await getStores(params)
    const { list, total } = normalizePagination(res)
    // 映射后端字段到前端期望的字段名
    storeList.value = list.map(item => ({
      id: item.id,
      name: item.name || '',
      logo: item.logo || '',
      address: item.address || '',
      // 后端 Merchant 没有 manager 字段，使用 category 或空值
      manager: item.manager || item.category || '-',
      phone: item.phone || '',
      status: item.status === 1 ? 'enabled' : (item.status === 0 ? 'disabled' : 'enabled'),
      nfcConfigured: item.nfcConfigured || false,
      materialCount: item.materialCount || 0,
      videoCount: item.videoCount || 0,
      createdAt: item.createTime || item.createdAt || ''
    }))
    pagination.total = total
  } catch (error) {
    console.error('加载门店列表失败:', error)
    storeList.value = []
    pagination.total = 0
    ElMessage.error('加载门店列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => { pagination.page = 1; loadStores() }
const handleReset = () => { Object.assign(searchForm, { name: '', status: '' }); handleSearch() }

const handleAdd = () => {
  dialogTitle.value = '新增门店'
  isEdit.value = false
  Object.assign(storeForm, { id: null, name: '', logo: '', address: '', manager: '', phone: '', status: 'enabled', nfcConfigured: false, materialCount: 0, videoCount: 0 })
  dialogVisible.value = true
}

const handleEdit = (row) => {
  dialogTitle.value = '编辑门店'
  isEdit.value = true
  Object.assign(storeForm, {
    id: row.id,
    name: row.name || '',
    logo: row.logo || '',
    address: row.address || '',
    manager: row.manager || '',
    phone: row.phone || '',
    status: row.status || 'enabled',
    nfcConfigured: row.nfcConfigured || false,
    materialCount: row.materialCount || 0,
    videoCount: row.videoCount || 0
  })
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()
  submitting.value = true
  try {
    const formData = { name: storeForm.name, logo: storeForm.logo, address: storeForm.address, manager: storeForm.manager, phone: storeForm.phone, status: storeForm.status, nfcConfigured: storeForm.nfcConfigured, materialCount: storeForm.materialCount, videoCount: storeForm.videoCount }
    if (isEdit.value) {
      await updateStore(storeForm.id, formData)
      ElMessage.success('更新成功')
    } else {
      await createStore(formData)
      ElMessage.success('新增成功')
    }
    dialogVisible.value = false
    loadStores()
  } catch (error) {
    console.error('提交失败:', error)
    ElMessage.error(error.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除门店 "${row.name}" 吗？`, '提示', { type: 'warning' })
    await deleteStore(row.id)
    ElMessage.success('删除成功')
    loadStores()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(error.message || '删除失败')
    }
  }
}

const handleSizeChange = () => loadStores()
const handleCurrentChange = () => loadStores()
const handleDialogClose = () => formRef.value?.resetFields()

// 门店详情
const handleStoreDetail = async (row) => {
  detailStore.value = { ...row }
  detailTab.value = 'basic'
  detailDialogVisible.value = true
  detailLoading.value = true
  try {
    const res = await getStoreManageDetail(row.id)
    const data = res && typeof res === 'object' ? res : {}
    Object.assign(detailStore.value, data)
    // 填充各tab表单
    facilityForm.facilities = data.facilities || []
    poiForm.poiId = data.poiId || ''
    poiForm.poiName = data.poiName || ''
    poiForm.platforms = data.poiPlatforms || []
    decorationForm.themeColor = data.themeColor || '#409eff'
    decorationForm.backgroundImage = data.backgroundImage || ''
    decorationForm.customCss = data.customCss || ''
    tableStickerEnabled.value = data.tableStickerEnabled || false
  } catch (err) {
    console.error('获取门店详情失败:', err)
    facilityForm.facilities = []
    poiForm.poiId = ''
    poiForm.poiName = ''
    poiForm.platforms = []
    decorationForm.themeColor = '#409eff'
    decorationForm.backgroundImage = ''
    decorationForm.customCss = ''
    tableStickerEnabled.value = false
  } finally {
    detailLoading.value = false
  }
}

const handleSaveFacilities = async () => {
  detailSaving.value = true
  try {
    await updateStoreManage({ id: detailStore.value.id, type: 'facilities', data: { facilities: facilityForm.facilities } })
    ElMessage.success('保存成功')
  } catch (err) {
    console.error('保存设施失败:', err)
    ElMessage.error('保存失败')
  } finally {
    detailSaving.value = false
  }
}

const handleSavePoi = async () => {
  detailSaving.value = true
  try {
    await updateStoreManage({ id: detailStore.value.id, type: 'poi', data: { poiId: poiForm.poiId, poiName: poiForm.poiName, platforms: poiForm.platforms } })
    ElMessage.success('保存成功')
  } catch (err) {
    console.error('保存POI失败:', err)
    ElMessage.error('保存失败')
  } finally {
    detailSaving.value = false
  }
}

const handleSaveDecoration = async () => {
  detailSaving.value = true
  try {
    await updateStoreDecoration({ id: detailStore.value.id, themeColor: decorationForm.themeColor, backgroundImage: decorationForm.backgroundImage, customCss: decorationForm.customCss })
    ElMessage.success('保存成功')
  } catch (err) {
    console.error('保存装修配置失败:', err)
    ElMessage.error('保存失败')
  } finally {
    detailSaving.value = false
  }
}

const handleGenerateQrCode = async () => {
  detailSaving.value = true
  try {
    const res = await getStoreQrCode(detailStore.value.id)
    qrcodeUrl.value = res?.url || res?.qrcode || ''
    if (!qrcodeUrl.value) {
      qrcodeUrl.value = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=store_${detailStore.value.id}`
    }
    ElMessage.success('二维码已生成')
  } catch (err) {
    console.error('生成二维码失败:', err)
    qrcodeUrl.value = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=store_${detailStore.value.id}`
    ElMessage.success('二维码已生成')
  } finally {
    detailSaving.value = false
  }
}

const handleDownloadQrCode = () => {
  if (!qrcodeUrl.value) return
  const link = document.createElement('a')
  link.href = qrcodeUrl.value
  link.download = `${detailStore.value.name || 'store'}_qrcode.png`
  link.target = '_blank'
  link.click()
}

const handleLoadNfcPath = async () => {
  detailSaving.value = true
  try {
    const res = await getStoreNfcPath(detailStore.value.id)
    nfcPath.value = res?.path || `nfc://store/${detailStore.value.id}`
  } catch (err) {
    console.error('获取NFC路径失败:', err)
    nfcPath.value = `nfc://store/${detailStore.value.id}`
  } finally {
    detailSaving.value = false
  }
}

const handleCopyNfcPath = () => {
  navigator.clipboard.writeText(nfcPath.value).then(() => {
    ElMessage.success('已复制到剪贴板')
  }).catch(() => {
    ElMessage.error('复制失败')
  })
}

const handleToggleTableSticker = async (val) => {
  try {
    await toggleTableSticker({ id: detailStore.value.id, enabled: val })
    ElMessage.success(val ? '已启用桌贴' : '已禁用桌贴')
  } catch (err) {
    console.error('切换桌贴状态失败:', err)
    tableStickerEnabled.value = !val
    ElMessage.error('操作失败')
  }
}

// 批量导入
const handleBatchImport = () => {
  Object.assign(importForm, { type: 'store', jsonData: '', file: null })
  importDialogVisible.value = true
}


const resetImportForm = () => {
  importForm.jsonData = ''
  importForm.file = null
}

const handleImportFileChange = (file) => {
  importForm.file = file.raw
}

const handleImportSubmit = async () => {
  if (!importForm.jsonData && !importForm.file) {
    ElMessage.warning('请上传文件或粘贴JSON数据')
    return
  }
  importSubmitting.value = true
  try {
    let data
    if (importForm.jsonData) {
      data = JSON.parse(importForm.jsonData)
    }
    if (importForm.type === 'store') {
      await batchImportStores({ data, file: importForm.file })
    } else {
      await batchImportPoi({ data, file: importForm.file })
    }
    ElMessage.success('导入成功')
    importDialogVisible.value = false
    loadStores()
  } catch (err) {
    console.error('导入失败:', err)
    ElMessage.error(err.message || '导入失败')
  } finally {
    importSubmitting.value = false
  }
}

onMounted(() => {
  loadStores()
})
</script>

<style lang="scss" scoped>
.stores-container {
  padding: 20px;

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

  .package-info {
    display: flex;
    flex-direction: column;
    gap: 2px;

    .package-item {
      font-size: 12px;
      color: #909399;
    }
  }

  .qrcode-section {
    text-align: center;
    padding: 20px 0;

    .qrcode-preview {
      margin-bottom: 16px;
      img {
        border: 1px solid #ebeef5;
        border-radius: 8px;
      }
    }

    .qrcode-actions {
      display: flex;
      justify-content: center;
      gap: 12px;
    }
  }

  .upload-tip {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
  }
}
</style>
