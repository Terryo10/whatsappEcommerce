<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low Stock Alerts')
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
                    ->orderBy('stock_qty')
            )
            ->columns([
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('category.name')->badge(),
                TextColumn::make('stock_qty')->label('In Stock')->color('danger'),
                TextColumn::make('low_stock_threshold')->label('Threshold'),
                TextColumn::make('unit'),
            ])
            ->emptyStateHeading('All stock levels are fine!')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordActions([
                EditAction::make()
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
