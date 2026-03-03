<template>
  <div class="material-selector">
    <!-- 选择结果展示 -->
    <div v-if="selectedMaterials.length > 0" class="selected-preview">
      <div class="selected-header">
        <span class="selected-count">已选择 {{ selectedMaterials.length }} 个素材</span>
        <el-button type="primary" size="small" @click="openSelector">
          修改选择
        </el-button>
      </div>
      <div class="selected-materials">
        <component
          :is="draggableComponent"
          v-if="draggableComponent"
          v-model="selectedMaterials"
          item-key="id"
          class="selected-grid"
          ghost-class="ghost"
          animation="200"
        >
          <template #item="{ element, index }">
            <div class="selected-item">
              <el-image
                v-if="element.type === 'image'"
                :src="element.url"
                :alt="element.name"
                fit="cover"
                class="selected-thumb"
              />
              <div v-else class="selected-icon">
                <el-icon><VideoPlay /></el-icon>
              </div>
              <div class="selected-index">{{ index + 1 }}</div>
              <el-button
                type="danger"
                size="small"
                circle
                class="remove-btn"
                @click="removeMaterial(index)"
              >
                <el-icon><Close /></el-icon>
              </el-button>
            </div>
          </template>
        </component>
        <!-- 如果没有 draggable，使用普通布局 -->
        <div v-else class="selected-grid">
          <div
            v-for="(element, index) in selectedMaterials"
            :key="element.id"
            class="selected-item"
          >
            <el-image
              v-if="element.type === 'image'"
              :src="element.url"
              :alt="element.name"
              fit="cover"
              class="selected-thumb"
            />
            <div v-else class="selected-icon">
              <el-icon><VideoPlay /></el-icon>
            </div>
            <div class="selected-index">{{ index + 1 }}</div>
            <el-button
              type="danger"
              size="small"
              circle
              class="remove-btn"
              @click="removeMaterial(index)"
            >
              <el-icon><Close /></el-icon>
            </el-button>
          </div>
        </div>
      </div>
    </div>

    <!-- 未选择状态 -->
    <div v-else class="empty-selector" @click="openSelector">
      <el-icon class="add-icon"><Plus /></el-icon>
      <span>点击选择素材</span>
    </div>

    <!-- 选择器对话框 -->
    <el-dialog
      v-model="selectorVisible"
      title="选择素材"
      width="900px"
      destroy-on-close
      class="material-selector-dialog"
    >
      <div class="selector-content">
        <!-- 筛选栏 -->
        <div class="selector-filter">
          <el-radio-group v-model="listQuery.type" @change="handleFilterChange">
            <el-radio-button label="">全部</el-radio-button>
            <el-radio-button label="image">图片</el-radio-button>
            <el-radio-button label="video">视频</el-radio-button>
          </el-radio-group>
        </div>

        <!-- 素材网格 -->
        <div v-loading="loading" class="material-grid">
          <div
            v-for="item in materialList"
            :key="item.id"
            class="material-item"
            :class="{ 'is-selected': isItemSelected(item.id) }"
            @click="toggleSelect(item)"
          >
            <!-- 图片预览 -->
            <template v-if="item.type === 'image'">
              <el-image
                :src="item.url"
                :alt="item.name"
                fit="cover"
                class="material-thumb"
              >
                <template #error>
                  <div class="thumb-error">
                    <el-icon><Picture /></el-icon>
                  </div>
                </template>
              </el-image>
            </template>

            <!-- 视频预览 -->
            <template v-else-if="item.type === 'video'">
              <div class="video-thumb">
                <el-icon class="play-icon"><VideoPlay /></el-icon>
              </div>
            </template>

            <!-- 选中状态 -->
            <div v-if="isItemSelected(item.id)" class="selected-badge">
              <el-icon><Check /></el-icon>
              <span>{{ getSelectedIndex(item.id) + 1 }}</span>
            </div>

            <!-- 文件名 -->
            <div class="material-name" :title="item.name">{{ item.name }}</div>
          </div>

          <!-- 空状态 -->
          <div v-if="!loading && materialList.length === 0" class="empty-state">
            <el-empty description="暂无素材" />
          </div>
        </div>

        <!-- 分页 -->
        <div v-if="total > 0" class="pagination">
          <el-pagination
            v-model:current-page="listQuery.page"
            v-model:page-size="listQuery.limit"
            :total="total"
            :page-sizes="[20, 40, 60]"
            layout="total, prev, pager, next"
            @size-change="handleSizeChange"
            @current-change="handlePageChange"
          />
        </div>
      </div>

      <template #footer>
        <div class="dialog-footer">
          <span class="footer-tip">已选择 {{ tempSelected.length }} 个素材</span>
          <el-button @click="selectorVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmSelection">确定</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted, shallowRef } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Close, Check, Picture, VideoPlay } from '@element-plus/icons-vue'
