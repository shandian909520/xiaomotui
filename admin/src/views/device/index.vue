<template>
  <div class="device-container">
    <!-- 搜索和操作栏 -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="设备名称">
          <el-input v-model="searchForm.keyword" placeholder="设备名称或编码" clearable />
        </el-form-item>
        <el-form-item label="设备状态">
          <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
            <el-option label="全部" value="" />
            <el-option label="在线" :value="1" />
            <el-option label="离线" :value="0" />
            <el-option label="维护" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="设备类型">
          <el-select v-model="searchForm.type" placeholder="全部类型" clearable>
            <el-option label="全部" value="" />
            <el-option label="桌台" value="TABLE" />
            <el-option label="墙面" value="WALL" />
            <el-option label="柜台" value="COUNTER" />
            <el-option label="入口" value="ENTRANCE" />
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
          添加设备
        </el-button>
        <el-button @click="handleRefresh">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
        <el-button :disabled="!selectedDevices.length" @click="handleBatchEnable">
          批量启用
        </el-button>
        <el-button :disabled="!selectedDevices.length" @click="handleBatchDisable">
          批量禁用
        </el-button>
      </div>
    </el-card>

    <!-- 设备列表表格 -->
    <el-card class="table-card">
      <el-table
        :data="deviceList"
        v-loading="loading"
        @selection-change="handleSelectionChange"
        stripe
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="device_code" label="设备编码" width="150" />
        <el-table-column prop="device_name" label="设备名称" width="180" />
        <el-table-column prop="type" label="设备类型" width="100">
          <template #default="{ row }">
            <el-tag>{{ getDeviceType(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="location" label="位置" width="150" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="电量" width="120">
          <template #default="{ row }">
            <el-progress
              :percentage="row.battery_level || 0"
              :status="getBatteryStatus(row.battery_level)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="trigger_mode" label="触发模式" width="120">
          <template #default="{ row }">
            {{ getTriggerMode(row.trigger_mode) }}
          </template>
        </el-table-column>
        <el-table-column prop="last_heartbeat" label="最后心跳" width="180">
          <template #default="{ row }">
            {{ formatTime(row.last_heartbeat) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleConfig(row)">配置</el-button>
            <el-button size="small" @click="handleEdit(row)">编辑</el-button>
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

    <!-- 添加/编辑设备对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="600px"
      @close="handleDialogClose"
    >
      <el-form :model="deviceForm" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="设备编码" prop="device_code">
          <el-input v-model="deviceForm.device_code" :disabled="isEdit" placeholder="请输入设备编码" />
        </el-form-item>
        <el-form-item label="设备名称" prop="device_name">
          <el-input v-model="deviceForm.device_name" placeholder="请输入设备名称" />
        </el-form-item>
        <el-form-item label="设备类型" prop="type">
          <el-select v-model="deviceForm.type" placeholder="请选择设备类型" style="width: 100%">
            <el-option label="桌台" value="TABLE" />
            <el-option label="墙面" value="WALL" />
            <el-option label="柜台" value="COUNTER" />
            <el-option label="入口" value="ENTRANCE" />
          </el-select>
        </el-form-item>
        <el-form-item label="设备位置" prop="location">
          <el-input v-model="deviceForm.location" placeholder="请输入设备位置" />
        </el-form-item>
        <el-form-item label="设备描述">
          <el-input
            v-model="deviceForm.description"
            type="textarea"
            :rows="3"
            placeholder="请输入设备描述（可选）"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- 设备配置对话框 -->
    <el-dialog
      v-model="configDialogVisible"
      title="设备配置"
      width="700px"
      @close="handleConfigDialogClose"
    >
      <el-form :model="configForm" label-width="120px">
        <el-form-item label="触发模式">
          <el-select v-model="configForm.trigger_mode" placeholder="请选择触发模式" style="width: 100%">
            <el-option label="视频生成" value="VIDEO" />
            <el-option label="优惠券" value="COUPON" />
            <el-option label="WiFi连接" value="WIFI" />
            <el-option label="好友添加" value="CONTACT" />
            <el-option label="菜单展示" value="MENU" />
            <el-option label="消费者推广" value="PROMO" />
          </el-select>
        </el-form-item>

        <el-form-item label="内容模板" v-if="configForm.trigger_mode === 'VIDEO'">
          <el-select v-model="configForm.template_id" placeholder="请选择内容模板" style="width: 100%">
            <el-option label="默认模板" :value="0" />
            <el-option
              v-for="template in templateList"
              :key="template.id"
              :label="template.name"
              :value="template.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="跳转链接" v-if="['COUPON', 'MENU'].includes(configForm.trigger_mode)">
          <el-input v-model="configForm.redirect_url" placeholder="请输入跳转链接" />
        </el-form-item>

        <el-form-item label="WiFi名称" v-if="configForm.trigger_mode === 'WIFI'">
          <el-input v-model="configForm.wifi_ssid" placeholder="请输入WiFi名称" />
        </el-form-item>

        <el-form-item label="WiFi密码" v-if="configForm.trigger_mode === 'WIFI'">
          <el-input v-model="configForm.wifi_password" type="password" placeholder="请输入WiFi密码" show-password />
        </el-form-item>

        <el-form-item label="企业微信" v-if="configForm.trigger_mode === 'CONTACT'">
          <el-input v-model="configForm.contact_qr" placeholder="请输入企业微信二维码地址" />
        </el-form-item>

        <!-- 推广配置 -->
        <template v-if="configForm.trigger_mode === 'PROMO'">
          <el-divider content-position="left">推广配置</el-divider>

          <el-form-item label="推广视频">
            <el-select v-model="configForm.promo_video_id" placeholder="请选择推广视频" style="width: 100%">
              <el-option label="未选择" :value="0" />
              <el-option-group label="视频库素材" v-if="videoLibraryList.length">
                <el-option
                  v-for="video in videoLibraryList"
                  :key="'v_' + video.id"
                  :label="video.name || video.title"
                  :value="video.id"
                />
              </el-option-group>
              <el-option-group label="内容模板" v-if="templateList.length">
                <el-option
                  v-for="tpl in templateList"
                  :key="'t_' + tpl.id"
                  :label="tpl.name"
                  :value="tpl.id"
                />
              </el-option-group>
            </el-select>
            <div class="form-tip">选择商家上传的推广视频，消费者将下载该视频发布到短视频平台</div>
          </el-form-item>

          <el-form-item label="推广文案">
            <el-input
              v-model="configForm.promo_copywriting"
              type="textarea"
              :rows="3"
              placeholder="请输入推广文案，消费者发布视频时将复制此文案"
              maxlength="500"
              show-word-limit
            />
          </el-form-item>

          <el-form-item label="话题标签">
            <div class="tags-input-area">
              <el-tag
                v-for="(tag, index) in promoTags"
                :key="index"
                closable
                @close="removePromoTag(index)"
                style="margin-right: 8px; margin-bottom: 4px;"
              >
                {{ tag }}
              </el-tag>
              <el-input
                v-if="showTagInput"
                ref="tagInputRef"
                v-model="newTagValue"
                size="small"
                style="width: 160px"
                placeholder="输入标签按回车"
                @keyup.enter="addPromoTag"
                @blur="addPromoTag"
              />
              <el-button v-else size="small" @click="showTagInput = true">
                + 添加标签
              </el-button>
            </div>
            <div class="form-tip">推荐添加带 # 的话题标签，如 #美食推荐 #打卡</div>
          </el-form-item>

          <el-form-item label="奖励优惠券">
            <el-select v-model="configForm.promo_reward_coupon_id" placeholder="请选择奖励优惠券" style="width: 100%">
              <el-option label="无奖励" :value="0" />
              <el-option
                v-for="coupon in couponList"
                :key="coupon.id"
                :label="`${coupon.name} (剩余${coupon.remaining ?? '∞'}张)`"
                :value="coupon.id"
              />
            </el-select>
            <div class="form-tip">消费者发布视频后将自动获得此优惠券作为奖励</div>
          </el-form-item>
        </template>

        <el-form-item label="设备状态">
          <el-radio-group v-model="configForm.status">
            <el-radio :label="1">在线</el-radio>
            <el-radio :label="0">离线</el-radio>
            <el-radio :label="2">维护</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="configDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveConfig" :loading="configSubmitting">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh } from '@element-plus/icons-vue'
import { 
  getDeviceList, 
  getDeviceDetail,
  createDevice, 
  updateDevice, 
  deleteDevice, 
  updateDeviceConfig,
  updateDeviceStatus,
  batchEnableDevice, 
  batchDisableDevice 
} from '@/api/device'
import { getTemplateList } from '@/api/content'
import { getCouponList } from '@/api/coupon'
import { getVideoLibraryList } from '@/api/video-library'

// 搜索表单
const searchForm = reactive({
  keyword: '',
  status: '',
  type: ''
})

// 设备列表
const deviceList = ref([])
const loading = ref(false)
const selectedDevices = ref([])

// 分页
const pagination = reactive({
  page: 1,
  limit: 20,
  total: 0
})

// 对话框
const dialogVisible = ref(false)
const dialogTitle = ref('添加设备')
const isEdit = ref(false)
const formRef = ref(null)
const submitting = ref(false)

// 设备表单
const deviceForm = reactive({
  id: null,
  device_code: '',
  device_name: '',
  type: 'TABLE',
  location: '',
  description: ''
})

// 配置对话框
const configDialogVisible = ref(false)
const configSubmitting = ref(false)
const configForm = reactive({
  deviceId: null,
  trigger_mode: 'VIDEO',
  template_id: 0,
  redirect_url: '',
  wifi_ssid: '',
  wifi_password: '',
  contact_qr: '',
  status: 1,
  promo_video_id: 0,
  promo_copywriting: '',
  promo_reward_coupon_id: 0
})

// 模板列表
const templateList = ref([])

// 优惠券列表
const couponList = ref([])

// 视频库列表
const videoLibraryList = ref([])

// 推广标签
const promoTags = ref([])
const showTagInput = ref(false)
const newTagValue = ref('')
const tagInputRef = ref(null)

// 表单验证规则
const rules = {
  device_code: [
    { required: true, message: '请输入设备编码', trigger: 'blur' },
    { min: 3, max: 50, message: '长度在 3 到 50 个字符', trigger: 'blur' }
  ],
  device_name: [
    { required: true, message: '请输入设备名称', trigger: 'blur' },
    { min: 2, max: 100, message: '长度在 2 到 100 个字符', trigger: 'blur' }
  ],
  type: [
    { required: true, message: '请选择设备类型', trigger: 'change' }
  ],
  location: [
    { required: true, message: '请输入设备位置', trigger: 'blur' }
  ]
}

// 加载设备列表
const loadDevices = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      limit: pagination.limit
    }

    // 添加搜索条件
    if (searchForm.keyword) {
      params.keyword = searchForm.keyword
    }
    if (searchForm.status !== '') {
      params.status = searchForm.status
    }
    if (searchForm.type) {
      params.type = searchForm.type
    }

    const res = await getDeviceList(params)

    // request.js 拦截器已解包，res 即为 data 字段
    deviceList.value = res.list || res.data || []
    pagination.total = res.pagination?.total || res.total || 0
  } catch (error) {
    console.error('加载设备列表失败:', error)
    ElMessage.error(error.message || '加载设备列表失败')
  } finally {
    loading.value = false
  }
}

// 加载模板列表
const loadTemplates = async () => {
  try {
    const res = await getTemplateList({ page: 1, limit: 100 })
    templateList.value = res.list || res.data || res || []
  } catch (error) {
    console.error('加载模板列表失败:', error)
  }
}

// 加载优惠券列表
const loadCoupons = async () => {
  try {
    const res = await getCouponList({ page: 1, limit: 100, status: 'active' })
    couponList.value = res.list || res.data || res || []
  } catch (error) {
    console.error('加载优惠券列表失败:', error)
  }
}

// 加载视频库列表
const loadVideoLibrary = async () => {
  try {
    const res = await getVideoLibraryList({ page: 1, limit: 100 })
    videoLibraryList.value = res.list || res.data || res || []
  } catch (error) {
    console.error('加载视频库列表失败:', error)
  }
}

// 添加推广标签
const addPromoTag = () => {
  const val = newTagValue.value.trim()
  if (val && !promoTags.value.includes(val)) {
    promoTags.value.push(val)
  }
  newTagValue.value = ''
  showTagInput.value = false
}

// 移除推广标签
const removePromoTag = (index) => {
  promoTags.value.splice(index, 1)
}

// 搜索
const handleSearch = () => {
  pagination.page = 1
  loadDevices()
}

// 重置
const handleReset = () => {
  Object.assign(searchForm, {
    keyword: '',
    status: '',
    type: ''
  })
  handleSearch()
}

// 刷新
const handleRefresh = () => {
  loadDevices()
  ElMessage.success('刷新成功')
}

// 添加设备
const handleAdd = () => {
  dialogTitle.value = '添加设备'
  isEdit.value = false
  Object.assign(deviceForm, {
    id: null,
    device_code: '',
    device_name: '',
    type: 'TABLE',
    location: '',
    description: ''
  })
  dialogVisible.value = true
}

// 编辑设备
const handleEdit = (row) => {
  dialogTitle.value = '编辑设备'
  isEdit.value = true
  Object.assign(deviceForm, {
    id: row.id,
    device_code: row.device_code,
    device_name: row.device_name,
    type: row.type || 'TABLE',
    location: row.location || '',
    description: row.description || ''
  })
  dialogVisible.value = true
}

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate()

  submitting.value = true
  try {
    const data = { ...deviceForm }
    delete data.id

    // 新建设备时设置默认触发模式
    if (!isEdit.value && !data.trigger_mode) {
      data.trigger_mode = 'VIDEO'
    }

    if (isEdit.value) {
      await updateDevice(deviceForm.id, data)
      ElMessage.success('更新成功')
    } else {
      await createDevice(data)
      ElMessage.success('添加成功')
    }
    dialogVisible.value = false
    loadDevices()
  } catch (error) {
    console.error('提交失败:', error)
    ElMessage.error(error.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

// 配置设备
const handleConfig = async (row) => {
  try {
    // 获取设备详情和配置
    const data = await getDeviceDetail(row.id)

    if (data) {
      Object.assign(configForm, {
        deviceId: row.id,
        trigger_mode: data.trigger_mode || 'VIDEO',
        template_id: data.template_id || 0,
        redirect_url: data.redirect_url || '',
        wifi_ssid: data.wifi_ssid || '',
        wifi_password: data.wifi_password || '',
        contact_qr: data.contact_qr || '',
        status: data.status ?? 1,
        promo_video_id: data.promo_video_id || 0,
        promo_copywriting: data.promo_copywriting || '',
        promo_reward_coupon_id: data.promo_reward_coupon_id || 0
      })

      // 加载推广标签
      promoTags.value = Array.isArray(data.promo_tags) ? data.promo_tags : []

      // 加载模板列表、优惠券列表和视频库
      await Promise.all([loadTemplates(), loadCoupons(), loadVideoLibrary()])

      configDialogVisible.value = true
    }
  } catch (error) {
    console.error('获取配置失败:', error)
    ElMessage.error(error.message || '获取配置失败')
  }
}

// 保存配置
const handleSaveConfig = async () => {
  configSubmitting.value = true
  try {
    const deviceId = configForm.deviceId
    
    // 1. 更新配置
    const configData = {
      trigger_mode: configForm.trigger_mode,
      template_id: configForm.template_id,
      redirect_url: configForm.redirect_url,
      wifi_ssid: configForm.wifi_ssid,
      wifi_password: configForm.wifi_password,
      contact_qr: configForm.contact_qr
    }

    // 推广模式额外字段
    if (configForm.trigger_mode === 'PROMO') {
      configData.promo_video_id = configForm.promo_video_id
      configData.promo_copywriting = configForm.promo_copywriting
      configData.promo_tags = promoTags.value
      configData.promo_reward_coupon_id = configForm.promo_reward_coupon_id
    }

    await updateDeviceConfig(deviceId, configData)

    // 2. 更新状态
    await updateDeviceStatus(deviceId, configForm.status)

    ElMessage.success('配置保存成功')
    configDialogVisible.value = false
    loadDevices()
  } catch (error) {
    console.error('保存配置失败:', error)
    ElMessage.error(error.message || '保存配置失败')
  } finally {
    configSubmitting.value = false
  }
}

// 删除设备
const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定删除设备 "${row.device_name}" 吗？此操作不可恢复。`,
      '提示',
      {
        type: 'warning',
        confirmButtonText: '确定',
        cancelButtonText: '取消'
      }
    )

    await deleteDevice(row.id)
    ElMessage.success('删除成功')
    loadDevices()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error(error.message || '删除失败')
    }
  }
}

// 选择变化
const handleSelectionChange = (selection) => {
  selectedDevices.value = selection
}

// 批量启用
const handleBatchEnable = async () => {
  if (!selectedDevices.value.length) return
  try {
    const ids = selectedDevices.value.map(item => item.id)
    await batchEnableDevice(ids)
    ElMessage.success('批量启用成功')
    loadDevices()
  } catch (error) {
    console.error('批量启用失败:', error)
    ElMessage.error(error.message || '批量启用失败')
  }
}

// 批量禁用
const handleBatchDisable = async () => {
  if (!selectedDevices.value.length) return
  try {
    const ids = selectedDevices.value.map(item => item.id)
    await batchDisableDevice(ids)
    ElMessage.success('批量禁用成功')
    loadDevices()
  } catch (error) {
    console.error('批量禁用失败:', error)
    ElMessage.error(error.message || '批量禁用失败')
  }
}

// 分页大小变化
const handleSizeChange = (val) => {
  pagination.limit = val
  loadDevices()
}

// 页码变化
const handleCurrentChange = (val) => {
  pagination.page = val
  loadDevices()
}

// 关闭弹窗
const handleDialogClose = () => {
  formRef.value?.resetFields()
}

const handleConfigDialogClose = () => {
  // 重置配置表单
  Object.assign(configForm, {
    deviceId: null,
    trigger_mode: 'VIDEO',
    template_id: 0,
    redirect_url: '',
    wifi_ssid: '',
    wifi_password: '',
    contact_qr: '',
    status: 1,
    promo_video_id: 0,
    promo_copywriting: '',
    promo_reward_coupon_id: 0
  })
  promoTags.value = []
  showTagInput.value = false
  newTagValue.value = ''
}

// 辅助函数
const getDeviceType = (type) => {
  const map = {
    TABLE: '桌台',
    WALL: '墙面',
    COUNTER: '柜台',
    ENTRANCE: '入口'
  }
  return map[type] || type
}

const getStatusText = (status) => {
  const map = {
    0: '离线',
    1: '在线',
    2: '维护'
  }
  return map[status] || '未知'
}

const getStatusType = (status) => {
  const map = {
    0: 'info',
    1: 'success',
    2: 'warning'
  }
  return map[status] || 'info'
}

const getBatteryStatus = (level) => {
  if (!level) return 'exception'
  if (level > 50) return 'success'
  if (level > 20) return 'warning'
  return 'exception'
}

const getTriggerMode = (mode) => {
  const map = {
    VIDEO: '视频生成',
    COUPON: '优惠券',
    WIFI: 'WiFi连接',
    CONTACT: '好友添加',
    MENU: '菜单展示',
    PROMO: '消费者推广'
  }
  return map[mode] || mode || '-'
}

const formatTime = (time) => {
  if (!time) return '-'
  return time.replace('T', ' ').substring(0, 19)
}

// 定时刷新
let refreshTimer = null

onMounted(() => {
  loadDevices()
  // 每30秒自动刷新
  refreshTimer = setInterval(() => {
    loadDevices()
  }, 30000)
})

onBeforeUnmount(() => {
  if (refreshTimer) {
    clearInterval(refreshTimer)
  }
})
</script>

<style lang="scss" scoped>
.device-container {
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

.form-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
  margin-top: 4px;
}

.tags-input-area {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}
</style>
