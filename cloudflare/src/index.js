import { renderPage } from './render.js';
import { createGame, startGame, swapOne, swapAll, pass, knock, renewMiddleSevenEightNine, computerTurn } from './game.js';
import { GameRoom } from './gameRoom.js';

export { GameRoom };

async function broadcastGameUpdate(env, code) {
    const id = env.GAME_ROOMS.idFromName(code);
    const stub = env.GAME_ROOMS.get(id);
    await stub.fetch('https://internal/broadcast', { method: 'POST' });
}

const COOKIE_SID = 'sid';
const COOKIE_SHOW_ONCE = 'show_once';

function parseCookies(request) {
    const header = request.headers.get('Cookie') || '';
    const cookies = {};
    for (const part of header.split(';')) {
        const [key, ...rest] = part.trim().split('=');
        if (!key) continue;
        cookies[key] = decodeURIComponent(rest.join('=') || '');
    }
    return cookies;
}

function normalizeGameCode(code) {
    return (code || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
}

function generateToken() {
    return crypto.randomUUID().replace(/-/g, '');
}

async function createGameCode(kv) {
    let code;
    do {
        code = crypto.randomUUID().replace(/-/g, '').slice(0, 6).toUpperCase();
    } while (await kv.get(`game:${code}`) !== null);
    return code;
}

async function loadSharedGame(kv, code) {
    return kv.get(`game:${code}`, 'json');
}

async function saveSharedGame(kv, code, game) {
    await kv.put(`game:${code}`, JSON.stringify(game));
}

async function loadClaims(kv, code) {
    return (await kv.get(`claims:${code}`, 'json')) || {};
}

async function saveClaims(kv, code, claims) {
    await kv.put(`claims:${code}`, JSON.stringify(claims));
}

function countHumanPlayers(game) {
    return game.players.filter((p) => !p.computer).length;
}

async function claimNextSharedSlot(kv, code, game) {
    const humanPlayers = countHumanPlayers(game);
    const claims = await loadClaims(kv, code);
    for (let number = 1; number <= humanPlayers; number++) {
        if (!claims[number]) {
            const token = generateToken();
            claims[number] = token;
            await saveClaims(kv, code, claims);
            return [number, token];
        }
    }
    return [0, ''];
}

async function verifySharedSlot(kv, code, playerNumber, token) {
    if (playerNumber < 1 || !token) return false;
    const claims = await loadClaims(kv, code);
    return claims[playerNumber] === token;
}

async function releaseSharedSlot(kv, code, playerNumber) {
    const claims = await loadClaims(kv, code);
    delete claims[playerNumber];
    await saveClaims(kv, code, claims);
}

async function loadSessionGame(kv, sid) {
    if (!sid) return null;
    return kv.get(`session:${sid}`, 'json');
}

async function saveSessionGame(kv, sid, game) {
    await kv.put(`session:${sid}`, JSON.stringify(game), { expirationTtl: 60 * 60 * 24 });
}

function baseCookies(sid) {
    return [`${COOKIE_SID}=${sid}; Path=/; Max-Age=2592000; SameSite=Lax`];
}

async function handleRequest(request, env, url) {
    const kv = env.SCHWIMMEN_KV;
    const cookies = parseCookies(request);
    let sid = cookies[COOKIE_SID];
    const newSid = !sid;
    if (!sid) sid = crypto.randomUUID();

    const setCookies = baseCookies(sid);

    const gameCode = normalizeGameCode(url.searchParams.get('spiel') || '');
    let isMultiplayer = gameCode !== '';
    let playerNumber = isMultiplayer ? 0 : 1;
    const method = request.method;

    let formData = null;
    if (method === 'POST') {
        formData = await request.formData();
    }

    const playerToken = String(url.searchParams.get('token') || formData?.get('token') || '');
    const requestedPlayerNumber = parseInt(url.searchParams.get('spieler') || formData?.get('spieler') || '0', 10);

    let shareUrl = '';
    let showRules = false;
    let joinError = '';
    let game;

    function redirectToGame(code = null, pNumber = null, token = null) {
        const target = new URL(url.pathname, url.origin);
        if (code) {
            target.searchParams.set('spiel', code);
            if (pNumber) {
                target.searchParams.set('spieler', String(pNumber));
                target.searchParams.set('token', token);
            }
        }
        const headers = new Headers({ Location: target.toString() });
        for (const cookie of setCookies) headers.append('Set-Cookie', cookie);
        headers.append('Set-Cookie', `${COOKIE_SHOW_ONCE}=1; Path=/; Max-Age=10; SameSite=Lax`);
        return new Response(null, { status: 303, headers });
    }

    if (isMultiplayer) {
        game = await loadSharedGame(kv, gameCode);
        if (!game) {
            game = createGame();
            joinError = 'Dieses Spiel wurde nicht gefunden.';
            isMultiplayer = false;
            playerNumber = 1;
        } else {
            if (requestedPlayerNumber > 0 && await verifySharedSlot(kv, gameCode, requestedPlayerNumber, playerToken)) {
                playerNumber = requestedPlayerNumber;
            } else if (method !== 'POST') {
                const [claimedNumber, claimedToken] = await claimNextSharedSlot(kv, gameCode, game);
                if (claimedNumber > 0) {
                    return redirectToGame(gameCode, claimedNumber, claimedToken);
                }
            }
            shareUrl = `${url.origin}${url.pathname}?spiel=${encodeURIComponent(gameCode)}`;
        }
    } else {
        game = await loadSessionGame(kv, sid);
        if (!game) game = createGame();
    }

    if (method !== 'POST') {
        if (!isMultiplayer) {
            if (cookies[COOKIE_SHOW_ONCE]) {
                setCookies.push(`${COOKIE_SHOW_ONCE}=; Path=/; Max-Age=0`);
            } else if (!joinError) {
                game = createGame();
                await saveSessionGame(kv, sid, game);
            }
        }
    } else {
        if (formData.has('create_multiplayer')) {
            const humanPlayers = Math.max(2, Math.min(4, parseInt(formData.get('human_players') || '2', 10)));
            const code = await createGameCode(kv);
            game = createGame();
            startGame(game, humanPlayers);
            await saveSharedGame(kv, code, game);
            const [claimedNumber, claimedToken] = await claimNextSharedSlot(kv, code, game);
            return redirectToGame(code, claimedNumber, claimedToken);
        }

        if (formData.has('join_multiplayer')) {
            const postedCode = normalizeGameCode(formData.get('game_code') || '');
            const postedGame = postedCode ? await loadSharedGame(kv, postedCode) : null;
            if (postedGame) {
                const [claimedNumber, claimedToken] = await claimNextSharedSlot(kv, postedCode, postedGame);
                if (claimedNumber > 0) {
                    return redirectToGame(postedCode, claimedNumber, claimedToken);
                }
                joinError = 'Dieses Spiel ist bereits voll.';
            } else {
                joinError = 'Spielcode nicht gefunden.';
            }
            game = createGame();
        } else if (formData.has('title_screen')) {
            if (isMultiplayer && playerNumber > 0) {
                await releaseSharedSlot(kv, gameCode, playerNumber);
            }
            if (isMultiplayer) {
                return redirectToGame();
            }
            game = createGame();
        } else if (formData.has('show_rules')) {
            game = createGame();
            showRules = true;
        } else if (formData.has('start_game') || formData.has('new_game')) {
            const humanPlayers = isMultiplayer ? Math.max(2, countHumanPlayers(game)) : 1;
            game = createGame();
            startGame(game, humanPlayers);
        } else if (game.status === 1) {
            const action = formData.get('action') || '';
            if (action === 'swap_one') {
                const handIndex = parseInt(formData.get('hand_index') ?? '-1', 10);
                const middleIndex = parseInt(formData.get('middle_index') ?? '-1', 10);
                swapOne(game, playerNumber, handIndex, middleIndex);
            } else if (action === 'swap_all') {
                swapAll(game, playerNumber);
            } else if (action === 'pass') {
                pass(game, playerNumber);
            } else if (action === 'knock') {
                knock(game, playerNumber);
            } else if (action === 'renew_middle') {
                renewMiddleSevenEightNine(game, playerNumber);
            } else if (action === 'computer') {
                computerTurn(game);
            }
        }

        if (isMultiplayer) {
            await saveSharedGame(kv, gameCode, game);
            await broadcastGameUpdate(env, gameCode);
        } else {
            await saveSessionGame(kv, sid, game);
        }

        if (!showRules && !joinError) {
            return redirectToGame(
                isMultiplayer ? gameCode : null,
                isMultiplayer && playerNumber > 0 ? playerNumber : null,
                isMultiplayer && playerNumber > 0 ? playerToken : null
            );
        }
    }

    const currentHumanPlayer = game.players.find((p) => p.number === playerNumber) || null;
    const isMyTurn = game.status === 1
        && currentHumanPlayer
        && !currentHumanPlayer.computer
        && game.currentPlayer === playerNumber;

    const html = renderPage({
        game,
        isMultiplayer,
        gameCode,
        currentHumanPlayer,
        shareUrl,
        isMyTurn,
        joinError,
        showRules,
    });

    const headers = new Headers({ 'Content-Type': 'text/html; charset=utf-8' });
    for (const cookie of setCookies) headers.append('Set-Cookie', cookie);
    return new Response(html, { headers });
}

export default {
    async fetch(request, env, ctx) {
        const url = new URL(request.url);

        if (url.pathname === '/ws') {
            const code = normalizeGameCode(url.searchParams.get('spiel') || '');
            if (!code) return new Response('Spielcode fehlt.', { status: 400 });
            const id = env.GAME_ROOMS.idFromName(code);
            const stub = env.GAME_ROOMS.get(id);
            return stub.fetch(request);
        }

        try {
            return await handleRequest(request, env, url);
        } catch (err) {
            return new Response(`Serverfehler: ${err.message}`, { status: 500 });
        }
    },
};
