<x-mail::message>
# Thank you, {{ $order->customer_name }}

Your order **{{ $order->order_no }}** is paid.

@if ($sandbox)
*Sandbox test — no real money was charged.*
@endif

<x-mail::table>
| Item | Qty | Amount |
| :--- | ---: | ---: |
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->qty }} | {{ $item->line_total }} |
@endforeach
| **Total ({{ $order->currency }})** | | **{{ $order->total }}** |
</x-mail::table>

Your invoice is attached as a PDF.

Thanks,<br>
Mirai
</x-mail::message>
