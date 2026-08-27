<?php require_once __DIR__ . '/includes/site_nav.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>
/* Runs before paint so a light-theme user never sees a dark flash. */
(function(){try{var t=localStorage.getItem("bluesky-theme");
if(!t)t="dark";
document.documentElement.setAttribute("data-theme",t);
document.documentElement.style.colorScheme=t;}catch(e){}})();
</script>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shipping &amp; Delivery Policy — BlueSky Agency</title>
<meta name="description" content="How digital orders are delivered and how long they take.">
<link rel="icon" href="assets/img/logo-mark.png">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<div class="bg-mesh"></div>
<div class="bg-grain"></div>

<?= site_nav('') ?>

<section class="section legal-page">
  <div class="container">
    <div class="legal-head">
      <div class="eyebrow">Legal</div>
      <h1>Shipping &amp; Delivery Policy</h1>
      <p class="legal-intro">How digital orders are delivered and how long they take.</p>
      <span class="legal-updated">Last updated: 24 August 2026</span>
    </div>

    <div class="legal-body">

      <div class="legal-callout">
        <strong>Everything we sell is digital.</strong> Nothing is physically
        shipped — orders are delivered electronically to your account and over
        WhatsApp.
      </div>

      <h2>1. How delivery works</h2>
      <p>Once your payment is confirmed, your order appears in your dashboard
         straight away with the status <em>Preparing</em>. Our team then sets up
         your account or prepares your product.</p>

      <h2>2. How long it takes</h2>
      <ul>
        <li><strong>Ad account plans:</strong> usually within a few hours of
            payment, and up to 24 hours at busy times.</li>
        <li><strong>Pages, profiles and Instagram accounts:</strong> generally
            the same day, depending on availability.</li>
        <li><strong>Ads management and creative work:</strong> begins once we
            have your brief; timelines are agreed with you directly.</li>
      </ul>

      <h2>3. Where you receive it</h2>
      <p>Account numbers and details appear in your
         <a href="/dashboard">Dashboard</a> as soon as the order goes live.
         Credentials and anything sensitive are shared with you over WhatsApp on
         the number registered to your account.</p>

      <h2>4. If something is delayed</h2>
      <p>If your order has not been delivered within 24 hours of payment,
         message us on WhatsApp at +91 95071 96648 or email
         <a href="mailto:info@blueskyage.in">info@blueskyage.in</a> with your invoice number, and we
         will tell you exactly where it stands.</p>

      <h2>5. Delivery charges</h2>
      <p>There are none. Digital delivery is included in the price you see at
         checkout.</p>

      <h2>Business details</h2>
      <p><strong>BlueSky Agency</strong><br>
         Patna, Bihar &ndash; 845426, India</p>
      <p>Email: <a href="mailto:info@blueskyage.in">info@blueskyage.in</a><br>
         WhatsApp: +91 95071 96648<br>
         Website: <a href="https://blueskyage.in">blueskyage.in</a></p>
    </div>

    <div class="legal-contact">
      <h3>Questions about this policy?</h3>
      <p>Write to us at <a href="mailto:info@blueskyage.in">info@blueskyage.in</a> or message us on
         WhatsApp at <a href="#" data-wa-message="Hi BlueSky Agency!">+91 95071 96648</a>.
         We reply within one business day.</p>
    </div>
  </div>
</section>

<footer>
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <img src="assets/img/logo-full.png" alt="BlueSky Agency">
        <p>Premium agency Meta ad accounts, ads management and Facebook/Instagram assets — built for businesses that advertise seriously.</p>
        <div class="footer-social">
          <a href="https://www.instagram.com/blueskyai_ads/?hl=en" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg></a>
          <a href="https://www.facebook.com/profile.php?id=61592661373434" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>
          <a href="#" data-wa-message="Hi BlueSky Agency!" aria-label="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.2h.01c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm5.8 14.1c-.24.68-1.4 1.32-1.93 1.4-.5.08-1.13.11-1.82-.11-.42-.13-.96-.31-1.65-.6-2.91-1.26-4.8-4.18-4.94-4.37-.14-.19-1.18-1.57-1.18-3s.75-2.13 1.02-2.42c.26-.29.58-.36.77-.36h.55c.18 0 .42-.07.65.5.24.58.81 2 .88 2.15.07.15.11.32.02.51-.09.19-.14.31-.28.48-.14.16-.29.36-.42.48-.14.14-.28.29-.12.56.16.28.72 1.19 1.55 1.93 1.06.95 1.96 1.24 2.24 1.38.28.14.44.12.6-.07.16-.19.68-.79.87-1.07.18-.28.36-.23.61-.14.24.09 1.55.73 1.82.87.26.14.44.2.5.32.06.13.06.72-.18 1.4z"/></svg></a>
        </div>
      </div>
      <div class="footer-col">
        <h5>Services</h5>
        <a href="/services">Meta Agency Ad Account</a>
        <a href="/services">Meta Ads Management</a>
        <a href="/services">Ads Creative Services</a>
      </div>
      <div class="footer-col">
        <h5>Legal</h5>
        <a href="/privacy-policy">Privacy Policy</a>
        <a href="/terms">Terms &amp; Conditions</a>
        <a href="/refund-cancellation">Cancellation &amp; Refund</a>
        <a href="/shipping-delivery">Shipping &amp; Delivery</a>
      </div>
      <div class="footer-col">
        <h5>Contact</h5>
        <a href="#" data-wa-message="Hi BlueSky Agency!">WhatsApp: +91 95071 96648</a>
        <a href="mailto:info@blueskyage.in">info@blueskyage.in</a>
        <a href="/contact">Contact Form</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 BlueSky Agency. All rights reserved.</span>
      <span>Designed by Code Of X</span>
    </div>
  </div>
</footer>

<script src="assets/js/main.js?v=<?= @filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
</body>
</html>
