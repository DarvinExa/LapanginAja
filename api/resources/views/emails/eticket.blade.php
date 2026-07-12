<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>E-Ticket Anda - {{ $booking->booking_code }}</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #F9FAFB; margin: 0; padding: 20px; color: #1F2937; }
        .ticket-container { max-width: 600px; margin: 0 auto; background-color: #FFFFFF; border-radius: 8px; border: 1px solid #E5E7EB; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
        .header { background-color: #4F46E5; padding: 20px; text-align: center; color: #FFFFFF; }
        .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
        .content { padding: 30px; }
        .qr-wrapper { text-align: center; margin: 30px 0; }
        .qr-wrapper img { width: 150px; height: 150px; border: 4px solid #E5E7EB; border-radius: 4px; }
        .booking-details { border-top: 1px dashed #E5E7EB; border-bottom: 1px dashed #E5E7EB; padding: 20px 0; margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .detail-label { color: #6B7280; font-weight: bold; }
        .detail-value { color: #111827; text-align: right; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #9CA3AF; background-color: #F9FAFB; }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="header">
            <h1>E-Ticket LapanginAja</h1>
            <p style="margin: 5px 0 0 0; font-size: 14px;">Kode Booking: <strong>{{ $booking->booking_code }}</strong></p>
        </div>
        <div class="content">
            <p>Halo <strong>{{ $booking->customer_name }}</strong>,</p>
            <p>Pemesanan lapangan Anda telah berhasil dikonfirmasi. Berikut adalah e-ticket Anda yang sah untuk check-in:</p>

            <div class="qr-wrapper">
                <img src="data:image/svg+xml;base64,{{ $qrCode }}" alt="QR Code E-Ticket">
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #6B7280;">Tunjukkan QR Code ini kepada petugas lapangan saat check-in.</p>
            </div>

            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Penyedia Lapangan</span>
                    <span class="detail-value"><strong>{{ $booking->tenant->name }}</strong></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Lapangan</span>
                    <span class="detail-value">{{ $booking->court->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Waktu Main</span>
                    <span class="detail-value">
                        {{ $booking->start_time->copy()->timezone($booking->tenant->timezone)->format('d M Y') }}<br>
                        {{ $booking->start_time->copy()->timezone($booking->tenant->timezone)->format('H:i') }} - {{ $booking->end_time->copy()->timezone($booking->tenant->timezone)->format('H:i') }} ({{ $booking->tenant->timezone }})
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status Pembayaran</span>
                    <span class="detail-value" style="color: #059669; font-weight: bold;">LUNAS</span>
                </div>
            </div>

            <p style="font-size: 13px; color: #4B5563;">
                PDF Invoice resmi untuk pemesanan ini telah dilampirkan bersama email ini. Jika Anda memerlukan bantuan atau ingin melakukan perubahan, silakan hubungi pengelola lapangan di nomor <strong>{{ $booking->tenant->phone }}</strong>.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} LapanginAja. All rights reserved.
        </div>
    </div>
</body>
</html>
