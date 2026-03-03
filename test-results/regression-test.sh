#!/bin/bash

# 小魔推管理系统回归测试脚本
# 测试时间: 2026-02-13
# 目标: 验证所有已修复的问题

API_BASE="http://localhost:8000"
ADMIN_BASE="http://localhost:3003"

echo "========================================"
echo "小魔推管理系统 - 回归测试"
echo "========================================"
echo "测试时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo "API地址: $API_BASE"
echo ""

# 用于存储测试结果
declare -A TEST_RESULTS
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# 测试函数
test_api() {
    local test_id=$1
    local test_name=$2
    local method=$3
    local endpoint=$4
    local expected_status=$5

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    echo ""
    echo "[$test_id] 测试: $test_name"
    echo "接口: $method $endpoint"

    # 发送请求
    if [ "$method" = "GET" ]; then
        RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$API_BASE$endpoint" \
            -H "Authorization: Bearer $TOKEN" \
            -H "Content-Type: application/json")
    fi

    # 提取状态码和响应体
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | sed '$d')

    echo "HTTP状态码: $HTTP_CODE"

    # 判断测试结果
    if [ "$HTTP_CODE" -eq "$expected_status" ]; then
        echo "✅ 测试通过"
        TEST_RESULTS[$test_id]="通过"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo "❌ 测试失败"
        echo "响应内容: $BODY"
        TEST_RESULTS[$test_id]="失败(HTTP $HTTP_CODE)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

# Step 1: 登录获取Token
echo ""
echo "========================================"
echo "步骤1: 管理员登录"
echo "========================================"

LOGIN_RESPONSE=$(curl -s -X POST "$API_BASE/api/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"admin123456"}')

echo "登录响应: $LOGIN_RESPONSE"

# 提取token
TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*"' | sed 's/"token":"//;s/"$//')

if [ -z "$TOKEN" ]; then
    echo "❌ 登录失败,无法获取Token"
    echo "响应内容: $LOGIN_RESPONSE"
    exit 1
else
    echo "✅ 登录成功"
    echo "Token: ${TOKEN:0:50}..."
fi

# Step 2: 执行回归测试
echo ""
echo "========================================"
echo "步骤2: 回归测试 - 验证已修复问题"
echo "========================================"

# RT-001: 仪表盘数据 (ISSUE-001)
test_api "RT-001" "仪表盘数据(ISSUE-001)" "GET" "/api/statistics/dashboard" 200

# RT-002: 数据统计概览 (ISSUE-002)
test_api "RT-002" "数据统计概览(ISSUE-002)" "GET" "/api/statistics/overview" 200

# RT-003: NFC触发记录 (ISSUE-003)
test_api "RT-003" "NFC触发记录(ISSUE-003)" "GET" "/api/merchant/nfc/trigger-records" 200

# RT-004: 生成任务列表 (ISSUE-004)
test_api "RT-004" "生成任务列表(ISSUE-004)" "GET" "/api/content/my" 200

# RT-005: 用户领取记录 (ISSUE-005)
test_api "RT-005" "用户领取记录(ISSUE-005)" "GET" "/api/coupon/my" 200

# RT-006: 系统用户管理 (ISSUE-006)
test_api "RT-006" "系统用户管理(ISSUE-006)" "GET" "/api/admin/users" 200

# RT-007: 系统设置 (ISSUE-006)
test_api "RT-007" "系统设置(ISSUE-006)" "GET" "/api/admin/settings" 200

# RT-008: 操作日志 (ISSUE-006)
test_api "RT-008" "操作日志(ISSUE-006)" "GET" "/api/admin/operation-logs" 200

# Step 3: 基础功能验证
echo ""
echo "========================================"
echo "步骤3: 基础功能验证(确保无新问题)"
echo "========================================"

# RT-009: 设备管理
test_api "RT-009" "设备管理" "GET" "/api/merchant/device/list" 200

# RT-010: AI内容创作历史
test_api "RT-010" "AI内容创作历史" "GET" "/api/ai-content/history" 200

# RT-011: 模板管理
test_api "RT-011" "模板管理" "GET" "/api/template/list" 200

# RT-012: 视频库管理
test_api "RT-012" "视频库管理" "GET" "/api/video-library/list" 200

# RT-013: 券码列表
test_api "RT-013" "券码列表" "GET" "/api/merchant/coupon/list" 200

# RT-014: 商户列表
test_api "RT-014" "商户列表" "GET" "/api/merchant/list" 200

# Step 4: 生成测试报告
echo ""
echo "========================================"
echo "测试结果汇总"
echo "========================================"
echo "总测试数: $TOTAL_TESTS"
echo "通过: $PASSED_TESTS"
echo "失败: $FAILED_TESTS"
echo "通过率: $(awk "BEGIN {printf \"%.2f\", ($PASSED_TESTS/$TOTAL_TESTS)*100}")%"
echo ""
echo "详细结果:"
for key in "${!TEST_RESULTS[@]}"; do
    echo "  [$key] ${TEST_RESULTS[$key]}"
done
echo ""

# 计算修复前后的改进
echo "========================================"
echo "修复前后对比"
echo "========================================"
echo "修复前: 8个失败接口"
echo "修复后: $FAILED_TESTS 个失败接口"
echo "改进: $((8 - FAILED_TESTS)) 个接口已修复"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo "✅ 所有问题已修复!"
    exit 0
else
    echo "⚠️ 仍有 $FAILED_TESTS 个接口存在问题"
    exit 1
fi
