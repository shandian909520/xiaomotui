<template>
  <div class="activity-scenes">
    <PageHeader title="场景配置" subtitle="管理和配置营销活动场景">
      <template #extra>
        <el-button type="primary" @click="handleCreate">新增场景</el-button>
      </template>
    </PageHeader>

    <FilterToolbar showSearch showExport @search="handleSearch" @export="handleExport" />

    <div class="scene-list">
      <el-table :data="filteredScenes" stripe>
        <el-table-column prop="name" label="场景名称" />
        <el-table-column prop="type" label="场景类型" width="120">
          <template #default="{ row }">
            <el-tag>{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="storeName" label="适用门店" />
        <el-table-column prop="materialCount" label="素材数量" width="100" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === '启用' ? 'success' : 'info'">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="创建时间" width="120" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" @click="handleToggle(row)">{{ row.status === '启用' ? '禁用' : '启用' }}</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑场景' : '新增场景'" width="500px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="场景名称"><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="场景类型">
          <el-select v-model="form.type">
            <el-option label="NFC触发" value="NFC" />
            <el-option label="海报扫码" value="海报" />
            <el-option label="小程序" value="小程序" />
          </el-select>
        </el-form-item>
        <el-form-item label="适用门店">
          <el-select v-model="form.storeId" placeholder="选择门店">
            <el-option v-for="s in stores" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="活动素材">
          <el-button @click="dialogVisible = false">选择素材</el-button>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { PageHeader, FilterToolbar } from '@/components/xmt'
import { ElMessage, ElMessageBox } from 'element-plus'
import { scenesApi } from '@/api/activity'
import { normalizeListPayload } from '@/utils/responseHelper'

const searchQuery = ref('')
const dialogVisible = ref(false)
const isEdit = ref(false)
const form = ref({ id: null, name: '', type: '', storeId: null })

const stores = ref([{ id: 1, name: '测试门店' }, { id: 2, name: '旗舰店' }])


const scenes = ref([])

const filteredScenes = computed(() => scenes.value.filter(s => !searchQuery.value || s.name.includes(searchQuery.value)))

// 加载场景列表
const loadScenes = async () => {
  try {
    const res = await scenesApi.getScenes()
    const rawList = normalizeListPayload(res)
    // 映射后端 SceneConfig 字段到前端期望的字段
    scenes.value = rawList.map(item => ({
      id: item.id,
      name: item.name || item.storeName || '',
      type: item.type || 'NFC',
      storeName: item.storeName || '',
      storeId: item.storeId || null,
      materialCount: item.materialCount || 0,
      status: item.status === 1 ? '启用' : '禁用',
      createdAt: item.createTime || item.createdAt || ''
    }))
  } catch (error) {
    console.error('获取场景列表失败:', error)
    scenes.value = []
    ElMessage.error('获取场景列表失败，请稍后重试')
  }
}

const handleSearch = (q) => { searchQuery.value = q }
const handleExport = () => {
  if (!filteredScenes.value.length) {
    ElMessage.warning('暂无数据可导出')
    return
  }
  try {
    const columns = [
      { label: '场景名称', key: 'name' },
      { label: '场景类型', key: 'type' },
      { label: '适用门店', key: 'storeName' },
      { label: '素材数量', key: 'materialCount' },
      { label: '状态', key: 'status' },
      { label: '创建时间', key: 'createdAt' }
    ]
    const header = columns.map(c => c.label).join(',')
    const rows = filteredScenes.value.map(row =>
      columns.map(c => {
        let val = String(row[c.key] ?? '')
        if (val.includes(',') || val.includes('"') || val.includes('\n')) {
          val = '"' + val.replace(/"/g, '""') + '"'
        }
        return val
      }).join(',')
    )
    const csv = '\uFEFF' + header + '\n' + rows.join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `场景配置_${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  } catch (e) {
    console.error('导出失败:', e)
    ElMessage.error('导出失败')
  }
}
const handleCreate = () => { isEdit.value = false; form.value = { id: null, name: '', type: '', storeId: null }; dialogVisible.value = true }
const handleEdit = (row) => { isEdit.value = true; form.value = { ...row }; dialogVisible.value = true }
const handleSave = async () => {
  try {
    if (isEdit.value) {
      await scenesApi.updateScene(form.value.id, form.value)
    } else {
      await scenesApi.createScene(form.value)
    }
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadScenes()
  } catch (error) {
    console.error('保存场景失败:', error)
    ElMessage.error('保存失败')
  }
}
const handleToggle = async (row) => {
  const enabled = row.status !== '启用'
  try {
    await scenesApi.toggleScene(row.id, enabled)
    row.status = enabled ? '启用' : '禁用'
    ElMessage.success('状态已更新')
  } catch (error) {
    console.error('切换状态失败:', error)
    ElMessage.error('状态更新失败')
  }
}
const handleDelete = (row) => {
  ElMessageBox.confirm('确定删除该场景？', '提示', { type: 'warning' }).then(async () => {
    try {
      await scenesApi.deleteScene(row.id)
      ElMessage.success('删除成功')
      loadScenes()
    } catch (error) {
      console.error('删除场景失败:', error)
      ElMessage.error('删除失败')
    }
  })
}

onMounted(() => {
  loadScenes()
})
</script>

<style scoped lang="scss">
.activity-scenes { padding: 20px; }
.scene-list { background: #fff; border-radius: 14px; padding: 16px; margin-top: 16px; }
</style>
