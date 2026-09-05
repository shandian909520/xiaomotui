// 任务Mock数据
const mockTasks = [
  {
    id: 1,
    type: '视频剪辑',
    title: '星巴克新品咖啡测评',
    storeName: '星巴克上海旗舰店',
    status: '处理中',
    progress: 65,
    priority: '高',
    assigner: '张主管',
    executor: '小智',
    startTime: '2024-05-25 09:30:00',
    endTime: null,
    estimatedTime: '30分钟'
  },
  {
    id: 2,
    type: '文案创作',
    title: '瑞幸咖啡新品推广文案',
    storeName: '瑞幸咖啡深圳海岸城店',
    status: '已完成',
    progress: 100,
    priority: '中',
    assigner: '李主管',
    executor: '小雪',
    startTime: '2024-05-24 14:20:00',
    endTime: '2024-05-24 14:45:00',
    estimatedTime: '20分钟'
  },
  {
    id: 3,
    type: '素材上传',
    title: '麦当劳早餐系列素材',
    storeName: '麦当劳北京路店',
    status: '失败',
    progress: 30,
    priority: '高',
    assigner: '王主管',
    executor: null,
    startTime: '2024-05-24 10:15:00',
    endTime: null,
    estimatedTime: '15分钟',
    errorMsg: '素材加载失败，请重试'
  },
  {
    id: 4,
    type: '视频发布',
    title: '奈雪の茶新品上市发布',
    storeName: '奈雪の茶广州天河城店',
    status: '待处理',
    progress: 0,
    priority: '低',
    assigner: '陈主管',
    executor: null,
    startTime: '2024-05-25 11:00:00',
    endTime: null,
    estimatedTime: '10分钟'
  },
  {
    id: 5,
    type: '数据统计',
    title: '周数据统计报告',
    storeName: '星巴克上海旗舰店',
    status: '已完成',
    progress: 100,
    priority: '中',
    assigner: '张主管',
    executor: '小能',
    startTime: '2024-05-24 18:00:00',
    endTime: '2024-05-24 18:30:00',
    estimatedTime: '25分钟'
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
  // 获取任务列表
  getTasks: (params) => {
    let result = [...mockTasks]
    if (params?.status) {
      result = result.filter(item => item.status === params.status)
    }
    if (params?.type) {
      result = result.filter(item => item.type === params.type)
    }
    if (params?.priority) {
      result = result.filter(item => item.priority === params.priority)
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

  // 重试任务
  retryTask: (id) => {
    const task = mockTasks.find(item => item.id === id)
    if (task) {
      task.status = '待处理'
      task.progress = 0
      task.errorMsg = null
      return response(task)
    }
    return response({ code: 404, message: '任务不存在' })
  },

  // 获取任务详情
  getTaskDetail: (id) => {
    const task = mockTasks.find(item => item.id === id)
    return response(task)
  },

  // 取消任务
  cancelTask: (id) => {
    const task = mockTasks.find(item => item.id === id)
    if (task) {
      task.status = '已取消'
      return response(task)
    }
    return response({ code: 404, message: '任务不存在' })
  }
}