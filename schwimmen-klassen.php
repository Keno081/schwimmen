<?php

class Card
{
    private $index;
    private $suit;
    private $sign;
    private $rank;
    private $value;
    private $unicode;

    public function __construct($index, $suit, $sign, $rank, $unicode, $value = null)
    {
        $this->index = $index;
        $this->suit = $suit;
        $this->sign = $sign;
        $this->rank = $rank;
        $this->value = $value;
        $this->unicode = $unicode;
    }

    public function getIndex()
    {
        return $this->index;
    }

    public function getSuit()
    {
        return $this->suit;
    }

    public function getSign()
    {
        return $this->sign;
    }

    public function getRank()
    {
        return $this->rank;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getUnicode()
    {
        return $this->unicode;
    }

    public function getShortName()
    {
        $rank = $this->rank;
        if ($rank === 'Bube') {
            $rank = 'B';
        } elseif ($rank === 'Dame') {
            $rank = 'D';
        } elseif ($rank === 'König') {
            $rank = 'K';
        } elseif ($rank === 'Ass') {
            $rank = 'A';
        }

        return $rank . $this->sign;
    }

    public function __toString()
    {
        $cssColor = ($this->getSign() == "♦" || $this->getSign() == "♥") ? "red" : "black";
        return "<span class=\"card $cssColor\"><span class=\"unicode\">{$this->getUnicode()}</span><span>{$this->getShortName()}</span></span>";
    }
}

class Cardset
{
    private $cards = [];

    public function getCards(): array
    {
        return $this->cards;
    }

    public function setCards(array $cards)
    {
        $this->cards = array_values($cards);
    }

    public function addCard(Card $card)
    {
        $this->cards[] = $card;
    }

    public function getCard(int $index): ?Card
    {
        return $this->cards[$index] ?? null;
    }

    public function replaceCard(int $index, Card $card)
    {
        if (!isset($this->cards[$index])) {
            throw new Exception("Karte nicht vorhanden.");
        }
        $this->cards[$index] = $card;
    }

    public function getTopCard(): ?Card
    {
        if ($this->isEmpty()) {
            return null;
        }
        return array_pop($this->cards);
    }

    public function removeCard(Card $card)
    {
        foreach ($this->cards as $index => $storedCard) {
            if ($storedCard->getIndex() === $card->getIndex()) {
                unset($this->cards[$index]);
                $this->cards = array_values($this->cards);
                return;
            }
        }
    }

    public function shuffle()
    {
        shuffle($this->cards);
    }

    public function clear()
    {
        $this->cards = [];
    }

    public function isEmpty(): bool
    {
        return count($this->cards) == 0;
    }

    public function count(): int
    {
        return count($this->cards);
    }

    public function __toString(): string
    {
        $output = [];
        foreach ($this->cards as $card) {
            $output[] = (string) $card;
        }
        return implode(" ", $output);
    }
}

class Player
{
    private $number = 0;
    private $name;
    private $hand;
    private $computer;
    private $passed = false;

    public function __construct($name, $computer = false)
    {
        $this->name = $name;
        $this->computer = $computer;
        $this->hand = new Cardset();
    }

    public function setNumber(int $number)
    {
        $this->number = $number;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHand(): Cardset
    {
        return $this->hand;
    }

    public function isComputer(): bool
    {
        return $this->computer;
    }

    public function hasPassed(): bool
    {
        return $this->passed;
    }

    public function setPassed(bool $passed)
    {
        $this->passed = $passed;
    }
}

class Cardgame
{
    protected Cardset $deck;
    protected Cardset $discardpile;
    protected array $players;
    protected int $currentPlayer = 1;
    protected bool $direction = true;
    protected int $status = 0;
    protected string $message = '';

    public function __construct()
    {
        $this->deck = new Cardset();
        $this->discardpile = new Cardset();
        $this->players = [];
    }

    public function getDeck(): Cardset
    {
        return $this->deck;
    }

    public function getDiscardPile(): Cardset
    {
        return $this->discardpile;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status)
    {
        $this->status = $status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
    }

