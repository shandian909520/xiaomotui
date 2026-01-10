# Merchant模型使用文档

## 概述

Merchant模型用于管理商家/商户信息，是小摸推系统的核心模型之一。该模型符合ThinkPHP 8.0规范，提供了完整的CRUD操作、关联查询、查询作用域等功能。

## 数据库表结构

```sql
CREATE TABLE `xmt_merchants` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '商家ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '关联用户ID',
  `name` varchar(100) NOT NULL COMMENT '商家名称',
  `category` varchar(50) NOT NULL COMMENT '商家类别',
  `address` varchar(255) NOT NULL COMMENT '地址',
  `longitude` decimal(10,7) DEFAULT NULL COMMENT '经度',
  `latitude` decimal(10,7) DEFAULT NULL COMMENT '纬度',
  `phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `description` text COMMENT '商家描述',
  `logo` varchar(255) DEFAULT NULL COMMENT '商家logo',
  `business_hours` json DEFAULT NULL COMMENT '营业时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1正常 2审核中',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商家表';
```

## 常量定义

### 状态常量

```php
Merchant::STATUS_DISABLED = 0;      // 已禁用
Merchant::STATUS_ACTIVE = 1;        // 正常
Merchant::STATUS_UNDER_REVIEW = 2;  // 审核中
```

### 类别常量

```php
Merchant::CATEGORY_RESTAURANT = '餐饮';
Merchant::CATEGORY_RETAIL = '零售';
Merchant::CATEGORY_SERVICE = '服务';
Merchant::CATEGORY_ENTERTAINMENT = '娱乐';
Merchant::CATEGORY_EDUCATION = '教育';
Merchant::CATEGORY_HEALTHCARE = '医疗';
Merchant::CATEGORY_HOTEL = '酒店';
Merchant::CATEGORY_OTHER = '其他';
```

## 基本用法

### 创建商家

```php
use app\model\Merchant;

// 创建商家
$merchant = Merchant::create([
    'user_id' => 1,
    'name' => '星巴克（国贸店）',
    'category' => Merchant::CATEGORY_RESTAURANT,
    'address' => '北京市朝阳区建国门外大街1号',
    'longitude' => 116.407394,
    'latitude' => 39.904211,
    'phone' => '13800138000',
    'description' => '全球知名咖啡连锁品牌',
    'logo' => '/uploads/starbucks.png',
    'business_hours' => [
        'open' => '07:00',
        'close' => '22:00'
    ],
    'status' => Merchant::STATUS_ACTIVE
]);
```

### 查询商家

```php
// 根据ID查询
$merchant = Merchant::find(1);

// 根据条件查询
$merchant = Merchant::where('name', '星巴克（国贸店）')->find();

// 查询所有商家
$merchants = Merchant::select();

// 分页查询
$merchants = Merchant::paginate(10);
```

### 更新商家

```php
// 方式1：先查询后更新
$merchant = Merchant::find(1);
$merchant->name = '星巴克（望京店）';
$merchant->address = '北京市朝阳区望京街10号';
$merchant->save();

// 方式2：直接更新
Merchant::where('id', 1)->update([
    'name' => '星巴克（望京店）',
    'address' => '北京市朝阳区望京街10号'
]);
```

### 删除商家

```php
// 方式1：先查询后删除
$merchant = Merchant::find(1);
$merchant->delete();

// 方式2：直接删除
Merchant::destroy(1);

// 方式3：条件删除
Merchant::where('status', Merchant::STATUS_DISABLED)->delete();
```

## 查询作用域

### 查询正常营业的商家

```php
$merchants = Merchant::active()->select();
```

### 按类别查询

```php
$restaurants = Merchant::byCategory(Merchant::CATEGORY_RESTAURANT)->select();
```

### 按状态查询

```php
$merchants = Merchant::byStatus(Merchant::STATUS_ACTIVE)->select();
```

### 查询附近商家

```php
// 查询5公里范围内的商家
$latitude = 39.904211;
$longitude = 116.407394;
$radius = 5; // 公里

$nearbyMerchants = Merchant::nearby($latitude, $longitude, $radius)
    ->active()
    ->select();
```

### 组合使用作用域

```php
// 查询附近5公里内的正常营业的餐饮商家
$merchants = Merchant::nearby($latitude, $longitude, 5)
    ->active()
    ->byCategory(Merchant::CATEGORY_RESTAURANT)
    ->select();
