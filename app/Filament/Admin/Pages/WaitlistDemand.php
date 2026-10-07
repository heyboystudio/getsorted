<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Models\WaitlistEntry;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

final class WaitlistDemand extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 1;

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
