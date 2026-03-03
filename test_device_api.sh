#!/bin/bash

# 设备管理API完整测试脚本
# 作者: Claude AI
# 日期: 2026-01-25

API_BASE="http://localhost:8001/api"
TOKEN=""
DEVICE_ID=""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 测试结果统计
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
    ((PASSED_TESTS++))
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
    ((FAILED_TESTS++))
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# 测试函数
test_api() {
    local test_name=$1
    local method=$2
    local url=$3
    local data=$4
    local expected_code=$5

    ((TOTAL_TESTS++))
    log_info "测试 $test_name"

    # 构建curl命令
    if [ -z "$data" ]; then
        if [ -z "$TOKEN" ]; then
            response=$(curl -s -X $method "$url" -H "Content-Type: application/json")
        else
            response=$(curl -s -X $method "$url" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN")
        fi
    else
        if [ -z "$TOKEN" ]; then
            response=$(curl -s -X $method "$url" -H "Content-Type: application/json" -d "$data")
        else
            response=$(curl -s -X $method "$url" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" -d "$data")
        fi
    fi

    # 解析响应
    actual_code=$(echo $response | grep -o '"code":[0-9]*' | cut -d: -f2)

    if [ "$actual_code" = "$expected_code" ]; then
        log_success "$test_name - 状态码: $actual_code"
        echo "响应: $response"
        return 0
    else
        log_error "$test_name - 期望: $expected_code, 实际: $actual_code"
        echo "响应: $response"
        return 1
    fi
}

echo "======================================"
echo "设备管理API完整测试"
echo "======================================"
echo ""

# ==================== 第一步: 登录获取token ====================
log_info "================= 第一步: 用户登录 =================="

# 首先发送验证码
log_info "发送验证码到 13800138000"
test_api "发送验证码" "POST" "$API_BASE/auth/send-code" '{"phone":"13800138000"}' "200"
echo ""

# 登录
log_info "手机号登录"
response=$(curl -s -X POST "$API_BASE/auth/phone-login" \
    -H "Content-Type: application/json" \
    -d '{"phone":"13800138000","code":"123456"}')

echo "登录响应: $response"

# 提取token
TOKEN=$(echo $response | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    log_error "登录失败，无法获取token"
    # 尝试使用万能测试码 123456
    response=$(curl -s -X POST "$API_BASE/auth/phone-login" \
        -H "Content-Type: application/json" \
        -d '{"phone":"13800138000","code":"123456"}')

    # 检查是否是验证码过期问题，如果是，直接创建测试用户和token
    if echo "$response" | grep -q "验证码错误或已过期"; then
        log_warning "验证码验证失败，这可能是正常行为（生产环境需要真实验证码）"
        log_info "注意: 在真实环境中，需要先通过短信服务获取验证码"
    fi

    # 尝试使用已知的测试token（如果数据库中有）
    # 这里我们需要检查数据库是否有测试用户
    log_info "检查数据库中是否有测试用户..."
    exit 1
fi

log_success "登录成功，获取到token: ${TOKEN:0:50}..."
echo ""

# ==================== 第二步: 设备列表测试 ====================
log_info "================= 第二步: 获取设备列表 =================="
test_api "获取设备列表(默认)" "GET" "$API_BASE/merchant/device/list" "" "200"
test_api "获取设备列表(分页)" "GET" "$API_BASE/merchant/device/list?page=1&limit=10" "" "200"
test_api "获取设备列表(状态筛选)" "GET" "$API_BASE/merchant/device/list?status=1" "" "200"
test_api "获取设备列表(关键字搜索)" "GET" "$API_BASE/merchant/device/list?keyword=test" "" "200"
echo ""

# ==================== 第三步: 创建设备测试 ====================
log_info "================= 第三步: 创建设备 =================="

# 测试正常创建
create_data='{
    "device_code": "TEST'$(date +%s)'",
    "device_name": "测试设备001",
    "type": "TABLE",
    "trigger_mode": "VIDEO",
    "location": "一楼A区",
    "template_id": 1,
    "redirect_url": "https://example.com"
}'

response=$(curl -s -X POST "$API_BASE/merchant/device/create" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "$create_data")

echo "创建设备响应: $response"

# 提取设备ID
DEVICE_ID=$(echo $response | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)

if [ -n "$DEVICE_ID" ]; then
    log_success "设备创建成功，设备ID: $DEVICE_ID"
else
    log_error "设备创建失败"
fi
echo ""

# ==================== 第四步: 获取设备详情 ====================
log_info "================= 第四步: 获取设备详情 =================="

if [ -n "$DEVICE_ID" ]; then
    test_api "获取设备详情" "GET" "$API_BASE/merchant/device/$DEVICE_ID" "" "200"
else
    log_warning "没有设备ID，跳过详情测试"
fi
echo ""

# ==================== 第五步: 更新设备 ====================
log_info "================= 第五步: 更新设备 =================="

if [ -n "$DEVICE_ID" ]; then
    update_data='{
        "device_name": "测试设备001(已更新)",
        "location": "一楼B区"
    }'

    test_api "更新设备信息" "PUT" "$API_BASE/merchant/device/$DEVICE_ID/update" "$update_data" "200"
else
    log_warning "没有设备ID，跳过更新测试"
fi
echo ""

# ==================== 第六步: 更新设备状态 ====================
log_info "================= 第六步: 更新设备状态 =================="

if [ -n "$DEVICE_ID" ]; then
    test_api "更新设备状态为在线" "PUT" "$API_BASE/merchant/device/$DEVICE_ID/status" '{"status":1}' "200"
    test_api "更新设备状态为离线" "PUT" "$API_BASE/merchant/device/$DEVICE_ID/status" '{"status":0}' "200"
else
    log_warning "没有设备ID，跳过状态更新测试"
fi
echo ""

# ==================== 第七步: 更新设备配置 ====================
log_info "================= 第七步: 更新设备配置 =================="

if [ -n "$DEVICE_ID" ]; then
    config_data='{
        "template_id": 2,
        "trigger_mode": "COUPON",
        "redirect_url": "https://example.com/updated"
    }'

    test_api "更新设备配置" "PUT" "$API_BASE/merchant/device/$DEVICE_ID/config" "$config_data" "200"
