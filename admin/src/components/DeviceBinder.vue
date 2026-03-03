<template>
  <div class="device-binder">
    <!-- 添加设备 -->
    <div class="add-device-section">
      <div class="section-title">添加设备</div>
      <div class="add-methods">
        <el-input
          v-model="searchKeyword"
          placeholder="输入设备SN码搜索"
          clearable
          class="search-input"
          @keyup.enter="handleSearchDevice"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
        <el-button type="primary" @click="handleSearchDevice">
          搜索设备
        </el-button>
        <el-button @click="showDeviceSelector = true">
          从设备列表选择
        </el-button>
      </div>
    </div>

    <!-- 已绑定设备列表 -->
    <div class="bound-devices-section">
      <div class="section-header">
        <span class="section-title">已绑定设备 ({{ boundDevices.length }})</span>
        <el-button
          v-if="boundDevices.length > 0"
          type="danger"
          size="small"
          text
          @click="handleUnbindAll"
        >
          全部解绑
        </el-button>
      </div>

      <div v-if="boundDevices.length > 0" class="device-list">
        <div
          v-for="device in boundDevices"
          :key="device.id"
          class="device-item"
        >
          <div class="device-info">
            <div class="device-main">
              <span class="device-name">{{ device.name || device.sn }}</span>
              <el-tag
                :type="device.status === 'online' ? 'success' : 'info'"
                size="small"
              >
                {{ device.status === 'online' ? '在线' : '离线' }}
              </el-tag>
            </div>
            <div class="device-meta">
              <span class="device-sn">SN: {{ device.sn }}</span>
              <span v-if="device.location" class="device-location">
                {{ device.location }}
              </span>
            </div>
          </div>
          <div class="device-actions">
            <el-button
              type="danger"
              size="small"
              text
              @click="handleUnbindDevice(device)"
            >
              解绑
            </el-button>
          </div>
        </div>
      </div>

      <el-empty v-else description="暂无绑定设备" :image-size="80" />
    </div>

    <!-- 设备选择器对话框 -->
    <el-dialog
      v-model="showDeviceSelector"
      title="选择设备"
      width="800px"
      destroy-on-close
      append-to-body
    >
      <div class="device-selector">
        <!-- 搜索栏 -->
        <div class="selector-filter">
          <el-input
            v-model="deviceQuery.keyword"
            placeholder="搜索设备名称或SN码"
            clearable
            style="width: 200px"
            @keyup.enter="getDeviceList"
          >
            <template #prefix>
              <el-icon><Search /></el-icon>
            </template>
          </el-input>
          <el-select
            v-model="deviceQuery.status"
            placeholder="设备状态"
            clearable
            style="width: 120px"
            @change="getDeviceList"
          >
            <el-option label="在线" value="online" />
            <el-option label="离线" value="offline" />
          </el-select>
          <el-button @click="getDeviceList">
            <el-icon><Refresh /></el-icon>
            刷新
          </el-button>
        </div>

        <!-- 设备表格 -->
        <el-table
          ref="deviceTableRef"
          v-loading="deviceLoading"
          :data="availableDevices"
          border
          stripe
          max-height="400"
          @selection-change="handleDeviceSelect"
        >
          <el-table-column type="selection" width="55" align="center" />
          <el-table-column label="设备名称" prop="name" min-width="120" show-overflow-tooltip>
            <template #default="{ row }">
              {{ row.name || row.sn }}
            </template>
          </el-table-column>
          <el-table-column label="SN码" prop="sn" width="140" />
          <el-table-column label="状态" width="80" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 'online' ? 'success' : 'info'" size="small">
                {{ row.status === 'online' ? '在线' : '离线' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="位置" prop="location" min-width="120" show-overflow-tooltip>
            <template #default="{ row }">
              {{ row.location || '-' }}
            </template>
          </el-table-column>
        </el-table>

        <!-- 分页 -->
        <div v-if="deviceTotal > 0" class="selector-pagination">
          <el-pagination
            v-model:current-page="deviceQuery.page"
            v-model:page-size="deviceQuery.limit"
            :total="deviceTotal"
            :page-sizes="[10, 20, 50]"
            layout="total, sizes, prev, pager, next"
            small
            @size-change="getDeviceList"
            @current-change="getDeviceList"
          />
        </div>
      </div>

      <template #footer>
        <el-button @click="showDeviceSelector = false">取消</el-button>
        <el-button
          type="primary"
          :disabled="selectedDevices.length === 0"
          @click="confirmAddDevices"
        >
          添加 ({{ selectedDevices.length }})
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh } from '@element-plus/icons-vue'
import { getDeviceList } from '@/api/device'
import { bindDevices, unbindDevice } from '@/api/promo-campaign'

// Props
const props = defineProps({
  campaignId: {
    type: [Number, String],
    required: true
  },
  initialDevices: {
    type: Array,
    default: () => []
  }
})

// Emits
const emit = defineEmits(['update', 'error'])

// 状态
const searchKeyword = ref('')
const showDeviceSelector = ref(false)
const boundDevices = ref([])
const deviceLoading = ref(false)
const deviceTableRef = ref(null)

// 设备查询参数
const deviceQuery = reactive({
  page: 1,
  limit: 10,
  keyword: '',
  status: ''
})

// 设备列表数据
const deviceListData = ref([])
const deviceTotal = ref(0)

// 选中的设备
const selectedDevices = ref([])

// 可用设备（排除已绑定的）
const availableDevices = computed(() => {
  const boundIds = boundDevices.value.map(d => d.id)
  return deviceListData.value.filter(d => !boundIds.includes(d.id))
})

// 获取设备列表
const fetchDeviceList = async () => {
  deviceLoading.value = true
  try {
    const params = { ...deviceQuery }
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })

    const response = await getDeviceList(params)
    if (response) {
      if (response.list) {
        deviceListData.value = response.list
        deviceTotal.value = response.pagination?.total || 0
      } else if (response.data) {
        deviceListData.value = Array.isArray(response.data) ? response.data : []
        deviceTotal.value = response.total || deviceListData.value.length
      } else if (Array.isArray(response)) {
        deviceListData.value = response
        deviceTotal.value = response.length
      }
    }
  } catch (error) {
    console.error('获取设备列表失败:', error)
    ElMessage.error('获取设备列表失败')
  } finally {
    deviceLoading.value = false
  }
}

