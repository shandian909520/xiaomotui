<!--
  CopywriterCompare AI 文案对比组件
  视频里 AI 文案生成后给 5 套候选，左右滑动对比选择
-->
<template>
  <div class="xmt-copywriter-compare">
    <el-carousel
      v-model="active"
      :autoplay="false"
      :loop="false"
      arrow="always"
      indicator-position="outside"
      height="220px"
    >
      <el-carousel-item v-for="(c, idx) in candidates" :key="idx">
        <div class="xmt-copy-card" :class="{ 'is-active': idx === innerIndex }">
          <div class="xmt-copy-head">
            <span class="xmt-copy-tag">方案 {{ idx + 1 }}</span>
            <el-tag v-if="c && c.score" size="small" type="warning" effect="plain">
              ⭐ {{ c.score }} 分
            </el-tag>
          </div>
          <div class="xmt-copy-body">{{ c && c.text || c && c.content || typeof c === 'string' ? c : '' }}</div>
          <div class="xmt-copy-actions">
            <el-button
              size="small"
              :type="idx === innerIndex ? 'primary' : ''"
              :plain="idx !== innerIndex"
              @click="onSelect(idx)"
            >
              {{ idx === innerIndex ? '已选中' : '选这个' }}
            </el-button>
            <el-button size="small" plain @click="onApply(c)">应用</el-button>
          </div>
        </div>
      </el-carousel-item>
    </el-carousel>
    <div class="xmt-copy-pager">{{ innerIndex + 1 }} / {{ candidates.length }}</div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

/**
 * @typedef {Object} CopywriterCandidate
 * @property {string} text    文案内容
 * @property {number} [score] 评分（0-100）
 */

/**
 * @typedef {Object} CopywriterCompareProps
 * @property {Array<string|CopywriterCandidate>} candidates 候选文案数组
 * @property {number} [modelValue] 当前选中索引，支持 v-model
 */
const props = defineProps({
  candidates: { type: Array, default: () => [] },
  modelValue: { type: Number, default: -1 }
})

const emit = defineEmits(['update:modelValue', 'apply'])

const active = ref(0)

const innerIndex = computed(() => (props.modelValue >= 0 ? props.modelValue : active.value))

watch(active, (v) => {
  if (v !== props.modelValue) emit('update:modelValue', v)
})

watch(() => props.modelValue, (v) => {
  if (typeof v === 'number' && v >= 0 && v !== active.value) active.value = v
})

const onSelect = (idx) => {
  active.value = idx
  emit('update:modelValue', idx)
}

const onApply = (c) => {
  emit('apply', c)
}
</script>

<script>
export default { name: 'CopywriterCompare' }
</script>

<style lang="scss" scoped>
.xmt-copywriter-compare {
  position: relative;
}

.xmt-copy-card {
  margin: 0 24px;
  padding: 16px;
  background: #fff;
  border: 2px solid #f0f0f0;
  border-radius: 12px;
  height: 200px;
  display: flex;
  flex-direction: column;
  transition: border-color 0.2s;

  &.is-active {
    border-color: #FF6B35;
    box-shadow: 0 2px 8px rgba(255, 107, 53, 0.15);
  }
}

.xmt-copy-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.xmt-copy-tag {
  background: rgba(255, 107, 53, 0.1);
  color: #FF6B35;
  font-size: 12px;
  padding: 2px 10px;
  border-radius: 10px;
  font-weight: 600;
}

.xmt-copy-body {
  flex: 1;
  font-size: 14px;
  color: #303133;
  line-height: 1.6;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 5;
  -webkit-box-orient: vertical;
  word-break: break-all;
}

.xmt-copy-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 12px;
}

.xmt-copy-pager {
  text-align: center;
  font-size: 12px;
  color: #909399;
  margin-top: 8px;
}

:deep(.el-carousel__arrow) {
  background: rgba(255, 107, 53, 0.85);
  border: none;
}
</style>
