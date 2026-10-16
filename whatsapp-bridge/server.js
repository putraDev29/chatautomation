import makeWASocket, { DisconnectReason, useMultiFileAuthState } from '@whiskeysockets/baileys';
import qrcodeTerminal from 'qrcode-terminal';
import QRCode from 'qrcode';
import pino from 'pino';
import axios from 'axios';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const LARAVEL_MESSAGE_URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000/api/whatsapp/bridge-message';
const LARAVEL_STATUS_URL = 'http://127.0.0.1:8000/api/whatsapp/bridge-status';
const LARAVEL_ADMIN_REPLY_URL = 'http://127.0.0.1:8000/api/whatsapp/bridge-admin-message';
const LARAVEL_DELETE_URL = 'http://127.0.0.1:8000/api/whatsapp/bridge-delete';
const LARAVEL_ADMINS_URL = 'http://127.0.0.1:8000/api/whatsapp/bridge-admins';

console.log('====================================================');
console.log(' BINTANG MOBIL MULTI-SESSION WHATSAPP GATEWAY');
console.log(' Mendukung Banyak Barcode untuk Masing-Masing Admin');
console.log('====================================================\n');

// Active sessions map: sessionId -> { sock, status, qr, phone, name }
const activeSessions = new Map();

async function syncStatusToLaravel(payload) {
    try {
        await axios.post(LARAVEL_STATUS_URL, payload);
    } catch (err) {
        // Silent if Laravel is booting
    }
}

function extractMessageBody(msg) {
    if (!msg.message) return '';
    const m = msg.message;

    // Check direct fields
    if (m.conversation) return m.conversation;
    if (m.extendedTextMessage?.text) return m.extendedTextMessage.text;
    if (m.imageMessage?.caption) return m.imageMessage.caption;
    if (m.videoMessage?.caption) return m.videoMessage.caption;
    if (m.documentMessage?.caption) return m.documentMessage.caption;

    // Check wrapped message types (ephemeral, viewOnce, etc.)
    const unwrapped = m.ephemeralMessage?.message ||
                      m.viewOnceMessage?.message ||
                      m.viewOnceMessageV2?.message ||
                      m.documentWithCaptionMessage?.message;

    if (unwrapped) {
        return unwrapped.conversation ||
               unwrapped.extendedTextMessage?.text ||
               unwrapped.imageMessage?.caption ||
               unwrapped.videoMessage?.caption ||
               '';
    }

    return '';
}

