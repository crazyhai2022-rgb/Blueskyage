<?php
require_once __DIR__ . '/account_nav.php';   // brings in auth.php + avatar helpers

/**
 * Navbar for the public pages.
 * Signed out: a single "My Account" pill.
 * Signed in:  a "Dashboard" button next to the profile avatar, which opens
 *             the same menu used inside the account area.
 *
 * @param string $active  one of: home, products, contact
 */
function site_nav(string $active = ''): string {
    $user = current_user();

    $link = function (string $href, string $label, string $key) use ($active): string {
        $cls = $active === $key ? ' class="active"' : '';
        return '<a href="' . $href . '"' . $cls . '>' . $label . '</a>';
    };

    $nav = $link('/', 'Home', 'home')
         . $link('/products', 'Products', 'products')
         . $link('/contact', 'Contact', 'contact');

    if ($user) {
        $avatar = avatar_markup($user, 'sm');
        $name   = e($user['name']);
        $email  = e($user['email']);
        $cta = <<<HTML
      <a href="/dashboard" class="btn btn-primary btn-sm">Dashboard</a>
      <div class="acct" data-acct>
        <button type="button" class="acct-btn acct-btn-plain" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
          {$avatar}
        </button>
        <div class="acct-menu" hidden>
          <div class="acct-head">
            <b>{$name}</b>
            <span>{$email}</span>
          </div>
          <a href="/dashboard">Dashboard</a>
          <a href="/profile">My Profile</a>
          <a href="/logout" class="danger">Log Out</a>
        </div>
      </div>
HTML;
    } else {
        $cta = '      <a href="/login" class="btn btn-ghost btn-sm" style="margin-right:8px;">My Account</a>';
    }

    return <<<HTML
<header class="navbar">
  <div class="container nav-inner">
    <a href="/" class="brand">
      <img src="/assets/img/logo-mark.png" alt="BlueSky Agency">
      <span class="brand-word">BLUE<b>SKY</b></span>
    </a>
    <nav class="nav-links">
      {$nav}
    </nav>
    <div class="nav-cta">
{$cta}
      <button class="nav-toggle" aria-label="Menu"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>
HTML;
}
