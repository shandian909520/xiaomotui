# 视频库功能实现文档

## 功能概述

视频库功能允许用户浏览、搜索和使用已有的视频模板,快速创建自己的视频内容。

## 实现的功能

### 后端功能

1. **VideoLibrary控制器** (`api/app/controller/VideoLibrary.php`)
   - 视频模板列表查询 (支持多条件筛选和排序)
   - 视频模板详情查看
   - 创建视频模板
   - 使用视频模板(复制模板到自己的账户)
   - 获取分类列表
   - 获取筛选选项
   - 热门模板推荐
   - 视频库统计数据

2. **API路由** (`api/route/app.php`)
   ```
   GET  /api/video-library/list          # 获取视频模板列表
   GET  /api/video-library/detail/:id    # 获取视频模板详情
   POST /api/video-library/create        # 创建视频模板
   POST /api/video-library/use/:id       # 使用视频模板
   GET  /api/video-library/categories    # 获取分类列表
   GET  /api/video-library/filters       # 获取筛选选项
   GET  /api/video-library/hot           # 获取热门模板
   GET  /api/video-library/statistics    # 获取统计数据
   ```

3. **数据库扩展** (`api/database/migrations/20250125000001_add_video_library_fields.sql`)
   - 为`xmt_content_templates`表添加视频特有字段:
     - `video_url`: 视频文件URL
     - `video_duration`: 视频时长(秒)
     - `video_resolution`: 视频分辨率
     - `video_size`: 视频文件大小
     - `video_format`: 视频格式
     - `thumbnail_time`: 缩略图截取时间点
     - `aspect_ratio`: 宽高比
     - `is_template`: 是否作为模板
     - `template_tags`: 模板标签
     - `difficulty`: 制作难度
     - `industry`: 适用行业

### 前端功能

1. **视频库页面** (`admin/src/views/video-library/index.vue`)
   - 热门模板展示
   - 模板网格展示
   - 多条件筛选(关键词、分类、行业、难度、宽高比)
   - 排序功能
   - 分页功能
   - 模板详情查看
   - 使用模板功能

2. **路由配置** (`admin/src/router/index.js`)
   - 添加`/video-library`路由

3. **导航菜单** (`admin/src/layout/Sidebar.vue`)
   - 在"内容管理"菜单中添加"视频库"入口

## 部署步骤

### 1. 执行数据库迁移

```bash
# 连接到数据库
mysql -u your_username -p your_database

# 执行迁移文件
source api/database/migrations/20250125000001_add_video_library_fields.sql
```

### 2. 验证数据库表结构

```sql
DESCRIBE xmt_content_templates;
```

确保新增字段已正确添加。

### 3. 测试后端API

#### 3.1 获取筛选选项

```bash
curl -X GET "http://your-domain/api/video-library/filters" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

预期返回:
```json
{
  "code": 200,
  "message": "获取筛选选项成功",
  "data": {
    "industries": ["餐饮", "零售", "教育", ...],
    "difficulties": {
      "easy": "简单",
      "medium": "中等",
      "hard": "困难"
    },
    "aspect_ratios": {...},
    "sort_options": {...}
  }
}
```

#### 3.2 获取视频模板列表

```bash
curl -X GET "http://your-domain/api/video-library/list?page=1&limit=12" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 3.3 获取热门模板

```bash
curl -X GET "http://your-domain/api/video-library/hot?limit=6" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 3.4 使用视频模板

```bash
curl -X POST "http://your-domain/api/video-library/use/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "我的新视频"}'
```

### 4. 测试前端功能

1. 启动前端开发服务器:
```bash
cd admin
npm run dev
```

2. 访问视频库页面:
   - 登录管理后台
   - 在左侧菜单中找到 "内容管理" -> "视频库"
   - 访问 `http://localhost:5173/video-library`

3. 功能测试:
   - ✅ 查看热门模板
   - ✅ 浏览全部模板
   - ✅ 使用筛选功能
   - ✅ 查看模板详情
   - ✅ 使用模板
   - ✅ 测试分页
   - ✅ 测试排序

## 核心功能说明

### 1. 视频模板筛选

