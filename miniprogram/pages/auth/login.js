const app = getApp();
const authApi = require('../../api/auth');

Page({
  data: {
    isLoading: false
  },

  onLoad(options) {
    if (app.globalData.isLoggedIn) {
      wx.switchTab({ url: '/pages/index/index' });
    }
  },

  async handleWechatLogin() {
    if (this.data.isLoading) return;
    this.setData({ isLoading: true });

    try {
      const loginRes = await wx.login();
      if (!loginRes.code) {
        wx.showToast({ title: '微信登录失败', icon: 'none' });
        return;
      }

      wx.showLoading({ title: '登录中...' });

      const data = await authApi.login(loginRes.code);
      const token = data.token;

      app.globalData.token = token;
      app.globalData.isLoggedIn = true;
      wx.setStorageSync('token', token);

      if (data.user) {
        app.globalData.userInfo = data.user;
        wx.setStorageSync('userInfo', data.user);
      }

      wx.hideLoading();
      wx.showToast({ title: '登录成功', icon: 'success' });

      setTimeout(() => {
        wx.switchTab({ url: '/pages/index/index' });
      }, 1000);
    } catch (err) {
      wx.hideLoading();
      wx.showToast({ title: err.message || '登录失败，请重试', icon: 'none' });
    } finally {
      this.setData({ isLoading: false });
    }
  },

  handleGuestMode() {
    wx.switchTab({ url: '/pages/index/index' });
  }
});
