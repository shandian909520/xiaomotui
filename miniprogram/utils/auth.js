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
 */
function checkLogin() {
  const token = wx.getStorageSync('token');
  return !!token;
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
