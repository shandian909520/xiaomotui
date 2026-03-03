/**
 * 生成 HTML 测试报告
 */

const fs = require('fs');
const path = require('path');

function generateHTMLReport() {
  const reportPath = path.join(__dirname, 'video-library-test-report.json');
  const htmlReportPath = path.join(__dirname, 'video-library-test-report.html');

  if (!fs.existsSync(reportPath)) {
    console.log('未找到测试报告 JSON 文件');
    return;
  }

  const reportData = JSON.parse(fs.readFileSync(reportPath, 'utf-8'));

  const html = `
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>视频库前端功能测试报告</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f9fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .summary-card h3 {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .summary-card .number {
            font-size: 36px;
            font-weight: bold;
        }
        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #1f2937;
            border-left: 4px solid #667eea;
            padding-left: 12px;
        }
        .test-item {
            background: #f9fafb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #d1d5db;
        }
        .test-item.success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .test-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        .test-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .test-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .test-step {
            font-weight: bold;
            color: #374151;
        }
        .test-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-success {
            background: #d1fae5;
            color: #065f46;
        }
        .status-warning {
            background: #fed7aa;
            color: #9a3412;
        }
        .status-error {
            background: #fecaca;
            color: #991b1b;
        }
        .test-message {
            color: #6b7280;
            font-size: 14px;
        }
        .screenshots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .screenshot-item {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .screenshot-item img {
            width: 100%;
            height: auto;
            display: block;
        }
        .screenshot-title {
            padding: 12px;
            background: #f9fafb;
            font-size: 14px;
            color: #374151;
            text-align: center;
            font-weight: 500;
        }
        .error-item {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .error-message {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #991b1b;
            margin-top: 8px;
            white-space: pre-wrap;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background: #f9fafb;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>视频库前端功能测试报告</h1>
            <p>测试时间: ${new Date(reportData.testDate).toLocaleString('zh-CN')}</p>
        </div>

        <div class="summary">
            <div class="summary-card">
                <h3>总测试步骤</h3>
                <div class="number">${reportData.summary.total}</div>
            </div>
            <div class="summary-card">
                <h3>成功</h3>
                <div class="number success">${reportData.summary.success}</div>
            </div>
            <div class="summary-card">
                <h3>警告</h3>
                <div class="number warning">${reportData.summary.warning}</div>
            </div>
            <div class="summary-card">
                <h3>错误</h3>
                <div class="number error">${reportData.summary.errors}</div>
            </div>
        </div>

        <div class="content">
            <div class="section">
                <h2 class="section-title">测试步骤详情</h2>
                ${reportData.testResults.filter(r => r.step).map(item => `
                    <div class="test-item ${item.status}">
                        <div class="test-item-header">
                            <span class="test-step">步骤 ${item.step}</span>
                            <span class="test-status status-${item.status}">${
                                item.status === 'success' ? '成功' :
                                item.status === 'warning' ? '警告' : '失败'
                            }</span>
                        </div>
                        <div class="test-message">${item.message}</div>
                        ${item.detail ? `<div class="test-message" style="margin-top:8px;font-size:12px;">详情: ${JSON.stringify(item.detail)}</div>` : ''}
                    </div>
                `).join('')}
            </div>

            ${reportData.testResults.filter(r => r.type === 'error' || r.type === 'pageerror').length > 0 ? `
            <div class="section">
                <h2 class="section-title">错误日志</h2>
                ${reportData.testResults.filter(r => r.type === 'error' || r.type === 'pageerror').map(item => `
                    <div class="error-item">
                        <div style="font-weight:bold;margin-bottom:5px;">${item.type.toUpperCase()}</div>
                        <div class="error-message">${item.message}</div>
                        ${item.stack ? `<div class="error-message" style="margin-top:8px;font-size:11px;opacity:0.8;">${item.stack}</div>` : ''}
                    </div>
                `).join('')}
            </div>
            ` : ''}

            <div class="section">
                <h2 class="section-title">测试截图</h2>
                <div class="screenshots">
                    ${['01-login-page', '02-after-login', '03-video-library-page',
                        '04-hot-templates', '05-template-grid', '06-industry-filter',
                        '07-difficulty-filter', '08-ratio-filter', '09-keyword-search',
                        '10-sort-by-usage', '11-template-detail', '12-template-detail-info',
                        '13-pagination', '14-pagination-next', '15-final-state'
                    ].map(name => `
                        <div class="screenshot-item">
                            <img src="screenshots/video-library/${name}.png" alt="${name}" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22200%22%3E%3Crect fill=%22%23f3f4f6%22 width=%22300%22 height=%22200%22/%3E%3Ctext fill=%22%239ca3af%22 font-family=%22sans-serif%22 font-size=%2214%22 x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22%3E截图未生成%3C/text%3E%3C/svg%3E'">
                            <div class="screenshot-title">${name}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>

        <div class="footer">
            <p>本报告由自动化测试系统生成 | Playwright 测试框架</p>
        </div>
    </div>
</body>
</html>
  `;

  fs.writeFileSync(htmlReportPath, html, 'utf-8');
  console.log(`HTML 测试报告已生成: ${htmlReportPath}`);
}

generateHTMLReport();
