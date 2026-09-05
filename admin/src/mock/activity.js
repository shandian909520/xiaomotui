// 活动Mock数据
const mockScenes = [
  {
    id: 1,
    name: '新品上市活动',
    type: '新品发布',
    storeName: '星巴克上海旗舰店',
    status: '进行中',
    startTime: '2024-05-20 00:00:00',
    endTime: '2024-06-20 23:59:59',
    participantCount: 156,
    videoCount: 89,
    createdAt: '2024-05-19 10:30:00'
  },
  {
    id: 2,
    name: '618大促活动',
    type: '促销活动',
    storeName: '瑞幸咖啡深圳海岸城店',
    status: '预热中',
    startTime: '2024-06-01 00:00:00',
    endTime: '2024-06-18 23:59:59',
    participantCount: 0,
    videoCount: 0,
    createdAt: '2024-05-25 14:20:00'
  },
  {
    id: 3,
    name: '探店打卡活动',
    type: '打卡活动',
    storeName: '麦当劳北京路店',
    status: '已结束',
    startTime: '2024-04-01 00:00:00',
    endTime: '2024-05-01 23:59:59',
    participantCount: 456,
    videoCount: 312,
    createdAt: '2024-03-28 09:15:00'
  },
  {
    id: 4,
    name: '会员日活动',
    type: '会员活动',
    storeName: '奈雪の茶广州天河城店',
    status: '进行中',
    startTime: '2024-05-15 00:00:00',
    endTime: '2024-05-31 23:59:59',
    participantCount: 234,
    videoCount: 178,
    createdAt: '2024-05-14 16:30:00'
  }
]

const mockRedpackets = [
  {
    id: 1,
    name: '新人红包',
    type: '新人红包',
    amount: 5.00,
    totalCount: 1000,
    remainCount: 756,
    status: '进行中',
    validDays: 7,
    minVideoCount: 1,
    createdAt: '2024-05-20 10:30:00'
  },
  {
    id: 2,
    name: '分享红包',
    type: '分享红包',
    amount: 2.00,
    totalCount: 5000,
    remainCount: 2345,
    status: '进行中',
    validDays: 3,
    minVideoCount: 0,
    createdAt: '2024-05-21 14:20:00'
  },
  {
    id: 3,
    name: '大额红包',
    type: '活动红包',
    amount: 20.00,
    totalCount: 100,
    remainCount: 89,
    status: '已暂停',
    validDays: 1,
    minVideoCount: 3,
    createdAt: '2024-05-15 09:15:00'
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
  // 获取场景列表
  getScenes: (params) => {
    let result = [...mockScenes]
    if (params?.status) {
      result = result.filter(item => item.status === params.status)
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

  // 创建场景
  createScene: (data) => {
    const newScene = {
      id: mockScenes.length + 1,
      ...data,
      participantCount: 0,
      videoCount: 0,
      createdAt: new Date().toLocaleString()
    }
    mockScenes.push(newScene)
    return response(newScene)
  },

  // 更新场景
  updateScene: (id, data) => {
    const index = mockScenes.findIndex(item => item.id === id)
    if (index !== -1) {
      mockScenes[index] = { ...mockScenes[index], ...data }
      return response(mockScenes[index])
    }
    return response({ code: 404, message: '场景不存在' })
  },

  // 删除场景
  deleteScene: (id) => {
    const index = mockScenes.findIndex(item => item.id === id)
    if (index !== -1) {
      mockScenes.splice(index, 1)
      return response({ success: true })
    }
    return response({ code: 404, message: '场景不存在' })
  },

  // 获取红包列表
  getRedpackets: (params) => {
    let result = [...mockRedpackets]
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

  // 设置红包规则
  setRedpacketRules: (data) => {
    return response({ success: true, rules: data })
  }
}