<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Models\WaitlistEntry;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

final class WaitlistDemand extends Page
{
    protected string $view = 'filament.admin.pages.waitlist-demand';

    protected static ?string $navigationLabel = 'Waitlist demand';

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User && auth()->user()->isAdmin();
    }

    /** @return Collection<int, WaitlistEntry> */
    public function demand(): Collection
    {
        return WaitlistEntry::query()
            ->join('services', 'services.id', '=', 'waitlist_entries.service_id')
            ->leftJoin('suburbs', 'suburbs.id', '=', 'waitlist_entries.suburb_id')
            ->selectRaw('coalesce(suburbs.name, ?) as suburb_name, services.name as service_name, count(*) as total', [__('Other suburb')])
            ->groupBy('suburbs.name', 'services.name')
            ->orderByDesc('total')
            ->get();
    }
}
