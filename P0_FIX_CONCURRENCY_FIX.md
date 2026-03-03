# P0-3: 优惠券并发超发问题修复方案

## 问题描述
在 `app/service/NfcService.php` 的 `handleCouponTrigger` 方法中存在并发问题，需要使用Redis Lua 脚本来确保优惠券库存扣减的原子性。

## 问题分析

当前代码使用数据库行锁+ Redis 锁，但在释放锁和减库存之间存在时间窗口，仍可能出現并发问题。

## 解决方案

使用 Redis 的 eval 执行 Lua 脚本原子性地执行：检查库存、扣减库存、创建用户优惠券记录，确保操作原子性。需要在 NfcService 的 `handleCouponTrigger` 方法中实现。