# -*- coding: utf-8 -*-
"""
同步本地 api/ 到生产服务器（FTP）。

为什么用 ftplib 而不是 curl 批量上传：
  单条 curl 带多组 `-T local remote` 虽快，但会随机把部分文件写成截断/清零
  （实测多次出现 19161→2880、2851→0、13931→0）。改用 ftplib 复用同一条连接
  逐个 STOR，速度与批量相当，且不会损坏文件。

用法:
    python deploy/sync_api.py              # 全量同步
    python deploy/sync_api.py <git-ref>    # 只同步该 commit 之后的变更（增量）
"""
import os
import subprocess
import sys
import time
from ftplib import FTP, error_perm

_HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(_HERE)
API_DIR = os.path.join(REPO, "api")
STATE_FILE = os.path.join(REPO, ".deploy", "last_api_deploy")

# --- 读取 FTP 凭据 ---
HOST, PORT, USER, PASS = "123.57.68.51", 21, None, None
_env = os.path.join(_HERE, "ftp.env")
if os.path.isfile(_env):
    with open(_env, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line.startswith("FTP_HOST="):
                h = line.split("=", 1)[1].strip().strip('"').replace("ftp://", "").rstrip("/")
                HOST = h.split(":")[0]
                if ":" in h:
                    PORT = int(h.split(":")[1])
            elif line.startswith("FTP_USER="):
                c = line.split("=", 1)[1].strip().strip('"')
                USER, PASS = c.split(":", 1)


def collect(base_ref=None):
    """从 git 收集待上传文件（相对仓库根，如 api/app/...）"""
    if base_ref:
        out = subprocess.run(
            ["git", "diff", "--name-only", base_ref, "HEAD", "--", "api/"],
            cwd=REPO, capture_output=True, text=True, encoding="utf-8"
        ).stdout.split("\n")
        out += subprocess.run(
            ["git", "ls-files", "--others", "--exclude-standard", "--", "api/"],
            cwd=REPO, capture_output=True, text=True, encoding="utf-8"
        ).stdout.split("\n")
    else:
        out = subprocess.run(
            ["git", "ls-files", "--", "api/app", "api/config", "api/route",
             "api/database", "api/extend"],
            cwd=REPO, capture_output=True, text=True, encoding="utf-8"
        ).stdout.split("\n")

    res, seen = [], set()
    for f in out:
        f = f.strip()
        if not f or f in seen:
            continue
        if f.startswith(("api/public/", "api/.env", "api/runtime/")):
            continue
        if not f.endswith((".php", ".sql", ".json")):
            continue
        seen.add(f)
        res.append(f)
    return res


def ensure_dir(ftp, remote_dir):
    """逐层创建远程目录（已存在则忽略）"""
    if not remote_dir or remote_dir == "/":
        return
    parts = [p for p in remote_dir.split("/") if p]
    cur = ""
    for p in parts:
        cur = f"{cur}/{p}" if cur else p
        try:
            ftp.mkd(cur)
        except error_perm:
            pass  # 已存在
        except Exception:
            pass


def main():
    base_ref = sys.argv[1] if len(sys.argv) > 1 else None
    if not base_ref and os.path.isfile(STATE_FILE):
        with open(STATE_FILE, encoding="utf-8") as f:
            v = f.read().strip()
        if v:
            base_ref = v

    if not USER:
        print("错误: 未找到 FTP 凭据，请配置 deploy/ftp.env")
        return 2

    files = collect(base_ref)
    if not files:
        print("没有需要同步的文件")
        return 0

    mode = f"增量（基线 {base_ref[:8]}）" if base_ref else "全量"
    print(f"模式: {mode}    文件数: {len(files)}")

    ftp = FTP()
    ftp.connect(HOST, PORT, timeout=60)
    ftp.login(USER, PASS)
    ftp.set_pasv(True)

    t0 = time.time()
    uploaded = 0
    for i, rel in enumerate(files, 1):
        remote = rel[len("api/"):]
        local = os.path.join(API_DIR, remote.replace("/", os.sep))
        if not os.path.isfile(local):
            continue
        size = os.path.getsize(local)
        # 已一致则跳过
        try:
            if ftp.size(remote) == size:
                continue
        except Exception:
            pass
        ensure_dir(ftp, os.path.dirname(remote))
        with open(local, "rb") as fh:
            ftp.storbinary(f"STOR {remote}", fh)
        uploaded += 1
        if i % 50 == 0 or i == len(files):
            print(f"  进度 {i}/{len(files)}")

    print(f"上传完成: 实际传输 {uploaded} 个（跳过未变更 {len(files) - uploaded} 个），"
          f"耗时 {time.time() - t0:.1f}s")

    # ---- 校验 ----
    print("校验...")
    bad = []
    for rel in files:
        remote = rel[len("api/"):]
        local = os.path.join(API_DIR, remote.replace("/", os.sep))
        if not os.path.isfile(local):
            continue
        try:
            if ftp.size(remote) != os.path.getsize(local):
                bad.append(remote)
        except Exception:
            bad.append(remote)

    if bad:
        print(f"不一致 {len(bad)} 个，重传:")
        still = []
        for remote in bad:
            local = os.path.join(API_DIR, remote.replace("/", os.sep))
            with open(local, "rb") as fh:
                ftp.storbinary(f"STOR {remote}", fh)
            if ftp.size(remote) == os.path.getsize(local):
                print(f"  OK   {remote}")
            else:
                still.append(remote)
                print(f"  FAIL {remote}")
        if still:
            print(f"\n仍有 {len(still)} 个文件不一致，未记录同步基线")
            ftp.quit()
            return 1
    else:
        print(f"全部 {len(files)} 个文件校验一致")

    head = subprocess.run(["git", "rev-parse", "HEAD"], cwd=REPO,
                          capture_output=True, text=True).stdout.strip()
    os.makedirs(os.path.dirname(STATE_FILE), exist_ok=True)
    with open(STATE_FILE, "w", encoding="utf-8") as f:
        f.write(head + "\n")
    print(f"已记录同步基线 {head[:8]}")

    ftp.quit()
    return 0


if __name__ == "__main__":
    sys.exit(main())