else
    log_warning "没有设备ID，跳过配置更新测试"
fi
echo ""

# ==================== 第八步: 获取设备状态 ====================
log_info "================= 第八步: 获取设备状态 =================="

if [ -n "$DEVICE_ID" ]; then
    test_api "获取设备状态" "GET" "$API_BASE/merchant/device/$DEVICE_ID/status" "" "200"
else
    log_warning "没有设备ID，跳过状态查询测试"
fi
echo ""

# ==================== 第九步: 获取设备统计 ====================
log_info "================= 第九步: 获取设备统计 =================="

if [ -n "$DEVICE_ID" ]; then
    test_api "获取设备统计" "GET" "$API_BASE/merchant/device/$DEVICE_ID/statistics" "" "200"
    test_api "获取设备统计(自定义日期范围)" "GET" "$API_BASE/merchant/device/$DEVICE_ID/statistics?start_date=2026-01-01&end_date=2026-01-25" "" "200"
else
    log_warning "没有设备ID，跳过统计测试"
fi
echo ""

# ==================== 第十步: 获取触发历史 ====================
log_info "================= 第十步: 获取触发历史 =================="

if [ -n "$DEVICE_ID" ]; then
    test_api "获取触发历史" "GET" "$API_BASE/merchant/device/$DEVICE_ID/triggers" "" "200"
    test_api "获取触发历史(筛选成功)" "GET" "$API_BASE/merchant/device/$DEVICE_ID/triggers?status=success" "" "200"
else
    log_warning "没有设备ID，跳过触发历史测试"
fi
echo ""

# ==================== 第十一步: 健康检查 ====================
log_info "================= 第十一步: 设备健康检查 =================="

if [ -n "$DEVICE_ID" ]; then
    test_api "设备健康检查" "GET" "$API_BASE/merchant/device/$DEVICE_ID/health" "" "200"
else
    log_warning "没有设备ID，跳过健康检查测试"
fi
echo ""

# ==================== 第十二步: 批量操作 ====================
log_info "================= 第十二步: 批量操作 =================="

