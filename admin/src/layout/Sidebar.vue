<template>
  <aside class="layout-aside">
    <div class="layout-logo">
      <img class="logo-text" src="https://pyp-xmt.oss-cn-beijing.aliyuncs.com/1/0/upload/20260114/png/e7f04ad27f06a357b176e84a6add6552.png" alt="小魔推" />
    </div>

    <el-scrollbar>
      <el-menu :default-active="activeMenu" class="nav-menu">
        <template v-for="item in menuItems" :key="item.index || item.title">
          <el-menu-item v-if="!item.children" :index="item.index" @click="handleMenuClick(item.index)">
            <img v-if="item.iconImg" :src="getIcon(item)" class="menu-icon" />
            <template #title>
              <span class="menu-title">{{ item.title }}</span>
              <img v-if="item.badgeImg" :src="item.badgeImg" class="menu-badge-img" />
              <span v-else-if="item.badge" class="menu-badge">{{ item.badge }}</span>
            </template>
          </el-menu-item>

          <el-sub-menu v-else :index="item.index || item.title" class="disabled-submenu">
            <template #title>
              <img v-if="item.iconImg" :src="item.iconImg" class="menu-icon" />
              <span class="menu-title">{{ item.title }}</span>
            </template>
            <el-menu-item v-for="child in item.children" :key="child.index" :index="child.index" @click="handleMenuClick(child.index)">
              <img v-if="child.iconImg" :src="getIcon(child)" class="menu-icon" />
              <template #title>
                <span class="menu-title">{{ child.title }}</span>
                <img v-if="child.badgeImg" :src="child.badgeImg" class="menu-badge-img" />
                <span v-else-if="child.badge" class="menu-badge hot">{{ child.badge }}</span>
              </template>
            </el-menu-item>
          </el-sub-menu>
        </template>
      </el-menu>
    </el-scrollbar>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const activeMenu = computed(() => route.path)

const ICON_BASE = 'https://pyp-xmt.oss-cn-beijing.aliyuncs.com/static/image/home/'

const getIcon = (item) => {
  if (route.path === item.index && item.iconActiveImg) return item.iconActiveImg
  return item.iconImg
}

const handleMenuClick = (index) => {
  if (index && index !== route.path) {
    router.push(index)
  }
}

const currentVersion = computed(() => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    return user.version || 'basic'
  } catch (e) {
    return 'basic'
  }
})

