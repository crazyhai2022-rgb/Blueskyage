<?php
require_once __DIR__ . '/auth.php';

/**
 * Initials for the fallback avatar — first letter of the first and last word,
 * so "Vicky Raj Gupta" reads as VG.
 */
function user_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return '?';

    // mbstring isn't guaranteed on shared hosting, so fall back to the
    // byte-based functions rather than letting the whole page fatal.
    $cut = function (string $s): string {
        return function_exists('mb_substr') ? mb_substr($s, 0, 1) : substr($s, 0, 1);
    };
    $upper = function (string $s): string {
        return function_exists('mb_strtoupper') ? mb_strtoupper($s) : strtoupper($s);
    };

    $first = $cut($parts[0]);
    $last  = count($parts) > 1 ? $cut(end($parts)) : '';
    return $upper($first . $last);
}

/**
 * A stable colour per account, so the same person always gets the same
 * avatar tint rather than it changing between pages.
 */
function avatar_hue(string $seed): int {
    return crc32($seed) % 360;
}

/** Renders the avatar circle: uploaded picture if there is one, initials if not. */
function avatar_markup(array $user, string $size = 'md'): string {
    $cls = 'avatar avatar-' . $size;

    if (!empty($user['avatar_url'])) {
        return '<span class="' . $cls . '"><img src="' . e($user['avatar_url'])
             . '" alt="' . e($user['name']) . '"></span>';
    }

    $hue = avatar_hue($user['email'] ?? $user['name']);
    return '<span class="' . $cls . ' avatar-initials" style="--h:' . $hue . ';">'
         . e(user_initials($user['name'])) . '</span>';
}

/** The navbar shown on logged-in pages, with the profile dropdown. */
function account_navbar(array $user, string $active = ''): string {
    require_once __DIR__ . '/site_nav.php';

    $avatar = avatar_markup($user, 'sm');
    $dash   = $active === 'dashboard' ? ' on' : '';
    $prof   = $active === 'profile'   ? ' on' : '';
    $name   = e($user['name']);
    $email  = e($user['email']);
    $toggleDesktop = theme_toggle('only-desktop');
    $toggleMenu    = theme_toggle('menu-theme');

    return <<<HTML
<header class="navbar scrolled">
  <div class="container nav-inner">
    <a href="/" class="brand">
      <img src="/assets/img/logo-mark.png" alt="BlueSky Agency">
      <span class="brand-word">BLUE<b>SKY</b></span>
    </a>
    <nav class="nav-links">
      <a href="/products">Products</a>
      <a href="/contact">Contact</a>
    </nav>
    <div class="nav-cta">
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
          <a href="/dashboard" class="only-mobile-menu{$dash}">Dashboard</a>
          <a href="/profile" class="{$prof}">My Profile</a>
          <div class="acct-theme only-mobile-menu">{$toggleMenu}</div>
          <a href="/logout" class="danger">Log Out</a>
        </div>
      </div>
    </div>
  </div>
</header>
HTML;
}
