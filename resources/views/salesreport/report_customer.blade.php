<!DOCTYPE html>
<html>
<head>
    <title>Laporan Penjualan Per Customer</title>
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
    <div class="header-wrapper">
        <div class="logo-container">
            <img src="{{ public_path('assets/img/logo amdk.png') }}" class="logo">
        </div>
        <div class="header-content">
            <h2 style="margin: 0; padding-top: 10px;">LAPORAN PENJUALAN PER CUSTOMER</h2>
            <p style="margin: 5px 0 0 0;">Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</p>
            @if(isset($paymentStatus))
                <p style="margin: 2px 0 0 0; font-weight: bold;">Status: {{ ucfirst($paymentStatus == 'unpaid' ? 'Belum Lunas' : ($paymentStatus == 'paid' ? 'Lunas' : 'Semua')) }}</p>
            @if(isset($paymentStatus))
            <p style="margin: 2px 0 0 0; font-weight: bold;">Status: {{ ucfirst($paymentStatus == 'unpaid' ? 'Belum Lunas' : ($paymentStatus == 'paid' ? 'Lunas' : 'Semua')) }}</p>
            @endif
        </div>
        <div class="clear"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Nama Customer</th>
                <th width="20%" class="text-right">Total Transaksi</th>
                <th width="30%" class="text-right">Total Kontribusi (Omzet)</th>
                <th width="25%" class="text-right">Sisa Piutang</th>
            </tr>
        </thead>
        <tbody>
            @php 
            $grandTotal = 0;
            $grandTotalPiutang = 0;
             @endphp
            @foreach($data as $key => $row)
            <tr>
                <td>{{ $key + 1 }}</td>
                <td>{{ $row->customer_name }}</td>
                <td class="text-right">{{ number_format($row->total_orders) }} Order</td>
                <td class="text-right">Rp {{ number_format($row->total_kontribusi, 0, ',', '.') }}</td>
                <td class="text-right" style="color: {{ $row->total_piutang > 0 ? '#d9534f' : '#333' }};">
                    Rp {{ number_format($row->total_piutang, 0, ',', '.') }} </td>
            </tr>
            @php $grandTotal += $row->total_kontribusi; $grandTotalPiutang += $row->total_piutang; @endphp
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f9f9f9; font-weight: bold;">
                <th colspan="3" class="text-right">Total Keseluruhan</th>
                <th class="text-right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</th>
                <th class="text-right" style="color: #d9534f;">Rp {{ number_format($grandTotalPiutang, 0, ',', '.') }}</th>
            </tr>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Dicetak pada: {{ date('d/m/Y H:i') }}</p>
    </div>
</body>
</html>