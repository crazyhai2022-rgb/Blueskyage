<?php
/* Loads config.php and, if it hasn't been created yet, explains that
   clearly instead of letting PHP die with a blank 500 page. */

$configPath = dirname(__DIR__) . '/config.php';

if (!file_exists($configPath)) {
    http_response_code(503);

    // API endpoints answer in JSON so the checkout page can show the reason.
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => 'Site not configured: config.php is missing on the server.',
        ]);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup needed</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
       background:#080b18;color:#e8edfb;font-family:system-ui,-apple-system,sans-serif;padding:24px;}
  .box{max-width:520px;background:rgba(255,255,255,.04);border:1px solid rgba(120,150,255,.25);
       border-radius:16px;padding:32px;}
  h1{margin:0 0 12px;font-size:22px;}
  p{color:#9fb0d9;line-height:1.7;font-size:14.5px;margin:0 0 14px;}
  code{background:rgba(120,150,255,.14);padding:2px 7px;border-radius:5px;font-size:13px;}
  ol{color:#9fb0d9;line-height:1.9;font-size:14.5px;padding-left:20px;margin:0;}
</style></head>
<body><div class="box">
  <h1>Almost there — one file is missing</h1>
  <p>The site can't start because <code>config.php</code> isn't on the server yet.
     It holds the API keys, which is why it isn't part of the Git repository.</p>
  <ol>
    <li>Open File Manager and go to <code>public_html</code></li>
    <li>Copy <code>config.example.php</code> and rename the copy to <code>config.php</code></li>
    <li>Open it and fill in your Supabase and Razorpay keys</li>
    <li>Reload this page</li>
  </ol>
</div></body></html>
HTML;
    exit;
}

require_once $configPath;
