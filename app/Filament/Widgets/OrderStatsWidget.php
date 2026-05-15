<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayOrders = Order::whereDate('created_at', today())->count();
        $todayRevenue = Order::whereDate('created_at', today())->where('payment_status', 'paid')->sum('total');
        $weekRevenue = Order::where('created_at', '>=', now()->startOfWeek())->where('payment_status', 'paid')->sum('total');
        $pendingOrders = Order::whereIn('status', ['paid', 'preparing', 'assigned', 'in_transit'])->count();
        $lowStockCount = Product::where('is_active', true)->whereColumn('stock_qty', '<=', 'low_stock_threshold')->count();

        $whatsappToday = Order::whereDate('created_at', today())->where('channel', 'whatsapp')->count();
        $webToday = Order::whereDate('created_at', today())->where('channel', 'web')->count();
        $appToday = Order::whereDate('created_at', today())->where('channel', 'app')->count();

        return [
            Stat::make('Orders Today', $todayOrders)
                ->description("WA: {$whatsappToday} | Web: {$webToday} | App: {$appToday}")
                ->icon('heroicon-o-shopping-cart')
                ->color('primary'),

            Stat::make('Revenue Today', '$' . number_format($todayRevenue, 2))
                ->description('Week: $' . number_format($weekRevenue, 2))
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Active Orders', $pendingOrders)
                ->description('Paid / Preparing / In Transit')
                ->icon('heroicon-o-truck')
                ->color('warning'),

            Stat::make('Low Stock Alert', $lowStockCount . ' products')
                ->description('Below threshold')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}
