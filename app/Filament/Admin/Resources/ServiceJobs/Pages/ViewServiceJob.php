<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\Pages;

use App\Domain\Matching\Actions\InviteProManually;
use App\Domain\Matching\Actions\StopMatching;
use App\Domain\Matching\EligibleProsQuery;
use App\Domain\Matching\Exceptions\CannotInvite;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Filament\Admin\Resources\ServiceJobs\ServiceJobResource;
use App\Models\Pro;
use App\Models\ServiceJob;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;

final class ViewServiceJob extends ViewRecord
{
    protected static string $resource = ServiceJobResource::class;

    /** Manual invites and stopping waves, for support and super admins (spec 009, AC11). */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('invitePro')->label(__('Invite a pro'))
                ->visible(fn (): bool => $this->canManageMatching() && $this->job()->status === ServiceJobStatus::Open)
                ->schema([
                    Select::make('pro_id')->label(__('Pro'))->required()->searchable()
                        ->options(fn (): array => $this->invitablePros()),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InviteProManually::class)->handle($this->admin(), $this->job(), Pro::query()->findOrFail($data['pro_id']));
                    } catch (CannotInvite $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        throw new Halt;
                    }

                    Notification::make()->success()->title(__('Invited'))->send();
                }),
            Action::make('stopMatching')->label(__('Stop matching'))->color('danger')
                ->visible(fn (): bool => $this->canManageMatching() && $this->job()->status === ServiceJobStatus::Open && $this->job()->matching_stopped_at === null)
                ->schema([Textarea::make('reason')->label(__('Reason'))->required()->maxLength(500)->rows(2)])
                ->action(function (array $data): void {
                    app(StopMatching::class)->handle($this->admin(), $this->job(), $data['reason']);
                    $this->record = ServiceJob::query()->findOrFail($this->job()->id);
                    Notification::make()->success()->title(__('Matching stopped'))->send();
                }),
        ];
    }

    /** @return array<int, string> eligible pros not invited yet */
    private function invitablePros(): array
    {
        $job = ServiceJob::query()->with(['service.trade', 'property.suburb', 'customer'])->findOrFail($this->job()->id);

        if ($job->property?->suburb === null) {
            return [];
        }

        return app(EligibleProsQuery::class)->for($job->service, $job->property->suburb, $job->customer)
            ->whereDoesntHave('invites', fn ($invites) => $invites->where('service_job_id', $job->id))
            ->orderBy('business_name')->limit(100)->pluck('business_name', 'id')->all();
    }

    private function canManageMatching(): bool
    {
        return $this->admin()->can('manageMatching', $this->job());
    }

    private function job(): ServiceJob
    {
        $record = $this->getRecord();
        abort_unless($record instanceof ServiceJob, 404);

        return $record;
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
