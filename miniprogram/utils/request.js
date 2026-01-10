const app = getApp();

// 请求拦截器
function requestInterceptor(config) {
  // 添加token
  if (app.globalData.token) {
    config.header = config.header || {};
    config.header['Authorization'] = `Bearer ${app.globalData.token}`;
  }
  return config;
}

// 响应拦截器
function responseInterceptor(response) {
  const { statusCode, data } = response;

  if (statusCode === 200) {
    if (data.code === 200) {
      return Promise.resolve(data.data);
    } else if (data.code === 401) {
      // token过期，跳转登录
      wx.removeStorageSync('token');
      wx.redirectTo({
        url: '/pages/auth/login'
      });
      return Promise.reject(data);
    } else {
      // 其他错误
      wx.showToast({
        title: data.message || '请求失败',
        icon: 'none'
      });
      return Promise.reject(data);
    }
  } else {
    wx.showToast({
      title: '网络请求失败',
      icon: 'none'
    });
    return Promise.reject(response);
  }
}

// 封装的request方法
function request(options) {
  // 应用请求拦截器
  options = requestInterceptor(options);

  // 完整的URL
  options.url = app.globalData.apiBaseUrl + options.url;

  // 默认超时时间
  options.timeout = options.timeout || 10000;

  return new Promise((resolve, reject) => {
    wx.request({
      ...options,
      success: (res) => {
        responseInterceptor(res).then(resolve).catch(reject);
      },
      fail: (err) => {
        wx.showToast({
          title: '网络连接失败',
          icon: 'none'
        });
        reject(err);
      }
    });
  });
}

module.exports = {
  get: (url, data, options = {}) => {
    return request({
      url,
      data,
      method: 'GET',
      ...options
    });
  },

  post: (url, data, options = {}) => {
    return request({
      url,
      data,
      method: 'POST',
      ...options
    });
  },

  put: (url, data, options = {}) => {
    return request({
      url,
      data,
      method: 'PUT',
      ...options
    });
  },

  delete: (url, data, options = {}) => {
    return request({
      url,
      data,
      method: 'DELETE',
      ...options
    });
  }
};
