<?php
// 任务动作插件注册表
// 新增业务只需：实现 ActionPluginInterface → 在此登记
return [
    'plugins' => [
        // 内容分发类（信任模式：上传截图 → 查重 → 人工审核）
        'douyin_publish'       => \app\action\plugins\DouyinPublishAction::class,
        'kuaishou_publish'     => \app\action\plugins\KuaishouPublishAction::class,
        'xiaohongshu_publish'  => \app\action\plugins\XiaohongshuPublishAction::class,
        'moments_share'        => \app\action\plugins\MomentsShareAction::class,

        // 私域沉淀类
        'wework_add_friend'    => \app\action\plugins\WeworkAddFriendAction::class,      // 回调核验
        'official_account_follow' => \app\action\plugins\OfficialAccountFollowAction::class, // 回调核验
        'group_share'          => \app\action\plugins\GroupShareAction::class,           // 信任模式

        // 交易转化类（系统直判）
        'claim_coupon'         => \app\action\plugins\ClaimCouponAction::class,
    ],
];
