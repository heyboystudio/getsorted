<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use App\Settings\MatchingSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Spec 009, AC12; spec 020: super-admins tune distance matching and the quote cap. */
final class MatchingSettingsPage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 2;

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
            'invite_count' => $settings->invite_count,
            'max_quotes' => $settings->max_quotes,
            'default_radius_km' => $settings->default_radius_km,
            'soft_edge_km' => $settings->soft_edge_km,
            'invite_expiry_hours' => $settings->invite_expiry_hours,
            'urgent_invite_expiry_hours' => $settings->urgent_invite_expiry_hours,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('invite_count')->label(__('Pros invited when a job is posted'))->integer()->required()->minValue(1)->maxValue(30),
            TextInput::make('max_quotes')->label(__('Quotes a job accepts before it is full'))->integer()->required()->minValue(1)->maxValue(10),
            TextInput::make('default_radius_km')->label(__('Default travel radius for pros (km)'))->integer()->required()->minValue(1)->maxValue(50),
            TextInput::make('soft_edge_km')->label(__('Soft edge beyond a pro\'s radius (km)'))->integer()->required()->minValue(0)->maxValue(10),
            TextInput::make('invite_expiry_hours')->label(__('Hours a pro has to answer an invite'))->integer()->required()->minValue(1)->maxValue(168),
            TextInput::make('urgent_invite_expiry_hours')->label(__('Hours a pro has to answer an urgent invite'))->integer()->required()->minValue(1)->maxValue(48),
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

        /** @var array{invite_count: int|string, max_quotes: int|string, default_radius_km: int|string, soft_edge_km: int|string, invite_expiry_hours: int|string, urgent_invite_expiry_hours: int|string} $state */
        $state = $this->settingsForm()->getState();

        $settings = app(MatchingSettings::class);
        $settings->invite_count = (int) $state['invite_count'];
        $settings->max_quotes = (int) $state['max_quotes'];
        $settings->default_radius_km = (int) $state['default_radius_km'];
        $settings->soft_edge_km = (int) $state['soft_edge_km'];
        $settings->invite_expiry_hours = (int) $state['invite_expiry_hours'];
        $settings->urgent_invite_expiry_hours = (int) $state['urgent_invite_expiry_hours'];
        $settings->save();

        activity()->causedBy(auth()->user())->withProperties($settings->toArray())->log('matching_settings_updated');

        Notification::make()->success()->title(__('Saved'))->send();
    }
}
