<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\WhatsApp\CatalogSyncService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_whatsapp')
                ->label('Sync to WhatsApp')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action(function (): void {
                    try {
                        app(CatalogSyncService::class)->syncProduct($this->record);
                        Notification::make()->title('Synced to WhatsApp!')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Sync failed: ' . $e->getMessage())->danger()->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}
