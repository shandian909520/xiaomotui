#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import markdown
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import cm
from reportlab.lib.colors import black, blue, gray
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak
from reportlab.platypus.tableofcontents import TableOfContents
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_JUSTIFY
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
import os
import re
import sys

def register_chinese_fonts():
    """注册中文字体"""
    try:
        # 尝试注册系统中文字体
        font_paths = [
            'C:/Windows/Fonts/simhei.ttf',      # 黑体
            'C:/Windows/Fonts/simsun.ttc',      # 宋体
            'C:/Windows/Fonts/msyh.ttc',        # 微软雅黑
            'C:/Windows/Fonts/msyhbd.ttc',      # 微软雅黑粗体
        ]

        fonts_registered = False
        for font_path in font_paths:
            if os.path.exists(font_path):
                try:
                    if 'hei' in font_path.lower():
                        pdfmetrics.registerFont(TTFont('SimHei', font_path))
                        print(f"注册字体: SimHei ({font_path})")
                        fonts_registered = True
                    elif 'simsun' in font_path.lower():
                        pdfmetrics.registerFont(TTFont('SimSun', font_path))
                        print(f"注册字体: SimSun ({font_path})")
                        fonts_registered = True
                    elif 'msyh' in font_path.lower():
                        if 'bd' in font_path.lower():
                            pdfmetrics.registerFont(TTFont('MSYHBD', font_path))
                            print(f"注册字体: MSYHBD ({font_path})")
                        else:
                            pdfmetrics.registerFont(TTFont('MSYH', font_path))
                            print(f"注册字体: MSYH ({font_path})")
                        fonts_registered = True
                except Exception as e:
                    print(f"注册字体失败 {font_path}: {e}")
                    continue

        return fonts_registered
    except Exception as e:
        print(f"字体注册失败: {e}")
        return False

