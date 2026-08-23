# BlueSky Agency Website — Backend Setup Guide

This site now has a full backend: client signup/login, Razorpay payment,
a client dashboard, and an admin panel. The database is a **Supabase**
project named **"BlueSky"** (Postgres, free plan, Mumbai region) — already
created and connected. Follow these steps to go live on your hosting.

## 1. Database — Already Set Up ✅

The Supabase project **"BlueSky"** is live with the `users`, `orders`,
and `admins` tables already created (same structure as before, now on
Postgres instead of MySQL). `config.php` is already filled in with the
project's URL and API key — you don't need to touch this step.

You can view/manage the data anytime at https://supabase.com/dashboard
under the **BlueSky** project (inside your "Vicky Raj" organization).

**Note:** the `db_schema.sql` file in this folder is the old MySQL
version and is no longer used — safe to ignore or delete it.

## 2. Edit `config.php` — Only Razorpay Keys Needed

Open `config.php` and fill in just the Razorpay part:

```php
define('RAZORPAY_KEY_ID', 'rzp_live_xxxxxxxx');      // from Razorpay Dashboard
define('RAZORPAY_KEY_SECRET', 'xxxxxxxxxxxxxxxx');   // from Razorpay Dashboard
```

Get your Razorpay keys from: **Razorpay Dashboard → Settings → API Keys**.
Until these are filled in, checkout will show a friendly "payments not
set up yet" message instead of failing silently.

**Important:** your hosting's PHP must have the **curl extension**
enabled (nearly all cPanel hosts have this on by default) since the
backend talks to Supabase over its REST API.

## 3. Upload the Files

Upload the **entire contents** of this folder to your hosting's
`public_html` folder (or the subfolder for `blueskyage.in` if it's an
add-on domain), keeping the folder structure exactly as-is:

```
public_html/
  index.html, services.html, ... (existing site pages)
  config.php
  includes/
  api/
  admin/
  signup.php, login.php, logout.php, checkout.php, dashboard.php
  assets/
```

## 4. Log In to the Admin Panel — Change the Password Immediately

Go to `https://blueskyage.in/admin/login.php`

- Username: `admin`
- Password: `bluesky@2026`

**Change this password right away.** Ask a developer to run
`password_hash('NEWPASSWORD', PASSWORD_DEFAULT)` in PHP (or use any
trusted bcrypt generator) to get a hash, then update it from the
Supabase dashboard: **BlueSky project → Table Editor → admins →**
edit the `password_hash` cell for the `admin` row.

## 5. Test the Full Flow

1. Go to `services.html` → click **Get Started** on a plan
2. You'll be asked to sign up / log in
3. Fill in BM ID + Business Name → pay via Razorpay (use Razorpay's
   [test mode keys](https://razorpay.com/docs/payments/payments/test-card-upi-details/)
   first, before switching to live keys)
4. After payment, check `dashboard.php` — the order should show as **Preparing**
5. Log in to `/admin/login.php`, find the order, fill in **Slot ID** and
   **Ad Account ID**, set status to **Active**, click **Save**
6. Refresh the client's dashboard — it now shows **Active** with the
   account details

## How It Connects to the Rest of Your System

- The **Invoice No.** generated here (e.g. `BSA-0007`) follows the same
  format as your WhatsApp/manual invoices — keep using one continuous
  sequence across both.
- **Slot ID** here is the same permanent client-facing account number
  from your operations plan — it should never change, even after an
  account replacement (only the "Ad Account ID" field does).
- This admin panel is a lighter, faster version of your WhatsApp +
  spreadsheet tracker — for now, keep the spreadsheet as backup until
  you're confident relying on this alone.
