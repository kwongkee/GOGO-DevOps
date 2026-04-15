# GOGO DevOps Platform

企业级DevOps监控平台，集成Prometheus + Grafana + AlertManager，实现实时监控、智能告警和自动化运维。

## 🎯 核心功能

| 功能 | 说明 |
|------|------|
| 📊 **实时监控仪表盘** | Grafana可视化展示CPU、内存、磁盘、网络等指标 |
| 🚀 **自动扩缩容** | 根据负载自动调整容器资源配置 |
| 🔔 **智能告警** | 多渠道通知（钉钉、微信、邮件） |
| ⚡ **自动化运维** | 自动内存优化、IP封锁、服务重启 |

## 🏗️ 技术架构

```
┌─────────────────────────────────────────────────────────────┐
│                        GOGO DevOps Platform                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────┐   ┌─────────────┐   ┌─────────────────┐   │
│  │ Prometheus  │◄──│   Alert     │◄──│   GOGO-Monitor  │   │
│  │ (指标采集)   │   │  Manager    │   │    (PHP面板)    │   │
│  └──────┬──────┘   └──────┬──────┘   └────────┬────────┘   │
│         │                 │                     │             │
│         ▼                 ▼                     ▼             │
│  ┌─────────────┐   ┌─────────────┐   ┌─────────────────┐   │
│  │  Grafana   │   │   Webhook   │   │  自动化执行器    │   │
│  │ (可视化)    │   │  (通知)     │   │  (自动运维)      │   │
│  └─────────────┘   └──────┬──────┘   └────────┬────────┘   │
│                            │                     │             │
│                            ▼                     │             │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                  服务器: 39.108.11.214                   │   │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────────┐   │   │
│  │  │node-exporter│  │  cadvisor │  │ blackbox-exporter│ │   │
│  │  │ (主机监控)  │  │ (容器监控) │  │   (健康检查)    │   │   │
│  │  └────────────┘  └────────────┘  └────────────────┘   │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

## 📦 包含组件

| 组件 | 版本 | 端口 | 说明 |
|------|------|------|------|
| **Prometheus** | v2.45.0 | 9090 | 指标采集和时序数据库 |
| **Grafana** | 10.0.0 | 3030 | 可视化仪表盘 |
| **AlertManager** | v0.26.0 | 9093 | 告警管理 |
| **node-exporter** | v1.6.1 | 9100 | 主机指标采集 |
| **cadvisor** | v0.47.2 | 8080 | 容器指标采集 |
| **blackbox-exporter** | v0.23.0 | 9115 | 端点健康检查 |
| **alert-webhook** | Python 3.9 | 9094 | 告警通知处理器 |

## 🚀 快速开始

### 1. 本地启动

```bash
# 克隆项目
git clone https://github.com/kwongkee/GOGO-DevOps.git
cd GOGO-DevOps

# 启动所有服务
docker-compose up -d

# 查看服务状态
docker-compose ps

# 查看日志
docker-compose logs -f
```

### 2. 访问服务

| 服务 | 地址 | 默认账号 |
|------|------|---------|
| Grafana | http://39.108.11.214:3030 | admin / Gogo@DevOps2026 |
| Prometheus | http://39.108.11.214:9090 | - |
| AlertManager | http://39.108.11.214:9093 | - |
| node-exporter | http://39.108.11.214:9100 | - |
| cAdvisor | http://39.108.11.214:8080 | - |

### 3. 配置告警通知

编辑 `alertmanager/webhook_config.json`：

```json
{
  "dingtalk_webhook": "https://oapi.dingtalk.com/robot/send?access_token=YOUR_TOKEN",
  "wechat_webhook": "https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=YOUR_KEY",
  "auto_exec_enabled": true
}
```

## 🔧 API接口

### Prometheus指标

```bash
# 获取CPU使用率
GET /api/v1/query?query=node_cpu_usage

# 获取告警列表
GET /prometheus/alerts
```

### 自动化运维

```bash
# 一键优化
POST /api/monitor/api?action=autoOptimize

# 自动封锁攻击IP
POST /api/monitor/api?action=batchBlockAttacks

