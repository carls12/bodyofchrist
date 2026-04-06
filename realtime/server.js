const http = require('http');
const WebSocket = require('ws');

const PORT = process.env.RTC_PORT ? Number(process.env.RTC_PORT) : 3001;
const server = http.createServer((req, res) => {
  res.writeHead(200, { 'Content-Type': 'text/plain' });
  res.end('ok');
});

const wss = new WebSocket.Server({ server });
const clients = new Map(); // ws -> { userId, name, rooms:Set }
const rooms = new Map();   // room -> Set<ws>

function wsSend(ws, msg) {
  if (ws.readyState !== WebSocket.OPEN) return;
  ws.send(JSON.stringify(msg));
}

function joinRoom(ws, room) {
  if (!rooms.has(room)) rooms.set(room, new Set());
  rooms.get(room).add(ws);
  clients.get(ws).rooms.add(room);
}

function leaveRoom(ws, room) {
  if (!rooms.has(room)) return;
  rooms.get(room).delete(ws);
  if (rooms.get(room).size === 0) rooms.delete(room);
  clients.get(ws).rooms.delete(room);
}

function broadcastRoom(room, msg, excludeWs) {
  const set = rooms.get(room);
  if (!set) return;
  set.forEach((ws) => {
    if (excludeWs && ws === excludeWs) return;
    wsSend(ws, msg);
  });
}

function findByUserId(userId) {
  for (const [ws, info] of clients.entries()) {
    if (info.userId === userId) return ws;
  }
  return null;
}

wss.on('connection', (ws) => {
  clients.set(ws, { userId: null, name: '', rooms: new Set() });

  ws.on('message', (data) => {
    let msg = null;
    try { msg = JSON.parse(data.toString()); } catch (e) { return; }
    const info = clients.get(ws);
    if (!msg || !info) return;

    if (msg.type === 'hello') {
      info.userId = Number(msg.userId) || null;
      info.name = msg.name || '';
      return;
    }

    if (msg.type === 'join' && msg.room) {
      joinRoom(ws, msg.room);
      const peers = [];
      (rooms.get(msg.room) || new Set()).forEach((peer) => {
        if (peer === ws) return;
        const pi = clients.get(peer);
        if (!pi || !pi.userId) return;
        peers.push({ userId: pi.userId, name: pi.name });
      });
      wsSend(ws, { type: 'peers', room: msg.room, peers });
      if (info.userId) {
        broadcastRoom(msg.room, { type: 'peer_joined', room: msg.room, userId: info.userId, name: info.name }, ws);
      }
      return;
    }

    if (msg.type === 'leave' && msg.room) {
      leaveRoom(ws, msg.room);
      if (info.userId) {
        broadcastRoom(msg.room, { type: 'peer_left', room: msg.room, userId: info.userId }, ws);
      }
      return;
    }

    if (msg.type === 'signal' && msg.room && msg.data) {
      const payload = { type: 'signal', room: msg.room, from: info.userId, data: msg.data };
      if (msg.to) {
        const target = findByUserId(Number(msg.to));
        if (target) wsSend(target, payload);
      } else {
        broadcastRoom(msg.room, payload, ws);
      }
      return;
    }

    if (msg.type === 'call_invite') {
      const payload = {
        type: 'call_invite',
        room: msg.room,
        kind: msg.kind || 'video',
        from: info.userId,
        fromName: msg.fromName || info.name,
        groupId: msg.groupId || null
      };
      if (msg.to) {
        const target = findByUserId(Number(msg.to));
        if (target) wsSend(target, payload);
      } else if (msg.groupId) {
        broadcastRoom('presence:grp-' + msg.groupId, payload, ws);
      }
      return;
    }

    if (msg.type === 'call_cancel') {
      const payload = { type: 'call_cancel', room: msg.room, from: info.userId };
      if (msg.to) {
        const target = findByUserId(Number(msg.to));
        if (target) wsSend(target, payload);
      } else if (msg.room) {
        broadcastRoom(msg.room, payload, ws);
      }
      return;
    }

    if (msg.type === 'call_end' && msg.room) {
      broadcastRoom(msg.room, { type: 'call_end', room: msg.room, from: info.userId }, ws);
      return;
    }
  });

  ws.on('close', () => {
    const info = clients.get(ws);
    if (info) {
      info.rooms.forEach((room) => {
        leaveRoom(ws, room);
        if (info.userId) {
          broadcastRoom(room, { type: 'peer_left', room: room, userId: info.userId }, ws);
        }
      });
    }
    clients.delete(ws);
  });
});

server.listen(PORT, () => {
  console.log(`RTC signaling server listening on ${PORT}`);
});
