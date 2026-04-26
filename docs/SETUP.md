# 新機開發環境設定

適用：新增一台 Mac（筆電/桌機）加入 hswork 開發環境。

## 一、必裝

```bash
# Homebrew
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Node.js v22
brew install node@22

# Claude Code
npm install -g @anthropic-ai/claude-code

# 確認
node -v   # 應為 v22.x
claude --version
```

## 二、Clone 專案與記憶

```bash
# 主程式
git clone https://github.com/mech8859/hswork.git ~/hswork

# Claude 記憶（兩台機器透過這個 repo 同步對話脈絡）
MEMORY_DIR=~/.claude/projects/-Users-$(whoami)-hswork/memory
mkdir -p "$(dirname "$MEMORY_DIR")"
git clone https://github.com/mech8859/claude-memory.git "$MEMORY_DIR"
```

> GitHub 私人 repo，第一次 push/pull 會問帳密。Token 在記憶 `project_dual_mac_setup.md`。

## 三、同步腳本

把 Mac Mini 上的 `~/sync-memory.sh` 複製過來。最快方法（在公司網路內）：

```bash
scp ruantikaifa@192.168.2.185:~/sync-memory.sh ~/sync-memory.sh
chmod +x ~/sync-memory.sh
```

外地（沒 SSH 通）就手動建立：

```bash
cat > ~/sync-memory.sh << 'EOF'
#!/bin/bash
MEMORY_DIR="$HOME/.claude/projects/-Users-$(whoami)-hswork/memory"
[ -d "$MEMORY_DIR" ] || { echo "找不到記憶資料夾 $MEMORY_DIR"; exit 1; }
cd "$MEMORY_DIR" || exit 1
case "${1:-sync}" in
    pull|p) git pull --rebase origin main ;;
    push|u) git add -A; git diff --cached --quiet || { git commit -m "$(date '+%Y-%m-%d %H:%M') from $(hostname -s)"; git push origin main; } ;;
    sync|s|"") git add -A; git diff --cached --quiet || git commit -m "$(date '+%Y-%m-%d %H:%M') from $(hostname -s)"; git pull --rebase origin main; git push origin main; echo "✅ 同步完成" ;;
    status|st) git status ;;
    log|l) git log --oneline -10 ;;
    *) echo "用法: sync-memory [sync|pull|push|status|log]" ;;
esac
EOF
chmod +x ~/sync-memory.sh
```

## 四、每日工作流程

```bash
# 開始：拉最新程式 + 拉最新記憶
cd ~/hswork && git pull
~/sync-memory.sh pull

# 在 hswork 目錄啟動 Claude
claude

# 結束：推程式 + 推記憶
cd ~/hswork && git push
~/sync-memory.sh push
```

## 五、選配

### SSH 進 Mac Mini（呼叫 AI 辨識服務、傳檔）

```bash
ssh-keygen -t ed25519 -C "$(whoami)@$(hostname -s)"
ssh-copy-id ruantikaifa@192.168.2.185   # 公司網路
# 家中網路 IP 為 192.168.2.138
```

### FTP 部署（直接上傳到主機）

```bash
brew install lftp
```

部署指令見 `~/.claude/CLAUDE.md` 或 `DEPLOY.md`。

### 本機 PHP 測試環境

不一定需要，目前部署模式是 FTP 直推主機。如要本機跑：

```bash
brew install php@7.2 mysql
```

## 六、外地常見問題

| 狀況 | 原因 | 解法 |
|---|---|---|
| 連不到 192.168.2.185 | 不在公司內網 | 用 VPN，或外地不呼叫 AI 服務 |
| Claude 不知道專案脈絡 | 記憶沒同步 | `~/sync-memory.sh pull` |
| `git pull` 衝突 | 兩台同時改 | 開發前一定先 pull |
| FTP 連不上 | 主機 IP 變動或網路擋 | 換網路或用手機熱點 |

## 七、給筆電 Claude 的初次提示

開好環境後，第一次啟動 Claude Code 可以說：

> 我是新環境，已經 clone 好 hswork 和 claude-memory。請先讀 MEMORY.md 了解專案，然後幫我確認環境設定完整。
