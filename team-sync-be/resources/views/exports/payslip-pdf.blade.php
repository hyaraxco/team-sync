<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji - {{ $period }}</title>
    <style>
        @page { margin: 28px; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.35; color: #243247; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .header { margin-bottom: 16px; }
        .header td { width: 50%; }
        h1 { font-size: 17pt; margin: 0 0 4px; }
        .company { text-align: right; }
        .company strong { font-size: 11pt; }
        .muted, .label { color: #526075; }
        .identity { margin-bottom: 16px; table-layout: fixed; }
        .identity td { padding: 5px 0; border-bottom: 1px solid #e2e8f0; overflow-wrap: break-word; }
        .identity .label { width: 18%; }
        .identity .value { width: 32%; }
        .columns { table-layout: fixed; margin-bottom: 12px; }
        .columns > tbody > tr > td { width: 49%; }
        .columns > tbody > tr > td.gap { width: 2%; }
        .money { table-layout: fixed; }
        .money th, .section-title { background-color: #1e40af; color: #fff; font-size: 9pt; text-align: left; padding: 6px 7px; }
        .money td { border: 1px solid #dbe1e9; padding: 6px 7px; overflow-wrap: break-word; }
        .money th:last-child, .money td.amount { width: 36%; }
        .amount { text-align: right; white-space: nowrap; }
        .total td, .subtotal td { background-color: #f1f5f9; font-weight: bold; }
        .section-title { margin: 12px 0 0; page-break-after: avoid; }
        .adjustments-table { margin-bottom: 12px; }
        .adjustments-table thead { display: table-header-group; }
        .net-pay { margin: 12px 0; page-break-inside: avoid; }
        .net-pay th { width: auto; }
        .net-pay td { font-size: 11pt; font-weight: bold; color: #243247; background-color: #f1f5f9; }
        .attendance { page-break-inside: avoid; table-layout: fixed; }
        .attendance td { border: 1px solid #dbe1e9; padding: 5px 7px; width: 25%; }
        .attendance strong { display: block; margin-top: 2px; font-weight: normal; }
        .notes { margin-top: 12px; overflow-wrap: break-word; }
        .footer { border-top: 1px solid #dbe1e9; margin-top: 16px; padding-top: 7px; font-size: 8pt; color: #526075; }
        .footer p { margin: 2px 0; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td><h1>Slip Gaji</h1><strong>Periode {{ $period }}</strong></td>
            <td class="company"><strong>{{ $companyName }}</strong><br><span class="muted">{{ $companyAddress }}</span></td>
        </tr>
    </table>
    <table class="identity">
        <tr>
            <td class="label">Nama Karyawan</td><td class="value">{{ $employeeName }}</td>
            <td class="label">Departemen / Divisi</td><td class="value">{{ $department }}</td>
        </tr>
        <tr>
            <td class="label">NIK / Kode Karyawan</td><td class="value">{{ $employeeCode }}</td>
            <td class="label">Pembayaran</td><td class="value">{{ $paymentDate }}</td>
        </tr>
    </table>
    <table class="columns">
        <tr>
            <td>
                <table class="money">
                    <thead><tr><th>Penghasilan</th><th class="amount">Jumlah</th></tr></thead>
                    <tbody>
                        <tr><td>Gaji Pokok</td><td class="amount">{{ $basicSalary }}</td></tr>
                        @if($hasOvertime)
                        <tr><td>Lembur ({{ $overtimeHours }} jam)</td><td class="amount">{{ $overtimeAmount }}</td></tr>
                        @endif
                        <tr><td>Tunjangan</td><td class="amount">{{ $allowances }}</td></tr>
                        <tr><td>Bonus</td><td class="amount">{{ $bonus }}</td></tr>
                        <tr class="total"><td>Total Penghasilan Bruto</td><td class="amount">{{ $grossSalary }}</td></tr>
                    </tbody>
                </table>
            </td>
            <td class="gap"></td>
            <td>
                <table class="money">
                    <thead><tr><th>Potongan</th><th class="amount">Jumlah</th></tr></thead>
                    <tbody>
                        <tr><td>BPJS Kesehatan (Karyawan)</td><td class="amount">{{ $bpjsKesehatan }}</td></tr>
                        <tr><td>BPJS Ketenagakerjaan (Karyawan)</td><td class="amount">{{ $bpjsKetenagakerjaan }}</td></tr>
                        <tr class="subtotal"><td>Subtotal BPJS</td><td class="amount">{{ $totalBpjs }}</td></tr>
                        <tr><td>PPh 21 (Pajak Penghasilan)</td><td class="amount">{{ $tax }}</td></tr>
                        <tr><td>Potongan Ketidakhadiran ({{ $deductionDays }} hari)</td><td class="amount">{{ $absenceDeduction }}</td></tr>
                        <tr><td>Potongan Lainnya</td><td class="amount">{{ $otherDeductions }}</td></tr>
                        <tr class="total"><td>Total Potongan</td><td class="amount">{{ $totalDeductions }}</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
    @if(count($adjustments) > 0)
    <table class="money adjustments-table">
        <thead><tr><th>Penyesuaian</th><th class="amount">Jumlah</th></tr></thead>
        <tbody>
            @foreach($adjustments as $adj)
            <tr><td>{{ $adj['reason'] }}</td><td class="amount">{{ $adj['formatted_amount'] }}</td></tr>
            @endforeach
            @if($adjustmentTotalAmount != 0)
            <tr class="total"><td>Total Penyesuaian</td><td class="amount">{{ $adjustmentTotalFormatted }}</td></tr>
            @endif
        </tbody>
    </table>
    @endif
    <table class="money net-pay">
        <tr><th colspan="2">GAJI BERSIH (TAKE HOME PAY)</th></tr>
        <tr><td>Jumlah Diterima</td><td class="amount">{{ $netSalary }}</td></tr>
    </table>
    <h2 class="section-title">Ringkasan Kehadiran</h2>
    <table class="attendance">
        <tr>
            <td><span class="muted">Hari Kerja Efektif</span><strong>{{ $effectiveWorkingDays }} hari</strong></td>
            <td><span class="muted">Hadir</span><strong>{{ $attendedDays }} hari</strong></td>
            <td><span class="muted">Sakit</span><strong>{{ $sickDays }} hari</strong></td>
            <td><span class="muted">Cuti Berbayar</span><strong>{{ $paidLeaveDays }} hari</strong></td>
        </tr>
        <tr>
            <td><span class="muted">Cuti Tidak Berbayar</span><strong>{{ $unpaidLeaveDays }} hari</strong></td>
            <td><span class="muted">Absen</span><strong>{{ $absentDays }} hari</strong></td>
            <td><span class="muted">Lembur</span><strong>{{ $overtimeHours }} jam</strong></td>
            <td><span class="muted">Terlambat</span><strong>{{ $lateDays }} hari</strong></td>
        </tr>
    </table>
    @if($notes)
    <div class="notes"><strong>Catatan:</strong> {{ $notes }}</div>
    @endif
    <div class="footer">
        <p>Dokumen ini dibuat secara otomatis oleh sistem dan tidak memerlukan tanda tangan basah.</p>
        <p>Untuk pertanyaan terkait slip gaji, silakan hubungi Departemen HR atau Finance.</p>
        <p>Dicetak pada: {{ $generatedAt }}</p>
    </div>
</body>
</html>
