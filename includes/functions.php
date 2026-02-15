<?php
/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */

function safe_output($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function getPaginationParams($page, $perPage = 15) {
    $page = max(1, intval($page));
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'offset' => $offset, 'perPage' => $perPage];
}

function generatePagination($currentPage, $totalPages, $urlTemplate = '?page=') {
    if ($totalPages <= 1) return '';

    $html = '<div class="pagination">';

    if ($currentPage > 1) {
        $html .= '<a href="' . $urlTemplate . ($currentPage - 1) . '" class="page-arrow">◀</a>';
    } else {
        $html .= '<span class="page-arrow disabled">◀</span>';
    }

    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);

    if ($startPage > 1) {
        $html .= '<a href="' . $urlTemplate . '1">1</a>';
        if ($startPage > 2) {
            $html .= '<span class="page-dots">...</span>';
        }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<span class="page-number current">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $urlTemplate . $i . '" class="page-number">' . $i . '</a>';
        }
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<span class="page-dots">...</span>';
        }
        $html .= '<a href="' . $urlTemplate . $totalPages . '">' . $totalPages . '</a>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $urlTemplate . ($currentPage + 1) . '" class="page-arrow">▶</a>';
    } else {
        $html .= '<span class="page-arrow disabled">▶</span>';
    }

    $html .= '</div>';
    return $html;
}

function formatDateTime($datetime, $format = 'Y-m-d H:i') {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return '永久封禁';
    }
    return date($format, strtotime($datetime));
}

function isPermanentBan($unbanTime) {
    return empty($unbanTime) || $unbanTime == '0000-00-00 00:00:00';
}

function getClientIP() {
    $ip = '';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
