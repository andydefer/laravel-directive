<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\NonDirectives;

final class NotADirective
{
    public function getSignature(): string
    {
        return 'not-a-directive';
    }
}
