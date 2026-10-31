<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

final class CsrfAtivo extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
