import request from '@/utils/request'

// 获取素材列表
export function getMaterialsList(params) {
  return request.get('/admin/materials', { params })
}

// 上传素材
export function uploadMaterial(data) {
  return request.post('/admin/materials/upload', data)
}

// 更新素材
export function updateMaterial(id, data) {
  return request.put(`/admin/materials/${id}`, data)
}

// 删除素材
export function deleteMaterial(id) {
  return request.delete(`/admin/materials/${id}`)
}

// 批量删除素材
export function batchDeleteMaterials(ids) {
  return request.delete('/admin/materials/batch', { data: { ids } })
}

// 获取存储空间信息
export function getStorageInfo() {
  return request.get('/admin/materials/storage')
}

// 获取门店列表
export function getStoreList() {
  return request.get('/admin/stores/simple')
}

// 素材文件夹
export const getMaterialFolders = (params) => request.get('/material/folders', { params })
export const createMaterialFolder = (data) => request.post('/material/folder-create', data)
export const renameMaterialFolder = (data) => request.post('/material/folder-rename', data)
export const deleteMaterialFolder = (data) => request.post('/material/folder-delete', data)

// 素材操作
export const getMaterialList = (params) => request.get('/material/list', { params })
export const moveMaterial = (data) => request.post('/material/move', data)
export const batchDeleteMaterial = (data) => request.post('/material/batch-delete', data)
export const softDeleteMaterial = (data) => request.post('/material/soft-delete', data)
export const getMaterialTrash = (params) => request.get('/material/trash', { params })
export const restoreMaterial = (data) => request.post('/material/restore', data)
export const permanentDeleteMaterial = (data) => request.post('/material/permanent-delete', data)
