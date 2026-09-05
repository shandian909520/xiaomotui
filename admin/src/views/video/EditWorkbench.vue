<template>
  <div class="edit-workbench">
    <!-- ==================== 视图一：模式选择 ==================== -->
    <div v-if="currentView === 'select'" class="view-mode-select">
      <div class="page-header">
        <h2>剪辑工作台</h2>
        <p class="subtitle">选择适合您的创作模式</p>
      </div>

      <div class="mode-cards">
        <div class="mode-card" @click="enterMode('oneClick')">
          <div class="mode-icon">
            <el-icon :size="48"><MagicStick /></el-icon>
          </div>
          <div class="mode-name">一键成片</div>
          <div class="mode-desc">快速生成</div>
          <div class="mode-target">适用人群：新手小白</div>
          <div class="mode-steps">
            <span>选素材</span><span class="arrow">&rarr;</span>
            <span>选模板</span><span class="arrow">&rarr;</span>
            <span>生成</span>
          </div>
        </div>

        <div class="mode-card" @click="enterMode('batch')">
          <div class="mode-icon">
            <el-icon :size="48"><Film /></el-icon>
          </div>
          <div class="mode-name">批量混剪</div>
          <div class="mode-desc">批量产出</div>
          <div class="mode-target">适用人群：有经验的创作者</div>
          <div class="mode-steps">
            <span>选素材池</span><span class="arrow">&rarr;</span>
            <span>配置参数</span><span class="arrow">&rarr;</span>
            <span>批量生成</span>
          </div>
        </div>

        <div class="mode-card" @click="enterMode('storyboard')">
          <div class="mode-icon">
            <el-icon :size="48"><VideoCamera /></el-icon>
          </div>
          <div class="mode-name">分镜剪辑</div>
          <div class="mode-desc">专业创作</div>
          <div class="mode-target">适用人群：专业剪辑师</div>
          <div class="mode-steps">
            <span>添加镜头</span><span class="arrow">&rarr;</span>
            <span>配置参数</span><span class="arrow">&rarr;</span>
            <span>导出</span>
          </div>
        </div>
      </div>

      <!-- 底部区域 -->
      <div class="bottom-section">
        <div class="section-block">
          <h3>我的模板</h3>
          <div class="horizontal-scroll">
            <div v-if="myTemplates.length === 0" class="empty-hint">暂无模板</div>
            <div
              v-for="tpl in myTemplates"
              :key="tpl.id"
              class="template-card"
              @click="useTemplate(tpl)"
            >
              <div class="tpl-thumb">
                <el-image v-if="tpl.video_url" :src="tpl.video_url" fit="cover">
                  <template #error><div class="tpl-placeholder">模板</div></template>
                </el-image>
                <div v-else class="tpl-placeholder">模板</div>
              </div>
              <div class="tpl-name">{{ tpl.name }}</div>
            </div>
          </div>
        </div>

        <div class="section-block">
          <h3>最近工程</h3>
          <el-table :data="recentProjects" size="small" stripe>
            <el-table-column prop="name" label="名称" min-width="160" show-overflow-tooltip />
            <el-table-column prop="mode" label="模式" width="100">
              <template #default="{ row }">
                <el-tag size="small">{{ getModeName(row.mode) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="update_time" label="更新时间" width="160" />
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button link type="primary" size="small" @click="openProject(row)">继续编辑</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>
    </div>

    <!-- ==================== 统一编辑器主界面（所有模式共用） ==================== -->
    <div v-else class="view-editor">
      <!-- 顶部工具栏 -->
      <div class="task-button">
        <div class="task-left">
          <el-button class="toolbar-btn" @click="backToSelect">
            <el-icon><ArrowLeft /></el-icon>
          </el-button>
        </div>
        <div class="task-center">
          <div class="editor-tabs">
            <div
              v-for="(tab, idx) in editorModeTabs"
              :key="idx"
              :class="['editor-tab', { active: currentEditorMode === tab.key }]"
              @click="switchEditorMode(tab.key)"
            >{{ tab.name }}</div>
          </div>
          <span class="three-desc-link" @click="showModeDialog = true; currentView = 'select'">三种剪辑说明</span>
        </div>
        <div class="task-right">
          <el-button v-if="currentEditorMode !== 'oneClick'" class="toolbar-btn" @click="handleSaveAsTemplate">保存为模板</el-button>
          <el-button class="toolbar-btn" @click="handleMyTemplates">我的模版</el-button>
          <el-button class="toolbar-btn primary" :loading="saving" @click="saveProject">保存剪辑工程</el-button>
          <el-button class="toolbar-btn export-btn" :loading="generating" @click="handleExport">导出</el-button>
        </div>
      </div>

      <!-- 主体内容区 -->
      <div class="editor-body">
        <!-- 左侧/中间区域 -->
        <div class="editor-main">
          <!-- ====== 分镜剪辑：镜头Tab面板 ====== -->
          <div v-if="currentEditorMode === 'storyboard'" class="sence-container">
            <div class="c-tit">
              <div class="title-l">
                <div class="shot-tabs">
                  <div
                    v-for="(shot, idx) in shots"
                    :key="shot.id"
                    :class="['shot-tab', { active: selectedShotId === shot.id }]"
                    @click="selectedShotId = shot.id"
                  >镜头（{{ idx + 1 }}）</div>
                </div>
              </div>
              <div class="add-r">
                <el-button size="small" @click="showTFDialog = true; tfActiveTab = 'transition'">转场/滤镜</el-button>
                <el-button size="small" type="primary" :icon="Plus" @click="showMaterialSelector = true">添加镜头</el-button>
              </div>
            </div>
            <!-- 当前镜头详情 -->
            <div v-if="currentShot" class="shot-detail">
              <div class="shot-detail-header">
                <span class="shot-detail-title">镜头({{ shots.findIndex(s => s.id === currentShot.id) + 1 }})</span>
                <span class="shot-detail-dur">时长：{{ currentShot.duration }}s</span>
              </div>
              <div class="config-row">
                <span class="config-tag">镜头素材({{ currentShot.materialId ? 1 : 0 }})</span>
                <el-button size="small" type="primary" :icon="Plus" @click="showMaterialSelector = true">添加</el-button>
              </div>
              <div class="config-row">
                <div class="config-item">
                  <span class="config-label">自定义镜头时长</span>
                  <el-switch v-model="customShotDuration" active-color="#834EFF" />
                </div>
              </div>
              <div class="config-row">
                <div class="config-item">
                  <span class="config-label">消除原声</span>
                  <el-switch v-model="currentShot.muteOriginal" active-color="#834EFF" />
                </div>
              </div>
            </div>
            <div v-else class="empty-lens">点击"添加镜头"开始创作</div>
          </div>

          <!-- ====== 一键成片 / 批量混剪：简单镜头素材面板 ====== -->
          <div v-else class="sence-container">
            <div class="c-tit">
              <div class="title-l">
                <el-icon :size="18"><VideoCamera /></el-icon>
                <span class="title-text">镜头素材({{ shots.length }})</span>
              </div>
              <div class="add-r">
                <!-- 批量混剪：显示消除原声 -->
                <template v-if="currentEditorMode === 'batch'">
                  <span class="config-label">消除原声</span>
                  <el-switch v-model="batchMuteOriginal" active-color="#834EFF" />
                </template>
                <el-button size="small" @click="showTFDialog = true; tfActiveTab = 'transition'">转场/滤镜</el-button>
                <el-button size="small" type="primary" :icon="Plus" @click="showMaterialSelector = true">添加</el-button>
              </div>
            </div>
            <div class="lens-list" @dragover.prevent @drop="onDropShot">
              <div
                v-for="(shot, idx) in shots"
                :key="shot.id"
                class="lens-item"
                :class="{ active: selectedShotId === shot.id }"
                draggable="true"
                @dragstart="onDragStartShot($event, idx)"
                @click="selectedShotId = shot.id"
              >
                <div class="lens-thumb">
                  <el-image v-if="shot.thumbnail" :src="shot.thumbnail" fit="cover" />
                  <el-icon v-else :size="20"><Picture /></el-icon>
                </div>
                <div class="lens-info">
                  <span class="lens-name">{{ shot.name || `镜头 ${idx + 1}` }}</span>
                  <span class="lens-dur">{{ shot.duration }}s</span>
                </div>
                <el-icon class="lens-delete" @click.stop="removeShot(idx)"><Delete /></el-icon>
              </div>
              <div v-if="shots.length === 0" class="empty-lens">
                点击"添加"按钮或拖拽素材到此处
              </div>
            </div>
          </div>

          <!-- ====== 剪辑配置 / 高级设置 ====== -->
          <div class="bottom-main">
            <div class="c-tit">
              <div class="title-l">
                <el-icon :size="18"><Setting /></el-icon>
                <span class="title-text">{{ currentEditorMode === 'oneClick' ? '高级设置' : '剪辑配置' }}</span>
              </div>
            </div>

            <!-- 全局设置行 -->
            <div class="config-row">
              <span class="config-tag">全局设置</span>
              <div class="config-item">
                <el-switch v-model="globalConfig.subtitle.show" active-color="#834EFF" />
                <span class="config-label">显示字幕</span>
              </div>
              <div class="config-item">
                <el-checkbox v-model="globalConfig.subtitle.applyAll">字幕/花字应用到全局</el-checkbox>
              </div>
            </div>

            <div class="config-line"></div>

            <!-- 字幕配置 - 分镜剪辑默认"分镜字幕" -->
            <div class="config-row">
              <span class="config-tag">字幕配置</span>
              <el-radio-group v-model="subtitleMode" size="small" class="radio-mini">
                <el-radio v-if="currentEditorMode !== 'storyboard'" label="none">无需字幕</el-radio>
                <el-radio v-if="currentEditorMode === 'storyboard'" label="storyboard">分镜字幕</el-radio>
                <el-radio label="global">全局字幕</el-radio>
              </el-radio-group>
            </div>

            <!-- 一键成片：时长设置 -->
            <div v-if="currentEditorMode === 'oneClick'" class="config-row">
              <span class="config-tag">时长设置</span>
              <span class="config-text">生成视频时长:</span>
              <el-input-number v-model="globalConfig.durationMin" :min="1" :max="40" size="small" controls-position="right" style="width: 80px" />
              <span class="config-text">-</span>
              <el-input-number v-model="globalConfig.durationMax" :min="1" :max="40" size="small" controls-position="right" style="width: 80px" />
              <span class="config-text">秒</span>
            </div>

            <!-- 批量混剪：合成规则 -->
            <div v-if="currentEditorMode === 'batch'" class="config-section">
              <div class="config-row">
                <span class="config-tag">合成规则</span>
              </div>
              <div class="config-row indent">
                <span class="config-text">最终的成片视频抽取镜头</span>
                <el-input-number v-model="batchExtractMin" :min="1" :max="20" size="small" controls-position="right" style="width: 70px" />
                <span class="config-text">-</span>
                <el-input-number v-model="batchExtractMax" :min="1" :max="20" size="small" controls-position="right" style="width: 70px" />
                <span class="config-text">个</span>
              </div>
              <div class="config-row indent">
                <span class="config-text">每个镜头抽取</span>
                <el-input-number v-model="batchClipDurationMin" :min="1" :max="10000" size="small" controls-position="right" style="width: 70px" />
                <span class="config-text">-</span>
                <el-input-number v-model="batchClipDurationMax" :min="1" :max="10000" size="small" controls-position="right" style="width: 70px" />
                <span class="config-text">秒</span>
              </div>
              <div class="config-row indent">
                <span class="config-desc">(按此配置生成的成片大约{{ batchClipDurationMin }}~{{ batchClipDurationMax }}s/条)</span>
              </div>

              <!-- 画面加速 -->
              <div class="config-row">
                <span class="config-tag">画面加速</span>
                <el-popover placement="left" :width="280" trigger="click">
                  <template #reference>
                    <el-button size="small" class="setting-btn">设置</el-button>
                  </template>
                  <div class="popover-setting">
                    <div class="popover-row">
                      <span class="popover-label">画面加速：</span>
                      <el-slider v-model="speedValue" :min="0.75" :max="2" :step="0.25" :marks="{ 0.75: '0.75', 1.0: '1.0', 1.25: '1.25', 1.5: '1.5', 1.75: '1.75', 2.0: '2.0' }" :show-tooltip="false" />
                    </div>
                  </div>
                </el-popover>
                <span class="config-desc">当前: {{ speedValue.toFixed(1) }}倍速度</span>
              </div>
            </div>

            <!-- 分镜剪辑：镜头配音 -->
            <div v-if="currentEditorMode === 'storyboard'" class="config-section">
              <div class="config-row">
                <span class="config-tag">镜头({{ currentShotIndex + 1 }})</span>
                <span class="config-desc">镜头配音({{ currentShot?.voiceText ? 1 : 0 }})</span>
                <el-button size="small" class="setting-btn" @click="showDubbingSetting = !showDubbingSetting">添加配音演员</el-button>
              </div>
            </div>

            <!-- 音量调整 -->
            <div class="config-row">
              <span class="config-tag">音量调整</span>
              <el-popover placement="left" :width="280" trigger="click">
                <template #reference>
                  <el-button size="small" class="setting-btn">设置</el-button>
                </template>
                <div class="popover-setting">
                  <div class="popover-row">
                    <span class="popover-label">配音音量：</span>
                    <el-slider v-model="globalConfig.voiceVolume" :min="0" :max="3" :step="0.1" :show-tooltip="false" />
                    <el-input-number v-model="globalConfig.voiceVolume" :min="0" :max="3" :step="0.1" :precision="1" size="small" :controls="false" style="width:56px" />
                  </div>
                  <div class="popover-row">
                    <span class="popover-label">背景音乐：</span>
                    <el-slider v-model="globalConfig.music.volume" :min="0" :max="100" :step="1" :show-tooltip="false" />
                    <el-input-number :model-value="Number((globalConfig.music.volume / 100).toFixed(1))" @update:model-value="globalConfig.music.volume = Math.round($event * 100)" :min="0" :max="1" :step="0.1" :precision="1" size="small" :controls="false" style="width:56px" />
                  </div>
                  <div class="popover-row">
                    <span class="popover-label">配音语速：</span>
                    <el-slider v-model="globalConfig.voiceSpeed" :min="0.2" :max="3" :step="0.1" :show-tooltip="false" />
                    <el-input-number v-model="globalConfig.voiceSpeed" :min="0.2" :max="3" :step="0.1" :precision="1" size="small" :controls="false" style="width:56px" />
                  </div>
                </div>
              </el-popover>
              <span class="config-desc">配音音量:{{ globalConfig.voiceVolume || 1 }} 背景音乐:{{ (globalConfig.music.volume / 100).toFixed(1) }} 配音语速:{{ globalConfig.voiceSpeed || 1 }}</span>
            </div>

            <!-- 背景音乐 -->
            <div class="config-row">
              <span class="config-tag">背景音乐</span>
              <span class="config-desc">({{ bgMusicCount }}/100)</span>
              <el-button size="small" class="setting-btn" @click="showMusicDialog = true">添加背景音乐</el-button>
            </div>

            <!-- 批量混剪/分镜剪辑：标题文案 -->
            <div v-if="currentEditorMode !== 'oneClick'" class="config-row">
              <span class="config-tag">标题文案</span>
              <span class="config-desc">({{ titleTextCount }}/50)</span>
              <el-button size="small" class="setting-btn" @click="showTitleDialog = true">批量设置</el-button>
              <el-button size="small" class="setting-btn" @click="addTitleText">添加标题文案</el-button>
            </div>

            <!-- 批量混剪/分镜剪辑：贴纸 -->
            <div v-if="currentEditorMode !== 'oneClick'" class="config-row">
              <span class="config-tag">贴纸</span>
              <span class="config-desc">({{ stickerCount }}/50)</span>
              <el-button size="small" class="setting-btn" @click="showStickerDialog = true">添加贴纸</el-button>
            </div>
          </div>
        </div>

        <!-- 右侧面板 -->
        <div class="preview-right">
          <el-form label-width="100px" size="small" class="preview-right-form">
            <!-- 选择比例 -->
            <el-form-item label="选择比例">
              <div class="video-size">
                <div
                  v-for="ratio in ['9:16', '16:9', '1:1']"
                  :key="ratio"
                  :class="['size-option', { active: globalConfig.aspectRatio === ratio }]"
                  @click="globalConfig.aspectRatio = ratio"
                >
                  <div class="size-icon" :class="'icon-' + ratio.replace(':', 'x')"></div>
                  <span>{{ ratio }}</span>
                </div>
              </div>
            </el-form-item>

            <!-- 批量混剪/分镜剪辑：背景填充 -->
            <el-form-item v-if="currentEditorMode !== 'oneClick'" label="背景填充">
              <div class="bg-fill-row">
                <el-select v-model="bgFillType" size="small" style="width: 100px">
                  <el-option label="纯色" value="solid" />
                  <el-option label="模糊" value="blur" />
                  <el-option label="图片" value="image" />
                </el-select>
                <el-color-picker v-if="bgFillType === 'solid'" v-model="bgFillColor" size="small" />
              </div>
            </el-form-item>

            <!-- 分镜剪辑：视频时长 -->
            <el-form-item v-if="currentEditorMode === 'storyboard'" label="视频时长">
              <el-radio-group v-model="videoDurationMode" size="small">
                <el-radio label="video">按视频时长</el-radio>
                <el-radio label="voice">按配音时长</el-radio>
              </el-radio-group>
            </el-form-item>

            <!-- 帧率 -->
            <el-form-item label="帧率">
              <el-radio-group v-model="globalConfig.frameRate" size="small">
                <el-radio :label="25">25FPS</el-radio>
                <el-radio :label="30">30FPS</el-radio>
                <el-radio :label="60">60FPS</el-radio>
              </el-radio-group>
            </el-form-item>

            <!-- 颜色调整 -->
            <div class="color-section">
              <div class="color-header">
                <span class="config-tag">颜色调整</span>
                <el-popover placement="left" :width="300" trigger="click">
                  <template #reference>
                    <el-button size="small" class="setting-btn">颜色</el-button>
                  </template>
                  <div class="popover-setting">
                    <div class="popover-row">
                      <span class="popover-label">对比度：</span>
                      <el-slider v-model="globalConfig.color.contrast" :min="-100" :max="100" :show-tooltip="false" />
                      <el-input-number v-model="globalConfig.color.contrast" :min="-100" :max="100" size="small" :controls="false" style="width:56px" />
                    </div>
                    <div class="popover-row">
                      <span class="popover-label">饱和度：</span>
                      <el-slider v-model="globalConfig.color.saturation" :min="-100" :max="100" :show-tooltip="false" />
                      <el-input-number v-model="globalConfig.color.saturation" :min="-100" :max="100" size="small" :controls="false" style="width:56px" />
                    </div>
                    <div class="popover-row">
                      <span class="popover-label">亮度：</span>
                      <el-slider v-model="globalConfig.color.brightness" :min="-255" :max="255" :show-tooltip="false" />
                      <el-input-number v-model="globalConfig.color.brightness" :min="-255" :max="255" size="small" :controls="false" style="width:56px" />
                    </div>
                    <div class="popover-row">
                      <span class="popover-label">色度：</span>
                      <el-slider v-model="globalConfig.color.hue" :min="-100" :max="100" :show-tooltip="false" />
                      <el-input-number v-model="globalConfig.color.hue" :min="-100" :max="100" size="small" :controls="false" style="width:56px" />
                    </div>
                  </div>
                </el-popover>
                <span class="color-values">
                  <span>对比度:{{ globalConfig.color.contrast }}</span>
                  <span>饱和度:{{ globalConfig.color.saturation }}</span>
                  <span>亮度:{{ globalConfig.color.brightness }}</span>
                  <span>色度:{{ globalConfig.color.hue }}</span>
                </span>
              </div>
            </div>
          </el-form>
          <el-divider />
        </div>
      </div>
    </div>

    <!-- ==================== 转场/滤镜弹出层 ==================== -->
    <el-dialog v-model="showTFDialog" title="" width="600px" :append-to-body="true" class="tf-dialog" :show-close="true">
      <div class="tf-popover">
        <div class="tf-tabs">
          <div :class="['tf-tab', { active: tfActiveTab === 'transition' }]" @click="tfActiveTab = 'transition'">转场</div>
          <div :class="['tf-tab', { active: tfActiveTab === 'filter' }]" @click="tfActiveTab = 'filter'">滤镜</div>
        </div>
        <template v-if="tfActiveTab === 'transition'">
          <div class="tf-row"><span class="tf-label">随机转场</span><el-switch v-model="randomTransition" active-color="#834EFF" /></div>
          <div class="tf-hint">开启后当前下方所有镜头直接将随机使用</div>
          <div class="tf-row"><span class="tf-label">自选转场 <span class="tf-count">(已选择{{ selectedTransitions.length }})</span></span></div>
          <div class="tf-hint">将随机选用已选择的转场</div>
          <div class="tf-section-title">转场效果预览</div>
          <div class="tf-grid">
            <div v-for="item in transitionList" :key="item.name" :class="['tf-item', { active: selectedTransitions.includes(item.name) }]" @click="toggleTransition(item.name)">
              <div class="tf-item-icon"><img :src="item.img" :alt="item.name" /></div>
              <span>{{ item.name }}</span>
            </div>
          </div>
        </template>
        <template v-else>
          <div class="tf-row"><span class="tf-label">随机滤镜</span><el-switch v-model="randomFilter" active-color="#834EFF" /></div>
          <div class="tf-hint">开启后当前下方所有镜头直接将随机使用</div>
          <div class="tf-row"><span class="tf-label">自选滤镜 <span class="tf-count">(已选择{{ selectedFilters.length }})</span></span></div>
          <div class="tf-hint">将随机选用已选择的滤镜</div>
          <div class="tf-section-title">滤镜效果预览</div>
          <div class="tf-grid">
            <div v-for="item in filterList" :key="item.name" :class="['tf-item', { active: selectedFilters.includes(item.name) }]" @click="toggleFilter(item.name)">
              <div class="tf-item-icon"><img :src="item.img" :alt="item.name" /></div>
              <span>{{ item.name }}</span>
            </div>
          </div>
        </template>
      </div>
    </el-dialog>

    <!-- ==================== 背景音乐弹窗 ==================== -->
    <el-dialog v-model="showMusicDialog" title="选择音乐" width="680px" :append-to-body="true" class="music-dialog">
      <div class="music-dialog-body">
        <div class="music-tabs">
          <div :class="['music-tab', { active: musicTab === 'hot' }]" @click="musicTab = 'hot'">热门音乐</div>
          <div :class="['music-tab', { active: musicTab === 'material' }]" @click="musicTab = 'material'">素材音乐</div>
          <div :class="['music-tab', { active: musicTab === 'fav' }]" @click="musicTab = 'fav'">收藏</div>
        </div>
        <div class="music-search">
          <el-input v-model="musicSearchKey" placeholder="请输入名称" size="small" clearable style="width:200px" />
          <el-button size="small" type="primary" @click="searchMusic">搜索</el-button>
        </div>
        <div class="music-tags" v-if="musicTab === 'hot'">
          <span v-for="tag in musicCategoryTags" :key="tag" :class="['music-tag', { active: musicCategory === tag }]" @click="musicCategory = tag">{{ tag }}</span>
        </div>
        <div class="music-list">
          <div v-if="musicList.length === 0" class="empty-hint" style="padding:40px 0">暂无音乐数据，可从素材库上传音频文件</div>
          <div v-for="(item, idx) in musicList" :key="idx" class="music-item">
            <el-checkbox v-model="item.checked" />
            <span class="music-name">{{ item.name }}</span>
            <span class="music-duration">{{ item.duration }}</span>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="dialog-footer-left">
          <el-checkbox v-model="musicSelectAll" @change="toggleMusicSelectAll">单次最多添加100条</el-checkbox>
        </div>
        <el-button @click="showMusicDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmMusicSelect">确定</el-button>
      </template>
    </el-dialog>

    <!-- ==================== 标题文案弹窗 ==================== -->
    <el-dialog v-model="showTitleDialog" title="批量设置标题文案" width="520px" :append-to-body="true" class="title-dialog">
      <div class="title-dialog-body">
        <div class="title-tip">每行一条文案，最多50条</div>
        <el-input
          v-model="titleTextContent"
          type="textarea"
          :rows="10"
          placeholder="请输入标题文案，每行一条&#10;例如：&#10;好物推荐&#10;限时优惠&#10;品质之选"
          resize="vertical"
        />
      </div>
      <template #footer>
        <el-button @click="showTitleDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmTitleText">确定</el-button>
      </template>
    </el-dialog>

    <!-- ==================== 贴纸弹窗 ==================== -->
    <el-dialog v-model="showStickerDialog" title="添加贴纸" width="520px" :append-to-body="true" class="sticker-dialog">
      <div class="sticker-dialog-body">
        <div class="sticker-tip">可从素材库选择图片作为贴纸，最多50个</div>
        <div class="sticker-list">
          <div v-for="(sticker, idx) in stickerList" :key="idx" class="sticker-item">
            <el-image :src="sticker.url" fit="contain" class="sticker-img" />
            <el-button type="danger" size="small" circle :icon="Delete" @click="removeSticker(idx)" />
          </div>
          <div class="sticker-add" @click="addSticker">
            <el-icon :size="24"><Plus /></el-icon>
            <span>添加贴纸</span>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="showStickerDialog = false">取消</el-button>
        <el-button type="primary" @click="confirmStickers">确定</el-button>
      </template>
    </el-dialog>

    <!-- ==================== 素材选择器弹窗 ==================== -->
    <el-dialog v-model="showMaterialSelector" title="选择素材" width="720px" :append-to-body="true" class="material-selector-dialog" @open="materialSelectorChecked = []; materialSelectorSearch = ''">
      <div class="material-selector-body">
        <div class="material-selector-search">
          <el-input v-model="materialSelectorSearch" placeholder="搜索素材" prefix-icon="Search" clearable size="default" />
        </div>
        <el-tabs v-model="materialSelectorTab">
          <el-tab-pane label="视频" name="video">
            <div class="material-grid">
              <div
                v-for="item in filteredSelectorMaterials('video')"
                :key="item.id"
                class="material-grid-item"
                :class="{ checked: materialSelectorChecked.includes(item.id) }"
                @click="toggleMaterialCheck(item.id)"
              >
                <div class="material-thumb">
                  <el-image :src="item.thumbnail || item.cover || item.url" fit="cover" />
                  <div v-if="item.type === 'video'" class="material-play-icon"><el-icon :size="24"><VideoPlay /></el-icon></div>
                  <div v-if="materialSelectorChecked.includes(item.id)" class="material-check-icon"><el-icon :size="20"><CircleCheckFilled /></el-icon></div>
                </div>
                <div class="material-name">{{ item.name || item.title || '未命名' }}</div>
              </div>
              <div v-if="filteredSelectorMaterials('video').length === 0" class="material-empty">暂无视频素材</div>
            </div>
          </el-tab-pane>
          <el-tab-pane label="图片" name="image">
            <div class="material-grid">
              <div
                v-for="item in filteredSelectorMaterials('image')"
                :key="item.id"
                class="material-grid-item"
                :class="{ checked: materialSelectorChecked.includes(item.id) }"
                @click="toggleMaterialCheck(item.id)"
              >
                <div class="material-thumb">
                  <el-image :src="item.thumbnail || item.cover || item.url" fit="cover" />
                  <div v-if="materialSelectorChecked.includes(item.id)" class="material-check-icon"><el-icon :size="20"><CircleCheckFilled /></el-icon></div>
                </div>
                <div class="material-name">{{ item.name || item.title || '未命名' }}</div>
              </div>
              <div v-if="filteredSelectorMaterials('image').length === 0" class="material-empty">暂无图片素材</div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>
      <template #footer>
        <span class="dialog-footer-left">已选 {{ materialSelectorChecked.length }} 项</span>
        <el-button @click="showMaterialSelector = false">取消</el-button>
        <el-button type="primary" @click="confirmMaterialSelect" :disabled="materialSelectorChecked.length === 0">确认 {{ materialSelectorChecked.length }}</el-button>
      </template>
    </el-dialog>

    <!-- ==================== 三种剪辑说明弹出层 ==================== -->
    <div v-if="showModeDialog && currentView === 'select'" class="clip-one-dialog-mask">
      <div class="clip-one-dialog">
        <div class="dialog-header">
          <div class="dialog-header-top">
            <span class="dialog-title">三种剪辑说明</span>
          </div>
          <div class="dialog-tabs">
            <span
              v-for="(tab, idx) in dialogTabs"
              :key="idx"
              :class="['dialog-tab', { active: activeDialogTab === idx }]"
              @click="activeDialogTab = idx"
            >{{ tab.name }}</span>
          </div>
        </div>
        <div class="dialog-body">
          <div
            v-for="(card, idx) in dialogCards"
            :key="idx"
            class="dialog-card"
          >
            <div class="card-name">{{ card.name }}</div>
            <div class="card-header-row">
              <span class="card-star">★</span>
              <span class="card-tagline">{{ card.tagline }}</span>
            </div>
            <div class="card-desc">{{ card.desc }}</div>
            <div class="card-section">
              <div class="card-section-title">适合谁用</div>
              <div class="card-section-content">
                <div v-for="(t, ti) in card.target" :key="ti" class="card-target-item">{{ t }}</div>
              </div>
            </div>
            <div class="card-section">
              <div class="card-section-title">如何操作</div>
              <div class="card-section-content">
                <div v-for="(s, si) in card.steps" :key="si" class="card-step-item">{{ si + 1 }}、{{ s }}</div>
              </div>
            </div>
          </div>
        </div>
        <div class="dialog-footer">
          <button class="dialog-close-btn" @click="showModeDialog = false">关闭</button>
        </div>
        <div class="container-close" @click="showModeDialog = false">×</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, h } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  ArrowLeft, MagicStick, Film, VideoCamera, Check, Plus, Delete,
  Picture, Right, VideoPlay, Shop, Goods, Reading, Brush, Football, Setting, CircleCheckFilled
} from '@element-plus/icons-vue'
import { useRouter } from 'vue-router'
import {
  getClipProjectList, createClipProject, updateClipProject,
  getClipProjectDetail,
  saveAsTemplate as saveAsTemplateApi,
  getMyTemplates, exportClipProject as exportClipProjectApi,
  getMaterialList, getClipShots,
  generateAutoShots, batchRemix
} from '@/api/index'
import { getStoreList, getTemplateList } from '@/api/video'
import { normalizeListPayload } from '@/utils/responseHelper'

