// API接口统一管理
import request from '@/utils/request'

// 认证相关接口
export const authApi = {
  // 登录 (支持账号/手机号)
  login(data) {
    return request.post('/auth/login', data)
  },

  // 发送验证码
  sendCode(data) {
    return request.post('/auth/send-code', data)
  },

  // 退出登录
  logout() {
    return request.post('/auth/logout')
  },

  // 获取用户信息
  getUserInfo() {
    return request.get('/auth/userinfo')
  }
}

// NFC设备接口
export const nfcApi = {
  // 获取 NFC 设备列表(真设备,Nfc@deviceList,鉴权组 /api/merchant/nfc/devices)
  getDevices(params) {
    return request.get('/merchant/nfc/devices', { params })
  },

  // 获取设备详情
  getDevice(id) {
    return request.get(`/merchant/nfc/devices/${id}`)
  },

  // 创建设备
  createDevice(data) {
    return request.post('/merchant/nfc/devices', data)
  },

  // 更新设备
  updateDevice(id, data) {
    return request.put(`/merchant/nfc/devices/${id}`, data)
  },

  // 删除设备
  deleteDevice(id) {
    return request.delete(`/merchant/nfc/devices/${id}`)
  }
}

// 内容管理接口
export const contentApi = {
  // 获取任务列表
  getTasks(params) {
    return request.get('/content/tasks', { params })
  },

  // 生成内容
  generateContent(data) {
    return request.post('/content/generate', data)
  },

  // 获取模板列表
  getTemplates(params) {
    return request.get('/content/templates', { params })
  }
}

// 券码管理接口
export const couponApi = {
  // 获取券码列表
  getCoupons(params) {
    return request.get('/coupons', { params })
  },

  // 创建券码
  createCoupon(data) {
    return request.post('/coupons', data)
  },

  // 获取用户领取记录
  getUserCoupons(params) {
    return request.get('/coupons/users', { params })
  }
}

// 商户管理接口（P0 安全修复 2026-09-05：从公开组 /api/merchants 迁入鉴权组 /api/admin/merchants）
export const merchantApi = {
  // 获取商户列表
  getMerchants(params) {
    return request.get('/admin/merchants', { params })
  },

  // 创建商户
  createMerchant(data) {
    return request.post('/admin/merchants', data)
  },

  // 更新商户
  updateMerchant(id, data) {
    return request.put(`/admin/merchants/${id}`, data)
  }
}

// 统计接口
export const statsApi = {
  // 获取仪表盘统计
  getDashboard() {
    return request.get('/stats/dashboard')
  },

  // 获取趋势数据
  getTrends(params) {
    return request.get('/stats/trends', { params })
  }
}

// 推广统计接口
export const promoStatsApi = {
  // 获取统计概览
  getOverview(params) {
    return request.get('/merchant/promo-stats/overview', { params })
  },

  // 获取趋势数据
  getTrendData(params) {
    return request.get('/merchant/promo-stats/trend', { params })
  },

  // 获取平台分布
  getPlatformDistribution(params) {
    return request.get('/merchant/promo-stats/platform', { params })
  },

  // 获取设备排行
  getDeviceRanking(params) {
    return request.get('/merchant/promo-stats/device-ranking', { params })
  },

  // 获取活动对比
  getCampaignComparison(params) {
    return request.get('/merchant/promo-stats/campaign-comparison', { params })
  },

  // 获取今日统计
  getTodayStats() {
    return request.get('/merchant/promo-stats/today')
  },

  // 获取活动列表（用于下拉选择）
  getCampaignList(params) {
    return request.get('/merchant/promo-stats/campaign-list', { params })
  }
}

// 场景配置接口
export const sceneConfigApi = {
  getSceneConfigList(params) {
    return request.get('/scene-config/list', { params })
  },
  getSceneConfigDetail(params) {
    return request.get('/scene-config/detail', { params })
  },
  saveSceneConfig(data) {
    return request.post('/scene-config/save', data)
  },
  batchSaveSceneConfig(data) {
    return request.post('/scene-config/batch-save', data)
  },
  toggleSceneConfigStatus(data) {
    return request.post('/scene-config/toggle-status', data)
  },
  getScenePlatforms() {
    return request.get('/scene-config/platforms')
  }
}

// 内容库 - 视频
export const getVideoLibraryList = (params) => request.get('/content-library/video/list', { params })
export const createVideoLibrary = (data) => request.post('/content-library/video/create', data)
export const getVideoLibraryDetail = (id) => request.get(`/content-library/video/${id}`)
export const updateVideoLibrary = (id, data) => request.put(`/content-library/video/${id}`, data)
export const deleteVideoLibrary = (id) => request.delete(`/content-library/video/${id}`)
export const addLocalVideo = (id, data) => request.post(`/content-library/video/${id}/add-local`, data)
export const importVideo = (id, data) => request.post(`/content-library/video/${id}/import`, data)

