<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string|\UnitEnum|null $navigationGroup = 'Orders';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reference')->disabled(),
            Select::make('status')
                ->options([
                    'pending_payment' => 'Pending Payment',
                    'paid' => 'Paid',
                    'preparing' => 'Preparing',
                    'assigned' => 'Driver Assigned',
                    'in_transit' => 'In Transit',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                ]),
            Select::make('driver_id')
                ->label('Assign Driver')
                ->options(Driver::where('is_active', true)->pluck('name', 'id'))
                ->nullable()
                ->searchable(),
            Textarea::make('notes')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable()->weight('bold')->copyable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('channel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success', 'web' => 'info', 'app' => 'warning', default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending_payment' => 'warning',
                        'paid', 'preparing' => 'info',
                        'in_transit', 'assigned' => 'warning',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total')->money('USD')->sortable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success', 'failed' => 'danger', default => 'warning',
                    }),
                TextColumn::make('deliverySlot.label')->label('Slot')->placeholder('—'),
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable()->label('Placed'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('channel')->options(['whatsapp' => 'WhatsApp', 'web' => 'Web', 'app' => 'App']),
                SelectFilter::make('status')->options([
                    'pending_payment' => 'Pending Payment', 'paid' => 'Paid', 'preparing' => 'Preparing',
                    'assigned' => 'Assigned', 'in_transit' => 'In Transit', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
                ]),
                SelectFilter::make('payment_status')->options(['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed']),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('assign_driver')
                    ->label('Assign Driver')
                    ->icon('heroicon-o-truck')
                    ->form([
                        Select::make('driver_id')
                            ->label('Driver')
                            ->options(Driver::where('is_active', true)->where('is_available', true)->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $driver = Driver::find($data['driver_id']);
                        $record->update(['driver_id' => $driver->user_id, 'status' => 'assigned']);
                        Delivery::create([
                            'order_id' => $record->id,
                            'driver_id' => $data['driver_id'],
                            'status' => 'assigned',
                            'assigned_at' => now(),
                        ]);
                    })
                    ->visible(fn (Order $record): bool => in_array($record->status, ['paid', 'preparing'])),
                EditAction::make(),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
