import { onMounted, onBeforeUnmount, ref } from 'vue'
import * as echarts from 'echarts'

/**
 * ECharts Hook - 用于管理图表实例和响应式调整
 * @param {Ref} chartRef - 图表DOM元素的ref
 * @param {Object} options - 可选配置
 * @returns {Object} - 图表实例和相关方法
 */
export function useEcharts(chartRef, options = {}) {
  const chartInstance = ref(null)
  const isLoading = ref(false)

  // 默认主题色
  const defaultTheme = {
    color: [
      '#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de',
      '#3ba272', '#fc8452', '#9a60b4', '#ea7ccc'
    ]
  }

  /**
   * 初始化图表
   */
  const initChart = () => {
    if (!chartRef.value) {
      console.warn('Chart ref is not ready')
      return
    }

    // 如果已存在实例，先销毁
    if (chartInstance.value) {
      chartInstance.value.dispose()
    }

    // 创建新实例
    chartInstance.value = echarts.init(chartRef.value, options.theme || defaultTheme)

    // 监听窗口大小变化
    window.addEventListener('resize', handleResize)
  }

  /**
   * 设置图表配置
   * @param {Object} option - ECharts配置对象
   * @param {boolean} notMerge - 是否不与之前的option合并
   */
  const setOption = (option, notMerge = false) => {
    if (!chartInstance.value) {
      console.warn('Chart instance is not initialized')
      return
    }

    chartInstance.value.setOption(option, notMerge)
  }

  /**
   * 显示加载动画
   * @param {Object} loadingOption - 加载动画配置
   */
  const showLoading = (loadingOption = {}) => {
    if (!chartInstance.value) return

    isLoading.value = true
    chartInstance.value.showLoading({
      text: '加载中...',
      color: '#5470c6',
      textColor: '#000',
      maskColor: 'rgba(255, 255, 255, 0.8)',
      zlevel: 0,
      ...loadingOption
    })
  }

  /**
   * 隐藏加载动画
   */
  const hideLoading = () => {
    if (!chartInstance.value) return

    isLoading.value = false
    chartInstance.value.hideLoading()
  }

  /**
   * 处理窗口大小变化
   */
  const handleResize = () => {
    if (chartInstance.value) {
      chartInstance.value.resize()
    }
  }

  /**
   * 清空图表
   */
  const clear = () => {
    if (chartInstance.value) {
      chartInstance.value.clear()
    }
  }

  /**
   * 销毁图表实例
   */
  const dispose = () => {
    if (chartInstance.value) {
      chartInstance.value.dispose()
      chartInstance.value = null
    }
    window.removeEventListener('resize', handleResize)
  }

  /**
   * 获取图表实例 (用于高级操作)
   */
  const getInstance = () => {
    return chartInstance.value
  }

  // 组件挂载时初始化
  onMounted(() => {
    initChart()
  })

  // 组件卸载时清理
  onBeforeUnmount(() => {
    dispose()
  })

  return {
    chartInstance,
    isLoading,
    initChart,
    setOption,
    showLoading,
    hideLoading,
    handleResize,
    clear,
    dispose,
    getInstance
  }
}

/**
 * 获取通用图表配置
 */
export function getCommonChartOption() {
  return {
    grid: {
      left: '3%',
      right: '4%',
      bottom: '3%',
      top: '10%',
      containLabel: true
    },
    tooltip: {
      trigger: 'axis',
      backgroundColor: 'rgba(255, 255, 255, 0.9)',
      borderColor: '#ddd',
      borderWidth: 1,
      textStyle: {
        color: '#333'
      }
    },
    legend: {
      top: 0,
      textStyle: {
        color: '#666'
      }
    }
  }
}

/**
 * 获取折线图配置
 * @param {Array} xData - X轴数据
 * @param {Array} series - 系列数据
 * @param {Object} customOptions - 自定义配置
 */
