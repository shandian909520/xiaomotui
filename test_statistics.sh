#!/bin/bash

# 获取token
TOKEN=$(curl -s -X POST http://localhost:8001/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' | \
  python -c "import sys, json; print(json.load(sys.stdin)['data']['token'])")

echo "获取Token成功: ${TOKEN:0:50}..."
echo ""

# 测试接口列表
declare -a endpoints=(
  "dashboard?merchant_id=1"
  "overview?merchant_id=1"
  "devices?merchant_id=1"
  "content?merchant_id=1"
  "publish?merchant_id=1"
  "users?merchant_id=1"
  "trend?merchant_id=1"
  "realtime?merchant_id=1"
  "export?merchant_id=1"
)

# 测试结果统计
SUCCESS=0
FAILED=0

echo "=========================================="
echo "开始测试统计分析接口"
echo "=========================================="
echo ""

for endpoint in "${endpoints[@]}"; do
  echo "测试: GET /api/statistics/$endpoint"

  response=$(curl -s -X GET "http://localhost:8001/api/statistics/$endpoint" \
    -H "Authorization: Bearer $TOKEN" \
    -w "\n%{http_code}")

  http_code=$(echo "$response" | tail -n1)
  body=$(echo "$response" | head -n-1)

  if [ "$http_code" = "200" ]; then
    echo "✓ 状态: 成功 (200)"
    SUCCESS=$((SUCCESS + 1))
  else
    echo "✗ 状态: 失败 ($http_code)"
    FAILED=$((FAILED + 1))
  fi

  echo "响应: $body"
  echo ""
  echo "----------------------------------------"
  echo ""
done

echo "=========================================="
echo "测试完成"
echo "=========================================="
echo "成功: $SUCCESS"
echo "失败: $FAILED"
echo "总计: $((SUCCESS + FAILED))"
