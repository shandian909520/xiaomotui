# 优惠券功能测试文档

## 功能概述

优惠券功能包含商家侧和用户侧两部分，实现了完整的优惠券创建、发放、领取、使用流程。

## 一、商家侧功能

### 1.1 创建优惠券

**页面路径**: `/pages-sub/marketing/coupon/create`

**操作步骤**:
1. 在商家后台点击"优惠券管理"
2. 点击右上角"+ 创建"按钮
3. 填写优惠券信息：
   - 券名称：例如"新用户满减券"
   - 券类型：满减券/折扣券
   - 优惠额度：满减券输入金额（如10元），折扣券输入折扣（如8.5折）
   - 使用门槛：满多少可用（0表示无门槛）
   - 发放总量：例如100张
   - 有效期：选择开始和结束日期
   - 使用说明：选填
4. 点击"创建优惠券"按钮

**API接口**: `POST /marketing/coupon/create`

**请求参数**:
```json
{
  "name": "新用户满减券",
  "type": "fixed",
  "discount": 10,
  "min_amount": 50,
  "total": 100,
  "start_date": "2025-01-01",
  "end_date": "2025-03-31",
  "description": "新用户专享优惠"
}
```

### 1.2 优惠券列表

**页面路径**: `/pages-sub/marketing/coupon/list`

**功能说明**:
- 查看所有优惠券
- 按状态筛选：全部/进行中/已结束/已停用
- 显示优惠券信息：名称、类型、额度、剩余数量、有效期、状态
- 支持下拉刷新和上拉加载更多

**API接口**: `GET /marketing/coupon/list`

**查询参数**:
```
page: 1
limit: 15
status: active/expired/disabled
```

### 1.3 核销优惠券

**操作步骤**:
1. 用户出示优惠券二维码
2. 商家使用扫码设备扫描二维码
3. 系统验证优惠券有效性
4. 核销成功，优惠券状态变为"已使用"

**API接口**: `POST /marketing/coupon/use`

**请求参数**:
```json
{
  "id": 123,
  "code": "COUPON123456"
}
```

## 二、用户侧功能

### 2.1 领取优惠券

**页面路径**: `/pages/coupon/receive`

**操作步骤**:
1. 在首页点击"领优惠券"快捷入口
2. 或在个人中心点击"我的优惠券"，然后点击"去领券"
3. 浏览可领取的优惠券列表
4. 点击"立即领取"按钮
5. 领取成功后自动跳转到"我的优惠券"

**API接口**: `POST /marketing/coupon/claim/:id`

**功能特点**:
- 显示优惠券详细信息
- 显示剩余数量
- 已领取的优惠券显示"已领取"
- 已抢光的优惠券显示"已抢光"并禁用按钮

### 2.2 我的优惠券

**页面路径**: `/pages/coupon/my`

**操作步骤**:
1. 在个人中心点击"我的优惠券"
2. 查看已领取的优惠券
3. 按状态筛选：未使用/已使用/已过期
4. 点击未使用的优惠券查看详情和二维码

**API接口**: `GET /marketing/coupon/my`

**查询参数**:
```
page: 1
limit: 15
status: unused/used/expired
```

**功能特点**:
- 支持下拉刷新
- 支持上拉加载更多
- 已使用和已过期的优惠券显示为灰色
- 空状态提示"去领券"

### 2.3 使用优惠券

**页面路径**: `/pages/coupon/use?id=123`

**操作步骤**:
1. 在"我的优惠券"列表点击未使用的优惠券
2. 查看优惠券详细信息
3. 向商家出示二维码
4. 商家扫码核销

**功能特点**:
- 显示优惠券完整信息
- 生成二维码供商家扫描
- 显示券码
- 显示使用说明

## 三、API路由配置

### 3.1 商家侧路由（需要商家权限）

```php
// api/route/app.php 第 242-248 行
Route::group('coupon', function () {
    Route::get('list', 'Merchant/couponList');
    Route::post('create', 'Merchant/createCoupon');
    Route::put(':id', 'Merchant/updateCoupon');
    Route::delete(':id', 'Merchant/deleteCoupon');
    Route::get(':id/usage', 'Merchant/couponUsage');
});
```

### 3.2 用户侧路由（需要用户登录）

