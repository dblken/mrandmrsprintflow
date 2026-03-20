<?php
/**
 * Success Modal Component
 * Reusable modal for successful actions (Order placed, updated, etc.)
 */
?>
<style>
    .pf-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 30000;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .pf-modal-overlay.active {
        display: flex;
        opacity: 1;
    }
    .pf-modal-card {
        background: white;
        border-radius: 20px;
        width: 100%;
        max-width: 450px;
        padding: 2.1rem 2.2rem;
        text-align: center;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: scale(0.9);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .pf-modal-overlay.active .pf-modal-card {
        transform: scale(1);
    }
    .pf-modal-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #111827;
        margin-bottom: 0.75rem;
    }
    .pf-modal-message {
        color: #6b7280;
        line-height: 1.6;
        margin-bottom: 2rem;
        font-size: 0.95rem;
    }
    .pf-modal-actions {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .pf-modal-btn {
        padding: 0.875rem 1.5rem;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.9375rem;
        text-decoration: none;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }
    .pf-modal-btn-primary {
        background: #0a2530;
        color: white;
    }
    .pf-modal-btn-primary:hover {
        background: #0d3038;
        transform: translateY(-2px);
    }
    .pf-modal-btn-secondary {
        background: #f8fafc;
        color: #334155;
        border: 1px solid #e2e8f0;
    }
    .pf-modal-btn-secondary:hover {
        background: #f1f5f9;
    }
</style>

<div id="pfSuccessModal" class="pf-modal-overlay">
    <div class="pf-modal-card">
        <h2 id="pfModalTitle" class="pf-modal-title">Success!</h2>
        <p id="pfModalMessage" class="pf-modal-message">Your action was completed successfully.</p>
        
        <div class="pf-modal-actions">
            <a id="pfModalPrimaryBtn" href="#" class="pf-modal-btn pf-modal-btn-primary">View Results</a>
            <a id="pfModalSecondaryBtn" href="#" class="pf-modal-btn pf-modal-btn-secondary">Go to Dashboard</a>
        </div>
    </div>
</div>

<script>
function showSuccessModal(title, message, primaryUrl, secondaryUrl, primaryText = 'View Details', secondaryText = 'Go to Dashboard') {
    const modal = document.getElementById('pfSuccessModal');
    const titleEl = document.getElementById('pfModalTitle');
    const messageEl = document.getElementById('pfModalMessage');
    const primaryBtn = document.getElementById('pfModalPrimaryBtn');
    const secondaryBtn = document.getElementById('pfModalSecondaryBtn');

    titleEl.textContent = title;
    messageEl.innerHTML = message;
    primaryBtn.href = primaryUrl;
    primaryBtn.textContent = primaryText;
    secondaryBtn.href = secondaryUrl;
    secondaryBtn.textContent = secondaryText;

    // Handle Close behavior if URL is '#'
    primaryBtn.onclick = (e) => {
        if (primaryUrl === '#') {
            e.preventDefault();
            hideSuccessModal();
        }
    };
    secondaryBtn.onclick = (e) => {
        if (secondaryUrl === '#') {
            e.preventDefault();
            hideSuccessModal();
        }
    };

    modal.classList.add('active');
}

function hideSuccessModal() {
    document.getElementById('pfSuccessModal').classList.remove('active');
}

// Auto-show if session variables are set (using script injection in the page that needs it)
</script>
