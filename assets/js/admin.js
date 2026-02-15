/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */



class AdminSystem {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupGlobalHandlers();
    }

    bindEvents() {
        
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        
        const dangerousForms = document.querySelectorAll('form.dangerous');
        dangerousForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('此操作可能带来风险，确定要继续吗？')) {
                    e.preventDefault();
                    return false;
                }
            });
        });

        
        this.setupTableFunctions();
    }

    setupGlobalHandlers() {
        
        $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
            console.error('AJAX 请求失败:', settings.url, thrownError);

            
            if (jqxhr.status === 401 || jqxhr.status === 403) {
                window.location.href = '/admin/index.php?expired=1';
            } else {
                
                AdminSystem.showNotification('请求失败: ' + thrownError, 'error');
            }
        });

        
        $(document).ajaxStart(function() {
            $('body').append('<div class="global-loading"><div class="loading-spinner"></div></div>');
        });

        $(document).ajaxStop(function() {
            $('.global-loading').remove();
        });
    }

    setupTableFunctions() {
        const tables = document.querySelectorAll('.admin-table');

        tables.forEach(table => {
            
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.addEventListener('click', function(e) {
                    
                    if (e.target.type === 'checkbox' ||
                        e.target.closest('.actions-cell') ||
                        e.target.closest('a')) {
                        return;
                    }

                    const checkbox = this.querySelector('.row-select');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });

                
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'rgba(0, 180, 216, 0.05)';
                });

                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });

            
            const headers = table.querySelectorAll('th[data-sort]');
            headers.forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    const sortField = this.dataset.sort;
                    const currentOrder = this.dataset.order || 'asc';
                    const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';

                    
                    headers.forEach(h => {
                        h.classList.remove('sort-asc', 'sort-desc');
                        h.dataset.order = '';
                    });

                    
                    this.classList.add(`sort-${newOrder}`);
                    this.dataset.order = newOrder;

                    
                    console.log(`按 ${sortField} ${newOrder} 排序`);
                });
            });
        });
    }


    static showNotification(message, type = 'info') {
        
        const existing = document.querySelector(`.notification-${type}`);
        if (existing) {
            existing.remove();
        }

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

    static confirmAction(message, confirmText = '确认', cancelText = '取消') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'confirmation-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-body">
                        <i class="fas fa-question-circle"></i>
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-cancel">${cancelText}</button>
                        <button class="btn-confirm">${confirmText}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const confirmBtn = modal.querySelector('.btn-confirm');
            const cancelBtn = modal.querySelector('.btn-cancel');

            confirmBtn.addEventListener('click', () => {
                modal.remove();
                resolve(true);
            });

            cancelBtn.addEventListener('click', () => {
                modal.remove();
                resolve(false);
            });

            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                    resolve(false);
                }
            });
        });
    }

    static formatDateTime(timestamp) {
        const date = new Date(timestamp * 1000);
        return date.toLocaleString('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    static copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            this.showNotification('已复制到剪贴板', 'success');
        }).catch(err => {
            console.error('复制失败:', err);
            this.showNotification('复制失败', 'error');
        });
    }
}


document.addEventListener('DOMContentLoaded', function() {
    window.adminSystem = new AdminSystem();

    
    document.addEventListener('keydown', function(e) {
        
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const form = document.querySelector('form:not(.no-save)');
            if (form) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.click();
                }
            }
        }

        
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                }
            });
        }

        
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            const searchInput = document.querySelector('.table-search input, input[type="search"]');
            if (searchInput && document.activeElement !== searchInput) {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    
    const textareas = document.querySelectorAll('textarea[data-autosave]');
    textareas.forEach(textarea => {
        const storageKey = textarea.dataset.autosave;

        
        const draft = localStorage.getItem(storageKey);
        if (draft && !textarea.value) {
            textarea.value = draft;
        }

        
        let saveTimeout;
        textarea.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                localStorage.setItem(storageKey, this.value);
            }, 1000);
        });

        
        const form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function() {
                localStorage.removeItem(storageKey);
            });
        }
    });
});


function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatTimeAgo(timestamp) {
    const now = new Date();
    const date = new Date(timestamp * 1000);
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

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}


const globalStyles = `
    .global-loading {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(3px);
    }

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
        z-index: 9998;
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

    .confirmation-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        backdrop-filter: blur(5px);
    }

    .confirmation-modal .modal-content {
        background: rgba(20, 25, 40, 0.95);
        border: 1px solid rgba(0, 180, 216, 0.4);
        border-radius: 12px;
        padding: 30px;
        min-width: 400px;
        max-width: 500px;
        text-align: center;
    }

    .confirmation-modal .modal-body i {
        font-size: 3rem;
        color: #00b4d8;
        margin-bottom: 20px;
    }

    .confirmation-modal .modal-body p {
        color: #ffffff;
        font-size: 16px;
        line-height: 1.5;
        margin-bottom: 25px;
    }

    .confirmation-modal .modal-footer {
        display: flex;
        justify-content: center;
        gap: 15px;
    }

    .confirmation-modal button {
        padding: 12px 25px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-family: 'Orbitron', monospace;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
        min-width: 100px;
    }

    .confirmation-modal .btn-cancel {
        background: rgba(108, 117, 125, 0.2);
        color: #b0bec5;
        border: 1px solid #6c757d;
    }

    .confirmation-modal .btn-cancel:hover {
        background: rgba(108, 117, 125, 0.4);
    }

    .confirmation-modal .btn-confirm {
        background: rgba(0, 180, 216, 0.2);
        color: #00b4d8;
        border: 1px solid #00b4d8;
    }

    .confirmation-modal .btn-confirm:hover {
        background: rgba(0, 180, 216, 0.4);
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

    th.sort-asc::after {
        content: ' ↑';
        color: #00b4d8;
    }

    th.sort-desc::after {
        content: ' ↓';
        color: #00b4d8;
    }

    .row-selected {
        background: rgba(0, 180, 216, 0.1) !important;
    }
`;


const styleSheet = document.createElement('style');
styleSheet.textContent = globalStyles;
document.head.appendChild(styleSheet);