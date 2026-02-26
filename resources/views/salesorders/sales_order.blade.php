<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Order - {{ $order->so_number }}</title>
    <style>
        @page { margin: 0.8cm; }
        body { font-family: 'Helvetica', sans-serif; line-height: 1.2; color: #333; font-size: 11px; margin: 0; }
        .container { width: 100%; }
        .header { border-bottom: 2px solid #2c3e50; padding-bottom: 10px; margin-bottom: 15px; }
        .logo-container { float: left; width: 65px; }
        .logo { width: 100%; }
        .company-info { float: left; margin-left: 15px; }
        .company-info b { font-size: 16px; color: #2980b9; }
        .order-details { float: right; text-align: right; }
        .order-details h2 { margin: 0; color: #2c3e50; font-size: 20px; }
        
        .table-info { width: 100%; margin-bottom: 15px; }
        .table-items { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table-items th { background: #2c3e50; color: white; padding: 7px; text-align: left; }
        .table-items td { border: 1px solid #eee; padding: 7px; }
        
        .text-right { text-align: right; }
        .total-section { float: right; width: 250px; }
        .footer { margin-top: 50px; font-size: 9px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <img src="{{ public_path('assets/img/logo amdk.png') }}" class="logo">
        </div>
        <div class="company-info">
            <b>HYDROCORE AMDK</b><br>
            Jl. Rungkut Industri No. 2026, Surabaya<br>
            Telp: (031) 555-0192
        </div>
        <div class="order-details">
            <h2>SALES ORDER</h2>
            <b>#{{ $order->so_number }}</b><br>
            Tanggal: {{ date('d/m/Y', strtotime($order->order_date)) }}
        </div>
        <div class="clear"></div>
    </div>

    <table class="table-info">
        <tr>
            <td width="50%">
                <strong>Pemesan:</strong><br>
                {{ $order->customer->name }}<br>
                {{ $order->customer->address }}
            </td>
            <td class="text-right">
                <strong>Status Pesanan:</strong><br>
                <span style="text-transform: uppercase;">{{ $order->status }}</span>
            </td>
        </tr>
    </table>

    <table class="table-items">
        <thead>
            <tr>
                <th>Produk</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Harga Satuan</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td class="text-right">{{ $item->qty_pesanan }}</td>
                <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <table width="100%">
            <tr>
                <td><strong>GRAND TOTAL</strong></td>
                <td class="text-right"><strong>Rp {{ number_format($order->total_price, 0, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>
    <div class="clear"></div>

    <div class="footer">
        * Ini adalah dokumen pesanan resmi (Sales Order).<br>
        * Mohon lakukan pengecekan barang saat pengiriman dilakukan.<br>
        * Dicetak oleh: {{ Auth::user()->name ?? 'System' }} - {{ date('d/m/Y H:i') }}
    </div>
</body>
</html>