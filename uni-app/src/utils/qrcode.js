/**
 * 二维码生成工具
 * 基于 qrcodejs2 简化版
 */

class QRCode {
  constructor(canvasId, options = {}) {
    this.canvasId = canvasId
    this.options = {
      text: options.text || '',
      width: options.width || 200,
      height: options.height || 200,
      colorDark: options.colorDark || '#000000',
      colorLight: options.colorLight || '#ffffff',
      correctLevel: options.correctLevel || QRCode.CorrectLevel.H
    }

    this.generate()
  }

  generate() {
    const ctx = uni.createCanvasContext(this.canvasId)
    const { width, height, text, colorDark, colorLight } = this.options

    // 简化版：绘制一个带文本的占位符
    // 实际项目中应使用完整的 QRCode 库
    ctx.setFillStyle(colorLight)
    ctx.fillRect(0, 0, width, height)

    ctx.setFillStyle(colorDark)
    ctx.setFontSize(12)
    ctx.setTextAlign('center')
    ctx.fillText('二维码', width / 2, height / 2 - 10)
    ctx.fillText(text.substring(0, 20), width / 2, height / 2 + 10)

    ctx.draw()
  }
}

QRCode.CorrectLevel = {
  L: 1,
  M: 0,
  Q: 3,
  H: 2
}

export default QRCode
