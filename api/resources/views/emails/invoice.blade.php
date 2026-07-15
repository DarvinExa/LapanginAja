<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $booking->booking_code }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.4; margin: 0; padding: 20px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; font-size: 14px; }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
        .invoice-box table td { padding: 8px; vertical-align: top; }
        .invoice-box table tr.top table td { padding-bottom: 20px; }
        .invoice-box table tr.top table td.title { font-size: 28px; line-height: 28px; color: #16a34a; font-weight: bold; }
        .invoice-box table tr.information table td { padding-bottom: 40px; }
        .invoice-box table tr.heading td { background: #F3F4F6; border-bottom: 1px solid #E5E7EB; font-weight: bold; }
        .invoice-box table tr.item td { border-bottom: 1px solid #F3F4F6; }
        .invoice-box table tr.total td.price-cell { border-top: 2px solid #E5E7EB; font-weight: bold; font-size: 16px; color: #16a34a; }
        .qr-section { margin-top: 40px; text-align: center; }
        .qr-section img { width: 120px; height: 120px; }
        .footer { margin-top: 50px; font-size: 11px; color: #9CA3AF; text-align: center; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <table>
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title">
                                LapanginAja
                            </td>
                            <td style="text-align: right;">
                                Invoice #: {{ $booking->booking_code }}<br>
                                Tanggal: {{ now()->format('d M Y') }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                <strong>Penyedia Lapangan:</strong><br>
                                {{ $booking->tenant->name }}<br>
                                Telp: {{ $booking->tenant->phone }}
                            </td>
                            <td style="text-align: right;">
                                <strong>Detail Pelanggan:</strong><br>
                                {{ $booking->customer_name }}<br>
                                {{ $booking->customer_phone }}<br>
                                {{ $booking->customer_email }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="heading">
                <td>Item Layanan</td>
                <td style="text-align: right;">Total</td>
            </tr>
            <tr class="item">
                <td>
                    Sewa Lapangan: {{ $booking->court->name }}<br>
                    Waktu: {{ $booking->start_time->copy()->timezone($booking->tenant->timezone)->format('d M Y, H:i') }} - {{ $booking->end_time->copy()->timezone($booking->tenant->timezone)->format('H:i') }} ({{ $booking->tenant->timezone }})
                </td>
                <td style="text-align: right;">
                    Rp {{ number_format($booking->price, 0, ',', '.') }}
                </td>
            </tr>
            <tr class="total">
                <td></td>
                <td style="text-align: right;" class="price-cell">
                    Total: Rp {{ number_format($booking->price, 0, ',', '.') }}
                </td>
            </tr>
        </table>

        <div class="qr-section">
            <p><strong>Pindai QR Code E-Ticket Saat Kedatangan:</strong></p>
            <img src="data:image/svg+xml;base64,{{ $qrCode }}" alt="QR Code Booking">
            <p style="font-size: 12px; color: #4B5563;">{{ $booking->booking_code }}</p>
        </div>

        <div class="footer">
            Terima kasih telah melakukan pemesanan melalui LapanginAja.<br>
            Harap tunjukkan e-ticket ini kepada petugas lapangan saat check-in.
        </div>
    </div>
</body>
</html>
