# 设备管理API完整测试脚本 - PowerShell版本
# 作者: Claude AI
# 日期: 2026-01-25

$ErrorActionPreference = "Continue"

# 配置
$ApiBase = "http://localhost:8001/api"
$Token = ""
$DeviceId = 0

# 测试结果统计
$TotalTests = 0
$PassedTests = 0
$FailedTests = 0
$TestResults = @()

# 日志函数
function Log-Info {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Blue
}

function Log-Success {
    param([string]$Message)
    Write-Host "[SUCCESS] $Message" -ForegroundColor Green
    script:$PassedTests++
}

function Log-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
    script:$FailedTests++
}

function Log-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

# API测试函数
function Test-Api {
    param(
        [string]$TestName,
        [string]$Method,
        [string]$Url,
        [string]$Data,
        [int]$ExpectedCode
    )

    script:$TotalTests++
    Log-Info "测试: $TestName"

    try {
        # 构建请求头
        $headers = @{
            "Content-Type" = "application/json"
        }

        if ($Token) {
            $headers["Authorization"] = "Bearer $Token"
        }

        # 发送请求
        if ($Data) {
            $response = Invoke-RestMethod -Uri $Url -Method $Method -Headers $headers -Body $Data -ErrorAction Stop
        } else {
            $response = Invoke-RestMethod -Uri $Url -Method $Method -Headers $headers -ErrorAction Stop
        }

        # 检查响应码
        if ($response.code -eq $ExpectedCode) {
            Log-Success "$TestName - 状态码: $($response.code)"
            Write-Host "响应: $($response | ConvertTo-Json -Depth 3)" -ForegroundColor Cyan
            return @{
                Success = $true
                Response = $response
            }
        } else {
            Log-Error "$TestName - 期望: $ExpectedCode, 实际: $($response.code)"
            Write-Host "响应: $($response | ConvertTo-Json -Depth 3)" -ForegroundColor Red
            return @{
                Success = $false
                Response = $response
            }
        }
    } catch {
        Log-Error "$TestName - 异常: $($_.Exception.Message)"
        Write-Host "详细错误: $_" -ForegroundColor Red
        return @{
            Success = $false
            Error = $_.Exception.Message
        }
    }
}

