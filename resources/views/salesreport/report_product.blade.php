<!DOCTYPE html>
<html>
<head>
    <title>Laporan Penjualan Per Produk</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        
        /* Pengaturan Logo */
        .header-container { width: 100%; margin-bottom: 30px; border-bottom: 2px solid #444; padding-bottom: 10px; }
        .logo-container { float: left; width: 150px; } /* Membatasi lebar kontainer logo */
        .logo { width: 100%; height: auto; } /* Logo mengikuti lebar kontainer */
        
        /* Pengaturan Judul di sebelah logo atau di bawah */
        .header-text { text-align: right; margin-top: 10px; }
        .clear { clear: both; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; text-transform: uppercase; font-size: 11px; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header-container">
        <div class="logo-container">
            <img src="{{ public_path('assets/img/logo amdk.png') }}" class="logo">
        </div>
        <div class="header-text">
            <h2 style="margin: 0;">LAPORAN PENJUALAN PER PRODUK</h2>
            <p style="margin: 5px 0 0 0;">Periode: {{ $startDate }} s/d {{ $endDate }}</p>
        </div>
        <div class="clear"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="15%">Kode Produk</th>
                <th width="45%">Nama Produk</th>
                <th width="15%" class="text-center">Total Terjual</th>
                <th width="25%" class="text-right">Total Omzet</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr>
                <td>{{ $row->product_code }}</td>
                <td>{{ $row->product_name }}</td>
                <td class="text-center">{{ number_format($row->total_qty) }}</td>
                <td class="text-right">Rp {{ number_format($row->total_omzet, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f8f9fa;">
                <td colspan="2" class="text-right">TOTAL KESELURUHAN</td>
                <td class="text-center">{{ number_format($data->sum('total_qty')) }}</td>
                <td class="text-right">Rp {{ number_format($data->sum('total_omzet'), 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>