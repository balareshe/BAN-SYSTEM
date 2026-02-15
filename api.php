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

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$isAjax) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 15;

$pdo = getDBConnection();

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

if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$sql = "SELECT * FROM bans {$whereClause} ORDER BY ban_time DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bans = $stmt->fetchAll();

$formattedBans = array_map(function($ban) {
    return [
        'id' => (int)$ban['id'],
        'username' => $ban['username'],
        'ban_time' => formatDateTime($ban['ban_time'], 'Y-m-d H:i'),
        'unban_time' => formatDateTime($ban['unban_time'], 'Y-m-d H:i'),
        'punishment' => $ban['punishment'],
        'reason' => $ban['reason'],
        'created_at' => $ban['created_at']
    ];
}, $bans);

$response = [
    'success' => true,
    'bans' => $formattedBans,
    'pagination' => [
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'totalRecords' => $totalRecords,
        'perPage' => $perPage,
        'search' => $search
    ],
    'stats' => [
        'totalRecords' => $totalRecords,
        'currentPage' => $page,
        'totalPages' => $totalPages
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
