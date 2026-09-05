# -*- coding: utf-8 -*-
"""
校验生产服务器上的 API 文件与本地是否一致（按字节数比对）。

背景：批量 FTP 上传偶发写入截断/清零（如 19161→2880、2851→0），
      进程被中断也会留下半截文件，因此每次部署后必须校验。

用法:
    python deploy/verify_upload.py            # 仅校验
    python deploy/verify_upload.py --fix      # 校验并自动重传不一致的文件
"""
import os
import subprocess
import sys
from ftplib import FTP

# --- FTP 凭据：优先读 deploy/ftp.env ---
_HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(_HERE)
API_DIR = os.path.join(REPO, "api")

HOST, PORT, USER, PASS = "123.57.68.51", 21, None, None
_env = os.path.join(_HERE, "ftp.env")
if os.path.isfile(_env):
    with open(_env, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line.startswith("FTP_HOST="):
                h = line.split("=", 1)[1].strip().strip('"')
                # ftp://host:port
                h = h.replace("ftp://", "").rstrip("/")
                HOST = h.split(":")[0]
                if ":" in h:
                    PORT = int(h.split(":")[1])
            elif line.startswith("FTP_USER="):
                cred = line.split("=", 1)[1].strip().strip('"')
                USER, PASS = cred.split(":", 1)


def get_file_list():
    out = subprocess.run(
        ["git", "ls-files", "--", "api/app", "api/config", "api/route",
         "api/database", "api/extend"],
        cwd=REPO, capture_output=True, text=True, encoding="utf-8"
    ).stdout.split("\n")
    res = []
    for f in out:
        f = f.strip()
        if not f:
            continue
        if f.startswith(("api/public/", "api/.env", "api/runtime/")):
            continue
        if f.endswith((".php", ".sql", ".json")):
            res.append(f)
    return res


def main():
    fix = "--fix" in sys.argv
    files = get_file_list()

    if not USER:
        print("错误: 未找到 FTP 凭据，请配置 deploy/ftp.env")
        return 2

    ftp = FTP()
    ftp.connect(HOST, PORT, timeout=30)
    ftp.login(USER, PASS)
    ftp.set_pasv(True)

    mismatch = []
    for rel in files:
        remote = rel[len("api/"):]
        local_path = os.path.join(REPO, rel.replace("/", os.sep))
        if not os.path.isfile(local_path):
            continue
        local_size = os.path.getsize(local_path)
        try:
            remote_size = ftp.size(remote)
        except Exception:
            remote_size = None
        if remote_size != local_size:
            mismatch.append((remote, local_size, remote_size))

    if not mismatch:
        print(f"OK: {len(files)} 个文件与服务器完全一致")
        ftp.quit()
        return 0

    print(f"发现 {len(mismatch)} 个不一致文件（共 {len(files)} 个）:")
    for remote, ls, rs in mismatch:
        print(f"  - {remote}: 本地 {ls} 字节 / 远端 {rs}")

    if not fix:
        ftp.quit()
        return 1

    print("\n重新上传...")
    ok, bad = 0, []
    for remote, ls, _ in mismatch:
        local_path = os.path.join(API_DIR, remote.replace("/", os.sep))
        try:
            with open(local_path, "rb") as fh:
                ftp.storbinary(f"STOR {remote}", fh)
            if ftp.size(remote) == ls:
                ok += 1
                print(f"  OK   {remote}")
            else:
                bad.append(remote)
                print(f"  FAIL {remote}")
        except Exception as e:
            bad.append(remote)
            print(f"  FAIL {remote}: {e}")

    print(f"\n修复完成: 成功 {ok} / 失败 {len(bad)}")
    ftp.quit()
    return 0 if not bad else 1


if __name__ == "__main__":
    sys.exit(main())
