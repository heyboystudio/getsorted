<?php

declare(strict_types=1);

use App\Casts\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;

function moneyModel(): Model
{
    return new class extends Model
    {
        protected $guarded = [];

        protected function casts(): array
        {
            return ['total_cents' => MoneyCast::class];
        }
    };
}

it('reads integer cents as rand amounts', function (): void {
    $model = moneyModel()->setRawAttributes(['total_cents' => 123456]);

    expect($model->total_cents)->toBeInstanceOf(Money::class)
        ->and((string) $model->total_cents)->toBe('ZAR 1234.56');
});

it('stores Money as exact integer cents', function (): void {
    $model = moneyModel();
    $model->total_cents = Money::of('0.10', 'ZAR')->plus(Money::of('0.20', 'ZAR'));

    expect($model->getAttributes()['total_cents'])->toBe(30);
});

it('keeps null as null', function (): void {
    $model = moneyModel();
    $model->total_cents = null;

    expect($model->getAttributes()['total_cents'])->toBeNull()
        ->and($model->total_cents)->toBeNull();
});

it('refuses floats, integers and strings', function (mixed $value): void {
    moneyModel()->total_cents = $value;
})->with([12.5, 1250, '12.50'])->throws(InvalidArgumentException::class, 'must be set with a Brick\Money\Money instance');

it('refuses other currencies', function (): void {
    moneyModel()->total_cents = Money::of(10, 'USD');
})->throws(InvalidArgumentException::class, 'must be in ZAR');
