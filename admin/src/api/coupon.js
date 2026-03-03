import request from '@/utils/request'

export function getCouponList(params) {
  return request({
    url: '/merchant/coupon/list',
    method: 'get',
    params
  })
}

export function createCoupon(data) {
  return request({
    url: '/merchant/coupon/create',
    method: 'post',
    data
  })
}

export function updateCoupon(data) {
  return request({
    url: `/merchant/coupon/${data.id}`,
    method: 'put',
    data
  })
}

export function deleteCoupon(id) {
  return request({
    url: `/merchant/coupon/${id}`,
    method: 'delete'
  })
}
