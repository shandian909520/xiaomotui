<?php
declare(strict_types=1);

namespace app\service;

use app\model\CardKey;
use app\model\MerchantBenefit;
use think\facade\Db;
use think\facade\Log;

class CardKeyService
{
    public function activate(int $merchantId, string $cardKey): array
    {
        $card = CardKey::findByKey($cardKey);

        if (!$card) {
            throw new \Exception('卡密不存在');
        }

        if ($card->status !== CardKey::STATUS_UNUSED) {
            throw new \Exception('卡密已被使用或已过期');
        }

        if ($card->expire_at && strtotime($card->expire_at) < time()) {
            $card->status = CardKey::STATUS_EXPIRED;
            $card->save();
            throw new \Exception('卡密已过期');
        }

        $payload = is_array($card->benefit_payload)
            ? $card->benefit_payload
            : json_decode($card->benefit_payload, true);

        if (empty($payload)) {
            throw new \Exception('卡密权益数据异常');
        }

        Db::startTrans();
        try {
            $benefit = MerchantBenefit::where('merchant_id', $merchantId)->lock(true)->find();

            if (!$benefit) {
                $benefit = MerchantBenefit::createForMerchant($merchantId);
            }

            $applied = [];

            if (isset($payload['store_quota'])) {
                $benefit->store_quota += (int)$payload['store_quota'];
                $applied['store_quota'] = (int)$payload['store_quota'];
            }
            if (isset($payload['clip_power'])) {
                $benefit->clip_power += (int)$payload['clip_power'];
                $applied['clip_power'] = (int)$payload['clip_power'];
            }
            if (isset($payload['storage'])) {
                $benefit->storage += (int)$payload['storage'];
                $applied['storage'] = (int)$payload['storage'];
            }
            if (isset($payload['redpacket'])) {
                $benefit->redpacket_balance = bcadd(
                    (string)$benefit->redpacket_balance,
                    (string)$payload['redpacket'],
                    2
                );
                $applied['redpacket'] = (float)$payload['redpacket'];
            }
            if (isset($payload['version_upgrade'])) {
                $benefit->version_type = $payload['version_upgrade'];
                $benefit->store_quota = MerchantBenefit::getVersionStoreQuota($payload['version_upgrade']);
                $applied['version_upgrade'] = $payload['version_upgrade'];
            }

            $benefit->save();

            $card->status = CardKey::STATUS_USED;
            $card->merchant_id = $merchantId;
            $card->used_at = date('Y-m-d H:i:s');
            $card->save();

            Db::commit();

            Log::info('卡密激活成功', [
                'merchant_id' => $merchantId,
                'card_key' => $cardKey,
                'applied' => $applied,
            ]);

            return [
                'card_type' => $card->type,
                'applied' => $applied,
                'benefit' => [
                    'version_type' => $benefit->version_type,
                    'store_quota' => $benefit->store_quota,
                    'clip_power' => $benefit->clip_power,
                    'storage' => $benefit->storage,
                    'redpacket_balance' => $benefit->redpacket_balance,
                ],
            ];
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function generateCardKey(string $type, array $benefitPayload, int $createdBy = 0, ?string $expireAt = null): CardKey
    {
        $card = new CardKey();
        $card->card_key = CardKey::generateCode();
        $card->type = $type;
        $card->benefit_payload = $benefitPayload;
        $card->status = CardKey::STATUS_UNUSED;
        $card->expire_at = $expireAt;
        $card->created_by = $createdBy;
        $card->save();

        return $card;
    }
}