// 内容库 - 图文
export const getGraphicLibraryList = (params) => request.get('/content-library/graphic/list', { params })
export const createGraphicLibrary = (data) => request.post('/content-library/graphic/create', data)
export const getGraphicLibraryDetail = (id) => request.get(`/content-library/graphic/${id}`)
export const addGraphicContent = (id, data) => request.post(`/content-library/graphic/${id}/add-content`, data)
export const updateGraphicLibrary = (id, data) => request.put(`/content-library/graphic/${id}`, data)
export const deleteGraphicLibrary = (id) => request.delete(`/content-library/graphic/${id}`)

// 内容库 - 图片
export const getImageLibraryList = (params) => request.get('/content-library/image/list', { params })
export const createImageLibrary = (data) => request.post('/content-library/image/create', data)
export const getImageLibraryDetail = (id) => request.get(`/content-library/image/detail/${id}`)
export const addImage = (id, data) => request.post(`/content-library/image/${id}/add`, data)
export const updateImageLibrary = (id, data) => request.put(`/content-library/image/update/${id}`, data)
export const deleteImageLibrary = (id) => request.delete(`/content-library/image/delete/${id}`)

// 内容库 - 文案
export const getTextLibraryList = (params) => request.get('/content-library/text/list', { params })
export const createTextLibrary = (data) => request.post('/content-library/text/create', data)
export const getTextLibraryDetail = (id) => request.get(`/content-library/text/detail/${id}`)
export const addText = (id, data) => request.post(`/content-library/text/${id}/add`, data)
export const updateTextLibrary = (id, data) => request.put(`/content-library/text/update/${id}`, data)
export const deleteTextLibrary = (id) => request.delete(`/content-library/text/delete/${id}`)

// 内容库 - 话题
export const getTopicLibraryList = (params) => request.get('/content-library/topic/list', { params })
export const createTopicLibrary = (data) => request.post('/content-library/topic/create', data)
export const getTopicLibraryDetail = (id) => request.get(`/content-library/topic/detail/${id}`)
export const addTopic = (id, data) => request.post(`/content-library/topic/${id}/add`, data)
export const renameTopicLibrary = (id, data) => request.put(`/content-library/topic/${id}/rename`, data)
export const deleteTopicLibrary = (id) => request.delete(`/content-library/topic/${id}`)

// 内容库 - 通用
export const setLibraryWarningEmail = (id, email) => request.post(`/content-library/${id}/warning-email`, { email })
export const deleteLibraryItem = (id) => request.delete(`/content-library/item/${id}`)

// 账号商业化接口
export const accountApi = {
  changePassword(data) {
    return request.post('/account/change-password', data)
  },
  activateCard(data) {
    return request.post('/account/activate-card', data)
  },
  switchVersion(data) {
    return request.post('/account/switch-version', data)
  },
  getAccountBenefits() {
    return request.get('/account/benefits')
  }
}

// 素材文件夹
export const getMaterialFolders = (params) => request.get('/material/folders', { params })
export const createMaterialFolder = (data) => request.post('/material/folder-create', data)
export const renameMaterialFolder = (data) => request.post('/material/folder-rename', data)
export const deleteMaterialFolder = (data) => request.post('/material/folder-delete', data)

// 素材操作
export const getMaterialList = (params) => request.get('/material/list', { params })
export const moveMaterial = (data) => request.post('/material/move', data)
export const batchDeleteMaterial = (data) => request.post('/material/batch-delete', data)
export const softDeleteMaterial = (data) => request.post('/material/soft-delete', data)
export const getMaterialTrash = (params) => request.get('/material/trash', { params })
export const restoreMaterial = (data) => request.post('/material/restore', data)
export const permanentDeleteMaterial = (data) => request.post('/material/permanent-delete', data)

// 剪辑工程
export const getClipProjectList = (params) => request.get('/clip-project/list', { params })
export const createClipProject = (data) => request.post('/clip-project/create', data)
export const getClipProjectDetail = (id) => request.get('/clip-project/detail', { params: { id } })
export const updateClipProject = (data) => request.post('/clip-project/update', data)
export const deleteClipProject = (id) => request.post('/clip-project/delete', { id })
export const saveAsTemplate = (id) => request.post('/clip-project/save-as-template', { project_id: id })
export const getMyTemplates = (params) => request.get('/clip-project/my-templates', { params })
export const exportClipProject = (id) => request.post('/clip-project/export', { project_id: id })
export const generateAutoShots = (data) => request.post('/clip-project/generate-auto-shots', data)
export const batchRemix = (data) => request.post('/clip-project/batch-remix', data)
export const batchExport = (data) => request.post('/clip-project/batch-export', data)

