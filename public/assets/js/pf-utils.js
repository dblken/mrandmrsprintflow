/* global helper: pfCallWarn
   Ensures a safe global `window.pfCallWarn` exists so inline pages
   and other scripts can call it without throwing ReferenceError.
*/
(function(){
    if (typeof window === 'undefined') return;
    if (typeof window.pfCallWarn === 'function') return;

    // Inject minimal toast CSS for warnings
    try {
        var _css = document.createElement('style');
        _css.type = 'text/css';
        _css.appendChild(document.createTextNode('\n.pf-toast-warning{position:fixed;right:20px;bottom:20px;background:#f59e0b;color:#08203a;padding:10px 14px;border-radius:10px;box-shadow:0 6px 24px rgba(2,6,23,0.18);z-index:2147483647;font-family:Inter, system-ui, sans-serif;font-size:13px;opacity:1;transition:opacity .32s ease;max-width:320px;word-break:break-word}\n.pf-toast-warning.fade-out{opacity:0}\n'));
        document.head.appendChild(_css);
    } catch (e) {
        // ignore
    }

    window.pfCallWarn = function() {
        try {
            if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                console.warn.apply(console, ['[PFCallWarn]'].concat(Array.prototype.slice.call(arguments)));
            }

            var message = Array.prototype.slice.call(arguments).map(function(a){
                try { if (typeof a === 'string') return a; return JSON.stringify(a); } catch(e){ return String(a); }
            }).join(' ');

            if (!document || !document.body) return;
            var toast = document.createElement('div');
            toast.className = 'pf-toast-warning';
            toast.textContent = message || 'Warning';
            document.body.appendChild(toast);
            setTimeout(function(){
                try { toast.classList.add('fade-out'); setTimeout(function(){ try{ toast.remove(); }catch(e){} }, 350); } catch(e){}
            }, 3000);
        } catch (err) {
            try { console.warn('[PFCallWarn][error]', err); } catch(e) {}
        }
    };
})();
