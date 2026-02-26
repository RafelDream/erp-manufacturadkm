<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $invoice->no_invoice }}</title>
    <style>
        @page { margin: 0.8cm; }
        body { font-family: 'Helvetica', sans-serif; line-height: 1.1; color: #333; font-size: 11px; margin: 0; }
        .container { width: 100%; }
        
        /* Header Section */
        .header { border-bottom: 2px solid #0056b3; padding-bottom: 8px; margin-bottom: 15px; }
        .logo { width: 65px; float: left; }
        .company-info { float: left; margin-left: 15px; margin-top: 2px; }
        .company-info b { font-size: 14px; color: #0056b3; }
        .invoice-details { float: right; text-align: right; }
        .invoice-details h2 { margin: 0; color: #0056b3; font-size: 18px; text-transform: uppercase; }
        
        /* Info Tables */
        .table-info { width: 100%; margin-bottom: 15px; table-layout: fixed; }
        .table-info td { vertical-align: top; padding: 2px; }
        
        /* Main Item Table */
        .table-items { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table-items th { background: #0056b3; color: white; border: 1px solid #004494; padding: 6px; text-align: left; }
        .table-items td { border: 1px solid #ddd; padding: 6px; }
        
        /* Bottom Section */
        .bottom-section { width: 100%; margin-top: 5px; }
        .left-col { width: 55%; float: left; }
        .right-col { width: 40%; float: right; }
        
        .box { border: 1px solid #ddd; padding: 8px; background: #f9f9f9; border-radius: 4px; margin-bottom: 5px; }
        .box-title { font-weight: bold; border-bottom: 1px solid #ddd; margin-bottom: 5px; display: block; font-size: 10px; text-transform: uppercase; }
        
        .text-right { text-align: right; }
        .text-blue { color: #0056b3; font-weight: bold; }
        .text-red { color: #d9534f; }
        
        .total-row td { padding: 2px 0; }
        .grand-total { font-size: 12px; border-top: 1.5px solid #333; font-weight: bold; }
        
        /* Footer moved from fixed to relative to reduce whitespace */
        .footer { margin-top: 30px; width: 100%; font-size: 9px; color: #777; border-top: 1px solid #eee; padding-top: 8px; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ public_path('assets/img/logo amdk.png') }}" class="logo">
            <div class="company-info">
                <b>HYDROCORE AMDK</b><br>
                Spesialis Air Minum Higienis<br>
                Jl. Rungkut Industri No. 2026, Surabaya | (031) 555-0192
            </div>
            <div class="invoice-details">
                <h2>INVOICE</h2>
                <b>#{{ $invoice->no_invoice }}</b><br>
                Tgl: {{ date('d M Y', strtotime($invoice->tanggal)) }}
            </div>
            <div class="clear"></div>
        </div>

        <table class="table-info">
            <tr>
                <td>
                    <b>Pelanggan:</b><br>
                    {{ $invoice->customer->name }}<br>
                    {{ $invoice->customer->address }}
                </td>
                <td class="text-right">
                    <b>Status Pembayaran:</b><br>
                    <span class="text-blue">{{ strtoupper($invoice->payment_type) }}</span><br>
                    <b>Jatuh Tempo:</b> {{ date('d M Y', strtotime($invoice->due_date)) }}
                </td>
            </tr>
        </table>

        <table class="table-items">
            <thead>
                <tr>
                    <th width="50%">Deskripsi Produk</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Harga Satuan</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td class="text-right">{{ $item->qty }}</td>
                    <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="bottom-section">
            <div class="left-col">
                <div class="box">
                    <span class="box-title">Ringkasan Pembelian</span>
                    Pembelian Galon: <b>{{ $invoice->gallon_loan_qty }} unit</b><br>
                    Status: <i class="text-blue">{{ ucfirst($invoice->gallon_deposit_status) }}</i>
                </div>
                <p style="margin-top: 5px;"><i>Note: {{ $invoice->notes ?? 'Terima kasih atas pesanannya.' }}</i></p>
            </div>

            <div class="right-col">
                <table class="total-row" width="100%">
                    <tr>
                        <td>Total Harga</td>
                        <td class="text-right">Rp {{ number_format($invoice->total_price, 0, ',', '.') }}</td>
                    </tr>
                    @if($invoice->discount_amount > 0)
                    <tr class="text-red">
                        <td>Diskon</td>
                        <td class="text-right">-Rp {{ number_format($invoice->discount_amount, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="grand-total">
                        <td>Grand Total</td>
                        <td class="text-right">Rp {{ number_format($invoice->final_amount, 0, ',', '.') }}</td>
                    </tr>
                    
                    @if($invoice->payment_type == 'dp')
                        <tr>
                            <td>DP Dibayar</td>
                            <td class="text-right">Rp {{ number_format($invoice->dp_amount, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="text-blue">
                            <td>Sisa Tagihan</td>
                            <td class="text-right">Rp {{ number_format($invoice->balance_due, 0, ',', '.') }}</td>
                        </tr>
                    @else
                        <tr class="text-blue">
                            <td>Status Tagihan</td>
                            <td class="text-right">LUNAS</td>
                        </tr>
                    @endif
                </table>
            </div>
            <div class="clear"></div>
        </div>

        <div class="footer">
            * Harap simpan invoice ini sebagai bukti sah pembayaran.<br>
            * Barang yang sudah dipesan tidak dapat ditukar atau dikembalikan kecuali adanya kerusakan produksi.<br>
            * <b>HydroCore AMDK</b> - Terima kasih atas kepercayaan Anda. Invoice Dicetak otomatis pada {{ date('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>