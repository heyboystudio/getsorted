<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true, earlyReturn: true)
    ->withSkip([
        // Laravel inspects policy signatures (e.g. guest handling) and calls middleware with fixed arguments.
        RemoveUnusedPublicMethodParameterRector::class => [__DIR__.'/app/Policies', __DIR__.'/app/Http/Middleware'],
    ]);
