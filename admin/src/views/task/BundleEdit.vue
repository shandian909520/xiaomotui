<template>
  <div class="task-bundle-edit" v-loading="loading">
    <el-page-header @back="goBack" :content="isEdit ? '编辑任务包' : '新建任务包'" class="page-header" />

    <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px" class="edit-form">
      <!-- 基本信息 -->
      <el-card class="section-card" header="基本信息">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="任务包名称" prop="bundle_name">
              <el-input v-model="form.bundle_name" placeholder="内部名称，便于管理" maxlength="50" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="绑定设备ID">
              <el-input v-model="form.device_id" placeholder="NFC 设备 ID（可留空）" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="落地页标题" prop="title">
              <el-input v-model="form.title" placeholder="用户看到的任务标题" maxlength="50" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="副标题">
              <el-input v-model="form.subtitle" placeholder="落地页副标题（可选）" maxlength="100" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="封面图">
              <el-input v-model="form.cover" placeholder="封面图 URL 或点击右侧上传">
                <template #append>
                  <el-upload
                    :show-file-list="false"
                    :http-request="({ file }) => doUpload(file, url => (form.cover = url))"
                    accept="image/*"
                  >
                    <el-button :loading="uploading">上传</el-button>
                  </el-upload>
                </template>
              </el-input>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="有效时长">
              <el-input-number v-model="form.expire_hours" :min="1" :max="720" style="width: 100%" />
              <div class="form-tip">领取后完成任务的有效小时数</div>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="状态">
              <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-card>

      <!-- 动作管理 -->
      <el-card class="section-card" header="动作组合">
        <div class="action-add-bar">
          <el-select v-model="selectedPlugin" placeholder="选择插件添加动作" style="width: 280px" filterable>
            <el-option
              v-for="p in pluginList"
              :key="p.key"
              :label="p.name"
              :value="p.key"
            >
              <span>{{ p.name }}</span>
              <span class="plugin-desc">{{ p.description }}</span>
            </el-option>
          </el-select>
          <el-button type="primary" :disabled="!selectedPlugin" @click="addAction">添加动作</el-button>
        </div>

        <el-empty v-if="!form.actions.length" description="暂无动作，请从上方选择插件添加" :image-size="60" />

        <div v-for="(action, index) in form.actions" :key="index" class="action-card">
          <div class="action-card-header">
            <el-tag size="small">{{ pluginName(action.plugin_key) }}</el-tag>
            <span v-if="pluginVerifyMethod(action.plugin_key) === 'manual'" class="manual-tag">需人工审核凭证</span>
            <div class="spacer"></div>
            <el-button size="small" link :disabled="index === 0" @click="moveAction(index, -1)">上移</el-button>
            <el-button size="small" link :disabled="index === form.actions.length - 1" @click="moveAction(index, 1)">下移</el-button>
            <el-button size="small" type="danger" link @click="removeAction(index)">删除</el-button>
          </div>
          <el-row :gutter="20">
            <el-col :span="8">
              <el-form-item label="动作名称">
                <el-input v-model="action.action_name" placeholder="动作显示名称" maxlength="30" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="动作图标">
                <el-input v-model="action.action_icon" placeholder="图标 URL（可选）">
                  <template #append>
                    <el-upload
                      :show-file-list="false"
                      :http-request="({ file }) => doUpload(file, url => (action.action_icon = url))"
                      accept="image/*"
                    >
                      <el-button :loading="uploading">上传</el-button>
                    </el-upload>
                  </template>
                </el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="必须完成">
                <el-switch v-model="action.required" :active-value="1" :inactive-value="0" active-text="必须" inactive-text="可选" />
              </el-form-item>
            </el-col>
          </el-row>
          <!-- 按 plugin_key 渲染的动态配置表单 -->
          <el-row v-if="pluginConfigFields(action.plugin_key).length" :gutter="20">
            <el-col v-for="field in pluginConfigFields(action.plugin_key)" :key="field.key" :span="8">
              <el-form-item :label="field.label">
                <!-- 图片上传型 -->
                <template v-if="field.type === 'upload'">
                  <el-input v-model="action.action_config[field.key]" :placeholder="field.placeholder || '图片 URL 或上传'">
                    <template #append>
                      <el-upload
                        :show-file-list="false"
                        :http-request="({ file }) => doUpload(file, url => (action.action_config[field.key] = url))"
                        accept="image/*"
                      >
                        <el-button :loading="uploading">上传</el-button>
                      </el-upload>
                    </template>
                  </el-input>
                </template>
                <!-- 数字型 -->
                <el-input-number
                  v-else-if="field.type === 'number'"
                  v-model="action.action_config[field.key]"
                  :min="0"
                  style="width: 100%"
                  :placeholder="field.placeholder"
                />
                <!-- 文本型 -->
                <el-input v-else v-model="action.action_config[field.key]" :placeholder="field.placeholder" />
              </el-form-item>
            </el-col>
          </el-row>
        </div>
      </el-card>

      <!-- 完成规则 & 奖励 -->
      <el-card class="section-card" header="完成规则与奖励">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="完成规则">
              <el-radio-group v-model="form.completion_rule">
                <el-radio label="ALL">全部完成</el-radio>
                <el-radio label="ANY">任一完成</el-radio>
                <el-radio label="COUNT">完成指定数量</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item v-if="form.completion_rule === 'COUNT'" label="完成数量">
              <el-input-number v-model="form.completion_count" :min="1" :max="form.actions.length || 1" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="奖励类型">
              <el-select v-model="form.reward_type" style="width: 100%">
                <el-option label="无奖励" value="none" />
                <el-option label="红包" value="redpacket" />
                <el-option label="优惠券" value="coupon" />
                <el-option label="积分" value="points" />
              </el-select>
            </el-form-item>
            <!-- 奖励动态配置 -->
            <template v-if="form.reward_type === 'redpacket'">
              <el-form-item label="红包ID">
                <el-input v-model="form.reward_config.redpacket_id" placeholder="红包活动 ID" />
              </el-form-item>
              <el-form-item label="红包金额(元)">
                <el-input-number v-model="form.reward_config.amount" :min="0.01" :precision="2" style="width: 100%" />
              </el-form-item>
              <el-form-item label="每日上限">
                <el-input-number v-model="form.reward_config.daily_limit" :min="1" style="width: 100%" />
              </el-form-item>
            </template>
            <el-form-item v-else-if="form.reward_type === 'coupon'" label="优惠券ID">
              <el-input v-model="form.reward_config.coupon_id" placeholder="优惠券 ID" />
            </el-form-item>
            <template v-else-if="form.reward_type === 'points'">
              <el-form-item label="积分数量">
                <el-input-number v-model="form.reward_config.points_amount" :min="1" style="width: 100%" />
              </el-form-item>
              <el-form-item label="每日上限">
                <el-input-number v-model="form.reward_config.daily_limit" :min="1" style="width: 100%" />
              </el-form-item>
            </template>
          </el-col>
        </el-row>
      </el-card>

      <div class="footer-actions">
        <el-button @click="goBack">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSave">保存</el-button>
      </div>
    </el-form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { getBundleDetail, createBundle, updateBundle, getPluginList } from '@/api/task'