# 开始测试
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "设备管理API完整测试" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# ==================== 检查服务器状态 ====================
Log-Info "检查API服务器状态..."
try {
    $healthCheck = Invoke-RestMethod -Uri "$ApiBase/../" -Method Get
    Log-Success "服务器运行中 - 版本: $($healthCheck.data.version)"
} catch {
    Log-Error "服务器未响应，请启动API服务器"
    Write-Host "启动命令: php think run --host 0.0.0.0 --port 8001" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# ==================== 第一步: 登录获取token ====================
Log-Info "================= 第一步: 用户登录 =================="

# 检查数据库中是否有测试用户
Log-Info "检查数据库中是否有测试数据..."
try {
    # 这里我们需要直接检查数据库或创建测试用户
    # 暂时跳过，直接尝试登录

    # 尝试使用管理员账号登录
    $adminLoginData = @{
        username = "admin"
        password = "admin123"
    } | ConvertTo-Json

    $loginResult = Test-Api "管理员登录" "POST" "$ApiBase/auth/login" $adminLoginData 200

    if ($loginResult.Success) {
        $Token = $loginResult.Response.data.token
        Log-Success "登录成功，Token: ${Token}..."
    } else {
        Log-Warning "管理员登录失败"
    }
} catch {
    Log-Warning "登录测试跳过 - $($_.Exception.Message)"
}

Write-Host ""

# 如果没有获取到token，尝试创建测试数据
if (-not $Token) {
    Log-Warning "未能获取有效Token，将尝试创建测试用户"
    Log-Info "注意: 以下测试可能会因为认证失败而返回401错误"
    Write-Host ""

    # 设置一个模拟token用于测试（这会导致后续请求失败，但可以看到错误处理）
    # $Token = "test_token_no_auth"
}

# ==================== 第二步: 测试设备列表接口 ====================
Log-Info "================= 第二步: 获取设备列表 =================="

$testList = Test-Api "获取设备列表(默认)" "GET" "$ApiBase/merchant/device/list" $null 200
$testList2 = Test-Api "获取设备列表(分页)" "GET" "$ApiBase/merchant/device/list?page=1&limit=10" $null 200
$testList3 = Test-Api "获取设备列表(状态筛选)" "GET" "$ApiBase/merchant/device/list?status=1" $null 200
$testList4 = Test-Api "获取设备列表(关键字搜索)" "GET" "$ApiBase/merchant/device/list?keyword=test" $null 200

# 如果列表成功，记录第一个设备ID
if ($testList.Success -and $testList.Response.data) {
    $firstDevice = $testList.Response.data[0]
    if ($firstDevice) {
        $DeviceId = $firstDevice.id
        Log-Info "找到现有设备 ID: $DeviceId"
    }
}

Write-Host ""

# ==================== 第三步: 创建设备测试 ====================
Log-Info "================= 第三步: 创建设备 =================="

$timestamp = Get-Date -Format "yyyyMMddHHmmss"
$createData = @{
    device_code = "TEST$timestamp"
    device_name = "测试设备001"
    type = "TABLE"
    trigger_mode = "VIDEO"
    location = "一楼A区"
    template_id = 1
    redirect_url = "https://example.com"
} | ConvertTo-Json

$createResult = Test-Api "创建设备" "POST" "$ApiBase/merchant/device/create" $createData 201

if ($createResult.Success) {
    $DeviceId = $createResult.Response.data.id
    Log-Success "设备创建成功，设备ID: $DeviceId"
} else {
    Log-Error "设备创建失败"
}

Write-Host ""

# ==================== 第四步: 获取设备详情 ====================
Log-Info "================= 第四步: 获取设备详情 =================="

if ($DeviceId -gt 0) {
    $testDetail = Test-Api "获取设备详情" "GET" "$ApiBase/merchant/device/$DeviceId" $null 200
} else {
    Log-Warning "没有设备ID，跳过详情测试"
}

Write-Host ""

# ==================== 第五步: 更新设备 ====================
Log-Info "================= 第五步: 更新设备 =================="

if ($DeviceId -gt 0) {
    $updateData = @{
        device_name = "测试设备001(已更新)"
        location = "一楼B区"
    } | ConvertTo-Json

    $testUpdate = Test-Api "更新设备信息" "PUT" "$ApiBase/merchant/device/$DeviceId/update" $updateData 200
} else {
    Log-Warning "没有设备ID，跳过更新测试"
}

Write-Host ""

# ==================== 第六步: 更新设备状态 ====================
Log-Info "================= 第六步: 更新设备状态 =================="

if ($DeviceId -gt 0) {
    $statusData1 = @{ status = 1 } | ConvertTo-Json
    $statusData2 = @{ status = 0 } | ConvertTo-Json

    $testStatus1 = Test-Api "更新设备状态为在线" "PUT" "$ApiBase/merchant/device/$DeviceId/status" $statusData1 200
    $testStatus2 = Test-Api "更新设备状态为离线" "PUT" "$ApiBase/merchant/device/$DeviceId/status" $statusData2 200
} else {
    Log-Warning "没有设备ID，跳过状态更新测试"
}

Write-Host ""

# ==================== 第七步: 更新设备配置 ====================
Log-Info "================= 第七步: 更新设备配置 =================="

if ($DeviceId -gt 0) {
    $configData = @{
        template_id = 2
        trigger_mode = "COUPON"
        redirect_url = "https://example.com/updated"
    } | ConvertTo-Json

    $testConfig = Test-Api "更新设备配置" "PUT" "$ApiBase/merchant/device/$DeviceId/config" $configData 200
} else {
    Log-Warning "没有设备ID，跳过配置更新测试"
}

Write-Host ""

# ==================== 第八步: 获取设备状态 ====================
Log-Info "================= 第八步: 获取设备状态 =================="

if ($DeviceId -gt 0) {
    $testGetStatus = Test-Api "获取设备状态" "GET" "$ApiBase/merchant/device/$DeviceId/status" $null 200
} else {
    Log-Warning "没有设备ID，跳过状态查询测试"
}

Write-Host ""

# ==================== 第九步: 获取设备统计 ====================
Log-Info "================= 第九步: 获取设备统计 =================="

if ($DeviceId -gt 0) {
    $testStats = Test-Api "获取设备统计" "GET" "$ApiBase/merchant/device/$DeviceId/statistics" $null 200
    $testStats2 = Test-Api "获取设备统计(自定义日期范围)" "GET" "$ApiBase/merchant/device/$DeviceId/statistics?start_date=2026-01-01&end_date=2026-01-25" $null 200
} else {
    Log-Warning "没有设备ID，跳过统计测试"
}

Write-Host ""

# ==================== 第十步: 获取触发历史 ====================
Log-Info "================= 第十步: 获取触发历史 =================="

if ($DeviceId -gt 0) {
    $testTriggers = Test-Api "获取触发历史" "GET" "$ApiBase/merchant/device/$DeviceId/triggers" $null 200
    $testTriggers2 = Test-Api "获取触发历史(筛选成功)" "GET" "$ApiBase/merchant/device/$DeviceId/triggers?status=success" $null 200
} else {
    Log-Warning "没有设备ID，跳过触发历史测试"
}

Write-Host ""

# ==================== 第十一步: 健康检查 ====================
Log-Info "================= 第十一步: 设备健康检查 =================="

if ($DeviceId -gt 0) {
    $testHealth = Test-Api "设备健康检查" "GET" "$ApiBase/merchant/device/$DeviceId/health" $null 200
} else {
    Log-Warning "没有设备ID，跳过健康检查测试"
}

Write-Host ""

# ==================== 第十二步: 批量操作 ====================
Log-Info "================= 第十二步: 批量操作 =================="

if ($DeviceId -gt 0) {
    # 批量更新
    $batchUpdateData = @{
        device_ids = @($DeviceId)
        data = @{
            status = 1
            location = "批量更新位置"
        }
    } | ConvertTo-Json -Depth 3

    $testBatchUpdate = Test-Api "批量更新设备" "POST" "$ApiBase/merchant/device/batch/update" $batchUpdateData 200

    # 批量启用
    $batchEnableData = @{
        device_ids = @($DeviceId)
    } | ConvertTo-Json

    $testBatchEnable = Test-Api "批量启用设备" "POST" "$ApiBase/merchant/device/batch/enable" $batchEnableData 200

    # 批量禁用
    $testBatchDisable = Test-Api "批量禁用设备" "POST" "$ApiBase/merchant/device/batch/disable" $batchEnableData 200
} else {
    Log-Warning "没有设备ID，跳过批量操作测试"
}

Write-Host ""

# ==================== 第十三步: 绑定/解绑设备 ====================
Log-Info "================= 第十三步: 绑定/解绑设备 =================="

$timestamp2 = Get-Date -Format "yyyyMMddHHmmss"
$unbindDeviceCode = "TEST_UNBIND_$timestamp2"
$unbindCreateData = @{
    device_code = $unbindDeviceCode
    device_name = "待绑定设备"
    type = "COUNTER"
    trigger_mode = "WIFI"
} | ConvertTo-Json

$unbindCreateResult = Test-Api "创建待绑定设备" "POST" "$ApiBase/merchant/device/create" $unbindCreateData 201

$UnbindDeviceId = 0
if ($unbindCreateResult.Success) {
    $UnbindDeviceId = $unbindCreateResult.Response.data.id
    Log-Info "创建待绑定设备成功，ID: $UnbindDeviceId"

    # 测试绑定
    $testBind = Test-Api "绑定设备" "POST" "$ApiBase/merchant/device/$UnbindDeviceId/bind" $null 200

    # 测试解绑
    $testUnbind = Test-Api "解绑设备" "POST" "$ApiBase/merchant/device/$UnbindDeviceId/unbind" $null 200
} else {
    Log-Warning "创建待绑定设备失败"
}

Write-Host ""

# ==================== 第十四步: 删除设备 ====================
Log-Info "================= 第十四步: 删除设备 =================="

if ($DeviceId -gt 0) {
    $testDelete1 = Test-Api "删除设备" "DELETE" "$ApiBase/merchant/device/$DeviceId/delete" $null 200
}

if ($UnbindDeviceId -gt 0) {
    $testDelete2 = Test-Api "删除待绑定设备" "DELETE" "$ApiBase/merchant/device/$UnbindDeviceId/delete" $null 200
}

Write-Host ""

# ==================== 第十五步: 错误处理测试 ====================
Log-Info "================= 第十五步: 错误处理测试 =================="

# 测试不存在的设备
$testNotFound = Test-Api "获取不存在的设备" "GET" "$ApiBase/merchant/device/999999" $null 404

# 测试无效的参数
$invalidCreateData = @{
    device_name = "缺少必填字段"
} | ConvertTo-Json

$testInvalidParams = Test-Api "参数验证测试" "POST" "$ApiBase/merchant/device/create" $invalidCreateData 400

Write-Host ""

# ==================== 测试总结 ====================
Write-Host ""
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "测试总结" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "总测试数: $TotalTests" -ForegroundColor White
Write-Host "通过: $PassedTests" -ForegroundColor Green
Write-Host "失败: $FailedTests" -ForegroundColor Red

if ($TotalTests -gt 0) {
    $passRate = [math]::Round(($PassedTests / $TotalTests) * 100, 2)
    Write-Host "通过率: $passRate%" -ForegroundColor Cyan
}

Write-Host "======================================" -ForegroundColor Cyan

if ($FailedTests -eq 0) {
    Log-Success "所有测试通过！"
    exit 0
} else {
    Log-Error "部分测试失败，请查看详细日志"
    exit 1
}
