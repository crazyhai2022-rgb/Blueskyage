const { orders } = require('./supabase');

/**
 * Everything that can be bought, keyed by the slug used in checkout URLs.
 * `recurring` marks a monthly subscription; the rest are one-time products.
 * Prices live here so a tampered browser request can never change them.
 */
const CATALOG = {
  'basic':             { name: 'Basic Plan — Meta Agency Ad Account', amount: 1499, recurring: true },
  'pro':               { name: 'Pro Plan — Meta Agency Ad Account',   amount: 2499, recurring: true },
  'facebook-page':     { name: 'Facebook Page',                       amount: 1499, recurring: false },
  'name-change-page':  { name: 'Name Changeable Page',                amount: 1999, recurring: false },
  'old-profile':       { name: '15 Years Old Profile',                amount: 2499, recurring: false },
  'verified-profile':  { name: 'Identity Confirmed Profile',          amount: 8999, recurring: false },
  'instagram-account': { name: 'Instagram Account',                   amount: 999,  recurring: false },
};

const catalogItem = (slug) => CATALOG[String(slug || '').toLowerCase().trim()] || null;

const STATUS_LABEL = {
  pending_payment: 'Pending Payment',
  paid_preparing: 'Preparing',
  active: 'Active',
  cancelled: 'Cancelled',
};

const STATUS_CLASS = {
  pending_payment: 'badge badge-red',
  paid_preparing: 'badge badge-amber',
  active: 'badge badge-green',
  cancelled: 'badge badge-grey',
};

/** Sequential invoice number, e.g. BSA-0007. */
async function generateInvoiceNo() {
  const count = await orders.countInvoiced();
  return `BSA-${String(count + 1).padStart(4, '0')}`;
}

const formatDate = (iso) =>
  new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

const money = (n) => Number(n).toLocaleString('en-IN');

module.exports = { CATALOG, catalogItem, STATUS_LABEL, STATUS_CLASS, generateInvoiceNo, formatDate, money };
