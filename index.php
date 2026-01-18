<?php
// 首页 - 显示服务器状态

// 检查是否已安装
if (!file_exists('installed.lock')) {
    header('Location: install.php');
    exit;
}

// 引入配置文件
require_once 'config.php';
require_once 'db.php';
require_once 'api.php';

// 引入Chart.js用于图表展示
$chartjs_script = '<script src="dist/chart.umd.min.js"></script>';

// 连接数据库
$db = new Database();

// 获取所有服务器（使用默认排序，即按权重降序）
$servers = $db->getAllServers();

// 创建API实例
$minecraft_api = new MinecraftAPI();

// 优化：异步加载服务器状态，不阻塞页面渲染
// 服务器状态将通过 JavaScript 异步加载
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?></title>
    <?= $chartjs_script ?>
    <script>
        (function() {
            const MAX_RETRIES = 3;
            let retryCount = 0;
            
            function checkChartJs() {
                console.log('检查 Chart.js 加载状态 (尝试 ' + (retryCount + 1) + '/' + MAX_RETRIES + '):', typeof Chart);
                
                if (typeof Chart !== 'undefined') {
                    console.log('✓ Chart.js 已成功加载');
                    console.log('Chart.js 版本:', Chart.version);
                    return true;
                }
                return false;
            }
            
            function loadChartJs() {
                return new Promise((resolve, reject) => {
                    if (checkChartJs()) {
                        resolve();
                        return;
                    }
                    
                    console.log('Chart.js 未加载，尝试手动加载...');
                    const script = document.createElement('script');
                    script.src = 'dist/chart.umd.min.js';
                    script.async = true;
                    
                    script.onload = function() {
                        console.log('✓ Chart.js 手动加载成功');
                        if (checkChartJs()) {
                            resolve();
                        } else {
                            reject(new Error('Chart.js 加载后仍未定义'));
                        }
                    };
                    
                    script.onerror = function() {
                        console.error('✗ Chart.js 手动加载失败');
                        reject(new Error('无法加载 Chart.js 文件'));
                    };
                    
                    document.head.appendChild(script);
                });
            }
            
            window.addEventListener('DOMContentLoaded', async function() {
                try {
                    await loadChartJs();
                } catch (error) {
                    console.error('Chart.js 加载失败:', error);
                    retryCount++;
                    if (retryCount < MAX_RETRIES) {
                        console.log('重试加载 Chart.js...');
                        setTimeout(loadChartJs, 1000);
                    } else {
                        console.error('达到最大重试次数，Chart.js 加载失败');
                    }
                }
            });
            
            window.addEventListener('load', function() {
                setTimeout(() => {
                    if (!checkChartJs()) {
                        console.warn('页面加载完成后 Chart.js 仍未定义');
                    }
                }, 500);
            });
        })();
    </script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeIn 0.8s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .login-link {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .login-link a {
            color: #2196F3;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .login-link a:hover {
            background-color: #2196F3;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
        }
        .server-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .server-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: visible;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            border: 1px solid #e0e0e0;
            z-index: 1;
        }
        
        .server-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .server-header {
            padding: 15px;
            color: white;
            display: flex;
            align-items: center;
            min-height: 80px;
            transition: background-color 0.3s ease;
        }
        
        .server-header.online {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }
        
        .server-header.offline {
            background: linear-gradient(135deg, #f44336 0%, #da190b 100%);
        }
        
        .server-header.loading {
            background: linear-gradient(135deg, #9e9e9e 0%, #757575 100%);
        }
        
        .server-header.bedrock {
            background: linear-gradient(135deg, #2196F3 0%, #0b7dda 100%);
        }
        
        .server-icon {
            width: 64px;
            height: 64px;
            border-radius: 10px;
            margin-right: 15px;
            background-color: #fff;
            padding: 3px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .server-card:hover .server-icon {
            transform: scale(1.05);
        }
        
        .server-name {
            font-size: 18px;
            font-weight: bold;
            flex: 1;
        }
        
        .server-status {
            margin-left: auto;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            background-color: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .server-body {
            padding: 15px;
        }
        
        .server-info {
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .server-card:hover .server-info {
            transform: translateX(5px);
        }
        
        .server-info label {
            font-weight: bold;
            color: #666;
            display: block;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        
        .server-info p {
            margin: 0;
            color: #333;
            font-size: 1.05em;
        }
        .player-change {
            display: inline-block;
            margin-left: 8px;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
            animation: fadeInChange 0.5s ease-in;
            vertical-align: middle;
        }
        
        .player-increase {
            background-color: #e8f5e9;
            color: #2e7d32;
            box-shadow: 0 2px 4px rgba(46, 125, 50, 0.2);
        }
        
        .player-decrease {
            background-color: #ffebee;
            color: #c62828;
            box-shadow: 0 2px 4px rgba(198, 40, 40, 0.2);
        }
        
        @keyframes fadeInChange {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .online-info p {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .online-info p strong {
            font-size: 1.3em;
            font-weight: bold;
        }
        .online-info p .current-players {
            color: #4CAF50;
            font-weight: bold;
            font-size: 1.3em;
        }
        .server-type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            margin-top: 5px;
        }
        
        .server-type-badge.java {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .server-type-badge.bedrock {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .server-motd {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            margin-top: auto;
            margin-bottom: 0;
            font-style: normal;
            line-height: 1.6;
            white-space: normal;
            overflow-wrap: break-word;
            text-align: center;
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.3);
            border: 1px solid #1a252f;
            height: 80px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .server-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: visible;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            border: 1px solid #e0e0e0;
            z-index: 1;
            display: flex;
            flex-direction: column;
            min-height: 300px;
        }
        
        .server-body {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .player-list {
            display: none;
        }
        
        .player-list h4 {
            color: #fff;
            font-size: 0.9em;
            margin-bottom: 8px;
            font-weight: normal;
        }
        
        .player-names {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px;
        }
        
        .player-tag {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            transition: background-color 0.3s ease;
        }
        
        .player-tag:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .no-players {
            color: rgba(255, 255, 255, 0.7);
            font-style: italic;
            font-size: 0.9em;
        }
        
        .copy-ip-btn {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .copy-ip-btn:hover {
            background-color: #1976D2;
            transform: translateY(-1px);
        }
        
        .copy-ip-btn.copied {
            background-color: #4CAF50;
        }
        
        .no-servers {
            text-align: center;
            padding: 60px 40px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 40px;
        }
        
        .no-servers h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .no-servers p {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        
        @media (max-width: 768px) {
            .server-grid {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .container {
                padding: 10px;
            }
        }
        
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 6px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .server-card.loading-state {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .server-card.loading-state .server-icon {
            background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="cursor: pointer;"><a href="login.php" style="color: inherit; text-decoration: none;"><?= SITE_TITLE ?></a></h1>

        <?php if ($servers->num_rows > 0): ?>
            
            <?php
            // 收集所有服务器地址和类型，用于异步请求
            $servers_list = [];
            $servers_data = [];
            
            $servers->data_seek(0);
            
            while ($server = $servers->fetch_assoc()) {
                $server_type = !empty($server['server_type']) ? $server['server_type'] : 'java';
                $servers_list[] = [
                    'address' => $server['address'],
                    'type' => $server_type
                ];
                $servers_data[$server['address']] = $server;
            }
            
            $servers->data_seek(0);
            ?>
            
            <div class="server-grid">
                <?php while ($server = $servers->fetch_assoc()): ?>
                    <?php
                    $server_type = !empty($server['server_type']) ? $server['server_type'] : 'java';
                    $server_address = $server['address'];
                    ?>
                    <div class="server-card loading-state" data-server-id="<?= $server['id'] ?>" data-server-address="<?= $server['address'] ?>" data-server-type="<?= $server_type ?>">
                        <div class="server-header loading">
                            <div class="server-icon"></div>
                            <div class="server-name">
                                <span class="loading-spinner"></span><?= $server['name'] ?>
                            </div>
                            <div class="server-status">加载中...</div>
                        </div>
                        <div class="server-body">
                            <div class="server-info">
                                <label>地址</label>
                                <p>
                                    <?php
                                    // 检查是否显示IP
                                    if (isset($server['show_ip']) && $server['show_ip']) {
                                        echo $server['address'];
                                    } else {
                                        $description = !empty($server['ip_description']) ? $server['ip_description'] : 'IP地址已隐藏';
                                        echo $description;
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="server-info">
                                <label>类型</label>
                                <span class="server-type-badge <?= $server_type ?>"><?= $server_type === 'java' ? 'Java' : '基岩' ?></span>
                            </div>
                            <div class="server-motd">
                                <span class="loading-spinner" style="margin-right: 4px;"></span>正在获取服务器状态...
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-servers">
                <h2>暂无服务器数据</h2>
                <p>请联系管理员添加Minecraft服务器</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="chartModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">服务器在线人数历史数据</h3>
                <button id="closeModal" class="close-btn">×</button>
            </div>
            <div id="modalBody" class="modal-body">
            </div>
        </div>
    </div>

    <script>
        async function loadAllServerStatuses() {
            const serverCards = document.querySelectorAll('.server-card');
            const servers = [];
            serverCards.forEach(card => {
                const serverId = card.getAttribute('data-server-id');
                const serverAddress = card.getAttribute('data-server-address');
                const serverType = card.getAttribute('data-server-type');
                servers.push({
                    id: serverId,
                    address: serverAddress,
                    type: serverType
                });
            });
            
            const requestData = {
                action: 'get_servers_status_parallel',
                servers: JSON.stringify(servers.map(s => ({ address: s.address, type: s.type })))
            };
            
            try {
                const response = await fetch('api.php?' + new URLSearchParams(requestData));
                const result = await response.json();
                
                if (result.success && result.data) {
                    // 更新每个服务器的显示
                    Object.keys(result.data).forEach(address => {
                        const status = result.data[address];
                        const card = document.querySelector(`.server-card[data-server-address="${address}"]`);
                        if (card) {
                            updateServerCard(card, status);
                        }
                    });
                    updateLastUpdateTime();
                }
            } catch (error) {
                console.error('加载服务器状态失败:', error);
            }
        }
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            
            let updateTimeElement = document.getElementById('lastUpdateTime');
            if (!updateTimeElement) {
                updateTimeElement = document.createElement('div');
                updateTimeElement.id = 'lastUpdateTime';
                updateTimeElement.style.cssText = 'text-align: center; color: #666; font-size: 12px; margin-top: 10px; padding: 10px;';
                document.querySelector('.container').appendChild(updateTimeElement);
            }
            
            const refreshStatus = autoRefreshInterval ? '暂停' : '运行';
            const refreshButton = `<button id="toggleRefreshBtn" onclick="toggleAutoRefresh()" style="padding: 4px 12px; font-size: 12px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px; cursor: pointer;">${refreshStatus}</button>`;
            
            updateTimeElement.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="color: #666;">最后更新时间: ${timeString}</span>
                        <span style="color: #999;">(每10秒自动刷新)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <a href="https://github.com/Nangua-RMS/NG-MC-Staus" target="_blank" style="text-decoration: none;">
                            <span class="refresh-status ${autoRefreshInterval ? 'running' : 'paused'}" style="padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer;">
                                ${autoRefreshInterval ? '🎃运行中' : '⏸已暂停'}
                            </span>
                        </a>
                        <button id="toggleRefreshBtn" onclick="toggleAutoRefresh()" style="padding: 4px 12px; font-size: 12px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px; cursor: pointer;">${refreshStatus}</button>
                        <a href="https://github.com/Nangua-RMS/NG-MC-Staus" target="_blank" style="color: #2196F3; text-decoration: none; font-size: 12px;">良医☭南瓜</a>
                        <a>&强力驱动</a>
                    </div>
                </div>
            `;
        }
        
        function toggleAutoRefresh() {
            if (autoRefreshInterval) {
                stopAutoRefresh();
                console.log('自动刷新已暂停');
            } else {
                startAutoRefresh();
                console.log('自动刷新已恢复');
            }
            updateLastUpdateTime();
        }
        
        let autoRefreshInterval = null;
        
        function startAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            
            autoRefreshInterval = setInterval(() => {
                console.log('自动刷新服务器状态...');
                loadAllServerStatuses();
            }, 10000);
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }

        function updateServerCard(card, status) {
            const serverId = card.getAttribute('data-server-id');
            const serverType = card.getAttribute('data-server-type');
            const header = card.querySelector('.server-header');
            const statusText = card.querySelector('.server-status');
            const body = card.querySelector('.server-body');
            const lastOnlineCount = card.getAttribute('data-last-players');
            const currentOnlineCount = status.players_online || 0;
            let playerChange = null;
            if (lastOnlineCount !== null) {
                playerChange = currentOnlineCount - parseInt(lastOnlineCount);
            }
            card.setAttribute('data-last-players', currentOnlineCount);
            card.classList.remove('loading-state');
            header.classList.remove('loading');
            
            if (status.online) {
                header.classList.add('online');
                statusText.textContent = '在线';
            } else {
                header.classList.add('offline');
                statusText.textContent = '离线';
            }
            
            if (serverType === 'bedrock') {
                header.classList.add('bedrock');
            }
            
            const iconContainer = card.querySelector('.server-icon');
            if (status.server_icon && status.server_icon.startsWith('data:image')) {
                const img = document.createElement('img');
                img.src = status.server_icon;
                img.alt = 'Server Icon';
                img.className = 'server-icon';
                iconContainer.replaceWith(img);
            } else if (status.server_icon && !status.server_icon.startsWith('data:image')) {
                const img = document.createElement('img');
                img.src = status.server_icon;
                img.alt = 'Server Icon';
                img.className = 'server-icon';
                img.loading = 'lazy';
                iconContainer.replaceWith(img);
            }
            
            let onlineInfo = body.querySelector('.online-info');
            let versionInfo = body.querySelector('.version-info');
            let motdElement = body.querySelector('.server-motd');
            
            if (status.online) {
                if (!onlineInfo) {
                    onlineInfo = document.createElement('div');
                    onlineInfo.className = 'server-info online-info';
                    onlineInfo.innerHTML = '<label>在线人数</label><p></p>';
                    const typeInfo = body.querySelector('.server-type-badge').parentElement;
                    if (typeInfo) {
                        typeInfo.after(onlineInfo);
                    } else {
                        body.appendChild(onlineInfo);
                    }
                }
                
                const onlineCount = onlineInfo.querySelector('p');
                let onlineHtml = `<span class="current-players">${status.players_online}</span> / ${status.players_max}`;
                if (playerChange !== null && playerChange !== 0) {
                    const changeClass = playerChange > 0 ? 'player-increase' : 'player-decrease';
                    const changeIcon = playerChange > 0 ? '↑' : '↓';
                    const changeText = playerChange > 0 ? `+${playerChange}` : `${playerChange}`;
                    onlineHtml += ` <span class="player-change ${changeClass}" title="对比上次变化">${changeIcon} ${changeText}</span>`;
                }
                
                onlineCount.innerHTML = onlineHtml;
                
                if (!versionInfo) {
                    versionInfo = document.createElement('div');
                    versionInfo.className = 'server-info version-info';
                    versionInfo.innerHTML = '<label>版本</label><p></p>';
                    if (onlineInfo) {
                        onlineInfo.after(versionInfo);
                    } else {
                        body.appendChild(versionInfo);
                    }
                }
                
                const versionText = versionInfo.querySelector('p');
                versionText.textContent = status.version;

                if (!motdElement) {
                    motdElement = document.createElement('div');
                    motdElement.className = 'server-motd';
                    body.appendChild(motdElement);
                }
                motdElement.innerHTML = status.motd_html || status.motd;
                const connectionInfo = body.querySelector('.connection-info');
                if (connectionInfo) {
                    connectionInfo.remove();
                }

                savePlayerHistory(serverId, status.players_online, status.player_list);
            } else {
                if (onlineInfo) {
                    onlineInfo.remove();
                }
                if (versionInfo) {
                    versionInfo.remove();
                }

                let connectionInfo = body.querySelector('.connection-info');
                if (!connectionInfo) {
                    connectionInfo = document.createElement('div');
                    connectionInfo.className = 'server-info connection-info';
                    connectionInfo.innerHTML = '<label>连接信息</label><p></p>';

                    const typeInfo = body.querySelector('.server-type-badge').parentElement;
                    if (typeInfo) {
                        typeInfo.after(connectionInfo);
                    } else {
                        body.appendChild(connectionInfo);
                    }
                }

                const connectionText = connectionInfo.querySelector('p');
                connectionText.innerHTML = `
                    ${status.ip_address ? 'IP: ' + status.ip_address + '<br>' : ''}
                    ${status.hostname && status.hostname !== status.server_address ? '主机名: ' + status.hostname + '<br>' : ''}
                `;
                
                // 更新或创建 MOTD
                if (!motdElement) {
                    motdElement = document.createElement('div');
                    motdElement.className = 'server-motd';
                    body.appendChild(motdElement);
                }
                motdElement.innerHTML = status.motd_html || status.motd || '服务器当前离线';
            }
        }
        async function savePlayerHistory(serverId, playersOnline, playerList) {
            try {
                const params = new URLSearchParams({
                    action: 'save_player_history',
                    server_id: serverId,
                    players_online: playersOnline
                });
                
                if (playerList && playerList.length > 0) {
                    params.append('player_list', JSON.stringify(playerList));
                }
                
                await fetch('api.php?' + params.toString());
            } catch (error) {
                console.error('保存历史数据失败:', error);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM已加载，开始异步加载服务器状态');
            loadAllServerStatuses();

            startAutoRefresh();
            
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopAutoRefresh();
                    console.log('页面隐藏，暂停自动刷新');
                } else {
                    startAutoRefresh();
                    console.log('页面显示，恢复自动刷新');
                    loadAllServerStatuses();
                }
            });
            
            console.log('DOM已加载，初始化图表功能');
            
            const chartModal = document.getElementById('chartModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            const closeModalBtn = document.getElementById('closeModal');
            
            console.log('模态框元素存在状态：', {
                chartModal: !!chartModal,
                modalTitle: !!modalTitle,
                modalBody: !!modalBody,
                closeModalBtn: !!closeModalBtn
            });
            

            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', function() {
                    hideChartModal();
                });
            }
            
            if (chartModal) {
                chartModal.addEventListener('click', function(e) {
                    if (e.target === chartModal) {
                        hideChartModal();
                    }
                });
            }
            
            document.querySelector('.server-grid').addEventListener('click', function(e) {
                const card = e.target.closest('.server-card');
                if (card) {
                    const serverId = card.getAttribute('data-server-id');
                    const serverNameElement = card.querySelector('.server-name');
                    const serverName = serverNameElement ? serverNameElement.textContent.trim() : '未知服务器';
                    
                    console.log('点击了服务器卡片，显示图表，服务器ID:', serverId, '名称:', serverName);
                    showChartModal(serverId, serverName);
                }
            });
        });
        
        async function showChartModal(serverId, serverName) {
            console.log('显示图表模态框，服务器ID:', serverId, '名称:', serverName);
            
            const chartModal = document.getElementById('chartModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            if (!chartModal || !modalTitle || !modalBody) {
                console.error('模态框元素不存在！');
                return;
            }
            
            const serverCard = document.querySelector(`.server-card[data-server-id="${serverId}"]`);
            const isOnline = serverCard ? serverCard.querySelector('.server-header').classList.contains('online') : false;
            
            modalTitle.textContent = '';
            
            const titleIcon = document.createElement('div');
            titleIcon.className = 'modal-title-icon';
            
            if (serverCard) {
                const serverIcon = serverCard.querySelector('.server-icon');
                if (serverIcon && serverIcon.tagName === 'IMG') {
                    const iconImg = document.createElement('img');
                    iconImg.src = serverIcon.src;
                    iconImg.alt = 'Server Icon';
                    iconImg.style.width = '100%';
                    iconImg.style.height = '100%';
                    iconImg.style.objectFit = 'cover';
                    titleIcon.appendChild(iconImg);
                }
            }
            
            const titleText = document.createTextNode(serverName + ' - 在线人数历史数据');
            
            modalTitle.appendChild(titleIcon);
            modalTitle.appendChild(titleText);
            
            console.log('服务器在线状态:', isOnline);
            
            modalBody.innerHTML = '';
            modalBody.innerHTML = `
                <div class="chart-controls">
                    <div class="date-selector">
                        <label for="selectedDate">选择日期：</label>
                        <input type="date" id="selectedDate" class="date-input">
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="modalPlayerChart" width="400" height="300"></canvas>
                </div>
            `;

            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('selectedDate');
            dateInput.max = today;
            dateInput.value = today;

            function handleDateSelection() {
                const selectedDate = document.getElementById('selectedDate');
                if (!selectedDate) {
                    console.error('日期输入框不存在！');
                    return;
                }
                
                const dateValue = selectedDate.value;
                if (!dateValue) {
                    console.warn('未选择日期');
                    return;
                }
                
                console.log('应用日期筛选:', dateValue, '服务器ID:', serverId);
                console.log('当前图表实例:', currentModalChart);
                
                loadModalChartDataForDate(serverId, dateValue);
            }
            if (dateInput) {
                dateInput.addEventListener('change', handleDateSelection);
            }

            chartModal.style.display = 'flex';
            chartModal.style.opacity = '1';
            chartModal.style.zIndex = '2147483647';
            console.log('模态框显示状态:', chartModal.style.display);
            
            await initModalChart(serverId, 0);
            
            setTimeout(() => {
                handleDateSelection();
            }, 200);
        }
        
        function hideChartModal() {
            const chartModal = document.getElementById('chartModal');
            if (chartModal) {
                chartModal.style.display = 'none';
                console.log('模态框已隐藏');
            }
        }
        
        let currentModalChart = null;
        
        let modalPlayerLists = [];
        
        function waitForChartJs() {
            return new Promise((resolve, reject) => {
                const CHECK_INTERVAL = 100;
                const MAX_WAIT = 5000;
                let elapsed = 0;
                
                const checkInterval = setInterval(() => {
                    elapsed += CHECK_INTERVAL;
                    
                    if (typeof Chart !== 'undefined') {
                        clearInterval(checkInterval);
                        console.log('Chart.js 已就绪');
                        resolve();
                    } else if (elapsed >= MAX_WAIT) {
                        clearInterval(checkInterval);
                        reject(new Error('等待 Chart.js 超时'));
                    }
                }, CHECK_INTERVAL);
            });
        }
        
        async function initModalChart(serverId, days) {
            console.log('初始化图表，服务器ID:', serverId, '天数:', days);
            
            try {
                await waitForChartJs();
            } catch (error) {
                console.error('等待 Chart.js 失败:', error);

                const modalBody = document.getElementById('modalBody');
                if (modalBody) {
                    modalBody.innerHTML = '<p class="status-error">图表库加载失败，请刷新页面重试</p>';
                }
                return;
            }
            
            if (typeof Chart === 'undefined') {
                console.error('Chart.js未定义！尝试显示静态数据...');
                
                const mockData = generateMockData(days);
                const modalBody = document.getElementById('modalBody');
                
                if (modalBody) {
                    let dataHtml = '<div class="static-chart-data">';
                    dataHtml += '<h4>Chart.js未加载，显示静态数据</h4>';
                    dataHtml += '<table class="data-table">';
                    dataHtml += '<tr><th>时间</th><th>在线人数</th></tr>';
                    
                    for (let i = 0; i < Math.min(10, mockData.labels.length); i++) {
                        dataHtml += `<tr><td>${mockData.labels[i]}</td><td>${mockData.values[i]}</td></tr>`;
                    }
                    
                    if (mockData.labels.length > 10) {
                        dataHtml += `<tr><td colspan="2">... 还有 ${mockData.labels.length - 10} 条数据</td></tr>`;
                    }
                    
                    dataHtml += '</table>';
                    dataHtml += '<p class="status-warning">请检查网络连接或Chart.js CDN的可访问性</p>';
                    dataHtml += '</div>';
                    
                    modalBody.innerHTML = dataHtml;
                }
                
                try {
                    const script = document.createElement('script');
                    script.src = 'dist/chart.js';
                    script.onload = function() {
                            console.log('Chart.js重新加载成功！');
                            setTimeout(() => initModalChart(serverId, 0), 500);
                        };
                    document.head.appendChild(script);
                } catch (e) {
                    console.error('尝试重新加载Chart.js时出错:', e);
                }
                
                return;
            }
            
            if (currentModalChart) {
                currentModalChart.destroy();
                console.log('已销毁之前的图表实例');
            }
            
            const ctx = document.getElementById('modalPlayerChart');
            if (!ctx) {
                console.error('图表画布元素不存在！');
                return;
            }
            
            modalPlayerLists = [];
            
            const config = {
                type: 'line',
                data: {
                    labels: ['加载中...'],
                    datasets: [{
                        label: '在线人数',
                        data: [0],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: 'rgba(255, 255, 255, 0.2)',
                            borderWidth: 1,
                            padding: 10,
                            displayColors: false,
                            callbacks: {
                                title: function(context) {
                                    return `时间: ${context[0].label}`;
                                },
                                label: function(context) {
                                    console.log('Tooltip上下文:', context);
                                    console.log('modalPlayerLists类型:', typeof modalPlayerLists, '长度:', modalPlayerLists.length);
                                    console.log('当前索引:', context.dataIndex);
                                    
                                    const value = context.parsed.y || 0;
                                    return `在线人数：${value}`;
                                },
                                afterLabel: function(context) {
                                    try {
                                        if (context.dataIndex !== undefined && 
                                            Array.isArray(modalPlayerLists) && 
                                            context.dataIndex >= 0 && 
                                            context.dataIndex < modalPlayerLists.length) {
                                            
                                            const playerList = modalPlayerLists[context.dataIndex];
                                            console.log('当前玩家列表数据:', playerList);
                                            
                                            if (playerList) {
                                                let parsedPlayers = playerList;
                                                if (typeof playerList === 'string') {
                                                    try {
                                                        parsedPlayers = JSON.parse(playerList);
                                                    } catch (e) {
                                                        console.log('玩家列表不是JSON字符串，直接使用:', playerList);
                                                    }
                                                }
                                                
                                                if (Array.isArray(parsedPlayers) && parsedPlayers.length > 0) {
                                                    const playerNames = parsedPlayers.map(p => 
                                                        typeof p === 'string' ? p : 
                                                        typeof p === 'object' ? JSON.stringify(p) : 
                                                        String(p)
                                                    );
                                                    return `在线玩家：\n${playerNames.join('\n')}`;
                                                } else if (Array.isArray(parsedPlayers) && parsedPlayers.length === 0) {
                                                    return '在线玩家：无';
                                                } else if (parsedPlayers) {
                                                    return `玩家数据：${String(parsedPlayers).substring(0, 100)}`;
                                                }
                                            } else {
                                                console.log('当前索引没有对应的玩家列表数据');
                                            }
                                        } else {
                                            console.log('索引无效或玩家列表为空/非数组');
                                        }
                                    } catch (e) {
                                        console.error('处理玩家列表时出错:', e);
                                        return `错误: ${e.message.substring(0, 50)}`;
                                    }
                                    return '';
                                }
                            }
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '在线人数'
                            },
                            ticks: {
                                stepSize: 1
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: '时间'
                            }
                        }
                    }
                }
            };
            
            try {
                currentModalChart = new Chart(ctx, config);
                console.log('图表实例创建成功');
            } catch (e) {
                console.error('创建图表失败:', e);
                const modalBody = document.getElementById('modalBody');
                if (modalBody) {
                    modalBody.innerHTML = '<p class="status-error">创建图表失败：' + e.message + '</p>';
                    
                    const mockData = generateMockData(days);
                    let dataHtml = '<div class="static-chart-data">';
                    dataHtml += '<table class="data-table">';
                    dataHtml += '<tr><th>时间</th><th>在线人数</th></tr>';
                    
                    for (let i = 0; i < Math.min(10, mockData.labels.length); i++) {
                        dataHtml += `<tr><td>${mockData.labels[i]}</td><td>${mockData.values[i]}</td></tr>`;
                    }
                    
                    dataHtml += '</table>';
                    dataHtml += '</div>';
                    
                    modalBody.innerHTML += dataHtml;
                }
                return;
            }
            
            loadModalChartData(serverId, days);
        }
        

        function updateModalChart(serverId, days) {
            console.log('更新图表，服务器ID:', serverId, '天数:', days);
            loadModalChartData(serverId, days);
        }
        

        function loadModalChartData(serverId, days) {
            console.log('加载图表数据，服务器ID:', serverId, '天数:', days);
            
            try {

                const data = getHistoricalData(serverId, days);

                if (currentModalChart && data) {
                    currentModalChart.data.labels = data.labels;
                    currentModalChart.data.datasets[0].data = data.values;
                    currentModalChart.update();
                    console.log('图表数据更新成功，数据点数量:', data.values.length);
                }
            } catch (e) {
                console.error('加载图表数据失败:', e);
            }
        }
        
        function loadModalChartDataForDate(serverId, selectedDate) {
            console.log('加载日期图表数据，服务器ID:', serverId, '日期:', selectedDate);
            console.log('当前图表实例状态:', currentModalChart ? '存在' : '不存在');
            
            try {
                const data = getHistoricalDataForDate(serverId, selectedDate);
                
                console.log('获取到的数据:', data);
                console.log('数据点数量:', data ? data.values?.length : 0);
                
                if (currentModalChart && data) {
                    console.log('开始更新图表数据...');
                    currentModalChart.data.labels = data.labels || [];
                    currentModalChart.data.datasets[0].data = data.values || [];
                    currentModalChart.update();
                    console.log('图表数据按日期更新成功，数据点数量:', data.values?.length || 0);
                } else {
                    console.error('无法更新图表：图表实例不存在或数据无效');
                }
            } catch (e) {
                console.error('加载按日期图表数据失败:', e);
            }
        }
        
        function getHistoricalDataForDate(serverId, selectedDate) {
            console.log('获取指定日期的历史数据，服务器ID:', serverId, '日期:', selectedDate);
            
            try {
                const xhr = new XMLHttpRequest();
                
                const view_mode = 'date';
                const url = `get_player_data.php?server_id=${serverId}&view_mode=${view_mode}&date=${selectedDate}`;
                
                const timestamp = new Date().getTime();
                const fullUrl = url + '&_=' + timestamp;
                
                console.log('请求URL:', fullUrl);
                
                xhr.open('GET', fullUrl, false);
                xhr.send();
                
                console.log('数据请求响应状态码:', xhr.status);
                
                if (xhr.status === 200) {
                    console.log('数据请求响应内容:', xhr.responseText);
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success && response.data && response.data.labels && response.data.values) {
                                console.log('历史数据格式正确，返回数据');
                                if (response.data.labels.length > 0) {
                                    if (response.data.playerLists) {
                                        modalPlayerLists = Array.isArray(response.data.playerLists) ? response.data.playerLists : [];
                                        console.log('玩家列表数据已保存，数量:', modalPlayerLists.length);
                                    } else {
                                        modalPlayerLists = [];
                                        console.log('没有找到玩家列表数据');
                                    }
                                    
                                    return {
                                        labels: response.data.labels,
                                        values: response.data.values
                                    };
                                } else {
                                    console.log('返回了空数据，使用0值数据');
                                    modalPlayerLists = [];
                                    return generateEmptyData();
                                }
                            } else {
                            console.error('获取历史数据失败:', response.error || '未知错误');
                            const modalBody = document.getElementById('modalBody');
                            if (modalBody) {
                                modalBody.innerHTML = '<p class="status-error">获取数据失败：' + (response.error || '未知错误') + '</p>';
                            }
                            return generateEmptyData();
                        }
                    } catch (e) {
                        console.error('解析历史数据失败:', e);
                        const modalBody = document.getElementById('modalBody');
                        if (modalBody) {
                            modalBody.innerHTML = '<p class="status-error">数据解析失败：' + e.message + '</p>';
                        }
                        return generateEmptyData();
                    }
                } else {
                    console.error('数据请求失败，状态码:', xhr.status);
                    // 显示请求错误提示给用户
                    const modalBody = document.getElementById('modalBody');
                    if (modalBody) {
                        modalBody.innerHTML = '<p class="status-error">数据请求失败，状态码：' + xhr.status + '</p>';
                    }
                    return generateEmptyData();
                }
            } catch (e) {
                console.error('获取历史数据时发生异常:', e);
                const modalBody = document.getElementById('modalBody');
                if (modalBody) {
                    modalBody.innerHTML = '<p class="status-error">获取数据时发生异常：' + e.message + '</p>';
                }
                return generateEmptyData();
            }
            
            try {
                const data = getHistoricalData(serverId, days);
                
                if (currentModalChart && data) {
                    currentModalChart.data.labels = data.labels;
                    currentModalChart.data.datasets[0].data = data.values;
                    currentModalChart.update();
                    console.log('图表数据更新成功，数据点数量:', data.values.length);
                }
            } catch (e) {
                console.error('加载图表数据失败:', e);
            }
        }
        
        function getHistoricalData(serverId, days) {
            console.log('获取历史数据，服务器ID:', serverId, '天数:', days);
            
            try {

                const xhr = new XMLHttpRequest();
                
                const view_mode = 'raw';
                
                const url = `get_player_data.php?server_id=${serverId}&view_mode=${view_mode}`;
                
                const timestamp = new Date().getTime();
                const fullUrl = url + '&_=' + timestamp;
                
                console.log('请求URL:', fullUrl);
                
                xhr.open('GET', fullUrl, false);
                xhr.send();
                
                console.log('数据请求响应状态码:', xhr.status);
                
                if (xhr.status === 200) {
                    console.log('数据请求响应内容:', xhr.responseText);
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success && response.data && response.data.labels && response.data.values) {
                            console.log('历史数据格式正确，返回数据');
                            if (response.data.labels.length > 0) {
                                if (response.data.playerLists) {
                                    modalPlayerLists = Array.isArray(response.data.playerLists) ? response.data.playerLists : [];
                                    console.log('玩家列表数据已保存，数量:', modalPlayerLists.length);
                                } else {
                                    modalPlayerLists = [];
                                    console.log('没有找到玩家列表数据');
                                }
                                
                                return {
                                    labels: response.data.labels,
                                    values: response.data.values
                                };
                            } else {
                                console.log('返回了空数据，使用0值数据');
                                modalPlayerLists = [];
                                return generateEmptyData();
                            }
                        } else {
                            console.error('获取历史数据失败:', response.error || '未知错误');
                            const modalBody = document.getElementById('modalBody');
                            if (modalBody) {
                                modalBody.innerHTML = '<p class="status-error">获取数据失败：' + (response.error || '未知错误') + '</p>';
                            }
                            return generateEmptyData();
                        }
                    } catch (e) {
                        console.error('解析历史数据失败:', e);
                        const modalBody = document.getElementById('modalBody');
                        if (modalBody) {
                            modalBody.innerHTML = '<p class="status-error">数据解析失败：' + e.message + '</p>';
                        }
                        return generateEmptyData();
                    }
                } else {
                    console.error('数据请求失败，状态码:', xhr.status);
                    const modalBody = document.getElementById('modalBody');
                    if (modalBody) {
                        modalBody.innerHTML = '<p class="status-error">数据请求失败，状态码：' + xhr.status + '</p>';
                    }
                    return generateEmptyData();
                }
            } catch (e) {
                console.error('获取历史数据时发生异常:', e);
                // 显示异常提示给用户
                const modalBody = document.getElementById('modalBody');
                if (modalBody) {
                    modalBody.innerHTML = '<p class="status-error">获取数据时发生异常：' + e.message + '</p>';
                }
                return generateEmptyData();
            }
        }
        
        function generateEmptyData() {
            console.log('生成近两小时的0值数据');
            
            const labels = [];
            const values = [];
            
            const now = new Date();
            const step = 1800000;
            const totalPoints = 4;
            
            for (let i = totalPoints - 1; i >= 0; i--) {
                const time = new Date(now.getTime() - (i * step));
                // 格式化为小时:分钟
                const hours = time.getHours().toString().padStart(2, '0');
                const minutes = time.getMinutes().toString().padStart(2, '0');
                const label = hours + ':' + minutes;
                
                labels.push(label);
                values.push(0);
            }
            
            console.log('0值数据生成完成，数据点数量:', values.length);
            return { labels, values };
        }
        
        function generateMockData(days) {
            console.log('生成模拟数据（已兼容为0值数据），天数:', days);
            return generateEmptyData();
        }
        
        function debugModalElements() {
            console.log('===== 模态框元素调试信息 =====');
            console.log('chartModal:', document.getElementById('chartModal'));
            console.log('modalTitle:', document.getElementById('modalTitle'));
            console.log('modalBody:', document.getElementById('modalBody'));
            console.log('closeModal:', document.getElementById('closeModal'));
            console.log('show-chart-btn数量:', document.querySelectorAll('.show-chart-btn').length);
            console.log('Chart.js是否加载:', typeof Chart !== 'undefined');
            if (typeof Chart !== 'undefined') {
                console.log('Chart.js版本:', Chart.version);
                console.log('Chart.js对象结构:', Object.keys(Chart).slice(0, 10));
            } else {
                console.error('Chart.js未加载！请检查CDN链接是否可访问。');
                try {
                    console.log('尝试重新加载Chart.js...');
                    const script = document.createElement('script');
                    script.src = 'dist/chart.js';
                    script.onload = function() {
                        console.log('Chart.js重新加载成功！');
                    };
                    script.onerror = function() {
                        console.error('Chart.js重新加载失败！');
                    };
                    document.head.appendChild(script);
                } catch (e) {
                    console.error('尝试重新加载Chart.js时出错:', e);
                }
            }
            console.log('=============================');
        }
        
        const staticChartStyles = `
            .static-chart-data {
                padding: 15px;
                background-color: rgba(0, 0, 0, 0.05);
                border-radius: 8px;
                margin-top: 15px;
            }
            
            .static-chart-data h4 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #6c757d;
                font-size: 1.1rem;
                text-align: center;
            }
            
            .data-table {
                width: 100%;
                border-collapse: collapse;
                background-color: white;
                border-radius: 4px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            .data-table th,
            .data-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #dee2e6;
            }
            
            .data-table th {
                background-color: #f8f9fa;
                font-weight: 600;
                color: #495057;
            }
            
            .data-table tr:last-child td {
                border-bottom: none;
            }
            
            .data-table tr:hover {
                background-color: #f8f9fa;
            }
            
            .status-warning {
                margin-top: 15px;
                padding: 10px;
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                color: #856404;
                font-size: 0.9rem;
                text-align: center;
            }`;
            
        try {
            const styleElement = document.createElement('style');
            styleElement.textContent = staticChartStyles;
            document.head.appendChild(styleElement);
        } catch (e) {
            console.error('添加静态图表样式失败:', e);
        }
        
        window.addEventListener('load', function() {
            console.log('页面完全加载完成');
            debugModalElements();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideChartModal();
            }
        });
    </script>

    <style>
        .chart-button-container {
            padding: 15px;
            display: flex;
            justify-content: center;
        }
        
        .show-chart-btn {
            background: linear-gradient(135deg, #2196F3 0%, #0b7dda 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .show-chart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(33, 150, 243, 0.3);
        }
        
        .chart-container {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 0;
            margin-bottom: 15px;
            padding: 15px;
            background-color: #fff;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            border: 1px solid #e0e0e0;
            border-top: none;
            z-index: 100;
            transform-origin: top center;
            transform: scaleY(0.95);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        
        .chart-container[style*='display: block'] {
            opacity: 1;
            transform: scaleY(1);
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .modal-title-icon {
            display: inline-flex;
            align-items: center;
            margin-right: 8px;
            width: 64px;
            height: 64px;
            border-radius: 10px;
            background-color: #fff;
            padding: 3px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .close-btn:hover {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
            max-height: calc(80vh - 120px);
            overflow-y: auto;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-body .chart-wrapper {
            height: 300px;
            margin: 20px 0;
        }
        
        .modal-body .chart-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .chart-container h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
            font-size: 16px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 200px;
            margin-bottom: 10px;
        }
        
        .chart-controls {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        .chart-btn {
            padding: 5px 10px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .chart-btn:hover {
            background-color: #e9e9e9;
        }
        
        .chart-btn.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        .date-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .date-selector label {
            font-weight: bold;
            color: #333;
            font-size: 14px;
            white-space: nowrap;
        }
        
        .date-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
            transition: border-color 0.2s ease;
            min-width: 150px;
        }
        
        .date-input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
        }
        
        .date-btn {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        
        .date-btn:hover {
            background-color: #45a049;
            transform: translateY(-1px);
        }
        
        .date-btn:active {
            transform: translateY(0);
        }

        .server-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }
        
        .server-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .chart-container {
            transition: opacity 0.3s ease;
        }
        
        .no-history-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .refresh-status.running {
            background-color: #4CAF50;
            color: white;
        }
        
        .refresh-status.paused {
            background-color: #f44336;
            color: white;
        }
    </style>
</body>
</html>