require('dotenv').config();

const path = require('path');
const crypto = require('crypto');
const express = require('express');
const session = require('express-session');
const bcrypt = require('bcryptjs');

const { users, orders, admins } = require('./lib/supabase');
const razorpay = require('./lib/razorpay');
const {
  catalogItem, STATUS_LABEL, STATUS_CLASS, generateInvoiceNo, formatDate, money,
} = require('./lib/helpers');

const app = express();
const PORT = process.env.PORT || 3000;

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

app.use(session({
  secret: process.env.SESSION_SECRET || crypto.randomBytes(32).toString('hex'),
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    maxAge: 1000 * 60 * 60 * 24 * 7,
  },
}));

// Values every template can use
app.use((req, res, next) => {
  res.locals.user = req.session.user || null;
  res.locals.admin = req.session.admin || null;
  res.locals.STATUS_LABEL = STATUS_LABEL;
  res.locals.STATUS_CLASS = STATUS_CLASS;
  res.locals.formatDate = formatDate;
  res.locals.money = money;
  next();
});

app.use(express.static(path.join(__dirname, 'public')));

/* ----------------------------- guards ----------------------------- */

const requireLogin = (req, res, next) => {
  if (!req.session.user) return res.redirect('/login?redirect=' + encodeURIComponent(req.originalUrl));
  next();
};

const requireAdmin = (req, res, next) => {
  if (!req.session.admin) return res.redirect('/admin/login');
  next();
};

/* ------------------------------ auth ------------------------------ */

app.get('/signup', (req, res) => res.render('signup', { error: null, values: {} }));

app.post('/signup', async (req, res) => {
  const name = (req.body.name || '').trim();
  const email = (req.body.email || '').trim().toLowerCase();
  const phone = (req.body.phone || '').trim();
  const password = req.body.password || '';
  const values = { name, email, phone };

  const fail = (error) => res.render('signup', { error, values });

  if (!name || !email || !phone || !password) return fail('Please fill in every field.');
  if (password.length < 6) return fail('Password must be at least 6 characters.');

  try {
    if (await users.findByEmail(email)) return fail('An account with this email already exists.');

    const user = await users.create({
      name, email, phone, password_hash: await bcrypt.hash(password, 10),
    });

    req.session.user = { id: user.id, name: user.name, email: user.email, phone: user.phone };
    res.redirect('/dashboard');
  } catch (err) {
    console.error('signup failed:', err.message);
    fail('Could not create your account right now. Please try again.');
  }
});

app.get('/login', (req, res) =>
  res.render('login', { error: null, email: '', redirect: req.query.redirect || '' }));

app.post('/login', async (req, res) => {
  const email = (req.body.email || '').trim().toLowerCase();
  const password = req.body.password || '';
  const redirect = req.body.redirect || '';

  const fail = () => res.render('login', { error: 'Wrong email or password.', email, redirect });

  try {
    const user = await users.findByEmail(email);
    if (!user || !(await bcrypt.compare(password, user.password_hash))) return fail();

    req.session.user = { id: user.id, name: user.name, email: user.email, phone: user.phone };
    res.redirect(redirect && redirect.startsWith('/') ? redirect : '/dashboard');
  } catch (err) {
    console.error('login failed:', err.message);
    res.render('login', { error: 'Could not log you in right now. Please try again.', email, redirect });
  }
});

app.get('/logout', (req, res) => req.session.destroy(() => res.redirect('/')));

/* --------------------------- client area -------------------------- */

app.get('/dashboard', requireLogin, async (req, res, next) => {
  try {
    res.render('dashboard', {
      orders: await orders.listForUser(req.session.user.id),
      paid: req.query.paid === '1',
    });
  } catch (err) { next(err); }
});

app.get('/checkout', requireLogin, (req, res) => {
  const slug = String(req.query.plan || req.query.item || '').toLowerCase();
  const item = catalogItem(slug);
  if (!item) return res.redirect('/services.html');

  res.render('checkout', { slug, item, razorpayKeyId: razorpay.keyId() });
});

/* ------------------------------ APIs ------------------------------ */

