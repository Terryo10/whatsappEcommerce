<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverResource\Pages;
use App\Models\Driver;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static string|\UnitEnum|null $navigationGroup = 'Delivery';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('User Account')
                ->options(User::pluck('name', 'id'))
                ->required()
                ->searchable(),
            TextInput::make('name')->required(),
            TextInput::make('phone')->required()->tel(),
            Grid::make(2)->schema([
                Select::make('vehicle_type')
                    ->options(['motorbike' => 'Motorbike', 'car' => 'Car', 'bicycle' => 'Bicycle', 'bakkie' => 'Bakkie'])
                    ->default('motorbike')
                    ->required(),
                TextInput::make('vehicle_plate')->maxLength(20)->nullable(),
            ]),
            Grid::make(2)->schema([
                Toggle::make('is_active')->default(true),
                Toggle::make('is_available')->default(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone'),
                TextColumn::make('vehicle_type')->badge(),
                TextColumn::make('vehicle_plate')->placeholder('—'),
                TextColumn::make('deliveries_count')->counts('deliveries')->label('Total Deliveries'),
                TextColumn::make('last_location_update')->dateTime('d M H:i')->label('Last GPS')->placeholder('Never'),
                ToggleColumn::make('is_active')->label('Active'),
                ToggleColumn::make('is_available')->label('Available'),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                TernaryFilter::make('is_available'),
            ])
            ->recordActions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
