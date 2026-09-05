// 监控Mock数据
const mockTopicsMonitor = [
  {
    id: 1,
    name: '#星巴克新品打卡',
    heat: 85600,
    trend: '上升',
    trendRate: 15.8,
    videoCount: 1256,
    viewCount: 1256789,
    storeRanking: [
      { storeName: '星巴克上海旗舰店', videoCount: 156, heat: 25800 },
      { storeName: '星巴克北京国贸店', videoCount: 123, heat: 21500 },
      { storeName: '星巴克深圳海岸城店', videoCount: 98, heat: 18600 }
    ],
    trendData: [
      { date: '05-19', heat: 68000 },
      { date: '05-20', heat: 72000 },
      { date: '05-21', heat: 75000 },
      { date: '05-22', heat: 78900 },
      { date: '05-23', heat: 81000 },
      { date: '05-24', heat: 83500 },
      { date: '05-25', heat: 85600 }
    ]
  },
  {
    id: 2,
    name: '#瑞幸咖啡推荐',
    heat: 72300,
    trend: '平稳',
    trendRate: 2.3,
    videoCount: 987,
    viewCount: 987654,
    storeRanking: [
      { storeName: '瑞幸咖啡深圳海岸城店', videoCount: 234, heat: 32000 },
      { storeName: '瑞幸咖啡广州天河城店', videoCount: 198, heat: 25800 }
    ],
    trendData: [
      { date: '05-19', heat: 70000 },
      { date: '05-20', heat: 70500 },
      { date: '05-21', heat: 71200 },
      { date: '05-22', heat: 71800 },
      { date: '05-23', heat: 72000 },
      { date: '05-24', heat: 72100 },
      { date: '05-25', heat: 72300 }
    ]
  },
  {
    id: 3,
    name: '#麦当劳早餐',
    heat: 56800,
    trend: '下降',
    trendRate: -8.5,
    videoCount: 654,
    viewCount: 654321,
    storeRanking: [
      { storeName: '麦当劳北京路店', videoCount: 178, heat: 18500 },
      { storeName: '麦当劳上海南京路店', videoCount: 156, heat: 16200 }
    ],
    trendData: [
      { date: '05-19', heat: 68000 },
      { date: '05-20', heat: 66500 },
      { date: '05-21', heat: 64800 },
      { date: '05-22', heat: 62500 },
      { date: '05-23', heat: 59800 },
      { date: '05-24', heat: 58200 },
      { date: '05-25', heat: 56800 }
    ]
  }
]

const mockVideoMonitor = [
  {
    id: 1,
    title: '星巴克新品咖啡测评',
    storeName: '星巴克上海旗舰店',
    platform: '抖音',
    views: 125680,
    likes: 8965,
    comments: 456,
    shares: 1234,
    duration: '00:01:30',
    status: '播放中',
    createdAt: '2024-05-24 15:30:00'
  },
  {
    id: 2,
    title: '瑞幸咖啡隐藏菜单推荐',
    storeName: '瑞幸咖啡深圳海岸城店',
    platform: '小红书',
    views: 98650,
    likes: 7654,
    comments: 321,
    shares: 876,
    duration: '00:02:15',
    status: '播放中',
    createdAt: '2024-05-23 18:20:00'
  },
  {
    id: 3,
    title: '麦当劳早餐系列',
    storeName: '麦当劳北京路店',
    platform: '抖音',
    views: 75680,
    likes: 5432,
    comments: 234,
    shares: 567,
    duration: '00:01:45',
    status: '已暂停',
    createdAt: '2024-05-25 10:00:00'
  }
]

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
  // 获取话题监控数据
  getTopicsMonitor: (params) => {
    let result = [...mockTopicsMonitor]
    if (params?.trend) {
      result = result.filter(item => item.trend === params.trend)
    }
    if (params?.keyword) {
      result = result.filter(item => item.name.includes(params.keyword))
    }
    return response({
      list: result,
      total: result.length,
      page: params?.page || 1,
      pageSize: params?.pageSize || 10
    })
  },

  // 获取视频监控数据
  getVideoMonitor: (params) => {
    let result = [...mockVideoMonitor]
    if (params?.platform) {
      result = result.filter(item => item.platform === params.platform)
    }
    if (params?.storeName) {
      result = result.filter(item => item.storeName.includes(params.storeName))
    }
    return response({
      list: result,
      total: result.length,
      page: params?.page || 1,
      pageSize: params?.pageSize || 10
    })
  },

  // 获取播放数据
  getPlayMonitor: (params) => {
    return response({
      totalViews: 1256789,
      todayViews: 45678,
      avgWatchTime: '00:01:23',
      completionRate: 68.5
    })
  }
}