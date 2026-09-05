---
name: xmt-components
description: 小魔推公共组件库
type: project
---

## 公共组件 (src/components/xmt/)

| 组件 | 说明 | 主要props |
|------|------|-----------|
| PageHeader | 页面头部 | title, subtitle, extra |
| FilterToolbar | 筛选工具栏 | filters[], showSearch, showDateRange |
| StatTile | 统计卡片 | title, value, icon, trend, color |
| MetricGroupCard | 数据分组卡片 | title, icon, color, items[] |
| AiStaffCard | 员工卡片 | name, role, avatar, hot, buttonText |
| WorkCard | 作品卡片 | title, background, badge |
| EmptyPanel | 空状态 | message, icon, actionText |

## CSS变量

```scss
$bg-main: #f8f3ff;        // 背景色
$bg-card: #ffffff;        // 卡片背景
$color-purple: #7b50ff;   // 主题紫
$color-pink: #ff2fb6;     // 高亮粉
$color-blue: #2c8cff;     // 辅助蓝
$color-green: #20d482;    // 成功绿
$color-orange: #ff9860;   // 警告橙
$text-primary: #181224;   // 文字主色
$text-secondary: #746b80; // 文字次色
```