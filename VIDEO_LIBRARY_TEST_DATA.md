# 视频库测试数据说明

## 数据库迁移

已成功为 `xmt_content_templates` 表添加视频库相关字段：
- ✅ `video_url`: 视频文件URL
- ✅ `video_duration`: 视频时长(秒)
- ✅ `video_resolution`: 视频分辨率
- ✅ `video_size`: 视频文件大小
- ✅ `video_format`: 视频格式
- ✅ `thumbnail_time`: 缩略图截取时间点
- ✅ `aspect_ratio`: 宽高比
- ✅ `is_template`: 是否作为模板
- ✅ `template_tags`: 模板标签
- ✅ `difficulty`: 制作难度
- ✅ `industry`: 适用行业

## 测试数据统计

### 总览
- **总视频模板数**: 22个
- **总使用次数**: 4,922次

### 行业分布
| 行业 | 模板数量 |
|------|---------|
| 零售 | 7个 |
| 其他 | 5个 |
| 餐饮 | 4个 |
| 教育 | 2个 |
| 医疗 | 1个 |
| 房地产 | 1个 |
| 旅游 | 1个 |
| 汽车 | 1个 |

### 难度分布
| 难度 | 模板数量 |
|------|---------|
| 简单 | 12个 |
| 中等 | 8个 |
| 困难 | 2个 |

### 宽高比分布
| 宽高比 | 模板数量 |
|--------|---------|
| 16:9 (横屏) | 17个 |
| 9:16 (竖屏) | 4个 |
| 1:1 (方形) | 1个 |

### 分类分布
- 促销: 8个
- 自定义: 12个
- 公告: 1个
- 联系方式: 1个

## 热门模板 TOP 10

| 排名 | 模板名称 | 使用次数 | 行业 | 难度 |
|------|---------|---------|------|------|
| 1 | 节日祝福视频 | 534 | 其他 | 简单 |
| 2 | 限时秒杀活动 | 467 | 零售 | 简单 |
| 3 | 新品上市宣传 | 421 | 零售 | 简单 |
| 4 | 产品介绍短视频 | 389 | 零售 | 简单 |
| 5 | 美食短视频模板 | 342 | 餐饮 | 简单 |
| 6 | 服装展示视频 | 267 | 零售 | 中等 |
| 7 | 餐厅开业宣传视频 | 256 | 餐饮 | 简单 |
| 8 | 会员招募视频 | 234 | 零售 | 简单 |
| 9 | 在线教育广告 | 223 | 教育 | 简单 |
| 10 | 旅游宣传视频 | 212 | 旅游 | 中等 |

## 测试建议

### 1. API测试
使用提供的测试脚本测试所有API端点：
- Windows: `test_video_library_api.bat`
- Linux/Mac: `test_video_library_api.sh`

**重要**: 使用前需要替换脚本中的 `TOKEN` 为实际的JWT Token

### 2. 获取JWT Token
```bash
# 登录获取Token
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

### 3. 前端测试
1. 启动前端开发服务器:
```bash
cd admin
npm run dev
```

2. 访问视频库页面:
   - URL: `http://localhost:5173/video-library`
   - 路径: 内容管理 → 视频库

3. 测试功能:
   - ✅ 查看热门模板
   - ✅ 浏览全部模板
   - ✅ 按行业筛选(餐饮、零售、教育等)
   - ✅ 按难度筛选(简单、中等、困难)
   - ✅ 按宽高比筛选(16:9、9:16、1:1)
   - ✅ 关键词搜索
   - ✅ 排序功能(使用次数、时长、创建时间)
   - ✅ 查看模板详情
   - ✅ 使用模板功能
   - ✅ 分页功能

## 测试场景

### 场景1: 餐饮行业商家
1. 筛选行业: "餐饮"
2. 预期结果: 显示4个餐饮相关模板
3. 推荐模板:
   - 餐厅开业宣传视频
   - 咖啡店唯美宣传
   - 美食短视频模板

### 场景2: 短视频创作者
1. 筛选宽高比: "9:16" (竖屏)
2. 预期结果: 显示4个竖屏模板
3. 推荐模板:
   - 美食短视频模板
   - 服装展示视频
   - 产品介绍短视频
   - 限时秒杀活动

### 场景3: 新手用户
1. 筛选难度: "简单"
2. 预期结果: 显示12个简单模板
3. 排序: 按使用次数降序
4. 预期: 优先显示热门模板

### 场景4: 搜索促销模板
1. 关键词: "促销"
2. 预期结果: 显示所有名称或标签包含"促销"的模板
3. 推荐模板:
   - 餐厅开业宣传视频
   - 新品上市宣传
   - 超市促销活动

## 数据更新

### 添加新模板
```sql
INSERT INTO xmt_content_templates
(name, type, category, style, content, video_url, video_duration,
 aspect_ratio, is_template, template_tags, difficulty, industry,
 usage_count, is_public, status, create_time, update_time)
VALUES
('新模板名称', 'VIDEO', '分类', '风格', '{}',
 '/uploads/videos/new.mp4', 10, '16:9', 1,
 '["标签1", "标签2"]', 'easy', '行业',
 0, 1, 1, NOW(), NOW());
```

### 更新模板使用次数
```sql
UPDATE xmt_content_templates
SET usage_count = usage_count + 1
WHERE id = 模板ID;
```

### 删除测试数据
```sql
DELETE FROM xmt_content_templates
WHERE type = 'VIDEO' AND is_template = 1;
```

## 注意事项

1. **视频文件**: 当前测试数据的`video_url`和`preview_url`是占位符，实际使用时需要上传真实的视频文件和缩略图

2. **文件上传**: 需要配置文件上传功能，或使用OSS存储

3. **权限控制**: API需要JWT Token认证，确保用户已登录

4. **分页测试**: 可以测试不同页码和每页数量

5. **缓存**: 建议在生产环境启用Redis缓存以提高性能

## 扩展测试

### 压力测试
使用Apache Bench或wrk测试API性能:
```bash
# 测试热门模板接口
ab -n 1000 -c 10 -H "Authorization: Bearer TOKEN" \
   http://localhost/api/video-library/hot
```

### 并发测试
模拟多个用户同时使用模板:
```bash
# 使用模板接口并发测试
ab -n 100 -c 10 -p use_template.json -T application/json \
   -H "Authorization: Bearer TOKEN" \
   http://localhost/api/video-library/use/1
```

## 总结

✅ 数据库迁移成功
✅ 测试数据已插入 (22个模板)
✅ 数据分布合理 (多行业、多难度、多尺寸)
✅ API端点已配置
✅ 前端页面已创建
✅ 测试脚本已准备

现在可以开始测试视频库功能了！
