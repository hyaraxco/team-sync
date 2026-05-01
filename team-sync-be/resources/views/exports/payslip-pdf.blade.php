<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Slip Gaji - {{ $period }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 18pt;
            color: #1e40af;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 9pt;
            color: #666;
        }
        
        .payslip-title {
            text-align: center;
            margin: 20px 0;
        }
        
        .payslip-title h2 {
            font-size: 16pt;
            color: #1e40af;
            margin-bottom: 5px;
        }
        
        .payslip-title .period {
            font-size: 11pt;
            color: #666;
        }
        
        .info-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 5px 10px;
            font-size: 10pt;
        }
        
        .info-table td:first-child {
            width: 35%;
            font-weight: bold;
            color: #475569;
        }
        
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #1e40af;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .salary-table tr {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .salary-table td {
            padding: 8px 10px;
            font-size: 10pt;
        }
        
        .salary-table td:first-child {
            width: 60%;
        }
        
        .salary-table td.amount {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: 500;
        }
        
        .salary-table tr.subtotal td {
            font-weight: bold;
            background-color: #f1f5f9;
            padding-top: 10px;
        }
        
        .salary-table tr.total td {
            font-weight: bold;
            font-size: 11pt;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 10px;
        }
        
        .net-salary-box {
            margin: 25px 0;
            padding: 20px;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            border-radius: 8px;
            text-align: center;
        }
        
        .net-salary-box h3 {
            font-size: 11pt;
            margin-bottom: 10px;
            font-weight: normal;
            opacity: 0.9;
        }
        
        .net-salary-box .amount-large {
            font-size: 20pt;
            font-weight: bold;
            font-family: 'DejaVu Sans Mono', monospace;
        }
        
        .attendance-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        
        .attendance-row {
            display: table-row;
        }
        
        .attendance-cell {
            display: table-cell;
            width: 50%;
            padding: 8px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        
        .attendance-cell .label {
            font-size: 9pt;
            color: #64748b;
            margin-bottom: 3px;
        }
        
        .attendance-cell .value {
            font-size: 12pt;
            font-weight: bold;
            color: #1e40af;
        }
        
        .adjustments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .adjustments-table tr {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .adjustments-table td {
            padding: 6px 10px;
            font-size: 9pt;
        }
        
        .adjustments-table td.amount {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
        }
        
        .adjustments-table td.amount.positive {
            color: #16a34a;
        }
        
        .adjustments-table td.amount.negative {
            color: #dc2626;
        }
        
        .notes-box {
            margin: 20px 0;
            padding: 12px;
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            font-size: 9pt;
            color: #92400e;
        }
        
        .notes-box strong {
            display: block;
            margin-bottom: 5px;
            color: #78350f;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e2e8f0;
            font-size: 8pt;
            color: #64748b;
            text-align: center;
        }
        
        .footer p {
            margin: 3px 0;
        }
        
        .footer .generated {
            margin-top: 10px;
            font-style: italic;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <!-- Company Header -->
    <div class="header">
        <h1>{{ $companyName }}</h1>
        <p>{{ $companyAddress }}</p>
    </div>

    <!-- Payslip Title -->
    <div class="payslip-title">
        <h2>SLIP GAJI</h2>
        <p class="period">Periode: {{ $period }}</p>
    </div>

    <!-- Employee Information -->
    <div class="info-section">
        <table class="info-table">
            <tr>
                <td>Nama Karyawan</td>
                <td>: {{ $employeeName }}</td>
            </tr>
            <tr>
                <td>NIK / Kode Karyawan</td>
                <td>: {{ $employeeCode }}</td>
            </tr>
            <tr>
                <td>Departemen / Divisi</td>
                <td>: {{ $department }}</td>
            </tr>
            <tr>
                <td>Tanggal Pembayaran</td>
                <td>: {{ $paymentDate }}</td>
            </tr>
        </table>
    </div>

    <!-- Earnings Section -->
    <h3 class="section-title">PENGHASILAN</h3>
    <table class="salary-table">
        <tr>
            <td>Gaji Pokok</td>
            <td class="amount">{{ $basicSalary }}</td>
        </tr>
        @if($overtimeAmount > 0)
        <tr>
            <td>Lembur ({{ $overtimeHours }} jam)</td>
            <td class="amount">{{ $overtimeAmount }}</td>
        </tr>
        @endif
        @if($allowances > 0)
        <tr>
            <td>Tunjangan</td>
            <td class="amount">{{ $allowances }}</td>
        </tr>
        @endif
        @if($bonus > 0)
        <tr>
            <td>Bonus</td>
            <td class="amount">{{ $bonus }}</td>
        </tr>
        @endif
        <tr class="total">
            <td>Total Penghasilan Bruto</td>
            <td class="amount">{{ $grossSalary }}</td>
        </tr>
    </table>

    <!-- Deductions Section -->
    <h3 class="section-title">POTONGAN</h3>
    <table class="salary-table">
        <!-- BPJS Breakdown -->
        @if($bpjsKesehatan > 0)
        <tr>
            <td>BPJS Kesehatan ({{ $bpjsKesehatanRate }}%)</td>
            <td class="amount">{{ $bpjsKesehatan }}</td>
        </tr>
        @endif
        @if($bpjsJht > 0)
        <tr>
            <td>BPJS JHT - Jaminan Hari Tua ({{ $bpjsJhtRate }}%)</td>
            <td class="amount">{{ $bpjsJht }}</td>
        </tr>
        @endif
        @if($bpjsJp > 0)
        <tr>
            <td>BPJS JP - Jaminan Pensiun ({{ $bpjsJpRate }}%)</td>
            <td class="amount">{{ $bpjsJp }}</td>
        </tr>
        @endif
        
        @if($bpjsKesehatan > 0 || $bpjsJht > 0 || $bpjsJp > 0)
        <tr class="subtotal">
            <td>Subtotal BPJS</td>
            <td class="amount">{{ $totalBpjs }}</td>
        </tr>
        @endif
        
        <!-- Tax -->
        @if($tax > 0)
        <tr>
            <td>PPh 21 (Pajak Penghasilan)</td>
            <td class="amount">{{ $tax }}</td>
        </tr>
        @endif
        
        <!-- Absence Deductions -->
        @if($absenceDeduction > 0)
        <tr>
            <td>Potongan Ketidakhadiran ({{ $deductionDays }} hari)</td>
            <td class="amount">{{ $absenceDeduction }}</td>
        </tr>
        @endif
        
        <!-- Other deductions -->
        @if($otherDeductions > 0)
        <tr>
            <td>Potongan Lainnya</td>
            <td class="amount">{{ $otherDeductions }}</td>
        </tr>
        @endif
        
        <tr class="total">
            <td>Total Potongan</td>
            <td class="amount">{{ $totalDeductions }}</td>
        </tr>
    </table>

    <!-- Adjustments (if any) -->
    @if(count($adjustments) > 0)
    <h3 class="section-title">PENYESUAIAN</h3>
    <table class="adjustments-table">
        @foreach($adjustments as $adj)
        <tr>
            <td>{{ $adj['reason'] }}</td>
            <td class="amount {{ $adj['amount_delta'] >= 0 ? 'positive' : 'negative' }}">
                {{ $adj['formatted_amount'] }}
            </td>
        </tr>
        @endforeach
        @if($adjustmentTotalAmount != 0)
        <tr style="font-weight: bold;">
            <td>Total Penyesuaian</td>
            <td class="amount {{ $adjustmentTotalAmount >= 0 ? 'positive' : 'negative' }}">
                {{ $adjustmentTotalFormatted }}
            </td>
        </tr>
        @endif
    </table>
    @endif

    <!-- Net Salary -->
    <div class="net-salary-box">
        <h3>GAJI BERSIH (TAKE HOME PAY)</h3>
        <p class="amount-large">{{ $netSalary }}</p>
    </div>

    <!-- Attendance Summary -->
    <h3 class="section-title">RINGKASAN KEHADIRAN</h3>
    <div class="attendance-grid">
        <div class="attendance-row">
            <div class="attendance-cell">
                <div class="label">Hari Kerja Efektif</div>
                <div class="value">{{ $effectiveWorkingDays }} hari</div>
            </div>
            <div class="attendance-cell">
                <div class="label">Hadir</div>
                <div class="value">{{ $attendedDays }} hari</div>
            </div>
        </div>
        <div class="attendance-row">
            <div class="attendance-cell">
                <div class="label">Sakit</div>
                <div class="value">{{ $sickDays }} hari</div>
            </div>
            <div class="attendance-cell">
                <div class="label">Cuti Berbayar</div>
                <div class="value">{{ $paidLeaveDays }} hari</div>
            </div>
        </div>
        <div class="attendance-row">
            <div class="attendance-cell">
                <div class="label">Cuti Tidak Berbayar</div>
                <div class="value">{{ $unpaidLeaveDays }} hari</div>
            </div>
            <div class="attendance-cell">
                <div class="label">Absen</div>
                <div class="value">{{ $absentDays }} hari</div>
            </div>
        </div>
        @if($overtimeHours > 0)
        <div class="attendance-row">
            <div class="attendance-cell">
                <div class="label">Lembur</div>
                <div class="value">{{ $overtimeHours }} jam</div>
            </div>
            <div class="attendance-cell">
                <div class="label">Terlambat</div>
                <div class="value">{{ $lateDays }} hari</div>
            </div>
        </div>
        @endif
    </div>

    <!-- Notes -->
    @if($notes)
    <div class="notes-box">
        <strong>Catatan:</strong>
        {{ $notes }}
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p><strong>Dokumen ini dibuat secara otomatis oleh sistem dan tidak memerlukan tanda tangan basah.</strong></p>
        <p>Untuk pertanyaan terkait slip gaji, silakan hubungi Departemen HR atau Finance.</p>
        <p class="generated">Dicetak pada: {{ $generatedAt }}</p>
    </div>
</body>
</html>
