import request from '@/utils/request'

export function getViolationHistory(params) {
  return request({
    url: '/violation/history',
    method: 'get',
    params
  })
}

export function reviewViolation(id, data) {
  return request({
    url: `/admin/violation/${id}/review`,
    method: 'put',
    data
  })
}

export function getViolationStatistics(params) {
  return request({
    url: '/violation/statistics',
    method: 'get',
    params
  })
}

export function batchDisableViolation(data) {
  return request({
    url: '/admin/violation/batch-disable',
    method: 'post',
    data
  })
}

export function getPendingAppeals(params) {
  return request({
    url: '/admin/appeals/pending',
    method: 'get',
    params
  })
}

export function processAppeal(id, data) {
  return request({
    url: `/admin/appeal/${id}/process`,
    method: 'put',
    data
  })
}
