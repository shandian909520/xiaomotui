const fs = require('fs');
const path = require('path');

// 由于环境限制，创建一个简单的HTML渲染器
function createHtmlFromMarkdown(markdownContent, title) {
    // 简单的Markdown到HTML转换
    let htmlContent = markdownContent
        // 标题
        .replace(/^# (.+)$/gm, '<h1>$1</h1>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^#### (.+)$/gm, '<h4>$1</h4>')
        .replace(/^##### (.+)$/gm, '<h5>$1</h5>')
        .replace(/^###### (.+)$/gm, '<h6>$1</h6>')
        // 粗体
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        // 斜体
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        // 代码块
        .replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>')
        // 行内代码
        .replace(/`(.+?)`/g, '<code>$1</code>')
        // 链接
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
        // 换行
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g, '<br>');

    // 包装在段落标签中
    if (!htmlContent.startsWith('<h1>') && !htmlContent.startsWith('<h2>')) {
        htmlContent = '<p>' + htmlContent + '</p>';
    }

    return `
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${title}</title>
    <style>
        body {
            font-family: "Microsoft YaHei", "SimSun", Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h1, h2, h3, h4, h5, h6 {
            color: #2c3e50;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        h1 { font-size: 28px; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        h2 { font-size: 24px; border-bottom: 1px solid #bdc3c7; padding-bottom: 5px; }
        h3 { font-size: 20px; }
        h4 { font-size: 18px; }
        h5 { font-size: 16px; }
        h6 { font-size: 14px; }
        p {
            margin-bottom: 15px;
            text-align: justify;
        }
        code {
            background-color: #f8f9fa;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: "Consolas", "Monaco", monospace;
            font-size: 0.9em;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid #3498db;
        }
        pre code {
            background-color: transparent;
            padding: 0;
        }
        blockquote {
            border-left: 4px solid #3498db;
            margin: 20px 0;
            padding-left: 20px;
            color: #7f8c8d;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        ul, ol {
            margin-bottom: 15px;
            padding-left: 30px;
        }
        li {
            margin-bottom: 5px;
        }
        .page-break {
            page-break-before: always;
        }
        @media print {
            body { margin: 0; padding: 15px; }
            h1 { page-break-after: avoid; }
            h2, h3 { page-break-after: avoid; }
            pre, blockquote { page-break-inside: avoid; }
            table { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    ${htmlContent}
</body>
</html>`;
}

// 文件转换配置
const conversions = [
    {
        input: 'D:\\xiaomotui\\材料鉴别项目管理文档_后30页.md',
        output: 'D:\\xiaomotui\\材料鉴别项目管理文档_后30页.pdf'
    },
    {
        input: 'D:\\xiaomotui\\项目文档体系与管理规范.md',
        output: 'D:\\xiaomotui\\项目文档体系与管理规范_前30页.pdf'
    },
    {
        input: 'D:\\xiaomotui\\程序鉴别材料.md',
        output: 'D:\\xiaomotui\\程序鉴别材料_前30页.pdf'
    }
];

async function convertMarkdownToPdf() {
    console.log('开始转换Markdown文档到PDF...');

    for (let i = 0; i < conversions.length; i++) {
        const { input, output } = conversions[i];
        console.log(`\n正在转换第${i + 1}个文档: ${path.basename(input)}`);

        try {
            // 读取Markdown文件
            const markdownContent = fs.readFileSync(input, 'utf8');

            // 提取标题
            const titleMatch = markdownContent.match(/^# (.+)$/m);
            const title = titleMatch ? titleMatch[1] : path.basename(input, '.md');

            // 转换为HTML
            const htmlContent = createHtmlFromMarkdown(markdownContent, title);

            // 保存HTML文件
            const htmlFile = input.replace('.md', '.html');
            fs.writeFileSync(htmlFile, htmlContent, 'utf8');

            console.log(`✓ HTML文件已生成: ${htmlFile}`);
            console.log(`✓ 准备转换为PDF: ${output}`);
            console.log('注意：由于环境限制，请手动将HTML文件在浏览器中打开并打印为PDF');
            console.log('  建议使用Chrome浏览器的"打印"功能，选择"另存为PDF"');

        } catch (error) {
            console.error(`✗ 转换失败: ${error.message}`);
        }
    }

    console.log('\n转换完成！');
    console.log('\n使用说明：');
    console.log('1. 在生成的HTML文件上右键');
    console.log('2. 选择"打开方式" > "Chrome浏览器"');
    console.log('3. 在浏览器中按 Ctrl+P 或选择"打印"');
    console.log('4. 在目标中选择"另存为PDF"');
    console.log('5. 调整页面设置（建议：A4纸，边距适中）');
    console.log('6. 点击"保存"');
}

// 执行转换
convertMarkdownToPdf().catch(console.error);