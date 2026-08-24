<?php
/* Shared Google / X / Apple sign-in block. Providers that haven't been
   switched on in the Supabase dashboard are simply left out, so no button
   ever leads to an error page. */
$enabledProviders = defined('SOCIAL_PROVIDERS') ? SOCIAL_PROVIDERS : [];
if (!$enabledProviders) return;

$icons = [
  'google' => '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.65l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.11a6.6 6.6 0 0 1 0-4.22V7.05H2.18a11 11 0 0 0 0 9.9l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.05l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/></svg>',
  'twitter' => '<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.46l8.6-9.83L0 1.15h7.6l5.24 6.93 6.06-6.93zm-1.29 19.5h2.04L6.49 3.24H4.3l13.31 17.41z"/></svg>',
  'apple' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M17.05 12.54c-.03-2.75 2.25-4.07 2.35-4.13-1.28-1.87-3.27-2.13-3.98-2.16-1.7-.17-3.31 1-4.17 1-.85 0-2.18-.98-3.58-.95-1.84.03-3.54 1.07-4.49 2.72-1.91 3.32-.49 8.23 1.37 10.92.91 1.32 2 2.8 3.42 2.74 1.37-.05 1.89-.89 3.55-.89 1.65 0 2.12.89 3.57.86 1.47-.02 2.41-1.34 3.31-2.66 1.04-1.53 1.47-3.01 1.5-3.09-.03-.01-2.88-1.11-2.91-4.4zM14.3 4.35c.75-.92 1.26-2.19 1.12-3.46-1.08.04-2.4.72-3.18 1.63-.7.81-1.31 2.11-1.15 3.35 1.21.09 2.45-.61 3.21-1.52z"/></svg>',
];
$labels = ['google' => 'Google', 'twitter' => 'X', 'apple' => 'Apple'];
?>
<div class="social-auth">
  <div class="social-sep"><span>or continue with</span></div>
  <div class="social-row">
    <?php foreach ($enabledProviders as $p): if (!isset($icons[$p])) continue; ?>
      <button type="button" class="social-btn" data-provider="<?= e($p) ?>">
        <?= $icons[$p] ?><span><?= e($labels[$p]) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
  <p class="social-msg" id="socialMsg"></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.js"></script>
<script>
(function () {
  // The UMD build exposes the factory under a couple of different names
  // depending on version, so pick whichever one actually loaded.
  const sb = window.supabase || window.Supabase;
  const msgEl = document.getElementById('socialMsg');
  if (!sb || typeof sb.createClient !== 'function') {
    msgEl.textContent = 'Social sign-in is unavailable right now — please use email.';
    msgEl.className = 'social-msg bad';
    document.querySelectorAll('.social-btn').forEach(b => { b.disabled = true; });
    return;
  }

  const client = sb.createClient(
    <?= json_encode(SUPABASE_URL) ?>,
    <?= json_encode(SUPABASE_KEY) ?>,
    { auth: { detectSessionInUrl: true, persistSession: false } }
  );

  const msg = msgEl;
  const show = (t, bad) => { msg.textContent = t; msg.className = 'social-msg' + (bad ? ' bad' : ''); };

  // Coming back from the provider: hand the token to our server, which
  // decides which local account it belongs to.
  (async function handleReturn() {
    const { data } = await client.auth.getSession();
    if (!data || !data.session) return;

    show('Signing you in\u2026');
    try {
      const res = await fetch('api/social_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ access_token: data.session.access_token })
      });
      const out = await res.json();
      await client.auth.signOut();          // our PHP session is the real one now
      if (!out.ok) return show(out.error || 'Sign-in failed.', true);

      const params = new URLSearchParams(location.search);
      const to = params.get('redirect');
      location.href = (to && to.charAt(0) === '/') ? to : 'dashboard.php';
    } catch (e) {
      show('Sign-in failed. Please try again.', true);
    }
  })();

  document.querySelectorAll('.social-btn').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      show('Opening ' + btn.textContent.trim() + '\u2026');
      const back = new URL(location.pathname, location.origin);
      const r = new URLSearchParams(location.search).get('redirect');
      if (r) back.searchParams.set('redirect', r);

      const { error } = await client.auth.signInWithOAuth({
        provider: btn.dataset.provider,
        options: { redirectTo: back.toString() }
      });
      if (error) show(error.message || 'Could not start sign-in.', true);
    });
  });
})();
</script>
