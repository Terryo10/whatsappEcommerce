<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliverySlotResource\Pages;
use App\Models\DeliverySlot;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliverySlotResource extends Resource
{
    protected static ?string $model = DeliverySlot::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static string|\UnitEnum|null $navigationGroup = 'Delivery';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->required(),
            TextInput::make('label')->required()->placeholder('Morning 7am–12pm'),
            Grid::make(2)->schema([
                TimePicker::make('time_from')->required()->seconds(false),
                TimePicker::make('time_to')->required()->seconds(false),
            ]),
            Grid::make(2)->schema([
                TextInput::make('max_orders')->numeric()->default(20)->required(),
                TextInput::make('current_orders')->numeric()->default(0)->disabled(),
            ]),
            Toggle::make('is_available')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')->date('D d M Y')->sortable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('time_from')->time('H:i'),
                TextColumn::make('time_to')->time('H:i'),
                TextColumn::make('current_orders')
                    ->label('Bookings')
                    ->formatStateUsing(fn (DeliverySlot $record): string => "{$record->current_orders}/{$record->max_orders}"),
                IconColumn::make('is_available')->boolean()->label('Open'),
            ])
            ->defaultSort('date')
            ->filters([
                Filter::make('upcoming')
                    ->query(fn (Builder $query): Builder => $query->where('date', '>=', today()))
                    ->default(),
                Filter::make('available')
                    ->query(fn (Builder $query): Builder => $query->where('is_available', true)),
            ])
            ->recordActions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliverySlots::route('/'),
            'create' => Pages\CreateDeliverySlot::route('/create'),
            'edit' => Pages\EditDeliverySlot::route('/{record}/edit'),
        ];
    }
}
