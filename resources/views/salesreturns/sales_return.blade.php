<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nota Retur - {{ $return->return_no }}</title>
    <style>
        @page { margin: 0.8cm; }
        body { font-family: 'Helvetica', sans-serif; line-height: 1.1; color: #333; font-size: 11px; margin: 0; }
        .container { width: 100%; }
        
        /* Header Section */
        .header { border-bottom: 2px solid #d9534f; padding-bottom: 10px; margin-bottom: 15px; }
        .logo-container { float: left; width: 65px; height: 65px; }
        .logo { width: 100%; height: auto; }
        .company-info { float: left; margin-left: 15px; }
        .company-info b { font-size: 16px; color: #d9534f; text-transform: uppercase; }
        .invoice-details { float: right; text-align: right; }
        .invoice-details h2 { margin: 0; color: #d9534f; font-size: 20px; text-transform: uppercase; }
        
        /* Table Sections */
        .table-info { width: 100%; margin-bottom: 15px; table-layout: fixed; }
        .table-info td { vertical-align: top; padding: 2px; }
        .table-items { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table-items th { background: #d9534f; color: white; border: 1px solid #c9302c; padding: 6px; text-align: left; }
        .table-items td { border: 1px solid #ddd; padding: 6px; }
        
        .text-right { text-align: right; }
        .text-red { color: #d9534f; font-weight: bold; }
        .footer { margin-top: 30px; width: 100%; font-size: 9px; color: #777; border-top: 1px solid #eee; padding-top: 8px; }
        .clear { clear: both; }
        .box { border: 1px solid #ddd; padding: 10px; background: #f9f9f9; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-container">
                {{-- Memanggil logo dari folder public --}}
                <img src="{{ public_path('assets/img/logo amdk.png') }}" class="logo">
            </div>
            <div class="company-info">
                <b>HYDROCORE AMDK</b><br>
                PT. Tirta Higienis Indonesia<br>
                Jl. Rungkut Industri No. 2026, Surabaya<br>
                Telp: (031) 555-0192 | Email: info@hydrocore.id
            </div>
            <div class="invoice-details">
                <h2>NOTA RETUR</h2>
                <b>#{{ $return->return_no }}</b><br>
                Tanggal: {{ date('d/m/Y', strtotime($return->return_date)) }}
            </div>
            <div class="clear"></div>
        </div>

        <table class="table-info">
            <tr>
                <td width="50%">
                    <b>Info Pelanggan:</b><br>
                    <strong>{{ $return->invoice->customer->name }}</strong><br>
                    {{ $return->invoice->customer->address }}
                </td>
                <td class="text-right" width="50%">
                    <b>Referensi Dokumen:</b><br>
                    No. Invoice: #{{ $return->invoice->no_invoice }}<br>
                    Alasan: <i>{{ $return->reason ?? 'Tidak ada alasan spesifik' }}</i>
                </td>
            </tr>
        </table>

        <table class="table-items">
            <thead>
                <tr>
                    <th width="40%">Deskripsi Produk</th>
                    <th width="15%">Kondisi</th>
                    <th width="10%" class="text-right">Qty</th>
                    <th width="15%" class="text-right">Harga Satuan</th>
                    <th width="20%" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($return->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td style="text-transform: capitalize;">{{ $item->condition }}</td>
                    <td class="text-right">{{ $item->qty }}</td>
                    <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right"><strong>TOTAL PENGURANGAN TAGIHAN</strong></td>
                    <td class="text-right text-red"><strong>Rp {{ number_format($return->total_return_amount, 0, ',', '.') }}</strong></td>
                </tr>
            </tfoot>
        </table>

        <div class="box">
            <p style="margin: 0;"><b>Catatan Penting:</b></p>
            <p style="margin: 5px 0 0 0;">
                - Kondisi <b>Good</b> telah dikembalikan ke stok gudang utama.<br>
                - Kondisi <b>Reject/Damaged</b> sedang dalam proses pengecekan lebih lanjut.<br>
                - Nilai total di atas telah memotong sisa piutang pada invoice #{{ $return->invoice->no_invoice }}.
            </p>
        </div>

        <div class="footer">
            * Dokumen ini sah dan dicetak otomatis melalui sistem HydroCore.<br>
            * Terima kasih telah bekerja sama dengan baik bersama kami.<br>
            * Dicetak oleh: {{ Auth::user()->name ?? 'Administrator' }} pada {{ date('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html> 