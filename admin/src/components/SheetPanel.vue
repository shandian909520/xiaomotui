<!--
  SheetPanel 底部抽屉组件（视频里 Wi-Fi/团购/点评/抽奖/加微 全是底部抽屉）
  作用：替代 modal，提供更接近手机 native 的从底部弹起的交互体验
-->
<template>
  <el-dialog
    :model-value="visible"
    :title="title"
    direction="btt"
    :show-close="true"
    :close-on-click-modal="true"
    :width="width"
    :style="rootStyle"
    custom-class="xmt-sheet-panel"
    @update:model-value="(v) => emit('update:visible', v)"
  >
    <div class="xmt-sheet-body">
      <slot />
    </div>
    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </el-dialog>
</template>

<script setup>
import { computed } from 'vue'

/**
 * @typedef {Object} SheetPanelProps
 * @property {string} title       抽屉标题
 * @property {boolean} visible    是否显示，支持 v-model:visible
 * @property {string} [height]    内容高度，如 '60vh'，默认 60vh
 * @property {string} [width]     抽屉宽度，默认 '92%'
 */
const props = defineProps({
  title: { type: String, required: true },
  visible: { type: Boolean, default: false },
  height: { type: String, default: '60vh' },
  width: { type: String, default: '92%' }
})

const emit = defineEmits(['update:visible', 'close'])

const rootStyle = computed(() => ({
  '--xmt-sheet-height': props.height
}))
</script>

<script>
export default { name: 'SheetPanel' }
</script>

<style lang="scss">
.xmt-sheet-panel {
  border-radius: 16px 16px 0 0 !important;
  overflow: hidden;

  &.el-dialog {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    height: var(--xmt-sheet-height, 60vh);
    max-height: 80vh;
  }

  .el-dialog__header {
    padding: 16px 20px 12px;
    border-bottom: 1px solid #f0f0f0;
    text-align: center;

    .el-dialog__title {
      font-size: 16px;
      font-weight: 600;
      color: #303133;
    }
  }

  .el-dialog__body {
    padding: 16px 20px;
    overflow-y: auto;
    max-height: calc(80vh - 100px);
  }

  .xmt-sheet-body {
    min-height: 80px;
  }
}
</style>
