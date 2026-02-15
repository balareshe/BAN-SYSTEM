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


$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;


$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(username LIKE ? OR reason LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';


$countSql = "SELECT COUNT(*) as total FROM bans {$whereClause}";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);


if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}


$offset = ($page - 1) * $perPage;
$sql = "SELECT * FROM bans {$whereClause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bans = $stmt->fetchAll();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validateCsrfToken();

    $action = $_POST['action'];
    $selected_ids = $_POST['selected_ids'] ?? [];

    if (empty($selected_ids)) {
        $operation_error = '请先选择要操作的记录';
    } else {
        $ids = array_map('intval', $selected_ids);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';

        switch ($action) {
            case 'delete':
                try {
                    $sql = "DELETE FROM bans WHERE id IN ({$placeholders})";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($ids);
                    $affected = $stmt->rowCount();

                    logAdminAction('BATCH_DELETE', "批量删除 {$affected} 条封禁记录");

                    $operation_success = "成功删除 {$affected} 条记录";

                    header("Location: bans.php?page={$page}&search=" . urlencode($search) . "&operation=success&message=" . urlencode("成功删除 {$affected} 条记录"));
                    exit;
                } catch (PDOException $e) {
                    $operation_error = '删除失败: ' . $e->getMessage();
                }
                break;

            case 'export':

                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="bans_export_' . date('Ymd_His') . '.json"');
                $exportData = [];
                $stmt = $pdo->prepare("SELECT * FROM bans WHERE id IN ({$placeholders})");
                $stmt->execute($ids);
                $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit;

            default:
                $operation_error = '未知的操作类型';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>封禁记录管理 - 后台管理</title>
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
                <a href="bans.php" class="nav-item active">
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
                <p>记录总数: <?php echo $totalRecords; ?></p>
                <p><?php echo date('Y-m-d H:i'); ?></p>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="admin-main">
            <!-- 头部 -->
            <header class="admin-header">
                <div class="header-left">
                    <h1>封禁记录管理</h1>
                    <p>共 <?php echo $totalRecords; ?> 条记录，当前第 <?php echo $page; ?> 页/共 <?php echo $totalPages; ?> 页</p>
                </div>

                <div class="header-right">
                    <div class="header-actions">
                        <a href="add.php" class="action-button">
                            <i class="fas fa-plus"></i>
                            添加记录
                        </a>
                        <a href="dashboard.php" class="action-button secondary">
                            <i class="fas fa-home"></i>
                            返回主页
                        </a>
                    </div>
                </div>
            </header>

            <!-- 内容区域 -->
            <div class="admin-content">
                <?php if (isset($operation_success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($operation_success); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($operation_error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($operation_error); ?>
                    </div>
                <?php endif; ?>

                <?php
                $operation_message = $_GET['message'] ?? '';
                $operation_status = $_GET['operation'] ?? '';
                if ($operation_status === 'success' && !empty($operation_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($operation_message); ?>
                    </div>
                <?php endif; ?>

                <div class="data-table-container">
                    <!-- 表格操作栏 -->
                    <div class="table-actions">
                        <form method="GET" action="" class="table-search">
                            <i class="fas fa-search"></i>
                            <input type="text"
                                   name="search"
                                   placeholder="搜索玩家用户名或违规原因..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" style="display: none;">搜索</button>
                        </form>

                        <?php if (!empty($search)): ?>
                            <a href="bans.php" class="action-button small secondary">
                                <i class="fas fa-times"></i>
                                清除搜索
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- 数据表格 -->
                    <table class="admin-table" id="bansTable">
                        <thead>
                            <tr>
                                <th width="5%">
                                    <input type="checkbox" id="selectAllHeader">
                                </th>
                                <th width="15%">玩家用户名</th>
                                <th width="15%">封禁时间</th>
                                <th width="15%">解封时间</th>
                                <th width="15%">惩罚类型</th>
                                <th width="25%">违规原因</th>
                                <th width="10%">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($bans)): ?>
                                <?php foreach ($bans as $ban): ?>
                                    <tr data-id="<?php echo $ban['id']; ?>">
                                        <td class="row-checkbox">
                                            <input type="checkbox" name="selected_ids[]" value="<?php echo $ban['id']; ?>" class="row-select">
                                        </td>
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
                                                <a href="delete.php?id=<?php echo $ban['id']; ?>" class="action-icon delete" title="删除" onclick="return confirmDelete(<?php echo $ban['id']; ?>, '<?php echo addslashes($ban['username']); ?>');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-database"></i>
                                        <h3>暂无封禁记录</h3>
                                        <p>还没有任何封禁记录，点击"添加记录"按钮开始添加。</p>
                                        <a href="add.php" class="action-button" style="margin-top: 20px;">
                                            <i class="fas fa-plus"></i>
                                            添加第一条记录
                                        </a>
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
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-arrow">◀</a>
                                <?php else: ?>
                                    <span class="page-arrow disabled">◀</span>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                if ($startPage > 1) {
                                    echo '<a href="?page=1&search=' . urlencode($search) . '">1</a>';
                                    if ($startPage > 2) {
                                        echo '<span class="page-dots">...</span>';
                                    }
                                }

                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    if ($i == $page) {
                                        echo '<span class="page-number current">' . $i . '</span>';
                                    } else {
                                        echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '" class="page-number">' . $i . '</a>';
                                    }
                                }

                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<span class="page-dots">...</span>';
                                    }
                                    echo '<a href="?page=' . $totalPages . '&search=' . urlencode($search) . '">' . $totalPages . '</a>';
                                }
                                ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-arrow">▶</a>
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

    <!-- 详情模态框 -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> 封禁记录详情</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- 详情内容将通过 JS 填充 -->
            </div>
            <div class="modal-footer">
                <button class="action-button secondary close-modal-btn">关闭</button>
                <a href="#" id="editLink" class="action-button">
                    <i class="fas fa-edit"></i>
                    编辑记录
                </a>
            </div>
        </div>
    </div>

    <!-- 脚本 -->
    <script src="../assets/js/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.table-search input');

            
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.form.submit();
                }
            });

            
            if (searchInput.value) {
                searchInput.focus();
                searchInput.select();
            }

            
            const modal = document.getElementById('detailsModal');
            const closeButtons = document.querySelectorAll('.close-modal, .close-modal-btn');
            const modalBody = document.getElementById('modalBody');
            const editLink = document.getElementById('editLink');

            function showDetails(banId) {
                
                modalBody.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <div class="loading-spinner"></div>
                        <p style="margin-top: 15px; color: #00b4d8;">加载中...</p>
                    </div>
                `;
                modal.style.display = 'block';

                
                setTimeout(() => {
                    
                    
                    const row = document.querySelector(`tr[data-id="${banId}"]`);
                    if (row) {
                        const username = row.querySelector('.player-info').textContent.trim();
                        const banTime = row.querySelectorAll('.time-display')[0].textContent.trim();
                        const unbanTime = row.querySelectorAll('.time-display')[1]?.textContent.trim() || '永久封禁';
                        const punishment = row.querySelector('.punishment-tag').textContent.trim();
                        const reason = row.querySelector('.reason-text').getAttribute('title') || row.querySelector('.reason-text').textContent.trim();

                        modalBody.innerHTML = `
                            <div class="details-grid">
                                <div class="detail-item">
                                    <label><i class="fas fa-user"></i> 玩家用户名</label>
                                    <div class="detail-value">${username}</div>
                                </div>
                                <div class="detail-item">
                                    <label><i class="fas fa-clock"></i> 封禁时间</label>
                                    <div class="detail-value">${banTime}</div>
                                </div>
                                <div class="detail-item">
                                    <label><i class="fas fa-calendar-check"></i> 解封时间</label>
                                    <div class="detail-value ${unbanTime === '永久封禁' ? 'permanent' : ''}">${unbanTime}</div>
                                </div>
                                <div class="detail-item">
                                    <label><i class="fas fa-gavel"></i> 惩罚类型</label>
                                    <div class="detail-value">${punishment}</div>
                                </div>
                                <div class="detail-item full-width">
                                    <label><i class="fas fa-exclamation-triangle"></i> 违规行为描述</label>
                                    <div class="detail-value reason-full">${reason}</div>
                                </div>
                                <div class="detail-item">
                                    <label><i class="fas fa-database"></i> 记录ID</label>
                                    <div class="detail-value">${banId}</div>
                                </div>
                                <div class="detail-item">
                                    <label><i class="fas fa-calendar"></i> 创建时间</label>
                                    <div class="detail-value">${new Date().toLocaleString('zh-CN')}</div>
                                </div>
                            </div>
                        `;

                        editLink.href = `edit.php?id=${banId}`;
                    } else {
                        modalBody.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #ff6b6b;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 20px;"></i>
                                <h3>记录未找到</h3>
                                <p>无法找到该封禁记录的详细信息。</p>
                            </div>
                        `;
                        editLink.style.display = 'none';
                    }
                }, 500);
            }

            closeButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            });

            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        function confirmDelete(id, username) {
            return confirm(`确定要删除玩家 "${username}" 的封禁记录吗？此操作不可撤销。`);
        }

        
        const style = document.createElement('style');
        style.textContent = `
            .action-select {
                padding: 10px 15px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(0, 180, 216, 0.4);
                border-radius: 8px;
                color: #ffffff;
                font-size: 14px;
                min-width: 120px;
            }

            .action-select:focus {
                outline: none;
                border-color: #00b4d8;
            }

            .action-button.small {
                padding: 10px 15px;
                font-size: 12px;
            }

            .details-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }

            .detail-item {
                margin-bottom: 20px;
            }

            .detail-item.full-width {
                grid-column: 1 / -1;
            }

            .detail-item label {
                display: flex;
                align-items: center;
                gap: 8px;
                color: #90caf9;
                font-size: 14px;
                margin-bottom: 8px;
                font-weight: 500;
            }

            .detail-item label i {
                font-size: 1rem;
            }

            .detail-value {
                padding: 12px 15px;
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 6px;
                color: #ffffff;
                font-size: 15px;
                word-break: break-word;
            }

            .detail-value.permanent {
                color: #ff6b6b;
                font-weight: 500;
            }

            .detail-value.reason-full {
                line-height: 1.6;
                white-space: pre-wrap;
            }

            @media (max-width: 768px) {
                .details-grid {
                    grid-template-columns: 1fr;
                }
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