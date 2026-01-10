/**
 * 本地存储工具类
 */

/**
 * 设置存储
 * @param {string} key - 键名
 * @param {*} value - 值
 */
function setStorage(key, value) {
  try {
    wx.setStorageSync(key, value);
    return true;
  } catch (err) {
    console.error('设置存储失败:', err);
    return false;
  }
}

/**
 * 获取存储
 * @param {string} key - 键名
 * @param {*} defaultValue - 默认值
 */
function getStorage(key, defaultValue = null) {
  try {
    const value = wx.getStorageSync(key);
    return value !== '' ? value : defaultValue;
  } catch (err) {
    console.error('获取存储失败:', err);
    return defaultValue;
  }
}

/**
 * 移除存储
 * @param {string} key - 键名
 */
function removeStorage(key) {
  try {
    wx.removeStorageSync(key);
    return true;
  } catch (err) {
    console.error('移除存储失败:', err);
    return false;
  }
}

/**
 * 清空所有存储
 */
function clearStorage() {
  try {
    wx.clearStorageSync();
    return true;
  } catch (err) {
    console.error('清空存储失败:', err);
    return false;
  }
}

/**
 * 获取存储信息
 */
function getStorageInfo() {
  try {
    return wx.getStorageInfoSync();
  } catch (err) {
    console.error('获取存储信息失败:', err);
    return null;
  }
}

module.exports = {
  setStorage,
  getStorage,
  removeStorage,
  clearStorage,
  getStorageInfo
};
