<article class="sx-order-card">
    <div class="sx-order-card-head">
        <strong>{{ $order['number'] }}</strong>
        <span>{{ $order['held_at'] }}</span>
    </div>
    <div class="sx-order-card-meta">Customer: {{ $order['customer'] }}</div>
    <ul class="sx-order-card-items">
        @foreach($order['items'] as $item)
            <li>
                <span>{{ $item['name'] }}</span>
                <strong>{{ $item['qty'] }}</strong>
            </li>
        @endforeach
    </ul>
    <div class="sx-order-card-foot">
        <span>Ksh {{ $order['total'] }}</span>
        <a href="{{ $order['resume_url'] }}">Open in POS</a>
    </div>
</article>
