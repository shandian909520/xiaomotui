# -*- coding: utf-8 -*-
"""
同步 admin 前端构建产物（admin/dist/）到线上 /public/admin/。

线上 FTP 根目录 = /www/wwwroot/pengpeng.moban8.top
pengh5.moban8.top 与 pengpeng.moban8.top 解析到同一站点 /public/ 目录
（nginx server_name 同源），所以 admin 端和 H5 端也部署在同一个 FTP 账号下。

用法:
    python deploy/sync_admin.py                # 增量同步 admin/dist/ -> /public/admin/
    python deploy/sync_admin.py --dry          # 演练，不实际上传
    python deploy/sync_admin.py --clean        # 先删除线上 /public/admin/assets_new, /assets 再上传
                                               #  （用于 dist hash 变了但旧文件残留导致 404）
    python deploy/sync_admin.py --verify       # 只校验字节数
"""
import argparse
import hashlib
import os
import sys
import io
from ftplib import FTP, error_perm

_HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(_HERE)
DIST = os.path.join(REPO, "admin", "dist")
REMOTE_ROOT = "/public/admin"

# --- FTP 凭据：复用 deploy/ftp.env ---
HOST, PORT, USER, PASS = "123.57.68.51", 21, None, None
_env = os.path.join(_HERE, "ftp.env")
if os.path.isfile(_env):
    with open(_env, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line.startswith("FTP_HOST="):
                h = line.split("=", 1)[1].strip().strip('"').replace("ftp://", "")
                if ":" in h:
                    HOST, p = h.split(":", 1)
                    PORT = int(p)
                else:
                    HOST = h
            elif line.startswith("FTP_USER="):
                c = line.split("=", 1)[1].strip().strip('"')
                if ":" in c:
                    USER, PASS = c.split(":", 1)


def ftp_connect():
    ftp = FTP()
    ftp.connect(HOST, PORT, timeout=60)
    ftp.login(USER, PASS)
    ftp.set_pasv(True)
    return ftp


def list_local_files(root):
    """返回相对 root 的 (relpath, abs_path, size)"""
    out = []
    for dp, dn, fn in os.walk(root):
        for f in fn:
            ap = os.path.join(dp, f)
            rp = os.path.relpath(ap, root).replace("\\", "/")
            out.append((rp, ap, os.path.getsize(ap)))
    return out


def remote_size(ftp, path):
    """FTP SIZE 命令拿字节数；失败返回 None"""
    try:
        return ftp.size(path)
    except error_perm:
        return None


def remote_fetch(ftp, path):
    """下载文件到 BytesIO,返回字节流。失败返回 None"""
    try:
        buf = io.BytesIO()
        ftp.retrbinary(f"RETR {path}", buf.write)
        return buf.getvalue()
    except error_perm:
        return None


def remote_md5(ftp, path):
    """Pure-FTPd 不一定支持 MD5 命令。改为下载文件本地算 hash（牺牲速度换可靠）。"""
    data = remote_fetch(ftp, path)
    if data is None:
        return None
    return hashlib.md5(data).hexdigest()


def local_md5(path):
    h = hashlib.md5()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def remote_nlst(ftp, path):
    """FTP NLST 拿文件列表；失败返回 []"""
    try:
        ftp.cwd(path)
        return [x for x in ftp.nlst() if x not in (".", "..")]
    except error_perm:
        return []


def ensure_remote_dir(ftp, dir_path):
    """递归 mkdir（FTP 没有真正递归，单步 try/cwd 跳过已存在）"""
    if not dir_path or dir_path == "/":
        return
    parts = [p for p in dir_path.split("/") if p]
    cumulative = ""
    for p in parts:
        cumulative += "/" + p
        try:
            ftp.cwd(cumulative)
        except error_perm:
            try:
                ftp.mkd(cumulative)
            except error_perm:
                pass  # 已存在


def upload_file(ftp, local_path, remote_path):
    """上传一个文件（STOR），二进制。"""
    with open(local_path, "rb") as f:
        ftp.storbinary(f"STOR {remote_path}", f)


def main():
    ap = argparse.ArgumentParser(description="同步 admin/dist 到线上 /public/admin/")
    ap.add_argument("--dry", action="store_true", help="演练,只看不传")
    ap.add_argument("--clean", action="store_true", help="先删除线上 assets_new/ assets/")
    ap.add_argument("--verify", action="store_true", help="只校验字节数")
    args = ap.parse_args()

    if not os.path.isdir(DIST):
        print(f"未找到 {DIST}, 请先构建 admin (npm run build)")
        return 1

    local = list_local_files(DIST)
    print(f"本地 {len(local)} 个文件, 字节数总和 {sum(s for _, _, s in local)}")
    if not local:
        return 0

    print(f"FTP {USER}@{HOST}:{PORT} 远程根 {REMOTE_ROOT}")
    if args.dry:
        for rp, _, sz in local[:10]:
            print(f"  [预览] {rp}  ({sz} bytes)")
        print("  ...")
        return 0

    ftp = ftp_connect()
    try:
        # 1. 可选 clean
        if args.clean:
            for sub in ("assets_new", "assets"):
                rdir = f"{REMOTE_ROOT}/{sub}"
                files = remote_nlst(ftp, rdir)
                for f in files:
                    try:
                        ftp.delete(f"{rdir}/{f}")
                    except error_perm:
                        pass
                print(f"已清 {rdir}/  ({len(files)} 个文件)")

        # 2. 校验 / 上传
        up_ok = up_skip = up_fail = 0
        mismatch = []
        for rp, ap, sz in local:
            remote = f"{REMOTE_ROOT}/{rp}"
            ensure_remote_dir(ftp, "/".join(remote.split("/")[:-1]))
            rs = remote_size(ftp, remote)
            if rs == sz:
                # 字节数一致但内容可能不同（hash 策略），二次 MD5 比对
                rm = remote_md5(ftp, remote)
                lm = local_md5(ap)
                if rm and rm == lm:
                    up_skip += 1
                    continue
                if rs is not None:
                    mismatch.append((rp, rs, sz, "size_equal_hash_diff"))
            else:
                rm = None
                if rs is not None:
                    mismatch.append((rp, rs, sz, "size_diff"))
            try:
                if args.verify:
                    up_fail += 1
                    continue
                upload_file(ftp, ap, remote)
                # 二次校验（必须 MD5 一致,字节数会受传输影响）
                rs2 = remote_size(ftp, remote)
                rm2 = remote_md5(ftp, remote)
                lm = local_md5(ap)
                if rm2 != lm:
                    print(f"  ✗ {rp}  上传后 hash 不一致: 本地 {lm[:8]} 线上 {rm2[:8] if rm2 else '?'}")
                    up_fail += 1
                else:
                    up_ok += 1
            except Exception as e:
                print(f"  ✗ {rp}  {e}")
                up_fail += 1

        print(f"\n上传完成: ok {up_ok} / 跳过 {up_skip} / 失败 {up_fail}")
        if mismatch and not args.verify:
            print(f"发现内容不一致 {len(mismatch)} 项:")
            for rp, rs, sz, why in mismatch[:10]:
                print(f"  [{why}] {rp}: 线上字节 {rs} 本地字节 {sz}")
        if up_fail > 0:
            return 1
        return 0
    finally:
        try:
            ftp.quit()
        except Exception:
            pass


if __name__ == "__main__":
    sys.exit(main())