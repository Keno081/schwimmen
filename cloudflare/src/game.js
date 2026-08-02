// Portierte Spiellogik von schwimmen-klassen.php / schwimmen-logik.php
// Spielzustand wird als reines JSON-Objekt gehalten, damit er in Cloudflare KV
// gespeichert werden kann.

const SUITS = {
    Kreuz: '♣',
    Pik: '♠',
    Herz: '♥',
    Karo: '♦',
};

const RANKS = {
    '7': 7,
    '8': 8,
    '9': 9,
    '10': 10,
    Bube: 10,
    Dame: 10,
    König: 10,
    Ass: 11,
};

const UNICODE_FRZ = {
    Kreuz: { 7: 127191, 8: 127192, 9: 127193, 10: 127194, Bube: 127195, Dame: 127197, König: 127198, Ass: 127185 },
    Pik: { 7: 127143, 8: 127144, 9: 127145, 10: 127146, Bube: 127147, Dame: 127149, König: 127150, Ass: 127137 },
    Herz: { 7: 127159, 8: 127160, 9: 127161, 10: 127162, Bube: 127163, Dame: 127165, König: 127166, Ass: 127153 },
    Karo: { 7: 127175, 8: 127176, 9: 127177, 10: 127178, Bube: 127179, Dame: 127181, König: 127182, Ass: 127169 },
};

function getUnicodeCard(suit, rank) {
    const code = UNICODE_FRZ[suit]?.[rank];
    return code ? String.fromCodePoint(code) : null;
}

export function createDeck() {
    const deck = [];
    let index = 1;
    for (const [suit, sign] of Object.entries(SUITS)) {
        for (const [rank, value] of Object.entries(RANKS)) {
            deck.push({
                index: index++,
                suit,
                sign,
                rank,
                value,
                unicode: getUnicodeCard(suit, rank),
            });
        }
    }
    shuffle(deck);
    return deck;
}