app.post('/api/create-order', requireLogin, async (req, res) => {
  try {
    // Price comes from the server-side catalog, never from the request body.
    const item = catalogItem(req.body.plan);
    if (!item) return res.json({ ok: false, error: 'Unknown plan or product.' });

    const order = await orders.create({
      user_id: req.session.user.id,
      plan: item.name,
      amount: item.amount,
      bm_id: (req.body.bmid || '').trim(),
      business_name: (req.body.business || '').trim(),
    });

    let rzp = null;
    try {
      rzp = await razorpay.createOrder({
        amount: item.amount,
        receipt: `order_${order.id}`,
        notes: { order_id: String(order.id), plan: item.name },
      });
    } catch (err) {
      console.error('razorpay order failed:', err.message);
    }

    if (rzp) await orders.update(order.id, { razorpay_order_id: rzp.id });

    res.json({
      ok: true,
      order_id: order.id,
      razorpay_order_id: rzp ? rzp.id : null,
      razorpay_amount: item.amount * 100,
    });
  } catch (err) {
    console.error('create-order failed:', err.message);
    res.json({ ok: false, error: 'Could not start checkout. Please try again.' });
  }
});

app.post('/api/verify-payment', requireLogin, async (req, res) => {
  try {
    const { order_id, razorpay_order_id, razorpay_payment_id, razorpay_signature } = req.body;

    if (!razorpay.verifySignature({ razorpay_order_id, razorpay_payment_id, razorpay_signature })) {
      return res.json({ ok: false, error: 'Payment verification failed.' });
    }

    // Make sure this order really belongs to the logged-in user.
    const order = await orders.findById(order_id);
    if (!order || String(order.user_id) !== String(req.session.user.id)) {
      return res.json({ ok: false, error: 'Order not found.' });
    }

    await orders.update(order_id, {
      status: 'paid_preparing',
      razorpay_payment_id,
      invoice_no: await generateInvoiceNo(),
    });

    res.json({ ok: true });
  } catch (err) {
    console.error('verify-payment failed:', err.message);
    res.json({ ok: false, error: 'Could not verify the payment. Contact support.' });
  }
});

/* ------------------------------ admin ----------------------------- */

app.get('/admin/login', (req, res) => res.render('admin-login', { error: null }));

app.post('/admin/login', async (req, res) => {
  try {
    const admin = await admins.findByUsername((req.body.username || '').trim());
    if (!admin || !(await bcrypt.compare(req.body.password || '', admin.password_hash))) {
      return res.render('admin-login', { error: 'Wrong username or password.' });
    }
    req.session.admin = { id: admin.id, username: admin.username };
    res.redirect('/admin');
  } catch (err) {
    console.error('admin login failed:', err.message);
    res.render('admin-login', { error: 'Could not log you in right now.' });
  }
});

app.get('/admin/logout', (req, res) => {
  delete req.session.admin;
  res.redirect('/admin/login');
});

app.get('/admin', requireAdmin, async (req, res, next) => {
  try {
    const status = STATUS_LABEL[req.query.status] ? req.query.status : '';
    const [list, stats] = await Promise.all([orders.listAll(status), orders.stats()]);
    res.render('admin', { orders: list, stats, filter: status || 'all' });
  } catch (err) { next(err); }
});

app.post('/admin/orders/:id', requireAdmin, async (req, res, next) => {
  try {
    const status = STATUS_LABEL[req.body.status] ? req.body.status : 'pending_payment';
    await orders.update(req.params.id, {
      slot_id: (req.body.slot_id || '').trim(),
      ad_account_id: (req.body.ad_account_id || '').trim(),
      status,
    });
    res.redirect('/admin' + (req.body.filter && req.body.filter !== 'all' ? `?status=${req.body.filter}` : ''));
  } catch (err) { next(err); }
});

/* ---------------------------- fallbacks --------------------------- */

app.use((req, res) => res.status(404).render('error', {
  title: 'Page not found',
  message: "That page doesn't exist.",
}));

app.use((err, req, res, _next) => {
  console.error(err);
  const configIssue = /not configured/i.test(err.message || '');
  res.status(configIssue ? 503 : 500).render('error', {
    title: configIssue ? 'Setup needed' : 'Something went wrong',
    message: configIssue
      ? 'The server is missing its Supabase settings. Copy .env.example to .env and fill in your keys.'
      : 'Please try again in a moment.',
  });
});

app.listen(PORT, () => console.log(`BlueSky running on http://localhost:${PORT}`));
