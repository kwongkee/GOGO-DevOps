#!/usr/bin/env python3
# ============================================
# AlertManager Webhook Receiver
# GOGO DevOps Platform
# 处理钉钉、微信、邮件通知及自动化运维执行
# ============================================

import json
import os
import requests
import subprocess
from flask import Flask, request, jsonify
from datetime import datetime

app = Flask(__name__)

# 配置文件
CONFIG_FILE = '/app/config.json'
CONFIG = {}

def load_config():
    """加载配置文件"""
    global CONFIG
    try:
        with open(CONFIG_FILE, 'r') as f:
            CONFIG = json.load(f)
    except Exception as e:
        print(f"配置文件加载失败: {e}")
        CONFIG = {
            "dingtalk_webhook": "",
            "wechat_webhook": "",
            "auto_exec_enabled": True
        }

def send_dingtalk(alert):
    """发送钉钉通知"""
    if not CONFIG.get('dingtalk_webhook'):
        print("钉钉Webhook未配置")
        return False
    
    try:
        # 判断告警级别
        severity = alert.get('labels', {}).get('severity', 'warning')
        emoji = "🔴" if severity == 'critical' else "🟡"
        
        # 构建消息
        message = {
            "msgtype": "markdown",
            "markdown": {
                "title": f"{emoji} GOGO监控告警",
                "text": f"""## {emoji} {alert['labels']['alertname']}

**告警级别**: {'🔴 紧急' if severity == 'critical' else '🟡 警告'}

**实例**: {alert.get('labels', {}).get('instance', 'N/A')}

**描述**: {alert['annotations'].get('description', '无描述')}

**时间**: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}

**操作建议**: {alert['annotations'].get('action', '请人工介入处理')}

> 点击查看详情: https://boss.gogo198.cn/?s=monitor
"""
            }
        }
        
        response = requests.post(CONFIG['dingtalk_webhook'], json=message, timeout=10)
        return response.status_code == 200
    except Exception as e:
        print(f"钉钉通知失败: {e}")
        return False

def send_wechat(alert):
    """发送微信模板消息"""
    if not CONFIG.get('wechat_webhook'):
        print("微信Webhook未配置")
        return False
    
    try:
        severity = alert.get('labels', {}).get('severity', 'warning')
        
        message = {
            "msgtype": "news",
            "news": {
                "articles": [
                    {
                        "title": f"{'🚨' if severity == 'critical' else '⚠️'} GOGO监控: {alert['labels']['alertname']}",
                        "description": alert['annotations'].get('description', '无描述'),
                        "url": "https://boss.gogo198.cn/?s=monitor",
                        "picurl": ""
                    }
                ]
            }
        }
        
        response = requests.post(CONFIG['wechat_webhook'], json=message, timeout=10)
        return response.status_code == 200
    except Exception as e:
        print(f"微信通知失败: {e}")
        return False

def execute_auto_action(alert):
    """执行自动化运维操作"""
    if not CONFIG.get('auto_exec_enabled'):
        print("自动执行已禁用")
        return
    
    auto_action = alert.get('labels', {}).get('auto_action')
    action_script = alert.get('annotations', {}).get('auto_action_script')
    
    if not auto_action or not action_script:
        return
    
    print(f"执行自动操作: {auto_action}")
    print(f"脚本: {action_script}")
    
    try:
        # 记录执行日志
        log_file = f"/var/log/gogo-auto-ops/{datetime.now().strftime('%Y%m%d')}.log"
        os.makedirs(os.path.dirname(log_file), exist_ok=True)
        
        with open(log_file, 'a') as f:
            f.write(f"[{datetime.now().isoformat()}] 执行: {auto_action}\n")
            f.write(f"告警: {alert['labels']['alertname']}\n")
            f.write(f"脚本: {action_script}\n")
        
        # 执行脚本
        result = subprocess.run(
            ['bash', '-c', action_script],
            capture_output=True,
            text=True,
            timeout=60
        )
        
        with open(log_file, 'a') as f:
            f.write(f"结果: {result.returncode}\n")
            f.write(f"输出: {result.stdout}\n")
            if result.stderr:
                f.write(f"错误: {result.stderr}\n")
            f.write("---\n")
        
        print(f"自动操作执行完成: {result.returncode}")
    except subprocess.TimeoutExpired:
        print("自动操作执行超时")
    except Exception as e:
        print(f"自动操作执行失败: {e}")

@app.route('/webhook/gogo-monitor', methods=['POST'])
def webhook_gogo_monitor():
    """GOGO Monitor webhook - 接收AlertManager告警"""
    try:
        data = request.json
        
        if 'alerts' not in data:
            return jsonify({"status": "error", "message": "无效的告警格式"})
        
        processed = 0
        for alert in data['alerts']:
            if alert.get('status') == 'resolved':
                print(f"告警恢复: {alert['labels']['alertname']}")
                continue
            
            # 发送到GOGO Monitor
            print(f"处理告警: {alert['labels']['alertname']} - {alert['annotations'].get('summary', '')}")
            processed += 1
            
            # 执行自动操作
            execute_auto_action(alert)
        
        return jsonify({
            "status": "success",
            "processed": processed
        })
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

@app.route('/webhook/dingtalk', methods=['POST'])
def webhook_dingtalk():
    """钉钉webhook"""
    try:
        data = request.json
        for alert in data.get('alerts', []):
            if alert.get('status') != 'firing':
                continue
            send_dingtalk(alert)
        return jsonify({"status": "success"})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

@app.route('/webhook/wechat', methods=['POST'])
def webhook_wechat():
    """微信webhook"""
    try:
        data = request.json
        for alert in data.get('alerts', []):
            if alert.get('status') != 'firing':
                continue
            send_wechat(alert)
        return jsonify({"status": "success"})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

@app.route('/webhook/auto-execute', methods=['POST'])
def webhook_auto_execute():
    """自动化运维执行webhook"""
    try:
        data = request.json
        for alert in data.get('alerts', []):
            if alert.get('status') == 'resolved':
                continue
            execute_auto_action(alert)
        return jsonify({"status": "success"})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

@app.route('/health', methods=['GET'])
def health():
    """健康检查"""
    return jsonify({"status": "healthy", "timestamp": datetime.now().isoformat()})

if __name__ == '__main__':
    load_config()
    app.run(host='0.0.0.0', port=5000, debug=False)
