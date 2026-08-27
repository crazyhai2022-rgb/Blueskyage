<?php
require_once __DIR__ . '/account_nav.php';   // brings in auth.php + avatar helpers

/** The light/dark switch. Sits beside Dashboard on desktop, inside the menu on phones. */
function theme_toggle(string $extraClass = ''): string {
    $cls = trim('theme-toggle ' . $extraClass);
    return <<<HTML
<button type="button" class="{$cls}" data-theme-toggle aria-label="Switch between light and dark">
  <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
  <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
  <span class="theme-toggle-label">Theme</span>
</button>
HTML;
}

/**
 * Navbar for the public pages.
 *
 * Signed out: a single "My Account" pill.
 * Signed in:  Dashboard button + theme switch + avatar menu on desktop.
 *             On phones only the avatar shows, and everything — Dashboard,
 *             My Profile, theme, Log Out — lives inside its menu.
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
        $toggleDesktop = theme_toggle('only-desktop');
        $toggleMenu    = theme_toggle('menu-theme');

        $cta = <<<HTML
      <a href="/dashboard" class="btn btn-primary btn-sm only-desktop">Dashboard</a>
      {$toggleDesktop}
      <div class="acct" data-acct>
        <button type="button" class="acct-btn acct-btn-plain" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
          {$avatar}
          <svg class="acct-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div class="acct-menu" hidden>
          <div class="acct-head">
            <b>{$name}</b>
            <span>{$email}</span>
          </div>
          <a href="/dashboard" class="only-mobile-menu">Dashboard</a>
          <a href="/profile">My Profile</a>
          <div class="acct-theme only-mobile-menu">{$toggleMenu}</div>
          <a href="/logout" class="danger">Log Out</a>
        </div>
      </div>
HTML;
    } else {
        $toggle = theme_toggle();
        $cta = <<<HTML
      {$toggle}
      <a href="/login" class="btn btn-ghost btn-sm" style="margin-left:8px;">My Account</a>
HTML;
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
