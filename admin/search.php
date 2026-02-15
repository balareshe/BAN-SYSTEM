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


$results = [];
$totalResults = 0;
$searchParams = [];
$hasSearch = false;


if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $hasSearch = true;

    
    $username = trim($_GET['username'] ?? '');
    $punishment = trim($_GET['punishment'] ?? '');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to = trim($_GET['date_to'] ?? '');
    $permanent_only = isset($_GET['permanent_only']) ? true : false;
    $keyword = trim($_GET['keyword'] ?? '');

    
    $where = [];
    $params = [];

    if (!empty($username)) {
        $where[] = "username LIKE ?";
        $params[] = "%{$username}%";
        $searchParams['username'] = $username;
    }

    if (!empty($punishment) && $punishment !== 'all') {
        $where[] = "punishment = ?";
        $params[] = $punishment;
        $searchParams['punishment'] = $punishment;
    }

    if (!empty($date_from)) {
        $where[] = "ban_time >= ?";
        $params[] = $date_from . ' 00:00:00';
        $searchParams['date_from'] = $date_from;
    }

    if (!empty($date_to)) {
        $where[] = "ban_time <= ?";
        $params[] = $date_to . ' 23:59:59';
        $searchParams['date_to'] = $date_to;
    }

    if ($permanent_only) {
        $where[] = "(unban_time IS NULL OR unban_time = '0000-00-00 00:00:00')";
        $searchParams['permanent_only'] = true;
    }

    if (!empty($keyword)) {
        $where[] = "(reason LIKE ? OR punishment LIKE ?)";
        $params[] = "%{$keyword}%";
        $params[] = "%{$keyword}%";
        $searchParams['keyword'] = $keyword;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    
    $countSql = "SELECT COUNT(*) as total FROM bans {$whereClause}";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalResults = $stmt->fetchColumn();

    
    $sql = "SELECT * FROM bans {$whereClause} ORDER BY ban_time DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}


$stmt = $pdo->query("SELECT DISTINCT punishment FROM bans ORDER BY punishment");
$punishmentTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>高级搜索 - 后台管理</title>
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
                <a href="search.php" class="nav-item active">
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
                <p>搜索系统</p>
                <p>高级筛选</p>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="admin-main">
            <!-- 头部 -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>高级搜索</h1>
                    <p>使用多个条件精确查找封禁记录</p>
                </div>

                <div class="header-right">
                    <div class="header-actions">
                        <a href="bans.php" class="action-button secondary">
                            <i class="fas fa-list"></i>
                            返回列表
                        </a>
                        <button type="button" onclick="exportResults()" class="action-button" <?php echo $totalResults == 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-download"></i>
                            导出结果
                        </button>
                    </div>
                </div>
            </header>

            <!-- 内容区域 -->
            <div class="admin-content">
                <!-- 搜索表单 -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-filter"></i>
                        搜索条件
                    </h3>

                    <form method="GET" action="" id="searchForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">
                                    <i class="fas fa-user"></i>
                                    玩家用户名
                                </label>
                                <input type="text"
                                       id="username"
                                       name="username"
                                       placeholder="输入玩家用户名（支持模糊搜索）"
                                       value="<?php echo htmlspecialchars($searchParams['username'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="punishment">
                                    <i class="fas fa-gavel"></i>
                                    惩罚类型
                                </label>
                                <select id="punishment" name="punishment">
                                    <option value="all">所有类型</option>
                                    <?php foreach ($punishmentTypes as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (isset($searchParams['punishment']) && $searchParams['punishment'] === $type) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_from">
                                    <i class="fas fa-calendar-alt"></i>
                                    封禁时间从
                                </label>
                                <input type="date"
                                       id="date_from"
                                       name="date_from"
                                       value="<?php echo htmlspecialchars($searchParams['date_from'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="date_to">
                                    <i class="fas fa-calendar-alt"></i>
                                    封禁时间至
                                </label>
                                <input type="date"
                                       id="date_to"
                                       name="date_to"
                                       value="<?php echo htmlspecialchars($searchParams['date_to'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="keyword">
                                    <i class="fas fa-key"></i>
                                    关键词搜索
                                </label>
                                <input type="text"
                                       id="keyword"
                                       name="keyword"
                                       placeholder="在违规原因或惩罚类型中搜索"
                                       value="<?php echo htmlspecialchars($searchParams['keyword'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label" style="margin-top: 25px;">
                                    <input type="checkbox"
                                           name="permanent_only"
                                           id="permanent_only"
                                           <?php echo isset($searchParams['permanent_only']) ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    仅显示永久封禁
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="reset" class="action-button secondary">
                                <i class="fas fa-redo"></i>
                                重置条件
                            </button>
                            <button type="submit" class="action-button">
                                <i class="fas fa-search"></i>
                                开始搜索
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 搜索结果 -->
                <?php if ($hasSearch): ?>
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-search-result"></i>
                            搜索结果
                            <span class="result-count"><?php echo $totalResults; ?> 条记录</span>
                        </h3>

                        <?php if ($totalResults > 0): ?>
                            <div class="search-results">
                                <div class="results-summary">
                                    <div class="summary-grid">
                                        <div class="summary-item">
                                            <i class="fas fa-list"></i>
                                            <span>总记录数</span>
                                            <strong><?php echo $totalResults; ?></strong>
                                        </div>
                                        <div class="summary-item">
                                            <i class="fas fa-clock"></i>
                                            <span>时间范围</span>
                                            <strong>
                                                <?php echo isset($searchParams['date_from']) ? $searchParams['date_from'] : '全部'; ?>
                                                ~
                                                <?php echo isset($searchParams['date_to']) ? $searchParams['date_to'] : '现在'; ?>
                                            </strong>
                                        </div>
                                        <div class="summary-item">
                                            <i class="fas fa-chart-pie"></i>
                                            <span>显示条数</span>
                                            <strong><?php echo min(100, $totalResults); ?> / <?php echo $totalResults; ?></strong>
                                        </div>
                                        <div class="summary-item">
                                            <i class="fas fa-download"></i>
                                            <span>操作</span>
                                            <button onclick="exportResults()" class="action-button small">
                                                导出全部
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-container" style="margin-top: 20px;">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th width="15%">玩家用户名</th>
                                                <th width="15%">封禁时间</th>
                                                <th width="15%">解封时间</th>
                                                <th width="15%">惩罚类型</th>
                                                <th width="30%">违规原因</th>
                                                <th width="10%">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($results as $ban): ?>
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
                                                            <?php echo htmlspecialchars(mb_substr($ban['reason'], 0, 50) . (mb_strlen($ban['reason']) > 50 ? '...' : '')); ?>
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
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($totalResults > 100): ?>
                                    <div class="search-note">
                                        <i class="fas fa-info-circle"></i>
                                        搜索结果较多，只显示前100条记录。请使用更精确的搜索条件。
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <h3>未找到匹配的记录</h3>
                                <p>请尝试调整搜索条件或使用更宽泛的条件。</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>等待搜索</h3>
                        <p>请输入搜索条件并点击"开始搜索"按钮查找封禁记录。</p>
                        <div class="search-tips">
                            <h4><i class="fas fa-lightbulb"></i> 搜索技巧：</h4>
                            <ul>
                                <li>使用玩家用户名进行精确或模糊搜索</li>
                                <li>选择特定的惩罚类型缩小范围</li>
                                <li>使用时间范围筛选特定时期的记录</li>
                                <li>关键词搜索会在违规原因和惩罚类型中查找</li>
                                <li>勾选"仅显示永久封禁"快速筛选永久封禁记录</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- 脚本 -->
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.getElementById('searchForm');
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');

            
            if (!dateFrom.value) {
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                dateFrom.value = thirtyDaysAgo.toISOString().split('T')[0];
            }

            if (!dateTo.value) {
                dateTo.value = new Date().toISOString().split('T')[0];
            }

            
            dateFrom.addEventListener('change', function() {
                if (dateTo.value && this.value > dateTo.value) {
                    alert('开始日期不能晚于结束日期');
                    this.value = dateTo.value;
                }
            });

            dateTo.addEventListener('change', function() {
                if (dateFrom.value && this.value < dateFrom.value) {
                    alert('结束日期不能早于开始日期');
                    this.value = dateFrom.value;
                }
            });

            
            searchForm.addEventListener('submit', function(e) {
                const dateFromVal = dateFrom.value;
                const dateToVal = dateTo.value;

                if (dateFromVal && dateToVal && dateFromVal > dateToVal) {
                    e.preventDefault();
                    alert('开始日期不能晚于结束日期');
                    dateFrom.focus();
                    return false;
                }

                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 搜索中...';
                submitBtn.disabled = true;

                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 2000);
            });
        });

        function exportResults() {
            const form = document.getElementById('searchForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData).toString();

            if (confirm('确定要导出搜索结果吗？')) {
                window.location.href = `export.php?${params}`;
            }
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

            .search-results {
                margin-top: 20px;
            }

            .results-summary {
                background: rgba(30, 35, 50, 0.5);
                border: 1px solid rgba(0, 180, 216, 0.2);
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }

            .summary-item {
                text-align: center;
                padding: 15px;
                background: rgba(255, 255, 255, 0.03);
                border-radius: 8px;
                border: 1px solid rgba(255, 255, 255, 0.05);
            }

            .summary-item i {
                font-size: 2rem;
                color: #00b4d8;
                margin-bottom: 10px;
                display: block;
            }

            .summary-item span {
                display: block;
                color: #90e0ef;
                font-size: 12px;
                margin-bottom: 5px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .summary-item strong {
                color: #ffffff;
                font-size: 18px;
                font-family: 'Orbitron', monospace;
            }

            .search-note {
                margin-top: 20px;
                padding: 15px;
                background: rgba(255, 193, 7, 0.1);
                border: 1px solid rgba(255, 193, 7, 0.3);
                border-radius: 8px;
                color: #ffc107;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .search-tips {
                margin-top: 30px;
                padding: 20px;
                background: rgba(30, 35, 50, 0.5);
                border-radius: 10px;
                border: 1px solid rgba(0, 180, 216, 0.2);
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
            }

            .search-tips h4 {
                color: #00b4d8;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .search-tips ul {
                margin-left: 20px;
                color: #b0bec5;
                line-height: 1.8;
            }

            .search-tips li {
                margin-bottom: 8px;
            }

            .action-button.small {
                padding: 8px 15px;
                font-size: 12px;
                margin-top: 5px;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>