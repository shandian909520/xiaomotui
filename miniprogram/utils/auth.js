const request = require('./request');

/**
 * 微信登录
 */
function wxLogin() {
  return new Promise((resolve, reject) => {
    wx.login({
      success: (res) => {
        if (res.code) {
          // 发送code到后端换取token
          request.post('/api/auth/login', {
            code: res.code
          }).then(data => {
            // 保存token
            wx.setStorageSync('token', data.token);
            getApp().globalData.token = data.token;
            getApp().globalData.isLoggedIn = true;
            getApp().globalData.userInfo = data.user;
            resolve(data);
          }).catch(reject);
        } else {
          reject(new Error('登录失败'));
        }
      },
      fail: reject
    });
  });
}

/**
 * 检查登录状态
 * 先检查本地token是否存在且未过期
 */
function checkLogin() {
  const token = wx.getStorageSync('token');
  if (!token) return false;

  // 简单检查JWT是否过期
  try {
    const parts = token.split('.');
    if (parts.length !== 3) return false;

    const payload = JSON.parse(
      decodeURIComponent(
        Array.from(atob(parts[1]), c =>
          '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
        ).join('')
      )
    );

    if (payload.exp && payload.exp * 1000 < Date.now()) {
      return false;
    }

    return true;
  } catch (e) {
    // 解析失败，仍返回true，由后续API调用处理401
    return true;
  }
}

/**
 * 退出登录
 */
function logout() {
  wx.removeStorageSync('token');
  getApp().globalData.token = null;
  getApp().globalData.isLoggedIn = false;
  getApp().globalData.userInfo = null;

  wx.redirectTo({
    url: '/pages/auth/login'
  });
}

module.exports = {
  wxLogin,
  checkLogin,
  logout
};
