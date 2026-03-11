<!DOCTYPE html>
<html>
<head>
    <title>Surat Jalan - {{ $do->no_sj }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo-container { text-align: left; margin-bottom: -40px; } /* Menyesuaikan posisi logo */
        .logo { width: 100px; height: auto; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f2f2f2; }
        .footer { width: 100%; margin-top: 50px; }
        .signature-box { width: 33%; float: left; text-align: center; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="header-wrapper">
        <div class="logo-container">
            <img src="{{ public_path('assets/img/logo amdk.png') }}" class="logo">
        </div>
        
        <div class="header">
            <h2>SURAT JALAN</h2>
            <strong>NOMOR: {{ $do->no_sj }}</strong>
        </div>
    </div>

    <table class="info-table">
        <tr>
            <td width="50%">
                <strong>Kepada Yth:</strong><br>
                {{ $do->customer->name }}<br>
                {{ $do->customer->address }}<br>
                Telp: {{ $do->customer->phone }}
            </td>
            <td width="50%" style="text-align: right;">
                <strong>Detail Pengiriman:</strong><br>
                Tanggal: {{ $do->tanggal->format('d F Y') }}<br>
                No. SPK: {{ $do->no_spk }}<br>
                Gudang: {{ $do->warehouse->name }}<br>
                <strong>No. Kendaraan: {{ $do->vehicle_number ?? '.................' }}</strong>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Nama Produk</th>
                <th width="15%" style="text-align: center;">Jumlah (Qty)</th>
                <th width="15%">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($do->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->product->name }}</td>
                <td style="text-align: center;">{{ $item->qty_realisasi }}</td>
                <td>PCS</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p><strong>Catatan:</strong> {{ $do->notes ?? '-' }}</p>

    <div class="footer">
        <div class="signature-box">
            Penerima,<br><br><br><br>
            ( ........................ )
        </div>
        <div class="signature-box">
            Sopir/Ekspedisi,<br><br><br><br>
            ( ........................ )
        </div>
        <div class="signature-box">
            Hormat Kami,<br><br><br><br>
            ( {{ $do->creator->name ?? 'Admin' }} )
        </div>
        <div class="clear"></div>
    </div>
</body>
</html>