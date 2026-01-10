/**
 * 初始化NFC
 */
function initNFC() {
  return new Promise((resolve, reject) => {
    wx.getHCEState({
      success: (res) => {
        console.log('NFC状态:', res);
        resolve(res);
      },
      fail: (err) => {
        console.error('获取NFC状态失败:', err);
        reject(err);
      }
    });
  });
}

/**
 * 开始NFC扫描
 */
function startNFCScan(callback) {
  wx.startHCE({
    success: (res) => {
      console.log('开始NFC扫描', res);
      // 监听NFC消息
      wx.onHCEMessage((res) => {
        console.log('收到NFC消息', res);
        if (callback) callback(res);
      });
    },
    fail: (err) => {
      console.error('开始NFC扫描失败', err);
      wx.showToast({
        title: '请检查NFC是否开启',
        icon: 'none'
      });
    }
  });
}

/**
 * 停止NFC扫描
 */
function stopNFCScan() {
  wx.stopHCE({
    success: (res) => {
      console.log('停止NFC扫描', res);
    },
    fail: (err) => {
      console.error('停止NFC扫描失败', err);
    }
  });
}

module.exports = {
  initNFC,
  startNFCScan,
  stopNFCScan
};
