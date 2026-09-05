import request from '@/utils/request'

export function getBlacklistList(params) {
  return request({
    url: '/admin/blacklist/list',
    method: 'get',
    params
  })
}

export function getBlacklistOverview() {
  return request({
    url: '/admin/blacklist/overview',
    method: 'get'
  })
}

export function checkIpStatus(params) {
  return request({
    url: '/admin/blacklist/check',
    method: 'get',
    params
  })
}

export function getIpStats(params) {
  return request({
    url: '/admin/blacklist/stats',
    method: 'get',
    params
  })
}

export function exportBlacklist(params) {
  return request({
    url: '/admin/blacklist/export',
    method: 'get',
    params
  })
}

export function addBlacklist(data) {
  return request({
    url: '/admin/blacklist/add',
    method: 'post',
    data
  })
}

export function batchAddBlacklist(data) {
  return request({
    url: '/admin/blacklist/batch-add',
    method: 'post',
    data
  })
}

export function removeBlacklist(data) {
  return request({
    url: '/admin/blacklist/remove',
    method: 'post',
    data
  })
}

export function batchRemoveBlacklist(data) {
  return request({
    url: '/admin/blacklist/batch-remove',
    method: 'post',
    data
  })
}

export function clearBlacklist(data) {
  return request({
    url: '/admin/blacklist/clear',
    method: 'post',
    data
  })
}