async function startSession(sessionId, adminMeta = {}) {
    if (activeSessions.has(sessionId)) {
        return activeSessions.get(sessionId);
    }

    const adminName = adminMeta.name || `Admin ${sessionId}`;
    console.log(`\n🚀 [INIT SESSION] Memulai sesi untuk: ${adminName} (${sessionId})...`);

    // Auth directory: admin_1 uses existing ./auth_info for zero downtime, others use ./auth_sessions/${sessionId}
    let authDir = path.join(__dirname, 'auth_sessions', sessionId);
    if (sessionId === 'admin_1') {
        authDir = path.join(__dirname, 'auth_info');
    }

    if (!fs.existsSync(authDir)) {
        fs.mkdirSync(authDir, { recursive: true });
    }

    const { state, saveCreds } = await useMultiFileAuthState(authDir);

    const sock = makeWASocket({
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
    });

    const sessionObj = {
        sessionId,
        adminMeta,
        sock,
        status: 'initializing',
        phone: null,
        name: adminName
    };
    activeSessions.set(sessionId, sessionObj);

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log(`\n📲 [BARCODE SIAP] QR Code untuk ${adminName} (${sessionId}):`);
            console.log(`   Scan dari Dashboard Web atau terminal di bawah:`);
            qrcodeTerminal.generate(qr, { small: true });

            try {
                const qrImageBase64 = await QRCode.toDataURL(qr, {
                    width: 300,
                    margin: 2,
                    color: {
                        dark: '#0f172a',
                        light: '#ffffff',
                    }
                });

                sessionObj.status = 'qr_ready';
                sessionObj.qr = qrImageBase64;

                await syncStatusToLaravel({
                    session_id: sessionId,
                    status: 'qr_ready',
                    qr_code: qrImageBase64,
                    phone: null,
                    name: adminName,
                    is_primary: sessionId === 'admin_1'
                });
                console.log(`✨ [SYNC] Barcode ${adminName} berhasil dikirim ke Web Dashboard!`);
            } catch (err) {
                console.error(`Gagal generate QR image ${sessionId}:`, err.message);
            }
        }

        if (connection === 'close') {
            const shouldReconnect = (lastDisconnect?.error)?.output?.statusCode !== DisconnectReason.loggedOut;
            console.log(`⚠️ [KONEKSI TERPUTUS] ${adminName} (${sessionId}). Reconnect:`, shouldReconnect);

            sessionObj.status = 'disconnected';
            sessionObj.phone = null;

            await syncStatusToLaravel({
                session_id: sessionId,
                status: 'disconnected',
                qr_code: null,
                phone: null,
                name: adminName,
                is_primary: sessionId === 'admin_1'
            });

            if (shouldReconnect) {
                activeSessions.delete(sessionId);
                startSession(sessionId, adminMeta);
            } else {
                console.log(`❌ Sesi ${adminName} (${sessionId}) telah di-logout. Silakan scan QR baru.`);
            }
        } else if (connection === 'open') {
            const userPhone = sock.user?.id ? sock.user.id.split(':')[0] : 'Nomor Terhubung';
            const userName = sock.user?.name || adminName;

            sessionObj.status = 'connected';
            sessionObj.phone = userPhone;
            sessionObj.name = userName;

            console.log(`\n✅ [TERHUBUNG] Sesi ${adminName} AKTIF!`);
            console.log(`   Nomor WA : ${userPhone}`);
            console.log(`   Status   : Web Dashboard TERHUBUNG (${sessionId})\n`);

            await syncStatusToLaravel({
                session_id: sessionId,
                status: 'connected',
                qr_code: null,
                phone: userPhone,
                name: userName,
                is_primary: sessionId === 'admin_1'
            });
        }
    });

    // Handle Incoming & Outgoing Messages for this session
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            const remoteJid = msg.key.remoteJid;
            if (!remoteJid || remoteJid.includes('@g.us') || remoteJid === 'status@broadcast') {
                continue;
            }

            const body = extractMessageBody(msg);
            if (!body || body.trim() === '') continue;

            const customerPhone = remoteJid.replace('@s.whatsapp.net', '').replace('@lid', '');
            const senderName = msg.pushName || `Customer ${customerPhone}`;

            // Case A: Message sent by Admin from this specific WhatsApp Business phone
            if (msg.key.fromMe) {
                console.log(`\n🧑‍💼 [PESAN DIKIRIM OLEH ${adminName.toUpperCase()} LANGSUNG DARI HP]:`);
                console.log(`   Ke Customer : ${customerPhone}`);
                console.log(`   Isi Pesan   : "${body}"`);

                try {
                    await axios.post(LARAVEL_ADMIN_REPLY_URL, {
                        session_id: sessionId,
                        phone_number: customerPhone,
                        message: body,
                        wa_message_id: msg.key.id,
                    });
                    console.log(`✅ [SYNC ADMIN] Pesan ${adminName} tersinkron ke Web Dashboard & Bot tetap aktif!\n`);
                } catch (err) {
                    console.error('Gagal sinkronkan pesan admin ke Laravel:', err.message);
                }
                continue;
            }

            // Case B: Inbound Message from Customer to this Admin's WhatsApp
            console.log(`\n📩 [PESAN MASUK KE ${adminName.toUpperCase()} (${sessionId})]:`);
            console.log(`   Dari     : ${senderName} (${customerPhone})`);
            console.log(`   Pesan    : "${body}"`);

            try {
                console.log(`⚙️ Meneruskan ke Bot Laravel (${sessionId})...`);
                const response = await axios.post(LARAVEL_MESSAGE_URL, {
                    session_id: sessionId,
                    phone_number: customerPhone,
                    customer_name: senderName,
                    message: body,
                    wa_message_id: msg.key.id,
                });

                const data = response.data;

                if (data.success && data.should_reply) {
                    console.log(`🤖 [BOT MEMBALAS ATAS NAMA ${adminName.toUpperCase()}]:`);
                    console.log(`   Jawaban: "${data.reply_text.split('\n')[0]}..."`);

                    // Send text reply
                    await sock.sendMessage(remoteJid, { text: data.reply_text });

                    // Send media attachment if requested by customer
                    if (data.media_url) {
                        try {
                            const cleanPath = data.media_url.replace(/^\//, '');
                            const localPath = path.join(__dirname, '..', 'public', cleanPath);

                            let mediaPayload = null;
                            if (fs.existsSync(localPath)) {
                                const fileBuffer = fs.readFileSync(localPath);
                                const isVideo = data.media_type === 'video' || localPath.endsWith('.mp4');
                                if (isVideo) {
                                    mediaPayload = {
                                        video: fileBuffer,
                                        mimetype: 'video/mp4',
                                        caption: 'Video unit Showroom Bintang Mobil'
                                    };
                                } else {
                                    mediaPayload = {
                                        image: fileBuffer,
                                        mimetype: 'image/jpeg',
                                        caption: 'Foto unit Showroom Bintang Mobil'
                                    };
                                }
                            } else {
                                const isVideo = data.media_type === 'video';
                                mediaPayload = isVideo
                                    ? { video: { url: data.media_url }, caption: 'Video unit Showroom Bintang Mobil' }
                                    : { image: { url: data.media_url }, caption: 'Foto unit Showroom Bintang Mobil' };
                            }

                            await sock.sendMessage(remoteJid, mediaPayload);
                            console.log(`📸 [MEDIA TERKIRIM KE ${customerPhone}]: ${data.media_url}`);
                        } catch (mediaErr) {
                            console.log(`⚠️ Info media: ${mediaErr.message}`);
                        }
                    }

                    console.log(`✅ Selesai: Balasan sukses terkirim ke customer ${customerPhone}!\n`);
                } else {
                    console.log(`⏸️ Bot tidak membalas: ${data.reason || 'Admin sedang menangani percakapan'}\n`);
                }
            } catch (err) {
                console.error(`❌ Gagal menghubungi Laravel:`, err.message);
            }
        }
    });

    // Handle message deletions
    sock.ev.on('messages.delete', async (item) => {
        try {
            if (item.all && item.jid) {
                const phone = item.jid.replace('@s.whatsapp.net', '').replace('@lid', '');
                await axios.post(LARAVEL_DELETE_URL, {
                    session_id: sessionId,
                    type: 'chat',
                    jid: item.jid,
                    phone_number: phone
                });
            } else if (item.keys && Array.isArray(item.keys)) {
                const messageIds = item.keys.map(k => k.id).filter(Boolean);
                if (messageIds.length > 0) {
                    await axios.post(LARAVEL_DELETE_URL, {
                        session_id: sessionId,
                        type: 'message',
                        ids: messageIds
                    });
                }
            }
        } catch (err) {
            console.error('Gagal sinkronkan delete pesan ke Laravel:', err.message);
        }
    });

    // Handle full chat deletions from WhatsApp Business
    sock.ev.on('chats.delete', async (chatJids) => {
        try {
            if (Array.isArray(chatJids)) {
                for (const jid of chatJids) {
                    const phone = jid.replace('@s.whatsapp.net', '').replace('@lid', '');
                    await axios.post(LARAVEL_DELETE_URL, {
                        session_id: sessionId,
                        type: 'chat',
                        jid: jid,
                        phone_number: phone
                    });
                }
            }
        } catch (err) {
            console.error('Gagal sinkronkan chats.delete ke Laravel:', err.message);
        }
    });

    return sessionObj;
}