const router = useRouter()

// ---- 全局状态 ----
const currentView = ref('select')
const showModeDialog = ref(true)
const activeDialogTab = ref(0)
const saving = ref(false)
const generating = ref(false)
const currentEditorMode = ref('storyboard')
const subtitleMode = ref('none')
const showVolumeSetting = ref(false)
const showMusicSetting = ref(false)
const showSpeedSetting = ref(false)
const showTitleSetting = ref(false)
const showDubbingSetting = ref(false)
const showMusicDialog = ref(false)
const showTitleDialog = ref(false)
const showStickerDialog = ref(false)
const showMaterialSelector = ref(false)
const materialSelectorTab = ref('video')
const materialSelectorSearch = ref('')
const materialSelectorChecked = ref([])
const musicTab = ref('hot')
const musicSearchKey = ref('')
const musicCategory = ref('推荐')
const musicCategoryTags = ['推荐', '热门榜', '卡点', '纯音乐', '旅行', 'DJ', '搞笑', '流行', '伤感', '励志', '欧美', '国风', '说唱', '民谣']
const musicSelectAll = ref(false)
const titleTextContent = ref('')

// 批量混剪专属配置
const batchMuteOriginal = ref(true)
const batchExtractMin = ref(1)
const batchExtractMax = ref(1)
const batchClipDurationMin = ref(3)
const batchClipDurationMax = ref(5)
const speedValue = ref(1.0)