// 分镜
export const getClipShots = (projectId) => request.get('/clip-project/shots', { params: { project_id: projectId } })
export const addClipShot = (data) => request.post('/clip-project/shot/add', data)
export const updateClipShot = (data) => request.post('/clip-project/shot/update', data)
export const deleteClipShot = (id) => request.post('/clip-project/shot/delete', { shot_id: id })
export const sortClipShots = (data) => request.post('/clip-project/shot/sort', data)

// 剪辑配置
export const getVoiceActors = () => request.get('/clip-project/voice-actors')
export const getTransitions = () => request.get('/clip-project/transitions')
export const getFilters = () => request.get('/clip-project/filters')
export const getAspectRatios = () => request.get('/clip-project/aspect-ratios')
export const getFrameRates = () => request.get('/clip-project/frame-rates')

// 智能员工
export const getStaffGroups = () => request.get('/ai-staff/groups')
export const getStaffListNew = (params) => request.get('/ai-staff/list', { params })
export const getStaffDetail = (id) => request.get('/ai-staff/detail', { params: { id } })
export const assignStaffWork = (data) => request.post('/ai-staff/assign', data)
export const getStaffUsage = (id) => request.get('/ai-staff/usage', { params: { id } })

// 员工管理（连锁版）
export const getEmployeeStatsByEmployee = (params) => request.get('/employee-stats/stats-by-employee', { params })
export const getEmployeeStatsByStore = (params) => request.get('/employee-stats/stats-by-store', { params })
export const getEmployeeStatsByTask = (params) => request.get('/employee-stats/stats-by-task', { params })
export const getEmployeeRankings = (params) => request.get('/employee-stats/rankings', { params })
export const getEmployeePublishDetails = (params) => request.get('/employee-stats/publish-details', { params })

// 红包活动
export const getRedpacketActivityList = (params) => request.get('/redpacket-activity/list', { params })
export const getRedpacketActivityDetail = (id) => request.get('/redpacket-activity/detail', { params: { id } })
export const createRedpacketActivity = (data) => request.post('/redpacket-activity/create', data)
export const updateRedpacketActivity = (data) => request.post('/redpacket-activity/update', data)
export const toggleRedpacketStatus = (data) => request.post('/redpacket-activity/toggle-status', data)
export const getRedpacketStats = () => request.get('/redpacket-activity/stats')
export const getRedpacketBalanceOverview = () => request.get('/redpacket-activity/balance-overview')

// 话题监控
export const getTopicMonitorList = (params) => request.get('/topic-monitor/list', { params })
export const addTopicMonitor = (data) => request.post('/topic-monitor/add', data)
export const getTopicMonitorDetail = (id) => request.get('/topic-monitor/detail', { params: { id } })
export const cancelTopicMonitor = (data) => request.post('/topic-monitor/cancel', data)
export const getTopicMonitorDailyTrend = (params) => request.get('/topic-monitor/daily-trend', { params })

// 门店管理增强
export const getStoreManageList = (params) => request.get('/store-manage/list', { params })
export const getStoreManageDetail = (id) => request.get('/store-manage/detail', { params: { id } })
export const updateStoreManage = (data) => request.post('/store-manage/update', data)
export const batchImportStores = (data) => request.post('/store-manage/batch-import', data)
export const batchImportPoi = (data) => request.post('/store-manage/batch-import-poi', data)
export const getStoreImportStatus = (taskId) => request.get('/store-manage/import-status', { params: { task_id: taskId } })
export const getStoreQrCode = (storeId) => request.get('/store-manage/qr-code', { params: { store_id: storeId } })
export const getStoreNfcPath = (storeId) => request.get('/store-manage/nfc-path', { params: { store_id: storeId } })
export const updateStoreDecoration = (data) => request.post('/store-manage/decoration', data)
export const toggleTableSticker = (data) => request.post('/store-manage/table-sticker', data)

// 首页驾驶舱
export const getDashboardFlowSteps = () => request.get('/dashboard/flow-steps')
export const getDashboardDataStats = (params) => request.get('/dashboard/data-stats', { params })
export const getDashboardConsumption = () => request.get('/dashboard/consumption')
export const getDashboardQuickEntries = () => request.get('/dashboard/quick-entries')
export const getDashboardQrCode = () => request.get('/dashboard/qr-code')

