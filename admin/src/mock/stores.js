// 门店Mock数据
const mockStores = [
  {
    id: 1,
    name: '星巴克上海旗舰店',
    address: '上海市南京东路步行街168号',
    contact: '张经理',
    phone: '13800138001',
    status: '营业中',
    videoCount: 256,
    todayVideoCount: 12,
    createdAt: '2024-01-15 10:30:00'
  },
  {
    id: 2,
    name: '瑞幸咖啡深圳海岸城店',
    address: '深圳市南山区海德三道海岸城购物中心',
    contact: '李经理',
    phone: '13800138002',
    status: '营业中',
    videoCount: 189,
    todayVideoCount: 8,
    createdAt: '2024-02-20 14:20:00'
  },
  {
    id: 3,
    name: '麦当劳北京路店',
    address: '北京市朝阳区建国路88号',
    contact: '王经理',
    phone: '13800138003',
    status: '营业中',
    videoCount: 342,
    todayVideoCount: 18,
    createdAt: '2023-11-08 09:15:00'
  },
  {
    id: 4,
    name: '肯德基杭州西湖店',
    address: '杭州市上城区延安路258号',
    contact: '赵经理',
    phone: '13800138004',
    status: '休息中',
    videoCount: 156,
    todayVideoCount: 0,
    createdAt: '2024-03-12 11:45:00'
  },
  {
    id: 5,
    name: '奈雪の茶广州天河城店',
    address: '广州市天河区天河路208号天河城',
    contact: '陈经理',
    phone: '13800138005',
    status: '营业中',
    videoCount: 278,
    todayVideoCount: 15,
    createdAt: '2024-01-28 16:30:00'
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
  // 获取门店列表
  getStores: (params) => {
    let result = [...mockStores]
    if (params?.status) {
      result = result.filter(item => item.status === params.status)
    }
    if (params?.keyword) {
      result = result.filter(item =>
        item.name.includes(params.keyword) ||
        item.address.includes(params.keyword)
      )
    }
    return response({
      list: result,
      total: result.length,
      page: params?.page || 1,
      pageSize: params?.pageSize || 10
    })
  },

  // 获取门店详情
  getStoreDetail: (id) => {
    const store = mockStores.find(item => item.id === id)
    return response(store)
  },

  // 创建门店
  createStore: (data) => {
    const newStore = {
      id: mockStores.length + 1,
      ...data,
      videoCount: 0,
      todayVideoCount: 0,
      createdAt: new Date().toLocaleString()
    }
    mockStores.push(newStore)
    return response(newStore)
  },

  // 更新门店
  updateStore: (id, data) => {
    const index = mockStores.findIndex(item => item.id === id)
    if (index !== -1) {
      mockStores[index] = { ...mockStores[index], ...data }
      return response(mockStores[index])
    }
    return response({ code: 404, message: '门店不存在' })
  },

  // 删除门店
  deleteStore: (id) => {
    const index = mockStores.findIndex(item => item.id === id)
    if (index !== -1) {
      mockStores.splice(index, 1)
      return response({ success: true })
    }
    return response({ code: 404, message: '门店不存在' })
  }
}