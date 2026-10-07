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
            ->join('trades', 'trades.id', '=', 'waitlist_entries.trade_id')
            ->selectRaw('coalesce(waitlist_entries.area_label, ?) as area_name, trades.name as trade_name, count(*) as total', [__('Other area')])
            ->groupBy('waitlist_entries.area_label', 'trades.name')
            ->orderByDesc('total')
            ->get();
    }
}
