// 首页Mock数据
const mockData = {
  // 统计数据
  stats: {
    totalStores: 126,
    activeStores: 98,
    totalVideos: 3456,
    todayVideos: 128,
    totalViews: 1256789,
    todayViews: 45678
  },
  // 门店分布
  storeDistribution: [
    { name: '上海市', value: 45 },
    { name: '北京市', value: 32 },
    { name: '广州市', value: 28 },
    { name: '深圳市', value: 21 }
  ],
  // 视频趋势
  videoTrend: [
    { date: '05-19', count: 98 },
    { date: '05-20', count: 112 },
    { date: '05-21', count: 105 },
    { date: '05-22', count: 128 },
    { date: '05-23', count: 135 },
    { date: '05-24', count: 142 },
    { date: '05-25', count: 128 }
  ],
  // 平台分布
  platformDistribution: [
    { name: '抖音', value: 45 },
    { name: '快手', value: 30 },
    { name: '小红书', value: 25 }
  ],
  // 热门视频
  hotVideos: [
    { id: 1, title: '夏日清凉特饮制作教程', store: '星巴克上海旗舰店', views: 125680, likes: 8965 },
    { id: 2, title: '新品上市啦', store: '麦当劳北京路店', views: 98650, likes: 7654 },
    { id: 3, title: '隐藏菜单大公开', store: '瑞幸咖啡深圳店', views: 87540, likes: 6543 }
  ],
  // 待处理任务
  pendingTasks: [
    { id: 1, type: '视频剪辑', store: '星巴克上海旗舰店', status: '处理中', progress: 65 },
    { id: 2, type: '素材上传', store: '瑞幸咖啡深圳店', status: '待处理', progress: 0 },
    { id: 3, type: '视频发布', store: '麦当劳北京路店', status: '失败', progress: 30 }
  ]
}

const response = (data) => {
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve({
        code: 200,
        message: 'success',
        data
      })
    }, 300)
  })
}

export default {
  // 获取首页统计数据
  getStats: () => response(mockData.stats),

  // 获取门店分布
  getStoreDistribution: () => response(mockData.storeDistribution),

  // 获取视频趋势
  getVideoTrend: () => response(mockData.videoTrend),

  // 获取平台分布
  getPlatformDistribution: () => response(mockData.platformDistribution),

  // 获取热门视频
  getHotVideos: () => response(mockData.hotVideos),

  // 获取待处理任务
  getPendingTasks: () => response(mockData.pendingTasks)
}