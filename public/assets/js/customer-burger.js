/**
 * Customer Burger Menu Handler
 * PrintFlow - Landing page and customer portal mobile menu
 */

(function() {
    'use strict';

    var PF_BURGER_DEBUG = false;
    function debugLog() {
        if (!PF_BURGER_DEBUG || !window.console || typeof console.log !== 'function') return;
        console.log.apply(console, arguments);
    }
    function debugWarn() {
        if (!PF_BURGER_DEBUG || !window.console || typeof console.warn !== 'function') return;
        console.warn.apply(console, arguments);
    }
    function debugError() {
        if (!PF_BURGER_DEBUG || !window.console || typeof console.error !== 'function') return;
        console.error.apply(console, arguments);
    }
    
    // Burger Menu Toggle Functions
    window.openBurgerMenu = function() {
        debugLog('[Customer Burger] Opening menu');
        var overlay = document.querySelector('[data-pf-burger-overlay]');
        var menu = document.querySelector('[data-pf-burger-menu]');
        if (overlay && menu) {
            overlay.classList.add('open');
            menu.classList.add('open');
            document.body.style.overflow = 'hidden';
            debugLog('[Customer Burger] Menu opened');
        } else {
            debugError('[Customer Burger] Overlay or menu not found', {overlay: overlay, menu: menu});
        }
    };
    
    window.closeBurgerMenu = function() {
        debugLog('[Customer Burger] Closing menu');
        var overlay = document.querySelector('[data-pf-burger-overlay]');
        var menu = document.querySelector('[data-pf-burger-menu]');
        if (overlay && menu) {
            overlay.classList.remove('open');
            menu.classList.remove('open');
            document.body.style.overflow = '';
            debugLog('[Customer Burger] Menu closed');
        }
    };
    
    // Initialize on DOM ready
    function init() {
        debugLog('[Customer Burger] DOM ready, binding events');
        
        // Burger button click handler
        var burgerBtn = document.querySelector('[data-pf-mobile-toggle]');
        if (burgerBtn) {
            debugLog('[Customer Burger] Burger button found, attaching click handler');
            burgerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                debugLog('[Customer Burger] Burger button clicked');
                window.openBurgerMenu();
            });
        } else {
            debugWarn('[Customer Burger] Burger button not found');
        }
        
        // Overlay click handler
        var overlay = document.querySelector('[data-pf-burger-overlay]');
        if (overlay) {
            overlay.addEventListener('click', function() {
                debugLog('[Customer Burger] Overlay clicked');
                window.closeBurgerMenu();
            });
        }
        
        // Close button handler
        var closeBtn = document.querySelector('.pf-burger-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                debugLog('[Customer Burger] Close button clicked');
                window.closeBurgerMenu();
            });
        }
        
        // Escape key handler
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var menu = document.querySelector('[data-pf-burger-menu]');
                if (menu && menu.classList.contains('open')) {
                    debugLog('[Customer Burger] Escape key pressed');
                    window.closeBurgerMenu();
                }
            }
        });
        
        // Close on navigation link click
        var burgerLinks = document.querySelectorAll('.pf-burger-menu a');
        burgerLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                debugLog('[Customer Burger] Navigation link clicked');
                setTimeout(window.closeBurgerMenu, 100);
            });
        });
        
        debugLog('[Customer Burger] Initialization complete');
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
