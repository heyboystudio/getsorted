<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use App\Settings\PlacesSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

/** Spec 015: super-admins set the daily cap on address searches. */
final class AddressSettingsPage extends Page
{
    protected static ?string $slug = 'address-settings';

    public static function getNavigationLabel(): string
    {
        return __('Address lookup');
    }

    public function getTitle(): string
    {
        return __('Address lookup');
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
        $this->settingsForm()->fill(['daily_session_cap' => app(PlacesSettings::class)->daily_session_cap]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('daily_session_cap')->label(__('Address searches per day (Durban time)'))
                ->helperText(__('After this, forms ask for the address manually until midnight. Each search costs about $0.017 after Google\'s free monthly allowance.'))
                ->integer()->required()->minValue(0)->maxValue(100_000),
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

        /** @var array{daily_session_cap: int|string} $state */
        $state = $this->settingsForm()->getState();

        $settings = app(PlacesSettings::class);
        $settings->daily_session_cap = (int) $state['daily_session_cap'];
        $settings->save();

        activity()->causedBy(auth()->user())->withProperties(['daily_session_cap' => $settings->daily_session_cap])->log('places_settings_updated');

        Notification::make()->success()->title(__('Saved'))->send();
    }
}