// 搜索设备
const handleSearchDevice = async () => {
  if (!searchKeyword.value.trim()) {
    ElMessage.warning('请输入设备SN码')
    return
  }

  deviceQuery.keyword = searchKeyword.value.trim()
  deviceQuery.page = 1
  showDeviceSelector.value = true
  await fetchDeviceList()
}

// 设备选择变化
const handleDeviceSelect = (selection) => {
  selectedDevices.value = selection
}

// 确认添加设备
const confirmAddDevices = async () => {
  if (selectedDevices.value.length === 0) {
    ElMessage.warning('请选择要添加的设备')
    return
  }

  try {
    const deviceIds = selectedDevices.value.map(d => d.id)
    await bindDevices(props.campaignId, deviceIds)

    // 添加到已绑定列表
    boundDevices.value.push(...selectedDevices.value)
    selectedDevices.value = []
    showDeviceSelector.value = false

    ElMessage.success(`成功绑定 ${deviceIds.length} 个设备`)
    emit('update', boundDevices.value)
  } catch (error) {
    console.error('绑定设备失败:', error)
    ElMessage.error('绑定设备失败')
    emit('error', error)
  }
}

// 解绑单个设备
const handleUnbindDevice = async (device) => {
  try {
    await ElMessageBox.confirm(
      `确定要解绑设备"${device.name || device.sn}"吗？`,
      '解绑确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await unbindDevice(props.campaignId, device.id)

    // 从已绑定列表中移除
    const index = boundDevices.value.findIndex(d => d.id === device.id)
    if (index > -1) {
      boundDevices.value.splice(index, 1)
    }

    ElMessage.success('设备已解绑')
    emit('update', boundDevices.value)
  } catch (error) {
    if (error !== 'cancel') {
      console.error('解绑设备失败:', error)
      ElMessage.error('解绑设备失败')
      emit('error', error)
    }
  }
}

// 解绑全部设备
const handleUnbindAll = async () => {
  try {
    await ElMessageBox.confirm(
      '确定要解绑所有设备吗？此操作不可撤销。',
      '批量解绑确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    // 逐个解绑
    const deviceIds = boundDevices.value.map(d => d.id)
    for (const deviceId of deviceIds) {
      await unbindDevice(props.campaignId, deviceId)
    }

    boundDevices.value = []
    ElMessage.success('所有设备已解绑')
    emit('update', boundDevices.value)
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量解绑失败:', error)
      ElMessage.error('批量解绑失败')
      emit('error', error)
    }
  }
}

// 监听初始设备列表
watch(
  () => props.initialDevices,
  (newVal) => {
    boundDevices.value = newVal || []
  },
  { immediate: true }
)

// 监听设备选择器打开
watch(showDeviceSelector, (val) => {
  if (val) {
    fetchDeviceList()
  } else {
    selectedDevices.value = []
    deviceQuery.keyword = ''
    deviceQuery.status = ''
    deviceQuery.page = 1
  }
})

// 暴露方法
defineExpose({
  boundDevices
})
</script>

<style scoped lang="scss">
.device-binder {
  .add-device-section {
    margin-bottom: 24px;

    .section-title {
      font-size: 14px;
      font-weight: 500;
      color: #303133;
      margin-bottom: 12px;
    }

    .add-methods {
      display: flex;
      gap: 12px;

      .search-input {
        width: 240px;
      }
    }
  }

  .bound-devices-section {
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .section-title {
      font-size: 14px;
      font-weight: 500;
      color: #303133;
    }

    .device-list {
      display: flex;
      flex-direction: column;
      gap: 8px;

      .device-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: #f5f7fa;
        border-radius: 8px;
        transition: all 0.3s;

        &:hover {
          background: #eef1f6;
        }

        .device-info {
          flex: 1;

          .device-main {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;

            .device-name {
              font-size: 14px;
              font-weight: 500;
              color: #303133;
            }
          }

          .device-meta {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: #909399;

            .device-sn {
              font-family: monospace;
            }
          }
        }

        .device-actions {
          flex-shrink: 0;
        }
      }
    }
  }

  .device-selector {
    .selector-filter {
      display: flex;
      gap: 12px;
      margin-bottom: 16px;
    }

    .selector-pagination {
      margin-top: 16px;
      display: flex;
      justify-content: flex-end;
    }
  }
}
</style>
