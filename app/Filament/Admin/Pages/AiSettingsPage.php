<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use App\Settings\AiSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Spec 007: super-admins switch the AI assistant on or off and set its limits (founder decisions 1 and 2). */
final class AiSettingsPage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ai-settings';

    public static function getNavigationLabel(): string
    {
        return __('AI settings');
    }

    public function getTitle(): string
    {
        return __('AI settings');
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole(Role::AdminSuper->value);
    }

    public function mount(): void
    {
        $settings = app(AiSettings::class);

        $this->settingsForm()->fill([
            'enabled' => $settings->enabled,
            'suggestion_min_confidence' => $settings->suggestion_min_confidence,
            'daily_call_budget' => $settings->daily_call_budget,
            'usage_retention_days' => $settings->usage_retention_days,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Toggle::make('enabled')->label(__('Assistant switched on'))
                ->helperText(__('Keep off until the privacy notice names the AI provider and its data processing terms are accepted.')),
            TextInput::make('suggestion_min_confidence')->label(__('Minimum confidence for a service suggestion'))
                ->numeric()->required()->minValue(0)->maxValue(1)->step(0.05),
            TextInput::make('daily_call_budget')->label(__('Calls per day (Durban time)'))
                ->integer()->required()->minValue(0)->maxValue(100_000),
            TextInput::make('usage_retention_days')->label(__('Keep usage records for (days)'))
                ->integer()->required()->minValue(1)->maxValue(365),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label(__('Save'))->submit('save')])]),
        ]);
    }

    private function settingsForm(): Schema
    {
        $form = $this->getSchema('form');
        abort_unless($form instanceof Schema, 500);

        return $form;
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);

        /** @var array{enabled: bool, suggestion_min_confidence: float|string, daily_call_budget: int|string, usage_retention_days: int|string} $state */
        $state = $this->settingsForm()->getState();

        $settings = app(AiSettings::class);
        $settings->enabled = (bool) $state['enabled'];
        $settings->suggestion_min_confidence = (float) $state['suggestion_min_confidence'];
        $settings->daily_call_budget = (int) $state['daily_call_budget'];
        $settings->usage_retention_days = (int) $state['usage_retention_days'];
        $settings->save();

        activity()->causedBy(auth()->user())->withProperties([
            'enabled' => $settings->enabled,
            'suggestion_min_confidence' => $settings->suggestion_min_confidence,
            'daily_call_budget' => $settings->daily_call_budget,
            'usage_retention_days' => $settings->usage_retention_days,
        ])->log('ai_settings_updated');

        Notification::make()->success()->title(__('Saved'))->send();
    }
}
