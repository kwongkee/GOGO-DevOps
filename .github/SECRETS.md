# GitHub Secrets 配置指南

## 必需的Secrets

在GitHub仓库设置中添加以下Secrets:

### 1. 服务器访问

| Secret名称 | 说明 | 示例 |
|-----------|------|------|
| `SERVER_HOST` | 服务器IP | `39.108.11.214` |
| `SERVER_SSH_KEY` | SSH私钥 | `-----BEGIN RSA PRIVATE KEY-----...` |

**生成SSH密钥并配置:**

```bash
# 1. 在本地生成密钥
ssh-keygen -t rsa -b 4096 -C "deploy@gogo198.cn"

# 2. 将公钥添加到服务器
ssh-copy-id -i ~/.ssh/id_rsa.pub root@39.108.11.214

# 3. 将私钥内容添加到GitHub Secrets
cat ~/.ssh/id_rsa
```

### 2. SonarQube (可选)

| Secret名称 | 说明 |
|-----------|------|
| `SONAR_TOKEN` | SonarQube用户令牌 |

### 3. 通知渠道 (可选)

| Secret名称 | 说明 |
|-----------|------|
| `WECHAT_WEBHOOK` | 企业微信Webhook地址 |

## 配置步骤

### 1. 添加SERVER_HOST

1. 打开 https://github.com/kwongkee/GOGO-DevOps/settings/secrets/actions
2. 点击 "New repository secret"
3. Name: `SERVER_HOST`
4. Value: `39.108.11.214`
5. 点击 "Add secret"

### 2. 添加SERVER_SSH_KEY

1. 本地执行:
```bash
# 读取私钥
cat ~/.ssh/id_rsa
```

2. 复制输出内容

3. GitHub添加Secret:
   - Name: `SERVER_SSH_KEY`
   - Value: (粘贴私钥内容)

### 3. 添加SONAR_TOKEN (可选)

1. 登录 http://39.108.11.214:9001
2. 进入: Administration → Security → Users
3. 点击右上角 Tokens
4. Generate new token: `gogo-devops-github-actions`
5. 复制token，添加到GitHub Secrets

## 验证配置

推送代码到develop分支，CI/CD会自动执行测试环境部署。

查看Actions日志: https://github.com/kwongkee/GOGO-DevOps/actions