    public function addPlayer(Player $player)
    {
        $player->setNumber(count($this->players) + 1);
        $this->players[$player->getNumber()] = $player;
    }

    public function getPlayers(): array
    {
        return $this->players;
    }

    public function getPlayer(int $index): ?Player
    {
        return $this->players[$index] ?? null;
    }

    public function getCurrentPlayer(): Player
    {
        return $this->players[$this->currentPlayer];
    }

    public function setCurrentPlayer(Player $player)
    {
        $this->currentPlayer = $player->getNumber();
    }

    public function getCurrentPlayerNumber(): int
    {
        return $this->currentPlayer;
    }

    public function nextPlayer()
    {
        if ($this->direction) {
            $this->currentPlayer = isset($this->players[$this->currentPlayer + 1]) ? $this->currentPlayer + 1 : 1;
        } else {
            $this->currentPlayer = isset($this->players[$this->currentPlayer - 1]) ? $this->currentPlayer - 1 : count($this->players);
        }
    }

    public function save()
    {
        $_SESSION['cardgame'] = serialize($this);
    }

    public static function load(): ?Cardgame
    {
        if (isset($_SESSION['cardgame'])) {
            return unserialize($_SESSION['cardgame']);
        }
        return null;
    }

    public function getUnicodeCard(string $suit, string $rank = '0'): ?string
    {
        $unicodeFrz = [
            'Kreuz' => ['7' => '&#127191;', '8' => '&#127192;', '9' => '&#127193;', '10' => '&#127194;', 'Bube' => '&#127195;', 'Dame' => '&#127197;', 'König' => '&#127198;', 'Ass' => '&#127185;'],
            'Pik' => ['7' => '&#127143;', '8' => '&#127144;', '9' => '&#127145;', '10' => '&#127146;', 'Bube' => '&#127147;', 'Dame' => '&#127149;', 'König' => '&#127150;', 'Ass' => '&#127137;'],
            'Herz' => ['7' => '&#127159;', '8' => '&#127160;', '9' => '&#127161;', '10' => '&#127162;', 'Bube' => '&#127163;', 'Dame' => '&#127165;', 'König' => '&#127166;', 'Ass' => '&#127153;'],
            'Karo' => ['7' => '&#127175;', '8' => '&#127176;', '9' => '&#127177;', '10' => '&#127178;', 'Bube' => '&#127179;', 'Dame' => '&#127181;', 'König' => '&#127182;', 'Ass' => '&#127169;'],
        ];

        return $unicodeFrz[$suit][$rank] ?? null;
    }
}

class SchwimmenGame extends Cardgame
{
    private Cardset $middle;
    private ?int $knockedBy = null;
    private ?int $lastPlayerAfterKnock = null;

    public function __construct()
    {
        parent::__construct();
        $this->middle = new Cardset();
    }

    public function start(int $humanPlayers = 1)
    {
        $this->status = 1;
        $this->message = 'Neues Spiel gestartet. Spieler 1 ist am Zug.';
        $this->players = [];
        $this->deck->clear();
        $this->discardpile->clear();
        $this->middle->clear();
        $this->knockedBy = null;
        $this->lastPlayerAfterKnock = null;
        $this->currentPlayer = 1;

        $this->createDeck();
        $humanPlayers = max(1, min(4, $humanPlayers));
        for ($number = 1; $number <= 4; $number++) {
            if ($humanPlayers === 1 && $number === 1) {
                $this->addPlayer(new Player('Du'));
            } elseif ($number <= $humanPlayers) {
                $this->addPlayer(new Player('Spieler ' . $number));
            } else {
                $this->addPlayer(new Player('Computer ' . ($number - $humanPlayers), true));
            }
        }

        for ($round = 0; $round < 3; $round++) {
            foreach ($this->players as $player) {
                $player->getHand()->addCard($this->deck->getTopCard());
            }
        }

        for ($i = 0; $i < 3; $i++) {
            $this->middle->addCard($this->deck->getTopCard());
        }

        $this->finishIfAnyPlayerHasThirtyTwo();
    }

