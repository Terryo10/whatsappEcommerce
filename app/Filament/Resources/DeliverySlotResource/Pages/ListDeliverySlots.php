<?php

namespace App\Filament\Resources\DeliverySlotResource\Pages;

use App\Filament\Resources\DeliverySlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeliverySlots extends ListRecords
{
    protected static string $resource = DeliverySlotResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
