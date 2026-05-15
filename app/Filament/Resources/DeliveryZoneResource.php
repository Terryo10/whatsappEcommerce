<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryZoneResource\Pages;
use App\Models\DeliveryZone;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';
    protected static string|\UnitEnum|null $navigationGroup = 'Delivery';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TagsInput::make('suburb_keywords')
                ->label('Suburb Keywords')
                ->placeholder('e.g. Borrowdale')
                ->helperText('Add suburb names that map to this zone'),
            Grid::make(3)->schema([
                TextInput::make('base_fee')->numeric()->prefix('$')->default(0)->label('Base Fee (USD)'),
                TextInput::make('per_km_fee')->numeric()->prefix('$')->default(0)->label('Per KM Fee'),
                TextInput::make('free_delivery_above')->numeric()->prefix('$')->nullable()->label('Free Delivery Above ($)'),
            ]),
            Grid::make(2)->schema([
                TextInput::make('estimated_minutes')->numeric()->default(60)->suffix('min')->label('Est. Delivery Time'),
                Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('suburb_keywords')
                    ->label('Suburbs')
                    ->formatStateUsing(fn ($state): string => implode(', ', (array) $state))
                    ->limit(50),
                TextColumn::make('base_fee')->money('USD')->label('Base Fee'),
                TextColumn::make('free_delivery_above')->money('USD')->label('Free Above')->placeholder('—'),
                TextColumn::make('estimated_minutes')->suffix('min')->label('Est. Time'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryZones::route('/'),
            'create' => Pages\CreateDeliveryZone::route('/create'),
            'edit' => Pages\EditDeliveryZone::route('/{record}/edit'),
        ];
    }
}
