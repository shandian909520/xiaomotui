@echo off
REM 视频库API测试脚本
REM 使用前请先获取有效的JWT Token

SET BASE_URL=http://localhost/api
SET TOKEN=your_jwt_token_here

echo ====================================
echo 视频库API测试
echo ====================================
echo.

REM 1. 获取筛选选项
echo [1] 获取筛选选项...
curl -X GET "%BASE_URL%/video-library/filters" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 2. 获取视频模板列表
echo [2] 获取视频模板列表 (第1页,12条)...
curl -X GET "%BASE_URL%/video-library/list?page=1&limit=12" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 3. 获取热门模板
echo [3] 获取热门模板 (前6个)...
curl -X GET "%BASE_URL%/video-library/hot?limit=6" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 4. 获取分类列表
echo [4] 获取分类列表...
curl -X GET "%BASE_URL%/video-library/categories" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 5. 按行业筛选
echo [5] 按行业筛选 (餐饮)...
curl -X GET "%BASE_URL%/video-library/list?industry=餐饮&page=1&limit=5" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 6. 按难度筛选
echo [6] 按难度筛选 (简单)...
curl -X GET "%BASE_URL%/video-library/list?difficulty=easy&page=1&limit=5" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 7. 按宽高比筛选
echo [7] 按宽高比筛选 (竖屏9:16)...
curl -X GET "%BASE_URL%/video-library/list?aspect_ratio=9:16&page=1&limit=5" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 8. 关键词搜索
echo [8] 关键词搜索 (促销)...
curl -X GET "%BASE_URL%/video-library/list?keyword=促销&page=1&limit=5" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 9. 获取统计数据
echo [9] 获取视频库统计数据...
curl -X GET "%BASE_URL%/video-library/statistics" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 10. 获取模板详情 (使用第一个模板ID)
echo [10] 获取模板详情 (ID=1)...
curl -X GET "%BASE_URL%/video-library/detail/1" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json"
echo.
echo.

REM 11. 使用模板 (复制模板)
echo [11] 使用模板 (复制模板 ID=1)...
curl -X POST "%BASE_URL%/video-library/use/1" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Content-Type: application/json" ^
  -d "{\"name\": \"我的新视频模板\"}"
echo.
echo.

echo ====================================
echo 测试完成!
echo ====================================
echo.
echo 提示:
echo 1. 请将 TOKEN 替换为您实际的JWT Token
echo 2. 请根据实际情况修改 BASE_URL
echo 3. 查看返回的JSON结果确认功能是否正常
pause
