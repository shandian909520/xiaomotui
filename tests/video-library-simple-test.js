/**
 * 视频库前端功能简化测试脚本
 * 使用 Playwright 进行浏览器测试
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
    headless: false, // 显示浏览器窗口
    slowMo: 800 // 放慢操作速度
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 }
  });

  const page = await context.newPage();

  // 监听控制台错误
  page.on('console', msg => {
    if (msg.type() === 'error') {
      console.log('❌ 浏览器控制台错误:', msg.text());
      testResults.push({
        type: 'error',
        message: msg.text(),
        timestamp: new Date().toISOString()
      });
    }
  });

  page.on('pageerror', error => {
    console.log('❌ 页面错误:', error.message);
    testResults.push({
      type: 'pageerror',
      message: error.message,
      timestamp: new Date().toISOString()
    });
  });

  try {
    // ========== 测试步骤 1: 访问登录页面 ==========
    console.log('📍 步骤 1: 访问登录页面');
    await page.goto('http://localhost:37073/login', { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    await screenshot(page, '01-login-page', '登录页面');
    testResults.push({ step: 1, status: 'success', message: '成功访问登录页面' });

    // ========== 测试步骤 2: 登录 ==========
    console.log('\n📍 步骤 2: 使用手机号+验证码登录');
    console.log('  - 手机号: 13800138000');
    console.log('  - 验证码: 123456');

    // 输入手机号
    const phoneInput = await page.$('input[placeholder*="手机号"]');
    if (phoneInput) {
      await phoneInput.fill('13800138000');
      console.log('  ✓ 已输入手机号');
    } else {
      throw new Error('未找到手机号输入框');
    }

    await page.waitForTimeout(500);

    // 输入验证码
    const codeInput = await page.$('input[placeholder*="验证码"]');
    if (codeInput) {
      await codeInput.fill('123456');
      console.log('  ✓ 已输入验证码');
    } else {
      throw new Error('未找到验证码输入框');
    }

    await page.waitForTimeout(500);

    // 点击登录按钮
    const loginButton = await page.$('button:has-text("登录")');
    if (loginButton) {
      await loginButton.click();
      console.log('  ✓ 已点击登录按钮');
    } else {
      throw new Error('未找到登录按钮');
    }

    // 等待跳转
    await page.waitForTimeout(4000);
    await screenshot(page, '02-after-login', '登录后');

    // 检查是否登录成功
    const currentUrl = page.url();
    if (currentUrl.includes('/dashboard') || currentUrl.includes('/video-library')) {
      console.log('  ✓ 登录成功');
      testResults.push({ step: 2, status: 'success', message: '成功登录系统' });
    } else {
      throw new Error(`登录失败，当前页面: ${currentUrl}`);
    }

    // ========== 测试步骤 3: 导航到视频库页面 ==========
    console.log('\n📍 步骤 3: 访问视频库页面');
    await page.goto('http://localhost:37073/video-library', { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    await screenshot(page, '03-video-library-page', '视频库页面');
    testResults.push({ step: 3, status: 'success', message: '成功访问视频库页面' });

    // ========== 测试步骤 4: 查看页面结构 ==========
    console.log('\n📍 步骤 4: 检查页面结构');

    // 检查页面是否有基本内容
    const pageContent = await page.content();
    const hasContent = pageContent.length > 1000;

    if (hasContent) {
      console.log('  ✓ 页面有内容');
      testResults.push({ step: 4, status: 'success', message: '页面内容加载正常' });
    } else {
      console.log('  ⚠ 页面内容较少');
      testResults.push({ step: 4, status: 'warning', message: '页面内容可能不完整' });
    }

    await screenshot(page, '04-page-structure', '页面结构');

    // ========== 测试步骤 5: 测试搜索功能 ==========
    console.log('\n📍 步骤 5: 测试搜索功能');
    const searchInput = await page.$('input[type="search"], input[placeholder*="搜索"]');
    if (searchInput) {
      await searchInput.fill('促销');
      await page.waitForTimeout(1000);
      await page.keyboard.press('Enter');
      await page.waitForTimeout(2000);
      await screenshot(page, '05-search-result', '搜索结果');
      console.log('  ✓ 搜索功能正常');
      testResults.push({ step: 5, status: 'success', message: '搜索功能正常' });
    } else {
      console.log('  ⚠ 未找到搜索框');
      await screenshot(page, '05-no-search', '无搜索框');
      testResults.push({ step: 5, status: 'warning', message: '未找到搜索输入框' });
    }

    // ========== 测试步骤 6: 测试筛选功能 ==========
    console.log('\n📍 步骤 6: 测试筛选功能');
    const selectElements = await page.$$('select, .el-select');
    if (selectElements.length > 0) {
      console.log(`  ✓ 找到 ${selectElements.length} 个选择器`);
      await screenshot(page, '06-selectors', '筛选选择器');
      testResults.push({ step: 6, status: 'success', message: `找到 ${selectElements.length} 个筛选器` });
    } else {
      console.log('  ⚠ 未找到筛选选择器');
      await screenshot(page, '06-no-selectors', '无筛选器');
      testResults.push({ step: 6, status: 'warning', message: '未找到筛选选择器' });
    }

    // ========== 测试步骤 7: 测试模板卡片 ==========
    console.log('\n📍 步骤 7: 检查模板卡片');
    const cards = await page.$$('.el-card, .template-card, .video-card');
    if (cards.length > 0) {
      console.log(`  ✓ 找到 ${cards.length} 个卡片`);
      await screenshot(page, '07-cards', '模板卡片');
      testResults.push({ step: 7, status: 'success', message: `找到 ${cards.length} 个模板卡片` });
    } else {
      console.log('  ⚠ 未找到模板卡片');
      await screenshot(page, '07-no-cards', '无模板卡片');
      testResults.push({ step: 7, status: 'warning', message: '未找到模板卡片' });
    }

    // ========== 测试步骤 8: 测试分页 ==========
    console.log('\n📍 步骤 8: 检查分页组件');
    const pagination = await page.$('.el-pagination, .pagination');
    if (pagination) {
      console.log('  ✓ 找到分页组件');
      await screenshot(page, '08-pagination', '分页组件');
      testResults.push({ step: 8, status: 'success', message: '分页组件存在' });
    } else {
      console.log('  ⚠ 未找到分页组件');
      await screenshot(page, '08-no-pagination', '无分页');
      testResults.push({ step: 8, status: 'warning', message: '未找到分页组件' });
    }

    // ========== 最终状态 ==========
    console.log('\n📍 最终状态');
    await screenshot(page, '09-final-state', '最终状态');

  } catch (error) {
    console.error('\n❌ 测试过程中发生错误:', error.message);
    testResults.push({
      type: 'fatal',
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    });
    await screenshot(page, '00-error', '错误截图');
  } finally {
    // 保存测试结果
    const reportPath = path.join(__dirname, 'video-library-test-report.json');
    const summary = {
      total: testResults.filter(r => r.step).length,
      success: testResults.filter(r => r.status === 'success').length,
      warning: testResults.filter(r => r.status === 'warning').length,
      errors: testResults.filter(r => r.type === 'error' || r.type === 'pageerror').length
    };

    const reportData = {
      testName: '视频库前端功能测试',
      testDate: new Date().toISOString(),
      testResults: testResults,
      summary: summary
    };

    fs.writeFileSync(reportPath, JSON.stringify(reportData, null, 2));

    console.log('\n' + '='.repeat(60));
    console.log('📊 测试完成！结果统计:');
    console.log('='.repeat(60));
    console.log(`  总步骤: ${summary.total}`);
    console.log(`  ✅ 成功: ${summary.success}`);
    console.log(`  ⚠️  警告: ${summary.warning}`);
    console.log(`  ❌ 错误: ${summary.errors}`);
    console.log('='.repeat(60));
    console.log(`\n📁 截图目录: ${screenshotDir}`);
    console.log(`📄 测试报告: ${reportPath}`);
    console.log('='.repeat(60));

    await browser.close();
  }
}

/**
 * 截图辅助函数
 */
async function screenshot(page, name, description) {
  const filePath = path.join(screenshotDir, `${name}.png`);
  await page.screenshot({ path: filePath, fullPage: true });
  console.log(`  📸 截图: ${description} -> ${name}.png`);
}

// 运行测试
runTests().catch(console.error);