import { getMaterialList } from '@/api/promo-material'

// 尝试导入 vuedraggable（可选依赖）
let draggableComponent = shallowRef(null)
try {
  const draggable = await import('vuedraggable')
  draggableComponent.value = draggable.default
} catch (e) {
  console.warn('vuedraggable not installed, drag sort will be disabled')
}

// Props
const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  maxSelect: {
    type: Number,
    default: 20
  },
  allowedTypes: {
    type: Array,
    default: () => ['image', 'video']
  }
})

// Emits
const emit = defineEmits(['update:modelValue', 'change'])

// 数据状态
const loading = ref(false)
const materialList = ref([])
const total = ref(0)

// 查询参数
const listQuery = reactive({
  page: 1,
  limit: 20,
  type: ''
})

// 已选择的素材
const selectedMaterials = ref([])

// 临时选择（对话框内）
const tempSelected = ref([])

// 对话框可见性
const selectorVisible = ref(false)

// 监听外部值变化
watch(() => props.modelValue, (newVal) => {
  if (Array.isArray(newVal)) {
    selectedMaterials.value = [...newVal]
  }
}, { immediate: true, deep: true })

// 监听内部选择变化，同步到外部
watch(selectedMaterials, (newVal) => {
  emit('update:modelValue', newVal)
  emit('change', newVal)
}, { deep: true })

// 获取素材列表
const getList = async () => {
  loading.value = true
  try {
    const params = { ...listQuery }
    // 移除空值
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })

    const response = await getMaterialList(params)
    if (response) {
      // 处理不同的响应格式
      if (response.list) {
        materialList.value = response.list
        total.value = response.pagination?.total || 0
      } else if (response.data) {
        materialList.value = Array.isArray(response.data) ? response.data : []
        total.value = response.total || materialList.value.length
      } else if (Array.isArray(response)) {
        materialList.value = response
        total.value = response.length
      }
    }
  } catch (error) {
    console.error('获取素材列表失败:', error)
    ElMessage.error('获取素材列表失败')
  } finally {
    loading.value = false
  }
}

// 打开选择器
const openSelector = () => {
  tempSelected.value = [...selectedMaterials.value]
  selectorVisible.value = true
  getList()
}

// 筛选变化
const handleFilterChange = () => {
  listQuery.page = 1
  getList()
}

// 分页大小变化
const handleSizeChange = (size) => {
  listQuery.limit = size
  listQuery.page = 1
  getList()
}

// 页码变化
const handlePageChange = (page) => {
  listQuery.page = page
  getList()
}

// 检查是否已选择
const isItemSelected = (id) => {
  return tempSelected.value.some(item => item.id === id)
}

// 获取选中序号
const getSelectedIndex = (id) => {
  return tempSelected.value.findIndex(item => item.id === id)
}

// 切换选择
const toggleSelect = (item) => {
  const index = tempSelected.value.findIndex(s => s.id === item.id)
  if (index > -1) {
    tempSelected.value.splice(index, 1)
  } else {
    if (tempSelected.value.length >= props.maxSelect) {
      ElMessage.warning(`最多只能选择 ${props.maxSelect} 个素材`)
      return
    }
    tempSelected.value.push(item)
  }
}