import { uploadMaterial } from '@/api/promo-material'
import { normalizeListPayload, snakeToCamel } from '@/utils/responseHelper'

const route = useRoute()
const router = useRouter()

const bundleId = computed(() => route.params.id)
const isEdit = computed(() => !!bundleId.value)

const loading = ref(false)
const submitting = ref(false)
const uploading = ref(false)
const formRef = ref(null)

const pluginList = ref([])
const selectedPlugin = ref('')

/**
 * 插件 action_config 动态表单字段映射表
 * type: text | number | upload
 */
const PLUGIN_CONFIG_FIELDS = {
  douyin_publish: [
    { key: 'topic', label: '话题', type: 'text', placeholder: '如 #探店打卡' },
    { key: 'poi_name', label: 'POI 名称', type: 'text', placeholder: '门店/地点名称' }
  ],
  kuaishou_publish: [
    { key: 'topic', label: '话题', type: 'text', placeholder: '如 #探店打卡' },
    { key: 'poi_name', label: 'POI 名称', type: 'text', placeholder: '门店/地点名称' }
  ],
  xiaohongshu_publish: [
    { key: 'topic', label: '话题', type: 'text', placeholder: '如 #探店攻略' }
  ],
  moments_share: [],
  group_share: [],
  wework_add_friend: [
    { key: 'qrcode_url', label: '企微二维码', type: 'upload', placeholder: '二维码图片 URL' }
  ],
  official_account_follow: [
    { key: 'qrcode_url', label: '公众号二维码', type: 'upload', placeholder: '二维码图片 URL' }
  ],
  claim_coupon: [
    { key: 'coupon_id', label: '优惠券ID', type: 'number', placeholder: '券 ID' }
  ]
}

const defaultForm = () => ({
  bundle_name: '',
  title: '',
  subtitle: '',
  cover: '',
  completion_rule: 'ALL',
  completion_count: 1,
  reward_type: 'none',
  reward_config: {},
  lander_config: {},
  expire_hours: 24,
  status: 1,
  device_id: '',
  actions: []
})

const form = reactive(defaultForm())

const formRules = {
  bundle_name: [{ required: true, message: '请输入任务包名称', trigger: 'blur' }],
  title: [{ required: true, message: '请输入落地页标题', trigger: 'blur' }]
}

const pluginConfigFields = (key) => PLUGIN_CONFIG_FIELDS[key] || []
const pluginName = (key) => pluginList.value.find(p => p.key === key)?.name || key
const pluginVerifyMethod = (key) => pluginList.value.find(p => p.key === key)?.verifyMethod || pluginList.value.find(p => p.key === key)?.verify_method

