document.addEventListener('DOMContentLoaded', function() {
    let userProfile = document.querySelector('.user-profile');
    const contentHeader = document.querySelector('.content-header');
    
    if (!userProfile && contentHeader) {
        // Fallback for pages like staff dashboard that might have a different header structure
        userProfile = contentHeader;
    }
    
    if (!userProfile) return;

    // Create Notification HTML
    const wrapper = document.createElement('div');
    wrapper.className = 'notification-wrapper';
    if (userProfile.classList.contains('content-header')) {
        wrapper.style.marginLeft = 'auto'; // Push to right if in header directly
    }
    wrapper.innerHTML = `
        <button class="notification-btn" id="notifBtn">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span class="notification-badge" id="notifBadge" style="display: none;">0</span>
        </button>
        <div class="notification-dropdown" id="notifDropdown">
            <div class="notification-header">
                <h3>Notifikasi</h3>
                <button class="mark-all-read" id="markAllRead">Tandai semua dibaca</button>
            </div>
            <div class="notification-list" id="notifList">
                <div class="notification-empty">Memuat...</div>
            </div>
        </div>
    `;

    if (userProfile.classList.contains('user-profile')) {
        userProfile.insertBefore(wrapper, userProfile.firstChild);
    } else {
        userProfile.appendChild(wrapper);
    }

    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifBadge = document.getElementById('notifBadge');
    const notifList = document.getElementById('notifList');
    const markAllRead = document.getElementById('markAllRead');

    // Toggle Dropdown
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle('show');
        if (notifDropdown.classList.contains('show')) {
            fetchNotifications();
        }
    });

    document.addEventListener('click', () => {
        notifDropdown.classList.remove('show');
    });

    notifDropdown.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    // Mark All Read
    markAllRead.addEventListener('click', () => {
        fetch('../api/notifications.php?action=mark_read')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    fetchNotifications();
                }
            });
    });

    function fetchNotifications() {
        fetch('../api/notifications.php?action=fetch')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Update Badge
                    if (data.unread_count > 0) {
                        notifBadge.textContent = data.unread_count;
                        notifBadge.style.display = 'block';
                    } else {
                        notifBadge.style.display = 'none';
                    }

                    // Update List
                    if (data.notifications.length === 0) {
                        notifList.innerHTML = '<div class="notification-empty">Tidak ada notifikasi baru.</div>';
                    } else {
                        notifList.innerHTML = data.notifications.map(n => `
                            <a href="${n.link || '#'}" class="notification-item ${n.status === 'unread' ? 'unread' : ''}" data-id="${n.id_notification}">
                                <div class="notif-icon">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                </div>
                                <div class="notif-content">
                                    <p class="notif-message">${n.message}</p>
                                    <span class="notif-time">${formatTime(n.created_at)}</span>
                                </div>
                            </a>
                        `).join('');

                        // Add click event for each item
                        document.querySelectorAll('.notification-item').forEach(item => {
                            item.addEventListener('click', function(e) {
                                const id = this.dataset.id;
                                fetch(`../api/notifications.php?action=mark_read&id=${id}`);
                            });
                        });
                    }
                }
            });
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Baru saja';
        if (diff < 3600) return Math.floor(diff / 60) + ' menit yang lalu';
        if (diff < 86400) return Math.floor(diff / 3600) + ' jam yang lalu';
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    }

    // Initial check and interval
    fetchNotifications();
    setInterval(fetchNotifications, 30000); // Check every 30 seconds
});
