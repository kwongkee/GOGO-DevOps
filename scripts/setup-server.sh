#!/bin/bash
# ============================================
# 服务器初始化脚本
# GOGO DevOps Platform
# ============================================

set -e

echo "开始服务器初始化..."

# 1. 安装必要工具
echo "1. 安装Docker和Docker Compose..."
if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
fi

if ! command -v docker-compose &> /dev/null; then
    curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
fi

# 2. 创建部署目录
echo "2. 创建部署目录..."
mkdir -p /opt/docker/gogo-devops
mkdir -p /opt/security-scripts
mkdir -p /var/log/gogo-devops

# 3. 防火墙配置
echo "3. 配置防火墙..."
systemctl stop firewalld 2>/dev/null || true
systemctl disable firewalld 2>/dev/null || true

# iptables规则
cat > /etc/sysconfig/iptables << 'EOF'
*filter
:INPUT ACCEPT [0:0]
:FORWARD ACCEPT [0:0]
:OUTPUT ACCEPT [0:0]
-A INPUT -i lo -j ACCEPT
-A INPUT -m state --state RELATED,ESTABLISHED -j ACCEPT
-A INPUT -p tcp --dport 22 -j ACCEPT
-A INPUT -p tcp --dport 80 -j ACCEPT
-A INPUT -p tcp --dport 443 -j ACCEPT
-A INPUT -p tcp --dport 9000 -j ACCEPT
-A INPUT -p tcp --dport 9090 -s 10.0.0.0/8 -j ACCEPT
-A INPUT -p tcp --dport 3030 -s 10.0.0.0/8 -j ACCEPT
-A INPUT -p icmp --icmp-type echo-request -j ACCEPT
-A INPUT -j DROP
COMMIT
EOF

# 4. 时区配置
echo "4. 配置时区..."
ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime

# 5. 安装常用工具
echo "5. 安装常用工具..."
yum install -y curl wget vim git jq net-tools bind-utils

# 6. Docker优化
echo "6. Docker系统配置..."
cat > /etc/docker/daemon.json << 'EOF'
{
    "log-driver": "json-file",
    "log-opts": {
        "max-size": "100m",
        "max-file": "3"
    },
    "storage-driver": "overlay2",
    "default-ulimits": {
        "nofile": {
            "Name": "nofile",
            "Hard": 65536,
            "Soft": 65536
        }
    }
}
EOF

systemctl daemon-reload
systemctl restart docker

# 7. 创建白名单文件
echo "7. 创建安全配置文件..."
cat > /opt/security-scripts/whitelist.txt << 'EOF'
# 白名单IP，每行一个
127.0.0.1
::1
EOF

# 8. 创建自动封锁脚本
cat > /opt/security-scripts/auto-block.sh << 'EOF'
#!/bin/bash
# 自动封锁攻击IP脚本

THRESHOLD=20
WHITELIST="/opt/security-scripts/whitelist.txt"
LOG_FILE="/var/log/gogo-blocked-ips.log"

# 获取今日攻击者IP
TODAY=$(date +%b\ %d)
ATTACK_IPS=$(grep "$TODAY" /var/log/secure 2>/dev/null | grep "Failed password" | awk '{print $11}' | sort | uniq -c | awk "\$1 > $THRESHOLD {print \$2}")

for IP in $ATTACK_IPS; do
    # 检查是否在白名单
    if grep -q "^$IP$" $WHITELIST 2>/dev/null; then
        echo "跳过白名单IP: $IP"
        continue
    fi
    
    # 检查是否已封锁
    if ! iptables -C INPUT -s $IP -j DROP 2>/dev/null; then
        iptables -I INPUT -s $IP -j DROP
        echo "[$(date)] AUTO_BLOCK: $IP" >> $LOG_FILE
        echo "已封锁: $IP"
    fi
done
EOF

chmod +x /opt/security-scripts/auto-block.sh

# 9. 创建自动内存优化脚本
cat > /opt/security-scripts/auto-memory-opt.sh << 'EOF'
#!/bin/bash
# 自动内存优化脚本

THRESHOLD=85

MEM_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100}')

if [ "$MEM_USAGE" -gt "$THRESHOLD" ]; then
    echo "[$(date)] 内存使用率: $MEM_USAGE%，开始优化..."
    
    # 同步并清理缓存
    sync
    echo 3 > /proc/sys/vm/drop_caches
    
    # 重启PHP-FPM
    systemctl restart php-fpm 2>/dev/null || true
    
    # 清理Docker
    docker system prune -f 2>/dev/null || true
    
    echo "[$(date)] 优化完成，当前内存: $(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100}')%"
fi
EOF

chmod +x /opt/security-scripts/auto-memory-opt.sh

# 10. 添加定时任务
echo "10. 配置定时任务..."
(crontab -l 2>/dev/null | grep -v "gogo-devops"; cat << 'EOF'
# GOGO DevOps 定时任务
0 */6 * * * /opt/security-scripts/auto-memory-opt.sh >> /var/log/gogo-devops/memory-opt.log 2>&1
*/10 * * * * /opt/security-scripts/auto-block.sh >> /var/log/gogo-devops/auto-block.log 2>&1
EOF
) | crontab -

echo ""
echo "=========================================="
echo "✅ 服务器初始化完成!"
echo "=========================================="
echo ""
echo "下一步:"
echo "  1. 上传 gogo-monitor-devops 到服务器"
echo "  2. 运行 deploy.sh 进行部署"
echo ""
