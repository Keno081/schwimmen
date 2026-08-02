// Durable Object: haelt die WebSocket-Verbindungen fuer ein Mehrspieler-Spiel
// und benachrichtigt alle verbundenen Clients, wenn sich der Spielstand aendert.
export class GameRoom {
    constructor(state) {
        this.state = state;
        this.sessions = new Set();
    }

    async fetch(request) {
        const url = new URL(request.url);

        if (request.headers.get('Upgrade') === 'websocket') {
            const pair = new WebSocketPair();
            const [client, server] = Object.values(pair);
            server.accept();
            this.sessions.add(server);
            const cleanup = () => this.sessions.delete(server);
            server.addEventListener('close', cleanup);
            server.addEventListener('error', cleanup);
            return new Response(null, { status: 101, webSocket: client });
        }

        if (url.pathname === '/broadcast' && request.method === 'POST') {
            for (const ws of this.sessions) {
                try {
                    ws.send('reload');
                } catch {
                    this.sessions.delete(ws);
                }
            }
            return new Response('ok');
        }

        return new Response('not found', { status: 404 });
    }
}
