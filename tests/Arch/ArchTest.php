<?php

declare(strict_types=1);

arch('domain does not depend on UI')
    ->expect('App\Domain')
    ->not->toUse(['App\Filament', 'App\Livewire', 'App\Http']);

arch('integrations only reached through contracts')
    ->expect('App\Integrations')
    ->toOnlyBeUsedIn(['App\Providers', 'Tests']);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('strict types')->expect('App')->toUseStrictTypes();
