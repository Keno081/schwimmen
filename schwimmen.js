'use strict';

const isMultiplayer = document.body.dataset.multiplayer === '1';
const isMyTurn = document.body.dataset.myTurn === '1';

if (isMultiplayer && !isMyTurn) {
    window.setTimeout(() => {
        window.location.reload();
    }, 4000);
}
