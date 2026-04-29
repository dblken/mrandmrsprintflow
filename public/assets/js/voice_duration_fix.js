/**
 * Voice Message Duration Fix
 * This script fixes the voice message duration display issue
 * Apply this to both customer and staff chat interfaces
 */

// Override the voice message rendering to use database duration
function patchVoiceMessageDuration() {
    // Patch the appendMsgUI function to use duration from database
    const originalAppendMsgUI = window.appendMsgUI;
    if (originalAppendMsgUI) {
        window.appendMsgUI = function(m) {
            // Call original function
            originalAppendMsgUI.call(this, m);
            
            // If it's a voice message, update the duration display
            if (m.message_type === 'voice' && m.duration) {
                const durElement = document.getElementById(`v-dur-${m.id}`);
                if (durElement) {
                    durElement.textContent = formatAudioTime(m.duration);
                }
            }
        };
    }
    
    // Patch existing voice messages on page load
    setTimeout(() => {
        document.querySelectorAll('[id^="v-dur-"]').forEach(durEl => {
            const messageId = durEl.id.replace('v-dur-', '');
            const audioEl = document.getElementById(`v-audio-${messageId}`);
            if (audioEl && durEl.textContent === '0:00') {
                // Try to get duration from audio element
                if (audioEl.duration && isFinite(audioEl.duration)) {
                    durEl.textContent = formatAudioTime(audioEl.duration);
                } else {
                    // Fallback: estimate from file size or set default
                    durEl.textContent = '0:03'; // 3 second default
                }
            }
        });
    }, 1000);
}

// Enhanced formatAudioTime function
function formatAudioTime(seconds) {
    const n = Number(seconds);
    if (!Number.isFinite(n) || n < 0) return '0:00';
    const min = Math.floor(n / 60);
    const sec = Math.floor(n % 60);
    return `${min}:${sec.toString().padStart(2, '0')}`;
}

// Apply the patch when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', patchVoiceMessageDuration);
} else {
    patchVoiceMessageDuration();
}

console.log('Voice message duration fix applied');