// 分镜剪辑专属配置
const customShotDuration = ref(false)
const videoDurationMode = ref('voice')

// 背景填充
const bgFillType = ref('solid')
const bgFillColor = ref('#000000')

// 转场/滤镜
const tfActiveTab = ref('transition')
const randomTransition = ref(false)
const randomFilter = ref(false)
const selectedTransitions = ref([])
const selectedFilters = ref([])
const showTransitionPopover = ref(false)
const showTFDialog = ref(false)

const CDN_TRANSITION = 'https://ice-pub-media.myalicdn.com/transition-video/webp/'
const transitionList = [
  { name: '对角切换', img: CDN_TRANSITION + '1.webp' },
  { name: '旋涡', img: CDN_TRANSITION + '2.webp' },
  { name: '栅格', img: CDN_TRANSITION + '3.webp' },
  { name: '垂直领结', img: CDN_TRANSITION + '4.webp' },
  { name: '水平领结', img: CDN_TRANSITION + '5.webp' },
  { name: '放大消失', img: CDN_TRANSITION + '6.webp' },
  { name: '线性模糊', img: CDN_TRANSITION + '7.webp' },
  { name: '水滴', img: CDN_TRANSITION + '8.webp' },
  { name: '故障', img: CDN_TRANSITION + '9.webp' },
  { name: '波点', img: CDN_TRANSITION + '10.webp' },
  { name: '蔓延', img: CDN_TRANSITION + '11.webp' },
  { name: '扭曲旋转', img: CDN_TRANSITION + '12.webp' },
  { name: '向上弹动', img: CDN_TRANSITION + '13.webp' },
  { name: '向下弹动', img: CDN_TRANSITION + '14.webp' },
  { name: '向右擦除', img: CDN_TRANSITION + '15.webp' },
  { name: '向左擦除', img: CDN_TRANSITION + '16.webp' },
  { name: '向下擦除', img: CDN_TRANSITION + '17.webp' },
  { name: '向上擦除', img: CDN_TRANSITION + '18.webp' },
  { name: '雪花消除', img: CDN_TRANSITION + '19.webp' },
  { name: '色彩溶解', img: CDN_TRANSITION + '20.webp' },
  { name: '轻微摇摆', img: CDN_TRANSITION + '23.webp' },
  { name: '圆形放大', img: CDN_TRANSITION + '25.webp' },
  { name: '圆形扫描', img: CDN_TRANSITION + '26.webp' },
  { name: '相册', img: CDN_TRANSITION + '27.webp' },
  { name: '波形放大', img: CDN_TRANSITION + '28.webp' },
  { name: '线性溶解', img: CDN_TRANSITION + '29.webp' },
  { name: '太空波纹', img: CDN_TRANSITION + '30.webp' },
  { name: '万花筒', img: CDN_TRANSITION + '31.webp' },
  { name: '百叶窗', img: CDN_TRANSITION + '32.webp' },
  { name: '蜂巢溶解', img: CDN_TRANSITION + '33.webp' },
  { name: '炫境', img: CDN_TRANSITION + '35.webp' },
  { name: '齿状上升', img: CDN_TRANSITION + '36.webp' },
  { name: '齿状下落', img: CDN_TRANSITION + '37.webp' },
  { name: '波纹', img: CDN_TRANSITION + '38.webp' },
  { name: '风车', img: CDN_TRANSITION + '39.webp' },
  { name: '时钟旋转', img: CDN_TRANSITION + '40.webp' },
  { name: '燃烧', img: CDN_TRANSITION + '41.webp' },
  { name: '椭圆遮罩', img: CDN_TRANSITION + '42.webp' },
  { name: '椭圆溶解', img: CDN_TRANSITION + '43.webp' },
  { name: '色相溶解', img: CDN_TRANSITION + '44.webp' },
  { name: '隧道扭曲', img: CDN_TRANSITION + '45.webp' },
  { name: '立方体', img: CDN_TRANSITION + '46.webp' },
  { name: '渐变擦除', img: CDN_TRANSITION + '47.webp' },
  { name: '开幕', img: CDN_TRANSITION + '48.webp' },
  { name: '渐隐', img: CDN_TRANSITION + '49.webp' },
  { name: '彩色渐隐', img: CDN_TRANSITION + '50.webp' },
  { name: '灰色渐隐', img: CDN_TRANSITION + '51.webp' },
  { name: '回忆', img: CDN_TRANSITION + '52.webp' },
  { name: '爱心遮罩', img: CDN_TRANSITION + '53.webp' },
  { name: '对角开幕', img: CDN_TRANSITION + '54.webp' },
  { name: '多层混合', img: CDN_TRANSITION + '55.webp' },
  { name: '像素溶解', img: CDN_TRANSITION + '56.webp' },
  { name: '花瓣遮罩', img: CDN_TRANSITION + '57.webp' },
  { name: '随机方块', img: CDN_TRANSITION + '58.webp' },
  { name: '旋转', img: CDN_TRANSITION + '59.webp' },
  { name: '方块替换', img: CDN_TRANSITION + '60.webp' },
  { name: '向内推入', img: CDN_TRANSITION + '61.webp' },
  { name: '切入', img: CDN_TRANSITION + '62.webp' },
  { name: '线形擦除', img: CDN_TRANSITION + '63.webp' },
]