支持的筛选条件:
- **关键词**: 搜索模板名称、分类、风格
- **分类**: 按业务类型筛选(菜单、促销、公告等)
- **行业**: 按行业筛选(餐饮、零售、教育等)
- **难度**: 按制作难度筛选(简单、中等、困难)
- **宽高比**: 16:9、9:16、1:1等
- **排序**: 按创建时间、使用次数、时长、名称排序

### 2. 使用视频模板流程

1. 用户在视频库中浏览模板
2. 选择感兴趣的模板,点击"使用此模板"
3. 系统复制模板内容,创建一个新的副本给用户
4. 用户可以在新副本基础上进行编辑和修改
5. 原始模板的使用次数自动+1

### 3. 权限控制

- **公开模板**: 所有用户都可以查看和使用
- **私有模板**: 只有模板创建者可以查看和使用
- **系统模板**: 由管理员创建,所有用户可用

## 数据结构

### ContentTemplate模型

视频模板的主要字段:

```javascript
{
  id: 1,
  name: "餐饮促销视频模板",
  type: "VIDEO",
  category: "促销",
  style: "现代",
  content: {
    scenes: [
      { text: "欢迎光临", duration: 3 },
      { text: "特价优惠", duration: 2 },
      { text: "欢迎下次光临", duration: 3 }
    ]
  },
  preview_url: "/uploads/templates/promotion_preview.jpg",
  video_url: "/uploads/videos/promotion_template.mp4",
  video_duration: 8,
  video_resolution: "1920x1080",
  video_format: "mp4",
  aspect_ratio: "16:9",
  template_tags: ["餐饮", "促销", "特价"],
  difficulty: "easy",
  industry: "餐饮",
  usage_count: 128,
  is_public: 1,
  is_template: 1,
  merchant_id: null,  // 系统模板
  create_time: "2025-01-25 12:00:00"
}
```

## 扩展建议

### 短期优化

1. **视频预览功能**
   - 集成视频播放器
   - 在线预览模板效果

2. **批量操作**
   - 批量使用模板
   - 批量导出模板

3. **收藏功能**
   - 用户可以收藏常用模板
   - 快速访问收藏的模板

### 长期规划

1. **AI智能推荐**
   - 根据用户行业、历史行为推荐模板
   - 个性化模板展示

2. **模板编辑器**
   - 在线编辑模板内容
   - 实时预览编辑效果

3. **模板市场**
   - 用户可以上传自己的模板
   - 模板交易和分享

4. **数据分析**
   - 模板使用趋势分析
   - 用户行为分析

## 常见问题

### Q1: 如何添加新的视频模板?

A: 可以通过以下两种方式:
1. 后端API: `POST /api/video-library/create`
2. 直接在数据库中插入记录(不推荐)

### Q2: 模板内容(content字段)的格式是什么?

A: content字段是JSON格式,用于存储模板的场景配置、文字内容、时间轴等信息。格式可以根据实际需求自定义。

### Q3: 如何修改视频文件的存储路径?

A: 在创建模板时,设置`video_url`和`preview_url`字段为实际的文件URL即可。可以使用本地路径或CDN URL。

### Q4: 热门模板是如何计算的?

A: 热门模板按`usage_count`(使用次数)降序排列,使用次数越多,排名越靠前。

## 技术栈

- **后端**: PHP 8.0+, ThinkPHP 8.0
- **前端**: Vue 3, Element Plus
- **数据库**: MySQL 5.7+
- **视频存储**: 本地存储或OSS

## 相关文件清单

### 后端文件
- `api/app/controller/VideoLibrary.php` - 视频库控制器
- `api/app/model/ContentTemplate.php` - 内容模板模型
- `api/database/migrations/20250125000001_add_video_library_fields.sql` - 数据库迁移文件
- `api/route/app.php` - 路由配置

### 前端文件
- `admin/src/views/video-library/index.vue` - 视频库页面
- `admin/src/router/index.js` - 路由配置
- `admin/src/layout/Sidebar.vue` - 导航菜单

## 总结

视频库功能为用户提供了一个便捷的视频模板管理平台,支持:
- ✅ 浏览和搜索视频模板
- ✅ 按多种条件筛选模板
- ✅ 查看模板详细信息
- ✅ 一键使用模板创建副本
- ✅ 热门模板推荐
- ✅ 完整的权限控制

用户可以快速找到合适的视频模板,并在其基础上创建自己的视频内容,大大提高了视频创作效率。
