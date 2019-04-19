<?php

class Game
{
    /** @var array Frame */
    private $frames;

    public function __construct()
    {
        $this->frames = [
            new Frame(1),
            new Frame(2),
            new Frame(3),
            new Frame(4),
            new Frame(5),
            new Frame(6),
            new Frame(7),
            new Frame(8),
            new Frame(9),
            new TenthFrame(10),
        ];
    }

    public function roll($pinCount)
    {
        $currentFrame = $this->currentFrame();
        if (!$currentFrame) {
            throw new GameOver;
        }

        $currentFrame->roll($pinCount);
    }

    public function score()
    {
        if ($this->currentFrame()) {
            throw new CannotScoreWhileInProgress;
        }

        return array_reduce($this->frames, function ($score, $frame) {
            return $score + $this->getScoreStrategy($frame)->score($frame);
        }, 0);
    }

    private function currentFrame()
    {
        foreach ($this->frames as $frame) {
            if ($frame->acceptsRolls()) {
                return $frame;
            }
        }
    }

    private function getScoreStrategy($frame)
    {
        switch ($frame->type()) {
            case Frame::SPARE:
                return new FrameWithBonusRollsScoringStrategy([$this->nextFrame($frame)->firstRoll()]);
            case Frame::STRIKE:
                $nextFrame = $this->nextFrame($frame);
                $bonusRolls = [$nextFrame->firstRoll()];
                $bonusRolls[] = $nextFrame->isStrike() ? $this->nextFrame($nextFrame)->firstRoll() : $nextFrame->secondRoll();
                return new FrameWithBonusRollsScoringStrategy($bonusRolls);
            default:
                return new OpenFrameScoringStrategy;
        }
    }

    public function nextFrame(Frame $frame): Frame
    {
        return $this->frames[$frame->number()];
    }
}

class Frame
{
    const TOTAL_PINS = 10;
    const TENTH = 'tenth';

    const OPEN = 'open';
    const SPARE = 'spare';
    const STRIKE = 'strike';

    protected $rolls = [];
    private $number;
    protected $type = Frame::OPEN;

    public function __construct($number)
    {
        $this->number = $number;
    }

    public function roll($pinCount)
    {
        if (!$this->isValidRoll($pinCount)) {
            throw new InvalidRoll;
        }

        $this->rolls[] = $pinCount;

        $this->markFrame();
    }

    protected function isValidRoll($pinCount)
    {
        if ($pinCount < 0 || $pinCount > 10) {
            return false;
        }

        return $this->totalPins() + $pinCount <= 10;
    }

    public function acceptsRolls()
    {
        return !$this->isStrike() && count($this->rolls) < 2;
    }

    public function totalPins()
    {
        return array_sum($this->rolls);
    }

    public function number()
    {
        return $this->number;
    }

    public function firstRoll()
    {
        return $this->rolls[0] ?? null;
    }

    public function secondRoll()
    {
        return $this->rolls[1] ?? null;
    }

    protected function markFrame()
    {
        if ($this->isSpare()) {
            $this->type = Frame::SPARE;
            return;
        }

        if ($this->isStrike()) {
            $this->type = Frame::STRIKE;
            return;
        }

        $this->type = Frame::OPEN;
    }

    public function type()
    {
        return $this->type;
    }

    public function isSpare()
    {
        return count($this->rolls) == 2 && $this->totalPins() == Frame::TOTAL_PINS;
    }

    public function isStrike()
    {
        return Frame::TOTAL_PINS == ($this->rolls[0] ?? 0);
    }
}

class TenthFrame extends Frame
{
    protected $type = Frame::TENTH;

    public function acceptsRolls()
    {
        $allowedRolls = 2;
        if (array_sum(array_slice($this->rolls, 0, 2)) >= self::TOTAL_PINS) {
            $allowedRolls = 3;
        }
        return count($this->rolls) < $allowedRolls;
    }

    protected function markFrame()
    {
    }

    public function isStrike()
    {
        return false;
    }

    protected function isValidRoll($pinCount)
    {
        if ($this->firstRoll() == self::TOTAL_PINS) {
            if ($this->secondRoll() == self::TOTAL_PINS) {
                return $pinCount <= 10;
            }

            return ($this->secondRoll() ?? 0) + $pinCount <= 10;
        }

        return $pinCount >= 0 && $pinCount <= 10;
    }
}

class OpenFrameScoringStrategy
{
    public function score(Frame $frame)
    {
        return $frame->totalPins();
    }
}

class FrameWithBonusRollsScoringStrategy
{
    private $bonusRolls;

    public function __construct(array $bonusRolls)
    {
        $this->bonusRolls = $bonusRolls;
    }

    public function score(Frame $frame)
    {
        return Frame::TOTAL_PINS + array_sum($this->bonusRolls);
    }
}

class InvalidRoll extends Exception
{
}

class CannotScoreWhileInProgress extends Exception
{
}

class GameOver extends Exception
{
}