    public function createDeck()
    {
        $suits = [
            'Kreuz' => '♣',
            'Pik' => '♠',
            'Herz' => '♥',
            'Karo' => '♦',
        ];
        $ranks = [
            '7' => 7,
            '8' => 8,
            '9' => 9,
            '10' => 10,
            'Bube' => 10,
            'Dame' => 10,
            'König' => 10,
            'Ass' => 11,
        ];

        $index = 1;
        foreach ($suits as $suit => $sign) {
            foreach ($ranks as $rank => $value) {
                $unicode = $this->getUnicodeCard($suit, $rank);
                $this->deck->addCard(new Card($index, $suit, $sign, $rank, $unicode, $value));
                $index++;
            }
        }

        $this->deck->shuffle();
    }

    public function getMiddle(): Cardset
    {
        return $this->middle;
    }

    public function scoreHand(Cardset $hand): float
    {
        $cards = $hand->getCards();
        if (count($cards) !== 3) {
            return 0;
        }

        if ($cards[0]->getRank() === 'Ass' && $cards[1]->getRank() === 'Ass' && $cards[2]->getRank() === 'Ass') {
            return 32;
        }

        if ($cards[0]->getRank() === $cards[1]->getRank() && $cards[1]->getRank() === $cards[2]->getRank()) {
            return 30.5;
        }

        $scores = [];
        foreach ($cards as $card) {
            $scores[$card->getSuit()] = ($scores[$card->getSuit()] ?? 0) + $card->getValue();
        }

        return max($scores);
    }

    public function scoreText(float $score): string
    {
        return $score == 30.5 ? '30,5' : (string) $score;
    }

    public function getBestScore(): float
    {
        $bestScore = 0;
        foreach ($this->players as $player) {
            $score = $this->scoreHand($player->getHand());
            if ($score > $bestScore) {
                $bestScore = $score;
            }
        }

        return $bestScore;
    }

    public function isWinner(Player $player): bool
    {
        return $this->status === 2 && $this->scoreHand($player->getHand()) === $this->getBestScore();
    }

    public function getWinnerNames(): string
    {
        $names = [];
        foreach ($this->players as $player) {
            if ($this->isWinner($player)) {
                $names[] = $player->getName();
            }
        }

        return implode(', ', $names);
    }

    public function swapOne(int $playerNumber, int $handIndex, int $middleIndex)
    {
        if ($this->currentPlayer !== $playerNumber) {
            $this->message = 'Du bist gerade nicht am Zug.';
            return;
        }

        $player = $this->players[$playerNumber] ?? null;
        if (!$player || $player->isComputer()) {
            $this->message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
            return;
        }

        $handCard = $player->getHand()->getCard($handIndex);
        $middleCard = $this->middle->getCard($middleIndex);

        if (!$handCard || !$middleCard) {
            $this->message = 'Bitte waehle eine Handkarte und eine Karte aus der Mitte.';
            return;
        }

        $player->getHand()->replaceCard($handIndex, $middleCard);
        $this->middle->replaceCard($middleIndex, $handCard);
        $this->resetPasses();
        $this->finishHumanTurn($playerNumber, $player->getName() . ' hat ' . $handCard->getShortName() . ' gegen ' . $middleCard->getShortName() . ' getauscht.');
    }

    public function swapAll(int $playerNumber)
    {
        if ($this->currentPlayer !== $playerNumber) {
            $this->message = 'Du bist gerade nicht am Zug.';
            return;
        }

        $player = $this->players[$playerNumber] ?? null;
        if (!$player || $player->isComputer()) {
            $this->message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
            return;
        }

        $oldHand = $player->getHand()->getCards();
        $oldMiddle = $this->middle->getCards();
        $player->getHand()->setCards($oldMiddle);
        $this->middle->setCards($oldHand);
        $this->resetPasses();
        $this->finishHumanTurn($playerNumber, $player->getName() . ' hat alle drei Karten getauscht.');
    }