const filterList = [
  { name: '花雾', img: 'https://img.alicdn.com/imgextra/i2/O1CN0165Hs0d1bNymkYBHvh_!!6000000003454-49-tps-400-225.webp' },
  { name: '午后', img: 'https://img.alicdn.com/imgextra/i2/O1CN01DBBxoV275ihKmEtz5_!!6000000007746-49-tps-400-225.webp' },
  { name: '童年', img: 'https://img.alicdn.com/imgextra/i4/O1CN01e9aKmt1kuLJQnt4uA_!!6000000004743-49-tps-400-225.webp' },
  { name: '小森林', img: 'https://img.alicdn.com/imgextra/i2/O1CN01XIJDtq1bansDUyRqs_!!6000000003482-49-tps-400-225.webp' },
  { name: '哑光', img: 'https://img.alicdn.com/imgextra/i3/O1CN01GIjWVE1gGGBiO5LaH_!!6000000004114-49-tps-400-225.webp' },
  { name: '艳丽', img: 'https://img.alicdn.com/imgextra/i4/O1CN01nXb2Qh1Mx2rusa2bB_!!6000000001500-49-tps-400-225.webp' },
  { name: '蓝光', img: 'https://img.alicdn.com/imgextra/i1/O1CN01FelHjg1qQTAkMjY6Z_!!6000000005490-49-tps-400-225.webp' },
  { name: '青空', img: 'https://img.alicdn.com/imgextra/i3/O1CN01n2CAWG1OVgRVPBczK_!!6000000001711-49-tps-400-225.webp' },
  { name: '暗调', img: 'https://img.alicdn.com/imgextra/i2/O1CN01EdapqC1FwP0HDPGbK_!!6000000000551-49-tps-400-225.webp' },
  { name: '柔和', img: 'https://img.alicdn.com/imgextra/i2/O1CN01vvUJuY1mll7Ys0Em0_!!6000000004995-49-tps-400-225.webp' },
  { name: '高调', img: 'https://img.alicdn.com/imgextra/i4/O1CN01eOJCa31tmrNwNWwGW_!!6000000005945-49-tps-400-225.webp' },
  { name: '雾气', img: 'https://img.alicdn.com/imgextra/i3/O1CN016oSQ6F1WuQlaXAV4H_!!6000000002848-49-tps-400-225.webp' },
  { name: '清新', img: 'https://img.alicdn.com/imgextra/i4/O1CN01FngAG627AIeyowpC1_!!6000000007756-49-tps-400-225.webp' },
  { name: '天色', img: 'https://img.alicdn.com/imgextra/i1/O1CN01RLovbi28YrW8vV2kz_!!6000000007945-49-tps-400-225.webp' },
  { name: '质感', img: 'https://img.alicdn.com/imgextra/i2/O1CN01aMl1cd1RgckVwmYa6_!!6000000002141-49-tps-400-225.webp' },
  { name: '咖啡', img: 'https://img.alicdn.com/imgextra/i3/O1CN01cGQGis1LgGkNyFyX2_!!6000000001328-49-tps-400-225.webp' },
  { name: '老街', img: 'https://img.alicdn.com/imgextra/i2/O1CN01Ye2biS1F4ofNLGWeg_!!6000000000434-49-tps-400-225.webp' },
  { name: '尤加利', img: 'https://img.alicdn.com/imgextra/i3/O1CN01TcGGTx1Oi38NTighP_!!6000000001738-49-tps-400-225.webp' },
  { name: '蓝霜', img: 'https://img.alicdn.com/imgextra/i1/O1CN018294py1wlR04aFtE3_!!6000000006348-49-tps-400-225.webp' },
  { name: '布达佩斯', img: 'https://img.alicdn.com/imgextra/i3/O1CN01BMEZUT1gnEhivquBo_!!6000000004186-49-tps-400-225.webp' },
  { name: '雪山', img: 'https://img.alicdn.com/imgextra/i2/O1CN01wHid1a1pVDfSzsKiy_!!6000000005365-49-tps-400-225.webp' },
  { name: '济州岛', img: 'https://img.alicdn.com/imgextra/i1/O1CN01cShYxY23Edx5awop8_!!6000000007224-49-tps-400-225.webp' },
  { name: '温暖', img: 'https://img.alicdn.com/imgextra/i4/O1CN01AeL90L1LzxmS2WfPA_!!6000000001371-49-tps-400-225.webp' },
  { name: '雨后', img: 'https://img.alicdn.com/imgextra/i1/O1CN01l5uCVn23IlW43VOYg_!!6000000007233-49-tps-400-225.webp' },
  { name: '东京', img: 'https://img.alicdn.com/imgextra/i1/O1CN01rXsa8V1L8NRNUB3Bo_!!6000000001254-49-tps-400-225.webp' },
  { name: '蓝雾', img: 'https://img.alicdn.com/imgextra/i3/O1CN01Q1BFsf28sYXzRvabs_!!6000000007988-49-tps-400-225.webp' },
  { name: '盐系', img: 'https://img.alicdn.com/imgextra/i2/O1CN0176MwNf1kZjUPFMNHr_!!6000000004698-49-tps-400-225.webp' },
  { name: '林间', img: 'https://img.alicdn.com/imgextra/i4/O1CN01FfMoTx1jfOlrBZK5B_!!6000000004575-49-tps-400-225.webp' },
  { name: '白桃', img: 'https://img.alicdn.com/imgextra/i4/O1CN01eutHQg1fZfM2vLJjA_!!6000000004021-49-tps-400-225.webp' },
  { name: '明媚', img: 'https://img.alicdn.com/imgextra/i3/O1CN01CFLOKb1aWORXBlhcV_!!6000000003337-49-tps-400-225.webp' },
  { name: '春芽', img: 'https://img.alicdn.com/imgextra/i1/O1CN01YfSnBB1nV6KduRA41_!!6000000005094-49-tps-400-225.webp' },
  { name: '柔和2', img: 'https://img.alicdn.com/imgextra/i2/O1CN01kGXBH821eAncVVrVq_!!6000000007009-49-tps-400-225.webp' },
  { name: '影调', img: 'https://img.alicdn.com/imgextra/i1/O1CN01C7W3d91Miqaw0YZFw_!!6000000001469-49-tps-400-225.webp' },
  { name: '秋色', img: 'https://img.alicdn.com/imgextra/i2/O1CN01meOhOa28dtsCBtJTZ_!!6000000007956-49-tps-400-225.webp' },
  { name: '暮晚', img: 'https://img.alicdn.com/imgextra/i2/O1CN01X3G0rW1xpO29FYx5g_!!6000000006492-49-tps-400-225.webp' },
  { name: '清透', img: 'https://img.alicdn.com/imgextra/i3/O1CN017FTV951ym0jBkTPa5_!!6000000006620-49-tps-400-225.webp' },
  { name: '工业', img: 'https://img.alicdn.com/imgextra/i2/O1CN01dVvzIS28CQ6tYqmYB_!!6000000007896-49-tps-400-225.webp' },
  { name: '禄来', img: 'https://img.alicdn.com/imgextra/i1/O1CN01jAMOVm1zSbYzT83u4_!!6000000006713-49-tps-400-225.webp' },
  { name: '宝丽来', img: 'https://img.alicdn.com/imgextra/i3/O1CN01ZMB5YR1VESrQpiAgj_!!6000000002621-49-tps-400-225.webp' },
  { name: '红外', img: 'https://img.alicdn.com/imgextra/i1/O1CN013c1I7q1auD2SkoJLF_!!6000000003389-49-tps-400-225.webp' },
  { name: '反转', img: 'https://img.alicdn.com/imgextra/i1/O1CN01SlI6rY20oQ2g9NApi_!!6000000006896-49-tps-400-225.webp' },
  { name: '复古', img: 'https://img.alicdn.com/imgextra/i1/O1CN01PkltDm23jKUAItdOt_!!6000000007291-49-tps-400-225.webp' },
  { name: '柯达', img: 'https://img.alicdn.com/imgextra/i3/O1CN01bF4ZUr1uQFRcaN1cS_!!6000000006031-49-tps-400-225.webp' },
  { name: '暖色', img: 'https://img.alicdn.com/imgextra/i2/O1CN01pYZzN81Rx70XuGxqL_!!6000000002177-49-tps-400-225.webp' },
  { name: '富士', img: 'https://img.alicdn.com/imgextra/i1/O1CN01wEHpjX1Cnkg8McCcG_!!6000000000126-49-tps-400-225.webp' },
  { name: '高调2', img: 'https://img.alicdn.com/imgextra/i4/O1CN01ac7m0b1fTFo6g4PPL_!!6000000004007-49-tps-400-225.webp' },
  { name: '通透', img: 'https://img.alicdn.com/imgextra/i1/O1CN01n7K1Ru22mFOQn7VCY_!!6000000007162-49-tps-400-225.webp' },
  { name: '灰橙', img: 'https://img.alicdn.com/imgextra/i2/O1CN01VKSe5s1u5BECDP91y_!!6000000005985-49-tps-400-225.webp' },
  { name: '暗淡', img: 'https://img.alicdn.com/imgextra/i3/O1CN01tNfGkM1bVJ7YTBn6y_!!6000000003470-49-tps-400-225.webp' },
  { name: '暗红', img: 'https://img.alicdn.com/imgextra/i4/O1CN01p9NRU51XvdQPfzh6p_!!6000000002986-49-tps-400-225.webp' },
  { name: '蓝调', img: 'https://img.alicdn.com/imgextra/i4/O1CN01Q4TUfZ1LPmUPCa9r5_!!6000000001292-49-tps-400-225.webp' },
  { name: '青阶', img: 'https://img.alicdn.com/imgextra/i4/O1CN01RkXXpu1V9QVJaXxY3_!!6000000002610-49-tps-400-225.webp' },
  { name: '灰调', img: 'https://img.alicdn.com/imgextra/i4/O1CN01BVrOQJ1UxybGG5Asu_!!6000000002585-49-tps-400-225.webp' },
  { name: '复古2', img: 'https://img.alicdn.com/imgextra/i1/O1CN01zUNn0V1d3UILeAj09_!!6000000003680-49-tps-400-225.webp' },
]

