/**
 * Admin Mobile Menu Handler
 * PrintFlow - Mobile burger menu and sidebar toggle
 * ONLY runs on admin/staff/manager pages
 */

(function() {
    'use strict';
    
    // Check if this is an admin page
    function isAdminPage() {
        const path = window.location.pathname;
        return path.includes('/admin/') || path.includes('/staff/') || path.includes('/manager/');
    }
    
    // Only run on admin pages
    if (!isAdminPage()) {
        return;
    }
    
    // Only run on mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    function initMobileMenu() {
        if (!isMobile()) {
            // Clean up mobile elements on desktop
            const burger = document.getElementById('mobileBurger');
            const overlay = document.getElementById('sidebarOverlay');
            const sidebar = document.querySelector('.sidebar');
            
            if (burger) burger.style.display = 'none';
            if (overlay) {
                overlay.classList.remove('active');
                overlay.style.display = 'none';
            }
            if (sidebar) {
                sidebar.classList.remove('active');
                sidebar.style.transform = '';
            }
            document.body.style.overflow = '';
            return;
        }
        
        // Create burger button if it doesn't exist
        let burger = document.getElementById('mobileBurger');
        if (!burger) {
            burger = document.createElement('button');
            burger.id = 'mobileBurger';
            burger.innerHTML = '<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>';
            burger.setAttribute('aria-label', 'Toggle menu');
            burger.style.display = 'flex';
            document.body.appendChild(burger);
        } else {
            burger.style.display = 'flex';
        }
        
        // Create overlay if it doesn't exist
        let overlay = document.getElementById('sidebarOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            document.body.appendChild(overlay);
        }
        overlay.style.display = 'block';
        
        const sidebar = document.querySelector('.sidebar');
        
        if (!sidebar) return;
        
        // Create close button inside sidebar if it doesn't exist
        let closeBtn = sidebar.querySelector('.sidebar-close-btn');
        if (!closeBtn) {
            closeBtn = document.createElement('button');
            closeBtn.className = 'sidebar-close-btn';
            closeBtn.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            closeBtn.setAttribute('aria-label', 'Close menu');
            closeBtn.style.display = 'flex';
            sidebar.insertBefore(closeBtn, sidebar.firstChild);
        } else {
            closeBtn.style.display = 'flex';
        }
        
        // Toggle sidebar
        function toggleSidebar(e) {
            if (e) e.stopPropagation();
            const isActive = sidebar.classList.contains('active');
            
            if (isActive) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        
        // Open sidebar
        function openSidebar() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        // Close sidebar
        function closeSidebar() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Remove old event listeners by cloning
        const newBurger = burger.cloneNode(true);
        burger.parentNode.replaceChild(newBurger, burger);
        burger = newBurger;
        
        const newOverlay = overlay.cloneNode(true);
        overlay.parentNode.replaceChild(newOverlay, overlay);
        overlay = newOverlay;
        
        const newCloseBtn = closeBtn.cloneNode(true);
        closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
        closeBtn = newCloseBtn;
        
        // Burger click
        burger.addEventListener('click', toggleSidebar);
        
        // Overlay click
        overlay.addEventListener('click', closeSidebar);
        
        // Close button click
        closeBtn.addEventListener('click', closeSidebar);
        
        // Close on navigation
        const navLinks = sidebar.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                setTimeout(closeSidebar, 100);
            });
        });
        
        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });
    }
    
    // Add data-label attributes to table cells for mobile view
    function enhanceTables() {
        if (!isMobile()) return;
        
        const tables = document.querySelectorAll('table');
        tables.forEach(table => {
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (headers[index] && !cell.getAttribute('data-label')) {
                        cell.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    }
    
    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initMobileMenu();
            enhanceTables();
        });
    } else {
        initMobileMenu();
        enhanceTables();
    }
    
    // Re-initialize on window resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (isMobile()) {
                initMobileMenu();
                enhanceTables();
            } else {
                // Clean up mobile elements on desktop
                const burger = document.getElementById('mobileBurger');
                const overlay = document.getElementById('sidebarOverlay');
                const sidebar = document.querySelector('.sidebar');
                
                if (burger) burger.style.display = 'none';
                if (overlay) overlay.classList.remove('active');
                if (sidebar) sidebar.classList.remove('active');
                document.body.style.overflow = '';
            }
        }, 250);
    });
    
    // Support for Turbo/dynamic content
    document.addEventListener('turbo:load', () => {
        initMobileMenu();
        enhanceTables();
    });
    
})();
