<?php
require_once __DIR__ . '/bootstrap.php';

/* =====================================================================
   SUPABASE-BACKED DB LAYER
   Talks to the BlueSky Supabase project (Postgres via REST/PostgREST)
   instead of a local MySQL server. Keeps the same prepare()/execute()/
   fetch()/fetchAll()/lastInsertId() interface the rest of the app
   already uses, so signup.php, login.php, dashboard.php, admin/*.php
   etc. did NOT need to change at all.
   ===================================================================== */

function get_db(): SupabaseDB {
    static $db = null;
    if ($db === null) $db = new SupabaseDB();
    return $db;
}

class SupabaseDB {
    public ?int $lastInsertIdValue = null;

    public function prepare(string $sql): SupabaseStatement {
        return new SupabaseStatement($this, $sql);
    }

    public function query(string $sql): SupabaseStatement {
        $stmt = $this->prepare($sql);
        $stmt->execute([]);
        return $stmt;
    }

    public function lastInsertId(): int {
        return (int)$this->lastInsertIdValue;
    }

    /** Raw call to Supabase's PostgREST endpoint. */
    public function request(string $method, string $path, $body = null): array {
        if (!function_exists('curl_init')) {
            http_response_code(500);
            die('The PHP curl extension is required to talk to Supabase. Ask your host to enable it.');
        }
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . $path;
        $ch = curl_init($url);
        $headers = [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            http_response_code(500);
            die('Could not reach Supabase: ' . htmlspecialchars($err));
        }
        if ($code >= 400) {
            http_response_code(500);
            die('Supabase error (' . $code . '): ' . htmlspecialchars($response));
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}

class SupabaseStatement {
    private SupabaseDB $db;
    private string $sql;
    private array $rows = [];

    public function __construct(SupabaseDB $db, string $sql) {
        $this->db = $db;
        $this->sql = trim(preg_replace('/\s+/', ' ', $sql));
    }

    public function execute(array $params = []): bool {
        $sql = $this->sql;

        // ---- users ----
        if (preg_match('/^SELECT id FROM users WHERE email = \?$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'users?select=id&email=eq.' . rawurlencode($params[0]));

        } elseif (preg_match('/^INSERT INTO users \(name, email, phone, password_hash\) VALUES/i', $sql)) {
            $res = $this->db->request('POST', 'users', [
                'name' => $params[0], 'email' => $params[1], 'phone' => $params[2], 'password_hash' => $params[3],
            ]);
            $this->db->lastInsertIdValue = $res[0]['id'] ?? null;

        } elseif (preg_match('/^SELECT id, password_hash FROM admins WHERE username = \?$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'admins?select=id,password_hash&username=eq.' . rawurlencode($params[0]));

        } elseif (preg_match('/^SELECT id, password_hash FROM users WHERE email = \?$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'users?select=id,password_hash&email=eq.' . rawurlencode($params[0]));

        } elseif (preg_match('/^SELECT id, name, email, phone, created_at FROM users WHERE id = \?$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'users?select=id,name,email,phone,created_at&id=eq.' . (int)$params[0]);

        } elseif (preg_match('/^SELECT id, username FROM admins WHERE id = \?$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'admins?select=id,username&id=eq.' . (int)$params[0]);

        // ---- orders ----
        } elseif (preg_match('/^SELECT \* FROM orders WHERE user_id = \? ORDER BY created_at DESC$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'orders?select=*&user_id=eq.' . (int)$params[0] . '&order=created_at.desc');

        } elseif (preg_match('/^SELECT \* FROM orders WHERE id = \? AND user_id = \?$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'orders?select=*&id=eq.' . (int)$params[0] . '&user_id=eq.' . (int)$params[1]);

        } elseif (preg_match("/^UPDATE orders SET status = 'paid_preparing', razorpay_payment_id = \\?, invoice_no = \\? WHERE id = \\?\$/i", $sql)) {
            $this->db->request('PATCH', 'orders?id=eq.' . (int)$params[2], [
                'status' => 'paid_preparing', 'razorpay_payment_id' => $params[0], 'invoice_no' => $params[1],
            ]);

        } elseif (preg_match("/^INSERT INTO orders \(user_id, plan, amount, bm_id, business_name, status\) VALUES \(\\?, \\?, \\?, \\?, \\?, 'pending_payment'\)\$/i", $sql)) {
            $res = $this->db->request('POST', 'orders', [
                'user_id' => (int)$params[0], 'plan' => $params[1], 'amount' => (int)$params[2],
                'bm_id' => $params[3], 'business_name' => $params[4], 'status' => 'pending_payment',
            ]);
            $this->db->lastInsertIdValue = $res[0]['id'] ?? null;

        } elseif (preg_match('/^UPDATE orders SET razorpay_order_id = \? WHERE id = \?$/i', $sql)) {
            $this->db->request('PATCH', 'orders?id=eq.' . (int)$params[1], ['razorpay_order_id' => $params[0]]);

        } elseif (preg_match('/^UPDATE orders SET slot_id = \?, ad_account_id = \?, status = \? WHERE id = \?$/i', $sql)) {
            $this->db->request('PATCH', 'orders?id=eq.' . (int)$params[3], [
                'slot_id' => $params[0], 'ad_account_id' => $params[1], 'status' => $params[2],
            ]);

        // ---- admin listing (join + optional status filter) ----
        } elseif (preg_match('/^SELECT o\.\*, u\.name AS user_name, u\.email, u\.phone FROM orders o JOIN users u ON u\.id = o\.user_id( WHERE o\.status = \?)? ORDER BY o\.created_at DESC$/i', $sql)) {
            $path = 'orders?select=*,users(name,email,phone)&order=created_at.desc';
            if (!empty($params)) $path .= '&status=eq.' . rawurlencode($params[0]);
            $raw = $this->db->request('GET', $path);
            $this->rows = array_map(function ($o) {
                $u = $o['users'] ?? [];
                $o['user_name'] = $u['name'] ?? '';
                $o['email'] = $u['email'] ?? '';
                $o['phone'] = $u['phone'] ?? '';
                unset($o['users']);
                return $o;
            }, $raw);

        } elseif (preg_match('/^SELECT status, COUNT\(\*\) c, SUM\(amount\) rev FROM orders GROUP BY status$/i', $sql)) {
            $all = $this->db->request('GET', 'orders?select=status,amount');
            $grouped = [];
            foreach ($all as $o) {
                $s = $o['status'];
                if (!isset($grouped[$s])) $grouped[$s] = ['status' => $s, 'c' => 0, 'rev' => 0];
                $grouped[$s]['c']++;
                $grouped[$s]['rev'] += (int)$o['amount'];
            }
            $this->rows = array_values($grouped);

        } elseif (preg_match("/^INSERT INTO orders \\(user_id, plan, amount, bm_id, business_name, status, razorpay_order_id\\) VALUES \\(\\?, \\?, \\?, \\?, \\?, 'pending_payment', \\?\\)\$/i", $sql)) {
            $res = $this->db->request('POST', 'orders', [
                'user_id' => (int)$params[0], 'plan' => $params[1], 'amount' => (int)$params[2],
                'bm_id' => $params[3], 'business_name' => $params[4],
                'status' => 'pending_payment', 'razorpay_order_id' => $params[5],
            ]);
            $this->db->lastInsertIdValue = $res[0]['id'] ?? null;

        } elseif (preg_match('/^SELECT \\* FROM orders WHERE razorpay_order_id = \\? AND user_id = \\?$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'orders?select=*&razorpay_order_id=eq.'
                . rawurlencode($params[0]) . '&user_id=eq.' . (int)$params[1]);

        } elseif (preg_match("/^INSERT INTO orders \\(user_id, plan, amount, bm_id, business_name, status, details, coupon_code, discount, original_amount\\) VALUES \\(\\?, \\?, \\?, \\?, \\?, 'pending_payment', \\?, \\?, \\?, \\?\\)\$/i", $sql)) {
            $res = $this->db->request('POST', 'orders', [
                'user_id' => (int)$params[0], 'plan' => $params[1], 'amount' => (int)$params[2],
                'bm_id' => $params[3], 'business_name' => $params[4], 'status' => 'pending_payment',
                'details' => $params[5], 'coupon_code' => $params[6],
                'discount' => (int)$params[7], 'original_amount' => (int)$params[8],
            ]);
            $this->db->lastInsertIdValue = $res[0]['id'] ?? null;

        } elseif (preg_match('/^SELECT \\* FROM coupons WHERE code = \\?$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'coupons?select=*&code=eq.' . rawurlencode($params[0]));

        } elseif (preg_match('/^SELECT used_count FROM coupons WHERE code = \\?$/i', $sql)) {
            $this->rows = $this->db->request('GET', 'coupons?select=used_count&code=eq.' . rawurlencode($params[0]));

        } elseif (preg_match('/^UPDATE coupons SET used_count = \\? WHERE code = \\?$/i', $sql)) {
            $this->db->request('PATCH', 'coupons?code=eq.' . rawurlencode($params[1]), ['used_count' => (int)$params[0]]);

        } elseif (preg_match('/^SELECT COUNT\\(\\*\\) AS c FROM orders WHERE invoice_no IS NOT NULL$/i', $sql)) {
            $all = $this->db->request('GET', 'orders?select=id&invoice_no=not.is.null');
            $this->rows = [['c' => count($all)]];

        } elseif (preg_match('/^SELECT COUNT\(\*\) c FROM users$/i', $sql)) {
            $all = $this->db->request('GET', 'users?select=id');
            $this->rows = [['c' => count($all)]];

        } else {
            http_response_code(500);
            die('Unrecognized query in Supabase shim: ' . htmlspecialchars($sql));
        }

        return true;
    }

    public function fetch() {
        return array_shift($this->rows) ?: false;
    }

    public function fetchAll(): array {
        return $this->rows;
    }
}
