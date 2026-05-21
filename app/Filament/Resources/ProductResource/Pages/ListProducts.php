<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\WhatsApp\CatalogSyncService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_all')
                ->label('Sync All to WhatsApp')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sync all products to WhatsApp?')
                ->modalDescription('This will push all active, WhatsApp-visible products to the Meta catalog. Already-synced products will be updated.')
                ->modalSubmitActionLabel('Yes, sync all')
                ->action(function (): void {
                    $service = app(CatalogSyncService::class);
                    $products = Product::where('is_active', true)
                        ->where('is_whatsapp_visible', true)
                        ->get();

                    $success = 0;
                    $failed = 0;

                    foreach ($products as $product) {
                        try {
                            $service->syncProduct($product);
                            $success++;
                        } catch (\Throwable) {
                            $failed++;
                        }
                    }

                    if ($failed === 0) {
                        Notification::make()
                            ->title("All {$success} products synced to WhatsApp!")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title("{$success} synced, {$failed} failed")
                            ->body('Check Sync Logs for details on failed products.')
                            ->warning()
                            ->send();
                    }
                }),

            CreateAction::make(),
        ];
    }
}
