<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use App\Settings\MatchingSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

/** Spec 009, AC12: super-admins tune invite waves. */
final class MatchingSettingsPage extends Page
{
    protected static ?string $slug = 'matching-settings';

    public static function getNavigationLabel(): string
    {
        return __('Matching settings');
    }

    public function getTitle(): string
    {
        return __('Matching settings');
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
        $settings = app(MatchingSettings::class);

        $this->settingsForm()->fill([
            'wave_one_size' => $settings->wave_one_size,
            'later_wave_size' => $settings->later_wave_size,
            'wave_interval_hours' => $settings->wave_interval_hours,
            'invite_expiry_hours' => $settings->invite_expiry_hours,
            'enough_quotes' => $settings->enough_quotes,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('wave_one_size')->label(__('Pros invited when a job is posted'))->integer()->required()->minValue(1)->maxValue(20),
            TextInput::make('later_wave_size')->label(__('Pros added in each later wave'))->integer()->required()->minValue(0)->maxValue(20),
            TextInput::make('wave_interval_hours')->label(__('Hours before the next wave'))->integer()->required()->minValue(1)->maxValue(72),
            TextInput::make('invite_expiry_hours')->label(__('Hours a pro has to answer an invite'))->integer()->required()->minValue(1)->maxValue(168),
            TextInput::make('enough_quotes')->label(__('Stop later waves once a job has this many quotes'))->integer()->required()->minValue(1)->maxValue(3),
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

        /** @var array{wave_one_size: int|string, later_wave_size: int|string, wave_interval_hours: int|string, invite_expiry_hours: int|string, enough_quotes: int|string} $state */
        $state = $this->settingsForm()->getState();

        $settings = app(MatchingSettings::class);
        $settings->wave_one_size = (int) $state['wave_one_size'];
        $settings->later_wave_size = (int) $state['later_wave_size'];
        $settings->wave_interval_hours = (int) $state['wave_interval_hours'];
        $settings->invite_expiry_hours = (int) $state['invite_expiry_hours'];
        $settings->enough_quotes = (int) $state['enough_quotes'];
        $settings->save();

        activity()->causedBy(auth()->user())->withProperties($settings->toArray())->log('matching_settings_updated');

        Notification::make()->success()->title(__('Saved'))->send();
    }
}
