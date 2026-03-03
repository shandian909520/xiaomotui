# 视频库前端功能自动化测试

## 测试概述

本测试套件使用 Playwright 自动化测试框架，对视频库的前端功能进行全面测试。

## 测试前准备

### 1. 启动后端服务
```bash
cd api
php think run
```
确保后端服务运行在 `http://localhost:8000`

### 2. 启动前端服务
```bash
cd admin
npm run dev
```
确保前端服务运行在 `http://localhost:37073`

### 3. 确保测试数据准备就绪
- 测试账号: `admin/admin123`
- 确保数据库中有视频模板数据

## 运行测试

### 方式一：使用批处理脚本（推荐）
```bash
run-video-test.bat
```

### 方式二：手动执行
```bash
# 创建必要的目录
mkdir tests\screenshots\video-library
mkdir tests\videos

# 运行测试脚本
node tests/video-library-test.js

# 生成 HTML 报告
node tests/generate-report.js
```

## 测试步骤

测试脚本会自动执行以下步骤：

1. **访问登录页面** - 访问 http://localhost:37073/login
2. **用户登录** - 使用 admin/admin123 登录系统
3. **访问视频库** - 导航到视频库页面
4. **测试热门模板区域** - 验证热门模板展示区域
5. **测试模板网格** - 查看全部模板网格展示
6. **测试行业筛选** - 筛选"餐饮"行业
7. **测试难度筛选** - 筛选"简单"难度
8. **测试宽高比筛选** - 筛选"9:16 竖屏"
9. **测试关键词搜索** - 搜索"促销"
10. **测试排序功能** - 按"使用次数"排序
11. **测试模板详情** - 点击模板查看详情
12. **测试分页功能** - 切换到下一页

## 测试结果

测试完成后，会生成以下文件：

### 截图文件
- 位置: `tests/screenshots/video-library/`
- 格式: PNG
- 包含每个测试步骤的截图

### JSON 报告
- 文件: `tests/video-library-test-report.json`
- 内容: 详细的测试结果数据

### HTML 报告
- 文件: `tests/video-library-test-report.html`
- 内容: 可视化的测试报告，包含所有截图和测试结果

### 视频录制
- 位置: `tests/videos/`
- 格式: WebM
- 内容: 完整的测试过程视频

## 查看测试报告

直接在浏览器中打开 `tests/video-library-test-report.html` 文件即可查看完整的测试报告，包括：
- 测试统计摘要
- 每个测试步骤的详细结果
- 所有测试截图
- 错误日志（如果有）

## 测试脚本说明

### video-library-test.js
主测试脚本，包含：
- 测试流程控制
- 页面交互逻辑
- 截图和视频录制
- 错误捕获
- 结果记录

### generate-report.js
HTML 报告生成器，将 JSON 结果转换为可视化的 HTML 报告。

## 故障排查

### 问题 1: 无法连接到页面
**解决方案**: 检查前端服务是否正常运行，访问 http://localhost:37073 确认

### 问题 2: 登录失败
**解决方案**:
- 确认后端服务正常运行
- 确认测试账号 admin/admin123 存在
- 检查网络连接

### 问题 3: 找不到页面元素
**解决方案**:
- 可能是页面加载时间过长，增加等待时间
- 检查页面选择器是否正确
- 查看截图确认实际页面结构

### 问题 4: Playwright 浏览器未安装
**解决方案**:
```bash
npx playwright install chromium
```

## 自定义测试

如需修改测试步骤，编辑 `tests/video-library-test.js` 文件：

```javascript
// 修改页面元素选择器
const industrySelect = await page.$('你的选择器');

// 修改测试数据
await searchInput.fill('你的搜索关键词');

// 添加新的测试步骤
console.log('测试新功能...');
const newElement = await page.$('.new-feature');
if (newElement) {
    // 执行操作
}
```

## 测试最佳实践

1. **测试环境隔离**：使用专门的测试数据库
2. **测试数据准备**：确保测试数据完整且一致
3. **并发测试**：避免同时运行多个测试实例
4. **定期运行**：在每次代码更新后运行测试
5. **结果归档**：保存历史测试报告用于对比

## 技术栈

- **测试框架**: Playwright 1.55.1
- **浏览器**: Chromium
- **Node.js**: 建议 v16+
- **操作系统**: Windows 10/11

## 支持

如有问题，请查看：
- Playwright 官方文档: https://playwright.dev/
- 项目 issue: (项目 issue 链接)
