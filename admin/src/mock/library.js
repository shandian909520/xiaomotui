// 成品库Mock数据
const mockVideos = [
  {
    id: 1,
    title: '星巴克新品咖啡测评',
    thumbnail: 'https://picsum.photos/300/400?random=20',
    duration: '00:01:30',
    platform: ['抖音', '快手'],
    storeName: '星巴克上海旗舰店',
    views: 125680,
    likes: 8965,
    comments: 456,
    shares: 1234,
    status: '已发布',
    createdAt: '2024-05-24 15:30:00'
  },
  {
    id: 2,
    title: '瑞幸咖啡隐藏菜单推荐',
    thumbnail: 'https://picsum.photos/300/400?random=21',
    duration: '00:02:15',
    platform: ['小红书'],
    storeName: '瑞幸咖啡深圳海岸城店',
    views: 98650,
    likes: 7654,
    comments: 321,
    shares: 876,
    status: '已发布',
    createdAt: '2024-05-23 18:20:00'
  },
  {
    id: 3,
    title: '麦当劳早餐系列',
    thumbnail: 'https://picsum.photos/300/400?random=22',
    duration: '00:01:45',
    platform: ['抖音'],
    storeName: '麦当劳北京路店',
    views: 75680,
    likes: 5432,
    comments: 234,
    shares: 567,
    status: '草稿',
    createdAt: '2024-05-25 10:00:00'
  }
]

const mockImages = [
  {
    id: 1,
    title: '新品上市宣传图',
    thumbnail: 'https://picsum.photos/300/400?random=30',
    dimensions: '1920x1080',
    platform: ['抖音', '快手'],
    storeName: '星巴克上海旗舰店',
    views: 45678,
    likes: 2345,
    status: '已发布',
    createdAt: '2024-05-24 14:30:00'
  },
  {
    id: 2,
    title: '促销活动海报',
    thumbnail: 'https://picsum.photos/300/400?random=31',
    dimensions: '1080x1920',
    platform: ['小红书'],
    storeName: '瑞幸咖啡深圳海岸城店',
    views: 34567,
    likes: 1876,
    status: '已发布',
    createdAt: '2024-05-23 16:45:00'
  },
  {
    id: 3,
    title: '门店环境展示',
    thumbnail: 'https://picsum.photos/300/400?random=32',
    dimensions: '1920x1080',
    platform: ['抖音'],
    storeName: '奈雪の茶广州天河城店',
    views: 23456,
    likes: 1234,
    status: '草稿',
    createdAt: '2024-05-25 09:15:00'
  }
]

const mockTopics = [
  {
    id: 1,
    name: '#星巴克新品打卡',
    heat: 85600,
    trend: '上升',
    videoCount: 1256,
    stores: ['星巴克上海旗舰店', '星巴克北京国贸店'],
    createdAt: '2024-05-20 10:00:00'
  },
  {
    id: 2,
    name: '#瑞幸咖啡推荐',
    heat: 72300,
    trend: '平稳',
    videoCount: 987,
    stores: ['瑞幸咖啡深圳海岸城店'],
    createdAt: '2024-05-18 14:30:00'
  },
  {
    id: 3,
    name: '#麦当劳早餐',
    heat: 56800,
    trend: '下降',
    videoCount: 654,
    stores: ['麦当劳北京路店', '麦当劳上海南京路店'],
    createdAt: '2024-05-15 09:00:00'
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
  // 获取成品视频列表
  getVideos: (params) => {
    let result = [...mockVideos]
    if (params?.storeName) {
      result = result.filter(item => item.storeName.includes(params.storeName))
    }
    if (params?.status) {
      result = result.filter(item => item.status === params.status)
    }
    return response({
      list: result,
      total: result.length,
      page: params?.page || 1,
      pageSize: params?.pageSize || 10
    })
  },

  // 获取成品图片列表
  getImages: (params) => {
    let result = [...mockImages]
    if (params?.storeName) {
      result = result.filter(item => item.storeName.includes(params.storeName))
    }
    if (params?.status) {
      result = result.filter(item => item.status === params.status)
    }
    return response({
      list: result,
      total: result.length,
      page: params?.page || 1,
      pageSize: params?.pageSize || 10
    })
  },

  // 获取话题列表
  getTopics: (params) => {
    let result = [...mockTopics]
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

  // 删除视频
  deleteVideo: (id) => {
    const index = mockVideos.findIndex(item => item.id === id)
    if (index !== -1) {
      mockVideos.splice(index, 1)
      return response({ success: true })
    }
    return response({ code: 404, message: '视频不存在' })
  },

  // 删除图片
  deleteImage: (id) => {
    const index = mockImages.findIndex(item => item.id === id)
    if (index !== -1) {
      mockImages.splice(index, 1)
      return response({ success: true })
    }
    return response({ code: 404, message: '图片不存在' })
  }
}