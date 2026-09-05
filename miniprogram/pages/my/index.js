var app = getApp();
var userApi = require('../../api/user');
var authApi = require('../../api/auth');

Page({
  data: {
    userInfo: null,
    stats: {
      publish_count: 0,
      reach_count: 0,
      fans_count: 0
    },
    isLoading: true,
    menuList: [
      { key: 'content', name: '我的内容', icon: '/images/menu-content.png' },
      { key: 'coupon', name: '我的优惠券', icon: '/images/menu-coupon.png' },
      { key: 'device', name: '我的设备', icon: '/images/menu-device.png' },
      { key: 'notify', name: '消息通知', icon: '/images/menu-notify.png' },
      { key: 'settings', name: '设置', icon: '/images/menu-settings.png' }
    ]
  },

  onLoad() {
    this.loadUserData();
  },

  onShow() {
    this.refreshUserInfo();
  },

  onPullDownRefresh() {
    this.loadUserData().then(function () {
      wx.stopPullDownRefresh();
    });
  },

  async loadUserData() {
    try {
      var results = await Promise.all([
        userApi.getUserInfo().catch(function () { return null; }),
        userApi.getUserStats().catch(function () { return null; })
      ]);

      var userInfo = results[0] || app.globalData.userInfo;
      var stats = results[1] || this.data.stats;

      if (userInfo) {
        app.globalData.userInfo = userInfo;
        wx.setStorageSync('userInfo', userInfo);
      }

      this.setData({
        userInfo: userInfo,
        stats: stats,
        isLoading: false
      });
    } catch (err) {
      this.setData({ isLoading: false });
    }
  },

  refreshUserInfo() {
    var userInfo = app.globalData.userInfo || wx.getStorageSync('userInfo');
    if (userInfo) {
      this.setData({ userInfo: userInfo });
    }
  },

  async handleGetUserProfile() {
    try {
      var userInfo = Object.assign({}, app.globalData.userInfo || {});

      var nicknameRes = await new Promise(function (resolve) {
        wx.showModal({
          title: '设置昵称',
          editable: true,
          placeholderText: '请输入昵称',
          content: userInfo.nickname || '',
          success: resolve
        });
      });

      if (nicknameRes.confirm && nicknameRes.content) {
        userInfo.nickname = nicknameRes.content;
      }

      await userApi.updateUserInfo({
        nickname: userInfo.nickname || '',
        avatar: userInfo.avatar || ''
      });

      app.globalData.userInfo = userInfo;
      wx.setStorageSync('userInfo', userInfo);
      this.setData({ userInfo: userInfo });
    } catch (err) {
      // 用户取消或操作失败
    }
  },

  handleMenuItem(e) {
    var key = e.currentTarget.dataset.key;
    var routes = {
      content: '/pages/content/detail?mode=list',
      coupon: '/pages/my/coupon',
      device: '/pages/my/device',
      notify: '/pages/my/notify',
      settings: '/pages/my/settings'
    };

    var url = routes[key];
    if (url) {
      wx.navigateTo({
        url: url,
        fail: function () {
          wx.showToast({ title: '功能开发中', icon: 'none' });
        }
      });
    }
  },

  handleLogout() {
    wx.showModal({
      title: '确认退出',
      content: '确定要退出登录吗？',
      success: async function (res) {
        if (res.confirm) {
          try {
            await authApi.logout();
          } catch (err) {
            // 静默处理
          }
          app.clearLoginState();
          wx.redirectTo({ url: '/pages/auth/login' });
        }
      }
    });
  }
});
