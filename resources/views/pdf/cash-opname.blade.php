<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Cash Opname {{ $opname->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; margin: 16px; }
        h1 { font-size: 13px; text-align: center; margin: 2px 0; }
        h2 { font-size: 11px; text-align: center; margin: 1px 0 10px; }
        .meta { margin-bottom: 16px; }
        .meta table { width: 100%; }
        .meta td { padding: 1px 0; vertical-align: top; }
        .section-title { font-weight: bold; margin: 10px 0 4px; text-transform: uppercase; font-size: 10px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.data th, table.data td { border: 1px solid #333; padding: 2px 5px; }
        table.data th { background: #f0f0f0; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary { margin-top: 8px; }
        .summary td { padding: 2px 0; }
        .terbilang { margin: 8px 0 14px; font-style: italic; }
        .signatures { width: 100%; margin-top: 20px; }
        .signatures td { width: 33%; text-align: center; vertical-align: top; padding: 0 8px; }
        .sign-line { margin-top: 48px; border-top: 1px solid #000; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>Prasasta Learning Centre</h1>
    <h2>BERITA ACARA CASH OPNAME</h2>

    <div class="meta">
        <table>
            <tr>
                <td style="width: 120px;">Nomor</td>
                <td>: {{ $opname->number }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>: {{ $opname->date->format('d-M-Y') }}</td>
            </tr>
            <tr>
                <td>Proyek</td>
                <td>: Prasasta</td>
            </tr>
            <tr>
                <td>Akun Kas</td>
                <td>: {{ $opname->account->code }} - {{ $opname->account->name }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">Section A — Saldo Buku</div>
    <table class="data">
        <tr>
            <th>Saldo Buku (GL)</th>
            <th class="text-right" style="width: 180px;">Jumlah (Rp)</th>
        </tr>
        <tr>
            <td>Saldo akun kas per tanggal opname</td>
            <td class="text-right">{{ number_format((float) $opname->book_balance, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Section B — Denominasi Fisik</div>

    <table style="width:100%; border:none;">
        <tr>
            <td style="width:50%; vertical-align:top; padding:0 4px 0 0;">
                <p><strong>Uang Kertas</strong></p>
                <table class="data">
                    <tr>
                        <th>Pecahan (Rp)</th>
                        <th class="text-center" style="width: 80px;">Lembar</th>
                        <th class="text-right" style="width: 140px;">Jumlah (Rp)</th>
                    </tr>
                    @foreach ($banknotes as $line)
                    <tr>
                        <td>{{ number_format($line->denomination, 0, ',', '.') }}</td>
                        <td class="text-center">{{ $line->units }}</td>
                        <td class="text-right">{{ number_format((float) $line->amount, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </table>
            </td>
            <td style="width:50%; vertical-align:top; padding:0 0 0 4px;">
                <p><strong>Uang Logam</strong></p>
                <table class="data">
                    <tr>
                        <th>Pecahan (Rp)</th>
                        <th class="text-center" style="width: 80px;">Keping</th>
                        <th class="text-right" style="width: 140px;">Jumlah (Rp)</th>
                    </tr>
                    @foreach ($coins as $line)
                    <tr>
                        <td>{{ number_format($line->denomination, 0, ',', '.') }}</td>
                        <td class="text-center">{{ $line->units }}</td>
                        <td class="text-right">{{ number_format((float) $line->amount, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">Section C — Ringkasan</div>
    <table class="summary" style="width: 100%;">
        <tr>
            <td style="width: 200px;">Total Fisik (C)</td>
            <td class="text-right">: Rp {{ number_format((float) $opname->physical_balance, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Selisih (A − C)</td>
            <td class="text-right">: Rp {{ number_format((float) $opname->difference, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="terbilang">
        Terbilang: <strong>{{ $terbilang }}</strong>
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div>Prepared By</div>
                <div class="sign-line">
                    {{ $opname->preparedBy?->name ?? '' }}<br>
                    <small>{{ $preparedRole }}</small>
                </div>
            </td>
            <td>
                <div>Checked By</div>
                <div class="sign-line">&nbsp;</div>
            </td>
            <td>
                <div>Verified By</div>
                <div class="sign-line">&nbsp;</div>
            </td>
        </tr>
    </table>
</body>
</html>
