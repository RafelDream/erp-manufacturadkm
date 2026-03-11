<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Aging Piutang - {{ $today }}</title>
    <style>
        @page { margin: 0.8cm; size: landscape; }
        body { font-family: 'Helvetica', sans-serif; line-height: 1.2; color: #333; font-size: 11px; margin: 0; }
        .container { width: 100%; }
        
        /* Header Section */
        .header { border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 20px; position: relative; }
        .logo-container { float: left; width: 60px; }
        .logo { width: 100%; }
        .company-info { float: left; margin-left: 15px; }
        .company-info b { font-size: 16px; color: #0056b3; text-transform: uppercase; }
        
        .report-title { float: right; text-align: right; }
        .report-title h2 { margin: 0; color: #0056b3; font-size: 20px; text-transform: uppercase; }
        .report-title span { font-style: italic; color: #666; }
        
        /* Table Styles */
        .table-data { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
        .table-data th { background: #0056b3; color: white; border: 1px solid #004494; padding: 8px; text-align: center; font-size: 10px; }
        .table-data td { border: 1px solid #ddd; padding: 6px 8px; vertical-align: middle; }
        
        /* Aging Categories Colors */
        .bg-safe { background-color: #f1f8e9; } /* Belum Jatuh Tempo - Hijau Sangat Muda */
        .bg-warning { background-color: #fff8e1; } /* 1-30 hari - Kuning Muda */
        .bg-danger { background-color: #ffebee; color: #b71c1c; } /* > 30 hari - Merah Muda */
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        
        /* Footer */
        .footer { margin-top: 30px; font-size: 9px; color: #777; text-align: left; border-top: 1px solid #eee; padding-top: 8px; }
        .clear { clear: both; }

        tfoot tr td { background: #f9f9f9; padding: 10px 8px; border-top: 2px solid #333 !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-container">
                <img src="{{ public_path('assets/img/logo amdk.png') }}" class="logo">
            </div>
            <div class="company-info">
                <b>HYDROCORE AMDK</b><br>
                Laporan Analisis Umur Piutang (Aging Schedule)<br>
                Per Tanggal: {{ $today }}
            </div>
            <div class="report-title">
                <h2>AGING REPORT</h2>
                <span>Mata Uang: IDR (Rp)</span>
            </div>
            <div class="clear"></div>
        </div>

        <table class="table-data">
            <thead>
                <tr>
                    <th width="18%">Pelanggan</th>
                    <th width="14%">No. Invoice</th>
                    <th width="12%">Tgl. Invoice</th>
                    <th width="12%">Jatuh Tempo</th>
                    <th width="10%">Hari Lewat</th>
                    <th width="14%">Sisa Piutang</th>
                    <th width="20%">Status / Kategori Aging</th>
                </tr>
            </thead>
            <tbody>
                @php $totalAll = 0; @endphp
                @forelse($data as $item)
                    @php 
                        $totalAll += $item->balance_due;
                        $days = $item->days_overdue;
                        $rowClass = '';
                        $status = 'Belum Jatuh Tempo';

                        if ($days > 0 && $days <= 30) {
                            $rowClass = 'bg-warning';
                            $status = 'Tertunggak 1-30 Hari';
                        } elseif ($days > 30) {
                            $rowClass = 'bg-danger';
                            $status = 'Macet > 30 Hari';
                        } else {
                            $rowClass = 'bg-safe';
                        }
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td>{{ $item->customer_name }}</td>
                        <td class="text-center">{{ $item->no_invoice }}</td>
                        <td class="text-center">{{ date('d/m/Y', strtotime($item->tanggal)) }}</td>
                        <td class="text-center">{{ date('d/m/Y', strtotime($item->due_date)) }}</td>
                        <td class="text-center bold">{{ $days > 0 ? $days : 0 }}</td>
                        <td class="text-right">Rp {{ number_format($item->balance_due, 0, ',', '.') }}</td>
                        <td class="text-center bold">{{ $status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 20px;">Tidak ada piutang outstanding saat ini.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right bold">TOTAL PIUTANG BERJALAN (OUTSTANDING)</td>
                    <td class="text-right bold" style="color: #d9534f; font-size: 12px;">
                        Rp {{ number_format($totalAll, 0, ',', '.') }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        

        <div class="footer">
            * Laporan ini mencakup seluruh tagihan yang memiliki sisa saldo di atas Rp 0.<br>
            * Dicetak secara otomatis oleh Sistem HydroCore pada {{ date('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>