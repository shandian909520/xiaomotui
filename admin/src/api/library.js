// 成品库接口
import request from '@/utils/request'

// 获取视频库列表
export const getVideos = (params) => {
  return request.get('/admin/library/videos', { params })
}

// 获取图文库列表
export const getImages = (params) => {
  return request.get('/admin/library/images', { params })
}

// 获取话题库列表
export const getTopics = (params) => {
  return request.get('/admin/library/topics', { params })
}

// 获取门店列表（用于筛选）
export const getStores = (params) => {
  return request.get('/admin/library/stores', { params })
}

// 获取平台列表（用于筛选）
export const getPlatforms = () => {
  return request.get('/admin/library/platforms')
}

// 删除视频
export const deleteVideo = (id) => {
  return request.delete(`/admin/library/videos/${id}`)
}

// 删除图文
export const deleteImage = (id) => {
  return request.delete(`/admin/library/images/${id}`)
}

// 删除话题
export const deleteTopic = (id) => {
  return request.delete(`/admin/library/topics/${id}`)
}

export default {
  getVideos,
  getImages,
  getTopics,
  getStores,
  getPlatforms,
  deleteVideo,
  deleteImage,
  deleteTopic
}
