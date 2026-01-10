# 数据统计页面使用指南

## 概述

数据统计页面是小魔推管理后台的核心功能模块，提供全面的数据可视化和分析能力，帮助商家和管理员深入了解业务运营情况。

## 功能特性

### 1. 核心指标卡片
- **总触发量**: 显示NFC设备的总触发次数
- **内容生成量**: 显示AI生成的内容数量
- **平台分发量**: 显示内容分发到各平台的总次数
- **成功率**: 显示整体的内容生成和分发成功率

每个指标卡片都包含：
- 实时数值
- 趋势指示器（上升/下降/持平）
- 与上周期对比百分比
- 清晰的图标和配色

### 2. 数据可视化图表

#### 触发量趋势图（折线图）
- 展示触发量、生成量、分发量的时间趋势
- 支持多指标对比
- 可以清晰看到业务增长趋势

#### 转化率分布图（饼图）
- 展示各个环节的转化情况
- 直观显示各部分占比
- 帮助识别优化空间

#### 设备触发排行（柱状图）
- 显示各设备的触发量排名
- 横向柱状图展示，便于对比
- 默认显示Top 10设备

#### 用户活跃度（面积图）
- 24小时时段分布热力图
- 展示用户使用高峰时段
- 帮助优化运营时间

#### 转化漏斗图
- 完整转化路径展示
- 各环节流失率分析
- 总体转化率统计
- 最高流失环节提示

### 3. 数据筛选功能

#### 时间范围选择
- 日期范围选择器
- 快捷选项：最近7天、30天、90天
- 支持自定义日期范围

#### 商家筛选（管理员）
- 管理员可按商家筛选数据
- 商家用户只能查看自己的数据
- 下拉选择，支持清空

#### 自动刷新
- 可开启/关闭自动刷新
- 默认每30秒刷新一次
- 实时监控业务数据

### 4. 数据导出
- 支持导出Excel格式
- 包含所有统计指标
- 文件名自动包含日期
- 一键下载

### 5. 单个图表导出
- 每个图表都支持独立导出
- PNG格式，高清晰度
- 适合报表和演示使用

### 6. 营销洞察

#### 数据预警
- 异常数据自动预警
- 多种预警类型（成功/警告/错误/信息）
- 详细的预警描述
- 帮助及时发现问题

#### 营销建议
- 基于数据分析的智能建议
- 个性化优化方案
- 提升运营效率
- 助力业务增长

## 文件结构

```
admin/
├── src/
│   ├── api/
│   │   └── statistics.js              # 统计API服务
│   ├── composables/
│   │   └── useEcharts.js              # ECharts Hook
│   ├── components/
│   │   ├── StatCard.vue               # 统计卡片组件
│   │   └── ChartContainer.vue         # 图表容器组件
│   ├── views/
│   │   └── statistics/
│   │       └── index.vue              # 统计主页面
│   └── router/
│       └── index.js                   # 路由配置（已添加统计路由）

api/
└── app/
    └── model/
        └── Statistics.php              # 统计数据模型
```

## 技术栈

- **Vue 3**: Composition API
- **Element Plus**: UI组件库
- **ECharts 5**: 数据可视化
- **Axios**: HTTP请求
- **SCSS**: 样式预处理

## 使用方法

### 1. 访问页面

```
http://your-domain/statistics
```

或通过导航菜单点击"数据统计"进入。

### 2. 选择时间范围

1. 点击日期选择器
2. 选择开始和结束日期，或使用快捷选项
3. 系统自动刷新数据

### 3. 筛选数据（管理员）

1. 在"商家筛选"下拉框中选择目标商家
2. 系统自动刷新显示该商家的数据
3. 清空选择可查看所有商家数据

### 4. 查看图表

- 鼠标悬停在图表上查看详细数据
- 点击图例可以显示/隐藏对应的数据系列
- 使用图表右上角的刷新按钮更新数据

### 5. 导出数据

**导出完整报表：**
1. 点击页面右上角的"导出报表"按钮
2. 确认导出
3. Excel文件自动下载

