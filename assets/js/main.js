/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */



class BanSystem {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupAjax();
        this.checkUrlParams();
    }

    bindEvents() {
        
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => this.handleSearch(e));
        }

        
        const clearBtn = document.getElementById('clearSearch');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearSearch());
        }

        
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            let timeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    if (e.target.value.length >= 2 || e.target.value.length === 0) {
                        this.handleSearch(null, false);
                    }
                }, 500);
            });
        }

        
        document.addEventListener('click', (e) => {
            const paginationLink = e.target.closest('.pagination a');
            if (paginationLink) {
                e.preventDefault();
                const url = new URL(paginationLink.href);
                const page = url.searchParams.get('page') || 1;
                const search = url.searchParams.get('search') || '';
                this.loadPage(page, search);
            }
        });

        
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            tableBody.addEventListener('mouseover', (e) => {
                const row = e.target.closest('.cyber-table-row');
                if (row && !row.classList.contains('empty-row')) {
                    row.style.transform = 'translateX(5px)';
                }
            });

            tableBody.addEventListener('mouseout', (e) => {
                const row = e.target.closest('.cyber-table-row');
                if (row && !row.classList.contains('empty-row')) {
                    row.style.transform = '';
                }
            });
        }
    }

    setupAjax() {
        
        $.ajaxSetup({
            beforeSend: function(xhr) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (csrfToken) {
                    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
                }
            }
        });
    }

    checkUrlParams() {
        
        const urlParams = new URLSearchParams(window.location.search);
        const search = urlParams.get('search');
        if (search) {
            this.highlightSearchTerms(search);
        }
    }

    async handleSearch(e, pushState = true) {
        if (e) e.preventDefault();

        const searchInput = document.getElementById('searchInput');
        const searchValue = searchInput ? searchInput.value.trim() : '';

        
        await this.loadPage(1, searchValue, pushState);
    }

    clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            this.handleSearch(null, true);
        }
    }

    async loadPage(page, search = '', pushState = true) {
        try {
            this.showLoading();

            
            const params = new URLSearchParams();
            params.set('page', page);
            if (search) {
                params.set('search', search);
            }

            
            const response = await fetch(`api.php?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            
            this.updateTable(data.bans);
            this.updatePagination(data.pagination);
            this.updateStats(data.stats);

            
            if (pushState) {
                const url = new URL(window.location);
                url.search = params.toString();
                window.history.pushState({ page, search }, '', url);
            }

            
            if (search) {
                this.highlightSearchTerms(search);
            }

        } catch (error) {
            console.error('加载数据失败:', error);
            this.showError('数据加载失败，请重试');
        } finally {
            this.hideLoading();
        }
    }

    updateTable(bans) {
        const tableBody = document.getElementById('tableBody');
        if (!tableBody) return;

        if (!bans || bans.length === 0) {
            
            const emptyRows = Array.from({ length: 15 }, (_, i) => i);
            tableBody.innerHTML = emptyRows.map(() => `
                <tr class="cyber-table-row empty-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            `).join('');
            return;
        }

        
        tableBody.innerHTML = bans.map(ban => `
            <tr class="cyber-table-row" data-id="${ban.id}">
                <td>
                    <div class="player-info">
                        <i class="fas fa-user"></i>
                        <span class="player-name">${this.escapeHtml(ban.username)}</span>
                    </div>
                </td>
                <td>
                    <div class="time-display">
                        <i class="far fa-clock"></i>
                        ${this.formatDateTime(ban.ban_time)}
                    </div>
                </td>
                <td>
                    <div class="time-display ${this.isPermanentBan(ban.unban_time) ? 'permanent' : ''}">
                        <i class="far fa-calendar-times"></i>
                        ${this.formatDateTime(ban.unban_time)}
                    </div>
                </td>
                <td>
                    <span class="punishment-tag">${this.escapeHtml(ban.punishment)}</span>
                </td>
                <td>
                    <div class="reason-text">${this.escapeHtml(ban.reason)}</div>
                </td>
            </tr>
        `).join('');

        
        const remainingRows = 15 - bans.length;
        if (remainingRows > 0) {
            const emptyRows = Array.from({ length: remainingRows }, (_, i) => i);
            tableBody.innerHTML += emptyRows.map(() => `
                <tr class="cyber-table-row empty-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            `).join('');
        }
    }

    updatePagination(pagination) {
        const paginationSection = document.querySelector('.pagination-section');
        if (!paginationSection) return;

        if (!pagination || pagination.totalPages <= 1) {
            paginationSection.innerHTML = '';
            return;
        }

        const { currentPage, totalPages, search } = pagination;
        const urlTemplate = search ? `?search=${encodeURIComponent(search)}&page=` : '?page=';

        let html = '<div class="pagination">';

        
        if (currentPage > 1) {
            html += `<a href="${urlTemplate}${currentPage - 1}" class="page-arrow">◀</a>`;
        } else {
            html += '<span class="page-arrow disabled">◀</span>';
        }

        
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            html += `<a href="${urlTemplate}1">1</a>`;
            if (startPage > 2) {
                html += '<span class="page-dots">...</span>';
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            if (i === currentPage) {
                html += `<span class="page-number current">${i}</span>`;
            } else {
                html += `<a href="${urlTemplate}${i}" class="page-number">${i}</a>`;
            }
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += '<span class="page-dots">...</span>';
            }
            html += `<a href="${urlTemplate}${totalPages}">${totalPages}</a>`;
        }

        
        if (currentPage < totalPages) {
            html += `<a href="${urlTemplate}${currentPage + 1}" class="page-arrow">▶</a>`;
        } else {
            html += '<span class="page-arrow disabled">▶</span>';
        }

        html += '</div>';
        paginationSection.innerHTML = html;
    }

    updateStats(stats) {
        const statsElement = document.querySelector('.stats');
        if (!statsElement || !stats) return;

        statsElement.innerHTML = `
            <div class="stat-item">
                <i class="fas fa-user-slash"></i>
                <span>总封禁记录: <strong>${stats.totalRecords}</strong></span>
            </div>
            <div class="stat-item">
                <i class="fas fa-file-alt"></i>
                <span>当前页: <strong>${stats.currentPage}</strong>/${stats.totalPages}</span>
            </div>
        `;
    }

    highlightSearchTerms(search) {
        const searchTerms = search.toLowerCase().split(/\s+/).filter(term => term.length >= 2);
        if (searchTerms.length === 0) return;

        const playerNames = document.querySelectorAll('.player-name');
        playerNames.forEach(element => {
            const originalText = element.textContent;
            let highlightedText = originalText;

            searchTerms.forEach(term => {
                const regex = new RegExp(`(${this.escapeRegExp(term)})`, 'gi');
                highlightedText = highlightedText.replace(regex, '<mark class="search-highlight">$1</mark>');
            });

            if (highlightedText !== originalText) {
                element.innerHTML = highlightedText;
            }
        });
    }

    showLoading() {
        
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="loading-cell" style="text-align: center; padding: 50px;">
                        <div class="loading"></div>
                        <p style="margin-top: 15px; color: #00b4d8;">加载中...</p>
                    </td>
                </tr>
            `;
        }
    }

    hideLoading() {
        
        const loadingCell = document.querySelector('.loading-cell');
        if (loadingCell) {
            loadingCell.remove();
        }
    }

    showError(message) {
        
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="error-cell" style="text-align: center; padding: 50px; color: #ff6b6b;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                        <p>${this.escapeHtml(message)}</p>
                        <button onclick="location.reload()" class="cyber-button" style="margin-top: 20px;">
                            <i class="fas fa-redo"></i> 重新加载
                        </button>
                    </td>
                </tr>
            `;
        }
    }

    formatDateTime(datetime) {
        if (!datetime || datetime === '0000-00-00 00:00:00') {
            return '永久封禁';
        }

        const date = new Date(datetime);
        return date.toLocaleString('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        }).replace(/\
    }

    isPermanentBan(unbanTime) {
        return !unbanTime || unbanTime === '0000-00-00 00:00:00';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
}


document.addEventListener('DOMContentLoaded', () => {
    window.banSystem = new BanSystem();

    
    window.addEventListener('popstate', (event) => {
        if (event.state) {
            const { page, search } = event.state;
            window.banSystem.loadPage(page, search, false);
        }
    });
});


function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) {
        return '刚刚';
    } else if (diffInSeconds < 3600) {
        return Math.floor(diffInSeconds / 60) + '分钟前';
    } else if (diffInSeconds < 86400) {
        return Math.floor(diffInSeconds / 3600) + '小时前';
    } else if (diffInSeconds < 2592000) {
        return Math.floor(diffInSeconds / 86400) + '天前';
    } else {
        return date.toLocaleDateString('zh-CN');
    }
}


const style = document.createElement('style');
style.textContent = `
    .search-highlight {
        background: linear-gradient(120deg, #dc2626, #ef4444);
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: 0 90%;
        color: white;
        padding: 2px 4px;
        border-radius: 3px;
        font-weight: bold;
        animation: highlight-pulse 2s infinite;
        box-shadow: 0 0 10px rgba(220, 38, 38, 0.5);
    }

    @keyframes highlight-pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.8;
        }
    }

    .loading-cell p {
        color: #dc2626 !important;
    }
`;
document.head.appendChild(style);