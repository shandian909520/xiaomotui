const contentApi = require('../../api/content');

Page({
  data: {
    mode: 'detail',
    taskId: '',
    contentDetail: null,
    contentList: [],
    isLoading: true,
    isPolling: false,
    statusText: {
      pending: '等待中',
      processing: '生成中',
      completed: '已完成',
      failed: '生成失败'
    }
  },

  onLoad(options) {
    if (options.mode === 'list') {
      this.setData({ mode: 'list' });
      this.loadContentList();
    } else if (options.taskId) {
      this.setData({ taskId: options.taskId });
      this.loadContentDetail(options.taskId);
    }
  },

  async loadContentDetail(taskId) {
    try {
      const data = await contentApi.getContentDetail(taskId);
      this.setData({ contentDetail: data, isLoading: false });

      if (data.status === 'pending' || data.status === 'processing') {
        this.startPolling(taskId);
      }
    } catch (err) {
      this.setData({ isLoading: false });
      wx.showToast({ title: '加载失败', icon: 'none' });
    }
  },

  async loadContentList() {
    try {
      const data = await contentApi.getContentList({ page: 1, page_size: 20 });
      this.setData({
        contentList: data.list || data || [],
        isLoading: false
      });
    } catch (err) {
      this.setData({ isLoading: false });
      wx.showToast({ title: '加载失败', icon: 'none' });
    }
  },

  startPolling(taskId) {
    if (this.data.isPolling) return;
    this.setData({ isPolling: true });
    this._pollCount = 0;
    this._maxPollCount = 100;
    this._pollTimer = setInterval(() => this.pollTaskStatus(taskId), 3000);
  },

  stopPolling() {
    if (this._pollTimer) {
      clearInterval(this._pollTimer);
      this._pollTimer = null;
    }
    this.setData({ isPolling: false });
  },

  async pollTaskStatus(taskId) {
    this._pollCount++;
    if (this._pollCount > this._maxPollCount) {
      this.stopPolling();
      return;
    }
    try {
      const data = await contentApi.getTaskStatus(taskId);
      if (data.status === 'completed' || data.status === 'failed') {
        this.stopPolling();
        this.loadContentDetail(taskId);
      }
    } catch (err) {
      this.stopPolling();
    }
  },

  handleCopyText() {
    var detail = this.data.contentDetail;
    if (!detail || !detail.content) {
      wx.showToast({ title: '暂无内容可复制', icon: 'none' });
      return;
    }
    var text = detail.content.text || detail.content.title || '';
    wx.setClipboardData({
      data: text,
      success: function () {
        wx.showToast({ title: '已复制到剪贴板', icon: 'success' });
      }
    });
  },

  handleShare() {
    wx.showShareMenu({ withShareTicket: true });
  },

  handlePublish() {
    var detail = this.data.contentDetail;
    if (!detail) return;
    wx.navigateTo({
      url: '/pages/publish/index?contentId=' + (detail.id || detail.task_id)
    });
  },

  goToListItem(e) {
    var id = e.currentTarget.dataset.id;
    wx.navigateTo({ url: '/pages/content/detail?taskId=' + id });
  },

  onUnload() {
    this.stopPolling();
  }
});