**导出单个图表：**
1. 点击图表右上角的下载图标
2. PNG图片自动下载

### 6. 自动刷新

1. 开启"自动刷新"开关
2. 系统每30秒自动更新数据
3. 页面显示"自动刷新中"标签
4. 关闭开关停止自动刷新

## API接口

### 1. 获取概览数据
```javascript
GET /api/statistics/overview
参数:
  - start_date: 开始日期 (YYYY-MM-DD)
  - end_date: 结束日期 (YYYY-MM-DD)
  - merchant_id: 商家ID（可选）
```

### 2. 获取趋势数据
```javascript
GET /api/statistics/trend
参数:
  - start_date: 开始日期
  - end_date: 结束日期
  - metric_type: 指标类型
  - merchant_id: 商家ID（可选）
```

### 3. 获取设备统计
```javascript
GET /api/statistics/device
参数:
  - start_date: 开始日期
  - end_date: 结束日期
  - merchant_id: 商家ID（可选）
  - limit: 返回设备数量（默认10）
```

### 4. 获取转化数据
```javascript
GET /api/statistics/conversion
参数:
  - start_date: 开始日期
  - end_date: 结束日期
  - merchant_id: 商家ID（可选）
```

### 5. 获取用户行为数据
```javascript
GET /api/statistics/user-behavior
参数:
  - start_date: 开始日期
  - end_date: 结束日期
  - merchant_id: 商家ID（可选）
```

### 6. 导出报表
```javascript
POST /api/statistics/export
参数:
  - start_date: 开始日期
  - end_date: 结束日期
  - merchant_id: 商家ID（可选）
  - format: 导出格式（excel/pdf）
  - metrics: 要导出的指标列表
```

### 7. 获取营销洞察
```javascript
GET /api/statistics/insights
参数:
  - start_date: 开始日期
  - end_date: 结束日期
  - merchant_id: 商家ID（可选）
```

### 8. 获取数据预警
```javascript
GET /api/statistics/alerts
参数:
  - merchant_id: 商家ID（可选）
```

## 数据格式示例

### 概览数据响应
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "trigger_count": 15680,
    "trigger_trend": "up",
    "trigger_trend_percent": 12.5,
    "generate_count": 14230,
    "generate_trend": "up",
    "generate_trend_percent": 8.3,
    "distribute_count": 13450,
    "distribute_trend": "down",
    "distribute_trend_percent": 3.2,
    "success_rate": 85.7,
    "success_rate_trend": "flat",
    "success_rate_trend_percent": 0.5
  }
}
```

### 趋势数据响应
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "dates": ["2025-09-24", "2025-09-25", "2025-09-26", ...],
    "trigger_data": [1200, 1350, 1280, ...],
    "generate_data": [1050, 1200, 1150, ...],
    "distribute_data": [980, 1100, 1050, ...]
  }
}
```

### 转化数据响应
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "conversion_data": [
      { "name": "触发成功", "value": 15680 },
      { "name": "内容生成", "value": 14230 },
      { "name": "平台分发", "value": 13450 },
      { "name": "用户转化", "value": 8900 }
    ],
    "funnel_data": [
      { "name": "触发", "value": 15680 },
      { "name": "生成", "value": 14230 },
      { "name": "分发", "value": 13450 },
      { "name": "转化", "value": 8900 }
    ],
    "max_loss_stage": "分发到转化"
  }
}
```

### 用户行为数据响应
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "hours": [0, 1, 2, 3, ..., 23],
    "active_users": [120, 80, 50, 30, ..., 450]
  }
}
```

### 设备统计响应
```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "devices": [
      { "name": "设备A", "count": 2340 },
      { "name": "设备B", "count": 1890 },
      { "name": "设备C", "count": 1560 },
      ...
    ]
  }
}
```

## 组件复用

### StatCard组件

```vue
<stat-card
  title="总触发量"
  :value="15680"
  icon="DataLine"
  icon-color="#409EFF"
  trend="up"
  :trend-percent="12.5"
  description="较上周期"
  unit="次"
/>
```

