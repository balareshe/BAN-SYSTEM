<?php

/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */



require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';


requireAdminLogin();


$csrfToken = generateCsrfToken();


$logFile = __DIR__ . '/../logs/admin_actions.log';
$logDir = dirname($logFile);


if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    file_put_contents($logFile, '');
}


$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'clear' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    validateCsrfToken();

    if (file_exists($logFile)) {
        if (file_put_contents($logFile, '') !== false) {
            logAdminAction('CLEAR_LOGS', '清空操作日志');
            $message = 'success:日志已清空';
        } else {
            $message = 'error:清空日志失败';
        }
    }
} elseif ($action === 'export') {
    validateCsrfToken();

    if (file_exists($logFile)) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="admin_logs_' . date('Ymd_His') . '.log"');
        readfile($logFile);
        exit;
    }
}


$logs = [];
if (file_exists($logFile) && filesize($logFile) > 0) {
    $content = file_get_contents($logFile);
    $lines = array_reverse(explode("\n", trim($content))); 

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        
        if (preg_match('/^\[([^\]]+)\]\s+([^\s-]+)\s+-\s+([^\s-]+)\s+-\s+([^\s-]+)\s+-\s+(.+)$/', $line, $matches)) {
            $logs[] = [
                'timestamp' => $matches[1],
                'ip' => $matches[2],
                'username' => $matches[3],
                'action' => $matches[4],
                'details' => $matches[5]
            ];
        } else {
            
            $logs[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => '未知',
                'username' => '系统',
                'action' => 'LOG_PARSE_ERROR',
                'details' => $line
            ];
        }
    }
}


$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$totalLogs = count($logs);
$totalPages = ceil($totalLogs / $perPage);
$offset = ($page - 1) * $perPage;
$paginatedLogs = array_slice($logs, $offset, $perPage);


$todayLogs = 0;
$yesterdayLogs = 0;
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

