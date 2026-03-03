import request from '@/utils/request'

// 获取视频库列表
export function getVideoLibraryList(params) {
  return request({
    url: '/video-library/list',
    method: 'get',
    params
  })
}

// 获取分类
export function getVideoCategories() {
  return request({
    url: '/video-library/categories',
    method: 'get'
  })
}

// 获取筛选选项
export function getVideoFilters() {
  return request({
    url: '/video-library/filters',
    method: 'get'
  })
}

// 获取热门模板
export function getHotVideos() {
  return request({
    url: '/video-library/hot',
    method: 'get'
  })
}

// 使用模板
export function useVideoTemplate(id) {
  return request({
    url: `/video-library/use/${id}`,
    method: 'post'
  })
}
