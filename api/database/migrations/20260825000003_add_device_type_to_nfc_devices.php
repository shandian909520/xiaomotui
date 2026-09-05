<?php

use think\migration\Migrator;

/**
 * 为 xmt_nfc_devices 增加 device_type 列，区分被动贴片（PASSIVE）与主动设备（ACTIVE）
 *
 * 业务背景：
 * - PASSIVE：NFC 贴片（桌贴/墙贴），被动硬件，不会上报心跳，"在线"概念无意义
 * - ACTIVE ：智能网关/POS 等主动设备，会发心跳，可信"在线"判定
 *
 * 默认 PASSIVE —— 老数据全部视作被动贴片，兼容现有逻辑
 */
class AddDeviceTypeToNfcDevices extends Migrator
{
    public function up()
    {
        $table = $this->table('nfc_devices');
        if (!$table->hasColumn('device_type')) {
            $table->addColumn('device_type', 'enum', [
                'values'   => ['PASSIVE', 'ACTIVE'],
                'default'  => 'PASSIVE',
                'null'     => false,
                'after'    => 'type',
                'comment'  => '设备形态 PASSIVE=被动贴片(无心跳) ACTIVE=主动设备(有心跳)',
            ])
            ->addIndex(['device_type'], ['name' => 'idx_device_type'])
            ->update();
        }
    }

    public function down()
    {
        $table = $this->table('nfc_devices');
        if ($table->hasColumn('device_type')) {
            $table->removeIndex(['device_type'])
                  ->removeColumn('device_type')
                  ->update();
        }
    }
}
