<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order Details')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('reference')->weight('bold'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('channel')->badge(),
                    TextEntry::make('customer.name')->label('Customer'),
                    TextEntry::make('customer.whatsapp_phone')->label('WhatsApp'),
                    TextEntry::make('created_at')->dateTime('d M Y H:i'),
                ]),
            ]),
            Section::make('Order Items')->schema([
                RepeatableEntry::make('items')->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('product_name')->label('Product'),
                        TextEntry::make('quantity'),
                        TextEntry::make('unit_price')->money('USD'),
                        TextEntry::make('subtotal')->money('USD')->weight('bold'),
                    ]),
                ]),
            ]),
            Section::make('Payment & Delivery')->schema([
                Grid::make(3)->schema([
                    TextEntry::make('subtotal')->money('USD'),
                    TextEntry::make('delivery_fee')->money('USD'),
                    TextEntry::make('total')->money('USD')->weight('bold'),
                    TextEntry::make('payment_method')->badge(),
                    TextEntry::make('payment_status')->badge(),
                    TextEntry::make('ecocash_number')->label('EcoCash #')->placeholder('—'),
                    TextEntry::make('deliveryAddress.suburb')->label('Suburb')->placeholder('—'),
                    TextEntry::make('deliverySlot.label')->label('Slot')->placeholder('—'),
                    TextEntry::make('tracking_token')->label('Tracking Token')->copyable()->placeholder('—'),
                ]),
            ]),
        ]);
    }
}
