<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\AiUsage;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/** Spec 007, AC16: daily assistant usage by purpose and outcome. Aggregates only, no customer text. */
final class AiUsageReport extends Page
{
    protected static ?string $slug = 'ai-usage';

    public static function getNavigationLabel(): string
    {
        return __('AI usage');
    }

    public function getTitle(): string
    {
        return __('AI usage');
    }

    protected string $view = 'filament.admin.pages.ai-usage';

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User && auth()->user()->isAdmin();
    }

    /** @return Collection<int, object{day: string, purpose: string, outcome: string, calls: int, input_tokens: int, output_tokens: int, avg_latency_ms: int}> */
    public function usage(): Collection
    {
        /** @var Collection<int, object{day: string, purpose: string, outcome: string, calls: int, input_tokens: int, output_tokens: int, avg_latency_ms: int}> $rows */
        $rows = AiUsage::query()->toBase()
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day")
            ->selectRaw('purpose, outcome, count(*) as calls, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, round(avg(latency_ms)) as avg_latency_ms')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day', 'purpose', 'outcome')
            ->orderByDesc('day')->orderBy('purpose')->orderBy('outcome')
            ->get()
            ->map(fn (object $row): object => (object) [
                'day' => (string) $row->day,
                'purpose' => (string) $row->purpose,
                'outcome' => (string) $row->outcome,
                'calls' => (int) $row->calls,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'avg_latency_ms' => (int) $row->avg_latency_ms,
            ]);

        return $rows;
    }
}
