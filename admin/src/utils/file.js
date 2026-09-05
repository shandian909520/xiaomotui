/**
 * 通用文件下载工具
 */

/**
 * 从 URL 中提取文件名
 */
function getFilenameFromUrl(url) {
  try {
    const parts = new URL(url).pathname.split('/')
    return parts[parts.length - 1] || 'download'
  } catch {
    return 'download'
  }
}

/**
 * 下载文件（支持跨域，自动降级）
 * @param {string} url - 文件 URL
 * @param {string} [filename] - 下载后的文件名，不传则从 URL 提取
 * @returns {Promise<boolean>} 是否通过 blob 方式下载成功
 */
export async function downloadFile(url, filename) {
  if (!url) {
    return false
  }
  try {
    const response = await fetch(url, { mode: 'cors' })
    if (!response.ok) throw new Error('下载失败')
    const blob = await response.blob()
    const blobUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = filename || getFilenameFromUrl(url)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(blobUrl)
    return true
  } catch {
    // 降级：直接打开链接
    window.open(url, '_blank')
    return false
  }
}

/**
 * 通过 canvas dataURL 下载图片
 * @param {string} dataUrl - canvas.toDataURL() 生成的 base64 字符串
 * @param {string} filename - 下载后的文件名
 */
export function downloadDataUrl(dataUrl, filename) {
  const link = document.createElement('a')
  link.href = dataUrl
  link.download = filename || 'download.png'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}
