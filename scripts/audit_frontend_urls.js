#!/usr/bin/env node
/* 审查:前端 api 模块 URL 是否与后端路由对齐 */
const fs = require('fs');
const path = require('path');

const routeSrc = fs.readFileSync('api/route/app.php', 'utf8');

// 后端注册的路径片段(按 group 前缀拼接)
// 简化:提取所有 Route::group('x' 与 Route::get/post/put/delete('y'
// 手动构造已知前缀树太复杂,改用直接串匹配:URL 去掉参数段后,其每一段都应出现在路由文件里
const segSet = new Set();
for (const m of routeSrc.matchAll(/Route::(?:group|get|post|put|delete|any)\(\s*'([^']+)'/g)) {
  const p = m[1];
  if (p.startsWith('/') || p.includes('\\') || p.includes('@')) continue;
  for (const seg of p.split('/')) {
    if (seg) segSet.add(seg.replace(/:[^:]+$/, ':param').replace(/:.*$/, ':param'));
  }
}
// 再把所有单引号字符串字面量(控制器之外的路径段)也收集
for (const m of routeSrc.matchAll(/'([a-z0-9\-_]+)'/gi)) segSet.add(m[1]);

const files = process.argv.slice(2);
let bad = 0;
for (const f of files) {
  if (!fs.existsSync(f)) { console.log('SKIP ' + f); continue; }
  const src = fs.readFileSync(f, 'utf8');
  const urls = [...src.matchAll(/['"`](\/api\/[A-Za-z0-9\/\-_]*)/g)].map(m => m[1]);
  const uniq = [...new Set(urls)];
  if (!uniq.length) { console.log('=== ' + f + ' (no /api urls)'); continue; }
  console.log('=== ' + f);
  for (const u of uniq) {
    const segs = u.replace(/^\//, '').split('/').slice(1); // 去 api
    const miss = segs.filter(s => !segSet.has(s) && !/^[:\$\{]/.test(s) && !/^\d+$/.test(s));
    const flag = miss.length ? '  ** SEG MISS: ' + miss.join(',') : '  ok';
    console.log('  ' + u + flag);
    if (miss.length) bad++;
  }
}
console.log(bad ? '\n' + bad + ' URL(s) 可疑' : '\nALL URLS ALIGN');
