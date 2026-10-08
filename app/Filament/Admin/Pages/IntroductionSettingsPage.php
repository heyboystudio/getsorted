<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use App\Settings\IntroductionSettings;
use App\Support\AppMode;
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

/** Spec 023, AC14: super-admins switch the introduction fee on or off and set its amount, the free allowance and the credit packs. */
final class IntroductionSettingsPage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'introduction-settings';

    public static function getNavigationLabel(): string
    {
        return __('Introduction fee');
    }

    public function getTitle(): string
    {
        return __('Introduction fee');
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
        $settings = app(IntroductionSettings::class);

        $this->settingsForm()->fill([
            'fee_enabled' => $settings->fee_enabled,
            'fee_rand' => intdiv($settings->fee_cents, 100),
            'free_introductions' => $settings->free_introductions,
            'packs_rand' => implode(', ', array_map(fn (int $cents): int => intdiv($cents, 100), $settings->credit_pack_cents)),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Toggle::make('fee_enabled')->label(__('Charge pros a fee when a client chooses them'))->helperText(__('Off at launch. Switch on only after the live PayFast account is set up on the server.')),
            TextInput::make('fee_rand')->label(__('Fee per introduction (rand)'))->integer()->required()->minValue(1)->maxValue(5000),
            TextInput::make('free_introductions')->label(__('Free introductions for each pro, once the fee is on'))->integer()->required()->minValue(0)->maxValue(1000),
            TextInput::make('packs_rand')->label(__('Credit packs pros can buy (rand, separated by commas)'))->required()->helperText(__('For example 297, 495, 990')),
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

        /** @var array{fee_enabled: bool, fee_rand: int|string, free_introductions: int|string, packs_rand: string} $state */
        $state = $this->settingsForm()->getState();

        $packs = array_values(array_filter(array_map(fn (string $pack): int => (int) trim($pack) * 100, explode(',', $state['packs_rand'])), fn (int $cents): bool => $cents > 0));

        if ($packs === [] || min($packs) < IntroductionSettings::MIN_PACK_CENTS) {
            Notification::make()->danger()->title(__('Add at least one credit pack of R5 or more (PayFast does not take smaller payments), e.g. 297, 495, 990'))->send();

            return;
        }

        if ($state['fee_enabled'] && ! AppMode::usesFakeIntegrations() && ! filled(config('services.payfast.merchant_id'))) {
            Notification::make()->danger()->title(__('PayFast is not set up on this server yet, so the fee cannot be switched on'))->send();

            return;
        }

        $settings = app(IntroductionSettings::class);
        $settings->fee_enabled = (bool) $state['fee_enabled'];
        $settings->fee_cents = (int) $state['fee_rand'] * 100;
        $settings->free_introductions = (int) $state['free_introductions'];
        $settings->credit_pack_cents = $packs;
        $settings->save();

        activity()->causedBy(auth()->user())->withProperties($settings->toArray())->log('introduction_settings_updated');

        Notification::make()->success()->title(__('Saved'))->send();
    }
}
