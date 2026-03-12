<?php
/**
 * Login and Register modals (blurred backdrop). Include when !$is_logged_in.
 * Requires: $base_url, csrf_field(), and optionally $_GET['auth_modal'], $_GET['error']
 * 
 * Version: 2.0 - Updated 2026-03-05
 * - Removed "Remember me" checkbox from login modal
 * - Added forgot password modal with Email/Mobile tabs
 * - Centered forgot password link in login modal
 */
$auth_modal = isset($_GET['auth_modal']) ? $_GET['auth_modal'] : '';
$auth_error = isset($_GET['error']) ? $_GET['error'] : '';
$auth_success = isset($_GET['success']) ? $_GET['success'] : '';
?>
<style>
/* Auth Modals — dark navy/teal theme matching landing page */
.auth-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 99998;
    background: rgba(0, 10, 18, 0.78);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.14s ease-out, visibility 0.14s ease-out;
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
    z-index: 99999;
    width: 100%;
    max-width: 28rem;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #00232b;
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 1rem;
    box-shadow: 0 30px 70px rgba(0,0,0,.65), 0 0 50px rgba(83,197,224,.07);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.14s ease-out, visibility 0.14s ease-out;
}
    .auth-modal-inner {
        overflow-y: auto;
        flex: 1;
        -webkit-overflow-scrolling: touch;
    }
    /* Custom scrollbar — Chrome / Edge / Safari */
    .auth-modal-inner::-webkit-scrollbar {
        width: 6px;
    }
    .auth-modal-inner::-webkit-scrollbar-track {
        background: transparent;
    }
    .auth-modal-inner::-webkit-scrollbar-thumb {
        background: rgba(50, 161, 196, 0.35);
        border-radius: 3px;
        transition: background 0.2s;
    }
    .auth-modal-inner::-webkit-scrollbar-thumb:hover {
        background: rgba(83, 197, 224, 0.6);
    }
    /* Custom scrollbar — Firefox */
    .auth-modal-inner {
        scrollbar-width: thin;
        scrollbar-color: rgba(50, 161, 196, 0.35) transparent;
    }