// Periodic heartbeat every 5 seconds to keep Laravel status fresh for all connected sessions
setInterval(async () => {
    for (const [sessionId, sessionObj] of activeSessions.entries()) {
        if (sessionObj.sock?.user) {
            const userPhone = sessionObj.sock.user.id ? sessionObj.sock.user.id.split(':')[0] : sessionObj.phone;
            await syncStatusToLaravel({
                session_id: sessionId,
                status: 'connected',
                qr_code: null,
                phone: userPhone,
                name: sessionObj.name,
                is_primary: sessionId === 'admin_1'
            });
        }
    }
}, 5000);

// Fetch admin sessions from Laravel and start them
async function syncAndStartAdminSessions() {
    try {
        const response = await axios.get(LARAVEL_ADMINS_URL);
        const admins = response.data;

        if (Array.isArray(admins) && admins.length > 0) {
            for (const admin of admins) {
                if (!activeSessions.has(admin.session_id)) {
                    await startSession(admin.session_id, admin);
                }
            }
        } else {
            // Default fallback if no admins returned
            if (!activeSessions.has('admin_1')) {
                await startSession('admin_1', { name: 'Admin Showroom Utama' });
            }
        }
    } catch (err) {
        // Fallback to primary admin if Laravel is not ready yet
        if (!activeSessions.has('admin_1')) {
            await startSession('admin_1', { name: 'Admin Showroom Utama' });
        }
    }
}

// Start initial sessions and check for new admin sessions periodically
syncAndStartAdminSessions();
setInterval(syncAndStartAdminSessions, 30000);
