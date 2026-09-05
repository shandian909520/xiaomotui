<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 任务完成凭证模型（信任模式）
 * @property int $id
 * @property int $task_instance_id 任务实例ID
 * @property int $action_id 动作ID
 * @property int $merchant_id 商家ID
 * @property string $file_url 凭证URL
 * @property string $file_hash SHA256
 * @property string $audit_status PENDING/APPROVED/REJECTED
 * @property string|null $audit_remark 审核备注
 * @property int|null $auditor_id 审核人
 * @property string|null $audited_at 审核时间
 */
class TaskProof extends Model
{
    public const AUDIT_PENDING = 'PENDING';
    public const AUDIT_APPROVED = 'APPROVED';
    public const AUDIT_REJECTED = 'REJECTED';

    protected $table = 'xmt_task_proofs';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'               => 'integer',
        'task_instance_id' => 'integer',
        'action_id'        => 'integer',
        'merchant_id'      => 'integer',
        'auditor_id'       => 'integer',
    ];

    protected $field = [
        'task_instance_id', 'action_id', 'merchant_id', 'file_url', 'file_hash',
        'audit_status', 'audit_remark', 'auditor_id', 'audited_at',
    ];

    /**
     * 检查文件hash是否已被使用过（全库查重，防重复截图）
     */
    public static function isHashUsed(string $hash, ?int $exceptProofId = null): bool
    {
        $query = self::where('file_hash', $hash);
        if ($exceptProofId) {
            $query->where('id', '<>', $exceptProofId);
        }
        return $query->count() > 0;
    }
}