foreach ($logs as $log) {
    if (strpos($log['timestamp'], $today) === 0) {
        $todayLogs++;
    } elseif (strpos($log['timestamp'], $yesterday) === 0) {
        $yesterdayLogs++;
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>操作日志 - 后台管理</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- 侧边栏 -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <span>ADMIN</span>
                </div>
            </div>

            <div class="sidebar-user">
                <div class="user-avatar">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($_SESSION['admin_username'] ?? '管理员'); ?></h4>
                    <span>超级管理员</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>仪表板</span>
                </a>
                <a href="bans.php" class="nav-item">
                    <i class="fas fa-user-slash"></i>
                    <span>封禁记录</span>
                </a>
                <a href="add.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>添加记录</span>
                </a>
                <a href="search.php" class="nav-item">
                    <i class="fas fa-search"></i>
                    <span>搜索玩家</span>
                </a>
                                <a href="admins.php" class="nav-item">
                    <i class="fas fa-users-cog"></i>
                    <span>管理员</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>系统设置</span>
                </a>
                <a href="logs.php" class="nav-item active">
                    <i class="fas fa-clipboard-list"></i>
                    <span>操作日志</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <p>日志总数: <?php echo $totalLogs; ?></p>
                <p>今日: <?php echo $todayLogs; ?> 条</p>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="admin-main">
            <!-- 头部 -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>操作日志</h1>
                    <p>记录所有管理员操作，便于审计和追踪</p>
                </div>

                <div class="header-right">
                    <div class="header-actions">
                        <button onclick="showLogStats()" class="action-button secondary">
                            <i class="fas fa-chart-bar"></i>
                            日志统计
                        </button>
                        <a href="?action=export&csrf_token=<?php echo $csrfToken; ?>" class="action-button" onclick="return confirm('确定要导出日志吗？');">
                            <i class="fas fa-download"></i>
                            导出日志
                        </a>
                    </div>
                </div>
            </header>

            <!-- 内容区域 -->
            <div class="admin-content">
                <?php if (!empty($message)): ?>
                    <?php list($type, $text) = explode(':', $message, 2); ?>
                    <div class="alert alert-<?php echo $type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($text); ?>
                    </div>
                <?php endif; ?>

                <!-- 日志统计 -->
                <div class="content-header">
                    <h2 class="content-title">
                        <i class="fas fa-chart-pie"></i>
                        日志概览
                    </h2>
                    <div class="content-actions">
                        <a href="?action=clear&csrf_token=<?php echo $csrfToken; ?>" class="action-button danger" onclick="return confirm('确定要清空所有日志吗？此操作不可撤销。');">
                            <i class="fas fa-trash"></i>
                            清空日志
                        </a>
                    </div>
                </div>

                <div class="cards-grid">
                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list"></i>
                                日志总数
                            </h3>
                            <span class="card-trend">记录</span>
                        </div>
                        <div class="card-value"><?php echo $totalLogs; ?></div>
                        <div class="card-footer">
                            <span>文件大小</span>
                            <span><?php echo file_exists($logFile) ? formatFileSize(filesize($logFile)) : '0 Bytes'; ?></span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar-day"></i>
                                今日日志
                            </h3>
                            <span class="card-trend <?php echo $todayLogs > $yesterdayLogs ? 'up' : 'down'; ?>">
                                <?php echo $todayLogs > $yesterdayLogs ? '+' : ''; ?><?php echo $todayLogs - $yesterdayLogs; ?>
                            </span>
                        </div>
                        <div class="card-value"><?php echo $todayLogs; ?></div>
                        <div class="card-footer">
                            <span>较昨日</span>
                            <span><?php echo $yesterdayLogs; ?></span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user-cog"></i>
                                活跃管理员
                            </h3>
                            <span class="card-trend">最近7天</span>
                        </div>
                        <div class="card-value">
                            <?php
                            $activeAdmins = [];
                            $weekAgo = date('Y-m-d', strtotime('-7 days'));
                            foreach ($logs as $log) {
                                if (strcmp($log['timestamp'], $weekAgo) >= 0) {
                                    $activeAdmins[$log['username']] = true;
                                }
                            }
                            echo count($activeAdmins);
                            ?>
                        </div>
                        <div class="card-footer">
                            <span>管理员</span>
                            <span><?php echo count($activeAdmins); ?> 人</span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-exclamation-triangle"></i>
                                错误日志
                            </h3>
                            <span class="card-trend">最近30天</span>
                        </div>
                        <div class="card-value">
                            <?php
                            $errorLogs = 0;
                            $monthAgo = date('Y-m-d', strtotime('-30 days'));
                            foreach ($logs as $log) {
                                if (strcmp($log['timestamp'], $monthAgo) >= 0 &&
                                    (stripos($log['action'], 'ERROR') !== false || stripos($log['action'], 'FAIL') !== false)) {
                                    $errorLogs++;
                                }
                            }
                            echo $errorLogs;
                            ?>
                        </div>
                        <div class="card-footer">
                            <span>错误数</span>
                            <span><?php echo $errorLogs; ?> 条</span>
                        </div>
                    </div>
                </div>

                <!-- 日志过滤器 -->
                <div class="content-header" style="margin-top: 40px;">
                    <h2 class="content-title">
                        <i class="fas fa-filter"></i>
                        日志查询
                    </h2>
                </div>

                <div class="form-section">
                    <form method="GET" action="" id="logFilterForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="filter_username">
                                    <i class="fas fa-user"></i>
                                    管理员
                                </label>
                                <select id="filter_username" name="username">
                                    <option value="">所有管理员</option>
                                    <?php
                                    $admins = [];
                                    foreach ($logs as $log) {
                                        $admins[$log['username']] = true;
                                    }
                                    foreach (array_keys($admins) as $admin):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($admin); ?>" <?php echo isset($_GET['username']) && $_GET['username'] === $admin ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($admin); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="filter_action">
                                    <i class="fas fa-cogs"></i>
                                    操作类型
                                </label>
                                <select id="filter_action" name="action_type">
                                    <option value="">所有操作</option>
                                    <?php
                                    $actions = [];
                                    foreach ($logs as $log) {
                                        $actions[$log['action']] = true;
                                    }
                                    foreach (array_keys($actions) as $action):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($action); ?>" <?php echo isset($_GET['action_type']) && $_GET['action_type'] === $action ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($action); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="filter_date">
                                    <i class="fas fa-calendar"></i>
                                    日期范围
                                </label>
                                <input type="date"
                                       id="filter_date"
                                       name="date"
                                       value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="filter_keyword">
                                    <i class="fas fa-search"></i>
                                    关键词搜索
                                </label>
                                <input type="text"
                                       id="filter_keyword"
                                       name="keyword"
                                       placeholder="在操作详情中搜索..."
                                       value="<?php echo htmlspecialchars($_GET['keyword'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="reset" class="action-button secondary">
                                <i class="fas fa-redo"></i>
                                重置条件
                            </button>
                            <button type="submit" class="action-button">
                                <i class="fas fa-search"></i>
                                筛选日志
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 日志列表 -->
                <div class="content-header" style="margin-top: 40px;">
                    <h2 class="content-title">
                        <i class="fas fa-list"></i>
                        日志列表
                        <span class="result-count"><?php echo $totalLogs; ?> 条记录</span>
                    </h2>
                    <span class="action-button secondary" onclick="refreshLogs()">
                        <i class="fas fa-sync-alt"></i>
                        刷新列表
                    </span>
                </div>

                <div class="data-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th width="15%">时间</th>
                                <th width="10%">管理员</th>
                                <th width="15%">IP地址</th>
                                <th width="15%">操作类型</th>
                                <th width="45%">操作详情</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($paginatedLogs)): ?>
                                <?php foreach ($paginatedLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="time-display">
                                                <i class="far fa-clock"></i>
                                                <?php echo htmlspecialchars($log['timestamp']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="user-tag"><?php echo htmlspecialchars($log['username']); ?></span>
                                        </td>
                                        <td>
                                            <code class="ip-address"><?php echo htmlspecialchars($log['ip']); ?></code>
                                        </td>
                                        <td>
                                            <span class="action-tag action-<?php echo strtolower($log['action']); ?>">
                                                <?php echo htmlspecialchars($log['action']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="log-details" title="<?php echo htmlspecialchars($log['details']); ?>">
                                                <?php echo htmlspecialchars(mb_substr($log['details'], 0, 80) . (mb_strlen($log['details']) > 80 ? '...' : '')); ?>
                                            </div>
                                            <?php if (mb_strlen($log['details']) > 80): ?>
                                                <button class="view-more" onclick="showLogDetails(this)">查看更多</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="fas fa-clipboard-list"></i>
                                        <h3>暂无操作日志</h3>
                                        <p>还没有任何操作记录。</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- 分页 -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-section">
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['username']) ? '&username=' . urlencode($_GET['username']) : ''; ?><?php echo isset($_GET['action_type']) ? '&action_type=' . urlencode($_GET['action_type']) : ''; ?><?php echo isset($_GET['date']) ? '&date=' . urlencode($_GET['date']) : ''; ?><?php echo isset($_GET['keyword']) ? '&keyword=' . urlencode($_GET['keyword']) : ''; ?>" class="page-arrow">◀</a>
                                <?php else: ?>
                                    <span class="page-arrow disabled">◀</span>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                if ($startPage > 1) {
                                    echo '<a href="?page=1">1</a>';
                                    if ($startPage > 2) {
                                        echo '<span class="page-dots">...</span>';
                                    }
                                }

                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    if ($i == $page) {
                                        echo '<span class="page-number current">' . $i . '</span>';
                                    } else {
                                        echo '<a href="?page=' . $i . '">' . $i . '</a>';
                                    }
                                }

                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<span class="page-dots">...</span>';
                                    }
                                    echo '<a href="?page=' . $totalPages . '">' . $totalPages . '</a>';
                                }
                                ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['username']) ? '&username=' . urlencode($_GET['username']) : ''; ?><?php echo isset($_GET['action_type']) ? '&action_type=' . urlencode($_GET['action_type']) : ''; ?><?php echo isset($_GET['date']) ? '&date=' . urlencode($_GET['date']) : ''; ?><?php echo isset($_GET['keyword']) ? '&keyword=' . urlencode($_GET['keyword']) : ''; ?>" class="page-arrow">▶</a>
                                <?php else: ?>
                                    <span class="page-arrow disabled">▶</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- 日志详情模态框 -->
    <div id="logDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> 日志详情</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- 详情内容将通过 JS 填充 -->
            </div>
            <div class="modal-footer">
                <button class="action-button secondary close-modal-btn">关闭</button>
                <button onclick="copyLogDetails()" class="action-button">
                    <i class="fas fa-copy"></i>
                    复制详情
                </button>
            </div>
        </div>
    </div>

    <!-- 脚本 -->
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            const dateFilter = document.getElementById('filter_date');
            if (!dateFilter.value) {
                dateFilter.value = new Date().toISOString().split('T')[0];
            }

            
            const logDetailsModal = document.getElementById('logDetailsModal');
            const closeButtons = document.querySelectorAll('.close-modal, .close-modal-btn');

            closeButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    logDetailsModal.style.display = 'none';
                });
            });

            window.addEventListener('click', function(e) {
                if (e.target === logDetailsModal) {
                    logDetailsModal.style.display = 'none';
                }
            });
        });

        function showLogDetails(button) {
            const row = button.closest('tr');
            const time = row.querySelector('.time-display').textContent.trim();
            const username = row.querySelector('.user-tag').textContent.trim();
            const ip = row.querySelector('.ip-address').textContent.trim();
            const action = row.querySelector('.action-tag').textContent.trim();
            const details = row.querySelector('.log-details').getAttribute('title');

            const content = `
                <div class="log-details-full">
                    <div class="detail-item">
                        <label><i class="far fa-clock"></i> 时间</label>
                        <div class="detail-value">${time}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="fas fa-user"></i> 管理员</label>
                        <div class="detail-value">${username}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="fas fa-network-wired"></i> IP地址</label>
                        <div class="detail-value">${ip}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="fas fa-cogs"></i> 操作类型</label>
                        <div class="detail-value">${action}</div>
                    </div>
                    <div class="detail-item full-width">
                        <label><i class="fas fa-info-circle"></i> 操作详情</label>
                        <div class="detail-value log-content">${details}</div>
                    </div>
                </div>
            `;

            document.getElementById('logDetailsContent').innerHTML = content;
            logDetailsModal.style.display = 'block';
        }

        function copyLogDetails() {
            const content = document.querySelector('.log-content').textContent;
            navigator.clipboard.writeText(content).then(() => {
                alert('日志详情已复制到剪贴板！');
            }).catch(err => {
                console.error('复制失败:', err);
                alert('复制失败，请手动复制。');
            });
        }

        function showLogStats() {
            alert('日志统计功能开发中...');
        }

        function refreshLogs() {
            window.location.reload();
        }

        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        
        const style = document.createElement('style');
        style.textContent = `
            .result-count {
                margin-left: 15px;
                padding: 4px 12px;
                background: rgba(0, 180, 216, 0.2);
                border: 1px solid rgba(0, 180, 216, 0.4);
                border-radius: 20px;
                font-size: 14px;
                color: #00b4d8;
            }

            .user-tag {
                display: inline-block;
                padding: 4px 8px;
                background: rgba(0, 180, 216, 0.2);
                color: #00b4d8;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
            }

            .ip-address {
                font-family: 'Courier New', monospace;
                color: #90e0ef;
                font-size: 12px;
            }

            .action-tag {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                text-transform: uppercase;
            }

            .action-login {
                background: rgba(40, 167, 69, 0.2);
                color: #6bff8d;
                border: 1px solid #28a745;
            }

            .action-logout {
                background: rgba(108, 117, 125, 0.2);
                color: #b0bec5;
                border: 1px solid #6c757d;
            }

            .action-add, .action-edit, .action-update {
                background: rgba(0, 123, 255, 0.2);
                color: #6ba3ff;
                border: 1px solid #007bff;
            }

            .action-delete, .action-clear {
                background: rgba(220, 53, 69, 0.2);
                color: #ff6b6b;
                border: 1px solid #dc3545;
            }

            .action-error, .action-fail {
                background: rgba(255, 193, 7, 0.2);
                color: #ffc107;
                border: 1px solid #ffc107;
            }

            .log-details {
                color: #cccccc;
                font-size: 13px;
                line-height: 1.4;
            }

            .view-more {
                background: transparent;
                border: none;
                color: #00b4d8;
                font-size: 11px;
                cursor: pointer;
                padding: 2px 5px;
                margin-top: 5px;
                text-decoration: underline;
            }

            .view-more:hover {
                color: #90e0ef;
            }

            .log-details-full {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .detail-item {
                margin-bottom: 15px;
            }

            .detail-item.full-width {
                grid-column: 1 / -1;
            }

            .detail-item label {
                display: flex;
                align-items: center;
                gap: 8px;
                color: #90caf9;
                font-size: 12px;
                margin-bottom: 5px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .detail-item .detail-value {
                padding: 10px;
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 6px;
                color: #ffffff;
                font-size: 14px;
                word-break: break-word;
            }

            .detail-item .log-content {
                white-space: pre-wrap;
                line-height: 1.5;
                font-family: 'Roboto', sans-serif;
            }

            .pagination-section {
                margin-top: 30px;
                display: flex;
                justify-content: center;
            }

            .pagination {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .page-arrow,
            .page-number,
            .page-dots {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 40px;
                height: 40px;
                padding: 0 5px;
                border-radius: 6px;
                font-family: 'Orbitron', monospace;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.3s ease;
                text-decoration: none;
                color: #90e0ef;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(0, 180, 216, 0.2);
            }

            .page-number:hover {
                background: rgba(0, 180, 216, 0.2);
                border-color: rgba(0, 180, 216, 0.4);
                transform: translateY(-2px);
            }

            .page-number.current {
                background: linear-gradient(135deg, #0077b6, #00b4d8);
                color: white;
                border-color: #00b4d8;
                box-shadow: 0 0 15px rgba(0, 180, 216, 0.5);
            }

            .page-arrow {
                background: rgba(0, 180, 216, 0.1);
                border: 1px solid rgba(0, 180, 216, 0.3);
            }

            .page-arrow:hover:not(.disabled) {
                background: rgba(0, 180, 216, 0.3);
                transform: translateY(-2px);
            }

            .page-arrow.disabled {
                opacity: 0.3;
                cursor: not-allowed;
            }

            .page-dots {
                background: transparent;
                border: none;
                color: #666;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>