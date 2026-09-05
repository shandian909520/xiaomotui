// 视频Mock数据
const mockVideoTasks = [
  {
    id: 1,
    title: '星巴克新品咖啡测评',
    storeName: '星巴克上海旗舰店',
    template: '产品测评模板',
    status: '处理中',
    progress: 65,
    videoCount: 1,
    platform: ['抖音', '快手'],
    createdAt: '2024-05-25 09:30:00',
    completedAt: null
  },
  {
    id: 2,
    title: '瑞幸咖啡隐藏菜单推荐',
    storeName: '瑞幸咖啡深圳海岸城店',
    template: '探店模板',
    status: '已完成',
    progress: 100,
    videoCount: 3,
    platform: ['抖音', '快手', '小红书'],
    createdAt: '2024-05-24 14:20:00',
    completedAt: '2024-05-24 15:45:00'
  },
  {
    id: 3,
    title: '麦当劳早餐系列',
    storeName: '麦当劳北京路店',
    template: '美食推荐模板',
    status: '失败',
    progress: 30,
    videoCount: 1,
    platform: ['抖音'],
    createdAt: '2024-05-24 10:15:00',
    completedAt: null,
    errorMsg: '素材加载失败，请重试'
  },
  {
    id: 4,
    title: '奈雪の茶新品上市',
    storeName: '奈雪の茶广州天河城店',
    template: '新品发布模板',
    status: '待处理',
    progress: 0,
    videoCount: 2,
    platform: ['快手', '小红书'],
    createdAt: '2024-05-25 11:00:00',
    completedAt: null
  },
  {
    id: 5,
    title: '肯德基下午茶优惠',
    storeName: '肯德基杭州西湖店',
    template: '优惠活动模板',
    status: '已完成',
    progress: 100,
    videoCount: 5,
    platform: ['抖音', '快手'],
    createdAt: '2024-05-23 16:30:00',
    completedAt: '2024-05-23 18:20:00'
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
  // 获取视频任务列表
  getVideoTasks: (params) => {
    let result = [...mockVideoTasks]
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

  // 创建视频任务
  createVideoTask: (data) => {
    const newTask = {
      id: mockVideoTasks.length + 1,
      ...data,
      status: '待处理',
      progress: 0,
      createdAt: new Date().toLocaleString(),
      completedAt: null
    }
    mockVideoTasks.push(newTask)
    return response(newTask)
  },

  // 重试视频任务
  retryVideoTask: (id) => {
    const task = mockVideoTasks.find(item => item.id === id)
    if (task) {
      task.status = '处理中'
      task.progress = 0
      task.errorMsg = null
      return response(task)
    }
    return response({ code: 404, message: '任务不存在' })
  },

  // 获取视频任务详情
  getVideoTaskDetail: (id) => {
    const task = mockVideoTasks.find(item => item.id === id)
    return response(task)
  },

  // 取消视频任务
  cancelVideoTask: (id) => {
    const task = mockVideoTasks.find(item => item.id === id)
    if (task) {
      task.status = '已取消'
      return response(task)
    }
    return response({ code: 404, message: '任务不存在' })
  }
}