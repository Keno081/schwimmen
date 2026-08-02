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

function generateShareToken(): string
{
    return bin2hex(random_bytes(8));
}

function claimNextSharedSlot(string $code, SchwimmenGame $game): array
{
    $humanPlayers = countHumanPlayers($game);
    $claims = loadSharedPlayerClaims($code);

    for ($number = 1; $number <= $humanPlayers; $number++) {
        if (empty($claims[(string) $number])) {
            $token = generateShareToken();
            $claims[(string) $number] = $token;
            saveSharedPlayerClaims($code, $claims);
            return [$number, $token];
        }
    }

    return [0, ''];
}

function verifySharedSlot(string $code, int $playerNumber, string $token): bool
{
    if ($playerNumber < 1 || $token === '') {
        return false;
    }

    $claims = loadSharedPlayerClaims($code);
    return isset($claims[(string) $playerNumber]) && hash_equals((string) $claims[(string) $playerNumber], $token);
}

function releaseSharedSlot(string $code, int $playerNumber): void
{
    $claims = loadSharedPlayerClaims($code);
    unset($claims[(string) $playerNumber]);
    saveSharedPlayerClaims($code, $claims);
}

function redirectToGame(?string $gameCode = null, ?int $playerNumber = null, ?string $token = null): void
{
    $target = $_SERVER['PHP_SELF'];
    $params = [];
    if ($gameCode) {
        $params['spiel'] = $gameCode;
        if ($playerNumber) {
            $params['spieler'] = $playerNumber;
            $params['token'] = $token;
        }
    }

    if ($params) {
        $target .= '?' . http_build_query($params);
    }

    header('Location: ' . $target);
    exit;
}

$gameCode = normalizeGameCode($_GET['spiel'] ?? '');
$isMultiplayer = $gameCode !== '';
$playerNumber = $isMultiplayer ? 0 : 1;
$playerToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$requestedPlayerNumber = (int) ($_GET['spieler'] ?? $_POST['spieler'] ?? 0);
$shareUrl = '';
$showRules = false;
$joinError = '';

if ($isMultiplayer) {
    $game = loadSharedGame($gameCode);
    if (!$game instanceof SchwimmenGame) {
        $game = new SchwimmenGame();
        $joinError = 'Dieses Spiel wurde nicht gefunden.';
        $isMultiplayer = false;
        $gameCode = '';
        $playerNumber = 1;
    } else {
        if ($requestedPlayerNumber > 0 && verifySharedSlot($gameCode, $requestedPlayerNumber, $playerToken)) {
            $playerNumber = $requestedPlayerNumber;
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            [$claimedNumber, $claimedToken] = claimNextSharedSlot($gameCode, $game);
            if ($claimedNumber > 0) {
                redirectToGame($gameCode, $claimedNumber, $claimedToken);
            }
        }

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
        saveSharedGame($gameCode, $game);
        [$claimedNumber, $claimedToken] = claimNextSharedSlot($gameCode, $game);
        redirectToGame($gameCode, $claimedNumber, $claimedToken);
    }

    if (isset($_POST['join_multiplayer'])) {
        $postedCode = normalizeGameCode($_POST['game_code'] ?? '');
        $postedGame = $postedCode ? loadSharedGame($postedCode) : null;
        if ($postedGame instanceof SchwimmenGame) {
            [$claimedNumber, $claimedToken] = claimNextSharedSlot($postedCode, $postedGame);
            if ($claimedNumber > 0) {
                redirectToGame($postedCode, $claimedNumber, $claimedToken);
            }
            $joinError = 'Dieses Spiel ist bereits voll.';
        } else {
            $joinError = 'Spielcode nicht gefunden.';
        }

        $game = new SchwimmenGame();
    } elseif (isset($_POST['title_screen'])) {
        if ($isMultiplayer && $playerNumber > 0) {
            releaseSharedSlot($gameCode, $playerNumber);
        }

        if ($isMultiplayer) {
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
        redirectToGame(
            $isMultiplayer ? $gameCode : null,
            ($isMultiplayer && $playerNumber > 0) ? $playerNumber : null,
            ($isMultiplayer && $playerNumber > 0) ? $playerToken : null
        );
    }
}

$currentHumanPlayer = $game->getPlayer($playerNumber);
$isMyTurn = $game->getStatus() === 1
    && $currentHumanPlayer instanceof Player
    && !$currentHumanPlayer->isComputer()
    && $game->getCurrentPlayerNumber() === $playerNumber;