.auth-modal.is-open {
    opacity: 1;
    visibility: visible;
}
    .auth-modal-register { max-width: 28rem; }
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
        color: #e0f2fe;
        background: rgba(255,255,255,.08);
    }
    .auth-modal-inner { padding: 2rem 1.5rem; overflow-y: auto; flex: 1; }
    .auth-modal h2 { margin: 0 0 0.25rem 0; font-size: 1.5rem; font-weight: 700; color: #ffffff; text-align: center; }
    .auth-modal .auth-modal-sub { margin: 0 0 1.5rem 0; font-size: 0.875rem; color: #94a3b8; text-align: center; }
    .auth-modal .input-field {
        width: 100%;
        padding: 0.55rem 0.85rem;
        background: rgba(255,255,255,.05);
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 0.5rem;
        font-size: 1rem;
        color: #e0f2fe;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .auth-modal .input-field::placeholder { color: #475569; }
    .auth-modal .input-field:focus {
        outline: none;
        border-color: #32a1c4;
        box-shadow: 0 0 0 3px rgba(83, 197, 224, 0.2);
        background: rgba(255,255,255,.09);
    }
    .auth-modal label { display: block; font-size: 0.875rem; font-weight: 500; color: #94a3b8; margin-bottom: 0.375rem; }
    .auth-modal .auth-alert-error { background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.35); color: #fca5a5; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1rem; }
    .auth-modal .auth-alert-success { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); color: #86efac; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; margin-bottom: 1rem; }
    .auth-modal .auth-btn-submit { width: 100%; padding: 0.7rem 1rem; background: #32a1c4; color: #fff; font-weight: 600; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 1rem; transition: background .2s, box-shadow .2s; }
    .auth-modal .auth-btn-submit:hover { background: #2a82a3; box-shadow: 0 0 24px rgba(83,197,224,.4); }
    .auth-modal .auth-btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
    .auth-modal .auth-switch { margin-top: 1.25rem; text-align: center; font-size: 0.875rem; color: #64748b; }
    .auth-modal .auth-switch a { color: #53C5E0; font-weight: 600; text-decoration: none; }
    .auth-modal .auth-switch a:hover { color: #7acae3; }
    .auth-modal .auth-field { margin-bottom: 1rem; }
    .auth-modal .auth-field-row { margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
    .auth-modal .auth-field-row label { margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #94a3b8; }
    .auth-modal .auth-field-row a { font-size: 0.875rem; color: #53C5E0; }
    .auth-modal input[type="checkbox"] { accent-color: #32a1c4; }
    .auth-modal .auth-google-wrap { margin-bottom: 1rem; }
    .auth-modal .auth-btn-google {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        padding: 0.6rem 1rem;
        background: rgba(255,255,255,.06);
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 0.5rem;
        color: #e0f2fe;
        font-size: 0.9375rem;
        font-weight: 500;
        text-decoration: none;
        transition: background 0.15s, border-color 0.15s;
    }
    .auth-modal .auth-btn-google:hover { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.25); }
    .auth-modal .auth-divider { display: flex; align-items: center; margin: 1rem 0; font-size: 0.8125rem; color: #475569; }
    .auth-modal .auth-divider::before, .auth-modal .auth-divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,.08); }
    .auth-modal .auth-divider span { padding: 0 0.75rem; }
    /* OTP register tabs */
    .reg-tabs { display: flex; gap: 0; margin-bottom: 1.25rem; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(255,255,255,.1); }
    .reg-tab { flex: 1; padding: 0.6rem 0.75rem; text-align: center; font-size: 0.875rem; font-weight: 600; background: rgba(255,255,255,.03); color: #64748b; border: none; cursor: pointer; transition: all 0.2s; }
    .reg-tab.active { background: #32a1c4; color: #fff; }
    .reg-tab:not(.active):hover { background: rgba(83,197,224,.1); color: #53C5E0; }
    .reg-otp-row { display: flex; gap: 0.5rem; align-items: flex-end; }
    .reg-otp-row .input-field { flex: 1; }
    .reg-otp-btn { padding: 0.5rem 1rem; background: #32a1c4; color: #fff; border: none; border-radius: 0.5rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: opacity 0.2s; }
    .reg-otp-btn:hover { opacity: 0.9; }
    .reg-otp-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .reg-step { display: none; }
    .reg-step.active { display: block; }
    .reg-code-inputs { display: flex; gap: 0.4rem; justify-content: center; margin: 1rem 0; }
    .reg-code-inputs input { width: 2.5rem; height: 2.8rem; text-align: center; font-size: 1.25rem; font-weight: 700; background: rgba(255,255,255,.05); border: 2px solid rgba(255,255,255,.12); border-radius: 0.5rem; color: #e0f2fe; }
    .reg-code-inputs input:focus { border-color: #32a1c4; outline: none; box-shadow: 0 0 0 3px rgba(83,197,224,.2); }
    .reg-verified { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.75rem; background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); color: #86efac; border-radius: 0.5rem; font-size: 0.8rem; font-weight: 600; }
    .reg-dev-code { background: rgba(251,191,36,.1); border: 1px solid rgba(251,191,36,.25); color: #fbbf24; padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.8rem; margin-top: 0.5rem; text-align: center; }
    .reg-countdown { font-size: 0.8rem; color: #64748b; margin-top: 0.5rem; text-align: center; }
    .reg-step-indicator { display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 1.25rem; font-size: 0.75rem; color: #64748b; }
    .reg-step-dot { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; background: rgba(255,255,255,.08); color: #64748b; }
    .reg-step-dot.active { background: #32a1c4; color: #fff; }
    .reg-step-dot.done { background: #22c55e; color: #fff; }
    .reg-step-line { width: 2rem; height: 2px; background: rgba(255,255,255,.08); }
    .reg-step-line.done { background: #22c55e; }
    .auth-password-wrap { position: relative; }
    .auth-password-wrap .input-field { padding-right: 3rem; }
    .auth-password-toggle {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border: none;
        background: transparent;
        color: #94a3b8;
        cursor: pointer;
        padding: 0;
    }
    .auth-password-toggle:hover { color: #e0f2fe; }
    .auth-password-toggle:focus-visible {
        outline: 2px solid rgba(83, 197, 224, 0.45);
        outline-offset: 2px;
        border-radius: 0.5rem;
    }
    .auth-password-toggle svg {
        width: 1.25rem;
        height: 1.25rem;
        pointer-events: none;
    }

    /* ═══ Validation Feedback Styles ═══ */
    .field-error {
        margin: 0.3rem 0 0; font-size: 0.75rem; color: #f87171;
        min-height: 1.1em; transition: opacity 0.15s;
    }
    .field-error:empty { opacity: 0; }
    .field-success {
        margin: 0.3rem 0 0; font-size: 0.75rem; color: #4ade80;
    }
    .input-field.is-invalid { border-color: #f87171 !important; }
    .input-field.is-valid { border-color: #4ade80 !important; }

    /* Password checklist */
    .pw-checklist {
        list-style: none; padding: 0; margin: 0.4rem 0 0;
        display: none; grid-template-columns: 1fr 1fr; gap: 0.15rem 0.75rem;
    }
    .pw-checklist.active {
        display: grid;
    }
    .pw-checklist li {
        font-size: 0.7rem; color: #f87171; display: flex; align-items: center; gap: 0.35rem;
        transition: color 0.2s;
    }
    .pw-checklist li.met { color: #4ade80; }
    .pw-checklist li .ck { display: inline-block; width: 14px; text-align: center; font-weight: 700; }

    /* Confirm match */
    .pw-match-indicator {
        margin: 0.3rem 0 0; font-size: 0.75rem; font-weight: 600;
        min-height: 1.1em;
    }
</style>

<div class="auth-modal-backdrop" id="auth-modal-backdrop"></div>

<!-- Login Modal -->
<div class="auth-modal" id="auth-modal-login" role="dialog" aria-labelledby="auth-login-title" aria-modal="true">
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
                <input type="email" id="auth-email" name="email" class="input-field" placeholder="you@example.com" required autocomplete="email" maxlength="100">
                <p class="field-error" id="auth-email-error"></p>
            </div>
            <div class="auth-field">
                <label for="auth-password">Password</label>
                <div class="auth-password-wrap">
                    <input type="password" id="auth-password" name="password" class="input-field" placeholder="••••••••" required autocomplete="current-password" maxlength="100">
                    <button type="button" class="auth-password-toggle" data-toggle-password aria-label="Show password" aria-controls="auth-password">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1.5 12s3.75-7.5 10.5-7.5S22.5 12 22.5 12 18.75 19.5 12 19.5 1.5 12 1.5 12z"></path><circle cx="12" cy="12" r="3" stroke-width="2"></circle></svg>
                    </button>
                </div>
                <p class="field-error" id="auth-password-error"></p>
            </div>
            <div class="auth-field-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; font-size: 0.875rem; cursor: pointer;">
                    <input type="checkbox" name="remember_me" value="1" style="width: 1rem; height: 1rem; accent-color: #32a1c4;">
                    Remember me
                </label>
                <a href="#" data-forgot-modal="open" style="font-size:0.875rem; color:#53C5E0; text-decoration:none;">Forgot password?</a>
            </div>
            <button type="submit" class="auth-btn-submit">Sign In</button>
        </form>
        <p class="auth-switch">Don't have an account? <a href="#" data-auth-open="register">Register now</a></p>
    </div>
</div>

<!-- Register Modal — 2-Step OTP Flow -->
<div class="auth-modal auth-modal-register" id="auth-modal-register" role="dialog" aria-labelledby="auth-register-title" aria-modal="true">
    <button type="button" class="auth-modal-close" data-auth-close aria-label="Close">&times;</button>
    <div class="auth-modal-inner">
        <h2 id="auth-register-title">Create Account</h2>
        <p class="auth-modal-sub">Join PrintFlow — verify your email or phone to get started</p>
        <div id="auth-register-message"></div>

        <!-- Step indicator (Removed) -->

        <!-- ═══ DIRECT REGISTRATION FORM ═══ -->
        <form method="POST" action="<?php echo htmlspecialchars($base_url); ?>/register/" id="reg-form-final">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="reg_type" value="direct">
            <input type="hidden" name="identifier_type" id="reg-h-type" value="email">

            <!-- Tabs -->
            <div class="reg-tabs">
                <button type="button" class="reg-tab active" id="reg-tab-email" onclick="regSwitchTab('email')">Email</button>
                <button type="button" class="reg-tab" id="reg-tab-phone" onclick="regSwitchTab('phone')">Phone</button>
            </div>

            <!-- Identifier input -->
            <div class="auth-field">
                <label id="reg-id-label" for="reg-identifier">Email Address</label>
                <input type="text" id="reg-identifier" name="identifier" class="input-field" 
                       placeholder="you@example.com" required maxlength="100"
                       value="<?php echo htmlspecialchars($_SESSION['otp_pending_email'] ?? ''); ?>">
                <p class="field-error" id="reg-email-error"></p>
            </div>

            <!-- Password fields -->
            <div class="auth-field">
                <label for="reg-password">Password <span style="color:#dc2626;">*</span></label>
                <div class="auth-password-wrap">
                    <input type="password" id="reg-password" name="password" class="input-field" placeholder="••••••••" required minlength="8" maxlength="100">
                    <button type="button" class="auth-password-toggle" data-toggle-password aria-label="Show password" aria-controls="reg-password">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1.5 12s3.75-7.5 10.5-7.5S22.5 12 22.5 12 18.75 19.5 12 19.5 1.5 12 1.5 12z"></path><circle cx="12" cy="12" r="3" stroke-width="2"></circle></svg>
                    </button>
                </div>
                <ul class="pw-checklist" id="reg-pw-checklist">
                    <li id="pw-rule-len"><span class="ck">✗</span> 8–64 characters</li>
                    <li id="pw-rule-upper"><span class="ck">✗</span> Uppercase</li>
                    <li id="pw-rule-lower"><span class="ck">✗</span> Lowercase</li>
                    <li id="pw-rule-num"><span class="ck">✗</span> Number</li>
                    <li id="pw-rule-spec"><span class="ck">✗</span> Special char</li>
                </ul>
                <div class="pw-strength-wrap">
                    <div class="pw-strength-track"><div class="pw-strength-fill" id="reg-pw-strength-fill"></div></div>
                    <span class="pw-strength-label" id="reg-pw-strength-label" style="color:#64748b;"></span>
                </div>
            </div>
            
            <div class="auth-field">
                <label for="reg-confirm-pw">Confirm Password <span style="color:#dc2626;">*</span></label>
                <div class="auth-password-wrap">
                    <input type="password" id="reg-confirm-pw" name="confirm_password" class="input-field" placeholder="••••••••" required minlength="8" maxlength="100">
                    <button type="button" class="auth-password-toggle" data-toggle-password aria-label="Show password" aria-controls="reg-confirm-pw">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1.5 12s3.75-7.5 10.5-7.5S22.5 12 22.5 12 18.75 19.5 12 19.5 1.5 12 1.5 12z"></path><circle cx="12" cy="12" r="3" stroke-width="2"></circle></svg>
                    </button>
                </div>
                <p class="pw-match-indicator" id="reg-pw-match"></p>
            </div>

            <button type="submit" class="auth-btn-submit" style="margin-top:1.5rem;">Create Account</button>
        </form>

        <p class="auth-switch">Already have an account? <a href="#" data-auth-open="login">Sign in</a></p>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="auth-modal-backdrop" id="forgot-modal-backdrop" style="z-index: 100000;"></div>
<div class="auth-modal" id="forgot-modal" role="dialog" aria-labelledby="forgot-modal-title" aria-modal="true" style="z-index: 100001;">
    <button type="button" class="auth-modal-close" data-forgot-close aria-label="Close">&times;</button>
    <div class="auth-modal-inner">
        <h2 id="forgot-modal-title">Reset Password</h2>
        <p class="auth-modal-sub">Enter your email and we'll send you a reset link</p>
        
        <div id="forgot-message"></div>
        
        <!-- Form -->
        <form id="forgot-form" onsubmit="handleForgotSubmit(event)">
            <input type="hidden" id="forgot-type" value="email">
            
            <div class="auth-field">
                <label id="forgot-label" for="forgot-identifier">Email Address</label>
                <input type="email" id="forgot-identifier" name="identifier" class="input-field" placeholder="you@example.com" required>
            </div>
            
            <button type="submit" class="auth-btn-submit" style="margin-top: 0.5rem;">Send Reset Link</button>
        </form>
        
        <p class="auth-switch" style="margin-top: 1rem;">
            <a href="#" data-forgot-close>Back to login</a>
        </p>
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
        // Close any already-open modal first so switching between login/register works
        if (loginModal) { loginModal.classList.remove('is-open'); }
        if (registerModal) { registerModal.classList.remove('is-open'); }
        var modal = name === 'register' ? registerModal : loginModal;
        if (!modal) return;
        backdrop.classList.add('is-open');
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        
        // Focus first input for accessibility
        setTimeout(function() {
            var firstInput = modal.querySelector('input');
            if (firstInput) firstInput.focus();
        }, 100);
    }
    function closeModal() {
        if (!backdrop) return;
        backdrop.classList.remove('is-open');
        if (loginModal) { loginModal.classList.remove('is-open'); }
        if (registerModal) { registerModal.classList.remove('is-open'); }
        document.body.style.overflow = '';
    }
    function showMessage(modalName, type, text) {
        if (!text) return;
        var el = document.getElementById('auth-' + modalName + '-message');
        if (!el) return;
        el.innerHTML = '<div class="auth-alert-' + type + '">' + escapeHtml(text) + '</div>';
    }
    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }
    function getPasswordIcon(isVisible) {
        return isVisible
            ? '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.584 10.587A2 2 0 0012 14a2 2 0 001.414-.586"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.878 5.099A10.45 10.45 0 0112 4.5c6.75 0 10.5 7.5 10.5 7.5a17.537 17.537 0 01-4.232 4.919M6.228 6.228A17.646 17.646 0 001.5 12s3.75 7.5 10.5 7.5a10.56 10.56 0 005.012-1.228"></path></svg>'
            : '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1.5 12s3.75-7.5 10.5-7.5S22.5 12 22.5 12 18.75 19.5 12 19.5 1.5 12 1.5 12z"></path><circle cx="12" cy="12" r="3" stroke-width="2"></circle></svg>';
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
        var toggle = e.target.closest('[data-toggle-password]');
        if (toggle) {
            e.preventDefault();
            var inputId = toggle.getAttribute('aria-controls');
            var input = inputId ? document.getElementById(inputId) : null;
            if (!input) return;
            var isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            toggle.innerHTML = getPasswordIcon(!isVisible);
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

    // ═══ Forgot Password Modal Logic ═══
    var forgotBackdrop = document.getElementById('forgot-modal-backdrop');
    var forgotModal = document.getElementById('forgot-modal');
    
    function openForgotModal() {
        closeModal(); // Close auth modals first
        if (forgotBackdrop && forgotModal) {
            forgotBackdrop.classList.add('is-open');
            forgotModal.classList.add('is-open');
            forgotModal.setAttribute('aria-hidden', 'false');
            forgotBackdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeForgotModal() {
        if (forgotBackdrop && forgotModal) {
            forgotBackdrop.classList.remove('is-open');
            forgotModal.classList.remove('is-open');
            document.body.style.overflow = '';
            // Clear form
            var form = document.getElementById('forgot-form');
            if (form) form.reset();
            var msg = document.getElementById('forgot-message');
            if (msg) msg.innerHTML = '';
        }
    }
    
    // handleForgotSubmit implementation (must handle AJAX)
    window.handleForgotSubmit = function(e) {
        e.preventDefault();
        var type = document.getElementById('forgot-type').value;
        var identifier = document.getElementById('forgot-identifier').value;
        var submitBtn = e.target.querySelector('button[type="submit"]');
        var originalText = submitBtn.textContent;
        var messageEl = document.getElementById('forgot-message');

        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        var formData = new FormData();
        formData.append('type', type);
        formData.append('identifier', identifier);

        fetch('<?php echo $base_url; ?>/public/api_forgot_password.php', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                var successMsg = data.message || 'Reset code sent!';
                if (data.debug && data.debug.reset_code) {
                    successMsg += '<br><br><strong>DEV MODE:</strong> Code: <code>' + data.debug.reset_code + '</code>';
                }
                messageEl.innerHTML = '<div class="auth-alert-success">' + successMsg + '</div>';
                setTimeout(function() {
                    window.location.href = '<?php echo $base_url; ?>/public/reset-password.php?type=' + type + '&identifier=' + encodeURIComponent(identifier);
                }, 2500);
            } else {
                messageEl.innerHTML = '<div class="auth-alert-error">' + (data.message || 'Error occurred') + '</div>';
            }
        })
        .catch(function(err) {
            messageEl.innerHTML = '<div class="auth-alert-error">Network error. Please try again.</div>';
        })
        .finally(function() {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    };
    
    function showForgotMessage(type, text) {
        var msgEl = document.getElementById('forgot-message');
        if (msgEl) {
            msgEl.innerHTML = '<div class="auth-alert-' + type + '">' + escapeHtml(text) + '</div>';
        }
    }
    
    window.handleForgotSubmit = function(e) {
        e.preventDefault();
        
        var type = document.getElementById('forgot-type').value;
        var identifier = document.getElementById('forgot-identifier').value;
        
        if (!type || !identifier) {
            showForgotMessage('error', 'Please fill in all fields.');
            return;
        }
        
        // Send AJAX request
        var formData = new FormData();
        formData.append('type', type);
        formData.append('identifier', identifier);
        
        fetch('<?php echo $base_url; ?>/api_forgot_password.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showForgotMessage('success', data.message || 'Reset link sent successfully! Check your ' + type + '.');
                setTimeout(function() {
                    closeForgotModal();
                    // Optionally redirect to reset page
                    // window.location.href = '<?php echo $base_url; ?>/public/reset-password.php?type=' + type + '&identifier=' + encodeURIComponent(identifier);
                }, 2000);
            } else {
                showForgotMessage('error', data.message || 'Failed to send reset link. Please try again.');
            }
        })
        .catch(function(error) {
            showForgotMessage('error', 'An error occurred. Please try again.');
            console.error('Error:', error);
        });
    };
    
    // Forgot modal event listeners
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-forgot-modal]')) {
            e.preventDefault();
            openForgotModal();
        }
        if (e.target.matches('[data-forgot-close]')) {
            e.preventDefault();
            closeForgotModal();
        }
        if (e.target === forgotBackdrop) {
            closeForgotModal();
        }
    });

    // ═══ Registration logic ═══
    window.regSwitchTab = function(type) {
        document.getElementById('reg-h-type').value = type;
        
        if (type === 'email') {
            document.getElementById('reg-tab-email').classList.add('active');
            document.getElementById('reg-tab-phone').classList.remove('active');
            document.getElementById('reg-id-label').textContent = 'Email Address';
            document.getElementById('reg-identifier').placeholder = 'you@example.com';
            document.getElementById('reg-identifier').type = 'email';
        } else {
            document.getElementById('reg-tab-phone').classList.add('active');
            document.getElementById('reg-tab-email').classList.remove('active');
            document.getElementById('reg-id-label').textContent = 'Phone Number';
            document.getElementById('reg-identifier').placeholder = '09171234567';
            document.getElementById('reg-identifier').type = 'tel';
        }
    };

    // ═══ VALIDATION ENGINE ═══
    var baseUrl = <?php echo json_encode($base_url); ?>;

    // --- Space-blocking helper ---
    function blockSpaces(el) {
        if (!el) return;
        el.addEventListener('keydown', function(e) { if (e.key === ' ') e.preventDefault(); });
        el.addEventListener('paste', function(e) {
            setTimeout(function() { el.value = el.value.replace(/\s/g, ''); }, 0);
        });
    }
    // Apply to login inputs
    blockSpaces(document.getElementById('auth-email'));
    blockSpaces(document.getElementById('auth-password'));
    // Apply to register inputs
    blockSpaces(document.getElementById('reg-identifier'));
    blockSpaces(document.getElementById('reg-password'));
    blockSpaces(document.getElementById('reg-confirm-pw'));

    // --- Login real-time validation ---
    var loginEmail = document.getElementById('auth-email');
    var loginPw = document.getElementById('auth-password');
    var loginEmailErr = document.getElementById('auth-email-error');
    var loginPwErr = document.getElementById('auth-password-error');

    if (loginEmail) {
        loginEmail.addEventListener('input', function() {
            var v = loginEmail.value.trim();
            if (!v) { loginEmailErr.textContent = ''; loginEmail.classList.remove('is-invalid','is-valid'); return; }
            var valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
            loginEmailErr.textContent = valid ? '' : 'Enter a valid email address.';
            loginEmail.classList.toggle('is-invalid', !valid);
            loginEmail.classList.toggle('is-valid', valid);
        });
    }
    if (loginPw) {
        loginPw.addEventListener('input', function() {
            var v = loginPw.value;
            if (!v) { loginPwErr.textContent = ''; loginPw.classList.remove('is-invalid','is-valid'); return; }
            var ok = v.length >= 8;
            loginPwErr.textContent = ok ? '' : 'Password must be at least 8 characters.';
            loginPw.classList.toggle('is-invalid', !ok);
            loginPw.classList.toggle('is-valid', ok);
        });
    }

    // --- Registration: Real-time email validation + async uniqueness ---
    var regEmail = document.getElementById('reg-identifier');
    var regEmailErr = document.getElementById('reg-email-error');
    var emailCheckTimer = null;
    var regEmailAvailable = true; // Track email uniqueness status

    function isEmailTab() {
        return (document.getElementById('reg-h-type') || {}).value === 'email';
    }

    if (regEmail) {
        regEmail.addEventListener('input', function() {
            if (!isEmailTab()) { regEmailErr.textContent = ''; regEmail.classList.remove('is-invalid','is-valid'); return; }
            var v = regEmail.value.trim();
            if (!v) { regEmailErr.textContent = ''; regEmail.classList.remove('is-invalid','is-valid'); return; }
            var validFmt = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
            if (!validFmt) {
                regEmailErr.textContent = 'Enter a valid email address.';
                regEmail.classList.add('is-invalid'); regEmail.classList.remove('is-valid');
            } else {
                regEmailErr.textContent = ''; regEmail.classList.remove('is-invalid');
            }
        });

        regEmail.addEventListener('blur', function() {
            if (!isEmailTab()) return;
            var v = regEmail.value.trim();
            if (!v || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return;
            clearTimeout(emailCheckTimer);
            emailCheckTimer = setTimeout(function() {
                fetch(baseUrl + '/public/api/check_email.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({email: v})
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.available) {
                        regEmailErr.textContent = 'This email is already registered.';
                        regEmail.classList.add('is-invalid'); regEmail.classList.remove('is-valid');
                        regEmailAvailable = false;
                    } else {
                        regEmailErr.textContent = '';
                        regEmail.classList.add('is-valid'); regEmail.classList.remove('is-invalid');
                        regEmailAvailable = true;
                    }
                })
                .catch(function() {});
            }, 400);
        });
    }

    // --- Registration: Password checklist ---
    var regPw = document.getElementById('reg-password');
    var regCpw = document.getElementById('reg-confirm-pw');
    var pwChecklist = document.getElementById('reg-pw-checklist');
    var pwRules = {
        len:   document.getElementById('pw-rule-len'),
        upper: document.getElementById('pw-rule-upper'),
        lower: document.getElementById('pw-rule-lower'),
        num:   document.getElementById('pw-rule-num'),
        spec:  document.getElementById('pw-rule-spec')
    };
    var matchEl = document.getElementById('reg-pw-match');

    function setRule(el, met) {
        if (!el) return;
        el.classList.toggle('met', met);
        var ck = el.querySelector('.ck');
        if (ck) ck.textContent = met ? '✓' : '✗';
    }

    function updatePwChecklist() {
        var v = regPw ? regPw.value : '';
        // Show all rules on first keystroke
        if (v.length > 0 && pwChecklist) pwChecklist.classList.add('active');
        if (v.length === 0 && pwChecklist) pwChecklist.classList.remove('active');
        setRule(pwRules.len,   v.length >= 8 && v.length <= 64);
        setRule(pwRules.upper, /[A-Z]/.test(v));
        setRule(pwRules.lower, /[a-z]/.test(v));
        setRule(pwRules.num,   /[0-9]/.test(v));
        setRule(pwRules.spec,  /[^A-Za-z0-9]/.test(v));
        updatePwMatch();
    }

    function updatePwMatch() {
        if (!regCpw || !matchEl) return;
        var cpv = regCpw.value;
        var pv  = regPw ? regPw.value : '';
        if (!cpv) { matchEl.textContent = ''; matchEl.style.color = ''; return; }
        if (cpv === pv) {
            matchEl.textContent = '✓ Passwords match';
            matchEl.style.color = '#4ade80';
            regCpw.classList.add('is-valid'); regCpw.classList.remove('is-invalid');
        } else {
            matchEl.textContent = '✗ Passwords do not match';
            matchEl.style.color = '#f87171';
            regCpw.classList.add('is-invalid'); regCpw.classList.remove('is-valid');
        }
    }

    if (regPw) regPw.addEventListener('input', updatePwChecklist);
    if (regCpw) regCpw.addEventListener('input', updatePwMatch);

    // --- Registration form submit guard ---
    var finalForm = document.getElementById('reg-form-final');
    if (finalForm) {
        finalForm.addEventListener('submit', function(e) {
            var pw = regPw ? regPw.value : '';
            var cpw = regCpw ? regCpw.value : '';
            var msgEl = document.getElementById('auth-register-message');
            var errors = [];

            if (pw.length < 8)            errors.push('at least 8 characters');
            if (pw.length > 64)           errors.push('at most 64 characters');
            if (!/[A-Z]/.test(pw))        errors.push('an uppercase letter');
            if (!/[a-z]/.test(pw))        errors.push('a lowercase letter');
            if (!/[0-9]/.test(pw))        errors.push('a number');
            if (!/[^A-Za-z0-9]/.test(pw)) errors.push('a special character');

            if (!regEmailAvailable && isEmailTab()) {
                e.preventDefault();
                msgEl.innerHTML = '<div class="auth-alert-error">This email is already registered. Please use another.</div>';
                return;
            }

            if (errors.length > 0) {
                e.preventDefault();
                msgEl.innerHTML = '<div class="auth-alert-error">Password must contain: ' + errors.join(', ') + '.</div>';
                return;
            }
            if (pw !== cpw) {
                e.preventDefault();
                msgEl.innerHTML = '<div class="auth-alert-error">Passwords do not match.</div>';
                return;
            }
        });
    }
    // Handle URL parameters for opening modals (e.g. from redirects)
    const urlParams = new URLSearchParams(window.location.search);
    const openModalParam = urlParams.get('auth_modal'); // Renamed to avoid conflict with existing openModal function
    const authErrorParam = urlParams.get('error'); // Renamed to avoid conflict with existing authError variable
    
    if (openModalParam === 'register') {
        openModal('register'); // Use existing openModal function
        if (authErrorParam) {
            showMessage('register', 'error', authErrorParam); // Use existing showMessage function
        }
    } else if (openModalParam === 'login') {
        openModal('login'); // Use existing openModal function
        if (authErrorParam) {
            showMessage('login', 'error', authErrorParam); // Use existing showMessage function
        }
    }
})();
</script>
