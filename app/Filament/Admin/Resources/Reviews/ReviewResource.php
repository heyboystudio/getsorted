<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Reviews;

use App\Filament\Admin\Resources\Reviews\Pages\ListReviews;
use App\Filament\Admin\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Client reviews of pros, newest first; an admin can hide one (spec 025, AC9). */
final class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $slug = 'reviews';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    public static function getModelLabel(): string
    {
        return __('review');
    }

    public static function getNavigationLabel(): string
    {
        return __('Reviews');
    }

    public static function getNavigationGroup(): string
    {
        return __('Pros');
    }

    /** @return Builder<Review> */
    public static function getEloquentQuery(): Builder
    {
        return Review::query()->with(['pro', 'customer']);
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListReviews::route('/')];
    }
}