const menuItems = computed(() => {
  const items = [
    { title: '首页', index: '/home', iconImg: ICON_BASE + 'shouye.png', iconActiveImg: ICON_BASE + 'shouye-a.png' },
    { title: '门店列表', index: '/stores', iconImg: ICON_BASE + 'mendianliebiao.png', iconActiveImg: ICON_BASE + 'mendianliebiao-a.png', badgeImg: ICON_BASE + '../../routing-custom-style.png' },
    ...(currentVersion.value === 'chain' ? [{ title: '员工管理', index: '/chain/employees', iconImg: ICON_BASE + 'staff.png', iconActiveImg: ICON_BASE + 'staff-a.png' }] : []),
    { title: '素材管理', index: '/materials', iconImg: ICON_BASE + 'sucaiguanli.png', iconActiveImg: ICON_BASE + 'sucaiguanli-a.png' },
    {
      title: '视频创作',
      index: 'video',
      iconImg: ICON_BASE + 'shipinchuangzuo.png',
      children: [
        { title: '新建剪辑', index: '/video/edit', iconImg: ICON_BASE + 'piliangjianji.png', iconActiveImg: ICON_BASE + 'piliangjianji-a.png' },
        { title: '剪辑工程', index: '/video/project', iconImg: ICON_BASE + 'jianjigongcheng.png', iconActiveImg: ICON_BASE + 'jianjigongcheng-a.png' }
      ]
    },
    {
      title: 'AI实验室',
      index: 'ai',
      iconImg: ICON_BASE + 'aishiyanshi.png',
      children: [
        { title: '智能员工', index: '/ai/staff', iconImg: ICON_BASE + 'lingganchuangzuo.png', iconActiveImg: ICON_BASE + 'lingganchuangzuo-a.png', badge: '热门' }
      ]
    },
    {
      title: 'AI成品库',
      index: 'library',
      iconImg: ICON_BASE + 'aichengpinku.png',
      children: [
        { title: '视频库', index: '/library/videos', iconImg: ICON_BASE + 'aishipin.png', iconActiveImg: ICON_BASE + 'aishipin-a.png' },
        { title: '图文库', index: '/library/images', iconImg: ICON_BASE + 'aitupian.png', iconActiveImg: ICON_BASE + 'aitupian-a.png' },
        { title: '话题库', index: '/library/topics', iconImg: ICON_BASE + 'aihuati.png', iconActiveImg: ICON_BASE + 'aihuati-a.png' }
      ]
    },
    {
      title: '发布管理',
      index: 'publish',
      iconImg: ICON_BASE + 'fabuguanli.png',
      children: [
        { title: '发布任务', index: '/publish', iconImg: ICON_BASE + 'faburenwu.png', iconActiveImg: ICON_BASE + 'faburenwu-a.png' },
        { title: '平台账号', index: '/publish/accounts', iconImg: ICON_BASE + 'pingtai.png', iconActiveImg: ICON_BASE + 'pingtai-a.png' }
      ]
    },
    {
      title: '活动管理',
      index: 'activity',
      iconImg: ICON_BASE + 'huodongguanli.png',
      children: [
        { title: '场景配置', index: '/activity/scenes', iconImg: ICON_BASE + 'pengyipeng.png', iconActiveImg: ICON_BASE + 'pengyipeng-a.png' },
        { title: '发红包', index: '/activity/redpackets', iconImg: ICON_BASE + 'fahongbao.png', iconActiveImg: ICON_BASE + 'fahongbao-a.png' }
      ]
    },
    {
      title: '团购商品',
      index: '/groupbuy',
      iconImg: ICON_BASE + 'mendianliebiao.png',
      iconActiveImg: ICON_BASE + 'mendianliebiao-a.png',
      children: [
        { title: '商品列表', index: '/groupbuy/item-list', iconImg: ICON_BASE + 'mendianliebiao.png', iconActiveImg: ICON_BASE + 'mendianliebiao-a.png' }
      ]
    },
    {
      title: '抽奖活动',
      index: '/lottery',
      iconImg: ICON_BASE + 'huodongguanli.png',
      children: [
        { title: '活动管理', index: '/lottery/activity-list', iconImg: ICON_BASE + 'huodongguanli.png' },
        { title: '中奖记录', index: '/lottery/record-list', iconImg: ICON_BASE + 'fahongbao.png', iconActiveImg: ICON_BASE + 'fahongbao-a.png' }
      ]
    },
    {
      title: '碰一碰任务',
      index: 'task',
      iconImg: ICON_BASE + 'pengyipeng.png',
      iconActiveImg: ICON_BASE + 'pengyipeng-a.png',
      children: [
        { title: '任务包管理', index: '/task/bundles', iconImg: ICON_BASE + 'pengyipeng.png', iconActiveImg: ICON_BASE + 'pengyipeng-a.png' },
        { title: '凭证审核', index: '/task/proofs', iconImg: ICON_BASE + 'neirongshenhe.png' },
        { title: '用户任务', index: '/task/instances', iconImg: ICON_BASE + 'faburenwu.png', iconActiveImg: ICON_BASE + 'faburenwu-a.png' }
      ]
    },
    {
      title: '数据监控',
      index: 'monitor',
      iconImg: ICON_BASE + 'shujujiankong.png',
      children: [
        { title: '话题监控', index: '/monitor/topics', iconImg: ICON_BASE + 'huatijiankong.png', iconActiveImg: ICON_BASE + 'huatijiankong-a.png' }
      ]
    },
    {
      title: '运营设计',
      index: 'design',
      iconImg: ICON_BASE + 'wuliaosheji.png',
      iconActiveImg: ICON_BASE + 'wuliaosheji-a.png',
      children: [
        { title: '物料设计', index: '/design/materials', iconImg: ICON_BASE + 'wuliaosheji.png', iconActiveImg: ICON_BASE + 'wuliaosheji-a.png' }
      ]
    },
    {
      title: '内容管理',
      index: 'content',
      iconImg: ICON_BASE + 'neirongguanli.png',
      children: [
        { title: '内容审核', index: '/content/audit', iconImg: ICON_BASE + 'neirongshenhe.png' }
      ]
    },
    {
      title: '设备管理',
      index: 'device',
      iconImg: ICON_BASE + 'shebeiguanli.png',
      children: [
        { title: '设备列表', index: '/device', iconImg: ICON_BASE + 'shebeiliebiao.png' },
        { title: '设备告警', index: '/device/alerts', iconImg: ICON_BASE + 'shebeijingbao.png' },
        { title: '告警规则', index: '/device/alert-rules', iconImg: ICON_BASE + 'gaojingguize.png' }
      ]
    },
    {
      title: '系统管理',
      index: 'system',
      iconImg: ICON_BASE + 'xitongguanli.png',
      children: [
        { title: 'IP黑名单', index: '/system/blacklist', iconImg: ICON_BASE + 'ipheimingdan.png' }
      ]
    }
  ]

  return items
})
</script>

