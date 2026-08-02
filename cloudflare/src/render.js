import { cardHtml, handHtml, getBestScore, scoreHand, scoreText, isWinner, canRenewMiddleSevenEightNine, MAX_PLAYERS } from './game.js';

function esc(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function findPlayer(game, number) {
    return game.players.find((p) => p.number === number) || null;
}

function playerCountOptions(min, max, selected) {
    let html = '';
    for (let n = min; n <= max; n++) {
        html += `<option value="${n}" ${n === selected ? 'selected' : ''}>${n}</option>`;
    }
    return html;
}

function renderTitleScreen({ joinError, showRules }) {
    return `
        <section class="title-screen">
            <div class="title-box">
                <div class="title-copy">
                    <div class="title-kicker">31 Punkte &middot; Echtzeit &middot; bis zu ${MAX_PLAYERS} Spieler</div>
                    <h1>Schwimmen</h1>
                    <p>Starte alleine gegen Computer oder eine private Mehrspieler-Runde mit Spielcode.</p>
                    <form method="post" class="title-actions">
                        <div class="player-count-picker">
                            <label>Mitspieler
                                <select name="human_players">${playerCountOptions(2, MAX_PLAYERS, 2)}</select>
                            </label>
                            <label>Computer
                                <select name="bot_count">${playerCountOptions(0, MAX_PLAYERS - 2, 2)}</select>
                            </label>
                        </div>
                        <button type="submit" name="create_multiplayer" value="1">Mehrspieler-Spiel erstellen</button>
                        <button type="submit" class="secondary" name="start_game" value="1">Allein spielen</button>
                        <button type="submit" class="secondary" name="show_rules" value="1">Regeln</button>
                    </form>
                </div>
                <div class="title-lobby">
                    <div class="title-cards" aria-hidden="true">
                        <span class="red">&#127153;</span>
                        <span class="black">&#127185;</span>
                        <span class="red">&#127169;</span>
                    </div>
                    <div class="join-card">
                        <h2>Spiel beitreten</h2>
                        <form method="post" class="join-game">
                            <input type="text" name="game_code" placeholder="CODE" maxlength="6">
                            <button type="submit" name="join_multiplayer" value="1">Beitreten</button>
                        </form>
                        ${joinError ? `<div class="form-error">${esc(joinError)}</div>` : ''}
                    </div>
                </div>
                ${showRules ? `
                <div class="rules-box">
                    <h2>Regeln</h2>
                    <ol>
                        <li>Jeder Spieler hat 3 Karten.</li>
                        <li>Du kannst eine Karte mit der Mitte tauschen, alle Karten tauschen oder schieben.</li>
                        <li>Punkte zaehlen nur Karten derselben Farbe zusammen.</li>
                        <li>Ass zaehlt 11 Punkte, Bildkarten und 10 zaehlen 10 Punkte.</li>
                        <li>Drei gleiche Karten zaehlen 30,5 Punkte.</li>
                        <li>Drei Asse zaehlen 32 Punkte.</li>
                        <li>Wenn ein Spieler 32 Punkte auf der Hand hat, endet das Spiel sofort.</li>
                        <li>Wer 31 Punkte erreicht oder am Ende die meisten Punkte hat, gewinnt.</li>
                        <li>Beim Klopfen hat jeder andere Spieler noch einen Zug.</li>
                        <li>Ein Spieler darf in seinem Zug entweder Karten tauschen oder klopfen.</li>
                        <li>Wenn alle Spieler schieben, kommen drei neue Karten in die Mitte.</li>
                        <li>Wenn in der Mitte 7, 8 und 9 liegen, zum Beispiel 789, 879 oder 978, darfst du neue Karten in die Mitte legen lassen.</li>
                    </ol>
                </div>` : ''}
            </div>
        </section>`;
}

function renderGameScreen(ctx) {
    const { game, isMultiplayer, gameCode, currentHumanPlayer, shareUrl, isMyTurn } = ctx;

    const multiplayerPanel = isMultiplayer ? `
        <section class="panel multiplayer-panel">
            <h2>Mehrspieler-Spiel</h2>
            <div class="table-info">
                <span class="badge">Code: ${esc(gameCode)}</span>
                <span class="badge">Du: ${esc(currentHumanPlayer ? currentHumanPlayer.name : 'Zuschauer')}</span>
            </div>
            <input type="text" value="${esc(shareUrl)}" readonly>
        </section>` : '';

    const resultSection = game.status === 2 ? `
        <section class="result-hero">
            <div class="result-title">
                <div><h2>Spiel beendet</h2></div>
                <div class="result-meta">
                    <span class="badge gold">Beste Punktzahl: ${esc(scoreText(getBestScore(game)))}</span>
                    <span class="badge">Karten im Stapel: ${game.deck.length}</span>
                    <span class="badge">Ablage: ${game.discardpile.length}</span>
                </div>
            </div>
            <form method="post" class="actions">
                <button type="submit" name="new_game" value="1">Neue Runde</button>
                <button type="submit" class="secondary" name="title_screen" value="1">Zum Titel</button>
            </form>
        </section>` : '';

    let playSection = '';
    if (game.status === 1) {
        const currentPlayer = findPlayer(game, game.currentPlayer);
        const handCardsHtml = currentHumanPlayer
            ? currentHumanPlayer.hand.map((card, index) => `
                <label class="choice">
                    <input type="radio" name="hand_index" value="${index}" ${index === 0 ? 'checked' : ''}>
                    ${cardHtml(card)}
                </label>`).join('')
            : '';

        const middleCardsHtml = game.middle.map((card, index) => `
            <label class="choice">
                <input type="radio" name="middle_index" value="${index}" ${index === 0 ? 'checked' : ''}>
                ${cardHtml(card)}
            </label>`).join('');

        const actionsHtml = isMyTurn ? `
            <div class="actions">
                <button type="submit" name="action" value="swap_one">Ausgewaehlte Karten tauschen</button>
                <button type="submit" name="action" value="swap_all">Alle tauschen</button>
                <button type="submit" class="secondary" name="action" value="pass">Schieben</button>
                <button type="submit" class="secondary" name="action" value="knock">Klopfen</button>
                ${canRenewMiddleSevenEightNine(game) ? '<button type="submit" class="secondary" name="action" value="renew_middle">Neue Mitte</button>' : ''}
            </div>` : '';

        const waitingLine = currentPlayer.computer
            ? `<form method="post" class="actions"><button type="submit" name="action" value="computer">${esc(currentPlayer.name)} ausfuehren</button></form>`
            : (!isMyTurn ? `<div class="score">Warte auf ${esc(currentPlayer.name)}.</div>` : '');

        playSection = `
        <section class="panel">
            <h2>Deine Hand</h2>
            ${currentHumanPlayer ? `<div class="score">Punkte: ${esc(scoreText(scoreHand(currentHumanPlayer.hand)))}</div>` : ''}
            <div class="table-info">
                <span class="badge">Am Zug: ${esc(currentPlayer.name)}</span>
                <span class="badge">Stapel: ${game.deck.length}</span>
                <span class="badge">Ablage: ${game.discardpile.length}</span>
            </div>
            <form method="post">
                ${currentHumanPlayer ? `<div class="cards">${handCardsHtml}</div>` : ''}
                <h2>Mitte</h2>
                <div class="cards">${middleCardsHtml}</div>
                ${actionsHtml}
            </form>
            ${waitingLine}
        </section>`;
    }

    const gridHtml = game.players.map((player) => {
        const canSeeCards = game.status === 2 || (currentHumanPlayer && player.number === currentHumanPlayer.number);
        let statusText = 'wartet';
        if (game.status === 2) statusText = 'beendet';
        else if (game.currentPlayer === player.number) statusText = 'am Zug';
        else if (player.passed) statusText = 'geschoben';

        return `
            <div class="panel ${isWinner(game, player) ? 'winner' : ''}">
                <div class="player-head">
                    <h2>${esc(player.name)}</h2>
                    ${isWinner(game, player) ? '<span class="badge gold">Gewonnen</span>' : ''}
                </div>
                <div class="score">Punkte: ${canSeeCards ? esc(scoreText(scoreHand(player.hand))) : 'verdeckt'}</div>
                <div>Status: ${statusText}</div>
                ${canSeeCards ? `<div class="cards">${handHtml(player.hand)}</div>` : ''}
            </div>`;
    }).join('');

    return `
    <div class="top">
        <div>
            <h1>Schwimmen</h1>
            <div>${game.players.length} Spieler, 3 Karten, Ziel: 31 Punkte.</div>
            ${isMultiplayer ? `<div>Spielcode: <strong>${esc(gameCode)}</strong> &middot; Du bist ${esc(currentHumanPlayer ? currentHumanPlayer.name : 'Zuschauer')}</div>` : ''}
        </div>
        <form method="post">
            <button type="submit" class="secondary" name="title_screen" value="1">Titel</button>
            <button type="submit" class="secondary" name="new_game" value="1">Neues Spiel</button>
        </form>
    </div>
    <div class="message ${game.status === 2 ? 'final' : ''}">${esc(game.message)}</div>
    ${multiplayerPanel}
    ${resultSection}
    ${playSection}
    <section class="grid">${gridHtml}</section>`;
}

export function renderPage(ctx) {
    const { game, isMultiplayer, isMyTurn, gameCode } = ctx;
    const body = game.status === 0 ? renderTitleScreen(ctx) : renderGameScreen(ctx);

    const currentPlayer = game.status === 1 ? findPlayer(game, game.currentPlayer) : null;
    const isCurrentComputer = Boolean(currentPlayer && currentPlayer.computer);

    return `<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schwimmen</title>
    <link rel="stylesheet" href="/schwimmen.css">
</head>
<body data-multiplayer="${isMultiplayer ? '1' : '0'}" data-my-turn="${isMyTurn ? '1' : '0'}" data-game-code="${esc(gameCode || '')}" data-current-computer="${isCurrentComputer ? '1' : '0'}">
<main>
${body}
</main>
    <script src="/schwimmen.js"></script>
</body>
</html>`;
}
