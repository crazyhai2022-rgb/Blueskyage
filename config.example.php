<?php
/* =====================================================================
   BLUESKY AGENCY — CONFIG FILE
   Database is now the "BlueSky" Supabase project (Postgres). Fill in
   your Razorpay API keys below. See README-SETUP.md for full steps.
   ===================================================================== */

// ---- Database (Supabase project: "BlueSky") ----
define('SUPABASE_URL', 'YOUR_SUPABASE_URL');
define('SUPABASE_KEY', 'YOUR_SUPABASE_ANON_KEY');

// ---- Razorpay (Dashboard → Settings → API Keys) ----
define('RAZORPAY_KEY_ID', 'YOUR_RAZORPAY_KEY_ID');
define('RAZORPAY_KEY_SECRET', 'YOUR_RAZORPAY_KEY_SECRET');

// ---- Site ----
define('SITE_URL', 'https://blueskyage.in');
define('WHATSAPP_NUMBER', '919507196648');

// ---- Security ----
// Random string used to help protect sessions. Change this to any
// long random text before going live.
define('APP_SECRET', 'change-this-to-a-long-random-string-before-launch');

date_default_timezone_set('Asia/Kolkata');

/* Social sign-in — see config.php notes.
   Supported values: 'google' */
define('SOCIAL_PROVIDERS', []);
