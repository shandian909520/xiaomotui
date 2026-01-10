const playwright = require('playwright');

(async () => {
  const browser = await playwright.chromium.launch({
    headless: false,
    args: ['--start-maximized']
  });

  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 }
  });

  const page = await context.newPage();

  const consoleLogs = [];
  const errors = [];
  const networkErrors = [];

  // 监听控制台消息
  page.on('console', msg => {
    consoleLogs.push({
      type: msg.type(),
      text: msg.text(),
      location: msg.location()
    });
  });

  // 监听页面错误
  page.on('pageerror', error => {
    errors.push(error.message);
  });

  // 监听网络请求
  page.on('response', response => {
    if (response.status() >= 400) {
      networkErrors.push({
        url: response.url(),
        status: response.status(),
        statusText: response.statusText()
      });
    }
  });

  console.log('正在访问 http://localhost:6151...');

  try {
    await page.goto('http://localhost:6151', {
      waitUntil: 'networkidle',
      timeout: 10000
    });

    console.log('页面加载完成，等待3秒...');
    await page.waitForTimeout(3000);

    // 获取页面信息
    const pageInfo = await page.evaluate(() => {
      return {
        title: document.title,
        bodyText: document.body.innerText.substring(0, 500),
        hasVueApp: !!document.querySelector('#app'),
        appHTML: document.querySelector('#app')?.innerHTML.substring(0, 1000) || 'No #app found',
        hasLoginForm: !!document.querySelector('form') || !!document.querySelector('input[type="password"]'),
        loginElements: {
          forms: document.querySelectorAll('form').length,
          inputs: document.querySelectorAll('input').length,
          buttons: document.querySelectorAll('button').length
        },
        visibleElements: document.body.querySelectorAll('*:not([style*="display: none"])').length
      };
    });

    console.log('\n===== 页面信息 =====');
    console.log(JSON.stringify(pageInfo, null, 2));

    console.log('\n===== 控制台日志（最后20条）=====');
    console.log(JSON.stringify(consoleLogs.slice(-20), null, 2));

    console.log('\n===== 页面错误 =====');
    console.log(JSON.stringify(errors, null, 2));

    console.log('\n===== 网络错误（404等）=====');
    console.log(JSON.stringify(networkErrors, null, 2));

    // 截图
    console.log('\n正在截图...');
    await page.screenshot({
      path: 'test-screenshots/homepage-full.png',
      fullPage: true
    });

    await page.screenshot({
      path: 'test-screenshots/homepage-viewport.png',
      fullPage: false
    });

    console.log('截图已保存到 test-screenshots/ 目录');

    // 查找登录相关元素
    const loginInfo = await page.evaluate(() => {
      const usernameInputs = Array.from(document.querySelectorAll('input[type="text"], input[placeholder*="用户"], input[placeholder*="账号"], input[placeholder*="手机"]'));
      const passwordInputs = Array.from(document.querySelectorAll('input[type="password"]'));
      const buttons = Array.from(document.querySelectorAll('button'));

      return {
        usernameInputs: usernameInputs.map(el => ({
          type: el.type,
          placeholder: el.placeholder,
          name: el.name,
          id: el.id,
          visible: el.offsetParent !== null
        })),
        passwordInputs: passwordInputs.map(el => ({
          type: el.type,
          placeholder: el.placeholder,
          name: el.name,
          id: el.id,
          visible: el.offsetParent !== null
        })),
        buttons: buttons.map(el => ({
          text: el.innerText,
          type: el.type,
          className: el.className,
          visible: el.offsetParent !== null
        }))
      };
    });

    console.log('\n===== 登录表单元素 =====');
    console.log(JSON.stringify(loginInfo, null, 2));

    console.log('\n测试完成！浏览器将保持打开5秒以便查看...');
    await page.waitForTimeout(5000);

  } catch (error) {
    console.error('测试过程中出错:', error);
  } finally {
    await browser.close();
  }
})();
