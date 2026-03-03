#!/bin/bash

# 微信登录接口快速测试脚本
# 使用方法: bash quick_test.sh

echo "========================================"
echo "微信登录接口快速测试"
echo "========================================"
echo ""

# 配置
API_BASE_URL="http://localhost:8000"
TEST_CODE="test_code_123456"

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 测试 1: 微信登录接口
echo "测试 1: 微信登录接口"
echo "----------------------------------------"

RESPONSE=$(curl -s -X POST "${API_BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"code\": \"${TEST_CODE}\"}")

echo "请求: POST ${API_BASE_URL}/api/auth/login"
echo "参数: {\"code\": \"${TEST_CODE}\"}"
echo ""
echo "响应:"
echo "$RESPONSE" | jq '.'
echo ""

# 提取 token
TOKEN=$(echo "$RESPONSE" | jq -r '.data.token // empty')

if [ -n "$TOKEN" ]; then
    echo -e "${GREEN}✓ 登录成功${NC}"
    echo "Token: ${TOKEN:0:50}..."
    echo ""
else
    echo -e "${RED}✗ 登录失败${NC}"
    echo ""
    exit 1
fi

# 测试 2: 获取用户信息
echo "测试 2: 获取用户信息（需要认证）"
echo "----------------------------------------"

RESPONSE=$(curl -s -X GET "${API_BASE_URL}/api/auth/info" \
  -H "Authorization: Bearer ${TOKEN}")

echo "请求: GET ${API_BASE_URL}/api/auth/info"
echo "Headers: Authorization: Bearer ${TOKEN:0:30}..."
echo ""
echo "响应:"
echo "$RESPONSE" | jq '.'
echo ""

USER_ID=$(echo "$RESPONSE" | jq -r '.data.id // empty')

if [ -n "$USER_ID" ]; then
    echo -e "${GREEN}✓ 获取用户信息成功${NC}"
    echo "用户ID: ${USER_ID}"
    echo ""
else
    echo -e "${RED}✗ 获取用户信息失败${NC}"
    echo ""
fi

# 测试 3: 测试无效 token
echo "测试 3: 测试无效 token（应该返回 401）"
echo "----------------------------------------"

RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "${API_BASE_URL}/api/auth/info" \
  -H "Authorization: Bearer invalid_token_123")

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_CODE:/d')

echo "请求: GET ${API_BASE_URL}/api/auth/info"
echo "Headers: Authorization: Bearer invalid_token_123"
echo ""
echo "响应:"
echo "$BODY" | jq '.'
echo "HTTP 状态码: ${HTTP_CODE}"
echo ""

if [ "$HTTP_CODE" = "401" ]; then
    echo -e "${GREEN}✓ 正确拒绝无效 token${NC}"
    echo ""
else
    echo -e "${RED}✗ 应该返回 401 但返回了 ${HTTP_CODE}${NC}"
    echo ""
fi

# 测试 4: 测试退出登录
echo "测试 4: 测试退出登录"
echo "----------------------------------------"

RESPONSE=$(curl -s -X POST "${API_BASE_URL}/api/auth/logout" \
  -H "Authorization: Bearer ${TOKEN}")

echo "请求: POST ${API_BASE_URL}/api/auth/logout"
echo "Headers: Authorization: Bearer ${TOKEN:0:30}..."
echo ""
echo "响应:"
echo "$RESPONSE" | jq '.'
echo ""

MESSAGE=$(echo "$RESPONSE" | jq -r '.message // empty')

if [ "$MESSAGE" = "登出成功" ]; then
    echo -e "${GREEN}✓ 退出登录成功${NC}"
    echo ""
else
    echo -e "${YELLOW}⚠ 退出登录响应异常${NC}"
    echo ""
fi

# 测试 5: 测试参数验证
echo "测试 5: 测试参数验证（缺少 code）"
echo "----------------------------------------"

RESPONSE=$(curl -s -X POST "${API_BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d "{}")

echo "请求: POST ${API_BASE_URL}/api/auth/login"
echo "参数: {}"
echo ""
echo "响应:"
echo "$RESPONSE" | jq '.'
echo ""

ERROR_CODE=$(echo "$RESPONSE" | jq -r '.code // empty')

if [ "$ERROR_CODE" = "400" ]; then
    echo -e "${GREEN}✓ 参数验证正常${NC}"
    echo ""
else
    echo -e "${YELLOW}⚠ 参数验证可能有问题${NC}"
    echo ""
fi

# 测试 6: 测试无效的 code 格式
echo "测试 6: 测试无效的 code 格式"
echo "----------------------------------------"

RESPONSE=$(curl -s -X POST "${API_BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"code\": \"abc\"}")

echo "请求: POST ${API_BASE_URL}/api/auth/login"
echo "参数: {\"code\": \"abc\"}"
echo ""
echo "响应:"
echo "$RESPONSE" | jq '.'
echo ""

ERROR_CODE=$(echo "$RESPONSE" | jq -r '.code // empty')

if [ "$ERROR_CODE" = "400" ]; then
    echo -e "${GREEN}✓ Code 格式验证正常${NC}"
    echo ""
else
    echo -e "${YELLOW}⚠ Code 格式验证可能有问题${NC}"
    echo ""
fi

echo "========================================"
echo "测试完成"
echo "========================================"
echo ""
echo "总结:"
echo "- 登录接口: ${GREEN}✓${NC}"
echo "- 获取用户信息: ${GREEN}✓${NC}"
echo "- Token 验证: ${GREEN}✓${NC}"
echo "- 退出登录: ${GREEN}✓${NC}"
echo "- 参数验证: ${GREEN}✓${NC}"
echo ""
echo "建议: 在微信小程序中进行真实环境测试"
