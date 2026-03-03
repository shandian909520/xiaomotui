import { ref, onUnmounted } from 'vue'
import { ElNotification } from 'element-plus'
import { useUserStore } from '@/stores/user'

/**
 * WebSocket实时通信Composable
 * 用于接收实时告警和状态更新
 */
export function useWebSocket(options = {}) {
  const {
    autoReconnect = true,
    reconnectInterval = 5000,
    heartbeatInterval = 30000,
    onMessage = null,
    onConnect = null,
    onDisconnect = null,
    onError = null
  } = options

  // WebSocket连接实例
  const ws = ref(null)

  // 连接状态
  const connected = ref(false)
  const connecting = ref(false)

  // 重连定时器
  let reconnectTimer = null

  // 心跳定时器
  let heartbeatTimer = null

  // 用户Store
  const userStore = useUserStore()

  // 构建WebSocket URL
  const getWsUrl = () => {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
    const host = import.meta.env.VITE_WS_HOST || window.location.hostname
    const port = import.meta.env.VITE_WS_PORT || '9501'

    // 添加认证token
    const token = userStore.token || ''

    return `${protocol}//${host}:${port}/ws?token=${encodeURIComponent(token)}`
  }

  // 连接WebSocket
  const connect = () => {
    if (connected.value || connecting.value) {
      console.log('WebSocket已连接或正在连接中')
      return
    }

    try {
      connecting.value = true
      const url = getWsUrl()

      console.log('正在连接WebSocket...', url)

      ws.value = new WebSocket(url)

      // 连接打开
      ws.value.onopen = () => {
        console.log('WebSocket连接成功')
        connected.value = true
        connecting.value = false

        // 清除重连定时器
        if (reconnectTimer) {
          clearTimeout(reconnectTimer)
          reconnectTimer = null
        }

        // 启动心跳
        startHeartbeat()

        // 触发连接回调
        if (onConnect && typeof onConnect === 'function') {
          onConnect()
        }
      }

      // 接收消息
      ws.value.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data)
          console.log('收到WebSocket消息:', message)

          // 处理不同类型的消息
          handleMessage(message)

          // 触发消息回调
          if (onMessage && typeof onMessage === 'function') {
            onMessage(message)
          }
        } catch (error) {
          console.error('解析WebSocket消息失败:', error)
        }
      }

      // 连接关闭
      ws.value.onclose = (event) => {
        console.log('WebSocket连接关闭:', event.code, event.reason)
        connected.value = false
        connecting.value = false

        // 停止心跳
        stopHeartbeat()

        // 触发断开回调
        if (onDisconnect && typeof onDisconnect === 'function') {
          onDisconnect(event)
        }

        // 自动重连
        if (autoReconnect && event.code !== 1000) {
          console.log(`将在${reconnectInterval}ms后尝试重连...`)
          reconnectTimer = setTimeout(() => {
            connect()
          }, reconnectInterval)
        }
      }

      // 连接错误
      ws.value.onerror = (error) => {
        console.error('WebSocket连接错误:', error)
        connecting.value = false

        // 触发错误回调
        if (onError && typeof onError === 'function') {
          onError(error)
        }
      }

    } catch (error) {
      console.error('创建WebSocket连接失败:', error)
      connecting.value = false
    }
  }

  // 处理消息
  const handleMessage = (message) => {
    const { type, data, merchant_id } = message

    // 验证商家ID
    const currentMerchantId = userStore.merchantId
    if (merchant_id && currentMerchantId && merchant_id !== currentMerchantId) {
      return // 不是当前商家的消息
    }

    switch (type) {
      case 'alert':
        handleAlertMessage(data)
        break

      case 'status':
        handleStatusMessage(data)
        break

      case 'data':
        handleDataMessage(data)
        break

      case 'system':
        handleSystemMessage(data)
        break

      default:
        console.warn('未知的消息类型:', type)
    }
  }

  // 处理告警消息
  const handleAlertMessage = (data) => {
    if (data.batch) {
      // 批量告警
      ElNotification({
        title: `收到${data.count}条新告警`,
        message: '请及时处理',
        type: 'warning',
        duration: 0,
        position: 'top-right'
      })
    } else {
      // 单个告警
      const levelMap = {
        low: 'info',
        medium: 'warning',
        high: 'warning',
        critical: 'error'
      }

      ElNotification({
        title: data.alert_title || '设备告警',
        message: data.alert_message || '',
        type: levelMap[data.alert_level] || 'warning',
        duration: 0,
        position: 'top-right'
      })
    }
  }

  // 处理状态消息
  const handleStatusMessage = (data) => {
    console.log('设备状态更新:', data)
    // 可以通过事件总线发送到其他组件
    window.dispatchEvent(new CustomEvent('device-status-update', { detail: data }))
  }

  // 处理数据消息
  const handleDataMessage = (data) => {
    console.log('数据更新:', data)
    // 可以通过事件总线发送到其他组件
    window.dispatchEvent(new CustomEvent('data-update', { detail: data }))
  }

  // 处理系统消息
  const handleSystemMessage = (data) => {
    ElNotification({
      title: data.title || '系统通知',
      message: data.content || '',
      type: data.level === 'error' ? 'error' : (data.level === 'warning' ? 'warning' : 'info'),
      duration: data.level === 'critical' ? 0 : 4500,
      position: 'top-right'
    })
  }

  // 启动心跳
  const startHeartbeat = () => {
    stopHeartbeat()

    heartbeatTimer = setInterval(() => {
      if (connected.value && ws.value) {
        try {
          ws.value.send(JSON.stringify({ type: 'ping', timestamp: Date.now() }))
          console.log('发送心跳')
        } catch (error) {
          console.error('发送心跳失败:', error)
        }
      }
    }, heartbeatInterval)
  }

  // 停止心跳
  const stopHeartbeat = () => {
    if (heartbeatTimer) {
      clearInterval(heartbeatTimer)
      heartbeatTimer = null
    }
  }

  // 发送消息
  const send = (message) => {
    if (!connected.value || !ws.value) {
      console.warn('WebSocket未连接，无法发送消息')
      return false
    }

    try {
      const data = typeof message === 'string' ? message : JSON.stringify(message)
      ws.value.send(data)
      console.log('发送WebSocket消息:', message)
      return true
    } catch (error) {
      console.error('发送消息失败:', error)
      return false
    }
  }

  // 断开连接
  const disconnect = () => {
    console.log('主动断开WebSocket连接')

    // 停止自动重连
    if (reconnectTimer) {
      clearTimeout(reconnectTimer)
      reconnectTimer = null
    }

    // 停止心跳
    stopHeartbeat()

    // 关闭连接
    if (ws.value) {
      ws.value.close(1000, '主动断开')
      ws.value = null
    }

    connected.value = false
    connecting.value = false
  }

  // 重新连接
  const reconnect = () => {
    console.log('重新连接WebSocket')
    disconnect()

    setTimeout(() => {
      connect()
    }, 1000)
  }

  // 组件卸载时清理
  onUnmounted(() => {
    disconnect()
  })

  return {
    // 状态
    connected,
    connecting,

    // 方法
    connect,
    disconnect,
    reconnect,
    send,

    // WebSocket实例
    ws
  }
}

/**
 * 监听设备状态更新
 */
export function useDeviceStatusUpdate() {
  const callbacks = []

  const onUpdate = (callback) => {
    callbacks.push(callback)

    const handler = (event) => {
      callback(event.detail)
    }

    window.addEventListener('device-status-update', handler)

    // 返回清理函数
    return () => {
      const index = callbacks.indexOf(callback)
      if (index > -1) {
        callbacks.splice(index, 1)
      }
      window.removeEventListener('device-status-update', handler)
    }
  }

  return {
    onUpdate
  }
}

/**
 * 监听数据更新
 */
export function useDataUpdate() {
  const callbacks = []

  const onUpdate = (callback) => {
    callbacks.push(callback)

    const handler = (event) => {
      callback(event.detail)
    }

    window.addEventListener('data-update', handler)

    // 返回清理函数
    return () => {
      const index = callbacks.indexOf(callback)
      if (index > -1) {
        callbacks.splice(index, 1)
      }
      window.removeEventListener('data-update', handler)
    }
  }

  return {
    onUpdate
  }
}
