<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProApplications\Pages;

use App\Domain\Pros\Actions\ChangeProStanding;
use App\Domain\Pros\Actions\DecideApplication;
use App\Domain\Pros\Actions\EditProCoverage;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Filament\Admin\Resources\ProApplications\ProApplicationResource;
use App\Models\Pro;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** One application with its vetting decisions (spec 008, AC8–AC11). */
final class ViewProApplication extends ViewRecord
{
    protected static string $resource = ProApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')->label(__('Approve'))->color('success')->requiresConfirmation()
                ->modalDescription(__('The pro starts receiving jobs for verified services in their suburbs.'))
                ->visible(fn (): bool => $this->pro()->status === ProStatus::Submitted)
                ->action(fn () => $this->attempt(fn () => app(DecideApplication::class)->approve($this->admin(), $this->pro()), __('Approved'))),
            Action::make('requestChanges')->label(__('Request changes'))->color('warning')
                ->schema([$this->reasonField()])
                ->visible(fn (): bool => $this->pro()->status === ProStatus::Submitted)
                ->action(fn (array $data) => $this->attempt(fn () => app(DecideApplication::class)->requestChanges($this->admin(), $this->pro(), $data['reason']), __('Changes requested'))),
            Action::make('reject')->label(__('Reject'))->color('danger')
                ->schema([$this->reasonField()])
                ->visible(fn (): bool => $this->pro()->status === ProStatus::Submitted)
                ->action(fn (array $data) => $this->attempt(fn () => app(DecideApplication::class)->reject($this->admin(), $this->pro(), $data['reason']), __('Rejected'))),
            Action::make('suspend')->label(__('Suspend'))->color('danger')
                ->schema([$this->reasonField()])
                ->visible(fn (): bool => $this->pro()->status === ProStatus::Approved)
                ->action(fn (array $data) => $this->attempt(fn () => app(ChangeProStanding::class)->suspend($this->admin(), $this->pro(), $data['reason']), __('Suspended'))),
            Action::make('reinstate')->label(__('Reinstate'))->requiresConfirmation()
                ->visible(fn (): bool => $this->pro()->status === ProStatus::Suspended)
                ->action(fn () => $this->attempt(fn () => app(ChangeProStanding::class)->reinstate($this->admin(), $this->pro()), __('Reinstated'))),
            Action::make('editCoverage')->label(__('Edit services and suburbs'))->color('gray')
                ->fillForm(fn (): array => ['service_ids' => $this->pro()->services->pluck('id')->all(), 'suburb_ids' => $this->pro()->serviceAreas->pluck('id')->all()])
                ->schema([
                    Select::make('service_ids')->label(__('Services'))->multiple()->required()
                        ->options(fn (): array => Service::query()->with('trade')->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn (Service $service): array => [$service->id => $service->trade->name.' · '.$service->name])->all()),
                    Select::make('suburb_ids')->label(__('Suburbs'))->multiple()->required()
                        ->options(fn (): array => Suburb::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                ])
                ->action(fn (array $data) => $this->attempt(fn () => app(EditProCoverage::class)->handle($this->admin(), $this->pro(), $data['service_ids'], $data['suburb_ids']), __('Saved'))),
        ];
    }

    protected function resolveRecord(int|string $key): Model
    {
        return $this->loaded(ProApplicationResource::getEloquentQuery()->where('public_id', $key)->firstOrFail());
    }

    private function loaded(Pro $pro): Pro
    {
        return $pro->load(['documents.media', 'events.actor']);
    }

    private function reasonField(): Textarea
    {
        return Textarea::make('reason')->label(__('Reason (the pro will see this)'))->required()->maxLength(1000)->rows(3);
    }

    /** Runs a vetting action; a refused change is shown as a notification instead of an error page. */
    private function attempt(Closure $action, string $success): void
    {
        try {
            $action();
        } catch (CannotChangeApplication $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw new Halt;
        }

        $this->refreshFormData([]);
        $this->record = $this->loaded(ProApplicationResource::getEloquentQuery()->findOrFail($this->pro()->id));
        Notification::make()->success()->title($success)->send();
    }

    private function pro(): Pro
    {
        $record = $this->getRecord();
        abort_unless($record instanceof Pro, 404);

        return $record;
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