const toggleTransition = (name) => {
  const idx = selectedTransitions.value.indexOf(name)
  if (idx > -1) selectedTransitions.value.splice(idx, 1)
  else selectedTransitions.value.push(name)
}

const toggleFilter = (name) => {
  const idx = selectedFilters.value.indexOf(name)
  if (idx > -1) selectedFilters.value.splice(idx, 1)
  else selectedFilters.value.push(name)
}

// 计数变量（由各模块的 computed 提供）

// 弹出层数据
const dialogTabs = [
  { name: '一键成片' },
  { name: '批量混剪' },
  { name: '分镜剪辑' }
]
const dialogCards = [
  {
    name: '一键成片',
    tagline: '小白闭眼冲！',
    desc: '传素材输关键词，一键批量出视频，门店宣传超省心',
    target: ['新手 / 0剪辑基础', '想操作简单 / 快速发布/ 轻量化交付', '商家宣传跑量'],
    steps: ['上传视频素材', '输入产品关键词 ，AI生成文案', '一键批量生成多条成片']
  },
  {
    name: '批量混剪',
    tagline: '剪辑新手套装！',
    desc: '批量出片还能随心定制，成片好看又可控',
    target: ['不满足一键成片效果，想要视频元素', '更丰富，需要批量+提升质量', '素材多且排版需求不高'],
    steps: ['上传视频素材', '自定义调整文案、字体、贴纸、背景音乐、配音等细节', '可视化剪辑把控质量', '一键批量生成多条高质量成片']
  },
  {
    name: '分镜剪辑',
    tagline: '专业级出片！',
    desc: '分镜头有序组合，口播精准贴合，种草探店质感直接拉满',
    target: ['有网感基础想做精细化种草/探店/真人', '出镜视频,追求高质量宣传效果'],
    steps: ['素材分类上传至 3-15 个独立镜头库', '可根据单镜头调整时长、文案、字体、贴纸、画中画等使内容更贴合', '视频按镜头组排列有序抽帧组合', '一键批量生成多条超优质成片']
  }
]

const editorModeTabs = [
  { name: '一键成片', key: 'oneClick' },
  { name: '批量混剪', key: 'batch' },
  { name: '分镜剪辑', key: 'storyboard' }
]