export function shuffle(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

export function shortName(card) {
    let rank = card.rank;
    if (rank === 'Bube') rank = 'B';
    else if (rank === 'Dame') rank = 'D';
    else if (rank === 'König') rank = 'K';
    else if (rank === 'Ass') rank = 'A';
    return rank + card.sign;
}

export function cardHtml(card) {
    const cssColor = card.sign === '♦' || card.sign === '♥' ? 'red' : 'black';
    return `<span class="card ${cssColor}"><span class="unicode">${card.unicode ?? ''}</span><span>${shortName(card)}</span></span>`;
}

export function handHtml(cards) {
    return cards.map(cardHtml).join(' ');
}

function findPlayer(game, number) {
    return game.players.find((p) => p.number === number) || null;
}

export function getCurrentPlayer(game) {
    return findPlayer(game, game.currentPlayer);
}

export function scoreHand(cards) {
    if (cards.length !== 3) return 0;

    if (cards.every((c) => c.rank === 'Ass')) return 32;
    if (cards[0].rank === cards[1].rank && cards[1].rank === cards[2].rank) return 30.5;

    const scores = {};
    for (const card of cards) {
        scores[card.suit] = (scores[card.suit] || 0) + card.value;
    }
    return Math.max(...Object.values(scores));
}

export function scoreText(score) {
    return score === 30.5 ? '30,5' : String(score);
}

export function getBestScore(game) {
    let best = 0;
    for (const player of game.players) {
        const score = scoreHand(player.hand);
        if (score > best) best = score;
    }
    return best;
}

export function isWinner(game, player) {
    return game.status === 2 && scoreHand(player.hand) === getBestScore(game);
}

export function getWinnerNames(game) {
    return game.players.filter((p) => isWinner(game, p)).map((p) => p.name).join(', ');
}

export function canRenewMiddleSevenEightNine(game) {
    const ranks = game.middle.map((c) => c.rank);
    return ranks.length === 3 && ranks.includes('7') && ranks.includes('8') && ranks.includes('9');
}

export function createGame() {
    return {
        status: 0,
        message: '',
        deck: [],
        discardpile: [],
        middle: [],
        players: [],
        currentPlayer: 1,
        knockedBy: null,
        lastPlayerAfterKnock: null,
    };
}

export const MAX_PLAYERS = 6;

export function startGame(game, humanPlayersInput, totalPlayersInput = 4) {
    game.status = 1;
    game.message = 'Neues Spiel gestartet. Spieler 1 ist am Zug.';
    game.players = [];
    game.deck = createDeck();
    game.discardpile = [];
    game.middle = [];
    game.knockedBy = null;
    game.lastPlayerAfterKnock = null;
    game.currentPlayer = 1;

    const totalPlayers = Math.max(2, Math.min(MAX_PLAYERS, totalPlayersInput));
    const humanPlayers = Math.max(1, Math.min(totalPlayers, humanPlayersInput));
    for (let number = 1; number <= totalPlayers; number++) {
        if (humanPlayers === 1 && number === 1) {
            game.players.push({ number, name: 'Du', hand: [], computer: false, passed: false });
        } else if (number <= humanPlayers) {
            game.players.push({ number, name: `Spieler ${number}`, hand: [], computer: false, passed: false });
        } else {
            game.players.push({ number, name: `Computer ${number - humanPlayers}`, hand: [], computer: true, passed: false });
        }
    }

    for (let round = 0; round < 3; round++) {
        for (const player of game.players) {
            player.hand.push(game.deck.pop());
        }
    }

    for (let i = 0; i < 3; i++) {
        game.middle.push(game.deck.pop());
    }

    finishIfAnyPlayerHasThirtyTwo(game);
    return game;
}

function resetPasses(game) {
    for (const player of game.players) player.passed = false;
}

function nextPlayer(game) {
    const exists = (n) => game.players.some((p) => p.number === n);
    if (exists(game.currentPlayer + 1)) {
        game.currentPlayer = game.currentPlayer + 1;
    } else {
        game.currentPlayer = 1;
    }
}

function finishIfAnyPlayerHasThirtyTwo(game) {
    for (const player of game.players) {
        if (scoreHand(player.hand) === 32) {
            finishGame(game, `${player.name} hat 32 Punkte auf der Hand.`);
            return true;
        }
    }
    return false;
}

function finishGame(game, reason) {
    let bestPlayer = null;
    let bestScore = -1;
    for (const player of game.players) {
        const score = scoreHand(player.hand);
        if (score > bestScore) {
            bestScore = score;
            bestPlayer = player;
        }
    }
    game.status = 2;
    game.message = `${reason} Gewinner: ${bestPlayer.name} mit ${scoreText(bestScore)} Punkten.`;
}

function renewMiddleCards(game, emptyDeckMessage) {
    if (game.deck.length < 3) {
        finishGame(game, emptyDeckMessage);
        return false;
    }
    for (const card of game.middle) game.discardpile.push(card);
    game.middle = [];
    for (let i = 0; i < 3; i++) game.middle.push(game.deck.pop());
    return true;
}

function renewMiddleAfterPasses(game) {
    if (!renewMiddleCards(game, 'Alle Spieler haben geschoben und es sind nicht genug Karten im Stapel.')) {
        return;
    }
    resetPasses(game);
    nextPlayer(game);
    game.message = 'Alle Spieler haben geschoben. Es liegen drei neue Karten in der Mitte.';
}

function nextSchwimmenPlayer(game) {
    if (game.knockedBy !== null && game.currentPlayer === game.lastPlayerAfterKnock) {
        finishGame(game, 'Die Runde nach dem Klopfen ist vorbei.');
        return;
    }

    for (let i = 0; i < game.players.length; i++) {
        nextPlayer(game);
        if (!getCurrentPlayer(game).passed) return;
    }

    renewMiddleAfterPasses(game);
}

function finishHumanTurn(game, playerNumber, message) {
    if (finishIfAnyPlayerHasThirtyTwo(game)) return;

    const player = findPlayer(game, playerNumber);
    const score = scoreHand(player.hand);
    if (score >= 31) {
        finishGame(game, `${player.name} hat ${scoreText(score)} Punkte erreicht.`);
        return;
    }

    game.message = message;
    nextSchwimmenPlayer(game);
    if (game.status === 1) {
        game.message += ` Jetzt ist ${getCurrentPlayer(game).name} am Zug.`;
    }
}

export function swapOne(game, playerNumber, handIndex, middleIndex) {
    if (game.currentPlayer !== playerNumber) {
        game.message = 'Du bist gerade nicht am Zug.';
        return;
    }
    const player = findPlayer(game, playerNumber);
    if (!player || player.computer) {
        game.message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
        return;
    }
    const handCard = player.hand[handIndex];
    const middleCard = game.middle[middleIndex];
    if (!handCard || !middleCard) {
        game.message = 'Bitte waehle eine Handkarte und eine Karte aus der Mitte.';
        return;
    }
    player.hand[handIndex] = middleCard;
    game.middle[middleIndex] = handCard;
    resetPasses(game);
    finishHumanTurn(game, playerNumber, `${player.name} hat ${shortName(handCard)} gegen ${shortName(middleCard)} getauscht.`);
}

export function swapAll(game, playerNumber) {
    if (game.currentPlayer !== playerNumber) {
        game.message = 'Du bist gerade nicht am Zug.';
        return;
    }
    const player = findPlayer(game, playerNumber);
    if (!player || player.computer) {
        game.message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
        return;
    }
    const oldHand = player.hand;
    const oldMiddle = game.middle;
    player.hand = oldMiddle;
    game.middle = oldHand;
    resetPasses(game);
    finishHumanTurn(game, playerNumber, `${player.name} hat alle drei Karten getauscht.`);
}

export function pass(game, playerNumber) {
    if (game.currentPlayer !== playerNumber) {
        game.message = 'Du bist gerade nicht am Zug.';
        return;
    }
    const player = findPlayer(game, playerNumber);
    if (!player || player.computer) {
        game.message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
        return;
    }
    player.passed = true;
    finishHumanTurn(game, playerNumber, `${player.name} hat geschoben.`);
}

export function knock(game, playerNumber) {
    if (game.currentPlayer !== playerNumber || game.knockedBy !== null) {
        game.message = 'Klopfen ist gerade nicht moeglich.';
        return;
    }
    const player = findPlayer(game, playerNumber);
    if (!player || player.computer) {
        game.message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
        return;
    }
    game.knockedBy = playerNumber;
    game.lastPlayerAfterKnock = playerNumber === 1 ? game.players.length : playerNumber - 1;
    finishHumanTurn(game, playerNumber, `${player.name} hat geklopft. Jeder andere Spieler hat noch einen Zug.`);
}

export function renewMiddleSevenEightNine(game, playerNumber) {
    if (game.currentPlayer !== playerNumber) {
        game.message = 'Du bist gerade nicht am Zug.';
        return;
    }
    const player = findPlayer(game, playerNumber);
    if (!player || player.computer) {
        game.message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
        return;
    }
    if (!canRenewMiddleSevenEightNine(game)) {
        game.message = 'Neue Karten sind nur moeglich, wenn in der Mitte 7, 8 und 9 liegen, zum Beispiel 789, 879 oder 978.';
        return;
    }
    if (!renewMiddleCards(game, 'Es sind nicht genug Karten im Stapel.')) return;
    resetPasses(game);
    finishHumanTurn(game, playerNumber, `In der Mitte lagen 7, 8 und 9, egal in welcher Reihenfolge. ${player.name} hat drei neue Karten aufgedeckt.`);
}

function scoreCards(cards) {
    return scoreHand(cards);
}

export function computerTurn(game) {
    if (game.status !== 1 || !getCurrentPlayer(game).computer) return;
    if (finishIfAnyPlayerHasThirtyTwo(game)) return;

    const player = findPlayer(game, game.currentPlayer);
    const currentScore = scoreHand(player.hand);
    let bestScore = currentScore;
    let bestHand = player.hand;
    let bestMiddle = game.middle;
    let action = 'schiebt.';

    const middleScore = scoreCards(game.middle);
    if (middleScore > bestScore) {
        bestScore = middleScore;
        bestHand = game.middle;
        bestMiddle = player.hand;
        action = 'tauscht alle Karten.';
    }

    const handCards = player.hand;
    const middleCards = game.middle;
    for (let h = 0; h < 3; h++) {
        for (let m = 0; m < 3; m++) {
            const newHand = handCards.slice();
            const newMiddle = middleCards.slice();
            const temp = newHand[h];
            newHand[h] = newMiddle[m];
            newMiddle[m] = temp;
            const score = scoreCards(newHand);
            if (score > bestScore) {
                bestScore = score;
                bestHand = newHand;
                bestMiddle = newMiddle;
                action = 'tauscht eine Karte.';
            }
        }
    }

    if (bestScore > currentScore) {
        player.hand = bestHand;
        game.middle = bestMiddle;
        resetPasses(game);

        if (finishIfAnyPlayerHasThirtyTwo(game)) return;

        if (bestScore >= 31) {
            finishGame(game, `${player.name} hat ${scoreText(bestScore)} Punkte erreicht.`);
            return;
        }
    } else {
        if (currentScore >= 31) {
            finishGame(game, `${player.name} hat ${scoreText(currentScore)} Punkte erreicht.`);
            return;
        }

        if (currentScore >= 29 && game.knockedBy === null) {
            game.knockedBy = player.number;
            game.lastPlayerAfterKnock = player.number === 1 ? game.players.length : player.number - 1;
            action = 'klopft.';
        } else if (canRenewMiddleSevenEightNine(game)) {
            if (!renewMiddleCards(game, `${player.name} wollte neue Karten aufdecken, aber es sind nicht genug Karten im Stapel.`)) {
                return;
            }
            resetPasses(game);
            action = 'deckt neue Karten in der Mitte auf.';
        } else {
            player.passed = true;
        }
    }

    game.message = `${player.name} ${action}`;
    nextSchwimmenPlayer(game);
}
