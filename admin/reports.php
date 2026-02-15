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


$period = isset($_GET['period']) ? $_GET['period'] : '30days';
$dateFormat = 'Y-m-d';

switch ($period) {
    case '7days':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $groupFormat = '%Y-%m-%d';
        $interval = '1 DAY';
        break;
    case '30days':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $groupFormat = '%Y-%m-%d';
        $interval = '1 DAY';
        break;
    case '90days':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        $groupFormat = '%Y-%m-%d';
        $interval = '1 DAY';
        break;
    case '1year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        $groupFormat = '%Y-%m';
        $interval = '1 MONTH';
        break;
    default:
        $period = '30days';
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $groupFormat = '%Y-%m-%d';
        $interval = '1 DAY';
}


$sql = "SELECT
            DATE_FORMAT(ban_time, '{$groupFormat}') as date,
            COUNT(*) as count,
            SUM(CASE WHEN unban_time IS NULL OR unban_time = '0000-00-00 00:00:00' THEN 1 ELSE 0 END) as permanent,
            SUM(CASE WHEN unban_time IS NOT NULL AND unban_time != '0000-00-00 00:00:00' THEN 1 ELSE 0 END) as temporary
        FROM bans
        WHERE ban_time >= ?
        GROUP BY DATE_FORMAT(ban_time, '{$groupFormat}')
        ORDER BY date";

$stmt = $pdo->prepare($sql);
$stmt->execute([$startDate . ' 00:00:00']);
$dailyStats = $stmt->fetchAll();


$sql = "SELECT
            punishment,
            COUNT(*) as count,
            COUNT(*) * 100.0 / (SELECT COUNT(*) FROM bans WHERE ban_time >= ?) as percentage
        FROM bans
        WHERE ban_time >= ?
        GROUP BY punishment
        ORDER BY count DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$startDate . ' 00:00:00', $startDate . ' 00:00:00']);
$punishmentStats = $stmt->fetchAll();


$sql = "SELECT
            CASE
                WHEN unban_time IS NULL OR unban_time = '0000-00-00 00:00:00' THEN '永久封禁'
                WHEN DATEDIFF(unban_time, ban_time) <= 1 THEN '1天以内'
                WHEN DATEDIFF(unban_time, ban_time) <= 7 THEN '1-7天'
                WHEN DATEDIFF(unban_time, ban_time) <= 30 THEN '8-30天'
                ELSE '30天以上'
            END as duration_range,
            COUNT(*) as count
        FROM bans
        WHERE ban_time >= ?
        GROUP BY duration_range
        ORDER BY
            CASE duration_range
                WHEN '永久封禁' THEN 1
                WHEN '1天以内' THEN 2
                WHEN '1-7天' THEN 3
                WHEN '8-30天' THEN 4
                ELSE 5
            END";
$stmt = $pdo->prepare($sql);
$stmt->execute([$startDate . ' 00:00:00']);
$durationStats = $stmt->fetchAll();


$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$thisWeekStart = date('Y-m-d', strtotime('monday this week'));
$thisMonthStart = date('Y-m-01');

$stats = [];


$sql = "SELECT COUNT(*) as count FROM bans WHERE DATE(ban_time) = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$today]);
$stats['today'] = $stmt->fetchColumn();


$stmt->execute([$yesterday]);
$stats['yesterday'] = $stmt->fetchColumn();


$sql = "SELECT COUNT(*) as count FROM bans WHERE DATE(ban_time) >= ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$thisWeekStart]);
$stats['this_week'] = $stmt->fetchColumn();


$stmt->execute([$thisMonthStart]);
$stats['this_month'] = $stmt->fetchColumn();


$sql = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN unban_time IS NULL OR unban_time = '0000-00-00 00:00:00' THEN 1 ELSE 0 END) as permanent_total,
            AVG(CASE WHEN unban_time IS NOT NULL AND unban_time != '0000-00-00 00:00:00' THEN DATEDIFF(unban_time, ban_time) END) as avg_duration
        FROM bans";