class PDFGenerator:
    def __init__(self, input_file, output_file):
        self.input_file = input_file
        self.output_file = output_file
        self.styles = getSampleStyleSheet()
        self.custom_styles = {}
        self.setup_styles()

    def setup_styles(self):
        """设置文档样式"""
        # 注册中文字体
        chinese_fonts_available = register_chinese_fonts()

        if chinese_fonts_available:
            # 标题样式
            self.custom_styles['CustomTitle'] = ParagraphStyle(
                'CustomTitle',
                parent=self.styles['Title'],
                fontName='MSYHBD' if 'MSYHBD' in pdfmetrics.getRegisteredFontNames() else 'MSYH',
                fontSize=24,
                spaceAfter=30,
                alignment=TA_CENTER,
                textColor=black
            )

            self.custom_styles['CustomHeading1'] = ParagraphStyle(
                'CustomHeading1',
                parent=self.styles['Heading1'],
                fontName='MSYHBD' if 'MSYHBD' in pdfmetrics.getRegisteredFontNames() else 'MSYH',
                fontSize=18,
                spaceAfter=12,
                spaceBefore=20,
                textColor=black
            )

            self.custom_styles['CustomHeading2'] = ParagraphStyle(
                'CustomHeading2',
                parent=self.styles['Heading2'],
                fontName='MSYHBD' if 'MSYHBD' in pdfmetrics.getRegisteredFontNames() else 'MSYH',
                fontSize=14,
                spaceAfter=10,
                spaceBefore=15,
                textColor=black
            )

            self.custom_styles['CustomHeading3'] = ParagraphStyle(
                'CustomHeading3',
                parent=self.styles['Heading3'],
                fontName='MSYHBD' if 'MSYHBD' in pdfmetrics.getRegisteredFontNames() else 'MSYH',
                fontSize=12,
                spaceAfter=8,
                spaceBefore=12,
                textColor=black
            )

            # 正文样式
            self.custom_styles['CustomNormal'] = ParagraphStyle(
                'CustomNormal',
                parent=self.styles['Normal'],
                fontName='MSYH' if 'MSYH' in pdfmetrics.getRegisteredFontNames() else 'Helvetica',
                fontSize=10,
                spaceAfter=6,
                alignment=TA_JUSTIFY,
                leading=14
            )

            # 代码样式
            self.custom_styles['CustomCode'] = ParagraphStyle(
                'CustomCode',
                parent=self.styles['Normal'],
                fontName='Courier' if 'Courier' in pdfmetrics.getRegisteredFontNames() else 'Helvetica',
                fontSize=9,
                spaceAfter=6,
                spaceBefore=6,
                leftIndent=10,
                backgroundColor='#f5f5f5',
                borderPadding=5
            )
        else:
            # 使用默认字体的备用样式
            print("警告: 未找到中文字体，使用默认字体")
            self.custom_styles['CustomTitle'] = self.styles['Title']
            self.custom_styles['CustomHeading1'] = self.styles['Heading1']
            self.custom_styles['CustomHeading2'] = self.styles['Heading2']
            self.custom_styles['CustomHeading3'] = self.styles['Heading3']
            self.custom_styles['CustomNormal'] = self.styles['Normal']
            self.custom_styles['CustomCode'] = self.styles['Code']

    def parse_markdown(self, content):
        """解析Markdown内容"""
        extensions = [
            'markdown.extensions.tables',
            'markdown.extensions.fenced_code',
            'markdown.extensions.codehilite',
            'markdown.extensions.toc',
            'markdown.extensions.nl2br'
        ]

        md = markdown.Markdown(extensions=extensions)
        html_content = md.convert(content)
        return html_content, md.toc

    def html_to_platypus(self, html_content):
        """将HTML转换为ReportLAB Platypus元素"""
        elements = []

        # 简单的HTML解析
        lines = html_content.split('\n')
        current_tag = None
        code_block = False
        code_content = []

        for line in lines:
            line = line.strip()

            if not line:
                continue

            # 处理标题
            if line.startswith('<h1>'):
                text = re.sub(r'<[^>]+>', '', line)
                elements.append(Paragraph(text, self.custom_styles['CustomHeading1']))
                elements.append(Spacer(1, 0.3*cm))
            elif line.startswith('<h2>'):
                text = re.sub(r'<[^>]+>', '', line)
                elements.append(Paragraph(text, self.custom_styles['CustomHeading2']))
                elements.append(Spacer(1, 0.2*cm))
            elif line.startswith('<h3>'):
                text = re.sub(r'<[^>]+>', '', line)
                elements.append(Paragraph(text, self.custom_styles['CustomHeading3']))
                elements.append(Spacer(1, 0.1*cm))
            elif line.startswith('<h4>'):
                text = re.sub(r'<[^>]+>', '', line)
                elements.append(Paragraph(text, self.custom_styles['CustomHeading3']))
                elements.append(Spacer(1, 0.1*cm))
            # 处理段落
            elif line.startswith('<p>') and line.endswith('</p>'):
                text = re.sub(r'<[^>]+>', '', line)
                if text.strip():
                    elements.append(Paragraph(text, self.custom_styles['CustomNormal']))
                    elements.append(Spacer(1, 0.2*cm))
            # 处理列表
            elif line.startswith('<ul>') or line.startswith('</ul>') or line.startswith('<li>') or line.endswith('</li>'):
                if line.startswith('<li>'):
                    text = re.sub(r'<[^>]+>', '', line)
                    elements.append(Paragraph(f"• {text}", self.custom_styles['CustomNormal']))
                    elements.append(Spacer(1, 0.1*cm))
            # 处理代码块
            elif line.startswith('<pre>'):
                code_block = True
                code_content = []
            elif line.endswith('</pre>'):
                code_block = False
                if code_content:
                    code_text = '\n'.join(code_content)
                    # 清理HTML标签
                    code_text = re.sub(r'<[^>]+>', '', code_text)
                    elements.append(Paragraph(code_text, self.custom_styles['CustomCode']))
                    elements.append(Spacer(1, 0.2*cm))
                code_content = []
            elif code_block:
                code_content.append(line)
            # 处理表格（简单处理）
            elif line.startswith('<table>'):
                continue  # 跳过表格标签
            elif line.startswith('</table>'):
                continue  # 跳过表格标签
            elif line.startswith('<tr>') or line.startswith('</tr>'):
                continue  # 跳过表格行标签
            elif line.startswith('<td>') or line.startswith('<th>'):
                text = re.sub(r'<[^>]+>', '', line)
                if text.strip():
                    elements.append(Paragraph(text, self.custom_styles['CustomNormal']))
                    elements.append(Spacer(1, 0.1*cm))

        return elements

    def generate_pdf(self):
        """生成PDF文档"""
        try:
            # 读取Markdown文件
            with open(self.input_file, 'r', encoding='utf-8') as f:
                markdown_content = f.read()

            # 创建PDF文档
            doc = SimpleDocTemplate(
                self.output_file,
                pagesize=A4,
                rightMargin=2*cm,
                leftMargin=2*cm,
                topMargin=2*cm,
                bottomMargin=2*cm
            )

            # 转换Markdown为HTML
            html_content, toc = self.parse_markdown(markdown_content)

            # 创建内容元素
            elements = []

            # 添加封面页
            title_text = "小磨推智能营销系统技术文档"
            elements.append(Paragraph(title_text, self.custom_styles['CustomTitle']))
            elements.append(Spacer(1, 2*cm))

            elements.append(Paragraph("程序鉴别材料", self.custom_styles['CustomHeading1']))
            elements.append(Spacer(1, 1*cm))

            elements.append(Paragraph("版本号: V1.0", self.custom_styles['CustomNormal']))
            elements.append(Paragraph("编写日期: 2024年12月", self.custom_styles['CustomNormal']))
            elements.append(Paragraph("编写单位: 小磨推科技有限公司", self.custom_styles['CustomNormal']))
            elements.append(Spacer(1, 1*cm))

            elements.append(PageBreak())

            # 添加目录
            elements.append(Paragraph("目录", self.custom_styles['CustomHeading1']))
            elements.append(Spacer(1, 0.5*cm))

            # 生成简单目录
            toc_lines = markdown_content.split('\n')
            for line in toc_lines:
                if line.startswith('### '):
                    text = line[4:].strip()
                    elements.append(Paragraph(f"   • {text}", self.custom_styles['CustomNormal']))
                elif line.startswith('## '):
                    text = line[3:].strip()
                    elements.append(Paragraph(f"• {text}", self.custom_styles['CustomHeading3']))

            elements.append(PageBreak())

            # 添加正文内容
            content_elements = self.html_to_platypus(html_content)
            elements.extend(content_elements)

            # 生成PDF
            doc.build(elements)

            print(f"PDF生成成功: {self.output_file}")
            return True

        except Exception as e:
            print(f"PDF生成失败: {e}")
            import traceback
            traceback.print_exc()
            return False

def main():
    """主函数"""
    input_file = r"D:\xiaomotui\程序鉴别材料.md"
    output_file = r"D:\xiaomotui\程序鉴别材料_前30页.pdf"

    # 检查输入文件是否存在
    if not os.path.exists(input_file):
        print(f"输入文件不存在: {input_file}")
        return 1

    print(f"开始转换: {input_file} -> {output_file}")
    print("这可能需要几分钟时间...")

    # 创建PDF生成器并生成PDF
    generator = PDFGenerator(input_file, output_file)

    if generator.generate_pdf():
        print("转换完成!")
        print(f"输出文件: {output_file}")
        return 0
    else:
        print("转换失败!")
        return 1

if __name__ == "__main__":
    sys.exit(main())