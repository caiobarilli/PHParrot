<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('returns a successful response', function (): void {
    get('/')->assertStatus(200);
});
