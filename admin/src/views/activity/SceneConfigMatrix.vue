<template>
  <div class="scene-config-matrix">
    <el-tabs v-model="activeTab" type="border-card" class="scene-tabs">
      <!-- Tab 1: 现有场景配置矩阵（保持原逻辑不动） -->
      <el-tab-pane name="matrix" label="场景配置矩阵">
        <!-- 顶部筛选区域 -->
        <el-card class="search-card">
          <el-form :inline="true" :model="searchForm">
            <el-form-item label="门店名称">
              <el-input v-model="searchForm.storeName" placeholder="请输入门店名称" clearable @keyup.enter="handleSearch" />
            </el-form-item>
            <el-form-item label="状态">
              <el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 150px">
                <el-option label="启用" value="enabled" />
                <el-option label="禁用" value="disabled" />
              </el-select>
            </el-form-item>
            <el-form-item label="创建时间">
              <el-date-picker
                v-model="searchForm.dateRange"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                value-format="YYYY-MM-DD"
                style="width: 260px"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleSearch">搜索</el-button>
              <el-button @click="handleReset">重置</el-button>
            </el-form-item>
          </el-form>

          <div class="actions">
            <el-button type="primary" :disabled="!selectedRows.length" @click="handleBatchConfig">
              批量设置
            </el-button>
            <span v-if="selectedRows.length" class="selected-info">
              已选择 {{ selectedRows.length }} 个门店
            </span>
          </div>
        </el-card>

        <!-- 配置矩阵表格 -->
        <el-card class="table-card">
          <el-table
            ref="tableRef"
            :data="tableData"
            v-loading="loading"
            stripe
            border
            @selection-change="handleSelectionChange"
            style="width: 100%"
          >
            <el-table-column type="selection" width="45" fixed="left" />
            <el-table-column prop="storeName" label="门店名称" min-width="140" fixed="left" show-overflow-tooltip />

            <el-table-column v-for="col in configColumns" :key="col.key" :label="col.label" :min-width="col.width || 110" align="center">
              <template #default="{ row }">
                <el-tag
                  :type="getConfigStatus(row, col.key) ? 'success' : 'info'"
                  class="config-tag"
                  @click="openConfigDialog(row, col.key)"
                >
                  {{ getConfigStatus(row, col.key) ? '已配置' : '未配置' }}
                </el-tag>
              </template>
            </el-table-column>

            <el-table-column label="状态" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 'enabled' ? 'success' : 'info'">
                  {{ row.status === 'enabled' ? '启用' : '禁用' }}
                </el-tag>
              </template>
            </el-table-column>

            <el-table-column label="操作" width="140" fixed="right" align="center">
              <template #default="{ row }">
                <el-button size="small" type="primary" link @click="handleViewDetail(row)">详情</el-button>
                <el-button size="small" :type="row.status === 'enabled' ? 'warning' : 'success'" link @click="handleToggleStatus(row)">
                  {{ row.status === 'enabled' ? '禁用' : '启用' }}
                </el-button>
              </template>
            </el-table-column>
          </el-table>

          <el-pagination
            v-model:current-page="pagination.page"
            v-model:page-size="pagination.limit"
            :total="pagination.total"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="loadData"
            @current-change="loadData"
          />
        </el-card>
      </el-tab-pane>

      <!-- Tab 2: 聚合页区块排序 / 开关 / 主推 -->
      <el-tab-pane name="aggregate" label="聚合页区块">
        <el-card class="agg-card">
          <div class="agg-toolbar">
            <span class="agg-tip">
              顾客端 H5 聚合页（NFC 触发后展示），按下方顺序展示各功能区块；勾选关闭则该区块不展示；选"今日主推"则置顶展示。
            </span>
            <div class="agg-tip">
              <el-tag size="small">所选门店：</el-tag>
              <el-select v-model="aggStoreId" placeholder="先选择门店（也可仅查看模板）" clearable filterable style="width: 280px" @change="loadAggConfig">
                <el-option v-for="r in tableData" :key="r.id" :label="r.storeName" :value="r.id" />
              </el-select>
            </div>
          </div>

          <el-table :data="aggBlocks" v-loading="aggLoading" border>
            <el-table-column label="排序" width="90" align="center">
              <template #default="{ row, $index }">
                <div class="drag-cell">
                  <el-button-group>
                    <el-button size="small" :disabled="$index === 0" @click="moveBlock($index, -1)">↑</el-button>
                    <el-button size="small" :disabled="$index === aggBlocks.length - 1" @click="moveBlock($index, 1)">↓</el-button>
                  </el-button-group>
                  <span class="drag-index">#{{ $index + 1 }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="label" label="区块名称" min-width="160" />
            <el-table-column prop="key" label="区块标识" width="160">
              <template #default="{ row }"><el-tag size="small">{{ row.key }}</el-tag></template>
            </el-table-column>
            <el-table-column label="是否显示" width="110" align="center">
              <template #default="{ row }">
                <el-switch :model-value="row.enabled" @change="(v) => row.enabled = v" />
              </template>
            </el-table-column>
            <el-table-column label="今日主推" width="120" align="center">
              <template #default="{ row }">
                <el-radio :model-value="featuredKey" :value="row.key" @change="featuredKey = row.key">置顶</el-radio>
              </template>
            </el-table-column>
            <el-table-column label="备注" min-width="200">
              <template #default="{ row }">
                <span class="agg-remark">{{ row.remark }}</span>
              </template>
            </el-table-column>
          </el-table>

          <div class="agg-footer">
            <el-button type="primary" :loading="aggSaving" :disabled="!aggStoreId" @click="saveAggConfig">保存聚合页配置</el-button>
            <el-button :disabled="!aggStoreId" @click="loadAggConfig">重新加载</el-button>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- Tab 3: 点评链接配置 -->
      <el-tab-pane name="review" label="点评链接配置">
        <el-card class="review-card">
          <div class="review-toolbar">
            <el-tag size="small">所选门店：</el-tag>
            <el-select v-model="reviewStoreId" placeholder="先选择门店" clearable filterable style="width: 280px" @change="loadReviewConfig">
              <el-option v-for="r in tableData" :key="r.id" :label="r.storeName" :value="r.id" />
            </el-select>
            <el-tag type="info" size="small">仅录入商家在各点评平台的"门店主页"URL,顾客端"打卡点评"模块会据此生成入口</el-tag>
          </div>

          <el-form :model="reviewForm" label-width="120px" v-loading="reviewLoading" :disabled="!reviewStoreId">
            <el-divider content-position="left">点评平台入口</el-divider>
            <el-form-item label="大众点评">
              <el-input v-model="reviewForm.dianpingUrl" placeholder="https://www.dianping.com/shop/xxx" />
            </el-form-item>
            <el-form-item label="美团">
              <el-input v-model="reviewForm.meituanUrl" placeholder="https://www.meituan.com/shop/xxx" />
            </el-form-item>
            <el-form-item label="高德">
              <el-input v-model="reviewForm.gaodeUrl" placeholder="https://uri.amap.com/marker?position=xxx" />
            </el-form-item>
            <el-form-item label="百度地图">
              <el-input v-model="reviewForm.baiduUrl" placeholder="https://map.baidu.com/poi/xxx" />
            </el-form-item>
            <el-form-item label="抖音点评">
              <el-input v-model="reviewForm.douyinUrl" placeholder="https://www.douyin.com/xxx" />
            </el-form-item>

            <el-divider content-position="left">开关与文案</el-divider>
            <el-form-item label="启用点评模块">
              <el-switch v-model="reviewForm.enabled" />
            </el-form-item>
            <el-form-item label="AI 评价灵感">
              <el-switch v-model="reviewForm.aiDraftEnabled" />
              <span class="agg-remark">开启后顾客在点评页可点击"换一批"获取 AI 草稿（合规：仅作参考）</span>
            </el-form-item>
            <el-form-item label="引导文案">
              <el-input v-model="reviewForm.guideText" type="textarea" :rows="3" placeholder="例如：欢迎给我们一个真实评价，您的体验对我们非常重要" />
            </el-form-item>
          </el-form>

          <div class="agg-footer">
            <el-button type="primary" :loading="reviewSaving" :disabled="!reviewStoreId" @click="saveReviewConfig">保存点评配置</el-button>
            <el-button :disabled="!reviewStoreId" @click="loadReviewConfig">重新加载</el-button>
          </div>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- 短视频平台配置弹窗 -->
    <el-dialog v-model="dialogs.videoPlatform" title="短视频平台配置" width="560px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="选择平台">
          <el-checkbox-group v-model="configForm.platforms">
            <el-checkbox v-for="p in platformOptions" :key="p.value" :label="p.value">{{ p.label }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="每日发布上限">
          <el-input-number v-model="configForm.dailyLimit" :min="1" :max="100" />
        </el-form-item>
        <el-form-item label="发布时间段">
          <el-time-picker v-model="configForm.publishTime" is-range range-separator="至" start-placeholder="开始" end-placeholder="结束" format="HH:mm" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.videoPlatform = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('videoPlatform')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 图文发布配置弹窗 -->
    <el-dialog v-model="dialogs.imageText" title="图文发布配置" width="520px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="发布平台">
          <el-checkbox-group v-model="configForm.platforms">
            <el-checkbox v-for="p in platformOptions" :key="p.value" :label="p.value">{{ p.label }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="图文模板">
          <el-select v-model="configForm.template" placeholder="选择图文模板">
            <el-option label="标准模板" value="standard" />
            <el-option label="简约模板" value="simple" />
            <el-option label="活动模板" value="campaign" />
          </el-select>
        </el-form-item>
        <el-form-item label="水印设置">
          <el-switch v-model="configForm.watermark" active-text="开启" inactive-text="关闭" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.imageText = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('imageText')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 打卡配置弹窗 -->
    <el-dialog v-model="dialogs.checkIn" title="打卡配置" width="520px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="打卡类型">
          <el-select v-model="configForm.checkInType" placeholder="选择打卡类型">
            <el-option label="位置打卡" value="location" />
            <el-option label="扫码打卡" value="qrcode" />
            <el-option label="NFC打卡" value="nfc" />
          </el-select>
        </el-form-item>
        <el-form-item label="打卡范围(米)" v-if="configForm.checkInType === 'location'">
          <el-input-number v-model="configForm.range" :min="50" :max="5000" :step="50" />
        </el-form-item>
        <el-form-item label="每日打卡上限">
          <el-input-number v-model="configForm.dailyLimit" :min="1" :max="10" />
        </el-form-item>
        <el-form-item label="打卡奖励">
          <el-input-number v-model="configForm.reward" :min="0" :precision="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.checkIn = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('checkIn')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 关注门店账号配置弹窗 -->
    <el-dialog v-model="dialogs.follow" title="关注门店账号配置" width="520px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="平台">
          <el-select v-model="configForm.platform" placeholder="选择平台">
            <el-option label="抖音" value="douyin" />
            <el-option label="快手" value="kuaishou" />
            <el-option label="小红书" value="xiaohongshu" />
            <el-option label="微信公众号" value="wechat" />
          </el-select>
        </el-form-item>
        <el-form-item label="关注链接">
          <el-input v-model="configForm.followUrl" placeholder="请输入关注链接" />
        </el-form-item>
        <el-form-item label="引导文案">
          <el-input v-model="configForm.guideText" type="textarea" :rows="2" placeholder="请输入引导文案" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.follow = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('follow')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 点赞/分享配置弹窗 -->
    <el-dialog v-model="dialogs.likeShare" title="点赞/分享配置" width="520px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="点赞数量要求">
          <el-input-number v-model="configForm.likeCount" :min="1" :max="100" />
        </el-form-item>
        <el-form-item label="分享次数要求">
          <el-input-number v-model="configForm.shareCount" :min="1" :max="50" />
        </el-form-item>
        <el-form-item label="完成奖励(元)">
          <el-input-number v-model="configForm.reward" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="分享平台">
          <el-checkbox-group v-model="configForm.sharePlatforms">
            <el-checkbox label="wechat">微信</el-checkbox>
            <el-checkbox label="moments">朋友圈</el-checkbox>
            <el-checkbox label="qq">QQ</el-checkbox>
            <el-checkbox label="weibo">微博</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.likeShare = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('likeShare')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 团购商品关联弹窗 -->
    <el-dialog v-model="dialogs.groupBuy" title="团购商品关联" width="560px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="团购商品ID">
          <el-input v-model="configForm.productId" placeholder="请输入团购商品ID" />
        </el-form-item>
        <el-form-item label="优惠价格(元)">
          <el-input-number v-model="configForm.price" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="优惠描述">
          <el-input v-model="configForm.description" type="textarea" :rows="2" placeholder="请输入优惠描述" />
        </el-form-item>
        <el-form-item label="有效期">
          <el-date-picker v-model="configForm.expireDate" type="date" placeholder="选择有效期" value-format="YYYY-MM-DD" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.groupBuy = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('groupBuy')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- Wi-Fi配置弹窗 -->
    <el-dialog v-model="dialogs.wifi" title="Wi-Fi配置" width="480px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="SSID">
          <el-input v-model="configForm.ssid" placeholder="请输入Wi-Fi名称" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input v-model="configForm.wifiPassword" placeholder="请输入Wi-Fi密码" show-password />
        </el-form-item>
        <el-form-item label="自动连接">
          <el-switch v-model="configForm.autoConnect" active-text="开启" inactive-text="关闭" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.wifi = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('wifi')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 微信名片配置弹窗 -->
    <el-dialog v-model="dialogs.wechatCard" title="微信名片配置" width="480px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="微信号">
          <el-input v-model="configForm.wechatId" placeholder="请输入微信号" />
        </el-form-item>
        <el-form-item label="名片链接">
          <el-input v-model="configForm.cardUrl" placeholder="请输入名片链接" />
        </el-form-item>
        <el-form-item label="引导文案">
          <el-input v-model="configForm.guideText" placeholder="请输入引导文案" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.wechatCard = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('wechatCard')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 自定义链接配置弹窗 -->
    <el-dialog v-model="dialogs.customLink" title="自定义链接配置" width="520px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="链接标题">
          <el-input v-model="configForm.linkTitle" placeholder="请输入链接标题" />
        </el-form-item>
        <el-form-item label="链接URL">
          <el-input v-model="configForm.linkUrl" placeholder="请输入链接地址" />
        </el-form-item>
        <el-form-item label="打开方式">
          <el-radio-group v-model="configForm.openType">
            <el-radio label="_blank">新窗口</el-radio>
            <el-radio label="_self">当前窗口</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.customLink = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('customLink')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 碰一碰配置弹窗 -->
    <el-dialog v-model="dialogs.nfcTouch" title="碰一碰配置" width="480px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="启用碰一碰">
          <el-switch v-model="configForm.nfcEnabled" active-text="开启" inactive-text="关闭" />
        </el-form-item>
        <el-form-item label="触发动作">
          <el-select v-model="configForm.nfcAction" placeholder="选择触发动作">
            <el-option label="打开页面" value="openPage" />
            <el-option label="领取优惠券" value="getCoupon" />
            <el-option label="关注公众号" value="follow" />
          </el-select>
        </el-form-item>
        <el-form-item label="触发页面">
          <el-input v-model="configForm.nfcPage" placeholder="请输入触发页面地址" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.nfcTouch = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('nfcTouch')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 扫码体验配置弹窗 -->
    <el-dialog v-model="dialogs.scanExperience" title="扫码体验配置" width="520px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="体验页面">
          <el-select v-model="configForm.pageType" placeholder="选择体验页面">
            <el-option label="活动页面" value="activity" />
            <el-option label="门店首页" value="storeHome" />
            <el-option label="优惠券页" value="coupon" />
          </el-select>
        </el-form-item>
        <el-form-item label="引导文案">
          <el-input v-model="configForm.guideText" placeholder="请输入引导文案" />
        </el-form-item>
        <el-form-item label="自动播放">
          <el-switch v-model="configForm.autoPlay" active-text="开启" inactive-text="关闭" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.scanExperience = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('scanExperience')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 评价文案配置弹窗 -->
    <el-dialog v-model="dialogs.reviewText" title="评价文案配置" width="520px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="文案模板">
          <el-select v-model="configForm.template" placeholder="选择文案模板">
            <el-option label="好评模板" value="positive" />
            <el-option label="体验模板" value="experience" />
            <el-option label="自定义模板" value="custom" />
          </el-select>
        </el-form-item>
        <el-form-item label="自定义文案">
          <el-input v-model="configForm.customText" type="textarea" :rows="3" placeholder="请输入自定义评价文案" />
        </el-form-item>
        <el-form-item label="星级要求">
          <el-rate v-model="configForm.starRating" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.reviewText = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('reviewText')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- e代驾配置弹窗 -->
    <el-dialog v-model="dialogs.edaijia" title="e代驾配置" width="480px" @close="resetConfigForm">
      <el-form :model="configForm" label-width="100px">
        <el-form-item label="启用e代驾">
          <el-switch v-model="configForm.enabled" active-text="开启" inactive-text="关闭" />
        </el-form-item>
        <el-form-item label="商户ID">
          <el-input v-model="configForm.merchantId" placeholder="请输入e代驾商户ID" />
        </el-form-item>
        <el-form-item label="优惠金额(元)">
          <el-input-number v-model="configForm.discount" :min="0" :precision="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogs.edaijia = false">取消</el-button>
        <el-button type="primary" @click="saveConfig('edaijia')" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 批量设置弹窗 -->
    <el-dialog v-model="batchDialogVisible" title="批量设置" width="600px" @close="resetBatchForm">
      <el-form :model="batchForm" label-width="120px">
        <el-form-item label="已选门店">
          <span class="batch-store-count">{{ selectedRows.length }} 个门店</span>
        </el-form-item>
        <el-form-item label="配置项">
          <el-select v-model="batchForm.configKey" placeholder="请选择要配置的项目" style="width: 100%">
            <el-option v-for="col in configColumns" :key="col.key" :label="col.label" :value="col.key" />
          </el-select>
        </el-form-item>
        <el-form-item label="配置内容">
          <el-input v-model="batchForm.configValue" type="textarea" :rows="4" placeholder="请输入配置内容(JSON格式)" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="batchDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleBatchSave" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailDialogVisible" title="门店场景配置详情" width="700px">
      <el-descriptions :column="2" border v-if="currentRow">
        <el-descriptions-item label="门店名称">{{ currentRow.storeName }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="currentRow.status === 'enabled' ? 'success' : 'info'">
            {{ currentRow.status === 'enabled' ? '启用' : '禁用' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item v-for="col in configColumns" :key="col.key" :label="col.label">
          <el-tag :type="getConfigStatus(currentRow, col.key) ? 'success' : 'info'">
            {{ getConfigStatus(currentRow, col.key) ? '已配置' : '未配置' }}
          </el-tag>
        </el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="detailDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { sceneConfigApi, reviewApi } from '@/api/index.js'
import { normalizePagination } from '@/utils/responseHelper'

// 配置列定义
const configColumns = [
  { key: 'scanExperience', label: '扫码体验', width: 100 },
  { key: 'videoPlatform', label: '短视频平台', width: 110 },
  { key: 'imageText', label: '图文发布', width: 100 },
  { key: 'reviewText', label: '评价文案', width: 100 },
  { key: 'checkIn', label: '打卡配置', width: 100 },
  { key: 'follow', label: '关注门店账号', width: 120 },
  { key: 'likeShare', label: '点赞/分享', width: 100 },
  { key: 'groupBuy', label: '优惠团购', width: 100 },
  { key: 'wifi', label: 'Wi-Fi', width: 90 },
  { key: 'wechatCard', label: '添加微信', width: 100 },
  { key: 'customLink', label: '自定义链接', width: 110 },
  { key: 'edaijia', label: 'e代驾', width: 90 },
  { key: 'nfcTouch', label: '碰一碰', width: 90 }
]

// 平台选项
const platformOptions = [
  { label: '抖音', value: 'douyin' },
  { label: '快手', value: 'kuaishou' },
  { label: '小红书', value: 'xiaohongshu' },
  { label: '视频号', value: 'shipinhao' },
  { label: 'B站', value: 'bilibili' }
]

// 搜索表单
const searchForm = reactive({
  storeName: '',
  status: '',
  dateRange: null
})

// 表格相关
const tableRef = ref(null)
const tableData = ref([])
const loading = ref(false)
const selectedRows = ref([])
const pagination = reactive({
  page: 1,
  limit: 10,
  total: 0
})

// 弹窗控制
const dialogs = reactive({
  scanExperience: false,
  videoPlatform: false,
  imageText: false,
  reviewText: false,
  checkIn: false,
  follow: false,
  likeShare: false,
  groupBuy: false,
  wifi: false,
  wechatCard: false,
  customLink: false,
  edaijia: false,
  nfcTouch: false
})

const batchDialogVisible = ref(false)
const detailDialogVisible = ref(false)
const saving = ref(false)
const currentRow = ref(null)

// 配置表单
const configForm = reactive({
  storeId: null,
  configKey: '',
  platforms: [],
  dailyLimit: 5,
  publishTime: null,
  template: '',
  watermark: false,
  checkInType: 'location',
  range: 200,
  reward: 0,
  platform: '',
  followUrl: '',
  guideText: '',
  likeCount: 1,
  shareCount: 1,
  sharePlatforms: [],
  productId: '',
  price: 0,
  description: '',
  expireDate: '',
  ssid: '',
  wifiPassword: '',
  autoConnect: false,
  wechatId: '',
  cardUrl: '',
  linkTitle: '',
  linkUrl: '',
  openType: '_blank',
  nfcEnabled: false,
  nfcAction: 'openPage',
  nfcPage: '',
  pageType: 'activity',
  autoPlay: false,
  customText: '',
  starRating: 5,
  enabled: true,
  merchantId: '',
  discount: 0
})

// 批量表单
const batchForm = reactive({
  configKey: '',
  configValue: ''
})


const getConfigStatus = (row, key) => {
  return row.configs && row.configs[key]
}

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      limit: pagination.limit
    }
    if (searchForm.storeName) params.storeName = searchForm.storeName
    if (searchForm.status) params.status = searchForm.status
    if (searchForm.dateRange && searchForm.dateRange.length === 2) {
      params.startDate = searchForm.dateRange[0]
      params.endDate = searchForm.dateRange[1]
    }

    const res = await sceneConfigApi.getSceneConfigList(params)
    const { list, total } = normalizePagination(res)
    tableData.value = list
    pagination.total = total
  } catch (error) {
    console.error('加载场景配置列表失败:', error)
    tableData.value = []
    pagination.total = 0
    ElMessage.error('加载场景配置列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.page = 1
  loadData()
}

const handleReset = () => {
  searchForm.storeName = ''
  searchForm.status = ''
  searchForm.dateRange = null
  handleSearch()
}

const handleSelectionChange = (rows) => {
  selectedRows.value = rows
}

const resetConfigForm = () => {
  Object.assign(configForm, {
    platforms: [],
    dailyLimit: 5,
    publishTime: null,
    template: '',
    watermark: false,
    checkInType: 'location',
    range: 200,
    reward: 0,
    platform: '',
    followUrl: '',
    guideText: '',
    likeCount: 1,
    shareCount: 1,
    sharePlatforms: [],
    productId: '',
    price: 0,
    description: '',
    expireDate: '',
    ssid: '',
    wifiPassword: '',
    autoConnect: false,
    wechatId: '',
    cardUrl: '',
    linkTitle: '',
    linkUrl: '',
    openType: '_blank',
    nfcEnabled: false,
    nfcAction: 'openPage',
    nfcPage: '',
    pageType: 'activity',
    autoPlay: false,
    customText: '',
    starRating: 5,
    enabled: true,
    merchantId: '',
    discount: 0
  })
}

const openConfigDialog = (row, key) => {
  currentRow.value = row
  configForm.storeId = row.id
  configForm.configKey = key
  dialogs[key] = true
}

const saveConfig = async (configKey) => {
  saving.value = true
  try {
    const data = {
      storeId: configForm.storeId,
      configKey: configKey,
      configValue: { ...configForm }
    }
    await sceneConfigApi.saveSceneConfig(data)
    ElMessage.success('保存成功')
    dialogs[configKey] = false
    loadData()
  } catch (error) {
    console.error('保存配置失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

const handleToggleStatus = async (row) => {
  const newStatus = row.status === 'enabled' ? 'disabled' : 'enabled'
  const actionText = newStatus === 'enabled' ? '启用' : '禁用'
  try {
    await ElMessageBox.confirm(`确定${actionText}门店 "${row.storeName}" 的场景配置吗？`, '提示', { type: 'warning' })
    await sceneConfigApi.toggleSceneConfigStatus({ id: row.id, status: newStatus })
    row.status = newStatus
    ElMessage.success('状态已更新')
  } catch (error) {
    if (error !== 'cancel') {
      console.error('切换状态失败:', error)
      ElMessage.error('状态更新失败')
    }
  }
}

const handleViewDetail = (row) => {
  currentRow.value = row
  detailDialogVisible.value = true
}

const handleBatchConfig = () => {
  batchForm.configKey = ''
  batchForm.configValue = ''
  batchDialogVisible.value = true
}

const resetBatchForm = () => {
  batchForm.configKey = ''
  batchForm.configValue = ''
}

const handleBatchSave = async () => {
  if (!batchForm.configKey) {
    ElMessage.warning('请选择配置项')
    return
  }
  saving.value = true
  try {
    const storeIds = selectedRows.value.map(r => r.id)
    await sceneConfigApi.batchSaveSceneConfig({
      storeIds,
      configKey: batchForm.configKey,
      configValue: batchForm.configValue
    })
    ElMessage.success('批量设置成功')
    batchDialogVisible.value = false
    loadData()
  } catch (error) {
    console.error('批量设置失败:', error)
    ElMessage.error('批量设置失败')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadData()
})

// ============== 聚合页区块配置（TAB 2）==============
const activeTab = ref('matrix')

const AGG_BLOCKS_DEFAULT = [
  { key: 'wifi',         label: 'Wi-Fi 一键连',   enabled: true,  remark: '顾客进店首选：自动分发 iOS/Android 连接方案' },
  { key: 'video',        label: '视频发布',       enabled: true,  remark: '抖音/快手/小红书 + 文案池换一批' },
  { key: 'custom_link',  label: '自定义链接',     enabled: false, remark: '运营可在后台配置其它外链入口' },
  { key: 'contact',      label: '营销私域',       enabled: true,  remark: '加微信 / 加企微 / 加QQ（即将）' },
  { key: 'group_buy',    label: '优惠团购',       enabled: true,  remark: '商品列表由「团购商品」模块提供' },
  { key: 'review',       label: '打卡点评',       enabled: true,  remark: '大众点评/美团/高德/百度/抖音点评入口' },
  { key: 'lottery',      label: '抽奖活动',       enabled: false, remark: '由「抽奖活动」模块提供，关联 device' }
]

const aggLoading = ref(false)
const aggSaving = ref(false)
const aggStoreId = ref(null)
const featuredKey = ref('')
const aggBlocks = ref(AGG_BLOCKS_DEFAULT.map(b => ({ ...b })))

const moveBlock = (idx, dir) => {
  const arr = aggBlocks.value
  const target = idx + dir
  if (target < 0 || target >= arr.length) return
  const t = arr[idx]
  arr[idx] = arr[target]
  arr[target] = t
}

const loadAggConfig = async () => {
  if (!aggStoreId.value) {
    aggBlocks.value = AGG_BLOCKS_DEFAULT.map(b => ({ ...b }))
    featuredKey.value = ''
    return
  }
  aggLoading.value = true
  try {
    // 复用现有 SceneConfig 保存接口，将 blocks/featured 走 review_config 同槽位
    // 这里优先调 SceneConfig 详情，若聚合页字段已存在则回填
    const res = await sceneConfigApi.getSceneConfigDetail({ storeId: aggStoreId.value })
    const data = res && res.configs ? res.configs : null
    const aggCfg = data && (data.aggregation || data.agg_page)
    if (aggCfg && typeof aggCfg === 'object') {
      const order = Array.isArray(aggCfg.order) ? aggCfg.order : AGG_BLOCKS_DEFAULT.map(b => b.key)
      const enabledMap = (aggCfg.enabled && typeof aggCfg.enabled === 'object') ? aggCfg.enabled : {}
      aggBlocks.value = order.map(k => {
        const def = AGG_BLOCKS_DEFAULT.find(b => b.key === k) || { key: k, label: k, remark: '' }
        return { ...def, enabled: enabledMap[k] !== false }
      })
      featuredKey.value = aggCfg.featured || ''
    } else {
      aggBlocks.value = AGG_BLOCKS_DEFAULT.map(b => ({ ...b }))
      featuredKey.value = ''
    }
  } catch (err) {
    console.warn('加载聚合页配置失败，使用默认值:', err)
    aggBlocks.value = AGG_BLOCKS_DEFAULT.map(b => ({ ...b }))
    featuredKey.value = ''
  } finally {
    aggLoading.value = false
  }
}

const saveAggConfig = async () => {
  if (!aggStoreId.value) {
    ElMessage.warning('请先选择门店')
    return
  }
  aggSaving.value = true
  try {
    const order = aggBlocks.value.map(b => b.key)
    const enabled = {}
    aggBlocks.value.forEach(b => { enabled[b.key] = b.enabled })
    const payload = {
      storeId: aggStoreId.value,
      configKey: 'aggregation',
      configValue: { order, enabled, featured: featuredKey.value || '' }
    }
    await sceneConfigApi.saveSceneConfig(payload)
    ElMessage.success('聚合页配置已保存')
  } catch (err) {
    console.error('保存聚合页配置失败:', err)
    ElMessage.error('保存失败')
  } finally {
    aggSaving.value = false
  }
}

// ============== 点评链接配置（TAB 3）==============
const reviewStoreId = ref(null)
const reviewLoading = ref(false)
const reviewSaving = ref(false)

const blankReview = () => ({
  dianpingUrl: '',
  meituanUrl: '',
  gaodeUrl: '',
  baiduUrl: '',
  douyinUrl: '',
  enabled: true,
  aiDraftEnabled: true,
  guideText: ''
})
const reviewForm = reactive(blankReview())

const loadReviewConfig = async () => {
  if (!reviewStoreId.value) {
    Object.assign(reviewForm, blankReview())
    return
  }
  reviewLoading.value = true
  try {
    const res = await reviewApi.getConfig(reviewStoreId.value)
    const cfg = (res && (res.data || res)) || {}
    Object.assign(reviewForm, blankReview(), {
      dianpingUrl: cfg.dianpingUrl || cfg.dianping_url || cfg.dianping || '',
      meituanUrl: cfg.meituanUrl || cfg.meituan_url || cfg.meituan || '',
      gaodeUrl: cfg.gaodeUrl || cfg.gaode_url || cfg.gaode || '',
      baiduUrl: cfg.baiduUrl || cfg.baidu_url || cfg.baidu || '',
      douyinUrl: cfg.douyinUrl || cfg.douyin_url || cfg.douyin || '',
      enabled: cfg.enabled !== false,
      aiDraftEnabled: cfg.aiDraftEnabled !== false,
      guideText: cfg.guideText || cfg.guide_text || ''
    })
  } catch (err) {
    console.warn('加载点评配置失败，使用空表单:', err)
    Object.assign(reviewForm, blankReview())
  } finally {
    reviewLoading.value = false
  }
}

const saveReviewConfig = async () => {
  if (!reviewStoreId.value) {
    ElMessage.warning('请先选择门店')
    return
  }
  reviewSaving.value = true
  try {
    await reviewApi.saveConfig({
      device_id: reviewStoreId.value,
      dianping_url: reviewForm.dianpingUrl,
      meituan_url: reviewForm.meituanUrl,
      gaode_url: reviewForm.gaodeUrl,
      baidu_url: reviewForm.baiduUrl,
      douyin_url: reviewForm.douyinUrl,
      enabled: reviewForm.enabled ? 1 : 0,
      ai_draft_enabled: reviewForm.aiDraftEnabled ? 1 : 0,
      guide_text: reviewForm.guideText
    })
    ElMessage.success('点评配置已保存')
  } catch (err) {
    console.error('保存点评配置失败:', err)
    ElMessage.error('保存失败')
  } finally {
    reviewSaving.value = false
  }
}
</script>

<style lang="scss" scoped>
.scene-config-matrix {
  padding: 20px;

  .scene-tabs {
    :deep(.el-tabs__content) {
      padding: 12px 0 0 0;
    }
  }

  .agg-card,
  .review-card {
    margin-bottom: 16px;

    .agg-toolbar,
    .review-toolbar {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 12px;

      .agg-tip {
        font-size: 13px;
        color: #606266;
      }
    }

    .agg-footer {
      margin-top: 16px;
      display: flex;
      gap: 10px;
    }

    .drag-cell {
      display: flex;
      align-items: center;
      gap: 6px;
      justify-content: center;
      .drag-index {
        margin-left: 6px;
        color: #909399;
        font-size: 12px;
      }
    }

    .agg-remark {
      font-size: 12px;
      color: #909399;
    }
  }
}

.search-card {
  margin-bottom: 20px;

  .actions {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .selected-info {
    font-size: 13px;
    color: #909399;
  }
}

.table-card {
  :deep(.el-pagination) {
    margin-top: 20px;
    justify-content: flex-end;
  }
}

.config-tag {
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s;

  &:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
  }
}

.batch-store-count {
  font-weight: 500;
  color: #409eff;
}
</style>
