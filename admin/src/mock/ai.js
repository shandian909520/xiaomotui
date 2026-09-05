// AI员工Mock数据
const mockAiStaff = [
  {
    id: 1,
    name: '小雪',
    avatar: 'https://picsum.photos/100/100?random=10',
    type: '文案创作',
    status: '工作中',
    taskCount: 156,
    todayTaskCount: 12,
    ability: '擅长创作各类营销文案、种草文案、探店文案',
    stores: ['星巴克上海旗舰店', '瑞幸咖啡深圳海岸城店'],
    createdAt: '2024-01-15 10:30:00'
  },
  {
    id: 2,
    name: '小智',
    avatar: 'https://picsum.photos/100/100?random=11',
    type: '视频剪辑',
    status: '工作中',
    taskCount: 89,
    todayTaskCount: 7,
    ability: '专业视频剪辑，擅长节奏把控和特效添加',
    stores: ['麦当劳北京路店', '肯德基杭州西湖店'],
    createdAt: '2024-02-20 14:20:00'
  },
  {
    id: 3,
    name: '小美',
    avatar: 'https://picsum.photos/100/100?random=12',
    type: '图文设计',
    status: '空闲',
    taskCount: 234,
    todayTaskCount: 0,
    ability: '专业平面设计，擅长海报、宣传图设计',
    stores: ['奈雪の茶广州天河城店'],
    createdAt: '2023-11-08 09:15:00'
  },
  {
    id: 4,
    name: '小能',
    avatar: 'https://picsum.photos/100/100?random=13',
    type: '数据分析师',
    status: '工作中',
    taskCount: 45,
    todayTaskCount: 3,
    ability: '数据分析和可视化，生成运营报告',
    stores: ['星巴克上海旗舰店'],
    createdAt: '2024-03-12 11:45:00'
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
  // 获取AI员工列表
  getAiStaff: () => {
    return response(mockAiStaff)
  },

  // 获取AI员工详情
  getAiStaffDetail: (id) => {
    const staff = mockAiStaff.find(item => item.id === id)
    return response(staff)
  },

  // 分配AI员工
  assignAiStaff: (id, data) => {
    const staff = mockAiStaff.find(item => item.id === id)
    if (staff) {
      if (data.storeId) {
        staff.stores.push(data.storeName)
      }
      staff.taskCount++
      staff.todayTaskCount++
      return response({ success: true, staff })
    }
    return response({ code: 404, message: '员工不存在' })
  },

  // 更新AI员工配置
  updateAiStaff: (id, data) => {
    const index = mockAiStaff.findIndex(item => item.id === id)
    if (index !== -1) {
      mockAiStaff[index] = { ...mockAiStaff[index], ...data }
      return response(mockAiStaff[index])
    }
    return response({ code: 404, message: '员工不存在' })
  }
}