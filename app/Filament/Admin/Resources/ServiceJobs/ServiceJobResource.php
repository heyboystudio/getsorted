<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs;

use App\Filament\Admin\Resources\ServiceJobs\Pages\ListServiceJobs;
use App\Filament\Admin\Resources\ServiceJobs\Pages\ViewServiceJob;
use App\Filament\Admin\Resources\ServiceJobs\RelationManagers\InvitesRelationManager;
use App\Filament\Admin\Resources\ServiceJobs\RelationManagers\QuotesRelationManager;
use App\Filament\Admin\Resources\ServiceJobs\Schemas\ServiceJobInfolist;
use App\Filament\Admin\Resources\ServiceJobs\Tables\ServiceJobsTable;
use App\Models\ServiceJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Posted and draft jobs for admins (spec 005, AC15). View only; never shows
 * the street address or the customer's phone number.
 */
final class ServiceJobResource extends Resource
{
    protected static ?string $model = ServiceJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function getModelLabel(): string
    {
        return __('job');
    }

    public static function getNavigationGroup(): string
    {
        return __('Jobs');
    }

    public static function infolist(Schema $schema): Schema
    {
        return ServiceJobInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceJobsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InvitesRelationManager::class,
            QuotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceJobs::route('/'),
            'view' => ViewServiceJob::route('/{record}'),
        ];
    }
}
