<?php

declare(strict_types=1);
use App\Contracts\Data\MessageChannel;
use App\Contracts\Data\PaymentEventType;

arch('domain does not depend on UI')
    ->expect('App\Domain')
    ->not->toUse(['App\Filament', 'App\Livewire', 'App\Http']);

// An adapter may use its own helper classes; the rest of the app reaches it only through a contract.
arch('integrations only reached through contracts')
    ->expect('App\Integrations')
    ->toOnlyBeUsedIn(['App\Providers', 'App\Integrations', 'Tests']);

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
    ->ignoring([PaymentEventType::class, MessageChannel::class]);

arch('fakes implement a contract')
    ->expect('App\Integrations\Fakes')
    ->toBeFinal()
    ->toHavePrefix('Fake');