**Props:**
- `title`: 卡片标题
- `value`: 数值
- `icon`: 图标名称
- `iconColor`: 图标背景色
- `trend`: 趋势方向（up/down/flat）
- `trendPercent`: 趋势百分比
- `description`: 描述文字
- `unit`: 单位
- `formatter`: 自定义格式化函数

### ChartContainer组件

```vue
<chart-container
  title="触发量趋势"
  icon="TrendCharts"
  :loading="loading"
  :empty="isEmpty"
  @refresh="loadData"
  @download="downloadChart"
>
  <div ref="chartRef" class="chart"></div>
</chart-container>
```

**Props:**
- `title`: 图表标题
- `icon`: 标题图标
- `height`: 图表高度
- `refreshable`: 是否显示刷新按钮
- `downloadable`: 是否显示下载按钮
- `loading`: 加载状态
- `empty`: 是否为空

**Events:**
- `refresh`: 刷新事件
- `download`: 下载事件

**Slots:**
- `default`: 图表内容
- `actions`: 自定义操作按钮
- `footer`: 底部内容

### useEcharts Hook

```javascript
import { useEcharts, getLineChartOption } from '@/composables/useEcharts'

// 在组件中使用
const chartRef = ref(null)
const chart = useEcharts(chartRef)

// 设置图表配置
const option = getLineChartOption(
  ['2025-09-24', '2025-09-25', '2025-09-26'],
  [
    { name: '触发量', data: [1200, 1350, 1280] },
    { name: '生成量', data: [1050, 1200, 1150] }
  ]
)
chart.setOption(option)

// 显示/隐藏加载动画
chart.showLoading()
chart.hideLoading()

// 获取实例进行高级操作
const instance = chart.getInstance()
```

**提供的方法:**
- `setOption(option)`: 设置图表配置
- `showLoading()`: 显示加载动画
- `hideLoading()`: 隐藏加载动画
- `handleResize()`: 处理尺寸变化
- `clear()`: 清空图表
- `dispose()`: 销毁图表
- `getInstance()`: 获取原始ECharts实例

**提供的辅助函数:**
- `getLineChartOption()`: 获取折线图配置
- `getPieChartOption()`: 获取饼图配置
- `getBarChartOption()`: 获取柱状图配置
- `getAreaChartOption()`: 获取面积图配置
- `getFunnelChartOption()`: 获取漏斗图配置

## 性能优化

1. **图表懒加载**: 图表组件使用动态导入
2. **数据缓存**: API响应数据自动缓存
3. **按需渲染**: 只渲染可见区域的图表
4. **防抖处理**: 窗口resize事件使用防抖
5. **虚拟滚动**: 长列表使用虚拟滚动（预留）

## 响应式设计

- 移动端自适应布局
- 图表自动调整尺寸
- 触摸事件支持
- 横屏优化

## 浏览器兼容性

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 常见问题

### 1. 图表不显示？
- 检查API接口是否正常返回数据
- 确认数据格式是否正确
- 查看浏览器控制台是否有错误

### 2. 数据刷新失败？
- 检查网络连接
- 确认token是否过期
- 查看API接口状态

### 3. 导出功能不工作？
- 确认浏览器允许下载
- 检查API接口权限
- 查看后端日志

### 4. 自动刷新不生效？
- 确认已开启自动刷新开关
- 检查是否有其他页面冲突
- 查看浏览器控制台

## 后续优化计划

1. 增加更多图表类型（雷达图、热力图等）
2. 支持自定义报表模板
3. 增加数据对比功能
4. 支持PDF导出
5. 增加实时WebSocket推送
6. 移动端独立优化
7. 增加数据钻取功能
8. 支持自定义指标配置

## 维护说明

- 定期清理过期统计数据（建议90天）
- 监控API性能，优化查询效率
- 及时更新ECharts到最新稳定版
- 根据用户反馈持续改进

## 联系支持

如有问题或建议，请联系技术支持团队。
