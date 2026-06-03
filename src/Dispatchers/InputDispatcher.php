<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Dispatchers;

use AndyDefer\Directive\Contracts\InputStrategyInterface;
use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Strategies\Input\ConfirmationStrategy;
use AndyDefer\Directive\Strategies\Input\SimpleQuestionStrategy;
use AndyDefer\Directive\Strategies\Input\UserChoiceStrategy;

/**
 * Task responsible for handling user input interactions.
 *
 * This task uses a strategy pattern to delegate different types of user
 * input (simple questions, confirmations, choice selections) to specialized
 * strategies. It manages the input stream and orchestrates the execution.
 *
 * @example
 * $task = new InputDispatcher();
 * $record = new QuestionRecord('What is your name?');
 * $name = $task->execute($record, InputType::SIMPLE_QUESTION);
 *
 * @author Andy Defer
 */
class InputDispatcher
{
    /**
     * @var array<InputStrategyInterface>
     */
    private array $strategies;

    /**
     * @param resource $inputStream The input stream to read from (default: STDIN)
     */
    public function __construct($inputStream = STDIN)
    {
        $this->strategies = [
            new SimpleQuestionStrategy($inputStream),
            new ConfirmationStrategy($inputStream),
            new UserChoiceStrategy($inputStream),
        ];
    }

    /**
     * Executes the input strategy for the given record and type.
     *
     * Finds the appropriate strategy that supports the input type and
     * delegates the execution. Returns null if no strategy supports the type.
     *
     * @param object    $record The record containing input configuration
     * @param InputType $type   The type of input to perform
     *
     * @return mixed The user input result, or null if no strategy supports the type
     */
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
