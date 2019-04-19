<?php

class Game
{
    private $currentFrame = 0;

    public function __construct()
    {
        $this->initializeFrames();
        $this->currentFrame()->start();
    }

    public function roll($pinCount)
    {
        if ($this->gameIsOver()) {
            throw new GameIsOver;
        }

        foreach ($this->framesAcceptingRolls() as $frame) {
            $frame->roll($pinCount);
        }

        if ($this->shouldStartNextFrame()) {
            $this->frames[++$this->currentFrame]->start();
        }
    }

    public function score()
    {
        if ($this->gameIsNotOver()) {
            throw new CantScore;
        }

        return array_reduce($this->frames, function ($score, $frame) {
            return $score + $frame->totalPins();
        }, 0);
    }

    private function framesAcceptingRolls()
    {
        return array_filter($this->frames, function ($frame) {
            return $frame->isAcceptingRolls();
        });
    }

    private function shouldStartNextFrame()
    {
        if ($this->currentFrame == 9) {
            return false;
        }

        if ($this->currentFrame()->isAcceptingOnlyBonusRolls()) {
            return true;
        }

        return !$this->currentFrame()->isAcceptingRolls();
    }

    private function currentFrame()
    {
        return $this->frames[$this->currentFrame];
    }

    private function gameIsNotOver()
    {
        return !$this->gameIsOver();
    }

    private function gameIsOver()
    {
        return $this->currentFrame == 9 && !$this->currentFrame()->isAcceptingRolls();
    }

    private function initializeFrames(): array
    {
        return $this->frames = [
            new Frame(),
            new Frame(),
            new Frame(),
            new Frame(),
            new Frame(),
            new Frame(),
            new Frame(),
            new Frame(),
            new Frame(),
            new Frame()
        ];
    }
}

class Frame
{
    private $rolls = [];
    private $acceptingBonusRolls = false;
    private $acceptingRegularRolls = false;

    public function roll($pinCount)
    {
        $this->rolls[] = $this->newRoll($pinCount);
        $this->acceptingRegularRolls = $this->totalPins() < 10 && count($this->rolls) < 2;
        $this->acceptingBonusRolls = $this->totalPins() >= 10 && count($this->rolls) < 3;
    }

    public function totalPins()
    {
        return array_reduce($this->rolls, function ($pinCount, $roll) {
            return $pinCount + $roll->pinCount();
        }, 0);
    }

    public function isAcceptingRolls()
    {
        return $this->acceptingRegularRolls || $this->acceptingBonusRolls;
    }

    public function isAcceptingOnlyBonusRolls()
    {
        return $this->acceptingRegularRolls == false && $this->acceptingBonusRolls == true;
    }

    public function start()
    {
        $this->acceptingRegularRolls = true;
    }

    private function newRoll($pinCount): Roll
    {
        if ($this->isAcceptingOnlyBonusRolls()) {
            return new BonusRoll($this->rolls, $pinCount);
        }

        if (count($this->rolls) == 0) {
            return new FirstRoll($pinCount);
        }

        if (count($this->rolls) == 1) {
            return new SecondRoll($this->rolls[0], $pinCount);
        }
    }
}

abstract class Roll
{
    private $pinCount;

    public function __construct($pinCount)
    {
        if ($pinCount < 0) {
            throw new InvalidRoll('Roll values must be greater than zero');
        }

        if ($pinCount > 10) {
            throw new InvalidRoll('Roll values must be less than ten');
        }

        $this->pinCount = $pinCount;
    }

    public function pinCount()
    {
        return $this->pinCount;
    }
}

class FirstRoll extends Roll
{
}

class SecondRoll extends Roll
{
    public function __construct($firstRoll, $pinCount)
    {
        if (($firstRoll->pinCount() + $pinCount) > 10) {
            throw new InvalidRoll('Sum of regular rolls can\'t be greater 10');
        }

        parent::__construct($pinCount);
    }
}

class BonusRoll extends Roll
{
    public function __construct($rolls, $pinCount)
    {
        if (($this->sumOfBonusRolls($rolls) + $pinCount) > $this->allowedPinCount($rolls)) {
            throw new InvalidRoll('The sum of bonus rolls can\'t be greater than ' . $this->allowedPinCount($rolls));
        }

        parent::__construct($pinCount);
    }

    private function sumOfBonusRolls($rolls)
    {
        return array_reduce($this->bonusRolls($rolls), function ($bonusRollTotal, $roll) {
            return $bonusRollTotal + $roll->pinCount();
        }, 0);
    }

    private function allowedPinCount($rolls)
    {
        $bonusRolls = $this->bonusRolls($rolls);

        if (count($bonusRolls) == 0) {
            return 10;
        }

        return $bonusRolls[0]->pinCount() < 10 ? 10 : 20;
    }

    private function bonusRolls($rolls)
    {
        return array_values(array_filter($rolls, function ($roll) {
            return $roll instanceof BonusRoll;
        }));
    }
}

class InvalidRoll extends Exception
{
}

class CantScore extends Exception
{
    protected $message = "Game can't be scored.";
}

class GameIsOver extends Exception
{
    protected $message = "The Game is Over!";
}