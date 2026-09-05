import request from '@/utils/request'

export function getRecommendationList(params) {
  return request({
    url: '/recommendation/list',
    method: 'get',
    params
  })
}

export function getRecommendationBatch(data) {
  return request({
    url: '/recommendation/batch',
    method: 'post',
    data
  })
}

export function getUserProfile(params) {
  return request({
    url: '/recommendation/profile',
    method: 'get',
    params
  })
}

export function getTemplateSimilarity(params) {
  return request({
    url: '/recommendation/similarity',
    method: 'get',
    params
  })
}

export function getUserSimilarity(params) {
  return request({
    url: '/recommendation/user-similarity',
    method: 'get',
    params
  })
}

export function getEvaluation(params) {
  return request({
    url: '/recommendation/evaluation',
    method: 'get',
    params
  })
}

export function getAlgorithmComparison(params) {
  return request({
    url: '/recommendation/algorithm-comparison',
    method: 'get',
    params
  })
}

export function getAbTestAnalysis(params) {
  return request({
    url: '/recommendation/ab-test',
    method: 'get',
    params
  })
}

export function getCoverage(params) {
  return request({
    url: '/recommendation/coverage',
    method: 'get',
    params
  })
}

export function getCacheStats() {
  return request({
    url: '/recommendation/cache-stats',
    method: 'get'
  })
}

export function clearCache(data) {
  return request({
    url: '/recommendation/clear-cache',
    method: 'post',
    data
  })
}

export function trackBehavior(data) {
  return request({
    url: '/recommendation/track',
    method: 'post',
    data
  })
}

export function getConfig() {
  return request({
    url: '/recommendation/config',
    method: 'get'
  })
}

export function updateConfig(data) {
  return request({
    url: '/recommendation/config',
    method: 'post',
    data
  })
}

export function resetConfig() {
  return request({
    url: '/recommendation/config/reset',
    method: 'post'
  })
}

export function getExperiments(params) {
  return request({
    url: '/recommendation/experiments',
    method: 'get',
    params
  })
}

export function createExperiment(data) {
  return request({
    url: '/recommendation/experiments',
    method: 'post',
    data
  })
}

export function stopExperiment(id) {
  return request({
    url: `/recommendation/experiments/${id}/stop`,
    method: 'post'
  })
}
