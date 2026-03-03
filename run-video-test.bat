@echo off
echo ========================================
echo 视频库前端功能自动化测试
echo ========================================
echo.

echo [1/3] 检查环境...
node --version
if %errorlevel% neq 0 (
    echo 错误: 未找到 Node.js
    pause
    exit /b 1
)
echo.

echo [2/3] 确保测试目录存在...
if not exist "tests\screenshots\video-library" mkdir "tests\screenshots\video-library"
if not exist "tests\videos" mkdir "tests\videos"
echo.

echo [3/3] 运行测试...
echo.
echo 请确保:
echo   - 后端 API 服务已启动 (http://localhost:8000)
echo   - 前端管理后台已启动 (http://localhost:37073)
echo   - 测试账号 admin/admin123 可用
echo.
echo 测试开始...
echo.

node tests/video-library-test.js

echo.
echo ========================================
echo 测试完成
echo ========================================
echo.
echo 查看结果:
echo   - 截图: tests\screenshots\video-library\
echo   - JSON报告: tests\video-library-test-report.json
echo   - 视频录制: tests\videos\
echo.

echo 生成 HTML 报告...
node tests/generate-report.js

echo.
echo HTML 报告已生成: tests\video-library-test-report.html
echo.
pause