$stmt = $pdo->query($sql);
$totalStats = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>统计报表 - 后台管理</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
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
                <a href="reports.php" class="nav-item active">
                    <i class="fas fa-chart-bar"></i>
                    <span>统计报表</span>
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
                <p>数据统计</p>
                <p><?php echo date('Y-m-d'); ?></p>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="admin-main">
            <!-- 头部 -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>统计报表</h1>
                    <p>封禁数据统计与分析</p>
                </div>

                <div class="header-right">
                    <div class="header-actions">
                        <select id="periodSelect" class="action-select" onchange="changePeriod(this.value)">
                            <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>最近7天</option>
                            <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>最近30天</option>
                            <option value="90days" <?php echo $period === '90days' ? 'selected' : ''; ?>>最近90天</option>
                            <option value="1year" <?php echo $period === '1year' ? 'selected' : ''; ?>>最近1年</option>
                        </select>
                        <button onclick="exportReport()" class="action-button">
                            <i class="fas fa-file-export"></i>
                            导出报表
                        </button>
                    </div>
                </div>
            </header>

            <!-- 内容区域 -->
            <div class="admin-content">
                <!-- 关键指标 -->
                <div class="content-header">
                    <h2 class="content-title">
                        <i class="fas fa-tachometer-alt"></i>
                        关键指标
                    </h2>
                    <span class="action-button secondary" onclick="refreshStats()">
                        <i class="fas fa-sync-alt"></i>
                        刷新数据
                    </span>
                </div>

                <div class="cards-grid">
                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar-day"></i>
                                今日新增
                            </h3>
                            <span class="card-trend <?php echo $stats['today'] > $stats['yesterday'] ? 'up' : 'down'; ?>">
                                <?php echo $stats['today'] > $stats['yesterday'] ? '+' : ''; ?><?php echo $stats['today'] - $stats['yesterday']; ?>
                            </span>
                        </div>
                        <div class="card-value"><?php echo $stats['today']; ?></div>
                        <div class="card-footer">
                            <span>较昨日</span>
                            <span><?php echo $stats['yesterday']; ?></span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar-week"></i>
                                本周累计
                            </h3>
                            <span class="card-trend up">+<?php echo round($stats['this_week'] / 7); ?>/天</span>
                        </div>
                        <div class="card-value"><?php echo $stats['this_week']; ?></div>
                        <div class="card-footer">
                            <span>日均</span>
                            <span><?php echo round($stats['this_week'] / 7, 1); ?></span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar-month"></i>
                                本月累计
                            </h3>
                            <span class="card-trend up">+<?php echo round($stats['this_month'] / date('j')); ?>/天</span>
                        </div>
                        <div class="card-value"><?php echo $stats['this_month']; ?></div>
                        <div class="card-footer">
                            <span>日均</span>
                            <span><?php echo round($stats['this_month'] / date('j'), 1); ?></span>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-database"></i>
                                永久封禁
                            </h3>
                            <span class="card-trend">占比</span>
                        </div>
                        <div class="card-value"><?php echo $totalStats['permanent_total'] ?? 0; ?></div>
                        <div class="card-footer">
                            <span>占总封禁</span>
                            <span><?php echo $totalStats['total'] > 0 ? round(($totalStats['permanent_total'] ?? 0) / $totalStats['total'] * 100, 1) : 0; ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- 图表区域 -->
                <div class="content-header" style="margin-top: 40px;">
                    <h2 class="content-title">
                        <i class="fas fa-chart-line"></i>
                        封禁趋势
                    </h2>
                    <div class="chart-controls">
                        <button onclick="toggleChartType()" class="action-button small">
                            <i class="fas fa-exchange-alt"></i>
                            切换图表
                        </button>
                    </div>
                </div>

                <div class="charts-container">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h4><i class="fas fa-chart-bar"></i> 每日封禁数量</h4>
                            <span>时间范围: <?php echo $period === '7days' ? '7天' : ($period === '30days' ? '30天' : ($period === '90days' ? '90天' : '1年')); ?></span>
                        </div>
                        <div class="chart-body">
                            <canvas id="dailyChart" height="300"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h4><i class="fas fa-chart-line"></i> 每日封禁统计</h4>
                            <span>趋势分析</span>
                        </div>
                        <div class="chart-body">
                            <canvas id="dailyChart" height="300"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-header">
                            <h4><i class="fas fa-chart-pie"></i> 惩罚类型分布</h4>
                            <span>占比统计</span>
                        </div>
                        <div class="chart-body">
                            <canvas id="punishmentChart" height="300"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h4><i class="fas fa-chart-pie"></i> 封禁时长分布</h4>
                            <span>时长分析</span>
                        </div>
                        <div class="chart-body">
                            <canvas id="durationChart" height="300"></canvas>
                        </div>
                    </div>

                    <div class="chart-card full-width">
                        <div class="chart-header">
                            <h4><i class="fas fa-table"></i> 详细数据</h4>
                            <span>原始数据表格</span>
                        </div>
                        <div class="chart-body">
                            <div class="table-container">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>日期</th>
                                            <th>封禁总数</th>
                                            <th>永久封禁</th>
                                            <th>临时封禁</th>
                                            <th>永久占比</th>
                                            <th>趋势</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($dailyStats)): ?>
                                            <?php foreach ($dailyStats as $stat): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($stat['date']); ?></td>
                                                    <td><?php echo $stat['count']; ?></td>
                                                    <td><?php echo $stat['permanent']; ?></td>
                                                    <td><?php echo $stat['temporary']; ?></td>
                                                    <td>
                                                        <?php echo $stat['count'] > 0 ? round($stat['permanent'] / $stat['count'] * 100, 1) : 0; ?>%
                                                    </td>
                                                    <td>
                                                        <?php if ($stat['count'] > 0): ?>
                                                            <span class="trend-indicator <?php echo $stat['permanent'] > 0 ? 'up' : 'neutral'; ?>">
                                                                <?php echo $stat['permanent'] > 0 ? '↑' : '→'; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="empty-state">
                                                    <i class="fas fa-database"></i>
                                                    <p>暂无统计数据</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 惩罚类型详细统计 -->
                <div class="content-header" style="margin-top: 40px;">
                    <h2 class="content-title">
                        <i class="fas fa-list"></i>
                        惩罚类型统计
                    </h2>
                </div>

                <div class="data-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>惩罚类型</th>
                                <th>数量</th>
                                <th>占比</th>
                                <th>最近使用</th>
                                <th>趋势</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($punishmentStats)): ?>
                                <?php foreach ($punishmentStats as $stat): ?>
                                    <tr>
                                        <td>
                                            <span class="punishment-tag"><?php echo htmlspecialchars($stat['punishment']); ?></span>
                                        </td>
                                        <td><?php echo $stat['count']; ?></td>
                                        <td>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="width: <?php echo min(100, $stat['percentage']); ?>%;"></div>
                                                <span><?php echo round($stat['percentage'], 1); ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            
                                            $sql = "SELECT MAX(ban_time) as last_used FROM bans WHERE punishment = ? AND ban_time >= ?";
                                            $stmt = $pdo->prepare($sql);
                                            $stmt->execute([$stat['punishment'], $startDate . ' 00:00:00']);
                                            $lastUsed = $stmt->fetchColumn();
                                            echo $lastUsed ? formatDateTime($lastUsed, 'Y-m-d') : '从未使用';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($stat['percentage'] > 10): ?>
                                                <span class="trend-indicator up">高频</span>
                                            <?php elseif ($stat['percentage'] > 5): ?>
                                                <span class="trend-indicator neutral">中频</span>
                                            <?php else: ?>
                                                <span class="trend-indicator down">低频</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="fas fa-database"></i>
                                        <p>暂无惩罚类型统计</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- 脚本 -->
    <script src="../assets/js/admin.js"></script>
    <script>
        
        const dailyStats = <?php echo json_encode($dailyStats); ?>;
        const punishmentStats = <?php echo json_encode($punishmentStats); ?>;
        const durationStats = <?php echo json_encode($durationStats); ?>;

        let chartType = 'line'; 

        document.addEventListener('DOMContentLoaded', function() {
            renderCharts();
        });

        function renderCharts() {
            
            const dailyCtx = document.getElementById('dailyChart').getContext('2d');
            const dates = dailyStats.map(stat => stat.date);
            const counts = dailyStats.map(stat => stat.count);
            const permanent = dailyStats.map(stat => stat.permanent);
            const temporary = dailyStats.map(stat => stat.temporary);

            new Chart(dailyCtx, {
                type: chartType,
                data: {
                    labels: dates,
                    datasets: [
                        {
                            label: '总封禁数',
                            data: counts,
                            borderColor: '#00b4d8',
                            backgroundColor: 'rgba(0, 180, 216, 0.1)',
                            borderWidth: 2,
                            fill: true
                        },
                        {
                            label: '永久封禁',
                            data: permanent,
                            borderColor: '#ff6b6b',
                            backgroundColor: 'rgba(255, 107, 107, 0.1)',
                            borderWidth: 2,
                            fill: true
                        },
                        {
                            label: '临时封禁',
                            data: temporary,
                            borderColor: '#6bff8d',
                            backgroundColor: 'rgba(107, 255, 141, 0.1)',
                            borderWidth: 2,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#ffffff',
                                font: {
                                    family: "'Roboto', sans-serif"
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: '每日封禁统计',
                            color: '#00b4d8',
                            font: {
                                size: 16,
                                family: "'Orbitron', monospace"
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: '#90e0ef'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#90e0ef'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        }
                    }
                }
            });

            
            const punishmentCtx = document.getElementById('punishmentChart').getContext('2d');
            const punishmentLabels = punishmentStats.map(stat => stat.punishment);
            const punishmentData = punishmentStats.map(stat => stat.count);

            new Chart(punishmentCtx, {
                type: 'doughnut',
                data: {
                    labels: punishmentLabels,
                    datasets: [{
                        data: punishmentData,
                        backgroundColor: [
                            '#00b4d8', '#9b51e0', '#6bff8d', '#ffc107', '#ff6b6b',
                            '#36a2eb', '#ff6384', '#4bc0c0', '#9966ff', '#ff9f40'
                        ],
                        borderWidth: 2,
                        borderColor: 'rgba(255, 255, 255, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#ffffff',
                                font: {
                                    family: "'Roboto', sans-serif"
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: '惩罚类型分布',
                            color: '#00b4d8',
                            font: {
                                size: 16,
                                family: "'Orbitron', monospace"
                            }
                        }
                    }
                }
            });

            
            const durationCtx = document.getElementById('durationChart').getContext('2d');
            const durationLabels = durationStats.map(stat => stat.duration_range);
            const durationData = durationStats.map(stat => stat.count);

            new Chart(durationCtx, {
                type: 'pie',
                data: {
                    labels: durationLabels,
                    datasets: [{
                        data: durationData,
                        backgroundColor: [
                            '#ff6b6b', '#ffc107', '#6bff8d', '#00b4d8', '#9b51e0'
                        ],
                        borderWidth: 2,
                        borderColor: 'rgba(255, 255, 255, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#ffffff',
                                font: {
                                    family: "'Roboto', sans-serif"
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: '封禁时长分布',
                            color: '#00b4d8',
                            font: {
                                size: 16,
                                family: "'Orbitron', monospace"
                            }
                        }
                    }
                }
            });
        }

        function changePeriod(period) {
            window.location.href = `?period=${period}`;
        }

        function toggleChartType() {
            chartType = chartType === 'line' ? 'bar' : 'line';

            const chartContainer = document.getElementById('dailyChart').parentElement;
            const newCanvas = document.createElement('canvas');
            newCanvas.id = 'dailyChart';
            newCanvas.height = 300;
            chartContainer.innerHTML = '';
            chartContainer.appendChild(newCanvas);
            renderCharts();
        }

        function refreshStats() {
            const period = document.getElementById('periodSelect').value;
            window.location.reload();
        }

        function exportReport() {
            const period = document.getElementById('periodSelect').value;
            if (confirm('确定要导出当前报表吗？')) {
                window.location.href = `export_report.php?period=${period}`;
            }
        }

        
        const style = document.createElement('style');
        style.textContent = `
            .charts-container {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 25px;
                margin-bottom: 40px;
            }

            .chart-card {
                background: rgba(20, 25, 40, 0.8);
                border: 1px solid rgba(0, 180, 216, 0.3);
                border-radius: 12px;
                padding: 20px;
                backdrop-filter: blur(10px);
            }

            .chart-card.full-width {
                grid-column: 1 / -1;
            }

            .chart-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid rgba(0, 180, 216, 0.2);
            }

            .chart-header h4 {
                font-family: 'Orbitron', monospace;
                color: #00b4d8;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .chart-header span {
                color: #90e0ef;
                font-size: 12px;
            }

            .chart-body {
                height: 300px;
                position: relative;
            }

            .chart-controls {
                display: flex;
                gap: 10px;
            }

            .action-select {
                padding: 10px 15px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(0, 180, 216, 0.4);
                border-radius: 8px;
                color: #ffffff;
                font-size: 14px;
                min-width: 120px;
            }

            .progress-bar-container {
                width: 100%;
                height: 20px;
                background: rgba(255, 255, 255, 0.05);
                border-radius: 10px;
                overflow: hidden;
                position: relative;
            }

            .progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #0077b6, #00b4d8);
                border-radius: 10px;
                transition: width 0.3s ease;
            }

            .progress-bar-container span {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #ffffff;
                font-size: 12px;
                font-weight: 500;
            }

            .trend-indicator {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
            }

            .trend-indicator.up {
                background: rgba(40, 167, 69, 0.2);
                color: #6bff8d;
                border: 1px solid #28a745;
            }

            .trend-indicator.down {
                background: rgba(220, 53, 69, 0.2);
                color: #ff6b6b;
                border: 1px solid #dc3545;
            }

            .trend-indicator.neutral {
                background: rgba(108, 117, 125, 0.2);
                color: #b0bec5;
                border: 1px solid #6c757d;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>