<?php

/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */



require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';


$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 15; 


try {
    $pdo = getDBConnection();

    
    $stmt = $pdo->query("SHOW TABLES LIKE 'bans'");
    if ($stmt->rowCount() === 0) {
        
        header('Location: install.php');
        exit;
    }
} catch (Exception $e) {
    
    header('Location: install.php');
    exit;
}


$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "username LIKE ?";
    $params[] = "%{$search}%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';


$countSql = "SELECT COUNT(*) as total FROM bans {$whereClause}";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);


if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;


$offset = ($page - 1) * $perPage;
$sql = "SELECT * FROM bans {$whereClause} ORDER BY ban_time DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bans = $stmt->fetchAll();


if (empty($bans)) {
    
    $emptyRows = array_fill(0, min($perPage, 15), null);
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft 服务器 - 封禁公示系统</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https:
    <link href="https:
    <script src="https:
</head>
<body>
    <div class="cyber-container">
        <!-- 头部 -->
        <header class="cyber-header">
            <div class="header-glitch" data-text="MINECRAFT BAN SYSTEM">MINECRAFT BAN SYSTEM</div>
            <div class="header-subtitle">服务器违规封禁公示平台</div>
            <div class="header-line"></div>
        </header>

        <!-- 搜索区域 -->
        <div class="search-section">
            <div class="cyber-card">
                <div class="card-title">
                    <i class="fas fa-search"></i> 玩家搜索
                </div>
                <form id="searchForm" class="search-form">
                    <div class="input-group">
                        <input type="text"
                               id="searchInput"
                               name="search"
                               placeholder="输入玩家用户名进行搜索..."
                               value="<?php echo safe_output($search); ?>"
                               autocomplete="off">
                        <button type="submit" class="cyber-button">
                            <i class="fas fa-search"></i> 搜索
                        </button>
                        <button type="button" id="clearSearch" class="cyber-button secondary">
                            <i class="fas fa-times"></i> 清空
                        </button>
                    </div>
                </form>
                <div class="stats">
                    <div class="stat-item">
                        <i class="fas fa-user-slash"></i>
                        <span>总封禁记录: <strong><?php echo $totalRecords; ?></strong></span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-file-alt"></i>
                        <span>当前页: <strong><?php echo $page; ?></strong>/<?php echo $totalPages; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 数据表格 -->
        <div class="table-section">
            <div class="cyber-card">
                <div class="card-title">
                    <i class="fas fa-table"></i> 封禁记录列表
                </div>
                <div class="table-container">
                    <table class="cyber-table" id="bansTable">
                        <thead>
                            <tr>
                                <th width="15%">用户名</th>
                                <th width="20%">封禁时间</th>
                                <th width="20%">解封时间</th>
                                <th width="15%">违规惩罚</th>
                                <th width="30%">违规行为</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (!empty($bans)): ?>
                                <?php foreach ($bans as $ban): ?>
                                    <tr class="cyber-table-row" data-id="<?php echo $ban['id']; ?>">
                                        <td>
                                            <div class="player-info">
                                                <i class="fas fa-user"></i>
                                                <span class="player-name"><?php echo safe_output($ban['username']); ?></span>
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
                                            <span class="punishment-tag"><?php echo safe_output($ban['punishment']); ?></span>
                                        </td>
                                        <td>
                                            <div class="reason-text"><?php echo safe_output($ban['reason']); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif (isset($emptyRows)): ?>
                                <!-- 显示空白行保持表格结构 -->
                                <?php foreach ($emptyRows as $index): ?>
                                    <tr class="cyber-table-row empty-row">
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                        <td>&nbsp;</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">
                                        <i class="fas fa-database"></i>
                                        暂无封禁记录
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 分页控件 -->
                <?php if ($totalPages > 0): ?>
                    <div class="pagination-section">
                        <?php echo generatePagination($page, $totalPages, '?page=' . (!empty($search) ? '&search=' . urlencode($search) : '')); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 页脚 -->
        <footer class="cyber-footer">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="#"><i class="fas fa-server"></i> 服务器状态</a>
                    <a href="#"><i class="fas fa-question-circle"></i> 帮助中心</a>
                    <a href="#"><i class="fas fa-shield-alt"></i> 社区规则</a>
                    <a href="/admin/index.php"><i class="fas fa-cog"></i> 管理面板</a>
                </div>
                <div class="footer-info">
                    <p>Minecraft 封禁公示系统 &copy; <?php echo date('Y'); ?> | Powered by PHP + MySQL</p>
                    <p class="cyber-text">实时数据更新 | 公平公正公开</p>
                </div>
            </div>
        </footer>
    </div>

    <!-- 脚本 -->
    <script src="assets/js/main.js"></script>
    <script>
        
        const currentPage = <?php echo $page; ?>;
        const totalPages = <?php echo $totalPages; ?>;
        const searchQuery = "<?php echo addslashes($search); ?>";

        
        const CONFIG = {
            perPage: <?php echo $perPage; ?>,
            ajaxEnabled: true,
            apiUrl: 'api.php'
        };
    </script>
</body>
</html>