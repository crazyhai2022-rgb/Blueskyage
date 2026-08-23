const crypto = require('crypto');

const keyId = () => process.env.RAZORPAY_KEY_ID || '';
const keySecret = () => process.env.RAZORPAY_KEY_SECRET || '';

/** True once real keys are present in .env. */
const isConfigured = () =>
  keyId() && keySecret() && !keyId().startsWith('YOUR_') && !keySecret().startsWith('YOUR_');

/**
 * Create an order on Razorpay. Returns null when keys aren't set yet, so the
 * checkout page can show a clear message instead of failing silently.
 */
async function createOrder({ amount, receipt, notes }) {
  if (!isConfigured()) return null;

  const auth = Buffer.from(`${keyId()}:${keySecret()}`).toString('base64');
  const res = await fetch('https://api.razorpay.com/v1/orders', {
    method: 'POST',
    headers: { Authorization: `Basic ${auth}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({
      amount: amount * 100, // Razorpay works in paise
      currency: 'INR',
      receipt,
      notes,
    }),
  });

  const data = await res.json();
  if (!res.ok) throw new Error(data?.error?.description || 'Razorpay order creation failed');
  return data;
}

/**
 * Confirm the payment really came from Razorpay.
 * timingSafeEqual avoids leaking information through comparison timing.
 */
function verifySignature({ razorpay_order_id, razorpay_payment_id, razorpay_signature }) {
  if (!isConfigured() || !razorpay_signature) return false;

  const expected = crypto
    .createHmac('sha256', keySecret())
    .update(`${razorpay_order_id}|${razorpay_payment_id}`)
    .digest('hex');

  const a = Buffer.from(expected, 'utf8');
  const b = Buffer.from(String(razorpay_signature), 'utf8');
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

module.exports = { isConfigured, createOrder, verifySignature, keyId };