// 移除已选素材
const removeMaterial = (index) => {
  selectedMaterials.value.splice(index, 1)
}

// 确认选择
const confirmSelection = () => {
  selectedMaterials.value = [...tempSelected.value]
  selectorVisible.value = false
}

// 初始化
onMounted(() => {
  if (props.modelValue && props.modelValue.length > 0) {
    selectedMaterials.value = [...props.modelValue]
  }
})
</script>

<style scoped lang="scss">
.material-selector {
  .empty-selector {
    border: 2px dashed #dcdfe6;
    border-radius: 8px;
    padding: 40px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.3s;
    background: #fafafa;

    &:hover {
      border-color: #6366f1;
      background: #f5f3ff;
    }

    .add-icon {
      font-size: 32px;
      color: #c0c4cc;
    }

    span {
      color: #909399;
      font-size: 14px;
    }
  }

  .selected-preview {
    .selected-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;

      .selected-count {
        font-size: 14px;
        color: #606266;
        font-weight: 500;
      }
    }

    .selected-materials {
      .selected-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 12px;
      }

      .selected-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 6px;
        overflow: hidden;
        cursor: move;
        border: 2px solid #e4e7ed;
        transition: all 0.3s;

        &:hover {
          border-color: #6366f1;

          .remove-btn {
            opacity: 1;
          }
        }

        .selected-thumb {
          width: 100%;
          height: 100%;
        }

        .selected-icon {
          width: 100%;
          height: 100%;
          display: flex;
          align-items: center;
          justify-content: center;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: #fff;
          font-size: 32px;
        }

        .selected-index {
          position: absolute;
          top: 4px;
          left: 4px;
          width: 20px;
          height: 20px;
          border-radius: 50%;
          background: #6366f1;
          color: #fff;
          font-size: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .remove-btn {
          position: absolute;
          top: 4px;
          right: 4px;
          opacity: 0;
          transition: opacity 0.3s;
        }
      }
    }
  }

  .ghost {
    opacity: 0.5;
    background: #c8ebfb;
  }
}

.material-selector-dialog {
  .selector-content {
    .selector-filter {
      margin-bottom: 16px;
    }

    .material-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 16px;
      min-height: 300px;
      max-height: 500px;
      overflow-y: auto;
      padding: 4px;

      .material-item {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.3s;
        background: #fff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);

        &:hover {
          border-color: #6366f1;
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        &.is-selected {
          border-color: #6366f1;
          box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }

        .material-thumb {
          width: 100%;
          aspect-ratio: 1;
        }

        .video-thumb {
          width: 100%;
          aspect-ratio: 1;
          display: flex;
          align-items: center;
          justify-content: center;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: #fff;

          .play-icon {
            font-size: 36px;
            opacity: 0.9;
          }
        }

        .thumb-error {
          width: 100%;
          aspect-ratio: 1;
          display: flex;
          align-items: center;
          justify-content: center;
          background: #f5f7fa;
          color: #c0c4cc;
          font-size: 32px;
        }

        .selected-badge {
          position: absolute;
          top: 8px;
          right: 8px;
          width: 28px;
          height: 28px;
          border-radius: 50%;
          background: #6366f1;
          color: #fff;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 2px;
          font-size: 12px;
          font-weight: 600;

          .el-icon {
            font-size: 14px;
          }
        }

        .material-name {
          padding: 8px;
          font-size: 12px;
          color: #606266;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          background: #fff;
        }
      }

      .empty-state {
        grid-column: 1 / -1;
        padding: 40px 0;
      }
    }

    .pagination {
      margin-top: 16px;
      display: flex;
      justify-content: center;
    }
  }

  .dialog-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;

    .footer-tip {
      color: #909399;
      font-size: 14px;
      margin-right: auto;
    }
  }
}
</style>
