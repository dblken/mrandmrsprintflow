/**
 * Customer Burger Menu Handler
 * PrintFlow - Landing page and customer portal mobile menu
 */

(function() {
    'use strict';
    
    console.log('[Customer Burger] Initializing...');
    
    // Burger Menu Toggle Functions
    window.openBurgerMenu = function() {
        console.log('[Customer Burger] Opening menu');
        var overlay = document.querySelector('[data-pf-burger-overlay]');
        var menu = document.querySelector('[data-pf-burger-menu]');
        if (overlay && menu) {
            overlay.classList.add('open');
            menu.classList.add('open');
            document.body.style.overflow = 'hidden';
            console.log('[Customer Burger] Menu opened');
        } else {
            console.error('[Customer Burger] Overlay or menu not found', {overlay: overlay, menu: menu});
        }
    };
    
    window.closeBurgerMenu = function() {
        console.log('[Customer Burger] Closing menu');
        var overlay = document.querySelector('[data-pf-burger-overlay]');
        var menu = document.querySelector('[data-pf-burger-menu]');
        if (overlay && menu) {
            overlay.classList.remove('open');
            menu.classList.remove('open');
            document.body.style.overflow = '';
            console.log('[Customer Burger] Menu closed');
        }
    };
    
    // Initialize on DOM ready
    function init() {
        console.log('[Customer Burger] DOM ready, binding events');
        
        // Burger button click handler
        var burgerBtn = document.querySelector('[data-pf-mobile-toggle]');
        if (burgerBtn) {
            console.log('[Customer Burger] Burger button found, attaching click handler');
            burgerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('[Customer Burger] Burger button clicked');
                window.openBurgerMenu();
            });
        } else {
            console.warn('[Customer Burger] Burger button not found');
        }
        
        // Overlay click handler
        var overlay = document.querySelector('[data-pf-burger-overlay]');
        if (overlay) {
            overlay.addEventListener('click', function() {
                console.log('[Customer Burger] Overlay clicked');
                window.closeBurgerMenu();
            });
        }
        
        // Close button handler
        var closeBtn = document.querySelector('.pf-burger-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                console.log('[Customer Burger] Close button clicked');
                window.closeBurgerMenu();
            });
        }
        
        // Escape key handler
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var menu = document.querySelector('[data-pf-burger-menu]');
                if (menu && menu.classList.contains('open')) {
                    console.log('[Customer Burger] Escape key pressed');
                    window.closeBurgerMenu();
                }
            }
        });
        
        // Close on navigation link click
        var burgerLinks = document.querySelectorAll('.pf-burger-menu a');
        burgerLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                console.log('[Customer Burger] Navigation link clicked');
                setTimeout(window.closeBurgerMenu, 100);
            });
        });
        
        console.log('[Customer Burger] Initialization complete');
    }
    
    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Also run on Turbo load if Turbo is present
    if (typeof Turbo !== 'undefined') {
        document.addEventListener('turbo:load', init);
    }
    
})();
