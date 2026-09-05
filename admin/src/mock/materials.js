// 素材Mock数据
const mockMaterials = [
  {
    id: 1,
    name: '咖啡拉花教程视频',
    type: 'video',
    category: '教程类',
    size: '256MB',
    duration: '00:05:32',
    thumbnail: 'https://picsum.photos/200/300?random=1',
    storeName: '星巴克上海旗舰店',
    status: '已通过',
    createdAt: '2024-05-20 10:30:00'
  },
  {
    id: 2,
    name: '新品上市宣传图',
    type: 'image',
    category: '宣传类',
    size: '5.2MB',
    dimensions: '1920x1080',
    thumbnail: 'https://picsum.photos/200/300?random=2',
    storeName: '瑞幸咖啡深圳海岸城店',
    status: '已通过',
    createdAt: '2024-05-21 14:20:00'
  },
  {
    id: 3,
    name: '门店环境展示',
    type: 'image',
    category: '环境类',
    size: '8.1MB',
    dimensions: '1920x1080',
    thumbnail: 'https://picsum.photos/200/300?random=3',
    storeName: '麦当劳北京路店',
    status: '待审核',
    createdAt: '2024-05-22 09:15:00'
  },
  {
    id: 4,
    name: '产品特写素材包',
    type: 'video',
    category: '产品类',
    size: '512MB',
    duration: '00:12:45',
    thumbnail: 'https://picsum.photos/200/300?random=4',
    storeName: '奈雪の茶广州天河城店',
    status: '已通过',
    createdAt: '2024-05-23 16:30:00'
  },
  {
    id: 5,
    name: '促销活动海报',
    type: 'image',
    category: '促销类',
    size: '3.5MB',
    dimensions: '1080x1920',
    thumbnail: 'https://picsum.photos/200/300?random=5',
    storeName: '星巴克上海旗舰店',
    status: '已通过',
    createdAt: '2024-05-24 11:45:00'
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
  // 获取素材列表
  getMaterials: (params) => {
    let result = [...mockMaterials]
    if (params?.type) {
      result = result.filter(item => item.type === params.type)
    }
    if (params?.category) {
      result = result.filter(item => item.category === params.category)
    }
    if (params?.status) {
      result = result.filter(item => item.status === params.status)
    }
    if (params?.keyword) {
      result = result.filter(item =>
        item.name.includes(params.keyword) ||
        item.storeName.includes(params.keyword)
      )
    }
    return response({
      list: result,
      total: result.length,
      page: params?.page || 1,
      pageSize: params?.pageSize || 10
    })
  },

  // 上传素材
  uploadMaterial: (data) => {
    const newMaterial = {
      id: mockMaterials.length + 1,
      ...data,
      status: '待审核',
      createdAt: new Date().toLocaleString()
    }
    mockMaterials.push(newMaterial)
    return response(newMaterial)
  },

  // 删除素材
  deleteMaterial: (id) => {
    const index = mockMaterials.findIndex(item => item.id === id)
    if (index !== -1) {
      mockMaterials.splice(index, 1)
      return response({ success: true })
    }
    return response({ code: 404, message: '素材不存在' })
  },

  // 获取素材分类
  getCategories: () => {
    return response(['教程类', '宣传类', '环境类', '产品类', '促销类'])
  }
}