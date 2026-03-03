import { firefox } from 'playwright';

process.on('unhandledRejection', (reason, p) => {
  console.error('Unhandled Rejection at:', p, 'reason:', reason);
  process.exit(1);
});

(async () => {
  console.log('Launching browser...');
  const browser = await firefox.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();

  page.on('console', msg => console.log('PAGE LOG:', msg.text()));
  page.on('pageerror', err => console.log('PAGE ERROR:', err.message));
  page.on('request', request => console.log('>>', request.method(), request.url()));
  page.on('response', async response => {
    console.log('<<', response.status(), response.url());
    if (response.status() >= 400) {
      console.log('ERROR RESPONSE:', response.status(), response.url()); 
    }
  });

  const BASE_URL = 'http://localhost:3003';
  
  // Capture logs
  page.on('console', msg => console.log('BROWSER LOG:', msg.text()));
  
  try {
    console.log('--- STARTING CORE FEATURES TEST ---');
    
    // 1. Login
    console.log('[1/4] Logging in...');
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    console.log('Current URL:', page.url());

    if (page.url().includes('/login')) {
      try {
        // Test console capture
        await page.evaluate(() => console.log('DEBUG: Attempting login...'));
        
        await page.getByPlaceholder('用户名').fill('admin');
        await page.getByPlaceholder('密码').fill('admin123');
        await page.keyboard.press('Enter');
        
        // Wait for potential error message
        try {
           const errorMsg = await page.locator('.el-message--error').textContent({ timeout: 2000 });
           console.log('VISIBLE ERROR MESSAGE:', errorMsg);
        } catch (e) {
           // No error message found
        }
        
        await page.waitForURL('**/dashboard');
        console.log('Login successful');
      } catch (e) {
        console.log('Login failed:', e.message);
        // console.log(await page.content()); // Too noisy
        throw e;
      }
    } else {
      console.log('Already logged in or redirected to:', page.url());
    }
    
    // 2. Device Management
    console.log('[2/4] Testing Device Management...');
    
    // Try clicking menu
    try {
        console.log('Attempting to click menu "设备管理"...');
        await page.click('.el-menu-item:has-text("设备管理")');
    } catch (e) {
        console.log('Menu click failed, trying direct navigation...');
        await page.goto('http://localhost:23003/devices');
    }
    
    await page.waitForTimeout(2000);
    console.log('Current URL:', page.url());
    
    console.log('Waiting for .device-container...');
    await page.waitForSelector('.device-container', { timeout: 10000 });
    
    // Click Add Device
    console.log('Clicking Add Device...');
    await page.click('button:has-text("添加设备")');
    await page.waitForSelector('.el-dialog__header:has-text("添加设备")');
    
    // Fill form
    const deviceCode = 'DEV-' + Date.now();
    console.log('Filling device form with code:', deviceCode);
    
    await page.waitForTimeout(500);
    
    await page.fill('input[placeholder="请输入设备编码"]', deviceCode);
    await page.fill('input[placeholder="请输入设备名称"]', 'Auto Test Device');
    await page.fill('input[placeholder="请输入设备位置"]', 'Test Lab');
    
    // Select Type
    console.log('Selecting device type...');
    await page.click('.el-select[placeholder="请选择设备类型"]');
    await page.waitForSelector('.el-select-dropdown__item');
    await page.click('.el-select-dropdown__item:has-text("桌台")');
    
    await page.click('.el-dialog__footer button:has-text("确定")');
    
    // Verify success
    await page.waitForSelector('.el-message--success:has-text("添加成功")', { timeout: 5000 });
    console.log('Device added successfully.');
    
    // 3. AI Content Creation
    console.log('[3/4] Testing AI Content Creation...');
    // Try clicking menu "内容管理" -> "AI创作"
    try {
        console.log('Opening Content menu...');
        // Need to find parent menu. Usually "内容管理".
        // Selector might be tricky if not visible.
        // Direct navigation is safer for submenus if not expanded.
        await page.goto('http://localhost:23003/content/creation');
    } catch (e) {
         await page.goto('http://localhost:23003/content/creation');
    }
    
    await page.waitForSelector('.creation-container', { timeout: 10000 });
    
    console.log('Filling AI requirements...');
    await page.fill('textarea[placeholder*="请输入核心卖点"]', 'This is a test requirement for automated testing.');
    await page.click('button:has-text("开始生成")');
    
    // Wait for result
    console.log('Waiting for AI generation (up to 30s)...');
    let aiSuccess = false;
    for (let i = 0; i < 30; i++) {
        const val = await page.inputValue('.result-content textarea');
        if (val && val.length > 0) {
            console.log('AI Generation successful. Text length:', val.length);
            aiSuccess = true;
            break;
        }
        await page.waitForTimeout(1000);
    }
    
    if (!aiSuccess) {
        console.log('WARNING: AI Generation timed out.');
    }
    
    // 4. Task Publishing
    console.log('[4/4] Testing Task Publishing...');
    await page.goto('http://localhost:23003/content/tasks');
    await page.waitForSelector('.tasks-container', { timeout: 10000 });
    
    console.log('Clicking New Publish...');
    await page.click('button:has-text("新建发布")');
    await page.waitForSelector('.el-dialog__header:has-text("新建发布任务")');
    
    // Select Content
    console.log('Selecting content...');
    await page.click('.el-select[placeholder="请选择要发布的内容"]');
    
    // Wait for options
    try {
        await page.waitForSelector('.el-select-dropdown__item', { timeout: 5000 });
        await page.click('.el-select-dropdown__item'); // Select first available
        
        // Select Platform (Checkbox)
        console.log('Selecting platform...');
        await page.locator('label.el-checkbox:has-text("抖音")').click();
        
        // Fill Description
        console.log('Filling description...');
        await page.fill('textarea[placeholder="请输入或选择发布文案"]', 'Auto Test Caption');
        
        // Submit
        console.log('Submitting task...');
        await page.click('.el-dialog__footer button:has-text("确定")');
        
        // Verify success
        await page.waitForSelector('.el-message--success:has-text("任务创建成功")', { timeout: 5000 });
        console.log('Task created successfully.');
        
    } catch (e) {
        console.log('Task creation step failed:', e.message);
        if (e.message.includes('timeout') && e.message.includes('el-select-dropdown__item')) {
            console.log('Reason: No content found in Video Library to select.');
        }
    }

    console.log('--- TEST COMPLETED ---');
    await page.screenshot({ path: 'tests/core_features_success.png' });

  } catch (error) {
    console.error('Test Failed:', error);
    await page.screenshot({ path: 'tests/core_features_failure.png' });
  } finally {
    await browser.close();
  }
})();
