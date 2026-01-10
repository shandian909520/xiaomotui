const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  // 监控数据收集
  const errors = [];
  const consoleMessages = [];
  const networkRequests = [];
  const failedRequests = [];

  // 设置监控
  page.on('console', msg => {
    const text = msg.text();
    consoleMessages.push({ type: msg.type(), text });
    console.log(`[Console ${msg.type()}] ${text}`);
  });

  page.on('pageerror', error => {
    errors.push(error.message);
    console.log(`[Page Error] ${error.message}`);
  });

  page.on('response', response => {
    const url = response.url();
    const status = response.status();
    const method = response.request().method();

    networkRequests.push({ method, url, status });

    // 特别关注404错误
    if (status === 404) {
      failedRequests.push({ method, url, status });
      console.log(`[404 Error] ${method} ${url}`);
    }

    // 记录API请求
    if (url.includes('/api/') || url.includes('.js')) {
      console.log(`[Network] ${method} ${url} -> ${status}`);
    }
  });

  console.log('\n=== 测试开始: http://localhost:6151 ===\n');

  try {
    // 1. 访问首页
    console.log('Step 1: 访问首页...');
    await page.goto('http://localhost:6151', {
      waitUntil: 'networkidle',
      timeout: 30000
    });

    // 2. 等待页面加载
    console.log('\nStep 2: 等待页面完全加载(10秒)...');
    await page.waitForTimeout(10000);

    // 3. 检查页面标题
    console.log('\nStep 3: 检查页面标题...');
    const title = await page.title();
    console.log(`页面标题: ${title}`);

    // 4. 检查404错误
    console.log('\nStep 4: 检查404错误...');
    const has404ForRequestJs = failedRequests.some(r => r.url.includes('/api/request.js'));
    const has404ForAuthJs = failedRequests.some(r => r.url.includes('/api/modules/auth.js'));

    console.log(`/api/request.js 404错误: ${has404ForRequestJs ? '存在' : '已修复'}`);
    console.log(`/api/modules/auth.js 404错误: ${has404ForAuthJs ? '存在' : '已修复'}`);
    console.log(`总计404错误: ${failedRequests.length}个`);

    // 5. 检查#app容器
    console.log('\nStep 5: 检查#app容器内容...');
    const appContent = await page.locator('#app').innerHTML().catch(() => '');
    const hasContent = appContent.length > 100;
    console.log(`#app容器有内容: ${hasContent}`);
    console.log(`#app内容长度: ${appContent.length}字符`);

    // 6. 截图当前页面
    console.log('\nStep 6: 截图当前页面状态...');
    await page.screenshot({
      path: 'test-screenshot-after-fix.png',
      fullPage: true
    });
    console.log('截图已保存: test-screenshot-after-fix.png');

    // 7. 查找登录相关元素
    console.log('\nStep 7: 查找登录相关元素...');
    const loginForm = await page.locator('form, .login-form, [class*="login"]').count();
    const inputFields = await page.locator('input[type="text"], input[type="password"], input[placeholder*="账号"], input[placeholder*="密码"]').count();
    const buttons = await page.locator('button, .btn, [class*="button"]').count();

    console.log(`登录表单: ${loginForm}个`);
    console.log(`输入框: ${inputFields}个`);
    console.log(`按钮: ${buttons}个`);

    // 8. 如果找到登录表单,截图
    if (loginForm > 0 || inputFields > 0) {
      console.log('\nStep 8: 截图登录界面...');
      await page.screenshot({
        path: 'test-login-interface.png',
        fullPage: false
      });
      console.log('登录界面截图已保存: test-login-interface.png');
    }

    // 9. 统计网络请求
    console.log('\nStep 9: 网络请求统计...');
    const totalRequests = networkRequests.length;
    const successRequests = networkRequests.filter(r => r.status >= 200 && r.status < 300).length;
    const jsRequests = networkRequests.filter(r => r.url.endsWith('.js')).length;
    const jsFailedRequests = networkRequests.filter(r => r.url.endsWith('.js') && r.status === 404).length;

    console.log(`总请求数: ${totalRequests}`);
    console.log(`成功请求: ${successRequests}`);
    console.log(`JS文件请求: ${jsRequests}`);
    console.log(`JS文件404: ${jsFailedRequests}`);
    console.log(`失败请求: ${failedRequests.length}`);

    // 10. JavaScript控制台错误
    console.log('\nStep 10: JavaScript控制台错误...');
    const jsErrors = consoleMessages.filter(m => m.type === 'error');
    console.log(`控制台错误数: ${jsErrors.length}`);

    if (jsErrors.length > 0) {
      console.log('\n错误详情:');
      jsErrors.slice(0, 5).forEach((err, i) => {
        console.log(`  ${i+1}. ${err.text}`);
      });
    }

    // 详细报告
    console.log('\n\n=== 修复后测试报告 ===');
    console.log(`页面标题: ${title}`);
    console.log(`404错误数量: ${failedRequests.length}`);
    console.log(`/api/request.js 404: ${has404ForRequestJs ? '仍存在❌' : '已修复✓'}`);
    console.log(`/api/modules/auth.js 404: ${has404ForAuthJs ? '仍存在❌' : '已修复✓'}`);
    console.log(`页面内容渲染: ${hasContent ? '正常✓' : '异常❌'}`);
    console.log(`控制台错误: ${jsErrors.length}个`);
    console.log(`总网络请求: ${totalRequests}个`);
    console.log(`成功率: ${((successRequests/totalRequests)*100).toFixed(1)}%`);

    if (failedRequests.length > 0) {
      console.log('\n失败请求列表:');
      failedRequests.forEach(req => {
        console.log(`  - ${req.method} ${req.url} (${req.status})`);
      });
    }

    console.log('\n测试完成!');

  } catch (error) {
    console.error('测试失败:', error.message);
  } finally {
    await browser.close();
  }
})();
