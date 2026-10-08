<?php

declare(strict_types=1);
use App\Contracts\Data\PaymentEventType;

arch('domain does not depend on UI')
    ->expect('App\Domain')
    ->not->toUse(['App\Filament', 'App\Livewire', 'App\Http']);

// The rest of the app reaches integrations only through a contract; an adapter may use its own helpers only.
arch('integrations only reached through contracts')
    ->expect('App\Integrations')
    ->toOnlyBeUsedIn(['App\Providers', 'App\Integrations', 'Tests']);

arch('fakes are never used by real adapters')
    ->expect('App\Integrations\Fakes')
    ->toOnlyBeUsedIn(['App\Providers', 'Tests']);

arch('the Anthropic adapter keeps its helpers to itself')
    ->expect('App\Integrations\Anthropic')
    ->toOnlyBeUsedIn(['App\Providers', 'App\Integrations\Anthropic', 'Tests']);

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('strict types')->expect('App')->toUseStrictTypes();

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces()
    ->ignoring(['App\Contracts\Data', 'App\Contracts\Exceptions']);

arch('contract data objects are immutable')
    ->expect('App\Contracts\Data')
    ->classes()
    ->toBeReadonly()
    ->toBeFinal()
    ->ignoring([PaymentEventType::class]);

arch('fakes implement a contract')
    ->expect('App\Integrations\Fakes')
    ->toBeFinal()
    ->toHavePrefix('Fake');
