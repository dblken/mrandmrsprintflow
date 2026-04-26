const express = require('express');
const http = require('http');
const { Server } = require('socket.io');

const PORT = Number(process.env.PORT || 3000);
const HOST = process.env.HOST || '0.0.0.0';
const app = express();
const server = http.createServer(app);

const configuredOrigins = (process.env.ALLOWED_ORIGINS || '')
    .split(',')
    .map((origin) => origin.trim())
    .filter(Boolean);

const io = new Server(server, {
    cors: {
        origin(origin, callback) {
            if (!origin || configuredOrigins.length === 0 || configuredOrigins.includes(origin)) {
                callback(null, true);
                return;
            }

            callback(new Error(`Origin not allowed: ${origin}`));
        },
        methods: ['GET', 'POST']
    }
});

const activeUsers = new Map();
const activeCalls = new Map();

function makeUserKey(userId, userType) {
    return `${userType}:${userId}`;
}

function broadcastUserStatus(userId, userType, isOnline) {
    io.emit('user-status-change', { userId, userType, isOnline });
}

function getActiveCallByUser(key) {
    const callId = activeCalls.get(key);
    return callId ? activeCalls.get(callId) : null;
}

function storeActiveCall(callId, call) {
    activeCalls.set(callId, call);
    activeCalls.set(call.callerKey, callId);
    activeCalls.set(call.calleeKey, callId);
}

function clearActiveCall(callId) {
    const call = activeCalls.get(callId);
    if (!call || typeof call !== 'object') {
        return null;
    }

    activeCalls.delete(callId);
    activeCalls.delete(call.callerKey);
    activeCalls.delete(call.calleeKey);
    return call;
}

function relayToUser(targetKey, eventName, payload) {
    const target = activeUsers.get(targetKey);
    if (!target) {
        return false;
    }

    io.to(target.socketId).emit(eventName, payload);
    return true;
}

app.get('/', (_req, res) => {
    res.json({
        ok: true,
        service: 'PrintFlow signaling server',
        port: PORT
    });
});

app.get('/health', (_req, res) => {
    res.json({ ok: true });
});

io.on('connection', (socket) => {
    const queryUserId = socket.handshake.query.userId;
    const queryUserType = socket.handshake.query.userType;

    const registerUser = (payload = {}) => {
        const userId = payload.userId || queryUserId;
        const userType = payload.userType || queryUserType;
        if (!userId || !userType) {
            return null;
        }

        const key = makeUserKey(userId, userType);
        activeUsers.set(key, {
            socketId: socket.id,
            userId,
            userType,
            name: payload.name || '',
            avatar: payload.avatar || ''
        });

        socket.data.userKey = key;
        socket.data.userId = userId;
        socket.data.userType = userType;

        broadcastUserStatus(userId, userType, true);
        return key;
    };

    registerUser();

    socket.on('register', (payload) => {
        registerUser(payload);
    });

    socket.on('callUser', (payload = {}) => {
        const callerKey = socket.data.userKey;
        if (!callerKey) {
            socket.emit('pf-call-error', { message: 'Caller is not registered.' });
            return;
        }

        const toUserId = payload.toUserId || payload.receiverId;
        const toUserType = payload.toUserType;
        if (!toUserId || !toUserType) {
            socket.emit('pf-call-error', { message: 'Missing call target.' });
            return;
        }

        if (getActiveCallByUser(callerKey)) {
            socket.emit('pf-call-busy', { message: 'You are already in another call.' });
            return;
        }

        const calleeKey = makeUserKey(toUserId, toUserType);
        if (getActiveCallByUser(calleeKey)) {
            socket.emit('pf-call-busy', { message: 'User is currently on another call.' });
            return;
        }

        const callId = `${callerKey}->${calleeKey}:${Date.now()}`;
        const call = {
            id: callId,
            callerKey,
            calleeKey,
            callerSocketId: socket.id,
            callType: payload.callType || payload.type || 'voice',
            orderId: payload.orderId || null,
            startedAt: Date.now()
        };

        storeActiveCall(callId, call);

        const caller = activeUsers.get(callerKey) || {};
        const didRelay = relayToUser(calleeKey, 'incomingCall', {
            fromUserId: socket.data.userId,
            fromUserType: socket.data.userType,
            fromName: payload.fromName || caller.name || 'Unknown',
            fromAvatar: payload.fromAvatar || caller.avatar || '',
            callType: call.callType,
            orderId: call.orderId
        });

        if (!didRelay) {
            clearActiveCall(callId);
            socket.emit('pf-call-error', { message: 'User is offline or unavailable.' });
        }
    });

    socket.on('pf-accept-call', (payload = {}) => {
        const callerKey = makeUserKey(payload.toUserId, payload.toUserType);
        const currentKey = socket.data.userKey;
        if (!currentKey) {
            return;
        }

        const call = getActiveCallByUser(currentKey) || getActiveCallByUser(callerKey);
        if (!call) {
            socket.emit('pf-call-error', { message: 'Call session was not found.' });
            return;
        }

        relayToUser(call.callerKey, 'pf-call-accepted', {});
    });

    socket.on('pf-reject-call', (payload = {}) => {
        const targetKey = makeUserKey(payload.toUserId, payload.toUserType);
        const currentKey = socket.data.userKey;
        const call = getActiveCallByUser(currentKey) || getActiveCallByUser(targetKey);

        if (call) {
            clearActiveCall(call.id);
            relayToUser(call.callerKey === currentKey ? call.calleeKey : call.callerKey, 'pf-call-rejected', {});
        }
    });

    socket.on('pf-end-call', (payload = {}) => {
        const targetKey = makeUserKey(payload.toUserId, payload.toUserType);
        const currentKey = socket.data.userKey;
        const call = getActiveCallByUser(currentKey) || getActiveCallByUser(targetKey);

        if (call) {
            clearActiveCall(call.id);
            relayToUser(call.callerKey === currentKey ? call.calleeKey : call.callerKey, 'pf-call-ended', {});
        }
    });

    socket.on('pf-webrtc-offer', (payload = {}) => {
        relayToUser(makeUserKey(payload.toUserId, payload.toUserType), 'pf-webrtc-offer', {
            offer: payload.offer
        });
    });

    socket.on('pf-webrtc-answer', (payload = {}) => {
        relayToUser(makeUserKey(payload.toUserId, payload.toUserType), 'pf-webrtc-answer', {
            answer: payload.answer
        });
    });

    socket.on('pf-ice-candidate', (payload = {}) => {
        relayToUser(makeUserKey(payload.toUserId, payload.toUserType), 'pf-ice-candidate', {
            candidate: payload.candidate
        });
    });

    socket.on('disconnect', () => {
        const { userKey, userId, userType } = socket.data;
        if (userKey) {
            const existing = activeUsers.get(userKey);
            if (existing && existing.socketId === socket.id) {
                activeUsers.delete(userKey);
                broadcastUserStatus(userId, userType, false);
            }
        }

        const call = userKey ? getActiveCallByUser(userKey) : null;
        if (call) {
            clearActiveCall(call.id);
            const otherKey = call.callerKey === userKey ? call.calleeKey : call.callerKey;
            relayToUser(otherKey, 'pf-call-ended', {});
        }
    });
});

server.listen(PORT, HOST, () => {
    console.log(`PrintFlow signaling server running on port ${PORT}`);
});