export function getLineChartOption(xData, series, customOptions = {}) {
  return {
    ...getCommonChartOption(),
    xAxis: {
      type: 'category',
      data: xData,
      axisLine: {
        lineStyle: {
          color: '#ddd'
        }
      },
      axisLabel: {
        color: '#666'
      }
    },
    yAxis: {
      type: 'value',
      axisLine: {
        lineStyle: {
          color: '#ddd'
        }
      },
      axisLabel: {
        color: '#666'
      },
      splitLine: {
        lineStyle: {
          color: '#f0f0f0'
        }
      }
    },
    series: series.map(item => ({
      type: 'line',
      smooth: true,
      ...item
    })),
    ...customOptions
  }
}

/**
 * 获取饼图配置
 * @param {Array} data - 数据
 * @param {Object} customOptions - 自定义配置
 */
export function getPieChartOption(data, customOptions = {}) {
  return {
    ...getCommonChartOption(),
    tooltip: {
      trigger: 'item',
      formatter: '{a} <br/>{b}: {c} ({d}%)'
    },
    series: [
      {
        name: '数据分布',
        type: 'pie',
        radius: ['40%', '70%'],
        avoidLabelOverlap: false,
        itemStyle: {
          borderRadius: 10,
          borderColor: '#fff',
          borderWidth: 2
        },
        label: {
          show: true,
          formatter: '{b}: {d}%'
        },
        emphasis: {
          label: {
            show: true,
            fontSize: 16,
            fontWeight: 'bold'
          }
        },
        data: data
      }
    ],
    ...customOptions
  }
}

/**
 * 获取柱状图配置
 * @param {Array} xData - X轴数据
 * @param {Array} series - 系列数据
 * @param {Object} customOptions - 自定义配置
 */
export function getBarChartOption(xData, series, customOptions = {}) {
  return {
    ...getCommonChartOption(),
    xAxis: {
      type: 'category',
      data: xData,
      axisLine: {
        lineStyle: {
          color: '#ddd'
        }
      },
      axisLabel: {
        color: '#666'
      }
    },
    yAxis: {
      type: 'value',
      axisLine: {
        lineStyle: {
          color: '#ddd'
        }
      },
      axisLabel: {
        color: '#666'
      },
      splitLine: {
        lineStyle: {
          color: '#f0f0f0'
        }
      }
    },
    series: series.map(item => ({
      type: 'bar',
      barWidth: '60%',
      ...item
    })),
    ...customOptions
  }
}

/**
 * 获取面积图配置
 * @param {Array} xData - X轴数据
 * @param {Array} series - 系列数据
 * @param {Object} customOptions - 自定义配置
 */
export function getAreaChartOption(xData, series, customOptions = {}) {
  return {
    ...getCommonChartOption(),
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: xData,
      axisLine: {
        lineStyle: {
          color: '#ddd'
        }
      },
      axisLabel: {
        color: '#666'
      }
    },
    yAxis: {
      type: 'value',
      axisLine: {
        lineStyle: {
          color: '#ddd'
        }
      },
      axisLabel: {
        color: '#666'
      },
      splitLine: {
        lineStyle: {
          color: '#f0f0f0'
        }
      }
    },
    series: series.map(item => ({
      type: 'line',
      smooth: true,
      areaStyle: {},
      ...item
    })),
    ...customOptions
  }
}

/**
 * 获取漏斗图配置
 * @param {Array} data - 数据
 * @param {Object} customOptions - 自定义配置
 */
export function getFunnelChartOption(data, customOptions = {}) {
  return {
    ...getCommonChartOption(),
    tooltip: {
      trigger: 'item',
      formatter: '{a} <br/>{b}: {c}'
    },
    series: [
      {
        name: '转化漏斗',
        type: 'funnel',
        left: '10%',
        top: 60,
        bottom: 60,
        width: '80%',
        min: 0,
        max: 100,
        minSize: '0%',
        maxSize: '100%',
        sort: 'descending',
        gap: 2,
        label: {
          show: true,
          position: 'inside',
          formatter: '{b}: {c}'
        },
        labelLine: {
          length: 10,
          lineStyle: {
            width: 1,
            type: 'solid'
          }
        },
        itemStyle: {
          borderColor: '#fff',
          borderWidth: 1
        },
        emphasis: {
          label: {
            fontSize: 20
          }
        },
        data: data
      }
    ],
    ...customOptions
  }
}
