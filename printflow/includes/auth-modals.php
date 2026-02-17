<?php
/**
 * Login and Register modals (blurred backdrop). Include when !$is_logged_in.
 * Requires: $base_url, csrf_field(), and optionally $_GET['auth_modal'], $_GET['error']
 */
$auth_modal = isset($_GET['auth_modal']) ? $_GET['auth_modal'] : '';
$auth_error = isset($_GET['error']) ? $_GET['error'] : '';
$auth_success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<style>
/* Lightweight modal: solid overlay (no blur), short transitions, no scale */
.auth-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9998;
    background: rgba(15, 23, 42, 0.6);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.12s ease-out, visibility 0.12s ease-out;
}
.auth-modal-backdrop.is-open {
    opacity: 1;
    visibility: visible;
}
.auth-modal {
    position: fixed;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    z-index: 9999;
    width: 100%;
    max-width: 28rem;
    max-height: 90vh;
    overflow-y: hidden; /* Removed scrollbar as requested */
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.12s ease-out, visibility 0.12s ease-out;
}
.auth-modal.is-open {
    opacity: 1;
    visibility: visible;
}
    .auth-modal-register { max-width: 50rem; } /* Widened for 3-col layout */
    .auth-modal-close {
        position: absolute;
        right: 1rem;
        top: 1rem;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        background: none;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: color 0.2s, background 0.2s;
    }
    .auth-modal-close:hover {
        color: #1e293b;
        background: #f1f5f9;
    }
    .auth-modal-inner { padding: 2rem 1.5rem; }
    .auth-modal h2 { margin: 0 0 0.25rem 0; font-size: 1.5rem; font-weight: 700; color: #0f172a; text-align: center; }
    .auth-modal .auth-modal-sub { margin: 0 0 1.5rem 0; font-size: 0.875rem; color: #64748b; text-align: center; }
    .auth-modal .input-field {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        font-size: 1rem;
        box-sizing: border-box;
    }
    .auth-modal .input-field:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }
    .auth-modal label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.375rem; }
    .auth-modal .auth-alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1rem; }
    .auth-modal .auth-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1rem; }
    .auth-modal .auth-btn-submit { width: 100%; padding: 0.625rem 1rem; background: linear-gradient(to right, #4f46e5, #7c3aed); color: #fff; font-weight: 500; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 1rem; }
    .auth-modal .auth-btn-submit:hover { opacity: 0.95; }
    .auth-modal .auth-switch { margin-top: 1.25rem; text-align: center; font-size: 0.875rem; color: #64748b; }
    .auth-modal .auth-switch a { color: #4f46e5; font-weight: 500; text-decoration: none; }
    .auth-modal .auth-switch a:hover { text-decoration: underline; }
    .auth-modal .auth-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .auth-modal .auth-grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    @media (max-width: 640px) { 
        .auth-modal .auth-grid2, .auth-modal .auth-grid3 { grid-template-columns: 1fr; } 
    }
    .auth-modal .auth-field { margin-bottom: 1rem; }
    .auth-modal .auth-field-row { margin-bottom: 1rem; }
    .auth-modal .auth-google-wrap { margin-bottom: 1rem; }
    .auth-modal .auth-btn-google {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        padding: 0.6rem 1rem;
        background: #fff;
        border: 1px solid #dadce0;
        border-radius: 0.5rem;
        color: #3c4043;
        font-size: 0.9375rem;
        font-weight: 500;
        text-decoration: none;
        transition: background 0.15s, border-color 0.15s;
    }
    .auth-modal .auth-btn-google:hover { background: #f8f9fa; border-color: #d2d4d6; }
    .auth-modal .auth-divider { display: flex; align-items: center; margin: 1rem 0; font-size: 0.8125rem; color: #64748b; }
    .auth-modal .auth-divider::before, .auth-modal .auth-divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
    .auth-modal .auth-divider span { padding: 0 0.75rem; }
</style>

<div class="auth-modal-backdrop" id="auth-modal-backdrop" aria-hidden="true"></div>

<!-- Login Modal -->
<div class="auth-modal" id="auth-modal-login" role="dialog" aria-labelledby="auth-login-title" aria-modal="true" aria-hidden="true">
    <button type="button" class="auth-modal-close" data-auth-close aria-label="Close">&times;</button>
    <div class="auth-modal-inner">
        <h2 id="auth-login-title">Welcome Back</h2>
        <p class="auth-modal-sub">Sign in to your PrintFlow account</p>
        <div id="auth-login-message"></div>
        <?php if (!empty($google_client_id)): ?>
        <div class="auth-field auth-google-wrap">
            <a href="<?php echo htmlspecialchars($url_google_auth ?? $base_url . '/google-auth/'); ?>?action=login" class="auth-btn-google" aria-label="Sign in with Google">
                <svg width="20" height="20" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Sign in with Google
            </a>
        </div>
        <p class="auth-divider"><span>or</span></p>
        <?php endif; ?>
        <form method="POST" action="<?php echo htmlspecialchars($base_url); ?>/login/">
            <?php echo csrf_field(); ?>
            <div class="auth-field">
                <label for="auth-email">Email Address</label>
                <input type="email" id="auth-email" name="email" class="input-field" placeholder="you@example.com" required>
            </div>
            <div class="auth-field">
                <label for="auth-password">Password</label>
                <input type="password" id="auth-password" name="password" class="input-field" placeholder="••••••••" required>
            </div>
            <div class="auth-field-row" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
                <label style="display:flex;align-items:center;gap:0.5rem;margin:0;">
                    <input type="checkbox" name="remember" style="width:1rem;height:1rem;">
                    <span>Remember me</span>
                </label>
                <a href="<?php echo $url_forgot_password ?? $base_url . '/forgot-password/'; ?>" style="font-size:0.875rem;color:#4f46e5;">Forgot password?</a>
            </div>
            <button type="submit" class="auth-btn-submit">Sign In</button>
        </form>
        <p class="auth-switch">Don't have an account? <a href="#" data-auth-open="register">Register now</a></p>
    </div>
</div>

<!-- Register Modal -->
<div class="auth-modal auth-modal-register" id="auth-modal-register" role="dialog" aria-labelledby="auth-register-title" aria-modal="true" aria-hidden="true">
    <button type="button" class="auth-modal-close" data-auth-close aria-label="Close">&times;</button>
    <div class="auth-modal-inner">
        <h2 id="auth-register-title">Create Account</h2>
        <p class="auth-modal-sub">Join PrintFlow and start ordering custom prints</p>
        <div id="auth-register-message"></div>
        <form method="POST" action="<?php echo htmlspecialchars($base_url); ?>/register/">
            <?php echo csrf_field(); ?>
            <!-- 3-Column Names -->
            <div class="auth-grid3">
                <div class="auth-field">
                    <label for="auth-first_name">First Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="auth-first_name" name="first_name" class="input-field" required>
                </div>
                <div class="auth-field">
                    <label for="auth-middle_name">Middle Name</label>
                    <input type="text" id="auth-middle_name" name="middle_name" class="input-field">
                </div>
                <div class="auth-field">
                    <label for="auth-last_name">Last Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="auth-last_name" name="last_name" class="input-field" required>
                </div>
            </div>

            <!-- 2-Column Gender/Contact -->
            <div class="auth-grid2">
                <div class="auth-field">
                    <label for="auth-gender">Gender</label>
                    <select id="auth-gender" name="gender" class="input-field">
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="auth-field">
                    <label for="auth-contact">Contact Number</label>
                    <input type="tel" id="auth-contact" name="contact_number" class="input-field" placeholder="+63 123 456 7890">
                </div>
            </div>

            <!-- 2-Column Email/DOB -->
            <div class="auth-grid2">
                <div class="auth-field">
                    <label for="auth-email-reg">Email Address <span style="color:#dc2626;">*</span></label>
                    <input type="email" id="auth-email-reg" name="email" class="input-field" placeholder="you@example.com" required>
                </div>
                <div class="auth-field">
                    <label for="auth-dob">Date of Birth</label>
                    <input type="date" id="auth-dob" name="dob" class="input-field">
                </div>
            </div>

            <div class="auth-grid2">
                <div class="auth-field">
                    <label for="auth-password-reg">Password <span style="color:#dc2626;">*</span></label>
                    <input type="password" id="auth-password-reg" name="password" class="input-field" placeholder="••••••••" required minlength="8">
                    <p style="margin:0.25rem 0 0;font-size:0.75rem;color:#64748b;">Min 8 characters</p>
                </div>
                <div class="auth-field">
                    <label for="auth-confirm_password">Confirm Password <span style="color:#dc2626;">*</span></label>
                    <input type="password" id="auth-confirm_password" name="confirm_password" class="input-field" placeholder="••••••••" required minlength="8">
                </div>
            </div>
            <button type="submit" class="auth-btn-submit" style="margin-top:1rem;">Create Account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="#" data-auth-open="login">Sign in</a></p>
    </div>
</div>

<script>
(function() {
    var backdrop = document.getElementById('auth-modal-backdrop');
    var loginModal = document.getElementById('auth-modal-login');
    var registerModal = document.getElementById('auth-modal-register');
    var authModal = <?php echo json_encode($auth_modal); ?>;
    var authError = <?php echo json_encode($auth_error); ?>;
    var authSuccess = <?php echo json_encode($auth_success); ?>;

    function openModal(name) {
        var modal = name === 'register' ? registerModal : loginModal;
        if (!modal) return;
        backdrop.classList.add('is-open');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        backdrop.classList.remove('is-open');
        if (loginModal) { loginModal.classList.remove('is-open'); loginModal.setAttribute('aria-hidden', 'true'); }
        if (registerModal) { registerModal.classList.remove('is-open'); registerModal.setAttribute('aria-hidden', 'true'); }
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    function showMessage(modalName, type, text) {
        if (!text) return;
        var el = document.getElementById('auth-' + modalName + '-message');
        if (!el) return;
        el.innerHTML = '<div class="auth-alert-' + type + '">' + (type === 'error' ? escapeHtml(text) : escapeHtml(text)) + '</div>';
    }
    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-auth-modal], [data-auth-open]')) {
            e.preventDefault();
            var name = (e.target.getAttribute('data-auth-modal') || e.target.getAttribute('data-auth-open') || '').toLowerCase();
            if (name === 'login' || name === 'register') openModal(name);
        }
        if (e.target.matches('[data-auth-close]') || e.target === backdrop) {
            e.preventDefault();
            closeModal();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && backdrop && backdrop.classList.contains('is-open')) closeModal();
    });

    if (authModal === 'login' || authModal === 'register') {
        openModal(authModal);
        if (authError) showMessage(authModal === 'login' ? 'login' : 'register', 'error', authError);
        if (authSuccess) showMessage(authModal === 'login' ? 'login' : 'register', 'success', authSuccess);
    }
})();
</script>
