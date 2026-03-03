from playwright.sync_api import sync_playwright
import time

console_errors = []

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport={"width": 1400, "height": 900})
    page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)

    # 1. 登录
    page.goto('http://localhost:3003')
    page.wait_for_load_state('networkidle')
    page.locator('input[placeholder="用户名"]').fill('admin')
    page.locator('input[placeholder="密码"]').fill('admin123456')
    page.locator('button.el-button--primary').click()
    page.wait_for_load_state('networkidle')
    time.sleep(3)

    # 保存token后直接导航
    token = page.evaluate("localStorage.getItem('token')")
    print(f"1. Token: {'已获取' if token else '未获取'}")

    # 2. 导航到视频库
    page.goto('http://localhost:3003/video-library')
    page.wait_for_load_state('networkidle')
    time.sleep(4)
    print(f"2. URL: {page.url}")

    # 3. 截图
    page.screenshot(path='D:/xiaomotui/screenshots/video_library_fixed.png', full_page=True)

    # 4. 检查数据
    # 热门模板
    hot_items = page.locator('.hot-templates .hot-item').all()
    print(f"3. 热门模板数: {len(hot_items)}")
    for item in hot_items:
        name = item.locator('h4').inner_text().strip()
        print(f"   - {name}")

    # 全部模板
    template_cards = page.locator('.template-list .template-card').all()
    total_text = page.locator('.stats').inner_text().strip()
    print(f"4. 全部模板: {total_text}, 卡片数: {len(template_cards)}")
    for card in template_cards:
        title = card.locator('.card-title').inner_text().strip()
        print(f"   - {title}")

    # 空状态
    empty = page.locator('.empty-state').all()
    if empty:
        print("5. 存在空状态提示")
    else:
        print("5. 无空状态（数据正常显示）")

    # 控制台错误
    if console_errors:
        print(f"6. 控制台错误 ({len(console_errors)}):")
        for err in console_errors:
            print(f"   - {err[:200]}")
    else:
        print("6. 无控制台错误")

    browser.close()
    print("\n测试完成!")