# 重启服务
POST /api/monitor/api?action=autoRestartService&service=php-fpm
```

### GOGO-Monitor API

| 接口 | 说明 |
|------|------|
| `/api/monitor/api?action=check` | 获取系统概览 |
| `/api/monitor/api?action=security` | 安全事件 |
| `/api/monitor/api?action=block_ip` | 封禁IP |
| `/api/monitor/api?action=prometheusAlerts` | Prometheus告警 |
| `/api/monitor/api?action=healthCheck` | 健康检查 |

## 📊 告警规则

| 告警名称 | 条件 | 严重程度 |
|----------|------|---------|
| HighCPU | CPU > 80% | 警告 |
| CriticalCPU | CPU > 95% | 紧急 |
| HighMemory | 内存 > 85% | 警告 |
| CriticalMemory | 内存 > 95% | 紧急 |
| HighDiskUsage | 磁盘 > 85% | 警告 |
| CriticalDiskUsage | 磁盘 > 95% | 紧急 |
| ContainerDown | 容器停止 | 紧急 |

## 🔄 CI/CD流程

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   代码提交   │───►│  代码检查    │───►│  单元测试   │
└─────────────┘    └─────────────┘    └──────┬──────┘
                                            │
┌─────────────┐    ┌─────────────┐    ┌──────▼──────┐
│  生产部署   │◄───│  SonarQube  │◄───│  Docker构建  │
└─────────────┘    └─────────────┘    └─────────────┘
       │
       ▼
┌─────────────┐
│  通知推送   │
└─────────────┘
```

## 📁 项目结构

```
gogo-monitor-devops/
├── docker-compose.yml          # Docker编排配置
├── prometheus/
│   ├── prometheus.yml          # Prometheus配置
│   └── rules/
│       └── alerts.yml          # 告警规则
├── grafana/
│   ├── provisioning/
│   │   ├── datasources/        # 数据源配置
│   │   └── dashboards/         # 仪表盘配置
│   └── dashboards/
│       └── host-monitor.json   # 主机监控仪表盘
├── alertmanager/
│   ├── alertmanager.yml        # 告警管理器配置
│   ├── webhook.py              # Webhook处理器
│   └── webhook_config.json     # Webhook配置
├── blackbox/
│   └── blackbox.yml            # 端点健康检查配置
├── application/
│   └── index/
│       └── controller/
│           └── Monitor.php     # GOGO-Monitor控制器
└── .github/
    └── workflows/
        └── devops-ci.yml       # CI/CD流程
```

## 🔐 安全配置

### 防火墙规则

```bash
# 只开放必要端口
iptables -A INPUT -p tcp --dport 22 -j ACCEPT      # SSH
iptables -A INPUT -p tcp --dport 80 -j ACCEPT      # HTTP
iptables -A INPUT -p tcp --dport 443 -j ACCEPT     # HTTPS
iptables -A INPUT -p tcp --dport 9090 -s 10.0.0.0/8 -j ACCEPT   # Prometheus内网
iptables -A INPUT -p tcp --dport 3030 -s 10.0.0.0/8 -j ACCEPT   # Grafana内网
```

### 白名单管理

将可信IP添加到白名单：

```bash
echo "1.2.3.4" >> /opt/security-scripts/whitelist.txt
```

## 📈 性能基准

| 指标 | 数值 |
|------|------|
| Prometheus内存占用 | ~500MB |
| Grafana内存占用 | ~200MB |
| node-exporter内存占用 | ~20MB |
| cadvisor内存占用 | ~150MB |
| **总内存占用** | **~1GB** |

## 🆘 故障排查

### 服务启动失败

```bash
# 查看日志
docker-compose logs -f [service-name]

# 重启单个服务
docker-compose restart [service-name]

# 完全重建
docker-compose down -v
docker-compose up -d
```

### Prometheus无法采集数据

```bash
# 检查target状态
curl http://localhost:9090/api/v1/targets

# 检查exporter是否运行
curl http://localhost:9100/metrics
```

### 告警未触发

```bash
# 检查AlertManager状态
curl http://localhost:9093/api/v1/status

# 检查告警规则
curl http://localhost:9090/api/v1/rules
```

## 📞 联系方式

- **技术支持**: 198@gogo198.net
- **监控系统**: https://boss.gogo198.cn/?s=monitor

## 📄 许可证

MIT License - © 2024 GOGO
