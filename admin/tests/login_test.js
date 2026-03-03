import { firefox } from 'playwright';

console.log('Script started');

(async () => {
  try {
    console.log('Launching browser...');
    const browser = await firefox.launch();
    console.log('Browser launched');
    const page = await browser.newPage();
    
    // Capture console logs
    page.on('console', msg => console.log('BROWSER LOG:', msg.text()));
    
    console.log('Navigating to login page...');
    await page.goto('http://localhost:23003');
    
    // Wait for load
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'tests/login_page_loaded.png' });
    
    // Click "Account Login" tab if it exists
    const accountTab = page.locator('.el-tabs__item', { hasText: '账号登录' });
    if (await accountTab.isVisible()) {
        console.log('Switching to Account Login tab...');
        await accountTab.click();
    } else {
        console.log('Account Login tab NOT visible');
        const bodyText = await page.locator('body').textContent();
        console.log('Body text sample:', bodyText.substring(0, 200));
    }

    // Fill credentials
    console.log('Filling credentials...');
    try {
        await page.fill('input[placeholder="用户名"]', 'admin', { timeout: 5000 });
        await page.fill('input[placeholder="密码"]', 'admin123');
    } catch (e) {
        console.log('Failed to fill credentials:', e.message);
        await page.screenshot({ path: 'tests/login_fill_failed.png' });
        throw e;
    }

    // Click Login
    console.log('Clicking login...');
    await page.click('button:has-text("登录")');

    // Wait a bit for processing
    await page.waitForTimeout(2000);

    // Check localStorage
    const token = await page.evaluate(() => localStorage.getItem('token'));
    console.log('LOCALSTORAGE TOKEN:', token);

    // Wait for navigation or success
    try {
        await page.waitForURL('**/dashboard', { timeout: 10000 });
        console.log('Login Successful: Redirected to dashboard (or at least URL matched)');
        console.log('Current URL:', page.url());
        
        // Verify user info in header
        console.log('Verifying user info in header...');
        const usernameLocator = page.locator('.username');
        await usernameLocator.waitFor({ state: 'visible', timeout: 5000 });
        const usernameText = await usernameLocator.textContent();
        console.log('Username displayed: ' + usernameText);
        
        if (usernameText.includes('admin') || usernameText.includes('管理员')) {
             console.log('SUCCESS: User logged in and identified correctly.');
        } else {
             console.log('WARNING: Username text mismatch. Found: ' + usernameText);
        }

        await page.screenshot({ path: 'tests/login_success_verified.png' });

    } catch (e) {
        console.log('Login verification failed or timed out. Checking for error messages...');
        try {
          const errorLocator = page.locator('.el-message--error');
          if (await errorLocator.isVisible()) {
             const text = await errorLocator.textContent();
             console.log('Error message found: ' + text);
          }
        } catch (err) {
            console.log('No error message found.');
        }
        
        console.log('Current URL: ' + page.url());
        
        await page.screenshot({ path: 'tests/login_failed_final.png' });
        throw e; // Rethrow to ensure script fails
    }

    await browser.close();
  } catch (err) {
      console.error('Fatal execution error:', err);
      process.exit(1);
  }
})();