// 物料设计场景
export const getDesignSceneList = (params) => request.get('/design-scene/list', { params })
export const getDesignSceneDetail = (sceneKey) => request.get('/design-scene/detail', { params: { scene_key: sceneKey } })
export const getDesignSceneTemplates = (params) => request.get('/design-scene/templates', { params })
export const previewDesignScene = (data) => request.post('/design-scene/preview', data)
export const generateDesignScene = (data) => request.post('/design-scene/generate', data)

// 通知
export const getNotificationList = (params) => request.get('/notification/list', { params })
export const getNotificationDetail = (id) => request.get('/notification/detail', { params: { id } })
export const markNotificationRead = (data) => request.post('/notification/mark-read', data)
export const markAllNotificationRead = () => request.post('/notification/mark-all-read')
export const getUnreadNotificationCount = () => request.get('/notification/unread-count')

// 任务中心
export const getUserTaskList = (params) => request.get('/user-task/list', { params })
export const getUserTaskDetail = (id) => request.get('/user-task/detail', { params: { id } })
export const getUserTaskSummary = () => request.get('/user-task/summary')

// 团购商品 Admin CRUD（模块5 - 营销聚合页）
export const groupBuyAdminApi = {
  list(params) {
    return request.get('/groupbuy/admin/items', { params })
  },
  detail(id) {
    return request.get(`/groupbuy/admin/items/${id}`)
  },
  create(data) {
    return request.post('/groupbuy/admin/items', data)
  },
  update(id, data) {
    return request.put(`/groupbuy/admin/items/${id}`, data)
  },
  remove(id) {
    return request.delete(`/groupbuy/admin/items/${id}`)
  }
}

// 抽奖活动 Admin（模块6 - 大转盘）
export const lotteryAdminApi = {
  // 活动
  activityList(params) {
    return request.get('/lottery/admin/activities', { params })
  },
  createActivity(data) {
    return request.post('/lottery/admin/activities', data)
  },
  updateActivity(id, data) {
    return request.put(`/lottery/admin/activities/${id}`, data)
  },
  removeActivity(id) {
    return request.delete(`/lottery/admin/activities/${id}`)
  },
  // 奖项(后端注册为 GET prizes?activity_id=,不是 /activities/{id}/prizes)
  prizes(activityId) {
    return request.get('/lottery/admin/prizes', { params: { activity_id: activityId } })
  },
  createPrize(data) {
    return request.post('/lottery/admin/prizes', data)
  },
  updatePrize(id, data) {
    return request.put(`/lottery/admin/prizes/${id}`, data)
  },
  removePrize(id) {
    return request.delete(`/lottery/admin/prizes/${id}`)
  },
  // 中奖记录
  records(params) {
    return request.get('/lottery/admin/records', { params })
  }
}

// 点评配置（模块4 - 打卡点评合规版）
export const reviewApi = {
  getConfig(deviceId) {
    return request.get('/review/config', { params: { device_id: deviceId } })
  },
  saveConfig(data) {
    return request.post('/review/admin/config', data)
  }
}

// 文案池（模块3 - Agent C 业务闭环）
export const copywritingAdminApi = {
  list(params) {
    return request.get('/copywriting/admin/list', { params })
  },
  create(data) {
    return request.post('/copywriting/admin', data)
  },
  update(id, data) {
    return request.put(`/copywriting/admin/${id}`, data)
  },
  remove(id) {
    return request.delete(`/copywriting/admin/${id}`)
  },
  batchImport(data) {
    return request.post('/copywriting/admin/batch-import', data)
  }
}

// 点评商家后台（模块4 - Agent C 业务闭环）
export const reviewAdminApi = {
  saveConfig(data) {
    return request.post('/review/admin/config', data)
  },
  draftTemplates(params) {
    return request.get('/review/admin/draft-templates', { params })
  },
  addTemplate(data) {
    return request.post('/review/admin/draft-template', data)
  },
  removeTemplate(id, params) {
    return request.delete(`/review/admin/draft-template/${id}`, { params })
  }
}

// QQ 联系方式（模块7 - Agent C 业务闭环）
export const contactQqApi = {
  getConfig(deviceId) {
    return request.get('/contact/qq-config', { params: { device_id: deviceId } })
  },
  saveConfig(data) {
    return request.put('/contact/admin/qq-config', data)
  },
  recordAction(data) {
    return request.post('/contact/qq-action', data)
  }
}

export default {
  authApi,
  nfcApi,
  contentApi,
  couponApi,
  merchantApi,
  statsApi,
  promoStatsApi,
  sceneConfigApi,
  accountApi,
  groupBuyAdminApi,
  lotteryAdminApi,
  reviewApi,
  copywritingAdminApi,
  reviewAdminApi,
  contactQqApi
}
