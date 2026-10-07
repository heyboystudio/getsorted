<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProChangeRequests;

use App\Filament\Admin\Resources\ProChangeRequests\Pages\ListProChangeRequests;
use App\Filament\Admin\Resources\ProChangeRequests\Tables\ProChangeRequestsTable;
use App\Models\ProChangeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Changes approved pros ask for: new services and new or renewed registrations (spec 021, AC27).
 * Only vetting and super admins open it (ProChangeRequestPolicy); decisions go through DecideProChange.
 */
final class ProChangeRequestResource extends Resource
{
    protected static ?string $model = ProChangeRequest::class;

    protected static ?string $slug = 'pro-changes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    public static function getModelLabel(): string
    {
        return __('profile change');
    }

    public static function getNavigationLabel(): string
    {
        return __('Profile changes');
    }

    public static function getNavigationGroup(): string
    {
        return __('Pros');
    }

    /**
     * An admin who is also a pro never sees their own requests here, as with applications.
     *
     * @return Builder<ProChangeRequest>
     */
    public static function getEloquentQuery(): Builder
    {
        return ProChangeRequest::query()
            ->whereHas('pro', fn (Builder $pro): Builder => $pro->where('user_id', '!=', auth()->id()))
            ->with(['pro', 'service', 'media']);
    }

    public static function table(Table $table): Table
    {
        return ProChangeRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListProChangeRequests::route('/')];
    }
}
