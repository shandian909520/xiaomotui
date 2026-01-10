/**
 * API统一导出
 * 提供统一的API访问入口
 */

import auth from './modules/auth.js'
import nfc from './modules/nfc.js'
import content from './modules/content.js'
import publish from './modules/publish.js'
import material from './modules/material.js'
import merchant from './modules/merchant.js'
import statistics from './modules/statistics.js'
import user from './modules/user.js'
import ai from './modules/ai.js'
import alert from './modules/alert.js'
import coupon from './modules/coupon.js'
import groupbuy from './modules/groupbuy.js'
import table from './modules/table.js'
import dining from './modules/dining.js'
import template from './modules/template.js'
import request from './request.js'

// 导出所有API模块
export default {
	auth,        // 认证模块
	nfc,         // NFC模块
	content,     // 内容生成模块
	publish,     // 发布管理模块
	material,    // 素材管理模块
	merchant,    // 商家管理模块
	statistics,  // 数据统计模块
	user,        // 用户管理模块
	ai,          // AI服务模块
	alert,       // 告警系统模块
	coupon,      // 优惠券模块
	groupbuy,    // 团购模块
	table,       // 餐桌管理模块
	dining,      // 就餐服务模块
	template,    // 模板管理模块
	request      // 请求实例（用于自定义请求）
}

// 也可以单独导出各个模块，方便按需引入
export {
	auth,
	nfc,
	content,
	publish,
	material,
	merchant,
	statistics,
	user,
	ai,
	alert,
	coupon,
	groupbuy,
	table,
	dining,
	template,
	request
}