<style lang="scss" scoped>
.layout-aside {
  width: 220px;
  flex: 0 0 220px;
  height: 100vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: linear-gradient(#faf2ff 0%, #f6f3ff 100%);
  border-right: 1px solid #e4e7ed;
}

.layout-logo {
  height: 50px;
  display: flex;
  align-items: center;
  padding: 0 16px;
  flex-shrink: 0;
}

.logo-text {
  width: 85%;
  height: auto;
}

.nav-menu {
  border-right: none;
  background: transparent;
  padding: 0 10px 20px;

  :deep(.el-menu-item) {
    height: 38px;
    line-height: 38px;
    margin-bottom: 10px;
    padding: 0 20px 0 28px !important;
    border-radius: 8px;
    color: #666;
    font-size: 14px;
    font-weight: 400;
  }

  :deep(.el-menu-item .menu-icon) {
    width: 18px;
    height: 18px;
    margin-right: 8px;
    vertical-align: middle;
  }

  :deep(.el-menu-item:hover) {
    background: rgba(255, 255, 255, 0.6);
  }

  :deep(.el-menu-item.is-active) {
    color: #4c535c;
    background: #fff;
    box-shadow: none;
  }

  :deep(.el-sub-menu__title) {
    height: 38px;
    line-height: 38px;
    color: #666;
    font-size: 14px;
    font-weight: 400;
    border-radius: 8px;
    padding-left: 28px !important;
  }

  :deep(.el-sub-menu__title .menu-icon) {
    width: 18px;
    height: 18px;
    margin-right: 8px;
    vertical-align: middle;
  }

  :deep(.el-sub-menu__title:hover) {
    background: rgba(255, 255, 255, 0.4);
  }

  :deep(.el-sub-menu .el-menu-item) {
    padding-left: 52px !important;
    min-width: auto;
  }

  :deep(.el-menu--inline) {
    background: transparent;
  }
}

.menu-title {
  font-size: 14px;
  font-weight: 400;
  color: #1d2129;
}

.menu-badge {
  display: inline-flex;
  align-items: center;
  margin-left: 6px;
  padding: 0 6px;
  height: 16px;
  border-radius: 8px;
  color: #834eff;
  background: #efe6ff;
  font-size: 10px;
  font-weight: 700;
  vertical-align: middle;

  &.hot {
    color: #fff;
    background: linear-gradient(90deg, #ff2f86, #9a42ff);
  }
}

.menu-badge-img {
  height: 27px;
  margin-left: 4px;
  vertical-align: middle;
}
</style>
