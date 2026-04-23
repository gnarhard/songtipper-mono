<?php

declare(strict_types=1);

use Illuminate\Routing\UrlGenerator;

it('does not force the URL scheme outside the production environment', function () {
    expect(app()->environment('production'))->toBeFalse();

    // Inspect the URL generator directly: if AppServiceProvider::boot() called
    // URL::forceScheme('https') in non-production, this property would be set.
    // The production guard means it should remain null in the testing env.
    $generator = app('url');
    expect($generator)->toBeInstanceOf(UrlGenerator::class);

    $reflection = new ReflectionProperty($generator, 'forceScheme');
    $forcedScheme = $reflection->getValue($generator);

    expect($forcedScheme)->toBeNull();
});
