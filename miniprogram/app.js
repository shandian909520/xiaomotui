// 环境配置：根据小程序运行环境自动切换 API 地址
// envVersion: 'develop'(开发版) / 'trial'(体验版) / 'release'(正式版)
const ENV_CONFIG = {
  develop: {
    apiBaseUrl: 'http://localhost:37080',
    debug: true
  },
  trial: {
    apiBaseUrl: 'https://pengh5.moban8.top',
    debug: true
  },
  release: {
    apiBaseUrl: 'https://pengh5.moban8.top',
    debug: false
  }
};

function detectEnv() {
  try {
    const info = wx.getAccountInfoSync();
    const env = info && info.miniProgram && info.miniProgram.envVersion;
    if (env && ENV_CONFIG[env]) return env;
  } catch (e) {
    console.warn('环境检测失败，默认使用 release', e);
  }
  return 'release';
}

const currentEnv = detectEnv();
const currentConfig = ENV_CONFIG[currentEnv];

App({
  globalData: {
    userInfo: null,
    token: null,
    apiBaseUrl: currentConfig.apiBaseUrl,
    env: currentEnv,
    debug: currentConfig.debug,
    isLoggedIn: false
  },

  onLaunch(options) {
    // 小程序启动时执行
    console.log('小程序启动', options, '环境:', currentEnv);

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

  // 验证token有效性
  validateToken() {
    const token = this.globalData.token;
    if (!token) {
      this.clearLoginState();
      return;
    }

    // 简单检查JWT格式和过期时间（本地预检）
    try {
      const parts = token.split('.');
      if (parts.length !== 3) {
        this.clearLoginState();
        return;
      }

      const payload = JSON.parse(
        decodeURIComponent(
          Array.from(atob(parts[1]), c =>
            '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
          ).join('')
        )
      );

      // 检查是否过期
      if (payload.exp && payload.exp * 1000 < Date.now()) {
        console.log('Token已过期，尝试刷新');
        this.refreshToken();
        return;
      }
    } catch (e) {
      console.warn('Token解析失败，将通过API验证', e);
    }

    // 调用后端API验证token有效性
    wx.request({
      url: this.globalData.apiBaseUrl + '/api/auth/info',
      method: 'GET',
      header: {
        'Authorization': 'Bearer ' + token
      },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          const userInfo = res.data.data;
          this.globalData.userInfo = userInfo;
          this.globalData.isLoggedIn = true;
          wx.setStorageSync('userInfo', userInfo);
        } else if (res.statusCode === 401) {
          console.log('Token无效，尝试刷新');
          this.refreshToken();
        }
      },
      fail: () => {
        console.warn('Token验证请求失败，保持当前登录状态');
      }
    });
  },

  // 刷新Token
  refreshToken() {
    const token = this.globalData.token;
    if (!token) {
      this.clearLoginState();
      return;
    }

    wx.request({
      url: this.globalData.apiBaseUrl + '/api/auth/refresh',
      method: 'POST',
      header: {
        'Authorization': 'Bearer ' + token
      },
      success: (res) => {
        if (res.statusCode === 200 && res.data && res.data.code === 200) {
          const data = res.data.data;
          if (data && data.token) {
            this.globalData.token = data.token;
            wx.setStorageSync('token', data.token);
            console.log('Token刷新成功');
          }
        } else {
          this.clearLoginState();
        }
      },
      fail: () => {
        this.clearLoginState();
      }
    });
  },

  // 清除登录状态
  clearLoginState() {
    this.globalData.token = null;
    this.globalData.isLoggedIn = false;
    this.globalData.userInfo = null;
    wx.removeStorageSync('token');
    wx.removeStorageSync('userInfo');
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
