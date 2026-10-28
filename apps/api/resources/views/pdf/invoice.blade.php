<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice {{ $order->order_no }}</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #18181b; }
    h1 { font-size: 22px; margin: 0; }
    .muted { color: #71717a; }
    .banner { background: #fef3c7; border: 1px solid #f59e0b; padding: 6px 10px; margin: 14px 0; }
    table { width: 100%; border-collapse: collapse; }
    .lines { margin-top: 16px; }
    .lines th, .lines td { padding: 6px 4px; border-bottom: 1px solid #e4e4e7; text-align: left; }
    .lines th { background: #f4f4f5; }
    .right { text-align: right !important; }
    .total td { font-weight: bold; border-bottom: none; font-size: 13px; }
    .block { margin-top: 18px; }
</style>
</head>
<body>
    <table>
        <tr>
            <td>
                <h1>Mirai</h1>
                <span class="muted">Computers, parts and accessories · Phnom Penh</span>
            </td>
            <td class="right">
                <strong>INVOICE / RECEIPT</strong><br>
                Order {{ $order->order_no }}<br>
                <span class="muted">Paid {{ $order->paid_at?->timezone('Asia/Phnom_Penh')->format('d M Y, H:i') }}</span>
            </td>
        </tr>
    </table>

    @if ($sandbox)
        <div class="banner">Sandbox test — no real money was charged.</div>
    @endif

    <div class="block">
        <strong>Billed to</strong><br>
        {{ $order->customer_name }}<br>
        {{ $order->customer_phone }}
        @if ($order->customer_email)<br>{{ $order->customer_email }}@endif
    </div>

    <table class="lines">
        <tr>
            <th>Item</th>
            <th>SKU</th>
            <th class="right">Unit price</th>
            <th class="right">Qty</th>
            <th class="right">Amount</th>
        </tr>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->product_sku }}</td>
                <td class="right">{{ $item->unit_price }}</td>
                <td class="right">{{ $item->qty }}</td>
                <td class="right">{{ $item->line_total }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="4" class="right">Total ({{ $order->currency }})</td>
            <td class="right">{{ $order->total }}</td>
        </tr>
    </table>

    @if ($payment)
        <div class="block">
            <strong>Payment</strong><br>
            {{ $payment->method->getLabel() }} via ABA PayWay<br>
            Transaction {{ $payment->tran_id }}
            @if ($payment->apv) · Approval {{ $payment->apv }}@endif
        </div>
    @endif

    <p class="block muted">Thank you for shopping with Mirai.</p>
</body>
</html>
