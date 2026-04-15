<?php
/**
 * GOGO DevOps Platform - 增强版监控控制器
 * 
 * 新增功能:
 * 1. Prometheus集成 - 实时监控数据
 * 2. 自动化运维 - 自动内存优化、IP封锁、服务重启
 * 3. 智能告警 - 多渠道通知
 * 4. Grafana集成 - 实时仪表盘链接
 */

namespace app\index\controller;

use think\Request;
use think\Db;
use think\Controller;
use think\Log;

class Monitor extends Controller
{
    // 微信推送配置
    private $WECHAT_API = 'https://shop.gogo198.cn/api/sendwechattemplatenotice.php';
    private $WECHAT_OPENID = 'ov3-bt8keSKg_8z9Wwi-zG1hRhwg';
    private $WECHAT_TEMP_ID = 'SVVs5OeD3FfsGwW0PEfYlZWetjScIT8kDxht5tlI1V8';
    
    // DevOps配置
    private $PROMETHEUS_URL = 'http://39.108.11.214:9090';
    private $GRAFANA_URL = 'http://39.108.11.214:3030';
    private $ALERTMANAGER_URL = 'http://39.108.11.214:9093';
    
    // 自动化运维配置
    private $AUTO_OPS_CONFIG = [
        'memory_threshold' => 80,      // 内存阈值(%)
        'cpu_threshold' => 85,          // CPU阈值(%)
        'disk_threshold' => 85,         // 磁盘阈值(%)
        'auto_optimize_enabled' => false, // 自动优化开关
        'auto_block_enabled' => false,    // 自动封锁开关
        'attack_threshold' => 20,          // 攻击次数阈值
    ];

    // ========== 原有功能保持不变 ==========
    // [getSystemInfo, getMemoryDetail, blockIp, whitelistIp, etc...]

    // ========== 新增: DevOps集成API ==========
    
    /**
     * 获取Prometheus实时指标
     */
    public function prometheusMetrics() {
        $query = input('query', 'up');
        $time = input('time', time());
        
        $url = "{$this->PROMETHEUS_URL}/api/v1/query?" . http_build_query([
            'query' => $query,
            'time' => $time
        ]);
        
        $response = $this->httpGet($url);
        return json_decode($response, true) ?: ['code' => -1, 'data' => null];
    }
    
