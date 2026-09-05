import request from '@/utils/request'

export const designApi = {
  getMaterials(params) {
    return request.get('/admin/design/materials', { params })
  },

  getMaterial(id) {
    return request.get(`/admin/design/materials/${id}`)
  },

  getSceneList(params) {
    return request.get('/design-scene/list', { params })
  },

  getSceneDetail(sceneKey) {
    return request.get('/design-scene/detail', { params: { scene_key: sceneKey } })
  },

  getSceneTemplates(params) {
    return request.get('/design-scene/templates', { params })
  },

  preview(data) {
    return request.post('/design-scene/preview', data)
  },

  generate(data) {
    return request.post('/design-scene/generate', data)
  }
}

export default designApi
