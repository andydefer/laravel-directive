<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Contracts\InputStrategyInterface;
use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Strategies\Input\ConfirmationStrategy;
use AndyDefer\Directive\Strategies\Input\SimpleQuestionStrategy;
use AndyDefer\Directive\Strategies\Input\UserChoiceStrategy;

class InputTask
{
    /**
     * @var array<InputStrategyInterface>
     */
    private array $strategies;

    public function __construct($inputStream = STDIN)
    {
        $this->strategies = [
            new SimpleQuestionStrategy($inputStream),
            new ConfirmationStrategy($inputStream),
            new UserChoiceStrategy($inputStream),
        ];
    }

    public function execute(object $record, InputType $type): mixed
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($type)) {
                return $strategy->execute($record, $type);
            }
        }

        return null;
    }
}
