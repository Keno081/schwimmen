'use strict';

const isMultiplayer = document.body.dataset.multiplayer === '1';
const isCurrentComputer = document.body.dataset.currentComputer === '1';
const gameCode = document.body.dataset.gameCode;

if (isMultiplayer && gameCode) {
    let reconnectDelay = 1000;

    function connect() {
        const protocol = location.protocol === 'https:' ? 'wss' : 'ws';
        const ws = new WebSocket(`${protocol}://${location.host}/ws?spiel=${encodeURIComponent(gameCode)}`);

        ws.addEventListener('open', () => {
            reconnectDelay = 1000;
        });

        ws.addEventListener('message', () => {
            window.location.reload();
        });

        ws.addEventListener('close', () => {
            window.setTimeout(connect, reconnectDelay);
            reconnectDelay = Math.min(reconnectDelay * 2, 10000);
        });

        ws.addEventListener('error', () => {
            ws.close();
        });
    }

    connect();
}

if (isCurrentComputer) {
    window.setTimeout(() => {
        const computerButton = document.querySelector('button[name="action"][value="computer"]');
        if (computerButton) {
            const form = computerButton.closest('form');
            if (form.requestSubmit) {
                form.requestSubmit(computerButton);
            } else {
                form.submit();
            }
        }
    }, 700);
}
