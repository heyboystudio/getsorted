<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\Schemas;

use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
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
                    TextEntry::make('service.name')->label(__('Service')),
                    TextEntry::make('service.trade.name')->label(__('Trade')),
                    TextEntry::make('status')->label(__('Status'))->badge()->formatStateUsing(fn ($state): string => __(str($state->value)->replace('_', ' ')->ucfirst()->toString())),
                    TextEntry::make('urgency')->label(__('Urgency'))->formatStateUsing(fn ($state): string => __(ucfirst($state->value))),
                    TextEntry::make('property.suburb.name')->label(__('Suburb'))->placeholder('—'),
                    TextEntry::make('time_window')->label(__('When'))
                        ->formatStateUsing(fn ($state, ServiceJob $record): string => $state->label().($record->preferred_date ? ', '.$record->preferred_date->format('D j M') : ''))
                        ->placeholder('—'),
                    TextEntry::make('posted_at')->label(__('Posted'))->dateTime('j M Y H:i')->placeholder('—'),
                    TextEntry::make('quote_window_ends_at')->label(__('Quotes close'))->dateTime('j M Y H:i')->placeholder('—'),
                ]),
                Section::make(__('Answers'))->schema([
                    TextEntry::make('answers')->hiddenLabel()
                        ->state(fn (ServiceJob $record): array => array_map(
                            fn (array $answer): string => $answer['prompt'].' — '.(is_array($answer['answer']) ? implode(', ', $answer['answer']) : (string) $answer['answer']),
                            $record->orderedAnswers(),
                        ))
                        ->listWithLineBreaks()->placeholder(__('No answers yet')),
                    TextEntry::make('customer_notes')->label(__('Customer notes'))->placeholder('—'),
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
