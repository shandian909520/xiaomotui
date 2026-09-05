const contentApi = require('../../api/content');

Page({
  data: {
    contentType: 'video',
    contentTypeOptions: [
      { value: 'video', label: '视频' },
      { value: 'image_text', label: '图文' }
    ],
    templates: [],
    selectedTemplateId: '',
    form: {
      title: '',
      industry: '',
      keywords: '',
      description: ''
    },
    isGenerating: false,
    pageLoading: true
  },

  onLoad() {
    this.loadTemplates();
  },

  onPullDownRefresh() {
    this.loadTemplates().then(() => {
      wx.stopPullDownRefresh();
    });
  },

  async loadTemplates() {
    try {
      const data = await contentApi.getTemplateList();
      const templates = data.list || data || [];
      this.setData({
        templates,
        selectedTemplateId: templates.length > 0 ? templates[0].id : '',
        pageLoading: false
      });
    } catch (err) {
      this.setData({ pageLoading: false });
      wx.showToast({ title: '加载模板失败', icon: 'none' });
    }
  },

  switchContentType(e) {
    const type = e.currentTarget.dataset.type;
    this.setData({ contentType: type });
    this.loadTemplates();
  },

  selectTemplate(e) {
    const id = e.currentTarget.dataset.id;
    this.setData({ selectedTemplateId: id });
  },

  handleInputChange(e) {
    const field = e.currentTarget.dataset.field;
    this.setData({ ['form.' + field]: e.detail.value });
  },

  async handleGenerate() {
    var form = this.data.form;

    if (!form.title.trim()) {
      wx.showToast({ title: '请输入标题', icon: 'none' });
      return;
    }

    this.setData({ isGenerating: true });
    wx.showLoading({ title: '内容生成中...', mask: true });

    try {
      var params = {
        type: this.data.contentType,
        title: form.title,
        industry: form.industry,
        keywords: form.keywords,
        description: form.description
      };

      if (this.data.selectedTemplateId) {
        params.template_id = this.data.selectedTemplateId;
      }

      var data = await contentApi.generateContent(params);
      wx.hideLoading();

      var taskId = data.task_id || data.taskId;
      if (taskId) {
        wx.navigateTo({
          url: '/pages/content/detail?taskId=' + taskId
        });
      } else {
        wx.showToast({ title: '生成成功', icon: 'success' });
      }
    } catch (err) {
      wx.hideLoading();
      wx.showToast({ title: err.message || '生成失败', icon: 'none' });
    } finally {
      this.setData({ isGenerating: false });
    }
  },

  goToHistory() {
    wx.navigateTo({ url: '/pages/content/detail?mode=list' });
  }
});
