const app = getApp();

Page({
  data: {
    userInfo: null,
    hasUserInfo: false,
    canIUseNFC: false
  },

  onLoad() {
    // 检查NFC支持
    this.checkNFCSupport();

    // 获取用户信息
    if (app.globalData.userInfo) {
      this.setData({
        userInfo: app.globalData.userInfo,
        hasUserInfo: true
      });
    }
  },

  // 检查NFC支持
  checkNFCSupport() {
    if (wx.getHCEState) {
      wx.getHCEState({
        success: (res) => {
          this.setData({
            canIUseNFC: true
          });
        },
        fail: () => {
          this.setData({
            canIUseNFC: false
          });
        }
      });
    }
  },

  // 跳转到NFC触发页面
  goToNFC() {
    if (!this.data.canIUseNFC) {
      wx.showToast({
        title: '您的设备不支持NFC',
        icon: 'none'
      });
      return;
    }

    wx.navigateTo({
      url: '/pages/nfc/trigger'
    });
  }
});
