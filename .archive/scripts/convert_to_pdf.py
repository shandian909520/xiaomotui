#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import markdown
import weasyprint
import os
import sys
from datetime import datetime

def add_custom_css():
    """添加自定义CSS样式以优化PDF输出"""
    return """
    <style>
        @page {
            size: A4;
            margin: 2cm;
            @top-center {
                content: "小磨推智能营销系统技术文档";
                font-size: 10pt;
                color: #666;
            }
            @bottom-center {
                content: counter(page);
                font-size: 10pt;
                color: #666;
            }
        }

        body {
            font-family: "Microsoft YaHei", "SimSun", sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: "Microsoft YaHei", "SimHei", sans-serif;
            font-weight: bold;
            margin-top: 1.5em;
            margin-bottom: 1em;
            page-break-after: avoid;
        }

        h1 {
            font-size: 24pt;
            text-align: center;
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }

        h2 {
            font-size: 20pt;
            color: #34495e;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 5px;
        }

        h3 {
            font-size: 16pt;
            color: #34495e;
        }

        h4 {
            font-size: 14pt;
            color: #7f8c8d;
        }

        p {
            margin-bottom: 1em;
            text-align: justify;
        }

        pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 1em;
            font-family: "Consolas", "Monaco", monospace;
            font-size: 10pt;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
            page-break-inside: avoid;
        }

        code {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 3px;
            padding: 0.2em 0.4em;
            font-family: "Consolas", "Monaco", monospace;
            font-size: 0.9em;
        }

        blockquote {
            border-left: 4px solid #3498db;
            padding-left: 1em;
            margin-left: 0;
            margin-right: 0;
            background-color: #f8f9fa;
            color: #6c757d;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 1em;
        }

        th, td {
            border: 1px solid #dee2e6;
            padding: 0.5em;
            text-align: left;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        ul, ol {
            margin-bottom: 1em;
            padding-left: 2em;
        }

        li {
            margin-bottom: 0.5em;
        }

        strong {
            font-weight: bold;
            color: #2c3e50;
        }

        em {
            font-style: italic;
        }

        a {
            color: #3498db;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        hr {
            border: none;
            border-top: 2px solid #ecf0f1;
            margin: 2em 0;
        }

        .toc {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 1em;
            margin-bottom: 2em;
        }

        .toc h2 {
            margin-top: 0;
            border-bottom: none;
            color: #2c3e50;
        }

        /* 代码块行号样式 */
        .linenums {
            counter-reset: linenumber;
        }

        .linenums li {
            list-style-type: none;
            counter-increment: linenumber;
        }

        .linenums li:before {
            content: counter(linenumber);
            color: #999;
            display: inline-block;
            width: 2em;
            margin-right: 0.5em;
            text-align: right;
        }

        /* 避免分页断行 */
        h1, h2, h3, h4, h5, h6 {
            page-break-after: avoid;
        }

        pre, blockquote, table {
            page-break-inside: avoid;
        }

        /* 首页样式 */
        .cover-page {
            page: cover;
        }

        @page cover {
            @top-center {
                content: none;
            }
            @bottom-center {
                content: none;
            }
            margin: 0;
        }

        .cover-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }

        .cover-title {
            font-size: 36pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 1em;
        }

        .cover-subtitle {
            font-size: 18pt;
            color: #7f8c8d;
            margin-bottom: 3em;
        }

        .cover-info {
            font-size: 14pt;
            color: #95a5a6;
        }
    </style>
    """

def convert_markdown_to_pdf(input_file, output_file):
    """将Markdown文件转换为PDF"""

    # 读取Markdown文件
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            markdown_content = f.read()
    except Exception as e:
        print(f"读取文件失败: {e}")
        return False

    # 配置Markdown扩展
    extensions = [
        'markdown.extensions.tables',
        'markdown.extensions.fenced_code',
        'markdown.extensions.codehilite',
        'markdown.extensions.toc',
        'markdown.extensions.nl2br',
        'markdown.extensions.sane_lists'
    ]

    extension_configs = {
        'markdown.extensions.codehilite': {
            'css_class': 'highlight',
            'use_pygments': False
        },
        'markdown.extensions.toc': {
            'permalink': True
        }
    }

    # 转换Markdown到HTML
    try:
        md = markdown.Markdown(extensions=extensions, extension_configs=extension_configs)
        html_content = md.convert(markdown_content)

        # 获取目录
        toc = getattr(md, 'toc', '')

    except Exception as e:
        print(f"Markdown转换失败: {e}")
        return False

    # 构建完整的HTML文档
    full_html = f"""
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>小磨推智能营销系统技术文档</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        {add_custom_css()}
    </head>
    <body>
        {html_content}
    </body>
    </html>
    """

    # 转换HTML到PDF
    try:
        # 配置WeasyPrint
        css = weasyprint.CSS(string=add_custom_css())

        # 创建PDF
        html_doc = weasyprint.HTML(string=full_html, base_url=os.path.dirname(input_file))
        html_doc.write_pdf(output_file, stylesheets=[css])

        print(f"PDF生成成功: {output_file}")
        return True

    except Exception as e:
        print(f"PDF生成失败: {e}")
        return False

def main():
    """主函数"""
    input_file = r"D:\xiaomotui\程序鉴别材料.md"
    output_file = r"D:\xiaomotui\程序鉴别材料_前30页.pdf"

    # 检查输入文件是否存在
    if not os.path.exists(input_file):
        print(f"输入文件不存在: {input_file}")
        return 1

    # 转换文件
    print(f"开始转换: {input_file} -> {output_file}")
    print("这可能需要几分钟时间...")

    if convert_markdown_to_pdf(input_file, output_file):
        print("转换完成!")
        print(f"输出文件: {output_file}")
        return 0
    else:
        print("转换失败!")
        return 1

if __name__ == "__main__":
    sys.exit(main())