```php
// api/route/app.php 第 252-256 行
Route::group('coupon', function () {
    Route::post('receive', 'Coupon/receive');
    Route::get('my', 'Coupon/my');
    Route::post('use', 'Coupon/use');
});
```

## 四、前端API方法

**文件路径**: `D:\xiaomotui\uni-app\src\api\modules\coupon.js`

### 4.1 商家侧方法

- `getList(params)` - 获取优惠券列表
- `getDetail(id)` - 获取优惠券详情
- `create(data)` - 创建优惠券
- `update(id, data)` - 更新优惠券
- `delete(id)` - 删除优惠券
- `grant(id, data)` - 发放优惠券

### 4.2 用户侧方法

- `claim(id)` - 领取优惠券
- `myList(params)` - 我的优惠券列表
- `use(id)` - 使用优惠券

## 五、测试场景

### 5.1 正常流程测试

1. **商家创建优惠券**
   - 创建一张满50减10的优惠券
   - 发放总量100张
   - 有效期30天

2. **用户领取优惠券**
   - 用户A登录后进入领券页面
   - 点击领取按钮
   - 验证领取成功提示
   - 验证跳转到我的优惠券页面

3. **用户查看优惠券**
   - 在"我的优惠券"页面查看已领取的券
   - 验证优惠券信息显示正确
   - 点击优惠券进入使用页面

4. **商家核销优惠券**
   - 用户出示二维码
   - 商家扫码核销
   - 验证核销成功
   - 验证优惠券状态变为"已使用"

### 5.2 异常场景测试

1. **重复领取**
   - 用户尝试领取已领取的优惠券
   - 验证按钮显示"已领取"并禁用

2. **库存不足**
   - 优惠券剩余数量为0
   - 验证按钮显示"已抢光"并禁用

3. **优惠券过期**
   - 优惠券超过有效期
   - 验证在"已过期"标签页显示
   - 验证无法使用

4. **重复核销**
   - 尝试核销已使用的优惠券
   - 验证系统提示"优惠券已使用"

## 六、注意事项

1. **二维码生成**
   - 当前使用简化版二维码工具
   - 生产环境建议使用完整的 qrcodejs2 库
   - 文件路径：`D:\xiaomotui\uni-app\src\utils\qrcode.js`

2. **权限控制**
   - 商家侧功能需要商家权限
   - 用户侧功能需要用户登录
   - 核销功能需要商家权限

3. **数据同步**
   - 领取后实时更新剩余数量
   - 核销后实时更新优惠券状态
   - 支持下拉刷新获取最新数据

4. **用户体验**
   - 所有页面支持自定义导航栏
   - 支持下拉刷新
   - 空状态友好提示
   - 加载状态显示

## 七、页面路由配置

已在 `pages.json` 中添加以下路由：

```json
{
  "path": "pages/coupon/my",
  "style": {
    "navigationBarTitleText": "我的优惠券",
    "navigationStyle": "custom",
    "enablePullDownRefresh": true
  }
},
{
  "path": "pages/coupon/receive",
  "style": {
    "navigationBarTitleText": "领取优惠券",
    "navigationStyle": "custom",
    "enablePullDownRefresh": true
  }
},
{
  "path": "pages/coupon/use",
  "style": {
    "navigationBarTitleText": "使用优惠券",
    "navigationStyle": "custom"
  }
}
```

## 八、快捷入口

### 8.1 首页入口
- 位置：首页快捷功能区
- 图标：🎫
- 文字：领优惠券
- 跳转：`/pages/coupon/receive`

### 8.2 个人中心入口
- 位置：个人中心快捷入口区
- 图标：🎟️
- 文字：我的优惠券
- 跳转：`/pages/coupon/my`

## 九、后续优化建议

1. **二维码功能**
   - 集成完整的 QRCode 库
   - 支持自定义二维码样式
   - 添加 logo 水印

2. **优惠券类型扩展**
   - 支持兑换券
   - 支持礼品券
   - 支持会员专享券

3. **营销功能**
   - 支持优惠券分享
   - 支持优惠券组合使用
   - 支持优惠券推荐

4. **数据统计**
   - 优惠券使用率统计
   - 优惠券转化率分析
   - 用户领取行为分析
