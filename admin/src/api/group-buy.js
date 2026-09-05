import request from '@/utils/request'

export function getGroupBuyStatistics(params) {
  return request({
    url: '/group-buy/statistics',
    method: 'get',
    params
  })
}

export function getGroupBuyConfig(deviceId) {
  return request({
    url: `/nfc/device/${deviceId}/group-buy`,
    method: 'get'
  })
}

export function configureGroupBuy(deviceId, data) {
  return request({
    url: `/nfc/device/${deviceId}/group-buy`,
    method: 'put',
    data
  })
}