    public function pass(int $playerNumber)
    {
        if ($this->currentPlayer !== $playerNumber) {
            $this->message = 'Du bist gerade nicht am Zug.';
            return;
        }

        $player = $this->players[$playerNumber] ?? null;
        if (!$player || $player->isComputer()) {
            $this->message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
            return;
        }

        $player->setPassed(true);
        $this->finishHumanTurn($playerNumber, $player->getName() . ' hat geschoben.');
    }

    public function knock(int $playerNumber)
    {
        if ($this->currentPlayer !== $playerNumber || $this->knockedBy !== null) {
            $this->message = 'Klopfen ist gerade nicht moeglich.';
            return;
        }

        $player = $this->players[$playerNumber] ?? null;
        if (!$player || $player->isComputer()) {
            $this->message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
            return;
        }

        $this->knockedBy = $playerNumber;
        $this->lastPlayerAfterKnock = $playerNumber === 1 ? count($this->players) : $playerNumber - 1;
        $this->finishHumanTurn($playerNumber, $player->getName() . ' hat geklopft. Jeder andere Spieler hat noch einen Zug.');
    }

    public function canRenewMiddleSevenEightNine(): bool
    {
        $ranks = [];
        foreach ($this->middle->getCards() as $card) {
            $ranks[] = $card->getRank();
        }

        return count($ranks) === 3
            && in_array('7', $ranks, true)
            && in_array('8', $ranks, true)
            && in_array('9', $ranks, true);
    }

    public function renewMiddleSevenEightNine(int $playerNumber)
    {
        if ($this->currentPlayer !== $playerNumber) {
            $this->message = 'Du bist gerade nicht am Zug.';
            return;
        }

        $player = $this->players[$playerNumber] ?? null;
        if (!$player || $player->isComputer()) {
            $this->message = 'Diese Aktion ist fuer diesen Spieler nicht moeglich.';
            return;
        }

        if (!$this->canRenewMiddleSevenEightNine()) {
            $this->message = 'Neue Karten sind nur moeglich, wenn in der Mitte 7, 8 und 9 liegen, zum Beispiel 789, 879 oder 978.';
            return;
        }

        if (!$this->renewMiddleCards('Es sind nicht genug Karten im Stapel.')) {
            return;
        }

        $this->resetPasses();
        $this->finishHumanTurn($playerNumber, 'In der Mitte lagen 7, 8 und 9, egal in welcher Reihenfolge. ' . $player->getName() . ' hat drei neue Karten aufgedeckt.');
    }
    public function computerTurn()
    {
        if ($this->status !== 1 || !$this->getCurrentPlayer()->isComputer()) {
            return;
        }

        if ($this->finishIfAnyPlayerHasThirtyTwo()) {
            return;
        }

        $player = $this->players[$this->currentPlayer];
        $currentScore = $this->scoreHand($player->getHand());
        $bestScore = $currentScore;
        $bestHand = $player->getHand()->getCards();
        $bestMiddle = $this->middle->getCards();
        $action = 'schiebt.';

        $middleScore = $this->scoreCards($this->middle->getCards());
        if ($middleScore > $bestScore) {
            $bestScore = $middleScore;
            $bestHand = $this->middle->getCards();
            $bestMiddle = $player->getHand()->getCards();
            $action = 'tauscht alle Karten.';
        }

        $handCards = $player->getHand()->getCards();
        $middleCards = $this->middle->getCards();
        for ($h = 0; $h < 3; $h++) {
            for ($m = 0; $m < 3; $m++) {
                $newHand = $handCards;
                $newMiddle = $middleCards;
                $temp = $newHand[$h];
                $newHand[$h] = $newMiddle[$m];
                $newMiddle[$m] = $temp;
                $score = $this->scoreCards($newHand);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestHand = $newHand;
                    $bestMiddle = $newMiddle;
                    $action = 'tauscht eine Karte.';
                }
            }
        }

