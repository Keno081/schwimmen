<?php

require_once __DIR__ . '/schwimmen-klassen.php';

session_start();

function getSharedGameDirectory(): string
{
    $directory = __DIR__ . '/spiele';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    return $directory;
}

function normalizeGameCode(string $code): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
}

function createGameCode(): string
{
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $file = getSharedGameFile($code);
    } while (is_file($file));

    return $code;
}

function getSharedGameFile(string $code): string
{
    return getSharedGameDirectory() . '/' . normalizeGameCode($code) . '.game';
}

function getSharedPlayerFile(string $code): string
{
    return getSharedGameDirectory() . '/' . normalizeGameCode($code) . '.players.json';
}

function loadSharedGame(string $code): ?SchwimmenGame
{
    $file = getSharedGameFile($code);
    if (!is_file($file)) {
        return null;
    }

    $game = unserialize(file_get_contents($file));
    return $game instanceof SchwimmenGame ? $game : null;
}

function saveSharedGame(string $code, SchwimmenGame $game): void
{
    file_put_contents(getSharedGameFile($code), serialize($game), LOCK_EX);
}

function countHumanPlayers(SchwimmenGame $game): int
{
    $count = 0;
    foreach ($game->getPlayers() as $player) {
        if (!$player->isComputer()) {
            $count++;
        }
    }

    return $count;
}

function loadSharedPlayerClaims(string $code): array
{
    $file = getSharedPlayerFile($code);
    if (!is_file($file)) {
        return [];
    }

    $claims = json_decode(file_get_contents($file), true);
    return is_array($claims) ? $claims : [];
}

function saveSharedPlayerClaims(string $code, array $claims): void
{
    file_put_contents(getSharedPlayerFile($code), json_encode($claims, JSON_PRETTY_PRINT), LOCK_EX);
}

function claimSharedPlayer(string $code, SchwimmenGame $game): int
{
    $sessionId = session_id();
    $humanPlayers = countHumanPlayers($game);
    $claims = loadSharedPlayerClaims($code);

    foreach ($claims as $number => $claimSessionId) {
        if ($claimSessionId === $sessionId) {
            return (int) $number;
        }
    }

    for ($number = 1; $number <= $humanPlayers; $number++) {
        if (empty($claims[(string) $number])) {
            $claims[(string) $number] = $sessionId;
            saveSharedPlayerClaims($code, $claims);
            return $number;
        }
    }

    return 0;
}

function releaseSharedPlayer(string $code): void
{
    $sessionId = session_id();
    $claims = loadSharedPlayerClaims($code);
    foreach ($claims as $number => $claimSessionId) {
        if ($claimSessionId === $sessionId) {
            unset($claims[$number]);
        }
    }

    saveSharedPlayerClaims($code, $claims);
}

function redirectToGame(?string $gameCode = null): void
{
    $target = $_SERVER['PHP_SELF'];
    if ($gameCode) {
        $target .= '?spiel=' . urlencode($gameCode);
    }

    header('Location: ' . $target);
    exit;
}

$gameCode = normalizeGameCode($_GET['spiel'] ?? '');
$isMultiplayer = $gameCode !== '';
$playerNumber = 1;
$shareUrl = '';
$showRules = false;
$joinError = '';

if (!isset($_SESSION['players_by_game']) || !is_array($_SESSION['players_by_game'])) {
    $_SESSION['players_by_game'] = [];
}

if ($isMultiplayer) {
    $game = loadSharedGame($gameCode);
    if (!$game instanceof SchwimmenGame) {
        $game = new SchwimmenGame();
        $joinError = 'Dieses Spiel wurde nicht gefunden.';
        $isMultiplayer = false;
        $gameCode = '';
    } else {
        if (!isset($_SESSION['players_by_game'][$gameCode])) {
            $_SESSION['players_by_game'][$gameCode] = claimSharedPlayer($gameCode, $game);
        }

        $playerNumber = (int) $_SESSION['players_by_game'][$gameCode];
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $shareUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?spiel=' . urlencode($gameCode);
    }
} else {
    $game = SchwimmenGame::load();
    if (!$game instanceof SchwimmenGame) {
        $game = new SchwimmenGame();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!$isMultiplayer) {
        if (!empty($_SESSION['show_game_once'])) {
            unset($_SESSION['show_game_once']);
        } elseif (!$joinError) {
            $game = new SchwimmenGame();
            $game->save();
        }
    }
} else {
    if (isset($_POST['create_multiplayer'])) {
        $humanPlayers = max(2, min(4, (int) ($_POST['human_players'] ?? 2)));
        $gameCode = createGameCode();
        $game = new SchwimmenGame();
        $game->start($humanPlayers);
        $_SESSION['players_by_game'][$gameCode] = 1;
        saveSharedPlayerClaims($gameCode, ['1' => session_id()]);
        saveSharedGame($gameCode, $game);
        redirectToGame($gameCode);
    }

    if (isset($_POST['join_multiplayer'])) {
        $postedCode = normalizeGameCode($_POST['game_code'] ?? '');
        $postedGame = $postedCode ? loadSharedGame($postedCode) : null;
        if ($postedGame instanceof SchwimmenGame) {
            $_SESSION['players_by_game'][$postedCode] = claimSharedPlayer($postedCode, $postedGame);
            redirectToGame($postedCode);
        }

        $joinError = 'Spielcode nicht gefunden.';
        $game = new SchwimmenGame();
    } elseif (isset($_POST['title_screen'])) {
        if ($isMultiplayer) {
            releaseSharedPlayer($gameCode);
            unset($_SESSION['players_by_game'][$gameCode]);
            redirectToGame();
        }

        $game = new SchwimmenGame();
    } elseif (isset($_POST['show_rules'])) {
        $game = new SchwimmenGame();
        $showRules = true;
    } elseif (isset($_POST['start_game']) || isset($_POST['new_game'])) {
        $humanPlayers = $isMultiplayer ? max(2, countHumanPlayers($game)) : 1;
        $game = new SchwimmenGame();
        $game->start($humanPlayers);
        $_SESSION['show_game_once'] = true;
    } elseif ($game->getStatus() === 1) {
        $action = $_POST['action'] ?? '';

        if ($action === 'swap_one') {
            $handIndex = isset($_POST['hand_index']) ? (int) $_POST['hand_index'] : -1;
            $middleIndex = isset($_POST['middle_index']) ? (int) $_POST['middle_index'] : -1;
            $game->swapOne($playerNumber, $handIndex, $middleIndex);
        } elseif ($action === 'swap_all') {
            $game->swapAll($playerNumber);
        } elseif ($action === 'pass') {
            $game->pass($playerNumber);
        } elseif ($action === 'knock') {
            $game->knock($playerNumber);
        } elseif ($action === 'renew_middle') {
            $game->renewMiddleSevenEightNine($playerNumber);
        } elseif ($action === 'computer') {
            $game->computerTurn();
        }

        $_SESSION['show_game_once'] = true;
    }

    if ($isMultiplayer) {
        saveSharedGame($gameCode, $game);
    } else {
        $game->save();
    }

    if (!$showRules && !$joinError) {
        redirectToGame($isMultiplayer ? $gameCode : null);
    }
}

$currentHumanPlayer = $game->getPlayer($playerNumber);
$isMyTurn = $game->getStatus() === 1
    && $currentHumanPlayer instanceof Player
    && !$currentHumanPlayer->isComputer()
    && $game->getCurrentPlayerNumber() === $playerNumber;
