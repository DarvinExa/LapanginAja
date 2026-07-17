<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantMemberRole;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Court;
use App\Models\OperatingHour;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Safe to run on every container restart.
        if (Tenant::query()->where('slug', 'senayan-sport')->exists()) {
            return;
        }

        // 1. Create Super Admin
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@lapanginaja.com',
            'phone' => '081122334455',
            'role' => UserRole::SUPER_ADMIN,
            'password' => bcrypt('password'),
        ]);

        // 2. Create Owner
        $owner = User::factory()->create([
            'name' => 'Andi Wijaya',
            'email' => 'owner@lapanginaja.com',
            'phone' => '081234567890',
            'role' => UserRole::OWNER,
            'password' => bcrypt('password'),
        ]);

        // 3. Create Tenant
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Senayan Sport Center',
            'slug' => 'senayan-sport',
            'address' => 'Jl. Asia Afrika No. 1, Gelora, Tanah Abang, Jakarta Pusat',
            'phone' => '0215706001',
            'timezone' => 'Asia/Jakarta',
            'hold_minutes' => 15,
            'cancellation_window_hours' => 2,
            'max_advance_days' => 30,
            'status' => TenantStatus::ACTIVE,
        ]);

        // Create Owner Tenant Membership
        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        // 4. Create Staff
        $staff = User::factory()->create([
            'name' => 'Sinta Lestari',
            'email' => 'staff@lapanginaja.com',
            'phone' => '082134567890',
            'role' => UserRole::STAFF,
            'password' => bcrypt('password'),
        ]);

        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'role' => TenantMemberRole::STAFF,
        ]);

        // 5. Create Players
        $player1 = User::factory()->create([
            'name' => 'Rizky Ramadhan',
            'email' => 'player@lapanginaja.com',
            'phone' => '083134567890',
            'role' => UserRole::PLAYER,
            'password' => bcrypt('password'),
        ]);

        $player2 = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '084134567890',
            'role' => UserRole::PLAYER,
            'password' => bcrypt('password'),
        ]);

        // 6. Create Courts & Operating Hours
        $courtsData = [
            ['name' => 'Court Futsal A', 'sport' => 'futsal', 'price' => 150000.00],
            ['name' => 'Court Badminton B', 'sport' => 'badminton', 'price' => 60000.00],
            ['name' => 'Court Padel C', 'sport' => 'padel', 'price' => 200000.00],
        ];

        foreach ($courtsData as $data) {
            $court = Court::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'sport_type' => $data['sport'],
                'price_per_hour' => $data['price'],
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ]);

            // Create operating hours for all 7 days of the week
            for ($day = 0; $day <= 6; $day++) {
                OperatingHour::create([
                    'court_id' => $court->id,
                    'day_of_week' => $day,
                    'open_time' => '08:00:00',
                    'close_time' => '22:00:00',
                    'is_closed' => false,
                ]);
            }

            // Create some bookings for each court
            // Booking 1: Confirmed and paid (tomorrow, 17:00 - 18:00)
            $start1 = now()->addDay()->setTime(17, 0, 0);
            $end1 = clone $start1;
            $end1->modify('+1 hour');

            $booking1 = Booking::create([
                'tenant_id' => $tenant->id,
                'court_id' => $court->id,
                'user_id' => $player1->id,
                'booking_code' => 'LA-'.Str::upper(Str::random(8)),
                'customer_name' => $player1->name,
                'customer_phone' => $player1->phone,
                'customer_email' => $player1->email,
                'start_time' => $start1,
                'end_time' => $end1,
                'price' => $court->price_per_hour,
                'status' => BookingStatus::CONFIRMED,
                'payment_status' => PaymentStatus::PAID,
                'expires_at' => null,
                'source' => 'online',
            ]);

            Payment::create([
                'booking_id' => $booking1->id,
                'order_id' => 'ORDER-'.Str::upper(Str::random(10)),
                'gross_amount' => $court->price_per_hour,
                'payment_type' => 'gopay',
                'transaction_id' => (string) Str::uuid(),
                'transaction_status' => 'settlement',
                'snap_token' => 'snap-token-'.Str::random(20),
                'paid_at' => now(),
            ]);

            // Booking 2: Pending (tomorrow, 19:00 - 20:00)
            $start2 = now()->addDay()->setTime(19, 0, 0);
            $end2 = clone $start2;
            $end2->modify('+1 hour');

            $booking2 = Booking::create([
                'tenant_id' => $tenant->id,
                'court_id' => $court->id,
                'user_id' => $player2->id,
                'booking_code' => 'LA-'.Str::upper(Str::random(8)),
                'customer_name' => $player2->name,
                'customer_phone' => $player2->phone,
                'customer_email' => $player2->email,
                'start_time' => $start2,
                'end_time' => $end2,
                'price' => $court->price_per_hour,
                'status' => BookingStatus::PENDING,
                'payment_status' => PaymentStatus::UNPAID,
                'expires_at' => now()->addMinutes(15),
                'source' => 'online',
            ]);

            Payment::create([
                'booking_id' => $booking2->id,
                'order_id' => 'ORDER-'.Str::upper(Str::random(10)),
                'gross_amount' => $court->price_per_hour,
                'payment_type' => 'qris',
                'transaction_id' => (string) Str::uuid(),
                'transaction_status' => 'pending',
                'snap_token' => 'snap-token-'.Str::random(20),
                'paid_at' => null,
            ]);
        }
    }
}
