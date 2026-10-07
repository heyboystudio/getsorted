<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProApplications;

use App\Filament\Admin\Resources\ProApplications\Pages\ListProApplications;
use App\Filament\Admin\Resources\ProApplications\Pages\ViewProApplication;
use App\Filament\Admin\Resources\ProApplications\RelationManagers\DocumentsRelationManager;
use App\Filament\Admin\Resources\ProApplications\RelationManagers\ReferencesRelationManager;
use App\Filament\Admin\Resources\ProApplications\Schemas\ProApplicationInfolist;
use App\Filament\Admin\Resources\ProApplications\Tables\ProApplicationsTable;
use App\Models\Pro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The vetting queue (spec 008, AC7–AC11). Only vetting and super admins can
 * open it (ProPolicy); every change goes through a domain action.
 */
final class ProApplicationResource extends Resource
{
    protected static ?string $model = Pro::class;

    protected static ?string $slug = 'applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'business_name';

    public static function getModelLabel(): string
    {
        return __('application');
    }

    public static function getNavigationLabel(): string
    {
        return __('Applications');
    }

    public static function getNavigationGroup(): string
    {
        return __('Pros');
    }

    /**
     * An admin who is also a pro never sees their own application here (security review).
     *
     * @return Builder<Pro>
     */
    public static function getEloquentQuery(): Builder
    {
        return Pro::query()->where('user_id', '!=', auth()->id())->with(['user', 'trades']);
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('viewVetting', $record) === true;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProApplicationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            ReferencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProApplications::route('/'),
            'view' => ViewProApplication::route('/{record}'),
        ];
    }
}
