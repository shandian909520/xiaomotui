import request from '@/utils/request'

// 获取门店列表
export function getStores(params) {
  return request.get('/admin/stores', { params })
}

// 获取门店详情
export function getStoreDetail(id) {
  return request.get(`/admin/stores/${id}`)
}

// 创建门店
export function createStore(data) {
  return request.post('/admin/stores', data)
}

// 更新门店
export function updateStore(id, data) {
  return request.put(`/admin/stores/${id}`, data)
}

// 删除门店
export function deleteStore(id) {
  return request.delete(`/admin/stores/${id}`)
}
