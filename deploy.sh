#!/bin/bash
# ============================================
# GOGO DevOps Platform 部署脚本
# ============================================

set -e

# 配置
DEPLOY_DIR="/opt/docker/gogo-devops"
BACKUP_DIR="/opt/docker/gogo-devops-backup"
LOG_FILE="/var/log/gogo-devops-deploy.log"

# 颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a $LOG_FILE
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a $LOG_FILE
    exit 1
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1" | tee -a $LOG_FILE
}

# 检查root权限
if [ "$EUID" -ne 0 ]; then
    error "请使用root权限运行此脚本"
fi

# 创建目录
mkdir -p $DEPLOY_DIR
mkdir -p $BACKUP_DIR
mkdir -p /var/log/gogo-devops

log "开始部署 GOGO DevOps Platform..."

# 备份当前配置
if [ -d "$DEPLOY_DIR" ]; then
    warn "备份当前配置..."
    BACKUP_NAME="backup-$(date +%Y%m%d%H%M%S)"
    cp -r $DEPLOY_DIR $BACKUP_DIR/$BACKUP_NAME
    log "备份已保存到: $BACKUP_DIR/$BACKUP_NAME"
fi

# 下载最新代码
log "拉取最新代码..."
cd $DEPLOY_DIR
if [ -d ".git" ]; then
    git pull origin main
else
    warn "不是git仓库，跳过拉取"
fi

# 拉取Docker镜像
log "拉取Docker镜像..."
docker-compose pull

# 停止旧服务
log "停止旧服务..."
docker-compose down

# 启动新服务
log "启动服务..."
docker-compose up -d

# 等待服务启动
log "等待服务启动..."
sleep 30

# 健康检查
log "执行健康检查..."

check_service() {
    local name=$1
    local url=$2
    local max_attempts=30
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        if curl -sf $url > /dev/null 2>&1; then
            log "$name 健康检查通过 ✓"
            return 0
        fi
        attempt=$((attempt + 1))
        echo -n "."
        sleep 2
    done
    
    error "$name 健康检查失败 ✗"
}

echo ""
check_service "Prometheus" "http://localhost:9090/-/healthy"
check_service "Grafana" "http://localhost:3030/api/health"
check_service "AlertManager" "http://localhost:9093/-/healthy"
check_service "node-exporter" "http://localhost:9100"
check_service "cAdvisor" "http://localhost:8080/healthz"

# 显示服务状态
log "服务状态:"
docker-compose ps

# 记录部署信息
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deployed successfully" >> /var/log/gogo-deploy.log

log ""
log "=========================================="
log "🎉 GOGO DevOps Platform 部署完成!"
log "=========================================="
log ""
log "访问地址:"
log "  - Grafana:     http://39.108.11.214:3030"
log "  - Prometheus:  http://39.108.11.214:9090"
log "  - AlertManager: http://39.108.11.214:9093"
log "  - node-exporter: http://39.108.11.214:9100"
log "  - cAdvisor:     http://39.108.11.214:8080"
log ""
log "查看日志: docker-compose logs -f"
log "停止服务: docker-compose down"
log "=========================================="
