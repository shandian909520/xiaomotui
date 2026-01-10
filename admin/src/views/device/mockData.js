// 设备管理页面的模拟数据
// 用于开发和测试

export const mockDevices = [
  {
    id: 1,
    device_code: 'NFC001',
    device_name: '大厅桌台1号',
    type: 'TABLE',
    location: '一楼大厅左侧第一桌',
    description: '靠窗位置，客流量大',
    status: 1,
    battery_level: 85,
    trigger_mode: 'VIDEO',
    template_id: 1,
    last_heartbeat: '2025-10-01 10:30:25',
    created_at: '2025-09-01 10:00:00',
    updated_at: '2025-10-01 10:30:25'
  },
  {
    id: 2,
    device_code: 'NFC002',
    device_name: '收银台设备',
    type: 'COUNTER',
    location: '一楼收银台',
    description: '主收银台',
    status: 1,
    battery_level: 92,
    trigger_mode: 'COUPON',
    redirect_url: 'https://example.com/coupons',
    last_heartbeat: '2025-10-01 10:29:50',
    created_at: '2025-09-01 10:00:00',
    updated_at: '2025-10-01 10:29:50'
  },
  {
    id: 3,
    device_code: 'NFC003',
    device_name: '入口欢迎设备',
    type: 'ENTRANCE',
    location: '店铺主入口',
    description: '门口迎宾位置',
    status: 1,
    battery_level: 45,
    trigger_mode: 'WIFI',
    wifi_ssid: 'Store_WiFi',
    wifi_password: 'password123',
    last_heartbeat: '2025-10-01 10:28:15',
    created_at: '2025-09-01 10:00:00',
    updated_at: '2025-10-01 10:28:15'
  },
  {
    id: 4,
    device_code: 'NFC004',
    device_name: '墙面广告位',
    type: 'WALL',
    location: '二楼楼梯口',
    description: '广告展示区',
    status: 0,
    battery_level: 15,
    trigger_mode: 'MENU',
    redirect_url: 'https://example.com/menu',
    last_heartbeat: '2025-09-30 18:20:00',
    created_at: '2025-09-01 10:00:00',
    updated_at: '2025-09-30 18:20:00'
  },
  {
    id: 5,
    device_code: 'NFC005',
    device_name: '包厢桌台',
    type: 'TABLE',
    location: '二楼VIP包厢',
    description: 'VIP客户专用',
    status: 2,
    battery_level: 68,
    trigger_mode: 'CONTACT',
    contact_qr: 'https://example.com/qr/wework.png',
    last_heartbeat: '2025-10-01 09:00:00',
    created_at: '2025-09-01 10:00:00',
    updated_at: '2025-10-01 09:00:00'
  },
  {
    id: 6,
    device_code: 'NFC006',
    device_name: '大厅桌台2号',
    type: 'TABLE',
    location: '一楼大厅右侧第一桌',
    description: '靠近洗手间',
    status: 1,
    battery_level: 78,
    trigger_mode: 'VIDEO',
    template_id: 2,
    last_heartbeat: '2025-10-01 10:31:00',
    created_at: '2025-09-01 10:00:00',
    updated_at: '2025-10-01 10:31:00'
  },
  {
    id: 7,
    device_code: 'NFC007',
    device_name: '前台设备',
    type: 'COUNTER',
    location: '一楼前台',
    description: '接待前台',
    status: 1,
    battery_level: 95,
    trigger_mode: 'COUPON',
    redirect_url: 'https://example.com/welcome-coupon',
    last_heartbeat: '2025-10-01 10:30:55',
    created_at: '2025-09-01 10:00:00',
    updated_at: '2025-10-01 10:30:55'
  },
  {
    id: 8,
    device_code: 'NFC008',
    device_name: '后门设备',
    type: 'ENTRANCE',
    location: '后门出入口',
    description: '员工通道',
    status: 0,
    battery_level: 30,
    trigger_mode: 'WIFI',
    wifi_ssid: 'Staff_WiFi',
    wifi_password: 'staff2024',
    last_heartbeat: '2025-09-29 22:00:00',
    created_at: '2025-09-01 10:00:00',
    updated_at: '2025-09-29 22:00:00'
  }
]

export const mockTemplates = [
  {
    id: 1,
    name: '默认视频模板',
    description: '标准欢迎视频模板',
    status: 1
  },
  {
    id: 2,
    name: '节日祝福模板',
    description: '节日特别版视频模板',
    status: 1
  },
  {
    id: 3,
    name: '促销活动模板',
    description: '促销活动宣传视频',
    status: 1
  },
  {
    id: 4,
    name: '会员专享模板',
    description: '会员专属视频模板',
    status: 1
  }
]

// 设备类型选项
export const deviceTypes = [
  { label: '桌台', value: 'TABLE' },
  { label: '墙面', value: 'WALL' },
  { label: '柜台', value: 'COUNTER' },
  { label: '入口', value: 'ENTRANCE' }
]

// 设备状态选项
export const deviceStatus = [
  { label: '在线', value: 1, type: 'success' },
  { label: '离线', value: 0, type: 'danger' },
  { label: '维护', value: 2, type: 'warning' }
]

// 触发模式选项
export const triggerModes = [
  { label: '视频生成', value: 'VIDEO', description: 'NFC触发后生成个性化视频' },
  { label: '优惠券', value: 'COUPON', description: '跳转到优惠券页面' },
  { label: 'WiFi连接', value: 'WIFI', description: '自动连接WiFi' },
  { label: '好友添加', value: 'CONTACT', description: '添加企业微信' },
  { label: '菜单展示', value: 'MENU', description: '展示电子菜单' }
]

// 获取模拟设备列表（支持分页和筛选）
export function getMockDevices(params = {}) {
  const { page = 1, limit = 20, keyword = '', status = '', type = '' } = params

  let filteredDevices = [...mockDevices]

  // 关键词筛选
  if (keyword) {
    filteredDevices = filteredDevices.filter(device =>
      device.device_code.toLowerCase().includes(keyword.toLowerCase()) ||
      device.device_name.toLowerCase().includes(keyword.toLowerCase())
    )
  }

  // 状态筛选
  if (status !== '') {
    filteredDevices = filteredDevices.filter(device => device.status === status)
  }

  // 类型筛选
  if (type) {
    filteredDevices = filteredDevices.filter(device => device.type === type)
  }

  // 分页
  const total = filteredDevices.length
  const start = (page - 1) * limit
  const end = start + limit
  const list = filteredDevices.slice(start, end)

  return {
    code: 0,
    message: 'success',
    data: list,
    pagination: {
      page,
      limit,
      total,
      pages: Math.ceil(total / limit)
    }
  }
}

// 获取模拟设备详情
export function getMockDevice(id) {
  const device = mockDevices.find(d => d.id === id)
  if (device) {
    return {
      code: 0,
      message: 'success',
      data: device
    }
  }
  return {
    code: 404,
    message: '设备不存在',
    data: null
  }
}

// 获取模拟模板列表
export function getMockTemplates() {
  return {
    code: 0,
    message: 'success',
    data: mockTemplates,
    pagination: {
      page: 1,
      limit: 100,
      total: mockTemplates.length,
      pages: 1
    }
  }
}

export default {
  mockDevices,
  mockTemplates,
  deviceTypes,
  deviceStatus,
  triggerModes,
  getMockDevices,
  getMockDevice,
  getMockTemplates
}
