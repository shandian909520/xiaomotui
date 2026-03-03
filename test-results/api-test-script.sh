#!/bin/bash

# 小魔推管理系统API测试脚本
# 测试时间: 2026-02-13

BASE_URL="http://localhost:8000"
TOKEN=""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 测试结果统计
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# 测试函数
test_api() {
    local test_name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_code="$5"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    echo -e "\n${YELLOW}测试 $TOTAL_TESTS: $test_name${NC}"
    echo "方法: $method"
    echo "端点: $BASE_URL$endpoint"

    if [ -n "$data" ]; then
        echo "请求数据: $data"
        RESPONSE=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $TOKEN" \
            -d "$data")
    else
        RESPONSE=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $TOKEN")
    fi

    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | head -n -1)

    echo "HTTP状态码: $HTTP_CODE"
    echo "响应体: $BODY"

    # 检查HTTP状态码
    if [ "$HTTP_CODE" == "$expected_code" ]; then
        # 检查业务状态码
        CODE=$(echo "$BODY" | grep -o '"code":[0-9]*' | cut -d':' -f2)
        if [ "$CODE" == "200" ]; then
            echo -e "${GREEN}✓ 测试通过${NC}"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            return 0
        else
            echo -e "${RED}✗ 测试失败: 业务状态码为 $CODE${NC}"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            return 1
        fi
    else
        echo -e "${RED}✗ 测试失败: HTTP状态码为 $HTTP_CODE (期望 $expected_code)${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

echo "====================================="
echo "小魔推管理系统API测试"
echo "====================================="
echo "测试环境: $BASE_URL"
echo "测试时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo "====================================="

# FP-001: 测试登录
echo -e "\n\n========== 模块一: 登录与认证 =========="
RESPONSE=$(curl -s -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"admin123456"}')

TOKEN=$(echo "$RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
echo "登录响应: $RESPONSE"
echo "获取到的Token: ${TOKEN:0:50}..."

if [ -n "$TOKEN" ]; then
    echo -e "${GREEN}✓ FP-001: 登录成功${NC}"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗ FP-001: 登录失败${NC}"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

# FP-004: 仪表盘数据
echo -e "\n\n========== 模块二: 仪表盘 =========="
test_api "FP-004: 获取仪表盘数据" "GET" "/api/statistics/dashboard" "" "200"

# FP-005: 设备管理
echo -e "\n\n========== 模块三: 设备管理 =========="
test_api "FP-005: 获取设备列表" "GET" "/api/merchant/device/list" "" "200"

# FP-006: 数据统计
echo -e "\n\n========== 模块四: 数据统计 =========="
test_api "FP-006: 获取统计概览" "GET" "/api/statistics/overview" "" "200"

# FP-007: NFC触发记录
echo -e "\n\n========== 模块五: NFC管理 =========="
test_api "FP-007: 获取NFC触发记录" "GET" "/api/merchant/nfc/trigger-records" "" "200"

# FP-008: AI创作
echo -e "\n\n========== 模块六: 内容管理 =========="
test_api "FP-008: 获取AI内容生成历史" "GET" "/api/ai-content/history" "" "200"

# FP-009: 生成任务
test_api "FP-009: 获取内容任务列表" "GET" "/api/content/my" "" "200"

# FP-010: 模板管理
test_api "FP-010: 获取模板列表" "GET" "/api/template/list" "" "200"

# FP-011: 视频库
test_api "FP-011: 获取视频库列表" "GET" "/api/video-library/list" "" "200"

# FP-012: 券码列表
echo -e "\n\n========== 模块七: 券码管理 =========="
test_api "FP-012: 获取券码列表" "GET" "/api/merchant/coupon/list" "" "200"

# FP-013: 用户领取记录
test_api "FP-013: 获取用户领取记录" "GET" "/api/coupon/my" "" "200"

# FP-014: 商户列表
echo -e "\n\n========== 模块八: 商户管理 =========="
test_api "FP-014: 获取商户列表" "GET" "/api/merchant/list" "" "200"

# FP-015: 系统用户管理
echo -e "\n\n========== 模块九: 系统管理 =========="
test_api "FP-015: 获取系统用户列表" "GET" "/api/admin/users" "" "200"

# FP-016: 系统设置
test_api "FP-016: 获取系统设置" "GET" "/api/admin/settings" "" "200"

# FP-017: 操作日志
test_api "FP-017: 获取操作日志" "GET" "/api/admin/operation-logs" "" "200"

# 打印测试结果
echo -e "\n\n====================================="
echo "测试结果统计"
echo "====================================="
echo -e "总测试数: $TOTAL_TESTS"
echo -e "${GREEN}通过: $PASSED_TESTS${NC}"
echo -e "${RED}失败: $FAILED_TESTS${NC}"
echo -e "通过率: $(awk "BEGIN {printf \"%.2f\", ($PASSED_TESTS/$TOTAL_TESTS)*100}")%"
echo "====================================="
