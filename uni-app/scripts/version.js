#!/usr/bin/env node

/**
 * 小魔推碰一碰 - 版本管理脚本
 * 用于自动更新版本号和生成更新日志
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// 颜色输出
const colors = {
  reset: '\x1b[0m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  red: '\x1b[31m',
  blue: '\x1b[34m'
};

const log = {
  info: (msg) => console.log(`${colors.green}[INFO]${colors.reset} ${msg}`),
  warn: (msg) => console.log(`${colors.yellow}[WARN]${colors.reset} ${msg}`),
  error: (msg) => console.log(`${colors.red}[ERROR]${colors.reset} ${msg}`),
  title: (msg) => console.log(`${colors.blue}${msg}${colors.reset}`)
};

// manifest.json 路径
const manifestPath = path.join(__dirname, '../manifest.json');

// 读取manifest.json
function readManifest() {
  try {
    const content = fs.readFileSync(manifestPath, 'utf-8');
    return JSON.parse(content);
  } catch (error) {
    log.error(`读取manifest.json失败: ${error.message}`);
    process.exit(1);
  }
}

// 写入manifest.json
function writeManifest(manifest) {
  try {
    const content = JSON.stringify(manifest, null, 2);
    fs.writeFileSync(manifestPath, content + '\n', 'utf-8');
    log.info('manifest.json 更新成功');
  } catch (error) {
    log.error(`写入manifest.json失败: ${error.message}`);
    process.exit(1);
  }
}

// 解析版本号
function parseVersion(versionName) {
  const parts = versionName.split('.');
  return {
    major: parseInt(parts[0]) || 0,
    minor: parseInt(parts[1]) || 0,
    patch: parseInt(parts[2]) || 0
  };
}

// 格式化版本号
function formatVersion(version) {
  return `${version.major}.${version.minor}.${version.patch}`;
}

// 递增版本号
function incrementVersion(versionName, type) {
  const version = parseVersion(versionName);

  switch (type) {
    case 'major':
      version.major++;
      version.minor = 0;
      version.patch = 0;
      break;
    case 'minor':
      version.minor++;
      version.patch = 0;
      break;
    case 'patch':
      version.patch++;
      break;
    default:
      log.error(`未知的版本类型: ${type}`);
      process.exit(1);
  }

  return formatVersion(version);
}

// 生成versionCode
function generateVersionCode(versionName) {
  const version = parseVersion(versionName);
  // versionCode = major * 10000 + minor * 100 + patch
  return (version.major * 10000 + version.minor * 100 + version.patch).toString();
}

// 获取git提交信息
function getGitCommits(fromTag) {
  try {
    const command = fromTag
      ? `git log ${fromTag}..HEAD --oneline --no-merges`
      : 'git log -10 --oneline --no-merges';
    const output = execSync(command, { encoding: 'utf-8' });
    return output.trim().split('\n').filter(line => line);
  } catch (error) {
    log.warn('无法获取git提交记录');
    return [];
  }
}

// 生成更新日志
function generateChangelog(newVersion, commits) {
  const date = new Date().toISOString().split('T')[0];
  let changelog = `## ${newVersion} (${date})\n\n`;

  if (commits.length > 0) {
    changelog += '### 更新内容\n\n';
    commits.forEach(commit => {
      // 提取提交信息（去除hash）
      const message = commit.substring(commit.indexOf(' ') + 1);
      changelog += `- ${message}\n`;
    });
  } else {
    changelog += '### 更新内容\n\n- 版本更新\n';
  }

  changelog += '\n';
  return changelog;
}

// 更新CHANGELOG文件
function updateChangelogFile(newVersion, commits) {
  const changelogPath = path.join(__dirname, '../CHANGELOG.md');
  const newEntry = generateChangelog(newVersion, commits);

  let content = '';
  if (fs.existsSync(changelogPath)) {
    content = fs.readFileSync(changelogPath, 'utf-8');
  } else {
    content = '# 更新日志\n\n';
  }

  // 在文件开头插入新版本
  const lines = content.split('\n');
  const insertIndex = lines.findIndex(line => line.startsWith('## ')) || 2;
  lines.splice(insertIndex, 0, newEntry);

  fs.writeFileSync(changelogPath, lines.join('\n'), 'utf-8');
  log.info('CHANGELOG.md 更新成功');
}

// 创建git tag
function createGitTag(version, message) {
  try {
    execSync(`git tag -a v${version} -m "${message}"`, { stdio: 'inherit' });
    log.info(`Git tag v${version} 创建成功`);
    log.warn('记得推送tag: git push origin v' + version);
  } catch (error) {
    log.warn('创建git tag失败，请手动创建');
  }
}

// 显示帮助信息
function showHelp() {
  console.log(`
小魔推碰一碰 - 版本管理工具

用法: node version.js <command> [options]

命令:
  show              显示当前版本
  bump <type>       递增版本号
    - patch         递增补丁版本 (1.0.0 -> 1.0.1)
    - minor         递增次版本 (1.0.0 -> 1.1.0)
    - major         递增主版本 (1.0.0 -> 2.0.0)
  set <version>     设置指定版本号
  changelog         生成更新日志

示例:
  node version.js show                    # 显示当前版本
  node version.js bump patch              # 递增补丁版本
  node version.js bump minor              # 递增次版本
  node version.js set 1.2.0               # 设置为1.2.0
  node version.js changelog               # 生成更新日志

选项:
  --no-tag          不创建git tag
  --no-changelog    不更新changelog
  `);
}

// 主函数
function main() {
  const args = process.argv.slice(2);

  if (args.length === 0 || args[0] === 'help' || args[0] === '--help') {
    showHelp();
    return;
  }

  const command = args[0];
  const manifest = readManifest();
  const currentVersion = manifest.versionName;
  const currentCode = manifest.versionCode;

  log.title('================================================');
  log.title('   小魔推碰一碰 - 版本管理');
  log.title('================================================');
  console.log('');

  switch (command) {
    case 'show':
      log.info(`当前版本: ${currentVersion}`);
      log.info(`版本代码: ${currentCode}`);
      break;

    case 'bump': {
      const type = args[1];
      if (!type || !['major', 'minor', 'patch'].includes(type)) {
        log.error('请指定版本类型: major, minor, patch');
        process.exit(1);
      }

      const newVersion = incrementVersion(currentVersion, type);
      const newCode = generateVersionCode(newVersion);

      log.info(`版本更新: ${currentVersion} -> ${newVersion}`);
      log.info(`版本代码: ${currentCode} -> ${newCode}`);

      // 更新manifest
      manifest.versionName = newVersion;
      manifest.versionCode = newCode;
      writeManifest(manifest);

      // 生成changelog
      if (!args.includes('--no-changelog')) {
        const commits = getGitCommits(`v${currentVersion}`);
        updateChangelogFile(newVersion, commits);
      }

      // 创建git tag
      if (!args.includes('--no-tag')) {
        createGitTag(newVersion, `Release v${newVersion}`);
      }

      log.title('================================================');
      log.info('版本更新完成！');
      log.title('================================================');
      break;
    }

    case 'set': {
      const newVersion = args[1];
      if (!newVersion || !/^\d+\.\d+\.\d+$/.test(newVersion)) {
        log.error('请提供有效的版本号，格式：x.y.z');
        process.exit(1);
      }

      const newCode = generateVersionCode(newVersion);

      log.info(`版本更新: ${currentVersion} -> ${newVersion}`);
      log.info(`版本代码: ${currentCode} -> ${newCode}`);

      // 更新manifest
      manifest.versionName = newVersion;
      manifest.versionCode = newCode;
      writeManifest(manifest);

      // 生成changelog
      if (!args.includes('--no-changelog')) {
        const commits = getGitCommits(`v${currentVersion}`);
        updateChangelogFile(newVersion, commits);
      }

      // 创建git tag
      if (!args.includes('--no-tag')) {
        createGitTag(newVersion, `Release v${newVersion}`);
      }

      log.title('================================================');
      log.info('版本更新完成！');
      log.title('================================================');
      break;
    }

    case 'changelog': {
      const commits = getGitCommits(`v${currentVersion}`);
      const changelog = generateChangelog(currentVersion, commits);
      console.log(changelog);
      break;
    }

    default:
      log.error(`未知命令: ${command}`);
      showHelp();
      process.exit(1);
  }
}

// 执行主函数
main();
