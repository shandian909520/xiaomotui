<?php

use think\migration\Migrator;

/**
 * device_triggers 表扩展：关联任务引擎
 */
class ExtendDeviceTriggers extends Migrator
{
    public function up()
    {
        $table = $this->table('device_triggers');
        if (!$table->hasColumn('bundle_id')) {
            $table->addColumn('bundle_id', 'integer', ['null' => true, 'after' => 'trigger_mode', 'comment' => '命中的任务包ID'])
                ->addIndex(['bundle_id'], ['name' => 'idx_bundle'])
                ->update();
        }
        if (!$table->hasColumn('task_instance_id')) {
            $table->addColumn('task_instance_id', 'integer', ['null' => true, 'after' => 'bundle_id', 'comment' => '对应任务实例ID'])
                ->update();
        }
    }

    public function down()
    {
        $table = $this->table('device_triggers');
        if ($table->hasColumn('bundle_id')) {
            $table->removeColumn('bundle_id')->update();
        }
        if ($table->hasColumn('task_instance_id')) {
            $table->removeColumn('task_instance_id')->update();
        }
    }
}
