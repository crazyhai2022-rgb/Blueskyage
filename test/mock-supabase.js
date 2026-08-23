/* Stands in for Supabase during local testing: implements just enough of
   PostgREST (eq / not.is.null / order / embedded users) for the app. */
const express = require('express');
const bcrypt = require('bcryptjs');

const db = {
  users: [],
  orders: [],
  admins: [{ id: 1, username: 'admin', password_hash: bcrypt.hashSync('bluesky@2026', 10) }],
};
let nextUser = 1, nextOrder = 1;

const app = express();
app.use(express.json());

function applyFilters(rows, query) {
  let out = [...rows];
  for (const [key, raw] of Object.entries(query)) {
    if (['select', 'order'].includes(key)) continue;
    const val = String(raw);
    if (val.startsWith('eq.')) {
      const want = val.slice(3);
      out = out.filter((r) => String(r[key]) === want);
    } else if (val === 'not.is.null') {
      out = out.filter((r) => r[key] !== null && r[key] !== undefined);
    }
  }
  if (query.order) {
    const [col, dir] = String(query.order).split('.');
    out.sort((a, b) => (String(a[col]) < String(b[col]) ? 1 : -1) * (dir === 'desc' ? 1 : -1));
  }
  return out;
}

function embedUsers(rows, select) {
  if (!select || !select.includes('users(')) return rows;
  return rows.map((o) => {
    const u = db.users.find((x) => x.id === o.user_id);
    return { ...o, users: u ? { name: u.name, email: u.email, phone: u.phone } : null };
  });
}

app.get('/rest/v1/:table', (req, res) => {
  const rows = applyFilters(db[req.params.table] || [], req.query);
  res.json(req.params.table === 'orders' ? embedUsers(rows, req.query.select) : rows);
});

app.post('/rest/v1/:table', (req, res) => {
  const t = req.params.table;
  const now = new Date().toISOString();
  if (t === 'users') {
    const row = { id: nextUser++, created_at: now, ...req.body };
    db.users.push(row);
    return res.status(201).json([row]);
  }
  if (t === 'orders') {
    const row = {
      id: nextOrder++, invoice_no: null, slot_id: null, ad_account_id: null,
      razorpay_order_id: null, razorpay_payment_id: null,
      created_at: now, updated_at: now, ...req.body,
    };
    db.orders.push(row);
    return res.status(201).json([row]);
  }
  res.status(400).json({ message: 'unsupported' });
});

app.patch('/rest/v1/:table', (req, res) => {
  const rows = applyFilters(db[req.params.table] || [], req.query);
  rows.forEach((r) => Object.assign(r, req.body, { updated_at: new Date().toISOString() }));
  res.json(rows);
});

const server = app.listen(54321, () => console.log('mock supabase on 54321'));
module.exports = { db, server };