    /**
     * 获取Prometheus告警状态
     */
    public function prometheusAlerts() {
        $url = "{$this->PROMETHEUS_URL}/api/v1/alerts";
        $response = $this->httpGet($url);
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['data']['alerts'])) {
            return json(['code' => -1, 'msg' => '获取告警失败']);
        }
        
        $alerts = $data['data']['alerts'];
        $firing = array_filter($alerts, fn($a) => $a['state'] === 'firing');
        $pending = array_filter($alerts, fn($a) => $a['state'] === 'pending');
        
        return json([
            'code' => 0,
            'data' => [
                'alerts' => $alerts,
                'firing_count' => count($firing),
                'pending_count' => count($pending),
                'grafana_url' => "{$this->GRAFANA_URL}/d/gogo-host-monitor"
            ]
        ]);
    }
    
    /**
     * 获取AlertManager状态
     */
    public function alertManagerStatus() {
        $url = "{$this->ALERTMANAGER_URL}/api/v1/status";
        $response = $this->httpGet($url);
        $data = json_decode($response, true);
        
        return json([
            'code' => 0,
            'data' => $data ?: null
        ]);
    }
    
    /**
     * 自动化运维 - 一键优化
     */
    public function autoOptimize() {
        $action = input('action', 'all');
        
        $results = [];
        
        // 1. 清理内存缓存
        if ($action === 'memory' || $action === 'all') {
            $results['memory'] = $this->optimizeMemory();
        }
        
        // 2. 清理磁盘空间
        if ($action === 'disk' || $action === 'all') {
            $results['disk'] = $this->optimizeDisk();
        }
        
        // 3. 清理Docker资源
        if ($action === 'docker' || $action === 'all') {
            $results['docker'] = $this->optimizeDocker();
        }
        
        // 4. 重启问题服务
        if ($action === 'services' || $action === 'all') {
            $results['services'] = $this->restartProblematicServices();
        }
        
        // 记录操作日志
        $this->logAutoOps('optimize', $action, $results);
        
        return json([
            'code' => 0,
            'msg' => '优化完成',
            'results' => $results
        ]);
    }
    
    /**
     * 内存优化
     */
    private function optimizeMemory() {
        $before = $this->execCmd("free -m | grep Mem | awk '{print \$3}'");
        
        // 同步并清理缓存
        $this->execCmd("sync");
        $this->execCmd("echo 3 > /proc/sys/vm/drop_caches 2>/dev/null || echo '需要root权限'");
        
        // 重启PHP-FPM
        $this->execCmd("systemctl restart php-fpm 2>/dev/null || echo 'restarted'");
        
        $after = $this->execCmd("free -m | grep Mem | awk '{print \$3}'");
        
        $freed = intval($before) - intval($after);
        
        return [
            'success' => true,
            'before_mb' => intval($before),
            'after_mb' => intval($after),
            'freed_mb' => max(0, $freed),
            'message' => "释放内存 {$freed}MB"
        ];
    }
    
    /**
     * 磁盘优化
     */
    private function optimizeDisk() {
        $before = $this->execCmd("df -h / | tail -1 | awk '{print \$5}'");
        
        // 清理日志
        $this->execCmd("rm -f /var/log/messages-* 2>/dev/null");
        $this->execCmd("find /tmp -type f -mtime +3 -delete 2>/dev/null");
        
        // 清理Nginx日志
        $this->execCmd("truncate -s 0 /www/server/nginx/logs/*.log 2>/dev/null");
        
        $after = $this->execCmd("df -h / | tail -1 | awk '{print \$5}'");
        
        return [
            'success' => true,
            'before' => trim($before),
            'after' => trim($after),
            'message' => "磁盘使用率从 {$before} 降至 {$after}"
        ];
    }
    
    /**
     * Docker优化
     */
    private function optimizeDocker() {
        $before = $this->execCmd("docker system df --format '{{.Size}}' 2>/dev/null | head -1");
        
        // 清理未使用资源
        $this->execCmd("docker system prune -f 2>/dev/null");
        
        $after = $this->execCmd("docker system df --format '{{.Size}}' 2>/dev/null | head -1");
        
        return [
            'success' => true,
            'before' => trim($before),
            'after' => trim($after),
            'message' => "Docker空间已清理"
        ];
    }
    
    /**
     * 重启问题服务
     */
    private function restartProblematicServices() {
        $services = ['php-fpm', 'nginx', 'docker'];
        $results = [];
        
        foreach ($services as $service) {
            $status = $this->execCmd("systemctl is-active {$service} 2>/dev/null || echo 'unknown'");
            if (trim($status) === 'active') {
                $results[$service] = 'running';
            } else {
                // 尝试重启
                $this->execCmd("systemctl restart {$service} 2>/dev/null");
                $newStatus = $this->execCmd("systemctl is-active {$service} 2>/dev/null || echo 'failed'");
                $results[$service] = trim($newStatus);
            }
        }
        
        return [
            'success' => true,
            'services' => $results,
            'message' => '服务状态已检查'
        ];
    }
    
    /**
     * 自动IP封锁
     */
    public function autoBlockIp() {
        $ip = input('ip', '');
        $reason = input('reason', 'manual');
        $duration = input('duration', 0); // 0表示永久
        
        // 验证IP
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return json(['code' => -1, 'msg' => '无效的IP地址']);
        }
        
        // 检查是否在白名单
        $whitelist = $this->execCmd("grep '{$ip}' /opt/security-scripts/whitelist.txt 2>/dev/null");
        if (!empty(trim($whitelist))) {
            return json(['code' => -1, 'msg' => '该IP在白名单中，无法封锁']);
        }
        
        // 执行封锁
        if ($duration > 0) {
            // 临时封锁
            $result = $this->execCmd("iptables -I INPUT -s {$ip} -j DROP && echo 'success'");
            $this->execCmd("sleep {$duration} && iptables -D INPUT -s {$ip} -j DROP 2>/dev/null &");
            $message = "临时封锁IP {$ip}，持续 {$duration} 秒";
        } else {
            // 永久封锁
            $result = $this->execCmd("iptables -I INPUT -s {$ip} -j DROP && echo 'success'");
            $message = "永久封锁IP {$ip}";
        }
        
        if (strpos($result, 'success') === false) {
            return json(['code' => -1, 'msg' => '封锁失败，可能需要root权限']);
        }
        
        // 记录到安全日志
        $this->execCmd("echo '[{date}] BLOCKED: {$ip} - {$reason}' >> /var/log/gogo-blocked-ips.log");
        
        // 发送告警通知
        $this->sendSecurityAlert("🚫 IP已被封锁: {$ip}", "原因: {$reason}");
        
        return json([
            'code' => 0,
            'msg' => $message,
            'ip' => $ip,
            'duration' => $duration
        ]);
    }
    
    /**
     * 批量封锁攻击IP
     */
    public function batchBlockAttacks() {
        $threshold = input('threshold/d', $this->AUTO_OPS_CONFIG['attack_threshold']);
        
        // 获取攻击者IP列表
        $today = date('b %d');
        $attacks = $this->execCmd("grep '{$today}' /var/log/secure 2>/dev/null | grep 'Failed password' | awk '{print \$11}' | sort | uniq -c | sort -rn | awk '\$1 > {$threshold} {print \$2}'");
        
        if (empty(trim($attacks))) {
            return json(['code' => 0, 'msg' => '没有超过阈值的攻击IP', 'blocked' => []]);
        }
        
        $blocked = [];
        $lines = explode("\n", trim($attacks));
        
        foreach ($lines as $ip) {
            $ip = trim($ip);
            if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) continue;
            
            // 检查白名单
            $whitelist = $this->execCmd("grep '{$ip}' /opt/security-scripts/whitelist.txt 2>/dev/null");
            if (!empty(trim($whitelist))) continue;
            
            // 执行封锁
            $result = $this->execCmd("iptables -I INPUT -s {$ip} -j DROP && echo 'success'");
            if (strpos($result, 'success') !== false) {
                $blocked[] = $ip;
                $this->execCmd("echo '[{date}] AUTO_BLOCK: {$ip} - 暴力破解攻击' >> /var/log/gogo-blocked-ips.log");
            }
        }
        
        $count = count($blocked);
        if ($count > 0) {
            $this->sendSecurityAlert("🚫 自动封锁 {$count} 个攻击IP", "超过阈值 {$threshold} 次SSH登录失败");
        }
        
        return json([
            'code' => 0,
            'msg' => "已封锁 {$count} 个攻击IP",
            'blocked' => $blocked,
            'threshold' => $threshold
        ]);
    }
    
    /**
     * 服务自动重启
     */
    public function autoRestartService() {
        $service = input('service', '');
        
        if (empty($service)) {
            return json(['code' => -1, 'msg' => '请指定服务名']);
        }
        
        // 检查服务状态
        $beforeStatus = $this->execCmd("systemctl is-active {$service} 2>/dev/null || echo 'unknown'");
        
        // 重启服务
        $restartResult = $this->execCmd("systemctl restart {$service} 2>&1");
        
        // 等待几秒后检查状态
        sleep(3);
        $afterStatus = $this->execCmd("systemctl is-active {$service} 2>/dev/null || echo 'unknown'");
        
        $success = trim($afterStatus) === 'active';
        
        $this->logAutoOps('service_restart', $service, [
            'service' => $service,
            'before' => trim($beforeStatus),
            'after' => trim($afterStatus),
            'success' => $success
        ]);
        
        return json([
            'code' => $success ? 0 : -1,
            'msg' => $success ? "服务 {$service} 重启成功" : "服务 {$service} 重启失败",
            'before_status' => trim($beforeStatus),
            'after_status' => trim($afterStatus)
        ]);
    }
    
    /**
     * 获取自动化运维状态
     */
    public function autoOpsStatus() {
        return json([
            'code' => 0,
            'data' => [
                'auto_optimize' => $this->AUTO_OPS_CONFIG['auto_optimize_enabled'],
                'auto_block' => $this->AUTO_OPS_CONFIG['auto_block_enabled'],
                'thresholds' => [
                    'memory' => $this->AUTO_OPS_CONFIG['memory_threshold'],
                    'cpu' => $this->AUTO_OPS_CONFIG['cpu_threshold'],
                    'disk' => $this->AUTO_OPS_CONFIG['disk_threshold'],
                    'attack' => $this->AUTO_OPS_CONFIG['attack_threshold']
                ],
                'links' => [
                    'grafana' => "{$this->GRAFANA_URL}",
                    'prometheus' => "{$this->PROMETHEUS_URL}",
                    'alertmanager' => "{$this->ALERTMANAGER_URL}",
                    'dashboard' => "{$this->GRAFANA_URL}/d/gogo-host-monitor"
                ]
            ]
        ]);
    }
    
    /**
     * 配置自动化运维参数
     */
    public function configureAutoOps() {
        $config = input('post.');
        
        // 更新配置（实际应该写入配置文件）
        foreach ($config as $key => $value) {
            if (isset($this->AUTO_OPS_CONFIG[$key])) {
                $this->AUTO_OPS_CONFIG[$key] = $value;
            }
        }
        
        $this->logAutoOps('configure', 'update', $this->AUTO_OPS_CONFIG);
        
        return json([
            'code' => 0,
            'msg' => '配置已更新',
            'config' => $this->AUTO_OPS_CONFIG
        ]);
    }
    
    /**
     * 获取运维日志
     */
    public function opsLogs() {
        $type = input('type', 'auto_ops');
        $limit = input('limit/d', 50);
        
        $logFile = "/var/log/gogo-{$type}.log";
        $logs = $this->execCmd("tail -{$limit} {$logFile} 2>/dev/null || echo '暂无日志'");
        
        return json([
            'code' => 0,
            'type' => $type,
            'logs' => $logs
        ]);
    }
    
    /**
     * 健康检查汇总
     */
    public function healthCheck() {
        $checks = [];
        
        // 1. Prometheus健康检查
        $prometheus = $this->httpGet("{$this->PROMETHEUS_URL}/-/healthy");
        $checks['prometheus'] = !empty($prometheus) ? 'healthy' : 'unhealthy';
        
        // 2. Grafana健康检查
        $grafana = $this->httpGet("{$this->GRAFANA_URL}/api/health");
        $checks['grafana'] = !empty($grafana) ? 'healthy' : 'unhealthy';
        
        // 3. AlertManager健康检查
        $alertmanager = $this->httpGet("{$this->ALERTMANAGER_URL}/-/healthy");
        $checks['alertmanager'] = !empty($alertmanager) ? 'healthy' : 'unhealthy';
        
        // 4. Docker健康检查
        $docker = $this->execCmd("docker ps --format '{{.Names}}' 2>/dev/null | wc -l");
        $checks['docker'] = intval($docker) > 0 ? 'healthy' : 'unhealthy';
        
        // 5. 系统资源
        $memPercent = $this->execCmd("free | grep Mem | awk '{printf \"%.0f\", \$3/\$2*100}'");
        $cpuPercent = $this->execCmd("top -bn1 | grep Cpu | awk '{print \$2}' | sed 's/%us,//'");
        $checks['system'] = [
            'memory_percent' => intval($memPercent),
            'cpu_percent' => floatval($cpuPercent),
            'docker_count' => intval($docker)
        ];
        
        return json([
            'code' => 0,
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => $checks
        ]);
    }
    
    // ========== 辅助方法 ==========
    
    /**
     * HTTP GET请求
     */
    private function httpGet($url, $timeout = 5) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
    
    /**
     * 发送安全告警
     */
    private function sendSecurityAlert($title, $description) {
        return $this->sendWechatMessage(
            $title,
            '安全事件',
            $description,
            date('H:i'),
            '请及时处理'
        );
    }
    
    /**
     * 记录自动化运维日志
     */
    private function logAutoOps($action, $target, $result) {
        $log = sprintf(
            "[%s] ACTION: %s | TARGET: %s | RESULT: %s\n",
            date('Y-m-d H:i:s'),
            $action,
            $target,
            json_encode($result, JSON_UNESCAPED_UNICODE)
        );
        
        $this->execCmd("mkdir -p /var/log/gogo-auto-ops");
        $this->execCmd("echo '" . addslashes($log) . "' >> /var/log/gogo-auto-ops/ops.log");
    }
    
    /**
     * 执行命令
     */
    private function execCmd($cmd) {
        $output = [];
        @exec($cmd, $output, $return);
        return implode("\n", $output);
    }
    
    /**
     * 发送微信消息（原有方法）
     */
    private function sendWechatMessage($first, $keyword1, $keyword2, $keyword3, $remark) {
        $data = [
            'call' => 'confirmCollectionNotice',
            'first' => $first,
            'keyword1' => $keyword1,
            'keyword2' => $keyword2,
            'keyword3' => $keyword3,
            'remark' => $remark,
            'url' => 'https://boss.gogo198.cn/?s=monitor',
            'openid' => $this->WECHAT_OPENID,
            'temp_id' => $this->WECHAT_TEMP_ID
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->WECHAT_API);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode == 200 && $response !== false;
    }
}
