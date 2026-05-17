<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatalogSyncLogResource\Pages;
use App\Models\CatalogSyncLog;
use App\Services\WhatsApp\CatalogSyncService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CatalogSyncLogResource extends Resource
{
    protected static ?string $model = CatalogSyncLog::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Sync Logs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('Product')->searchable()->placeholder('(deleted)'),
                TextColumn::make('action')->badge()->color(fn (string $state): string => match ($state) {
                    'create' => 'success', 'update' => 'info', 'delete' => 'danger', default => 'gray',
                }),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'success' => 'success', 'failed' => 'danger', default => 'warning',
                }),
                TextColumn::make('error_message')->label('Error')->limit(60)->placeholder('—')->color('danger'),
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable()->label('Time'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(['success' => 'Success', 'failed' => 'Failed', 'pending' => 'Pending']),
                SelectFilter::make('action')->options(['create' => 'Create', 'update' => 'Update', 'delete' => 'Delete', 'bulk_sync' => 'Bulk Sync']),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (CatalogSyncLog $record): bool => $record->status === 'failed' && $record->product !== null)
                    ->action(function (CatalogSyncLog $record): void {
                        try {
                            app(CatalogSyncService::class)->syncProduct($record->product);
                            Notification::make()->title('Retry successful!')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Retry failed: ' . $e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogSyncLogs::route('/'),
        ];
    }
}