if [ -n "$DEVICE_ID" ]; then
    # 批量更新
    batch_update_data='{
        "device_ids": ['$DEVICE_ID'],
        "data": {
            "status": 1,
            "location": "批量更新位置"
        }
    }'
    test_api "批量更新设备" "POST" "$API_BASE/merchant/device/batch/update" "$batch_update_data" "200"

    # 批量启用
    batch_enable_data='{
        "device_ids": ['$DEVICE_ID']
    }'
    test_api "批量启用设备" "POST" "$API_BASE/merchant/device/batch/enable" "$batch_enable_data" "200"

    # 批量禁用
    batch_disable_data='{
        "device_ids": ['$DEVICE_ID']
    }'
    test_api "批量禁用设备" "POST" "$API_BASE/merchant/device/batch/disable" "$batch_disable_data" "200"
else
    log_warning "没有设备ID，跳过批量操作测试"
fi
echo ""

# ==================== 第十三步: 绑定/解绑设备 ====================
log_info "================= 第十三步: 绑定/解绑设备 =================="

# 创建一个未绑定的设备用于测试
unbind_device_code="TEST_UNBIND_$(date +%s)"
unbind_create_data='{
    "device_code": "'$unbind_device_code'",
    "device_name": "待绑定设备",
    "type": "COUNTER",
    "trigger_mode": "WIFI"
}'

unbind_response=$(curl -s -X POST "$API_BASE/merchant/device/create" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "$unbind_create_data")

UNBIND_DEVICE_ID=$(echo $unbind_response | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)

if [ -n "$UNBIND_DEVICE_ID" ]; then
    log_info "创建待绑定设备成功，ID: $UNBIND_DEVICE_ID"

    # 先解绑（如果已经绑定）
    curl -s -X POST "$API_BASE/merchant/device/$UNBIND_DEVICE_ID/unbind" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $TOKEN" > /dev/null

    # 测试绑定
    test_api "绑定设备" "POST" "$API_BASE/merchant/device/$UNBIND_DEVICE_ID/bind" "" "200"

    # 测试解绑
    test_api "解绑设备" "POST" "$API_BASE/merchant/device/$UNBIND_DEVICE_ID/unbind" "" "200"
else
    log_warning "创建待绑定设备失败"
fi
echo ""

# ==================== 第十四步: 删除设备 ====================
log_info "================= 第十四步: 删除设备 =================="

if [ -n "$DEVICE_ID" ]; then
    test_api "删除设备" "DELETE" "$API_BASE/merchant/device/$DEVICE_ID/delete" "" "200"
else
    log_warning "没有设备ID，跳过删除测试"
fi

if [ -n "$UNBIND_DEVICE_ID" ]; then
    test_api "删除待绑定设备" "DELETE" "$API_BASE/merchant/device/$UNBIND_DEVICE_ID/delete" "" "200"
fi
echo ""

# ==================== 测试错误处理 ====================
log_info "================= 第十五步: 错误处理测试 =================="

# 测试无效的设备ID
test_api "获取不存在的设备" "GET" "$API_BASE/merchant/device/999999" "" "404"

# 测试无效的token
INVALID_TOKEN="invalid_token_123456"
response=$(curl -s -X GET "$API_BASE/merchant/device/list" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $INVALID_TOKEN")

if echo "$response" | grep -q "401\|未授权\|unauthorized"; then
    log_success "无效token测试通过 - 正确返回401"
else
    log_error "无效token测试失败 - 应返回401"
    echo "响应: $response"
fi

# 测试缺少必填字段的创建请求
invalid_create_data='{
    "device_name": "缺少device_code"
}'

response=$(curl -s -X POST "$API_BASE/merchant/device/create" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "$invalid_create_data")

if echo "$response" | grep -q "400\|验证失败\|require"; then
    log_success "参数验证测试通过 - 正确返回400"
else
    log_error "参数验证测试失败 - 应返回400"
    echo "响应: $response"
fi

echo ""

# ==================== 测试总结 ====================
echo ""
echo "======================================"
echo "测试总结"
echo "======================================"
echo -e "总测试数: ${BLUE}$TOTAL_TESTS${NC}"
echo -e "通过: ${GREEN}$PASSED_TESTS${NC}"
echo -e "失败: ${RED}$FAILED_TESTS${NC}"
echo -e "通过率: ${GREEN}$(awk "BEGIN {printf \"%.2f\", ($PASSED_TESTS/$TOTAL_TESTS)*100}")%${NC}"
echo "======================================"

if [ $FAILED_TESTS -eq 0 ]; then
    log_success "所有测试通过！"
    exit 0
else
    log_error "部分测试失败，请查看详细日志"
    exit 1
fi