```

## 关联查询

### 查询商家关联的用户

```php
$merchant = Merchant::with('user')->find(1);
echo $merchant->user->nickname;
```

### 查询商家的NFC设备

```php
$merchant = Merchant::with('nfcDevices')->find(1);
foreach ($merchant->nfcDevices as $device) {
    echo $device->device_name;
}
```

### 查询商家的优惠券

```php
$merchant = Merchant::with('coupons')->find(1);
foreach ($merchant->coupons as $coupon) {
    echo $coupon->title;
}
```

### 查询商家的内容模板

```php
$merchant = Merchant::with('contentTemplates')->find(1);
foreach ($merchant->contentTemplates as $template) {
    echo $template->name;
}
```

### 查询多个关联

```php
$merchant = Merchant::with(['user', 'nfcDevices', 'coupons'])->find(1);
```

## 模型方法

### 状态检查方法

```php
$merchant = Merchant::find(1);

// 检查是否正常营业
if ($merchant->isActive()) {
    echo '商家正常营业';
}

// 检查是否已禁用
if ($merchant->isDisabled()) {
    echo '商家已禁用';
}

// 检查是否审核中
if ($merchant->isUnderReview()) {
    echo '商家审核中';
}
```

### 更新状态

```php
$merchant = Merchant::find(1);

// 更新为正常状态
$merchant->updateStatus(Merchant::STATUS_ACTIVE);

// 更新为禁用状态
$merchant->updateStatus(Merchant::STATUS_DISABLED);

// 更新为审核中状态
$merchant->updateStatus(Merchant::STATUS_UNDER_REVIEW);
```

### 计算距离

```php
$merchant = Merchant::find(1);

// 计算商家到指定坐标的距离（单位：公里）
$targetLat = 39.915;
$targetLon = 116.404;
$distance = $merchant->getDistance($targetLat, $targetLon);

echo "距离: {$distance} 公里";
```

## 静态查询方法

### 根据用户ID获取商家列表

```php
$userId = 1;
$merchants = Merchant::getByUserId($userId);

// 带条件查询
$merchants = Merchant::getByUserId($userId, [
    'status' => Merchant::STATUS_ACTIVE
]);
```

### 根据类别获取商家列表

```php
$merchants = Merchant::getByCategory(Merchant::CATEGORY_RESTAURANT);

// 带条件查询
$merchants = Merchant::getByCategory(Merchant::CATEGORY_RESTAURANT, [
    'status' => Merchant::STATUS_ACTIVE
]);
```

### 获取附近商家（包含距离计算）

```php
$latitude = 39.904211;
$longitude = 116.407394;
$radius = 5; // 公里
$limit = 20; // 限制数量

$merchants = Merchant::getNearbyMerchants($latitude, $longitude, $radius, $limit);

// 返回结果已按距离排序，并包含distance字段
foreach ($merchants as $merchant) {
    echo "{$merchant['name']} - 距离: {$merchant['distance']} 公里\n";
}
```

## 获取器（Accessor）

### 状态文本

```php
$merchant = Merchant::find(1);
echo $merchant->status_text; // "正常" / "已禁用" / "审核中"
```

### 完整地址

```php
$merchant = Merchant::find(1);
echo $merchant->full_address; // 返回地址字段
```

### Logo URL

```php
$merchant = Merchant::find(1);
echo $merchant->logo_url; // 自动转换为完整URL
```

### 坐标

```php
$merchant = Merchant::find(1);
$coords = $merchant->coordinates;
echo "经度: {$coords['longitude']}, 纬度: {$coords['latitude']}";
```

### 营业时间文本

```php
$merchant = Merchant::find(1);
echo $merchant->business_hours_text; // 格式化的营业时间
```

## 营业时间格式

营业时间字段支持两种JSON格式：

### 简单格式

```php
[
    'open' => '09:00',
    'close' => '22:00'
]
```

### 按星期设置格式

```php
[
    'monday' => ['open' => '09:00', 'close' => '22:00'],
    'tuesday' => ['open' => '09:00', 'close' => '22:00'],
    'wednesday' => ['open' => '09:00', 'close' => '22:00'],
    'thursday' => ['open' => '09:00', 'close' => '22:00'],
    'friday' => ['open' => '09:00', 'close' => '23:00'],
    'saturday' => ['open' => '10:00', 'close' => '23:00'],
    'sunday' => ['open' => '10:00', 'close' => '22:00']
]
```

## 数据验证

```php
use app\model\Merchant;

// 获取验证规则
$rules = Merchant::getValidateRules();

// 获取验证消息
$messages = Merchant::getValidateMessages();

// 使用ThinkPHP验证器
$validate = new \think\Validate($rules, $messages);

$data = [
    'user_id' => 1,
    'name' => '测试商家',
    'category' => '餐饮',
    'address' => '测试地址',
];

