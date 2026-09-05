/**
 * 文案池 API(顾客端,轮换文案)
 * ⚠️ 后端 CopywritingPool Controller 尚未实现(留给 Agent C)
 * 本模块当前调用会 404,需等 Agent C 完成 Controller 后才能联通
 */

import request from '../request.js'

export default {
	/**
	 * 轮换文案(device_id + rotate=1 随机返回一条)
	 * @param {Object} params {device_id, rotate, scene?}
	 * @returns {Promise}
	 */
	rotateCopywriting(params = {}) {
		// TODO 留给 Agent C: 等 CopywritingPool Controller 建好后会注册
		// Route::get('copywriting/rotate', '\app\controller\CopywritingPool@rotate');
		return request.get('/api/copywriting/rotate', params)
	}
}