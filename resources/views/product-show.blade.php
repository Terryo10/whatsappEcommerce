<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->name }} | VegShop</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f7fb;
            color: #111827;
        }
        .container {
            max-width: 720px;
            margin: 24px auto;
            padding: 0 16px 32px;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(17, 24, 39, 0.08);
        }
        .image-wrap {
            background: #eef2f7;
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .meta {
            padding: 20px;
        }
        .name {
            font-size: 24px;
            margin: 0 0 8px;
        }
        .price {
            font-size: 22px;
            font-weight: 700;
            color: #0b7a36;
            margin: 0 0 12px;
        }
        .chip {
            display: inline-block;
            background: #eef8f1;
            color: #0b7a36;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            margin-bottom: 14px;
        }
        .desc {
            margin: 0 0 16px;
            line-height: 1.5;
            color: #374151;
        }
        .muted {
            font-size: 13px;
            color: #6b7280;
        }
    </style>
</head>
<body>
<main class="container">
    <article class="card">
        <div class="image-wrap">
            @if($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
            @else
                <span class="muted">No image available</span>
            @endif
        </div>
        <div class="meta">
            <h1 class="name">{{ $product->name }}</h1>
            <p class="price">${{ number_format((float) $product->price_usd, 2) }}</p>
            <div class="chip">{{ $product->category?->name ?? 'Groceries' }}</div>
            <p class="desc">{{ $product->description ?: 'Fresh product delivered to your door.' }}</p>
            <p class="muted">
                Availability:
                {{ ($product->stock_qty > 0 && $product->is_active) ? 'In stock' : 'Out of stock' }}
            </p>
        </div>
    </article>
</main>
</body>
</html>
