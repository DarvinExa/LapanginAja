# LapanginAja

LapanginAja is a multi tenant Software as a Service (SaaS) platform built to simplify sports venue bookings. It allows sports facility owners to host custom subpages, manage courts, track schedule calendars, and add cashier staff accounts. Players can book slots online and pay instantly through e-wallets or bank transfers.

## Features

- Multi Tenant Subdomains: Each venue gets a customizable public profile displaying its logo, banner, and business description.
- Interactive Court Calendars: Real time slot tracking that prevents double bookings.
- Instant Payments: Midtrans Sandbox integration supporting local payment methods like QRIS, Gopay, and bank transfers.
- PDF Invoices and WhatsApp Tickets: Automated document generator and dispatch.
- User Verification: Security measures including WhatsApp/Email OTP registration and password recovery.
- Restricted Staff Accounts: Owners can delegate booking operations to cashier staff while blocking access to settings and database configuration.

## Tech Stack

### Backend
- Framework: Laravel 11
- Database: PostgreSQL
- Authentication: Laravel Sanctum
- Tests: PHPUnit / Feature Tests

### Frontend
- Framework: React (Vite)
- Language: TypeScript
- Icons: Phosphor Icons
- Styling: Vanilla CSS and Tailwind CSS

---

## Setup Instructions

### 1. Backend Setup

Follow these steps to configure the Laravel API server:

1. Navigate to the api directory:
   ```bash
   cd api
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Create the configuration file:
   ```bash
   cp .env.example .env
   ```

4. Configure your database details inside the .env file.
   *Note: For production, set MAIL_MAILER, FONNTE_TOKEN, and MIDTRANS_SERVER_KEY to enable real notifications and transactions.*

5. Run migrations and seed the database with demo venues:
   ```bash
   php artisan migrate:fresh --seed
   ```

6. Start the local development server:
   ```bash
   php artisan serve
   ```

### 2. Frontend Setup

Follow these steps to run the client web interface:

1. Navigate to the web directory:
   ```bash
   cd web
   ```

2. Install Node dependencies:
   ```bash
   npm install
   ```

3. Start the Vite development server:
   ```bash
   npm run dev
   ```

4. Open http://localhost:5173 in your browser to view the application.

---

## What's Next

Future enhancements planned for the platform include:
- Multi tenant custom domain routing.
- Advanced revenue analytics dashboards for owners.
- Real time socket integration for schedule changes.
