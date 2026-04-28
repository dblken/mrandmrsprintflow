/**
 * PrintFlow WebRTC Call System v4.7 (Global & Stable)
 * =================================================
 */
'use strict';

(function(window) {
    // Prevent double-execution of the library logic
    if (window.PFCall && window.PFCall._libLoaded) return;

    const PF_STATE = Object.freeze({
        IDLE:     'idle',
        CALLING:  'calling',
        INCOMING: 'incoming',
        IN_CALL:  'in-call',
        ENDED:    'ended'
    });

    const PF_ICE_SERVERS = [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
    ];

    const PF_DEFAULT_AVATAR =
        'data:image/svg+xml;charset=UTF-8,' +
        encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" rx="60" fill="#e2e8f0"/><circle cx="60" cy="44" r="22" fill="#94a3b8"/><path d="M28 100c7-18 22-28 32-28s25 10 32 28" fill="#94a3b8"/></svg>');

    class PFAudio {
        constructor() {
            this._el  = null;
            this._ctx = null;
            this._osc = null;
        }

        play(basePath) {
            if (!this._el) {
                this._el = document.createElement('audio');
                this._el.loop = true;
                this._el.style.display = 'none';
                document.body.appendChild(this._el);
            }
            this._el.src = 'https://www.soundjay.com/phone/sounds/phone-ringing-1.mp3';
            this._el.play().catch(() => {
                this._el.src = `${basePath}/public/assets/audio/ringtone.mp3`;
                this._el.play().catch(() => this._beep());
            });
        }

        _beep() {
            if (this._osc) return;
            try {
                this._ctx = new (window.AudioContext || window.webkitAudioContext)();
                const tick = () => {
                    const osc  = this._ctx.createOscillator();
                    const gain = this._ctx.createGain();
                    osc.frequency.value = 480;
                    gain.gain.setValueAtTime(0.05, this._ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, this._ctx.currentTime + 0.4);
                    osc.connect(gain); gain.connect(this._ctx.destination);
                    osc.start(); osc.stop(this._ctx.currentTime + 0.4);
                };
                tick();
                this._osc = setInterval(tick, 2000);
            } catch (e) {}
        }

        stop() {
            if (this._el) { this._el.pause(); this._el.src = ''; }
            if (this._osc) { clearInterval(this._osc); this._osc = null; }
            try { if (this._ctx) { this._ctx.close(); this._ctx = null; } } catch (e) {}
        }
    }

    class PFCallManager {
        constructor() {
            this.userId = null;
            this.userType = null;
            this.userName = '';
            this.userAvatar = '';
            this.basePath = '';

            this.state = PF_STATE.IDLE;
            this.socket = null;
            this.isSocketConnected = false;

            this.partnerId = null;
            this.partnerType = null;
            this.partnerName = '';
            this.partnerAvatar = '';
            this.callType = 'voice';
            this.isInitiator = false;

            this.pc = null;
            this.localStream = null;
            this.iceQueue = [];

            this._timerInt = null;
            this._timerStart = 0;
            this._noAnswerTimeout = null;
            this._uiReady = false;
            this._initialized = false;
            this._libLoaded = true;
            
            this.audio = new PFAudio();
            this._tabActive = true;
            this._notification = null;
            this._endedCleanupTimeout = null;

            this._initVisibilityTracker();
        }

        _initVisibilityTracker() {
            document.addEventListener('visibilitychange', () => {
                this._tabActive = (document.visibilityState === 'visible');
                if (this._tabActive) {
                    this._ensureSocketConnection();
                }
            });
            window.addEventListener('pageshow', () => this._ensureSocketConnection());
            window.addEventListener('focus', () => this._ensureSocketConnection());
        }

        _ensureSocketConnection() {
            if (!this._initialized || !this.socket) return;
            if (!this.isSocketConnected && typeof this.socket.connect === 'function') {
                this.socket.connect();
            }
        }

        init(config) {
            if (window.__PFCallInitialized && this._initialized && this.userId === config.userId) {
                console.log("[PFCall] Already initialized, verifying UI...");
                this._ensureUI();
                return;
            }
            if (window.__PFCallInitialized && this.userId === config.userId) {
                console.log("[PFCall] Already initialized - skipping");
                this._ensureUI();
                return;
            }
            window.__PFCallInitialized = true;
            this._initialized = true;
            console.log("[PFCall] Initializing system...");

            this.userId = config.userId;
            this.userType = config.userType;
            this.userName = config.userName || 'User';
            this.userAvatar = config.userAvatar || '';
            this.basePath = (config.basePath || '').replace(/\/$/, '');

            this._buildUI();
            this._connectSocket();
            
            if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        }

        _connectSocket() {
            if (this.socket) return;
            if (typeof io === 'undefined') {
                console.error("[PFCall] Socket.IO library (io) not found. Signaling disabled.");
                window.PFCallSocketConnected = false;
                return;
            }

            // In production, point this to your separate Railway Node.js service URL.
            // Replace '<railway-node-service-url>' with the actual URL provided by Railway.
            const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            const url = isLocalhost 
                ? 'http://localhost:3000' 
                : 'https://mrandmrsprintflow-production.up.railway.app';

            console.log(`[PFCall] Connecting to signaling server: ${url}`);

            this.socket = io(url, {
                transports: ['websocket', 'polling'],
                query: { userId: this.userId, userType: this.userType },
                secure: url.startsWith('https'),
                reconnection: true,
                reconnectionAttempts: 5,
                reconnectionDelay: 2000,
                reconnectionDelayMax: 5000,
                timeout: 10000
            });

            this.socket.on('connect', () => {
                console.log("[PFCall] Socket connected successfully.");
                this.isSocketConnected = true;
                window.PFCallSocketConnected = true;
                window.dispatchEvent(new CustomEvent('PFCallConnected'));
                this.socket.emit('register', {
                    userId: this.userId,
                    userType: this.userType,
                    name: this.userName,
                    avatar: this.userAvatar
                });
            });

            this.socket.on('connect_error', () => {
                this.isSocketConnected = false;
                window.PFCallSocketConnected = false;
                if (!window.__PFCallFallbackWarned) {
                    window.__PFCallFallbackWarned = true;
                    console.warn("Socket not available, switching to fallback mode");
                }
            });
            this.socket.on('disconnect', () => {
                this.isSocketConnected = false;
                window.PFCallSocketConnected = false;
                window.dispatchEvent(new CustomEvent('PFCallDisconnected'));
            });
            this.socket.on('incomingCall', (data) => this._handleIncomingCall(data));
            this.socket.on('pf-call-accepted', () => this._onCallAccepted());
            this.socket.on('pf-call-rejected', () => this._onCallRejected());
            this.socket.on('pf-call-ended', () => this._onCallEnded());
            this.socket.on('pf-call-busy', (data) => this._flashEnded(data.message || 'User is busy.'));
            this.socket.on('pf-call-error', (data) => this._flashEnded(data.message || 'Calling failed.'));
            this.socket.on('pf-webrtc-offer', (data) => this._handleOffer(data));
            this.socket.on('pf-webrtc-answer', (data) => this._handleAnswer(data));
            this.socket.on('pf-ice-candidate', (data) => this._handleIceCandidate(data));
        }

        _normalizeAvatar(avatar, fallbackName = 'User') {
            if (typeof avatar === 'string' && avatar.trim()) {
                return avatar;
            }
            return PF_DEFAULT_AVATAR;
        }

        async startCall(targetId, targetType, targetName, targetAvatar, type = 'voice') {
            if (this.state !== PF_STATE.IDLE) return;
            if (!this.socket || !this.isSocketConnected) {
                if (!window.__PFCallFallbackWarned) {
                    window.__PFCallFallbackWarned = true;
                    console.warn("Socket not available, switching to fallback mode");
                }
                this._flashEnded('Calling is unavailable right now.');
                return;
            }
            this.state = PF_STATE.CALLING;
            this.partnerId = targetId;
            this.partnerType = targetType;
            this.partnerName = targetName || 'User';
            this.partnerAvatar = this._normalizeAvatar(targetAvatar, this.partnerName);
            this.callType = type;
            this.isInitiator = true;
            this._showOverlay(PF_STATE.CALLING, this.partnerName, this.partnerAvatar, `Calling... (${type})`);
            this.audio.play(this.basePath);

            this._noAnswerTimeout = setTimeout(() => {
                if (this.state === PF_STATE.CALLING) {
                    this._logCallEvent('missed');
                    this._flashEnded('No answer.');
                }
            }, 30000);

            try {
                this.localStream = await this._getMedia(type === 'video');
                if (this.state !== PF_STATE.CALLING) return;
                
                this.activeOrderId = (window.PFCallState ? window.PFCallState.activeId : null) || (typeof activeId !== 'undefined' ? activeId : null);

                this.socket.emit('callUser', {
                    toUserId: targetId, toUserType: targetType, type: type,
                    fromName: this.userName, fromAvatar: this.userAvatar,
                    orderId: this.activeOrderId
                });
            } catch (err) {
                this._flashEnded('Camera/Mic access denied.');
            }
        }

        _handleIncomingCall(data) {
            if (this.state === PF_STATE.ENDED) {
                this._cleanUp();
            }
            if (this.state !== PF_STATE.IDLE) {
                this.socket.emit('pf-call-busy', { toUserId: data.fromUserId, toUserType: data.fromUserType });
                return;
            }
            this.state = PF_STATE.INCOMING;
            this.partnerId = data.fromUserId || data.callerId;
            this.partnerType = data.fromUserType || data.callerType;
            this.partnerName = data.fromName || 'User';
            this.partnerAvatar = this._normalizeAvatar(data.fromAvatar, this.partnerName);
            this.callType = data.callType || data.type || 'voice';
            this.isInitiator = false;
            
            // Critical: Store the orderId from the signaling payload
            this.activeOrderId = data.orderId || null;
            if (this.activeOrderId && window.PFCallState) {
                window.PFCallState.activeId = this.activeOrderId;
            }

            this.audio.play(this.basePath);
            if (!document.querySelector('.chat-page') || !this._tabActive) {
                this._showToast(data);
                this._showSystemNotification(data);
            } else {
                this._showOverlay(PF_STATE.INCOMING, this.partnerName, this.partnerAvatar, `Incoming ${this.callType} call...`);
            }
            this._noAnswerTimeout = setTimeout(() => {
                if (this.state === PF_STATE.INCOMING) {
                    this._logCallEvent('missed');
                    this._cleanUp();
                }
            }, 35000);
        }

        async accept() {
            if (this.state !== PF_STATE.INCOMING) return;
            this._hideToast();
            this._closeSystemNotification();
            this.audio.stop();
            clearTimeout(this._noAnswerTimeout);
            try {
                this.localStream = await this._getMedia(this.callType === 'video');
                this.socket.emit('pf-accept-call', { toUserId: this.partnerId, toUserType: this.partnerType });
                this.state = PF_STATE.IN_CALL;
                this._showOverlay(PF_STATE.IN_CALL, this.partnerName, this.partnerAvatar, 'Connected');
                this._startTimer();
                this._createPC();
            } catch (err) {
                this._flashEnded('Failed to access camera/mic.');
            }
        }

        reject() {
            if (this.state !== PF_STATE.INCOMING) return;
            this._hideToast();
            this._closeSystemNotification();
            this.socket.emit('pf-reject-call', { toUserId: this.partnerId, toUserType: this.partnerType });
            this._logCallEvent('declined');
            this._flashEnded('Call declined.');
        }

        endCall() {
            if (this.state === PF_STATE.IDLE) return;
            const wasInCall = this.state === PF_STATE.IN_CALL;
            const duration = wasInCall ? Math.floor((Date.now() - this._timerStart) / 1000) : 0;
            this.socket.emit('pf-end-call', { toUserId: this.partnerId, toUserType: this.partnerType });
            if (wasInCall) this._logCallEvent('ended', duration);
            else if (this.state === PF_STATE.CALLING) this._logCallEvent('missed');
            this._cleanUp();
        }

        _onCallAccepted() {
            clearTimeout(this._noAnswerTimeout);
            this.audio.stop();
            this.state = PF_STATE.IN_CALL;
            this._showOverlay(PF_STATE.IN_CALL, this.partnerName, this.partnerAvatar, 'Connected');
            this._startTimer();
            this._createPC();
            this.pc.createOffer().then(offer => {
                this.pc.setLocalDescription(offer);
                this.socket.emit('pf-webrtc-offer', { toUserId: this.partnerId, toUserType: this.partnerType, offer });
            });
        }

        _onCallRejected() { this._flashEnded('Call declined.'); }
        _onCallEnded() { this._flashEnded('Call ended.'); }

        _logCallEvent(type, duration = 0) {
            const orderId = this.activeOrderId || (window.PFCallState && window.PFCallState.activeId) || (typeof activeId !== 'undefined' ? activeId : null);
            if (!orderId) {
                console.warn("[PFCall] Cannot log call event: No orderId associated with this session.");
                return;
            }
            const fd = new FormData();
            fd.append('order_id', orderId);
            fd.append('event_type', type);
            fd.append('call_type', this.callType);
            fd.append('duration', duration);
            fd.append('caller_id', this.isInitiator ? this.userId : this.partnerId);
            fd.append('caller_type', this.isInitiator ? this.userType : this.partnerType);
            fetch(`${this.basePath}/public/api/chat/send_call_event.php`, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) console.error("[PFCall] Failed to log event:", res.error);
                })
                .catch(err => console.error("[PFCall] Network error logging event:", err));
        }

        $(id) { return document.getElementById(id); }

        _buildUI() {
            if (this.$('pf-call-overlay')) return;
            const overlay = document.createElement('div');
            overlay.id = 'pf-call-overlay';
            overlay.innerHTML = `
                <div id="pf-video-grid">
                    <video id="pf-remote-video" autoplay playsinline></video>
                    <video id="pf-local-video" autoplay playsinline muted></video>
                </div>
                <div class="pf-call-card">
                    <div class="pf-avatar-ring" id="pf-avatar-ring">
                        <div class="pf-ripple pf-ripple-1"></div>
                        <div class="pf-ripple pf-ripple-2"></div>
                        <div class="pf-ripple pf-ripple-3"></div>
                        <img id="pf-call-avatar" src="" class="pf-call-avatar-img">
                    </div>
                    <div id="pf-call-name" class="pf-call-name">User</div>
                    <div id="pf-call-label" class="pf-call-label">Calling...</div>
                    <div id="pf-call-timer" class="pf-call-timer" style="display:none;">00:00</div>
                    <div class="pf-call-actions" id="pf-call-actions">
                        <div class="pf-btn-container" id="pf-act-reject">
                            <button class="pf-btn pf-btn-reject" onclick="window.PFCall.reject()"><i class="bi bi-telephone-x-fill"></i></button>
                            <span class="pf-btn-label">Decline</span>
                        </div>
                        <div class="pf-btn-container" id="pf-act-accept">
                            <button class="pf-btn pf-btn-accept" onclick="window.PFCall.accept()"><i class="bi bi-telephone-fill"></i></button>
                            <span class="pf-btn-label">Accept</span>
                        </div>
                        <div class="pf-btn-container" id="pf-act-end" style="display:none;">
                            <button class="pf-btn pf-btn-end" onclick="window.PFCall.endCall()"><i class="bi bi-telephone-x-fill"></i></button>
                            <span class="pf-btn-label">End</span>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);

            const toast = document.createElement('div');
            toast.id = 'pf-call-toast';
            toast.innerHTML = `
                <img src="" class="pf-toast-avatar" id="pf-toast-img">
                <div class="pf-toast-content">
                    <div class="pf-toast-name" id="pf-toast-name">User</div>
                    <div class="pf-toast-status">Incoming Call...</div>
                </div>
                <div class="pf-toast-actions">
                    <button class="pf-toast-btn pf-toast-reject" onclick="window.PFCall.reject()"><i class="bi bi-x-lg"></i></button>
                    <button class="pf-toast-btn pf-toast-accept" onclick="window.PFCall.accept()"><i class="bi bi-telephone-fill"></i></button>
                </div>
            `;
            toast.onclick = (e) => {
                if (!e.target.closest('button')) {
                    this._showOverlay(PF_STATE.INCOMING, null, null, null);
                    this._hideToast();
                }
            };
            document.body.appendChild(toast);
        }

        _showOverlay(state, name, avatar, label) {
            const overlay = this.$('pf-call-overlay');
            if (!overlay) return;
            overlay.className = `pf-call-overlay--${state}`;
            if (name) this.$('pf-call-name').textContent = name;
            if (avatar) this.$('pf-call-avatar').src = avatar;
            if (label) this.$('pf-call-label').textContent = label;
            const ring = this.$('pf-avatar-ring');
            if (state === PF_STATE.CALLING || state === PF_STATE.INCOMING) ring.classList.add('pf-ripple-active');
            else ring.classList.remove('pf-ripple-active');
            this.$('pf-act-reject').style.display = (state === PF_STATE.INCOMING) ? 'flex' : 'none';
            this.$('pf-act-accept').style.display = (state === PF_STATE.INCOMING) ? 'flex' : 'none';
            this.$('pf-act-end').style.display = (state === PF_STATE.CALLING || state === PF_STATE.IN_CALL) ? 'flex' : 'none';
            if (this.callType === 'video' && state === PF_STATE.IN_CALL) this.$('pf-video-grid').style.display = 'block';
            else this.$('pf-video-grid').style.display = 'none';
        }

        _showToast(data) {
            const toast = this.$('pf-call-toast');
            if (!toast) return;
            this.$('pf-toast-name').textContent = this.partnerName || data.fromName || 'User';
            this.$('pf-toast-img').src = this.partnerAvatar || this._normalizeAvatar(data.fromAvatar);
            toast.classList.add('active');
        }

        _showSystemNotification(data) {
            if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;
            const title = `Incoming ${this.callType === 'video' ? 'Video' : 'Voice'} Call`;
            const options = {
                body: `${this.partnerName || data.fromName || 'Someone'} is calling you on PrintFlow`,
                icon: this.partnerAvatar || this._normalizeAvatar(data.fromAvatar),
                tag: 'pf-incoming-call',
                renotify: true, requireInteraction: true, silent: true
            };
            this._notification = new Notification(title, options);
            this._notification.onclick = () => { window.focus(); this.accept(); };
        }

        _closeSystemNotification() { if (this._notification) { this._notification.close(); this._notification = null; } }
        _hideToast() { this.$('pf-call-toast')?.classList.remove('active'); }
        _flashEnded(msg) {
            if (this._endedCleanupTimeout) {
                clearTimeout(this._endedCleanupTimeout);
            }
            this.state = PF_STATE.ENDED;
            this._showOverlay(PF_STATE.ENDED, this.partnerName, this.partnerAvatar, msg);
            this._endedCleanupTimeout = setTimeout(() => this._cleanUp(), 2500);
        }

        _cleanUp() {
            clearTimeout(this._noAnswerTimeout);
            this._noAnswerTimeout = null;
            if (this._endedCleanupTimeout) {
                clearTimeout(this._endedCleanupTimeout);
                this._endedCleanupTimeout = null;
            }
            this.state = PF_STATE.IDLE;
            this.audio.stop();
            this._stopTimer();
            this._hideToast();
            this._closeSystemNotification();
            if (this.localStream) { this.localStream.getTracks().forEach(t => t.stop()); this.localStream = null; }
            if (this.pc) { this.pc.close(); this.pc = null; }
            this.iceQueue = [];
            this.partnerId = null;
            this.partnerType = null;
            this.partnerName = '';
            this.partnerAvatar = '';
            const overlay = this.$('pf-call-overlay');
            if (overlay) overlay.className = '';
            if (this.$('pf-call-timer')) this.$('pf-call-timer').style.display = 'none';
        }

        _startTimer() {
            this._stopTimer();
            this._timerStart = Date.now();
            const el = this.$('pf-call-timer');
            el.style.display = 'block';
            this._timerInt = setInterval(() => {
                const sec = Math.floor((Date.now() - this._timerStart) / 1000);
                const m = Math.floor(sec / 60).toString().padStart(2, '0');
                const s = (sec % 60).toString().padStart(2, '0');
                el.textContent = `${m}:${s}`;
            }, 1000);
        }

        _stopTimer() { if (this._timerInt) { clearInterval(this._timerInt); this._timerInt = null; } }

        async _getMedia(video) {
            try {
                return await navigator.mediaDevices.getUserMedia({ audio: true, video: video ? { facingMode: 'user' } : false });
            } catch (e) {
                if (video) return await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                throw e;
            }
        }

        _createPC() {
            if (this.pc) this.pc.close();
            this.pc = new RTCPeerConnection({ iceServers: PF_ICE_SERVERS });
            this.pc.onicecandidate = e => {
                if (e.candidate) this.socket.emit('pf-ice-candidate', { toUserId: this.partnerId, toUserType: this.partnerType, candidate: e.candidate });
            };
            this.pc.ontrack = e => {
                const remote = this.$('pf-remote-video');
                if (remote && e.streams[0]) remote.srcObject = e.streams[0];
            };
            if (this.localStream) this.localStream.getTracks().forEach(t => this.pc.addTrack(t, this.localStream));
        }

        _handleOffer(data) {
            if (!this.pc) this._createPC();
            this.pc.setRemoteDescription(new RTCSessionDescription(data.offer))
                .then(() => this.pc.createAnswer())
                .then(answer => {
                    this.pc.setLocalDescription(answer);
                    this.socket.emit('pf-webrtc-answer', { toUserId: this.partnerId, toUserType: this.partnerType, answer });
                    while (this.iceQueue.length) this.pc.addIceCandidate(this.iceQueue.shift());
                });
        }

        _handleAnswer(data) {
            this.pc.setRemoteDescription(new RTCSessionDescription(data.answer));
            while (this.iceQueue.length) this.pc.addIceCandidate(this.iceQueue.shift());
        }

        _handleIceCandidate(data) {
            const c = new RTCIceCandidate(data.candidate);
            if (this.pc && this.pc.remoteDescription) this.pc.addIceCandidate(c);
            else this.iceQueue.push(c);
        }

        _ensureUI() { this._buildUI(); }
    }

    // ─── GLOBAL SINGLETON ─────────────────────────────────────────────────────────
    if (!window.PFCall) {
        window.PFCall = new PFCallManager();
        window.PFCall.call = function(id, type, name, avatar, callType) {
            return window.PFCall.startCall(id, type, name, avatar, callType);
        };
        // Backward compatibility for .initialize()
        window.PFCall.initialize = function(userId, userType, userName, userAvatar, basePath) {
            return window.PFCall.init({ userId, userType, userName, userAvatar, basePath });
        };
        document.addEventListener('turbo:render', () => window.PFCall._ensureUI());
    }
})(window);
