/**
 * 视频库前端功能自动化测试脚本
 * 使用 Playwright 进行浏览器测试
 *
 * 测试步骤：
 * 1. 访问登录页面
 * 2. 使用账号 admin/admin123 登录
 * 3. 访问视频库页面
 * 4. 测试所有功能并截图
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

// 创建截图目录
const screenshotDir = path.join(__dirname, 'screenshots', 'video-library');
if (!fs.existsSync(screenshotDir)) {
  fs.mkdirSync(screenshotDir, { recursive: true });
}

// 测试结果记录
const testResults = [];

async function runTests() {
  console.log('开始视频库前端功能测试...\n');

  const browser = await chromium.launch({
    headless: false, // 显示浏览器窗口，方便观察测试过程
    slowMo: 500 // 放慢操作速度，便于观察
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    recordVideo: {
      dir: path.join(__dirname, 'videos'),
      size: { width: 1920, height: 1080 }
    }
  });

  const page = await context.newPage();

  // 监听控制台消息和错误
  page.on('console', msg => {
    if (msg.type() === 'error') {
      console.log('浏览器控制台错误:', msg.text());
      testResults.push({
        type: 'error',
        message: msg.text(),
        timestamp: new Date().toISOString()
      });
    }
  });

  page.on('pageerror', error => {
    console.log('页面错误:', error.message);
    testResults.push({
      type: 'pageerror',
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    });
  });

  try {
    // 测试步骤 1: 访问登录页面
    console.log('步骤 1: 访问登录页面 http://localhost:37073/login');
    await page.goto('http://localhost:37073/login', { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await screenshot(page, '01-login-page', '登录页面');
    testResults.push({ step: 1, status: 'success', message: '成功访问登录页面' });

    // 测试步骤 2: 登录
    console.log('\n步骤 2: 使用手机号+验证码登录 (13800138000/123456)');
    await page.fill('input[placeholder*="手机号"], input[placeholder*="手机"]', '13800138000');
    await page.fill('input[placeholder*="验证码"]', '123456');
    await page.click('button:has-text("登录"), button.el-button--primary');
    await page.waitForTimeout(3000);
    await screenshot(page, '02-after-login', '登录后首页');
    testResults.push({ step: 2, status: 'success', message: '成功登录系统' });

    // 测试步骤 3: 导航到视频库页面
    console.log('\n步骤 3: 导航到视频库页面');
    await page.goto('http://localhost:37073/video-library', { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    await screenshot(page, '03-video-library-page', '视频库页面');
    testResults.push({ step: 3, status: 'success', message: '成功访问视频库页面' });

    // 测试步骤 4.1: 查看热门模板展示区域
    console.log('\n步骤 4.1: 查看热门模板展示区域');
    const hotTemplatesSection = await page.$('.hot-templates, .popular-templates, [class*="hot"], [class*="popular"]');
    if (hotTemplatesSection) {
      await screenshot(page, '04-hot-templates', '热门模板展示区域');
      testResults.push({ step: '4.1', status: 'success', message: '热门模板展示区域正常显示' });
    } else {
      await screenshot(page, '04-hot-templates-not-found', '未找到热门模板区域');
      testResults.push({ step: '4.1', status: 'warning', message: '未找到热门模板展示区域' });
    }

    // 测试步骤 4.2: 查看全部模板网格展示
    console.log('\n步骤 4.2: 查看全部模板网格展示');
    const templateGrid = await page.$('.template-grid, .video-grid, [class*="grid"], .el-row');
    if (templateGrid) {
      await screenshot(page, '05-template-grid', '全部模板网格展示');
      const templateCount = await page.$$eval('.template-card, .video-card, .el-col', elements => elements.length);
      testResults.push({ step: '4.2', status: 'success', message: `模板网格显示正常，找到 ${templateCount} 个模板卡片` });
    } else {
      await screenshot(page, '05-template-grid-not-found', '未找到模板网格');
      testResults.push({ step: '4.2', status: 'warning', message: '未找到模板网格展示区域' });
    }

    // 测试步骤 4.3: 测试行业筛选（选择"餐饮"）
    console.log('\n步骤 4.3: 测试行业筛选 - 选择"餐饮"');
    const industrySelect = await page.$('select[name="industry"], .industry-select, [class*="industry"] select, .el-select:has-text("行业")');
    if (industrySelect) {
      await industrySelect.click();
      await page.waitForTimeout(500);
      await page.click('text=餐饮, .el-select-dropdown__item:has-text("餐饮")');
      await page.waitForTimeout(2000);
      await screenshot(page, '06-industry-filter', '行业筛选-餐饮');
      testResults.push({ step: '4.3', status: 'success', message: '成功筛选"餐饮"行业' });
    } else {
      await screenshot(page, '06-industry-filter-not-found', '未找到行业筛选器');
      testResults.push({ step: '4.3', status: 'warning', message: '未找到行业筛选器' });
    }

    // 测试步骤 4.4: 测试难度筛选（选择"简单"）
    console.log('\n步骤 4.4: 测试难度筛选 - 选择"简单"');
    const difficultySelect = await page.$('select[name="difficulty"], .difficulty-select, [class*="difficulty"] select, .el-select:has-text("难度")');
    if (difficultySelect) {
      await difficultySelect.click();
      await page.waitForTimeout(500);
      await page.click('text=简单, .el-select-dropdown__item:has-text("简单")');
      await page.waitForTimeout(2000);
      await screenshot(page, '07-difficulty-filter', '难度筛选-简单');
      testResults.push({ step: '4.4', status: 'success', message: '成功筛选"简单"难度' });
    } else {
      await screenshot(page, '07-difficulty-filter-not-found', '未找到难度筛选器');
      testResults.push({ step: '4.4', status: 'warning', message: '未找到难度筛选器' });
    }

    // 测试步骤 4.5: 测试宽高比筛选（选择"9:16 竖屏"）
    console.log('\n步骤 4.5: 测试宽高比筛选 - 选择"9:16 竖屏"');
    const ratioSelect = await page.$('select[name="ratio"], .ratio-select, [class*="ratio"] select, .el-select:has-text("宽高比")');
    if (ratioSelect) {
      await ratioSelect.click();
      await page.waitForTimeout(500);
      await page.click('text=9:16, text=竖屏, .el-select-dropdown__item:has-text("9:16")');
      await page.waitForTimeout(2000);
      await screenshot(page, '08-ratio-filter', '宽高比筛选-9:16竖屏');
      testResults.push({ step: '4.5', status: 'success', message: '成功筛选"9:16 竖屏"' });
    } else {
      await screenshot(page, '08-ratio-filter-not-found', '未找到宽高比筛选器');
      testResults.push({ step: '4.5', status: 'warning', message: '未找到宽高比筛选器' });
    }

    // 测试步骤 4.6: 测试关键词搜索（输入"促销"）
    console.log('\n步骤 4.6: 测试关键词搜索 - 输入"促销"');
    const searchInput = await page.$('input[type="search"], input[name="keyword"], input[placeholder*="搜索"], .search-input');
    if (searchInput) {
      await searchInput.fill('促销');
      await page.waitForTimeout(1000);
      // 按回车或点击搜索按钮
      await page.keyboard.press('Enter');
      await page.waitForTimeout(2000);
      await screenshot(page, '09-keyword-search', '关键词搜索-促销');
      testResults.push({ step: '4.6', status: 'success', message: '成功搜索关键词"促销"' });
    } else {
      await screenshot(page, '09-keyword-search-not-found', '未找到搜索输入框');
      testResults.push({ step: '4.6', status: 'warning', message: '未找到搜索输入框' });
    }

    // 测试步骤 4.7: 测试排序功能（选择"使用次数"）
    console.log('\n步骤 4.7: 测试排序功能 - 选择"使用次数"');
    const sortSelect = await page.$('select[name="sort"], .sort-select, [class*="sort"] select, .el-select:has-text("排序")');
    if (sortSelect) {
      await sortSelect.click();
      await page.waitForTimeout(500);
      await page.click('text=使用次数, .el-select-dropdown__item:has-text("使用次数")');
      await page.waitForTimeout(2000);
      await screenshot(page, '10-sort-by-usage', '排序-使用次数');
      testResults.push({ step: '4.7', status: 'success', message: '成功按"使用次数"排序' });
    } else {
      await screenshot(page, '10-sort-not-found', '未找到排序选择器');
      testResults.push({ step: '4.7', status: 'warning', message: '未找到排序选择器' });
    }

    // 测试步骤 4.8: 点击某个模板查看详情
    console.log('\n步骤 4.8: 点击模板查看详情');
    const firstTemplate = await page.$('.template-card, .video-card, .el-card');
    if (firstTemplate) {
      await firstTemplate.click();
      await page.waitForTimeout(2000);
      await screenshot(page, '11-template-detail', '模板详情对话框');
      testResults.push({ step: '4.8', status: 'success', message: '成功打开模板详情' });
    } else {
      await screenshot(page, '11-no-template-found', '未找到可点击的模板');
      testResults.push({ step: '4.8', status: 'warning', message: '未找到可点击的模板' });
    }

    // 测试步骤 4.9: 在详情对话框中查看模板信息
    console.log('\n步骤 4.9: 查看模板详情信息');
    const dialogVisible = await page.$('.el-dialog, .modal, [role="dialog"]');
    if (dialogVisible) {
      // 获取模板详情信息
      const detailInfo = await page.evaluate(() => {
        const dialog = document.querySelector('.el-dialog, .modal, [role="dialog"]');
        if (dialog) {
          return {
            title: dialog.querySelector('.title, h2, h3')?.textContent?.trim(),
            content: dialog.querySelector('.content, .body')?.textContent?.trim().substring(0, 200)
          };
        }
        return null;
      });
      testResults.push({ step: '4.9', status: 'success', message: '模板详情信息显示正常', detail: detailInfo });
      await screenshot(page, '12-template-detail-info', '模板详情信息');
    } else {
      testResults.push({ step: '4.9', status: 'warning', message: '详情对话框未打开' });
    }

    // 关闭详情对话框
    const closeButton = await page.$('.el-dialog__close, .close-button, button:has-text("关闭")');
    if (closeButton) {
      await closeButton.click();
      await page.waitForTimeout(1000);
    }

    // 测试步骤 4.10: 测试分页功能
    console.log('\n步骤 4.10: 测试分页功能');
    const pagination = await page.$('.el-pagination, .pagination');
    if (pagination) {
      await screenshot(page, '13-pagination', '分页组件');
      // 尝试点击下一页
      const nextPageButton = await page.$('.btn-next, .pagination-next, [class*="next"]:not([disabled])');
      if (nextPageButton) {
        await nextPageButton.click();
        await page.waitForTimeout(2000);
        await screenshot(page, '14-pagination-next', '下一页');
        testResults.push({ step: '4.10', status: 'success', message: '分页功能正常，成功切换到下一页' });
      } else {
        testResults.push({ step: '4.10', status: 'success', message: '分页组件显示正常，但没有下一页按钮（可能已是最后一页）' });
      }
    } else {
      await screenshot(page, '13-pagination-not-found', '未找到分页组件');
      testResults.push({ step: '4.10', status: 'warning', message: '未找到分页组件' });
    }

    // 最终截图
    await screenshot(page, '15-final-state', '最终状态');

  } catch (error) {
    console.error('测试过程中发生错误:', error);
    testResults.push({
      type: 'fatal',
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    });
    await screenshot(page, 'error-screenshot', '错误截图');
  } finally {
    // 保存测试结果
    const reportPath = path.join(__dirname, 'video-library-test-report.json');
    fs.writeFileSync(reportPath, JSON.stringify({
      testName: '视频库前端功能测试',
      testDate: new Date().toISOString(),
      testResults: testResults,
      summary: {
        total: testResults.filter(r => r.step).length,
        success: testResults.filter(r => r.status === 'success').length,
        warning: testResults.filter(r => r.status === 'warning').length,
        errors: testResults.filter(r => r.type === 'error' || r.type === 'pageerror').length
      }
    }, null, 2));

    console.log('\n测试完成！');
    console.log(`截图保存在: ${screenshotDir}`);
    console.log(`测试报告保存在: ${reportPath}`);
    console.log(`视频录制保存在: ${path.join(__dirname, 'videos')}`);

    await browser.close();
  }
}

/**
 * 截图辅助函数
 */
async function screenshot(page, name, description) {
  const filePath = path.join(screenshotDir, `${name}.png`);
  await page.screenshot({ path: filePath, fullPage: true });
  console.log(`  ✓ 截图: ${description} -> ${name}.png`);
}

// 运行测试
runTests().catch(console.error);