const switchEditorMode = async (mode) => {
  if (mode === currentEditorMode.value) return
  if (currentView.value !== 'select') {
    try {
      await ElMessageBox.confirm(
        '切换当前页面，系统可能不会保存您所上传的素材配置，是否切换',
        '提示',
        { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
      )
    } catch {
      return
    }
  }
  currentEditorMode.value = mode
  // 模式专属的默认值
  if (mode === 'storyboard') {
    if (subtitleMode.value === 'none') subtitleMode.value = 'storyboard'
  } else {
    if (subtitleMode.value === 'storyboard') subtitleMode.value = 'none'
  }
}
const storeList = ref([])
const materialList = ref([])
const templateList = ref([])
const myTemplates = ref([])
const recentProjects = ref([])
const currentProjectId = ref(null)

// 转场选项
const transitionOptions = [
  { label: '无', value: 'none' },
  { label: '淡入淡出', value: 'fade' },
  { label: '滑动', value: 'slide' },
  { label: '缩放', value: 'zoom' },
  { label: '擦除', value: 'wipe' },
  { label: '随机', value: 'random' }
]

// ---- 工具方法 ----
const getModeName = (mode) => {
  const map = { oneClick: '一键成片', batch: '批量混剪', storyboard: '分镜剪辑' }
  return map[getProjectMode({ mode })] || mode
}

const normalizeMode = (mode) => mode === 'oneClick' ? 'auto' : mode
const getProjectMode = (row) => row.mode === 'auto' ? 'oneClick' : (row.mode || 'storyboard')
const getProjectId = (res) => {
  return res?.id || res?.project_id || null
}
const mapShotToBackend = (shot, index) => ({
  sort_order: index + 1,
  material_id: shot.materialId || null,
  material_type: shot.materialType || 'image',
  thumbnail_url: shot.thumbnail || null,
  duration: shot.duration || 3,
  subtitle: shot.subtitle || null,
  voice_text: shot.voiceText || null,
  transition_type: shot.transition || 'none',
  filter_name: shot.filter || null,
  mute_original: shot.muteOriginal ? 1 : 0,
})

const mapShotFromBackend = (shot) => ({
  id: shot.id,
  serverId: shot.id,
  name: shot.subtitle || `镜头 ${shot.sort_order}`,
  thumbnail: shot.thumbnail_url || '',
  materialId: shot.material_id || null,
  materialType: shot.material_type || 'image',
  duration: shot.duration || 3,
  muteOriginal: !!shot.mute_original,
  transition: shot.transition_type || 'none',
  filter: shot.filter_name || '',
  subtitle: shot.subtitle || '',
  voiceText: shot.voice_text || '',
  sortOrder: shot.sort_order || 0,
})

const buildProjectPayload = () => {
  const config = {
    globalConfig: {
      subtitle: { ...globalConfig.subtitle },
      voice: { ...globalConfig.voice },
      music: { ...globalConfig.music },
      title: globalConfig.title,
      aspectRatio: globalConfig.aspectRatio,
      frameRate: globalConfig.frameRate,
      color: { ...globalConfig.color },
    }
  }

  if (currentView.value === 'oneClick') {
    config.oneClick = { ...oneClickForm }
  } else if (currentView.value === 'batch') {
    config.batch = { ...batchForm }
  }

  const payload = {
    id: currentProjectId.value || undefined,
    name: oneClickForm.title || globalConfig.title || '未命名工程',
    mode: normalizeMode(currentEditorMode.value),
    config,
    shots: shots.value.map(mapShotToBackend),
  }

  return payload
}

// ---- 模式选择 ----
const enterMode = (mode) => {
  currentView.value = 'editor'
  currentEditorMode.value = mode
  currentProjectId.value = null
  selectedShotId.value = null
  shots.value = []
  // 模式专属默认值
  if (mode === 'storyboard') {
    subtitleMode.value = 'storyboard'
  } else {
    subtitleMode.value = 'none'
  }
}
const backToSelect = () => {
  currentView.value = 'select'
}

// ---- 一键成片 ----
const ocStep = ref(0)
const oneClickForm = reactive({
  storeId: '',
  materialIds: [],
  templateId: '',
  title: '',
  platforms: ['douyin'],
  industry: '',
  promoText: ''
})

const ocAiLoading = ref(false)
const ocAiShotsLoading = ref(false)
const ocAutoShots = ref([])

const industryList = [
  { value: 'catering', label: '餐饮', icon: 'Shop' },
  { value: 'retail', label: '零售', icon: 'Goods' },
  { value: 'education', label: '教育', icon: 'Reading' },
  { value: 'beauty', label: '美业', icon: 'Brush' },
  { value: 'fitness', label: '健身', icon: 'Football' },
  { value: 'other', label: '其他', icon: 'Film' }
]

const getIndustryLabel = (val) => {
  const item = industryList.find(i => i.value === val)
  return item ? item.label : '未选择'
}

const getPlatformName = (p) => {
  const map = { douyin: '抖音', kuaishou: '快手', xiaohongshu: '小红书', weixin: '视频号' }
  return map[p] || p
}

const getTransitionLabel = (val) => {
  const item = transitionOptions.find(t => t.value === val)
  return item ? item.label : '无'
}

const getMusicLabel = (val) => {
  const map = { '': '无', light: '轻快节奏', dynamic: '动感节拍', warm: '温馨治愈', tense: '悬疑紧张' }
  return map[val] || '无'
}

const handleAiGenerateText = async () => {
  if (!oneClickForm.industry) { ElMessage.warning('请先选择行业类别'); return }
  ocAiLoading.value = true
  try {
    const res = await generateAutoShots({
      industry: oneClickForm.industry,
      mode: 'generate_text'
    })
    if (res && res.text) {
      oneClickForm.promoText = res.text
      ElMessage.success('文案生成成功')
    } else {
      ElMessage.success('文案生成成功')
    }
  } catch (e) {
    ElMessage.error('AI文案生成失败，请稍后重试')
  } finally {
    ocAiLoading.value = false
  }
}

const handleGenerateAutoShots = async () => {
  if (oneClickForm.materialIds.length === 0) { ElMessage.warning('请先选择素材'); return }
  ocAiShotsLoading.value = true
  try {
    const res = await generateAutoShots({
      industry: oneClickForm.industry,
      promo_text: oneClickForm.promoText,
      material_ids: oneClickForm.materialIds
    })
    const shotList = res?.shots || res?.data || []
    if (Array.isArray(shotList) && shotList.length > 0) {
      ocAutoShots.value = shotList.map((s, i) => ({
        id: i + 1,
        thumbnail: s.thumbnail_url || s.thumbnail || '',
        duration: s.duration || 3,
        subtitle: s.subtitle || '',
        materialId: s.material_id || null
      }))
      ElMessage.success(`已生成 ${ocAutoShots.value.length} 个镜头`)
    } else {
      ocAutoShots.value = oneClickForm.materialIds.slice(0, 6).map((mid, i) => {
        const mat = materialList.value.find(m => m.id === mid)
        return {
          id: i + 1,
          thumbnail: mat?.thumbnail || '',
          duration: 3,
          subtitle: `镜头 ${i + 1}`,
          materialId: mid
        }
      })
      ElMessage.success(`已生成 ${ocAutoShots.value.length} 个默认镜头`)
    }
  } catch (e) {
    ocAutoShots.value = oneClickForm.materialIds.slice(0, 6).map((mid, i) => {
      const mat = materialList.value.find(m => m.id === mid)
      return {
        id: i + 1,
        thumbnail: mat?.thumbnail || '',
        duration: 3,
        subtitle: `镜头 ${i + 1}`,
        materialId: mid
      }
    })
    ElMessage.warning('AI生成失败，已生成默认分镜')
  } finally {
    ocAiShotsLoading.value = false
  }
}

const handleOcStoreChange = async () => {
  oneClickForm.materialIds = []
  await loadMaterials(oneClickForm.storeId)
}

const toggleOcMaterial = (id) => {
  const idx = oneClickForm.materialIds.indexOf(id)
  if (idx > -1) oneClickForm.materialIds.splice(idx, 1)
  else oneClickForm.materialIds.push(id)
}

const nextOcStep = async () => {
  if (ocStep.value === 0) {
    if (!oneClickForm.industry) { ElMessage.warning('请选择行业类别'); return }
  }
  if (ocStep.value === 2) {
    if (oneClickForm.materialIds.length === 0) { ElMessage.warning('请选择至少一个素材'); return }
    await handleGenerateAutoShots()
  }
  ocStep.value++
}

const handleOneClickGenerate = async () => {
  if (!oneClickForm.title) { ElMessage.warning('请输入视频标题'); return }
  if (ocAutoShots.value.length === 0) { ElMessage.warning('请先生成分镜'); return }
  generating.value = true
  try {
    shots.value = ocAutoShots.value.map(s => ({
      id: shotIdCounter++,
      name: s.subtitle || `镜头`,
      thumbnail: s.thumbnail,
      materialId: s.materialId,
      materialType: 'image',
      duration: s.duration,
      muteOriginal: false,
      transition: 'none',
      filter: '',
      subtitle: s.subtitle || '',
      voiceText: '',
    }))
    const projectId = await ensureSavedProject()
    if (!projectId) return
    await exportClipProjectApi(projectId)
    ElMessage.success('已提交生成任务，正在渲染中...')
    await pollExportStatus(projectId)
  } catch (e) {
    ElMessage.error('生成失败')
  } finally {
    generating.value = false
  }
}

// ---- 批量混剪 ----
const batchStep = ref(0)
const batchForm = reactive({
  storeId: '',
  materialIds: [],
  clipDuration: 6,
  transition: 'fade',
  bgMusic: '',
  count: 5,
  shotsPerVideo: 4,
  enableVoice: false,
  voiceText: '',
  enableSubtitle: true
})
const batchResults = ref([])
const batchProgress = reactive({
  running: false,
  percent: 0,
  current: 0,
  total: 0,
  status: ''
})

const handleBatchStoreChange = async () => {
  batchForm.materialIds = []
  await loadMaterials(batchForm.storeId)
}

const toggleBatchMaterial = (id) => {
  const idx = batchForm.materialIds.indexOf(id)
  if (idx > -1) batchForm.materialIds.splice(idx, 1)
  else batchForm.materialIds.push(id)
}

const nextBatchStep = () => {
  if (batchStep.value === 0) {
    if (batchForm.materialIds.length < 2) { ElMessage.warning('请至少选择2个素材'); return }
  }
  batchStep.value++
}

const handleBatchGenerate = async () => {
  if (batchForm.materialIds.length < 2) { ElMessage.warning('请至少选择2个素材'); return }
  generating.value = true
  batchProgress.running = true
  batchProgress.percent = 0
  batchProgress.current = 0
  batchProgress.total = batchForm.count
  batchProgress.status = ''

  try {
    const payload = {
      mode: 'batch',
      material_ids: batchForm.materialIds,
      count: batchForm.count,
      clip_duration: batchForm.clipDuration,
      shots_per_video: batchForm.shotsPerVideo,
      transition: batchForm.transition,
      bg_music: batchForm.bgMusic,
      enable_voice: batchForm.enableVoice,
      voice_text: batchForm.voiceText,
      enable_subtitle: batchForm.enableSubtitle,
    }

    const res = await batchRemix(payload)
    const results = res?.videos || res?.data || []

    for (let i = 0; i < batchForm.count; i++) {
      batchProgress.current = i + 1
      batchProgress.percent = Math.round(((i + 1) / batchForm.count) * 100)
      await new Promise(r => setTimeout(r, 800 + Math.random() * 1200))
    }

    batchResults.value = results.length > 0
      ? results.map((v, i) => ({ ...v, index: i + 1 }))
      : Array.from({ length: batchForm.count }, (_, i) => ({
          index: i + 1,
          thumbnail: '',
          videoUrl: '',
        }))

    batchProgress.status = 'success'
    ElMessage.success('批量混剪完成')
    setTimeout(() => {
      batchStep.value = 3
    }, 500)
  } catch (e) {
    batchProgress.status = 'exception'
    ElMessage.error('批量生成失败')
  } finally {
    generating.value = false
    batchProgress.running = false
  }
}

const downloadVideo = (item) => {
  if (item.videoUrl) {
    window.open(item.videoUrl, '_blank')
  } else {
    ElMessage.info('视频尚在处理中，请稍后下载')
  }
}

// ---- 分镜剪辑 ----
const shots = ref([])
const selectedShotId = ref(null)
const materialSearch = ref('')
const materialTab = ref('image')
let shotIdCounter = 1

const dragOverIdx = ref(null)
let dragSourceIdx = null

const currentShot = computed(() => {
  if (!selectedShotId.value) return null
  return shots.value.find(s => s.id === selectedShotId.value) || null
})

const currentShotIndex = computed(() => {
  if (!selectedShotId.value) return -1
  return shots.value.findIndex(s => s.id === selectedShotId.value)
})

const globalConfig = reactive({
  subtitle: { show: true, text: '', fontSize: 24, color: '#ffffff', applyAll: false },
  voice: { actor: 'female1' },
  music: { src: '', volume: 50 },
  title: '',
  aspectRatio: '9:16',
  frameRate: 30,
  durationMin: 10,
  durationMax: 15,
  voiceVolume: 1,
  voiceSpeed: 1,
  color: { contrast: 0, saturation: 0, brightness: 0, hue: 0 }
})

const filteredMaterials = (type) => {
  const typeLower = type.toLowerCase()
  let list = materialList.value.filter(m => (m.type || m.materialType || '').toLowerCase() === typeLower)
  if (materialSearch.value) {
    const kw = materialSearch.value.toLowerCase()
    list = list.filter(m => m.name.toLowerCase().includes(kw))
  }
  return list
}

const filteredSelectorMaterials = (type) => {
  const typeLower = type.toLowerCase()
  let list = materialList.value.filter(m => {
    const mType = (m.type || m.materialType || '').toLowerCase()
    return mType === typeLower
  })
  if (materialSelectorSearch.value) {
    const kw = materialSelectorSearch.value.toLowerCase()
    list = list.filter(m => (m.name || m.title || '').toLowerCase().includes(kw))
  }
  return list
}

const toggleMaterialCheck = (id) => {
  const idx = materialSelectorChecked.value.indexOf(id)
  if (idx >= 0) {
    materialSelectorChecked.value.splice(idx, 1)
  } else {
    materialSelectorChecked.value.push(id)
  }
}

const confirmMaterialSelect = () => {
  const selected = materialSelectorChecked.value
  if (selected.length === 0) return
  selected.forEach(id => {
    const mat = materialList.value.find(m => m.id === Number(id))
    if (mat) {
      addShotFromMaterial(mat)
    }
  })
  materialSelectorChecked.value = []
  showMaterialSelector.value = false
}

const addEmptyShot = () => {
  const shot = {
    id: shotIdCounter++,
    name: `镜头 ${shots.value.length + 1}`,
    thumbnail: '',
    materialId: null,
    materialType: 'image',
    duration: 3,
    muteOriginal: false,
    transition: 'none',
    filter: '',
    subtitle: '',
    voiceText: '',
  }
  shots.value.push(shot)
  selectedShotId.value = shot.id
}

const addShotFromMaterial = (item) => {
  const shot = {
    id: shotIdCounter++,
    name: item.name,
    thumbnail: item.thumbnail,
    materialId: item.id,
    materialType: item.type || 'image',
    duration: item.duration || 3,
    muteOriginal: false,
    transition: 'none',
    filter: '',
    subtitle: '',
    voiceText: '',
  }
  shots.value.push(shot)
  selectedShotId.value = shot.id
}

const removeShot = (idx) => {
  const removed = shots.value.splice(idx, 1)[0]
  if (selectedShotId.value === removed.id) {
    selectedShotId.value = shots.value.length > 0 ? shots.value[shots.value.length - 1].id : null
  }
}

const onDragStartMaterial = (e, item) => {
  e.dataTransfer.setData('materialId', String(item.id))
  e.dataTransfer.effectAllowed = 'copy'
}

const onDragStartShot = (e, idx) => {
  dragSourceIdx = idx
  e.dataTransfer.setData('shotIdx', String(idx))
  e.dataTransfer.effectAllowed = 'move'
}

const onDragOverShot = (e, idx) => {
  dragOverIdx.value = idx
}

const onDropShot = (e) => {
  const materialId = e.dataTransfer.getData('materialId')
  if (materialId) {
    const item = materialList.value.find(m => m.id === Number(materialId))
    if (item) addShotFromMaterial(item)
  }
  dragOverIdx.value = null
}

const onDropShotAt = (e, targetIdx) => {
  const sourceIdx = e.dataTransfer.getData('shotIdx')
  if (sourceIdx === '' || sourceIdx === undefined) {
    onDropShot(e)
    return
  }
  const from = Number(sourceIdx)
  if (from === targetIdx) { dragOverIdx.value = null; return }
  const [moved] = shots.value.splice(from, 1)
  shots.value.splice(targetIdx, 0, moved)
  dragOverIdx.value = null
  dragSourceIdx = null
}

// ---- 通用操作 ----
const saveProject = async () => {
  saving.value = true
  try {
    const payload = buildProjectPayload()
    if (currentProjectId.value) {
      await updateClipProject(payload)
    } else {
      const res = await createClipProject(payload)
      const newId = getProjectId(res)
      if (newId) {
        currentProjectId.value = newId
        payload.id = newId
        await updateClipProject(payload)
      }
    }
    const savedProjectId = currentProjectId.value
    ElMessage.success('工程已保存')
    return savedProjectId
  } catch (e) {
    ElMessage.error('保存失败')
    return null
  } finally {
    saving.value = false
  }
}

const ensureSavedProject = async () => currentProjectId.value || await saveProject()

const handleSaveAsTemplate = async () => {
  try {
    await ElMessageBox.prompt('请输入模板名称', '保存为模板', {
      confirmButtonText: '保存',
      cancelButtonText: '取消'
    }).then(async ({ value }) => {
      const projectId = await ensureSavedProject()
      if (!projectId) return
      await saveAsTemplateApi(projectId)
      ElMessage.success('模板已保存')
    })
  } catch (e) { /* cancelled */ }
}

const handleMyTemplates = () => {
  backToSelect()
}

// ---- 背景音乐 ----
const musicList = ref([])
const bgMusicList = ref([])
const bgMusicCount = computed(() => bgMusicList.value.length)

const searchMusic = () => {
  // 模拟音乐搜索 - 实际应调用音乐库API
  ElMessage.info('音乐搜索功能需要对接音乐库API')
}

const toggleMusicSelectAll = (val) => {
  musicList.value.forEach(item => item.checked = val)
}

const confirmMusicSelect = () => {
  const selected = musicList.value.filter(item => item.checked)
  if (selected.length > 0) {
    bgMusicList.value.push(...selected.map(item => ({ name: item.name, url: item.url, duration: item.duration })))
    ElMessage.success(`已添加${selected.length}首音乐`)
  }
  showMusicDialog.value = false
}

// ---- 标题文案 ----
const titleTextList = ref([])
const titleTextCount = computed(() => titleTextList.value.length)

const addTitleText = () => {
  showTitleDialog.value = true
}

const confirmTitleText = () => {
  const lines = titleTextContent.value.split('\n').filter(line => line.trim())
  if (lines.length === 0) {
    ElMessage.warning('请输入至少一条文案')
    return
  }
  titleTextList.value = lines.slice(0, 50).map(text => ({ text }))
  ElMessage.success(`已设置${titleTextList.value.length}条文案`)
  showTitleDialog.value = false
}

// ---- 贴纸 ----
const stickerList = ref([])
const stickerCount = computed(() => stickerList.value.length)

const addSticker = () => {
  ElMessage.info('贴纸上传需要对接素材库选择器')
}

const removeSticker = (idx) => {
  stickerList.value.splice(idx, 1)
}

const confirmStickers = () => {
  showStickerDialog.value = false
  ElMessage.success(`已选择${stickerList.value.length}个贴纸`)
}

const handleExport = async () => {
  if (shots.value.length === 0) { ElMessage.warning('请至少添加一个镜头'); return }
  generating.value = true
  try {
    const projectId = await ensureSavedProject()
    if (!projectId) return
    await exportClipProjectApi(projectId)
    ElMessage.success('导出任务已提交，正在渲染中...')
    await pollExportStatus(projectId)
  } catch (e) {
    ElMessage.error('导出失败')
  } finally {
    generating.value = false
  }
}

/**
 * 轮询导出状态
 * 每2秒查询一次，最多60次（2分钟），完成后显示下载链接
 */
const pollExportStatus = (projectId) => {
  return new Promise((resolve, reject) => {
    let attempts = 0
    const maxAttempts = 60
    const interval = 2000

    const timer = setInterval(async () => {
      attempts++

      try {
        const detail = await getClipProjectDetail(projectId)

        if (detail && detail.status === 'completed') {
          clearInterval(timer)
          // 更新本地 projectId
          currentProjectId.value = projectId
          ElMessage({
            type: 'success',
            message: h('div', null, [
              '视频渲染完成！',
              h('a', {
                href: '/' + detail.video_url,
                target: '_blank',
                style: 'color:#409eff;margin-left:8px;',
              }, '点击下载'),
            ]),
            duration: 5000,
          })
          resolve(detail)
        } else if (detail && detail.status === 'failed') {
          clearInterval(timer)
          ElMessage.error('视频渲染失败，请检查工程配置后重试')
          resolve(null)
        } else if (attempts >= maxAttempts) {
          clearInterval(timer)
          ElMessage.warning('渲染超时，请稍后在工程列表中查看导出状态')
          resolve(null)
        }
        // 其他状态（exporting/processing）继续轮询
      } catch (e) {
        // 查询失败不中断轮询，继续等待
        if (attempts >= maxAttempts) {
          clearInterval(timer)
          ElMessage.warning('查询导出状态失败，请稍后手动查看')
          resolve(null)
        }
      }
    }, interval)
  })
}

const useTemplate = (tpl) => {
  currentView.value = 'storyboard'
  currentProjectId.value = null
  selectedShotId.value = null

  // 模板可能携带后端分镜数据
  if (tpl.shots && Array.isArray(tpl.shots)) {
    shots.value = tpl.shots.map(mapShotFromBackend)
    if (shots.value.length > 0) selectedShotId.value = shots.value[0].id
    shotIdCounter = Math.max(...tpl.shots.map(s => s.id || 0), 0) + 1
  }

  // 回填全局配置
  if (tpl.config && typeof tpl.config === 'object' && tpl.config.globalConfig) {
    const gc = tpl.config.globalConfig
    if (gc.subtitle) Object.assign(globalConfig.subtitle, gc.subtitle)
    if (gc.voice) Object.assign(globalConfig.voice, gc.voice)
    if (gc.music) Object.assign(globalConfig.music, gc.music)
    if (gc.title) globalConfig.title = gc.title
    if (gc.aspectRatio) globalConfig.aspectRatio = gc.aspectRatio
    if (gc.frameRate) globalConfig.frameRate = gc.frameRate
    if (gc.color) Object.assign(globalConfig.color, gc.color)
  }
}

const openProject = async (row) => {
  currentProjectId.value = row.id || null
  const mode = getProjectMode(row)
  currentView.value = 'editor'
  currentEditorMode.value = mode

  try {
    const res = await getClipProjectDetail(row.id)
    const detail = res
    if (!detail) return

    // 回填 config（后端 config 字段自动 JSON 解码）
    if (detail.config && typeof detail.config === 'object') {
      const cfg = detail.config
      if (cfg.globalConfig) {
        const gc = cfg.globalConfig
        if (gc.subtitle) Object.assign(globalConfig.subtitle, gc.subtitle)
        if (gc.voice) Object.assign(globalConfig.voice, gc.voice)
        if (gc.music) Object.assign(globalConfig.music, gc.music)
        if (gc.title) globalConfig.title = gc.title
        if (gc.aspectRatio) globalConfig.aspectRatio = gc.aspectRatio
        if (gc.frameRate) globalConfig.frameRate = gc.frameRate
        if (gc.color) Object.assign(globalConfig.color, gc.color)
      }
      if (mode === 'oneClick' && cfg.oneClick) {
        Object.assign(oneClickForm, cfg.oneClick)
      }
      if (mode === 'batch' && cfg.batch) {
        Object.assign(batchForm, cfg.batch)
      }
    }

    // 回填分镜（detail 接口 with(['shots']) 返回关联分镜）
    const rawShots = detail.shots || []
    if (rawShots.length > 0) {
      shots.value = rawShots.map(mapShotFromBackend)
      selectedShotId.value = shots.value[0]?.id || null
      shotIdCounter = Math.max(...rawShots.map(s => s.id), 0) + 1
    } else {
      // 没有关联分镜时，单独请求分镜列表
      try {
        const shotRes = await getClipShots(row.id)
        const shotList = toListPayload(shotRes)
        if (shotList.length > 0) {
          shots.value = shotList.map(mapShotFromBackend)
          selectedShotId.value = shots.value[0]?.id || null
          shotIdCounter = Math.max(...shotList.map(s => s.id), 0) + 1
        }
      } catch {
        // 分镜加载失败，保持空列表
      }
    }
  } catch (e) {
    ElMessage.error('加载工程详情失败')
  }
}

// ---- 数据加载 ----
const loadStores = async () => {
  try {
    const res = await getStoreList()
    storeList.value = normalizeListPayload(res)
  } catch {
    storeList.value = []
  }
}

const loadMaterials = async (storeId) => {
  try {
    const res = await getMaterialList({ storeId })
    materialList.value = normalizeListPayload(res)
  } catch {
    materialList.value = []
  }
}

const loadTemplates = async () => {
  try {
    const res = await getTemplateList()
    templateList.value = normalizeListPayload(res)
  } catch {
    templateList.value = []
  }
}

const loadMyTemplates = async () => {
  try {
    const res = await getMyTemplates()
    myTemplates.value = normalizeListPayload(res)
  } catch {
    myTemplates.value = []
  }
}

const loadRecentProjects = async () => {
  try {
    const res = await getClipProjectList({ page: 1, limit: 5 })
    recentProjects.value = normalizeListPayload(res)
  } catch {
    recentProjects.value = []
  }
}

onMounted(async () => {
  await Promise.all([loadStores(), loadMaterials(), loadTemplates(), loadMyTemplates(), loadRecentProjects()])
})
</script>

<style scoped lang="scss">
.edit-workbench {
  height: 100%;
  display: flex;
  flex-direction: column;
}

/* ========== 模式选择 ========== */
.view-mode-select {
  padding: 24px;
  overflow-y: auto;
}

.page-header {
  margin-bottom: 24px;
  h2 { margin: 0 0 4px; }
  .subtitle { color: #909399; margin: 0; font-size: 14px; }
}

.mode-cards {
  display: flex;
  gap: 24px;
  margin-bottom: 32px;
}

.mode-card {
  flex: 1;
  padding: 32px 24px;
  border: 2px solid #e4e7ed;
  border-radius: 12px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;

  &:hover {
    border-color: #409eff;
    box-shadow: 0 4px 16px rgba(64, 158, 255, 0.15);
    transform: translateY(-2px);
  }

  .mode-icon {
    color: #409eff;
    margin-bottom: 16px;
  }
  .mode-name { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
  .mode-desc { color: #409eff; font-size: 14px; margin-bottom: 8px; }
  .mode-target { color: #909399; font-size: 13px; margin-bottom: 16px; }
  .mode-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 13px;
    color: #606266;
    .arrow { color: #c0c4cc; }
  }
}

.bottom-section {
  .section-block {
    margin-bottom: 24px;
    h3 { margin: 0 0 12px; font-size: 16px; }
  }
}

.horizontal-scroll {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 8px;

  .empty-hint {
    color: #909399;
    font-size: 13px;
    padding: 20px 0;
  }
}

.template-card {
  min-width: 120px;
  cursor: pointer;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  overflow: hidden;
  transition: border-color 0.3s;

  &:hover { border-color: #409eff; }

  .tpl-thumb {
    width: 120px;
    height: 80px;
    background: #f5f7fa;
  }
  .tpl-name {
    padding: 6px 8px;
    font-size: 12px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

/* ========== 通用 mode-header ========== */
.mode-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 20px;
  background: #fff;
  border-bottom: 1px solid #e4e7ed;
  flex-shrink: 0;

  h3 { margin: 0; flex: 1; }
  .header-actions { display: flex; gap: 8px; }
}

/* ========== 编辑器主界面 ========== */
.view-editor {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: #f8f8f8;
}

/* 顶部工具栏 */
.task-button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  height: 56px;
  background: #fff;
  flex-shrink: 0;
  border-bottom: 1px solid #eee;

  .task-left, .task-right {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .task-center {
    display: flex;
    align-items: center;
    gap: 20px;
  }
}

.editor-tabs {
  display: flex;
  align-items: center;
  padding: 3px;
  border: 1px solid #ebeef5;
  border-radius: 34px;
  background: #fff;

  .editor-tab {
    padding: 6px 24px;
    font-size: 14px;
    color: #0c0d0e;
    cursor: pointer;
    border-radius: 34px;
    transition: all 0.2s;
    white-space: nowrap;

    &.active {
      background: linear-gradient(134deg, #be5cff, #8582ff);
      color: #fff;
      font-weight: 600;
    }

    &:hover:not(.active) {
      background: #f5f7fa;
    }
  }
}

.three-desc-link {
  font-size: 14px;
  color: #737a87;
  cursor: pointer;
  white-space: nowrap;

  &:hover { color: #8582ff; }
}

.toolbar-btn {
  padding: 8px 15px;
  border-radius: 8px;
  font-size: 14px;
  box-shadow: #ebeef5 0 0 0 1px inset;
  border: none;

  &.primary {
    background: linear-gradient(134deg, #be5cff, #8582ff);
    color: #fff;
    box-shadow: none;
  }
}

/* 主体内容区 */
.editor-body {
  display: flex;
  flex: 1;
  overflow: hidden;
  padding: 8px;
  gap: 6px;
}

.editor-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
  overflow-y: auto;
}

/* 通用标题行 */
.c-tit {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 32px;
  margin: 8px 0;

  .title-l {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #000;
  }

  .title-text {
    font-weight: 500;
  }

  .add-r {
    display: flex;
    align-items: center;
    gap: 8px;
  }
}

/* 镜头素材面板 */
.sence-container {
  background: #fff;
  padding: 0 20px 16px;
  border-radius: 4px;

  .lens-list {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    min-height: 60px;
    padding: 8px 0;
  }

  .lens-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 2px solid #e4e7ed;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;

    &:hover { border-color: #bd5ffb; }
    &.active { border-color: #834eff; background: #faf5ff; }

    .lens-thumb {
      width: 48px;
      height: 36px;
      background: #f5f7fa;
      border-radius: 4px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;

      .el-image { width: 100%; height: 100%; }
    }

    .lens-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
      .lens-name { font-size: 12px; color: #303133; }
      .lens-dur { font-size: 11px; color: #909399; }
    }

    .lens-delete {
      opacity: 0;
      transition: opacity 0.2s;
      color: #f56c6c;
      cursor: pointer;
    }

    &:hover .lens-delete { opacity: 1; }
  }

  .empty-lens {
    color: #909399;
    font-size: 13px;
    text-align: center;
    padding: 20px;
    width: 100%;
  }
}

/* 高级设置面板 */
.bottom-main {
  background: #fff;
  padding: 0 20px 16px;
  border-radius: 4px;
}

.config-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 10px;
}

.config-line {
  height: 1px;
  background: #eee;
  margin: 8px 0 12px;
}

.config-tag {
  display: inline-block;
  padding: 4px 10px;
  background: #f5f7fa;
  color: #787e87;
  font-size: 14px;
  margin-right: 6px;
  border-radius: 2px;
}

.config-label {
  font-size: 14px;
  color: #303133;
  margin-left: 4px;
}

.config-text {
  font-size: 14px;
  color: #606266;
}

.config-desc {
  font-size: 13px;
  color: #737a87;
}

.setting-btn {
  background: #f5f8fa;
  border: none;
  border-radius: 8px;
}

.export-btn {
  background: linear-gradient(135deg, #ff6b35, #ff8f35) !important;
  border: none !important;
  color: #fff !important;
  border-radius: 8px;
  &:hover { opacity: 0.9; }
}

.popover-setting {
  padding: 4px 0;
}

.popover-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  &:last-child { margin-bottom: 0; }
}

.popover-label {
  font-size: 13px;
  color: #333;
  white-space: nowrap;
  min-width: 70px;
}

.tpl-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #e8d5f5, #d5c8e8);
  color: #834EFF;
  font-size: 12px;
  border-radius: 6px;
}

/* 背景音乐弹窗 */
.music-dialog-body {
  .music-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 16px;
    border-bottom: 1px solid #eee;
  }
  .music-tab {
    padding: 8px 20px;
    cursor: pointer;
    font-size: 14px;
    color: #666;
    border-bottom: 2px solid transparent;
    &.active {
      color: #834EFF;
      border-bottom-color: #834EFF;
    }
  }
  .music-search {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
  }
  .music-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
  }
  .music-tag {
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 12px;
    background: #f5f5f5;
    color: #666;
    cursor: pointer;
    &.active {
      background: #834EFF;
      color: #fff;
    }
  }
  .music-list {
    max-height: 300px;
    overflow-y: auto;
  }
  .music-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 4px;
    border-bottom: 1px solid #f5f5f5;
    &:hover { background: #fafafa; }
  }
  .music-name {
    flex: 1;
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .music-duration {
    font-size: 12px;
    color: #999;
  }
}

.dialog-footer-left {
  float: left;
}

/* 标题文案弹窗 */
.title-dialog-body {
  .title-tip {
    font-size: 12px;
    color: #999;
    margin-bottom: 8px;
  }
}

/* 贴纸弹窗 */
.sticker-dialog-body {
  .sticker-tip {
    font-size: 12px;
    color: #999;
    margin-bottom: 12px;
  }
  .sticker-list {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
  }
  .sticker-item {
    width: 80px;
    height: 80px;
    position: relative;
    border-radius: 8px;
    border: 1px solid #eee;
    overflow: hidden;
    .el-button {
      position: absolute;
      top: 2px;
      right: 2px;
      width: 20px;
      height: 20px;
    }
  }
  .sticker-img {
    width: 100%;
    height: 100%;
  }
  .sticker-add {
    width: 80px;
    height: 80px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #999;
    font-size: 12px;
    gap: 4px;
    &:hover { border-color: #834EFF; color: #834EFF; }
  }
}

.material-selector-body {
  .material-selector-search {
    margin-bottom: 12px;
    .el-input { width: 100%; }
  }
  .material-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
    padding: 4px;
  }
  .material-grid-item {
    cursor: pointer;
    border-radius: 8px;
    border: 2px solid transparent;
    overflow: hidden;
    transition: all 0.2s;
    &:hover { border-color: #BD5FFB; }
    &.checked { border-color: #834EFF; background: rgba(131, 78, 255, 0.05); }
  }
  .material-thumb {
    position: relative;
    width: 100%;
    height: 120px;
    background: #f5f5f5;
    .el-image { width: 100%; height: 100%; }
  }
  .material-play-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #fff;
    background: rgba(0, 0, 0, 0.4);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .material-check-icon {
    position: absolute;
    top: 4px;
    right: 4px;
    color: #834EFF;
  }
  .material-name {
    padding: 6px 8px;
    font-size: 12px;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .material-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 40px 0;
    color: #999;
    font-size: 14px;
  }
}

.radio-mini {
  :deep(.el-radio) {
    margin-right: 16px;

    .el-radio__label {
      font-size: 13px;
    }
  }
}

/* 右侧面板 */
.preview-right {
  width: 380px;
  flex-shrink: 0;
  background: #fff;
  padding: 16px;
  border-radius: 4px;
  overflow-y: auto;

  .preview-right-form {
    :deep(.el-form-item) {
      margin-bottom: 12px;
    }

    :deep(.el-form-item__label) {
      font-size: 14px;
      color: #0c0d0e;
    }
  }
}

.video-size {
  display: flex;
  gap: 12px;
}

.size-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;

  &:hover { border-color: #bd5ffb; }
  &.active { border-color: #bd5ffb; color: #834eff; }

  .size-icon {
    width: 18px;
    height: 18px;
    border: 2px solid currentColor;
    border-radius: 2px;
  }

  .icon-9x16 { width: 12px; height: 20px; }
  .icon-16x9 { width: 22px; height: 13px; }
  .icon-1x1 { width: 18px; height: 18px; }
}

.color-section {
  margin-top: 4px;

  .color-header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }

  .color-values {
    display: flex;
    gap: 12px;
    font-size: 13px;
    color: #737a87;
  }
}

/* 分镜剪辑：镜头Tab */
.shot-tabs {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;

  .shot-tab {
    padding: 4px 14px;
    font-size: 13px;
    color: #606266;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.2s;
    white-space: nowrap;

    &:hover { background: #f5f7fa; }
    &.active {
      background: linear-gradient(134deg, #be5cff, #8582ff);
      color: #fff;
      font-weight: 500;
    }
  }
}

/* 镜头详情 */
.shot-detail {
  padding: 8px 0;

  .shot-detail-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 8px;
  }

  .shot-detail-title {
    font-size: 14px;
    font-weight: 500;
    color: #303133;
  }

  .shot-detail-dur {
    font-size: 13px;
    color: #909399;
  }
}

/* 配置区块 */
.config-section {
  margin-bottom: 8px;
}

.config-row.indent {
  padding-left: 20px;
}

/* 背景填充行 */
.bg-fill-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ========== 三种剪辑说明弹出层 ========== */
.clip-one-dialog-mask {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 999;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.clip-one-dialog {
  width: 976px;
  max-height: 90vh;
  overflow-y: auto;
  background: #fff;
  position: relative;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);

  .dialog-header {
    padding: 20px 24px 0;

    .dialog-header-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .dialog-title {
      font-size: 18px;
      font-weight: 600;
      color: #303133;
    }
  }

  .dialog-tabs {
    display: flex;
    gap: 0;
    margin-top: 12px;

    .dialog-tab {
      padding: 8px 28px;
      font-size: 14px;
      color: #606266;
      cursor: pointer;
      border: 1px solid #dcdfe6;
      background: #f5f7fa;
      transition: all 0.2s;

      &:first-child {
        border-radius: 4px 0 0 4px;
      }

      &:last-child {
        border-radius: 0 4px 4px 0;
      }

      &.active {
        background: linear-gradient(135deg, #be5cff, #8582ff);
        color: #fff;
        border-color: transparent;
      }

      &:hover:not(.active) {
        background: #ecf5ff;
        color: #409eff;
      }
    }
  }

  .dialog-body {
    display: flex;
    gap: 16px;
    padding: 20px 24px;
  }

  .dialog-card {
    flex: 1;
    padding: 20px;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    background: #fff;
    transition: all 0.3s;

    &:hover {
      border-color: #8582ff;
      box-shadow: 0 4px 16px rgba(133, 130, 255, 0.12);
    }

    .card-name {
      font-size: 15px;
      font-weight: 600;
      color: #303133;
      margin-bottom: 12px;
    }

    .card-header-row {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 8px;
    }

    .card-star {
      color: #f7ba2a;
      font-size: 18px;
    }

    .card-tagline {
      font-size: 15px;
      font-weight: 600;
      color: #303133;
    }

    .card-desc {
      font-size: 13px;
      color: #606266;
      line-height: 1.5;
      margin-bottom: 14px;
    }

    .card-section {
      margin-bottom: 12px;
    }

    .card-section-title {
      font-size: 13px;
      font-weight: 600;
      color: #8582ff;
      margin-bottom: 6px;
    }

    .card-section-content {
      font-size: 12px;
      color: #606266;
      line-height: 1.6;
    }

    .card-target-item {
      margin-bottom: 2px;
    }

    .card-step-item {
      margin-bottom: 2px;
    }
  }

  .dialog-footer {
    text-align: center;
    padding: 12px 24px 20px;

    .dialog-close-btn {
      padding: 8px 40px;
      font-size: 14px;
      color: #fff;
      background: linear-gradient(135deg, #be5cff, #8582ff);
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: opacity 0.2s;

      &:hover {
        opacity: 0.85;
      }
    }
  }

  .container-close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #fff;
    background: linear-gradient(83deg, #be5cff, #8582ff);
    border-radius: 50%;
    cursor: pointer;
    transition: opacity 0.2s;
    line-height: 1;

    &:hover {
      opacity: 0.8;
    }
  }
}

/* ========== 转场/滤镜弹出层 ========== */
.tf-popover {
  max-height: 420px;
  overflow-y: auto;
}

.tf-tabs {
  display: flex;
  border-bottom: 1px solid #eee;
  margin-bottom: 12px;

  .tf-tab {
    padding: 8px 20px;
    font-size: 14px;
    color: #606266;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;

    &.active {
      color: #834eff;
      border-bottom-color: #834eff;
      font-weight: 500;
    }

    &:hover:not(.active) { color: #a855f7; }
  }
}

.tf-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.tf-label {
  font-size: 14px;
  color: #303133;
  font-weight: 500;
}

.tf-count {
  font-size: 13px;
  color: #909399;
  font-weight: normal;
}

.tf-hint {
  font-size: 12px;
  color: #909399;
  margin-bottom: 10px;
}

.tf-section-title {
  font-size: 13px;
  font-weight: 500;
  color: #303133;
  margin-bottom: 8px;
}

.tf-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  max-height: 400px;
  overflow-y: auto;
  padding: 4px;
}

.tf-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 4px;
  border: 1px solid #eee;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 12px;
  color: #606266;

  &:hover {
    border-color: #bd5ffb;
  }

  &.active {
    border-color: #834eff;
    background: #faf5ff;
    color: #834eff;
  }

  .tf-item-icon {
    width: 100%;
    aspect-ratio: 16/9;
    border-radius: 4px;
    overflow: hidden;
    background: #f0f0f0;
    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }
}

/* ========== 颜色调整弹出层 ========== */
.color-popover {
  padding: 4px 0;
}

.color-slider-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;

  &:last-child {
    margin-bottom: 0;
  }

  .color-slider-label {
    font-size: 13px;
    color: #303133;
    white-space: nowrap;
    min-width: 56px;
  }
}
</style>
