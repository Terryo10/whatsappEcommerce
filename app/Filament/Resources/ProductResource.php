<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use App\Services\WhatsApp\CatalogSyncService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Actions\Action as TableAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->schema([
                Grid::make(1)->columnSpan(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Textarea::make('description')->rows(3),
                    Grid::make(2)->schema([
                        TextInput::make('price_usd')->label('Price (USD)')->numeric()->prefix('$')->required(),
                        TextInput::make('price_zwl')->label('Price (ZWL)')->numeric()->prefix('ZWL')->default(0),
                    ]),
                    Grid::make(3)->schema([
                        Select::make('category_id')
                            ->label('Category')
                            ->options(Category::pluck('name', 'id'))
                            ->required(),
                        Select::make('unit')
                            ->options(['kg' => 'Kilogram (kg)', 'bunch' => 'Bunch', 'piece' => 'Piece', 'g' => 'Grams', 'litre' => 'Litre'])
                            ->default('piece')
                            ->required(),
                        TextInput::make('stock_qty')->label('Stock Qty')->numeric()->default(0)->required(),
                    ]),
                    TextInput::make('low_stock_threshold')->numeric()->default(5)->label('Low Stock Alert Threshold'),
                ]),
                Grid::make(1)->columnSpan(1)->schema([
                    FileUpload::make('image_url')->label('Product Image')->image()->directory('products'),
                    Toggle::make('is_active')->label('Active')->default(true),
                    Toggle::make('is_whatsapp_visible')->label('Show on WhatsApp')->default(true),
                    TextInput::make('whatsapp_retailer_id')->label('WhatsApp Retailer ID')->disabled()->placeholder('Auto-set on sync'),
                    Select::make('whatsapp_sync_status')
                        ->label('WA Sync Status')
                        ->options(['not_synced' => 'Not Synced', 'pending' => 'Pending', 'synced' => 'Synced', 'failed' => 'Failed'])
                        ->disabled(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')->label('')->imageSize(48),
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('category.name')->label('Category')->badge(),
                TextColumn::make('price_usd')->label('Price')->money('USD')->sortable(),
                TextColumn::make('stock_qty')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn (Product $record): string => $record->isLowStock() ? 'danger' : 'success'),
                TextColumn::make('whatsapp_sync_status')
                    ->label('WA Sync')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'synced' => 'success', 'failed' => 'danger', 'pending' => 'warning', default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'synced' => 'Synced', 'failed' => 'Failed', 'pending' => 'Pending', default => 'Not Synced',
                    })
                    ->description(fn (Product $record): ?string => $record->whatsapp_synced_at
                        ? 'Last synced ' . $record->whatsapp_synced_at->diffForHumans()
                        : null
                    ),
                ToggleColumn::make('is_active')->label('Active'),
                ToggleColumn::make('is_whatsapp_visible')->label('On WA'),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'name'),
                SelectFilter::make('whatsapp_sync_status')->options(['synced' => 'Synced', 'failed' => 'Failed', 'pending' => 'Pending', 'not_synced' => 'Not Synced']),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                TableAction::make('sync_whatsapp')
                    ->label('Sync to WA')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (Product $record): void {
                        try {
                            app(CatalogSyncService::class)->syncProduct($record);
                            Notification::make()->title('Product synced to WhatsApp!')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Sync failed: ' . $e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('bulk_sync')
                        ->label('Sync to WhatsApp')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (Collection $records): void {
                            $service = app(CatalogSyncService::class);
                            $success = 0;
                            foreach ($records as $product) {
                                try { $service->syncProduct($product); $success++; } catch (\Throwable) {}
                            }
                            Notification::make()->title("Synced {$success}/{$records->count()} products")->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
