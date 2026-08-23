/**
 * Thin wrapper over Supabase's REST (PostgREST) API.
 * The key stays on the server — it is never sent to the browser.
 */

const URL_BASE = () => (process.env.SUPABASE_URL || '').replace(/\/$/, '');
const KEY = () => process.env.SUPABASE_KEY || '';

async function request(method, path, body) {
  if (!URL_BASE() || !KEY()) {
    throw new Error('Supabase is not configured — set SUPABASE_URL and SUPABASE_KEY in .env');
  }

  const res = await fetch(`${URL_BASE()}/rest/v1/${path}`, {
    method,
    headers: {
      apikey: KEY(),
      Authorization: `Bearer ${KEY()}`,
      'Content-Type': 'application/json',
      Prefer: 'return=representation',
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  const text = await res.text();
  if (!res.ok) {
    throw new Error(`Supabase ${res.status}: ${text.slice(0, 300)}`);
  }
  return text ? JSON.parse(text) : [];
}

/* ----------------------------- users ----------------------------- */

const users = {
  async findByEmail(email) {
    const rows = await request('GET', `users?select=*&email=eq.${encodeURIComponent(email)}`);
    return rows[0] || null;
  },

  async findById(id) {
    const rows = await request('GET', `users?select=id,name,email,phone,created_at&id=eq.${Number(id)}`);
    return rows[0] || null;
  },

  async create({ name, email, phone, password_hash }) {
    const rows = await request('POST', 'users', { name, email, phone, password_hash });
    return rows[0];
  },
};

/* ----------------------------- orders ---------------------------- */

const orders = {
  async listForUser(userId) {
    return request('GET', `orders?select=*&user_id=eq.${Number(userId)}&order=created_at.desc`);
  },

  async listAll(status) {
    let path = 'orders?select=*,users(name,email,phone)&order=created_at.desc';
    if (status) path += `&status=eq.${encodeURIComponent(status)}`;
    const rows = await request('GET', path);
    return rows.map((o) => {
      const u = o.users || {};
      const { users: _drop, ...rest } = o;
      return { ...rest, user_name: u.name || '', email: u.email || '', phone: u.phone || '' };
    });
  },

  async create({ user_id, plan, amount, bm_id, business_name }) {
    const rows = await request('POST', 'orders', {
      user_id, plan, amount, bm_id, business_name, status: 'pending_payment',
    });
    return rows[0];
  },

  async update(id, patch) {
    const rows = await request('PATCH', `orders?id=eq.${Number(id)}`, patch);
    return rows[0];
  },

  async findById(id) {
    const rows = await request('GET', `orders?select=*&id=eq.${Number(id)}`);
    return rows[0] || null;
  },

  async countInvoiced() {
    const rows = await request('GET', 'orders?select=id&invoice_no=not.is.null');
    return rows.length;
  },

  async stats() {
    const all = await request('GET', 'orders?select=status,amount,user_id');
    const byStatus = {};
    let revenue = 0;
    for (const o of all) {
      byStatus[o.status] = (byStatus[o.status] || 0) + 1;
      if (o.status !== 'pending_payment' && o.status !== 'cancelled') revenue += Number(o.amount);
    }
    return { byStatus, revenue, clients: new Set(all.map((o) => o.user_id)).size };
  },
};

/* ----------------------------- admins ---------------------------- */

const admins = {
  async findByUsername(username) {
    const rows = await request('GET', `admins?select=*&username=eq.${encodeURIComponent(username)}`);
    return rows[0] || null;
  },
};

module.exports = { request, users, orders, admins };