if (!$validate->check($data)) {
    echo $validate->getError();
}
```

## 完整示例

### 示例1：创建商家并关联设备

```php
use app\model\Merchant;
use app\model\NfcDevice;

// 创建商家
$merchant = Merchant::create([
    'user_id' => 1,
    'name' => '海底捞火锅',
    'category' => Merchant::CATEGORY_RESTAURANT,
    'address' => '北京市朝阳区大望路1号',
    'longitude' => 116.457,
    'latitude' => 39.915,
    'phone' => '13800138000',
    'description' => '知名火锅品牌',
    'business_hours' => [
        'open' => '10:00',
        'close' => '22:00'
    ],
    'status' => Merchant::STATUS_ACTIVE
]);

// 为商家添加NFC设备
NfcDevice::create([
    'merchant_id' => $merchant->id,
    'device_code' => 'NFC' . time(),
    'device_name' => '桌贴设备1',
    'location' => '1号桌',
    'type' => NfcDevice::TYPE_TABLE,
    'trigger_mode' => NfcDevice::TRIGGER_MENU,
    'status' => NfcDevice::STATUS_ONLINE
]);
```

### 示例2：查询附近的餐饮商家

```php
use app\model\Merchant;

// 用户当前位置
$userLat = 39.915;
$userLon = 116.404;

// 查询5公里内的正常营业的餐饮商家
$merchants = Merchant::getNearbyMerchants($userLat, $userLon, 5, 10);

// 输出结果
foreach ($merchants as $merchant) {
    echo "商家: {$merchant['name']}\n";
    echo "类别: {$merchant['category']}\n";
    echo "地址: {$merchant['address']}\n";
    echo "距离: {$merchant['distance']} 公里\n";
    echo "电话: {$merchant['phone']}\n";
    echo "---\n";
}
```

### 示例3：商家状态管理

```php
use app\model\Merchant;

$merchant = Merchant::find(1);

// 商家提交审核
if ($merchant->isActive()) {
    $merchant->updateStatus(Merchant::STATUS_UNDER_REVIEW);
    echo '商家已提交审核';
}

// 审核通过
if ($merchant->isUnderReview()) {
    $merchant->updateStatus(Merchant::STATUS_ACTIVE);
    echo '商家审核通过';
}

// 禁用商家
if ($merchant->isActive()) {
    $merchant->updateStatus(Merchant::STATUS_DISABLED);
    echo '商家已禁用';
}
```

## 性能优化建议

### 1. 使用查询作用域

```php
// 好的做法
$merchants = Merchant::active()->byCategory('餐饮')->select();

// 避免
$merchants = Merchant::where('status', 1)->where('category', '餐饮')->select();
```

### 2. 预加载关联数据

```php
// 好的做法 - 预加载，避免N+1查询
$merchants = Merchant::with(['user', 'nfcDevices'])->select();

// 避免 - 会产生N+1查询问题
$merchants = Merchant::select();
foreach ($merchants as $merchant) {
    echo $merchant->user->nickname; // 每次循环都会查询一次
}
```

### 3. 合理使用字段选择

```php
// 只查询需要的字段
$merchants = Merchant::field('id,name,address,phone')->select();
```

### 4. 使用索引字段进行查询

```php
// 使用了索引的字段：user_id, category, status
$merchants = Merchant::where('user_id', 1)
    ->where('category', '餐饮')
    ->where('status', 1)
    ->select();
```

## 注意事项

1. **坐标数据**：经度范围 -180 到 180，纬度范围 -90 到 90
2. **距离计算**：使用Haversine公式，适用于地球表面的短距离计算
3. **业务小时**：存储为JSON格式，支持灵活的时间配置
4. **状态管理**：使用updateStatus方法更新状态，确保状态值有效
5. **关联关系**：注意使用预加载避免N+1查询问题
6. **表前缀**：数据库表名为 `xmt_merchants`，但模型中使用 `merchants`

## 扩展开发

如果需要添加新功能，可以在模型中添加：

```php
namespace app\model;

use think\Model;

class Merchant extends Model
{
    // ... 现有代码 ...

    /**
     * 自定义方法示例：检查是否营业中
     */
    public function isOpenNow(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if (empty($this->business_hours)) {
            return true; // 默认全天营业
        }

        $hours = $this->business_hours;
        $currentTime = date('H:i');

        if (isset($hours['open']) && isset($hours['close'])) {
            return $currentTime >= $hours['open'] && $currentTime <= $hours['close'];
        }

        return true;
    }
}
```

## 更新日志

- 2025-10-01: 初始版本，完整实现Merchant模型
- 支持ThinkPHP 8.0
- 包含完整的CRUD操作
- 提供查询作用域和关联关系
- 实现距离计算功能
