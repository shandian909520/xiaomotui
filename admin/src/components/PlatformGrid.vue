<!--
  PlatformGrid 平台图标矩阵（视频里 7 通道：抖音/快手/小红书×2/朋友圈×2/视频号）
  用途：发布页、AI 文案页选择发布平台。点击切换选中。
-->
<template>
  <div class="xmt-platform-grid" :style="`grid-template-columns: repeat(${columns}, 1fr); gap: ${gap}px;`">
    <div
      v-for="p in platforms"
      :key="p.key"
      class="xmt-platform-item"
      :class="{ 'is-selected': isSelected(p.key), 'is-disabled': disabled }"
      @click="onToggle(p)"
    >
      <div class="xmt-pf-icon" :style="{ background: p.bg || '#FF6B35' }">
        <span class="xmt-pf-emoji">{{ p.icon }}</span>
        <el-icon v-if="isSelected(p.key)" class="xmt-pf-check"><Check /></el-icon>
      </div>
      <span class="xmt-pf-name">{{ p.name }}</span>
    </div>
  </div>
</template>

<script setup>
import { Check } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

/**
 * @typedef {Object} PlatformDef
 * @property {string} key     唯一 key，如 'douyin'
 * @property {string} name    显示名
 * @property {string} icon    emoji 或字符图标
 * @property {string} [bg]    背景色，默认橘红
 */

/**
 * @typedef {Object} PlatformGridProps
 * @property {PlatformDef[]} platforms 平台列表
 * @property {string[]} [selected]    已选中的 key 数组，支持 :selected.sync / v-model
 * @property {number} [max]           最多可选数量，0 表示不限制
 * @property {number} [columns]       每行几个，默认 4
 * @property {number} [gap]           间距像素，默认 12
 * @property {boolean} [disabled]     只读模式
 */
const props = defineProps({
  platforms: { type: Array, required: true },
  selected: { type: Array, default: () => [] },
  max: { type: Number, default: 0 },
  columns: { type: Number, default: 4 },
  gap: { type: Number, default: 12 },
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['update:selected', 'change'])

const isSelected = (key) => props.selected.includes(key)

const onToggle = (p) => {
  if (props.disabled) return
  const list = [...props.selected]
  const idx = list.indexOf(p.key)
  if (idx > -1) {
    list.splice(idx, 1)
  } else {
    if (props.max > 0 && list.length >= props.max) {
      ElMessage.warning(`最多选择 ${props.max} 个平台`)
      return
    }
    list.push(p.key)
  }
  emit('update:selected', list)
  emit('change', list)
}
</script>

<script>
export default { name: 'PlatformGrid' }
</script>

<style lang="scss" scoped>
.xmt-platform-grid {
  display: grid;
}

.xmt-platform-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 8px 4px;
  border-radius: 12px;
  background: #fafafa;
  cursor: pointer;
  transition: all 0.2s;
  user-select: none;

  &:hover:not(.is-disabled) {
    background: rgba(255, 107, 53, 0.06);
    transform: translateY(-2px);
  }

  &.is-selected {
    background: rgba(255, 107, 53, 0.12);
    box-shadow: 0 2px 8px rgba(255, 107, 53, 0.2);
  }

  &.is-disabled {
    cursor: not-allowed;
    opacity: 0.7;
  }
}

.xmt-pf-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  position: relative;
  font-size: 22px;
}

.xmt-pf-check {
  position: absolute;
  bottom: -4px;
  right: -4px;
  background: #FF6B35;
  color: #fff;
  border-radius: 50%;
  padding: 2px;
  font-size: 12px;
  border: 2px solid #fff;
  width: 16px;
  height: 16px;
  box-sizing: content-box;
}

.xmt-pf-name {
  font-size: 12px;
  color: #606266;
}
</style>
