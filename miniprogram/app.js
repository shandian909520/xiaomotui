App({
  globalData: {
    userInfo: null,
    token: null,
    apiBaseUrl: 'http://localhost:37080',  // API基础地址（开发环境）
    isLoggedIn: false
  },

  onLaunch(options) {
    // 小程序启动时执行
    console.log('小程序启动', options);

    // 检查登录状态
    this.checkLoginStatus();

    // 检查更新
    this.checkUpdate();
  },

  onShow(options) {
    // 小程序显示时执行
    console.log('小程序显示', options);
  },

  onHide() {
    // 小程序隐藏时执行
    console.log('小程序隐藏');
  },

  // 检查登录状态
  checkLoginStatus() {
    const token = wx.getStorageSync('token');
    if (token) {
      this.globalData.token = token;
      this.globalData.isLoggedIn = true;
      // 验证token有效性
      this.validateToken();
    }
  },

  // 验证token
  validateToken() {
    // TODO: 调用API验证token
  },

  // 检查更新
  checkUpdate() {
    if (wx.canIUse('getUpdateManager')) {
      const updateManager = wx.getUpdateManager();
      updateManager.onUpdateReady(() => {
        wx.showModal({
          title: '更新提示',
          content: '新版本已准备好，是否重启应用？',
          success: (res) => {
            if (res.confirm) {
              updateManager.applyUpdate();
            }
          }
        });
      });
    }
  }
});
