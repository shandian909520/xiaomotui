const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');

// Chrome无头模式PDF生成函数
function generatePdfFromHtml(htmlFile, outputFile) {
    return new Promise((resolve, reject) => {
        const chromePath = '"C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe"';
        const htmlFilePath = path.resolve(htmlFile);
        const outputFilePath = path.resolve(outputFile);

        const command = `${chromePath} --headless --disable-gpu --print-to-pdf="${outputFilePath}" --print-to-pdf-no-header --run-all-compositor-stages-before-draw --enable-logging --v=1 "file:///${htmlFilePath}"`;

        console.log(`执行命令: ${command}`);

        exec(command, (error, stdout, stderr) => {
            if (error) {
                console.error(`执行错误: ${error.message}`);
                reject(error);
                return;
            }

            if (stderr) {
                console.log(`Chrome输出: ${stderr}`);
            }

            // 检查PDF文件是否生成
            setTimeout(() => {
                if (fs.existsSync(outputFilePath)) {
                    console.log(`✓ PDF文件已生成: ${outputFile}`);
                    resolve(outputFile);
                } else {
                    reject(new Error('PDF文件生成失败'));
                }
            }, 2000);
        });
    });
}

// 文件转换配置
const conversions = [
    {
        html: 'D:\\xiaomotui\\材料鉴别项目管理文档_后30页.html',
        pdf: 'D:\\xiaomotui\\材料鉴别项目管理文档_后30页.pdf'
    },
    {
        html: 'D:\\xiaomotui\\项目文档体系与管理规范.html',
        pdf: 'D:\\xiaomotui\\项目文档体系与管理规范_前30页.pdf'
    },
    {
        html: 'D:\\xiaomotui\\程序鉴别材料.html',
        pdf: 'D:\\xiaomotui\\程序鉴别材料_前30页.pdf'
    }
];

async function convertAllToPdf() {
    console.log('开始自动转换HTML文件为PDF...\n');

    for (let i = 0; i < conversions.length; i++) {
        const { html, pdf } = conversions[i];

        try {
            console.log(`正在转换第${i + 1}个文档: ${path.basename(html)}`);

            if (!fs.existsSync(html)) {
                console.error(`✗ HTML文件不存在: ${html}`);
                continue;
            }

            await generatePdfFromHtml(html, pdf);

        } catch (error) {
            console.error(`✗ 转换失败: ${path.basename(html)} - ${error.message}`);

            // 提供备用方案
            console.log(`建议手动转换步骤:`);
            console.log(`1. 双击打开: ${html}`);
            console.log(`2. 按 Ctrl+P`);
            console.log(`3. 选择"另存为PDF"`);
            console.log(`4. 保存为: ${pdf}`);
        }

        console.log('---');
    }

    console.log('\n转换流程完成！');

    // 检查结果
    console.log('\n转换结果统计:');
    let successCount = 0;
    for (const conversion of conversions) {
        if (fs.existsSync(conversion.pdf)) {
            const stats = fs.statSync(conversion.pdf);
            console.log(`✓ ${path.basename(conversion.pdf)} (${(stats.size / 1024).toFixed(2)} KB)`);
            successCount++;
        } else {
            console.log(`✗ ${path.basename(conversion.pdf)} - 转换失败`);
        }
    }

    console.log(`\n成功转换: ${successCount}/${conversions.length} 个文档`);
}

// 执行转换
convertAllToPdf().catch(console.error);