        if ($bestScore > $currentScore) {
            $player->getHand()->setCards($bestHand);
            $this->middle->setCards($bestMiddle);
            $this->resetPasses();

            if ($this->finishIfAnyPlayerHasThirtyTwo()) {
                return;
            }

            if ($bestScore >= 31) {
                $this->finishGame($player->getName() . ' hat ' . $this->scoreText($bestScore) . ' Punkte erreicht.');
                return;
            }
        } else {
            if ($currentScore >= 31) {
                $this->finishGame($player->getName() . ' hat ' . $this->scoreText($currentScore) . ' Punkte erreicht.');
                return;
            }

            if ($currentScore >= 29 && $this->knockedBy === null) {
                $this->knockedBy = $player->getNumber();
                $this->lastPlayerAfterKnock = $player->getNumber() === 1 ? 4 : $player->getNumber() - 1;
                $action = 'klopft.';
            } elseif ($this->canRenewMiddleSevenEightNine()) {
                if (!$this->renewMiddleCards($player->getName() . ' wollte neue Karten aufdecken, aber es sind nicht genug Karten im Stapel.')) {
                    return;
                }
                $this->resetPasses();
                $action = 'deckt neue Karten in der Mitte auf.';
            } else {
                $player->setPassed(true);
            }
        }

        $this->message = $player->getName() . ' ' . $action;
        $this->nextSchwimmenPlayer();
    }

    private function scoreCards(array $cards): float
    {
        $set = new Cardset();
        $set->setCards($cards);
        return $this->scoreHand($set);
    }

    private function finishHumanTurn(int $playerNumber, string $message)
    {
        if ($this->finishIfAnyPlayerHasThirtyTwo()) {
            return;
        }

        $player = $this->players[$playerNumber];
        $score = $this->scoreHand($player->getHand());
        if ($score >= 31) {
            $this->finishGame($player->getName() . ' hat ' . $this->scoreText($score) . ' Punkte erreicht.');
            return;
        }

        $this->message = $message;
        $this->nextSchwimmenPlayer();
        if ($this->status === 1) {
            $this->message .= ' Jetzt ist ' . $this->getCurrentPlayer()->getName() . ' am Zug.';
        }
    }

    private function nextSchwimmenPlayer()
    {
        if ($this->knockedBy !== null && $this->currentPlayer === $this->lastPlayerAfterKnock) {
            $this->finishGame('Die Runde nach dem Klopfen ist vorbei.');
            return;
        }

        for ($i = 0; $i < count($this->players); $i++) {
            $this->nextPlayer();
            if (!$this->getCurrentPlayer()->hasPassed()) {
                return;
            }
        }

        $this->renewMiddleAfterPasses();
    }

    private function resetPasses()
    {
        foreach ($this->players as $player) {
            $player->setPassed(false);
        }
    }

    private function renewMiddleAfterPasses()
    {
        if (!$this->renewMiddleCards('Alle Spieler haben geschoben und es sind nicht genug Karten im Stapel.')) {
            return;
        }

        $this->resetPasses();
        $this->nextPlayer();
        $this->message = 'Alle Spieler haben geschoben. Es liegen drei neue Karten in der Mitte.';
    }

    private function renewMiddleCards(string $emptyDeckMessage): bool
    {
        if ($this->deck->count() < 3) {
            $this->finishGame($emptyDeckMessage);
            return false;
        }

        foreach ($this->middle->getCards() as $card) {
            $this->discardpile->addCard($card);
        }

        $this->middle->clear();
        for ($i = 0; $i < 3; $i++) {
            $this->middle->addCard($this->deck->getTopCard());
        }

        return true;
    }

    private function finishIfAnyPlayerHasThirtyTwo(): bool
    {
        foreach ($this->players as $player) {
            if ($this->scoreHand($player->getHand()) === 32.0) {
                $this->finishGame($player->getName() . ' hat 32 Punkte auf der Hand.');
                return true;
            }
        }

        return false;
    }

    private function finishGame(string $reason)
    {
        $bestPlayer = null;
        $bestScore = -1;
        foreach ($this->players as $player) {
            $score = $this->scoreHand($player->getHand());
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPlayer = $player;
            }
        }

        $this->status = 2;
        $this->message = $reason . ' Gewinner: ' . $bestPlayer->getName() . ' mit ' . $this->scoreText($bestScore) . ' Punkten.';
    }
}
