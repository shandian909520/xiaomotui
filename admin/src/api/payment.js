import request from '@/utils/request'

/**
 * 创建支付订单
 * @param {Object} data
 * @param {string} data.package_type - 套餐类型 basic/standard/chain
 * @param {number} data.duration - 订阅时长(月) 1/3/6/12
 * @param {string} [data.pay_method] - 支付方式 wechat/alipay
 * @returns {Promise}
 */
export function createOrder(data) {
  return request({
    url: '/payment/create-order',
    method: 'post',
    data
  })
}

/**
 * 发起微信支付
 * @param {Object} data
 * @param {string} data.order_no - 订单号
 * @param {string} [data.openid] - 用户openid(JSAPI支付必填)
 * @param {string} [data.trade_type] - 支付类型 JSAPI/H5/NATIVE
 * @returns {Promise}
 */
export function wechatPay(data) {
  return request({
    url: '/payment/wechat-pay',
    method: 'post',
    data
  })
}

/**
 * 查询订单详情
 * @param {number} id - 订单ID
 * @returns {Promise}
 */
export function queryOrder(id) {
  return request({
    url: `/payment/order/${id}`,
    method: 'get'
  })
}

/**
 * 获取订单列表
 * @param {Object} params
 * @param {number} [params.page] - 页码
 * @param {number} [params.page_size] - 每页数量
 * @returns {Promise}
 */
export function getOrderList(params) {
  return request({
    url: '/payment/orders',
    method: 'get',
    params
  })
}

/**
 * 获取套餐列表
 * @returns {Promise}
 */
export function getPackages() {
  return request({
    url: '/payment/packages',
    method: 'get'
  })
}

/**
 * 生成卡密（管理员）
 * @param {Object} data
 * @param {string} data.type - 卡密类型
 * @param {Object} [data.benefit_payload] - 权益内容
 * @param {string} [data.expire_at] - 过期时间
 * @returns {Promise}
 */
export function generateCardKey(data) {
  return request({
    url: '/admin/cardkey/generate',
    method: 'post',
    data
  })
}

/**
 * 获取卡密列表（管理员）
 * @param {Object} params
 * @param {number} [params.page] - 页码
 * @param {number} [params.page_size] - 每页数量
 * @param {string} [params.status] - 状态筛选
 * @returns {Promise}
 */
export function getCardKeyList(params) {
  return request({
    url: '/admin/cardkey/list',
    method: 'get',
    params
  })
}
