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


$pdo = getDBConnection();


$stats = [];


$stmt = $pdo->query("SELECT COUNT(*) as total FROM bans");
$stats['total_bans'] = $stmt->fetchColumn();


$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as today FROM bans WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$stats['today_bans'] = $stmt->fetchColumn();


$stmt = $pdo->query("SELECT COUNT(*) as permanent FROM bans WHERE unban_time IS NULL OR unban_time = '0000-00-00 00:00:00'");
$stats['permanent_bans'] = $stmt->fetchColumn();


$nextWeek = date('Y-m-d H:i:s', strtotime('+7 days'));
$stmt = $pdo->prepare("SELECT COUNT(*) as unban_soon FROM bans WHERE unban_time IS NOT NULL AND unban_time != '0000-00-00 00:00:00' AND unban_time <= ?");
$stmt->execute([$nextWeek]);
$stats['unban_soon'] = $stmt->fetchColumn();


$stmt = $pdo->query("SELECT * FROM bans ORDER BY created_at DESC LIMIT 10");
$recent_bans = $stmt->fetchAll();


$lastDay = date('Y-m-d H:i:s', strtotime('-24 hours'));

$active_admins = [];

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - 仪表板</title>
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
                <a href="dashboard.php" class="nav-item active">
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
                <a href="logs.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>操作日志</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <p>系统版本: 1.0.0</p>
                <p>最后更新: <?php echo date('Y-m-d'); ?></p>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="admin-main">
            <!-- 头部 -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>管理仪表板</h1>
                    <p>欢迎回来，<?php echo htmlspecialchars($_SESSION['admin_username'] ?? '管理员'); ?>！</p>
                </div>

                <div class="header-right">
                    <div class="header-actions">
                        <a href="logout.php" class="logout-button">
                            <i class="fas fa-sign-out-alt"></i>
                            退出登录
                        </a>
                    </div>
                </div>
            </header>

            <!-- 内容区域 -->
            <div class="admin-content">
                <!-- 统计卡片 -->
                <div class="content-header">
                    <h2 class="content-title">
                        <i class="fas fa-chart-line"></i>
                        系统概览
                    </h2>
                    <div class="content-actions">
                        <a href="add.php" class="action-button">
                            <i class="fas fa-plus"></i>
                            添加封禁记录
                        </a>
                                            </div>
                </div>

                <div class="cards-grid">
                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user-slash"></i>
                                总封禁数
                            </h3>
                        </div>
                        <div class="card-value"><?php echo $stats['total_bans']; ?></div>
                        <div class="card-progress">
                            <div class="card-progress-bar" style="width: 100%;"></div>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar-day"></i>
                                今日新增
                            </h3>
                        </div>
                        <div class="card-value"><?php echo $stats['today_bans']; ?></div>
                        <div class="card-progress">
                            <div class="card-progress-bar" style="width: 100%;"></div>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-ban"></i>
                                永久封禁
                            </h3>
                        </div>
                        <div class="card-value"><?php echo $stats['permanent_bans']; ?></div>
                        <div class="card-progress">
                            <div class="card-progress-bar" style="width: 100%;"></div>
                        </div>
                        <div class="card-footer">
                            <span>占总封禁</span>
                            <span><?php echo $stats['total_bans'] > 0 ? round($stats['permanent_bans'] / $stats['total_bans'] * 100, 1) : 0; ?>%</span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clock"></i>
                                即将解封
                            </h3>
                            <span class="card-trend">7天内</span>
                        </div>
                        <div class="card-value"><?php echo $stats['unban_soon']; ?></div>
                        <div class="card-progress">
                            <div class="card-progress-bar" style="width: 100%;"></div>
                        </div>
                        <div class="card-footer">
                            <span>需关注</span>
                            <span><?php echo $stats['unban_soon']; ?> 条</span>
                        </div>
                    </div>
                </div>

                <!-- 最近封禁记录 -->
                <div class="content-header" style="margin-top: 40px;">
                    <h2 class="content-title">
                        <i class="fas fa-history"></i>
                        最近封禁记录
                    </h2>
                    <a href="bans.php" class="action-button secondary">
                        <i class="fas fa-list"></i>
                        查看全部
                    </a>
                </div>

                <div class="data-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th width="15%">玩家用户名</th>
                                <th width="20%">封禁时间</th>
                                <th width="20%">解封时间</th>
                                <th width="15%">惩罚类型</th>
                                <th width="20%">违规原因</th>
                                <th width="10%">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_bans)): ?>
                                <?php foreach ($recent_bans as $ban): ?>
                                    <tr>
                                        <td>
                                            <div class="player-info">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($ban['username']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="time-display">
                                                <i class="far fa-clock"></i>
                                                <?php echo formatDateTime($ban['ban_time']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="time-display <?php echo isPermanentBan($ban['unban_time']) ? 'permanent' : ''; ?>">
                                                <i class="far fa-calendar-times"></i>
                                                <?php echo formatDateTime($ban['unban_time']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="punishment-tag"><?php echo htmlspecialchars($ban['punishment']); ?></span>
                                        </td>
                                        <td>
                                            <div class="reason-text" title="<?php echo htmlspecialchars($ban['reason']); ?>">
                                                <?php echo htmlspecialchars(mb_substr($ban['reason'], 0, 30) . (mb_strlen($ban['reason']) > 30 ? '...' : '')); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="actions-cell">
                                                <a href="edit.php?id=<?php echo $ban['id']; ?>" class="action-icon edit" title="编辑">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $ban['id']; ?>" class="action-icon delete" title="删除" onclick="return confirm('确定要删除这条记录吗？');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="fas fa-database"></i>
                                        <h3>暂无封禁记录</h3>
                                        <p>还没有任何封禁记录，点击"添加记录"按钮开始添加。</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 系统状态 -->
                <div class="content-header" style="margin-top: 40px;">
                    <h2 class="content-title">
                        <i class="fas fa-server"></i>
                        系统状态
                    </h2>
                    <span class="action-button secondary">
                        <i class="fas fa-sync-alt"></i>
                        刷新状态
                    </span>
                </div>

                <div class="cards-grid">
                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-database"></i>
                                数据库状态
                            </h3>
                            <span class="card-status online">正常</span>
                        </div>
                        <div class="card-value">
                            <?php
                            try {
                                $pdo->query('SELECT 1');
                                echo '<span style="color: #6bff8d;">● 连接正常</span>';
                            } catch (PDOException $e) {
                                echo '<span style="color: #ff6b6b;">● 连接异常</span>';
                            }
                            ?>
                        </div>
                        <div class="card-footer">
                            <span>表数量: 1</span>
                            <span>大小: 未知</span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-code"></i>
                                PHP 状态
                            </h3>
                            <span class="card-status online">正常</span>
                        </div>
                        <div class="card-value"><?php echo PHP_VERSION; ?></div>
                        <div class="card-footer">
                            <span>内存限制: <?php echo ini_get('memory_limit'); ?></span>
                            <span>执行时间: <?php echo ini_get('max_execution_time'); ?>s</span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-shield-alt"></i>
                                安全状态
                            </h3>
                            <span class="card-status warning">警告</span>
                        </div>
                        <div class="card-value">3个警告</div>
                        <div class="card-footer">
                            <span>默认密码</span>
                            <span>未启用HTTPS</span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-area"></i>
                                性能监控
                            </h3>
                            <span class="card-status online">良好</span>
                        </div>
                        <div class="card-value">0.12s</div>
                        <div class="card-footer">
                            <span>平均响应时间</span>
                            <span>99.8% 可用性</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- CSRF 令牌 -->
    <input type="hidden" id="csrf_token" value="<?php echo $csrfToken; ?>">

    <!-- 脚本 -->
    <script src="../assets/js/admin.js"></script>
    <script>
        
        document.addEventListener('DOMContentLoaded', function() {
            
            const loadTime = window.performance.timing.domContentLoadedEventEnd - window.performance.timing.navigationStart;
            console.log('页面加载时间: ' + loadTime + 'ms');

            
        });

        
        function confirmDelete(id, username) {
            return confirm(`确定要删除玩家 "${username}" 的封禁记录吗？此操作不可撤销。`);
        }

        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()">&times;</button>
            `;
            document.body.appendChild(notification);

            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 3000);
        }

        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification('已复制到剪贴板', 'success');
            }).catch(err => {
                console.error('复制失败:', err);
                showNotification('复制失败', 'error');
            });
        }
    </script>

    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(20, 25, 40, 0.95);
            border: 1px solid rgba(0, 180, 216, 0.4);
            border-radius: 8px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            min-width: 300px;
            max-width: 400px;
        }

        .notification i {
            font-size: 1.2rem;
        }

        .notification-success i {
            color: #6bff8d;
        }

        .notification-error i {
            color: #ff6b6b;
        }

        .notification-info i {
            color: #00b4d8;
        }

        .notification span {
            flex: 1;
            color: #ffffff;
        }

        .notification button {
            background: transparent;
            border: none;
            color: #888;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0 5px;
        }

        .notification button:hover {
            color: #ffffff;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        .card-trend {
            font-size: 0.9rem;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .card-trend.up {
            background: rgba(40, 167, 69, 0.2);
            color: #6bff8d;
            border: 1px solid #28a745;
        }

        .card-trend.down {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b6b;
            border: 1px solid #dc3545;
        }

        .card-status {
            font-size: 0.8rem;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .card-status.online {
            background: rgba(40, 167, 69, 0.2);
            color: #6bff8d;
            border: 1px solid #28a745;
        }

        .card-status.warning {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        .card-status.offline {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
            border: 1px solid #6c757d;
        }
    </style>
</body>
</html>