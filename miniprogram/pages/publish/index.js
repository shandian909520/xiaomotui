const publishApi = require('../../api/publish');

Page({
  data: {
    platforms: [],
    selectedPlatforms: [],
    publishList: [],
    form: {
      title: '',
      tags: '',
      scheduled_time: ''
    },
    contentId: '',
    isPublishing: false,
    isLoading: true,
    activeTab: 'publish',
    page: 1,
    hasMore: true
  },

  onLoad(options) {
    if (options.contentId) {
      this.setData({ contentId: options.contentId });
    }
    this.loadPlatforms();
    this.loadPublishList();
  },

  onPullDownRefresh() {
    this.setData({ page: 1, hasMore: true });
    Promise.all([
      this.loadPlatforms(),
      this.loadPublishList()
    ]).then(function () {
      wx.stopPullDownRefresh();
    });
  },

  async loadPlatforms() {
    try {
      var data = await publishApi.getPlatformAccounts();
      this.setData({
        platforms: data.list || data || [],
        isLoading: false
      });
    } catch (err) {
      this.setData({ isLoading: false });
    }
  },

  async loadPublishList() {
    try {
      var data = await publishApi.getPublishList({
        page: this.data.page,
        page_size: 10
      });
      var list = data.list || data || [];
      this.setData({
        publishList: this.data.page === 1 ? list : this.data.publishList.concat(list),
        hasMore: list.length >= 10
      });
    } catch (err) {
      // 静默处理
    }
  },

  switchTab(e) {
    this.setData({ activeTab: e.currentTarget.dataset.tab });
  },

  togglePlatform(e) {
    var id = e.currentTarget.dataset.id;
    var selected = [...this.data.selectedPlatforms];
    var idx = selected.indexOf(id);
    if (idx > -1) {
      selected.splice(idx, 1);
    } else {
      selected.push(id);
    }
    this.setData({ selectedPlatforms: selected });
  },

  async handleAuthorize(e) {
    var platform = e.currentTarget.dataset.platform;
    try {
      wx.showLoading({ title: '授权中...' });
      await publishApi.authorizePlatform({ platform: platform });
      wx.hideLoading();
      wx.showToast({ title: '授权成功', icon: 'success' });
      this.loadPlatforms();
    } catch (err) {
      wx.hideLoading();
      wx.showToast({ title: err.message || '授权失败', icon: 'none' });
    }
  },

  handleInputChange(e) {
    var field = e.currentTarget.dataset.field;
    this.setData({ ['form.' + field]: e.detail.value });
  },

  bindTimeChange(e) {
    this.setData({ 'form.scheduled_time': e.detail.value });
  },

  async handlePublish() {
    var selectedPlatforms = this.data.selectedPlatforms;
    if (selectedPlatforms.length === 0) {
      wx.showToast({ title: '请选择发布平台', icon: 'none' });
      return;
    }

    this.setData({ isPublishing: true });
    wx.showLoading({ title: '发布中...', mask: true });

    try {
      var params = {
        platform_ids: selectedPlatforms,
        title: this.data.form.title,
        tags: this.data.form.tags
      };

      if (this.data.contentId) {
        params.content_id = this.data.contentId;
      }

      if (this.data.form.scheduled_time) {
        params.scheduled_time = this.data.form.scheduled_time;
      }

      await publishApi.publishContent(params);
      wx.hideLoading();
      wx.showToast({ title: '发布成功', icon: 'success' });

      this.setData({
        selectedPlatforms: [],
        form: { title: '', tags: '', scheduled_time: '' },
        page: 1
      });
      this.loadPublishList();
    } catch (err) {
      wx.hideLoading();
      wx.showToast({ title: err.message || '发布失败', icon: 'none' });
    } finally {
      this.setData({ isPublishing: false });
    }
  },

  async handleCancel(e) {
    var taskId = e.currentTarget.dataset.id;
    wx.showModal({
      title: '确认取消',
      content: '确定要取消这个发布任务吗？',
      success: async function (res) {
        if (res.confirm) {
          try {
            await publishApi.cancelPublish(taskId);
            wx.showToast({ title: '已取消', icon: 'success' });
            this.loadPublishList();
          } catch (err) {
            wx.showToast({ title: err.message || '取消失败', icon: 'none' });
          }
        }
      }.bind(this)
    });
  },

  loadMore() {
    if (!this.data.hasMore) return;
    this.setData({ page: this.data.page + 1 });
    this.loadPublishList();
  }
});
