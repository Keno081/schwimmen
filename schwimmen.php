<?php
require_once __DIR__ . '/schwimmen-logik.php';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schwimmen</title>
    <link rel="stylesheet" href="schwimmen.css">
</head>
<body data-multiplayer="<?= $isMultiplayer ? '1' : '0' ?>" data-my-turn="<?= $isMyTurn ? '1' : '0' ?>">
<main>
    <?php if ($game->getStatus() === 0): ?>
        <section class="title-screen">
            <div class="title-box">
                <div class="title-copy">
                    <div class="title-kicker">31 Punkte · Echtzeit · 2 Geraete</div>
                    <h1>Schwimmen</h1>
                    <p>Starte alleine gegen Computer oder eine private Runde mit Spielcode.</p>
                    <form method="post" class="title-actions">
                        <button type="submit" name="create_multiplayer" value="1">2-Geräte-Spiel erstellen</button>
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
                        <?php if ($joinError): ?>
                            <div class="form-error"><?= htmlspecialchars($joinError) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($showRules): ?>
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
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
    <div class="top">
        <div>
            <h1>Schwimmen</h1>
            <div>4 Spieler, 3 Karten, Ziel: 31 Punkte.</div>
            <?php if ($isMultiplayer): ?>
                <div>Spielcode: <strong><?= htmlspecialchars($gameCode) ?></strong> · Du bist <?= htmlspecialchars($currentHumanPlayer ? $currentHumanPlayer->getName() : 'Zuschauer') ?></div>
            <?php endif; ?>
        </div>
        <form method="post">
            <button type="submit" class="secondary" name="title_screen" value="1">Titel</button>
            <button type="submit" class="secondary" name="new_game" value="1">Neues Spiel</button>
        </form>
    </div>

    <div class="message <?= $game->getStatus() === 2 ? 'final' : '' ?>"><?= htmlspecialchars($game->getMessage()) ?></div>

    <?php if ($isMultiplayer): ?>
        <section class="panel multiplayer-panel">
            <h2>2-Geraete-Spiel</h2>
            <div class="table-info">
                <span class="badge">Code: <?= htmlspecialchars($gameCode) ?></span>
                <span class="badge">Du: <?= htmlspecialchars($currentHumanPlayer ? $currentHumanPlayer->getName() : 'Zuschauer') ?></span>
            </div>
            <input type="text" value="<?= htmlspecialchars($shareUrl) ?>" readonly>
        </section>
    <?php endif; ?>

    <?php if ($game->getStatus() === 2): ?>
        <section class="result-hero">
            <div class="result-title">
                <div>
                    <h2>Spiel beendet</h2>
                </div>
                <div class="result-meta">
                    <span class="badge gold">Beste Punktzahl: <?= htmlspecialchars($game->scoreText($game->getBestScore())) ?></span>
                    <span class="badge">Karten im Stapel: <?= htmlspecialchars((string) $game->getDeck()->count()) ?></span>
                    <span class="badge">Ablage: <?= htmlspecialchars((string) $game->getDiscardPile()->count()) ?></span>
                </div>
            </div>
            <form method="post" class="actions">
                <button type="submit" name="new_game" value="1">Neue Runde</button>
                <button type="submit" class="secondary" name="title_screen" value="1">Zum Titel</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($game->getStatus() === 1): ?>
    <section class="panel">
        <h2>Deine Hand</h2>
        <?php if ($currentHumanPlayer): ?>
            <div class="score">Punkte: <?= htmlspecialchars($game->scoreText($game->scoreHand($currentHumanPlayer->getHand()))) ?></div>
        <?php endif; ?>
        <div class="table-info">
            <span class="badge">Am Zug: <?= htmlspecialchars($game->getCurrentPlayer()->getName()) ?></span>
            <span class="badge">Stapel: <?= htmlspecialchars((string) $game->getDeck()->count()) ?></span>
            <span class="badge">Ablage: <?= htmlspecialchars((string) $game->getDiscardPile()->count()) ?></span>
        </div>
        <form method="post">
            <?php if ($currentHumanPlayer): ?>
                <div class="cards">
                    <?php foreach ($currentHumanPlayer->getHand()->getCards() as $index => $card): ?>
                        <label class="choice">
                            <input type="radio" name="hand_index" value="<?= $index ?>" <?= $index === 0 ? 'checked' : '' ?>>
                            <?= $card ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2>Mitte</h2>
            <div class="cards">
                <?php foreach ($game->getMiddle()->getCards() as $index => $card): ?>
                    <label class="choice">
                        <input type="radio" name="middle_index" value="<?= $index ?>" <?= $index === 0 ? 'checked' : '' ?>>
                        <?= $card ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <?php if ($isMyTurn): ?>
                <div class="actions">
                    <button type="submit" name="action" value="swap_one">Ausgewaehlte Karten tauschen</button>
                    <button type="submit" name="action" value="swap_all">Alle tauschen</button>
                    <button type="submit" class="secondary" name="action" value="pass">Schieben</button>
                    <button type="submit" class="secondary" name="action" value="knock">Klopfen</button>
                    <?php if ($game->canRenewMiddleSevenEightNine()): ?>
                        <button type="submit" class="secondary" name="action" value="renew_middle">Neue Mitte</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>

        <?php if ($game->getStatus() === 1 && $game->getCurrentPlayer()->isComputer()): ?>
            <form method="post" class="actions">
                <button type="submit" name="action" value="computer">
                    <?= htmlspecialchars($game->getCurrentPlayer()->getName()) ?> ausfuehren
                </button>
            </form>
        <?php elseif ($game->getStatus() === 1 && !$isMyTurn): ?>
            <div class="score">Warte auf <?= htmlspecialchars($game->getCurrentPlayer()->getName()) ?>.</div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="grid">
        <?php foreach ($game->getPlayers() as $player): ?>
            <?php $canSeeCards = $game->getStatus() === 2 || ($currentHumanPlayer && $player->getNumber() === $currentHumanPlayer->getNumber()); ?>
            <div class="panel <?= $game->isWinner($player) ? 'winner' : '' ?>">
                <div class="player-head">
                    <h2><?= htmlspecialchars($player->getName()) ?></h2>
                    <?php if ($game->isWinner($player)): ?>
                        <span class="badge gold">Gewonnen</span>
                    <?php endif; ?>
                </div>
                <?php if ($canSeeCards): ?>
                    <div class="score">Punkte: <?= htmlspecialchars($game->scoreText($game->scoreHand($player->getHand()))) ?></div>
                <?php else: ?>
                    <div class="score">Punkte: verdeckt</div>
                <?php endif; ?>
                <div>Status:
                    <?php if ($game->getStatus() === 2): ?>
                        beendet
                    <?php elseif ($game->getCurrentPlayerNumber() === $player->getNumber()): ?>
                        am Zug
                    <?php elseif ($player->hasPassed()): ?>
                        geschoben
                    <?php else: ?>
                        wartet
                    <?php endif; ?>
                </div>

                <?php if ($canSeeCards): ?>
                    <div class="cards"><?= $player->getHand() ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</main>
    <script src="schwimmen.js"></script>
</body>
</html>
