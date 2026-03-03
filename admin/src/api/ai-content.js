import request from '@/utils/request'

// AI文案生成
export function generateText(data) {
  return request({
    url: '/ai-content/generate-text',
    method: 'post',
    data
  })
}

// 批量AI文案生成
export function batchGenerateText(data) {
  return request({
    url: '/ai-content/batch-generate-text',
    method: 'post',
    data
  })
}

// 获取创作历史
export function getCreationHistory(params) {
  return request({
    url: '/ai-content/history',
    method: 'get',
    params
  })
}
