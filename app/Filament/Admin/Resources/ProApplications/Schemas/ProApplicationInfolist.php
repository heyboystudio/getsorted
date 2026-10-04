<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProApplications\Schemas;

use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\ProEvent;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ProApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Business'))->columns(2)->schema([
                TextEntry::make('business_name')->label(__('Business name'))->placeholder('—'),
                TextEntry::make('business_type')->label(__('Trades as'))->formatStateUsing(fn ($state): string => $state->label())->placeholder('—'),
                TextEntry::make('vat_number')->label(__('VAT number'))->placeholder('—'),
                TextEntry::make('user.first_name')->label(__('Applicant'))
                    ->formatStateUsing(fn (string $state, Pro $record): string => $record->user->fullName()),
                TextEntry::make('status')->label(__('Status'))->badge()->formatStateUsing(fn (ProStatus $state): string => $state->label()),
                TextEntry::make('submitted_at')->label(__('Submitted'))->dateTime('j M Y H:i')->placeholder('—'),
                TextEntry::make('decision_reason')->label(__('Reason given to the pro'))->placeholder('—')->columnSpanFull(),
            ]),
            Section::make(__('Services and suburbs'))->schema([
                TextEntry::make('services_list')->label(__('Services'))
                    ->state(fn (Pro $record): string => $record->services->map(fn ($service): string => $service->trade->name.' · '.$service->name)->implode(', ')),
                TextEntry::make('suburbs_list')->label(__('Suburbs'))
                    ->state(fn (Pro $record): string => $record->serviceAreas->pluck('name')->sort()->implode(', ')),
            ]),
            Section::make(__('Profile'))->columns(2)->schema([
                ImageEntry::make('profile_photo')->label(__('Profile photo'))->height(120)
                    ->state(fn (Pro $record): ?string => $record->document(DocumentType::ProfilePhoto)?->file() === null ? null : $record->document(DocumentType::ProfilePhoto)->temporaryUrl()),
                TextEntry::make('bio')->label(__('Bio'))->placeholder('—'),
            ]),
            Section::make(__('History'))->collapsed()->schema([
                RepeatableEntry::make('events')->hiddenLabel()->schema([
                    TextEntry::make('created_at')->hiddenLabel()->dateTime('j M Y H:i'),
                    TextEntry::make('to_status')->hiddenLabel()
                        ->formatStateUsing(fn (ProStatus $state, ProEvent $record): string => $state->label()
                            .' · '.($record->actor?->fullName() ?? __('System'))
                            .($record->reason ? ' · '.$record->reason : '')),
                ])->columns(2),
            ]),
        ]);
    }
}
