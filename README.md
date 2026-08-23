# BlueSky Agency — Node.js version

Website, client dashboard, Razorpay checkout and admin panel.
Runs on Node 18+ with Express, using Supabase (Postgres) for data.

## Deploy on Hostinger

1. **hPanel → Advanced → Node.js → Create Application**
   - Node version: **18 or higher**
   - Application root: the folder holding these files
   - Startup file: `server.js`

2. **Upload the code** (Git deploy or File Manager). Don't upload `node_modules`.

3. **Install dependencies** — in the Node.js panel, click **NPM Install**
   (or run `npm install` in the terminal from the app folder).

4. **Create `.env`** — copy `.env.example` to `.env` and fill in:

   | Key | Where to get it |
   |---|---|
   | `SUPABASE_URL` | Supabase → BlueSky project → Settings → API |
   | `SUPABASE_KEY` | same page, the anon/public key |
   | `RAZORPAY_KEY_ID` | Razorpay → Settings → API Keys |
   | `RAZORPAY_KEY_SECRET` | same page |
   | `SESSION_SECRET` | any long random string you invent |

   `.env` is gitignored on purpose — it holds live secrets and must never
   reach GitHub. Create it directly on the server.

5. **Start the app** from the Node.js panel.

## Admin panel

`/admin/login` — username `admin`, password `bluesky@2026`.
**Change this immediately** after the first login: generate a bcrypt hash
and update the `password_hash` cell in Supabase → Table Editor → admins.

## What lives where

```
server.js          all routes
lib/supabase.js    database access (Supabase REST)
lib/razorpay.js    order creation + signature verification
lib/helpers.js     price catalog, invoice numbers, status labels
views/             EJS templates for the logged-in pages
public/            static site (home, services, products, contact) + assets
test/              local mock of Supabase, for testing without the real one
```

## Prices

All prices live in `lib/helpers.js` (`CATALOG`). They are read server-side
on every order, so editing the amount in the browser can't change what a
customer is charged. To change a price, edit it there and redeploy.

## Local development

```bash
npm install
cp .env.example .env    # then fill it in
npm start               # http://localhost:3000
```

To try it without touching the real database, run `node test/mock-supabase.js`
in one terminal and set `SUPABASE_URL=http://127.0.0.1:54321` in `.env`.
