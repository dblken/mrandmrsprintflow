<?php
/**
 * Reset Password Page
 * Validates the token and allows user to set a new password.
 */
$use_landing_css = true; // Use landing page design tokens
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$valid = false;
$user_data = null;

if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    // Verify token
    $rows = db_query(
        "SELECT * FROM password_resets WHERE reset_token = ? AND used = 0 AND expires_at > NOW() LIMIT 1",
        's', [$token]
    );

    if (empty($rows)) {
        $error = 'This reset link is invalid, has expired, or has already been used.';
    } else {
        $valid = true;
        $user_data = $rows[0];
    }
}

$page_title = 'Reset Your Password - PrintFlow';
?>
<style>
    .auth-card {
        max-width: 440px; margin: 100px auto; padding: 40px;
        background: #00232b; border-radius: 1.5rem;
        box-shadow: 0 30px 70px rgba(0,0,0,.6), 0 0 50px rgba(83,197,224,.05);
        border: 1px solid rgba(255,255,255,.08);
        font-family: 'Inter', -apple-system, sans-serif;
        color: #e0f2fe;
    }
    .auth-icon {
        width: 64px; height: 64px; background: rgba(83,197,224,.12);
        border-radius: 20px; display: flex; align-items: center;
        justify-content: center; margin: 0 auto 28px; color: #32a1c4;
        box-shadow: 0 0 24px rgba(83,197,224,.2);
    }
    .auth-icon svg { width: 32px; height: 32px; }
    .auth-title {
        text-align: center; font-size: 26px; font-weight: 800;
        color: #ffffff; margin-bottom: 12px; letter-spacing: -0.03em;
    }
    .auth-subtitle {
        text-align: center; color: #94a3b8; font-size: 15px;
        margin-bottom: 35px; line-height: 1.6;
    }
    .form-group { margin-bottom: 24px; }
    .auth-label {
        display: block; font-size: 0.875rem; font-weight: 600;
        color: #94a3b8; margin-bottom: 8px;
    }
    .input-wrapper {
        position: relative;
    }
    .form-input {
        width: 100%; padding: 14px 18px; border: 1px solid rgba(255,255,255,.12);
        border-radius: 12px; font-size: 16px; transition: all 0.2s;
        outline: none; background: rgba(255,255,255,.05); color: #e0f2fe;
        box-sizing: border-box;
    }
    .form-input::-ms-reveal,
    .form-input::-ms-clear { display: none; }
    .form-input:focus {
        border-color: #32a1c4; background: rgba(255,255,255,.08);
        box-shadow: 0 0 0 4px rgba(83,197,224,0.15);
    }
    .password-toggle {
        position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
        background: none; border: none; color: #64748b; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        padding: 5px; border-radius: 8px; transition: all 0.2s;
    }
    .password-toggle:hover { color: #e0f2fe; background: rgba(255,255,255,0.08); }
    .btn-submit {
        width: 100%; padding: 15px; font-size: 16px; font-weight: 700;
        background: #32a1c4;
        color: white; border: none; border-radius: 12px; cursor: pointer;
        transition: all 0.25s; box-shadow: 0 0 24px rgba(83,197,224,0.3);
        margin-top: 10px;
    }
    .btn-submit:hover:not(:disabled) {
        background: #2a82a3; transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(83,197,224,0.4);
    }
    .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
    .alert {
        padding: 14px 18px; border-radius: 12px; font-size: 14px;
        margin-bottom: 28px; line-height: 1.6; font-weight: 500;
    }
    .alert-error { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: #fca5a5; }
    .alert-success { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); color: #86efac; }
    .back-link {
        display: block; text-align: center; margin-top: 30px;
        font-size: 15px; color: #53C5E0; text-decoration: none; font-weight: 600;
    }
    .back-link:hover { color: #7acae3; text-decoration: none; }

    /* Validation Feedback */
    .field-error {
        margin: 6px 0 0; font-size: 0.75rem; color: #f87171;
        min-height: 1.1em; transition: opacity 0.15s;
    }
    .form-input.is-invalid { border-color: #f87171 !important; box-shadow: 0 0 0 4px rgba(248,113,113,0.1) !important; }
    .form-input.is-valid { border-color: #4ade80 !important; box-shadow: 0 0 0 4px rgba(74,222,128,0.1) !important; }

    /* Password checklist */
    .pw-checklist {
        list-style: none; padding: 0; margin: 0.75rem 0 0;
        display: none; grid-template-columns: 1fr 1fr; gap: 0.15rem 0.75rem;
    }
    .pw-checklist.active { display: grid; }
    .pw-checklist li {
        font-size: 0.7rem; color: #f87171; display: flex; align-items: center; gap: 0.35rem;
        transition: color 0.2s;
    }
    .pw-checklist li.met { color: #4ade80; }
    .pw-checklist li .ck { display: inline-block; width: 14px; text-align: center; font-weight: 700; }

    .pw-match-indicator {
        margin: 6px 0 0; font-size: 0.75rem; font-weight: 600;
        min-height: 1.1em;
    }
</style>

<div class="auth-card">
    <div class="auth-icon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
    </div>
    
    <h1 class="auth-title">Set New Password</h1>
    <p class="auth-subtitle">Final step! Secure your account with a fresh password.</p>

    <div id="reset-alert">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
    </div>

    <?php if ($valid): ?>
    <form id="resetForm" onsubmit="handleReset(event)">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        
        <div class="form-group">
            <label class="auth-label">New Password</label>
            <div class="input-wrapper">
                <input type="password" name="password" id="password" class="form-input" placeholder="Min. 8 characters" required minlength="8" maxlength="100">
                <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Toggle password">
                    <svg class="eye-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
            <ul class="pw-checklist" id="pw-checklist">
                <li id="pw-rule-len"><span class="ck">✗</span> 8–64 characters</li>
                <li id="pw-rule-upper"><span class="ck">✗</span> Uppercase</li>
                <li id="pw-rule-lower"><span class="ck">✗</span> Lowercase</li>
                <li id="pw-rule-num"><span class="ck">✗</span> Number</li>
                <li id="pw-rule-spec"><span class="ck">✗</span> Special char</li>
            </ul>
        </div>
        
        <div class="form-group">
            <label class="auth-label">Confirm Password</label>
            <div class="input-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Repeat new password" required minlength="8" maxlength="100">
                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" aria-label="Toggle password">
                    <svg class="eye-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
            <p class="pw-match-indicator" id="pw-match-indicator"></p>
        </div>
        
        <button type="submit" id="submitBtn" class="btn-submit">Update Password</button>
    </form>
    <?php endif; ?>

    <a href="<?php echo $base_url; ?>/" class="back-link">Back to Home</a>
</div>

<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
}

// ═══ VALIDATION ENGINE ═══
function blockSpaces(el) {
    if (!el) return;
    el.addEventListener('keydown', function(e) { if (e.key === ' ') e.preventDefault(); });
    el.addEventListener('paste', function(e) {
        setTimeout(function() { el.value = el.value.replace(/\s/g, ''); }, 0);
    });
}

const passInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const pwChecklist = document.getElementById('pw-checklist');
const matchIndicator = document.getElementById('pw-match-indicator');

blockSpaces(passInput);
blockSpaces(confirmInput);

const pwRules = {
    len:   document.getElementById('pw-rule-len'),
    upper: document.getElementById('pw-rule-upper'),
    lower: document.getElementById('pw-rule-lower'),
    num:   document.getElementById('pw-rule-num'),
    spec:  document.getElementById('pw-rule-spec')
};

function setRule(el, met) {
    if (!el) return;
    el.classList.toggle('met', met);
    const ck = el.querySelector('.ck');
    if (ck) ck.textContent = met ? '✓' : '✗';
}

function updatePwChecklist() {
    const v = passInput.value;
    if (v.length > 0) pwChecklist.classList.add('active');
    else pwChecklist.classList.remove('active');

    setRule(pwRules.len,   v.length >= 8 && v.length <= 64);
    setRule(pwRules.upper, /[A-Z]/.test(v));
    setRule(pwRules.lower, /[a-z]/.test(v));
    setRule(pwRules.num,   /[0-9]/.test(v));
    setRule(pwRules.spec,  /[^A-Za-z0-9]/.test(v));
    
    updatePwMatch();
}

function updatePwMatch() {
    const pv = passInput.value;
    const cpv = confirmInput.value;
    if (!cpv) { matchIndicator.textContent = ''; return; }
    
    if (pv === cpv) {
        matchIndicator.textContent = '✓ Passwords match';
        matchIndicator.style.color = '#4ade80';
        confirmInput.classList.add('is-valid');
        confirmInput.classList.remove('is-invalid');
    } else {
        matchIndicator.textContent = '✗ Passwords do not match';
        matchIndicator.style.color = '#f87171';
        confirmInput.classList.add('is-invalid');
        confirmInput.classList.remove('is-valid');
    }
}

if (passInput) passInput.addEventListener('input', updatePwChecklist);
if (confirmInput) confirmInput.addEventListener('input', updatePwMatch);

async function handleReset(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const alert = document.getElementById('reset-alert');
    const pw = passInput.value;
    const cpw = confirmInput.value;

    // Final checks
    const errors = [];
    if (pw.length < 8)            errors.push('at least 8 characters');
    if (pw.length > 64)           errors.push('at most 64 characters');
    if (!/[A-Z]/.test(pw))        errors.push('an uppercase letter');
    if (!/[a-z]/.test(pw))        errors.push('a lowercase letter');
    if (!/[0-9]/.test(pw))        errors.push('a number');
    if (!/[^A-Za-z0-9]/.test(pw)) errors.push('a special character');

    if (errors.length > 0) {
        alert.innerHTML = '<div class="alert alert-error">Password must contain: ' + errors.join(', ') + '.</div>';
        return;
    }

    if (pw !== cpw) {
        alert.innerHTML = '<div class="alert alert-error">Passwords do not match.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerText = 'Setting new password...';

    const formData = new FormData(e.target);

    try {
        const response = await fetch('<?php echo $asset_base; ?>/api_update_password.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert.innerHTML = `<div class="alert alert-success">${result.message}</div>`;
            document.getElementById('resetForm').style.display = 'none';
            setTimeout(() => {
                window.location.href = '<?php echo $base_url; ?>/?auth_modal=login';
            }, 2500);
        } else {
            alert.innerHTML = `<div class="alert alert-error">${result.message}</div>`;
            btn.disabled = false;
            btn.innerText = 'Update Password';
        }
    } catch (error) {
        alert.innerHTML = '<div class="alert alert-error">A network error occurred. Please try again.</div>';
        btn.disabled = false;
        btn.innerText = 'Update Password';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