const doUpload = async (file, onDone) => {
  uploading.value = true
  try {
    const res = await uploadMaterial(file, 'image')
    const url = res?.url || res?.data?.url || (typeof res === 'string' ? res : '')
    if (!url) {
      ElMessage.error('上传失败：未返回文件地址')
      return
    }
    onDone(url)
    ElMessage.success('上传成功')
  } catch (err) {
    console.error('上传失败:', err)
    ElMessage.error('上传失败，请稍后重试')
  } finally {
    uploading.value = false
  }
}

const loadPlugins = async () => {
  try {
    const res = await getPluginList()
    pluginList.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取插件列表失败:', err)
    pluginList.value = []
    ElMessage.error('获取插件列表失败，请稍后重试')
  }
}

const loadDetail = async () => {
  loading.value = true
  try {
    const res = await getBundleDetail(bundleId.value)
    const data = snakeToCamel(res && typeof res === 'object' ? (res.data && res.bundle === undefined ? res.data : res) : {}) || {}
    const bundle = data.bundle || {}
    const actions = data.actions || []
    Object.assign(form, defaultForm(), {
      bundle_name: bundle.bundleName || '',
      title: bundle.title || '',
      subtitle: bundle.subtitle || '',
      cover: bundle.cover || '',
      completion_rule: bundle.completionRule || 'ALL',
      completion_count: bundle.completionCount ?? 1,
      reward_type: bundle.rewardType || 'none',
      reward_config: bundle.rewardConfig || {},
      lander_config: bundle.landerConfig || {},
      expire_hours: bundle.expireHours ?? 24,
      status: bundle.status ?? 1,
      device_id: bundle.deviceId ?? '',
      actions: actions.map(a => ({
        plugin_key: a.pluginKey,
        action_name: a.actionName || '',
        action_icon: a.actionIcon || '',
        action_config: a.actionConfig || {},
        sort_order: a.sortOrder ?? 0,
        required: a.required ?? 1
      }))
    })
    // 确保 reward_config 是响应式可写对象
    form.reward_config = { ...form.reward_config }
    form.lander_config = { ...form.lander_config }
  } catch (err) {
    console.error('获取任务包详情失败:', err)
    ElMessage.error('获取任务包详情失败')
  } finally {
    loading.value = false
  }
}

const addAction = () => {
  const plugin = pluginList.value.find(p => p.key === selectedPlugin.value)
  if (!plugin) return
  form.actions.push({
    plugin_key: plugin.key,
    action_name: plugin.name,
    action_icon: plugin.icon || '',
    action_config: {},
    sort_order: form.actions.length,
    required: 1
  })
  selectedPlugin.value = ''
}

const removeAction = (index) => {
  form.actions.splice(index, 1)
  reindexActions()
}

const moveAction = (index, dir) => {
  const target = index + dir
  if (target < 0 || target >= form.actions.length) return
  const [item] = form.actions.splice(index, 1)
  form.actions.splice(target, 0, item)
  reindexActions()
}

const reindexActions = () => {
  form.actions.forEach((a, i) => { a.sort_order = i })
}

const handleSave = async () => {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }
  if (!form.actions.length) {
    ElMessage.warning('请至少添加一个动作')
    return
  }
  submitting.value = true
  try {
    const payload = {
      bundle_name: form.bundle_name,
      title: form.title,
      subtitle: form.subtitle,
      cover: form.cover,
      completion_rule: form.completion_rule,
      completion_count: form.completion_count,
      reward_type: form.reward_type,
      reward_config: form.reward_config,
      lander_config: form.lander_config,
      expire_hours: form.expire_hours,
      status: form.status,
      device_id: form.device_id,
      actions: form.actions.map((a, i) => ({
        plugin_key: a.plugin_key,
        action_name: a.action_name,
        action_icon: a.action_icon,
        action_config: a.action_config,
        sort_order: a.sort_order ?? i,
        required: a.required
      }))
    }
    if (isEdit.value) {
      await updateBundle(bundleId.value, payload)
      ElMessage.success('更新成功')
    } else {
      await createBundle(payload)
      ElMessage.success('创建成功')
    }
    goBack()
  } catch (err) {
    console.error('保存失败:', err)
    ElMessage.error(err.message || '保存失败，请稍后重试')
  } finally {
    submitting.value = false
  }
}

const goBack = () => {
  router.push('/task/bundles')
}

onMounted(() => {
  loadPlugins()
  if (isEdit.value) loadDetail()
})
</script>

<style scoped lang="scss">
.task-bundle-edit {
  padding: 20px;

  .page-header {
    margin-bottom: 20px;
  }

  .section-card {
    margin-bottom: 20px;
  }

  .form-tip {
    font-size: 12px;
    color: #909399;
    line-height: 1.4;
    margin-top: 4px;
  }

  .action-add-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;

    .plugin-desc {
      float: right;
      color: #909399;
      font-size: 12px;
      margin-left: 12px;
    }
  }

  .action-card {
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;

    .action-card-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;

      .manual-tag {
        font-size: 12px;
        color: #e6a23c;
      }

      .spacer {
        flex: 1;
      }
    }
  }

  .footer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 10px 0;
  }
}
</style>
