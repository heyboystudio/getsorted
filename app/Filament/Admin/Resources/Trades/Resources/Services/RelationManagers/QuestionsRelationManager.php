<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Resources\Services\RelationManagers;

use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\Catalogue\Support\CatalogueDefinitionValidator;
use App\Filament\Admin\Support\CatalogueFields;
use App\Models\ScopingQuestion;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Scoping questions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('prompt')->label(__('Question'))->required()->maxLength(300)->rows(2)->columnSpanFull(),
                CatalogueFields::key(fn () => ScopingQuestion::query()->where('service_id', $this->getOwnerRecord()->getKey())),
                Select::make('type')
                    ->label(__('Answer type'))
                    ->options(collect(QuestionType::cases())->mapWithKeys(fn (QuestionType $type): array => [$type->value => $type->label()])->all())
                    ->required()
                    ->live(),
                TagsInput::make('options')
                    ->label(__('Options'))
                    ->helperText(__('Press Enter after each option.'))
                    ->visible(fn (Get $get): bool => $this->type($get)?->hasOptions() ?? false)
                    ->live()
                    ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $type = $this->type($get);
                        $problem = $type instanceof QuestionType ? CatalogueDefinitionValidator::optionsProblem($type, array_values((array) $value)) : null;

                        if ($problem !== null) {
                            $fail($problem);
                        }
                    }]),
                Toggle::make('required')->label(__('Answer required')),
                TagsInput::make('flags.urgent_if')
                    ->label(__('Mark the job urgent if the answer is'))
                    ->helperText(__('Must match an option exactly (or yes / no for yes-no questions).'))
                    ->visible(fn (Get $get): bool => ($this->type($get)?->hasOptions() ?? false) || $this->type($get) === QuestionType::YesNo)
                    ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $type = $this->type($get);
                        $options = $type?->hasOptions() ? array_values((array) $get('options')) : [];
                        $problem = $type instanceof QuestionType ? CatalogueDefinitionValidator::urgentIfProblem($type, $options, array_values((array) $value)) : null;

                        if ($problem !== null) {
                            $fail($problem);
                        }
                    }]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('prompt')
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('prompt')->label(__('Question'))->wrap(),
                TextColumn::make('key')->label(__('Key'))->fontFamily('mono')->color('gray'),
                TextColumn::make('type')->label(__('Type'))->badge()->formatStateUsing(fn (QuestionType $state): string => $state->label()),
                IconColumn::make('required')->label(__('Required'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data): array => $this->normalise($data)),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data): array => $this->normalise($data)),
                DeleteAction::make(),
            ]);
    }

    private function type(Get $get): ?QuestionType
    {
        $value = $get('type');

        return $value instanceof QuestionType ? $value : QuestionType::tryFrom((string) $value);
    }

    /**
     * Options only for choice questions; flags only when set.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        $type = QuestionType::from((string) ($data['type'] instanceof QuestionType ? $data['type']->value : $data['type']));
        $data['options'] = $type->hasOptions() ? array_values($data['options'] ?? []) : [];
        $urgentIf = array_values($data['flags']['urgent_if'] ?? []);
        $data['flags'] = $urgentIf === [] ? [] : ['urgent_if' => $urgentIf];

        $data['sort'] ??= 0;

        return $data;
    }
}
