<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\Schemas;

use App\Domain\ServiceJobs\Enums\SummarySource;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Shows suburb only — never the street address or customer contact details. */
final class ServiceJobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Job'))->columns(2)->schema([
                    TextEntry::make('trade.name')->label(__('Trade')),
                    TextEntry::make('status')->label(__('Status'))->badge()->formatStateUsing(fn ($state): string => __(str($state->value)->replace('_', ' ')->ucfirst()->toString())),
                    TextEntry::make('urgency')->label(__('Urgency'))->formatStateUsing(fn ($state): string => __(ucfirst($state->value))),
                    TextEntry::make('area_label')->label(__('Area'))->placeholder('—'),
                    TextEntry::make('time_window')->label(__('When'))
                        ->formatStateUsing(fn ($state, ServiceJob $record): string => $state->label().($record->preferred_date ? ', '.$record->preferred_date->format('D j M') : ''))
                        ->placeholder('—'),
                    TextEntry::make('posted_at')->label(__('Posted'))->dateTime('j M Y H:i')->placeholder('—'),
                    TextEntry::make('quote_window_ends_at')->label(__('Quotes close'))->dateTime('j M Y H:i')->placeholder('—'),
                ]),
                Section::make(__('What the customer reported'))->schema([
                    TextEntry::make('facts_list')->hiddenLabel()
                        ->state(fn (ServiceJob $record): array => $record->factTexts())
                        ->badge()->placeholder(__('No facts yet')),
                    TextEntry::make('customer_notes')->label(__('Customer notes'))->placeholder('—'),
                    TextEntry::make('ai_summary')->label(__('Description for pros'))->placeholder('—'),
                    TextEntry::make('ai_summary_source')->label(__('Description source'))->formatStateUsing(fn (SummarySource $state): string => $state->label()),
                ]),
                Section::make(__('Photos'))->schema([
                    ImageEntry::make('job_photos')->hiddenLabel()->height(140)
                        ->state(fn (ServiceJob $record): array => $record->getMedia(ServiceJob::PHOTO_COLLECTION)
                            ->map(fn ($photo): string => $record->photoUrl($photo))->all()),
                ]),
                Section::make(__('Timeline'))->schema([
                    RepeatableEntry::make('events')->hiddenLabel()->schema([
                        TextEntry::make('created_at')->hiddenLabel()->dateTime('j M Y H:i'),
                        TextEntry::make('event_type')->hiddenLabel()
                            ->formatStateUsing(fn (string $state, ServiceJobEvent $record): string => str($state)->replace('_', ' ')->ucfirst().' · '.$record->actor_type->value),
                    ])->columns(2),
                ]),
            ]);
    }
}
