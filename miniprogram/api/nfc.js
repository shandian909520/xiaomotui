const request = require('../utils/request');

/**
 * 触发NFC设备
 * @param {string} deviceCode - 设备编码
 */
function triggerDevice(deviceCode, userLocation = null) {
  const data = { device_code: deviceCode };
  if (userLocation) {
    data.user_location = userLocation;
  }
  return request.post('/api/nfc/trigger', data);
}

/**
 * 获取设备信息
 * @param {string} deviceCode - 设备编码
 */
function getDeviceInfo(deviceCode) {
  return request.get('/api/nfc/device/config', { device_code: deviceCode });
}

/**
 * 获取设备列表
 * @param {object} params - 查询参数
 */
function getDeviceList(params) {
  return request.get('/api/nfc/devices', params);
}

module.exports = {
  triggerDevice,
  getDeviceInfo,
  getDeviceList
};
