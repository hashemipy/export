<?php
/**
 * سیستم هماهنگ‌سازی موجودی بین دو سایت
 * Stock Synchronization System
 *
 * معماری:
 *  - جدول pie_stock_map   : نگاشت ID محصول/متغیر سایت ۱ به سایت ۲
 *  - جدول pie_stock_queue : صف تغییرات موجودی که باید ارسال شوند
 *
 * جریان push فوری (هنگام خرید):
 *  woocommerce_reduce_order_stock → push_stock_change()
 *    → اگر موفق: sync انجام شد
 *    → اگر ناموفق (هاست مقابل قطع): در صف با retry_after ذخیره شود
 *
 * جریان retry (Cron هر ۱۵ دقیقه):
 *  → فقط ردیف‌های صف که retry_after گذشته پردازش شوند
 *  → موجودی هر دو طرف خوانده شود
 *  → کمترین موجودی به عنوان مرجع انتخاب شود (conflict resolution)
 *
 * جلوگیری از Ping-Pong:
 *  → قبل از push: is_locked=1 + locked_until=now+30s در جدول map ثبت شود
 *  → سایت گیرنده هنگام آپدیت موجودی، وجود lock را بررسی می‌کند
 *  → اگر lock بود: push برگشتی انجام نمی‌شود
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIE_StockSync {

    private static $instance = null;
    private $settings        = null;
    private $logging         = null;

    /** نام جداول در database */
    const TABLE_MAP   = 'pie_stock_map';
    const TABLE_QUEUE = 'pie_stock_queue';

    /** مدت lock برای جلوگیری از Ping-Pong (ثانیه) */
    const LOCK_TTL = 30;

    /** تعداد دقیقه تا retry بعد از خطا */
    const RETRY_DELAY_MINUTES = 15;

    /** نام Cron event */
    const CRON_HOOK = 'pie_stock_sync_retry';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->settings = PIE_Settings::get_instance();
        $this->logging  = PIE_Logging::get_instance();

        // hook کاهش موجودی هنگام ثبت سفارش
        add_action('woocommerce_reduce_order_stock', [$this, 'on_order_stock_reduced'], 10, 1);

        // hook تغییر دستی موجودی در ادمین
        add_action('woocommerce_product_set_stock',   [$this, 'on_stock_changed'], 10, 1);
        add_action('woocommerce_variation_set_stock', [$this, 'on_stock_changed'], 10, 1);

        // اضافه کردن interval سفارشی ۱۵ دقیقه‌ای
        // باید قبل از wp_schedule_event ثبت شود
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Cron برای retry ناموفق‌ها
        add_action(self::CRON_HOOK, [$this, 'process_retry_queue']);

        // ثبت Cron schedule بعد از init تا مطمئن شویم interval ثبت شده است
        add_action('init', [$this, 'maybe_schedule_cron'], 5);

        // AJAX handlers
        add_action('wp_ajax_pie_stock_get_map',      [$this, 'ajax_get_map']);
        add_action('wp_ajax_pie_stock_add_map',      [$this, 'ajax_add_map']);
        add_action('wp_ajax_pie_stock_delete_map',   [$this, 'ajax_delete_map']);
        add_action('wp_ajax_pie_stock_force_sync',   [$this, 'ajax_force_sync']);
        add_action('wp_ajax_pie_stock_fetch_remote', [$this, 'ajax_fetch_remote_products']);
        add_action('wp_ajax_pie_get_variations',     [$this, 'ajax_get_variations']);
    }

    /**
     * ثبت Cron schedule در هنگام init
     * فراخوانی در constructor خطرناک است چون interval filter هنوز اجرا نشده
     */
    public function maybe_schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'pie_every_15min', self::CRON_HOOK);
        }
    }

    // -------------------------------------------------------------------------
    // ساخت جداول database
    // -------------------------------------------------------------------------

    /**
     * ایجاد جداول در زمان فعال‌سازی پلاگین
     */
    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $map     = $wpdb->prefix . self::TABLE_MAP;
        $queue   = $wpdb->prefix . self::TABLE_QUEUE;

        $sql_map = "CREATE TABLE IF NOT EXISTS {$map} (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            s1_product_id       BIGINT UNSIGNED NOT NULL COMMENT 'Product ID in Site 1',
            s2_product_id       BIGINT UNSIGNED NOT NULL COMMENT 'Product ID in Site 2',
            s1_variation_id     BIGINT UNSIGNED DEFAULT NULL COMMENT 'Variation ID in Site 1 (null = simple)',
            s2_variation_id     BIGINT UNSIGNED DEFAULT NULL COMMENT 'Variation ID in Site 2 (null = simple)',
            product_name        VARCHAR(255)    DEFAULT '' COMMENT 'Product name for display',
            variation_attrs     VARCHAR(500)    DEFAULT '' COMMENT 'e.g. رنگ:قرمز|سایز:40',
            is_locked           TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Lock during push to prevent Ping-Pong',
            locked_until        DATETIME        DEFAULT NULL,
            last_synced_stock   BIGINT          DEFAULT NULL COMMENT 'Last confirmed synced stock',
            last_sync_at        DATETIME        DEFAULT NULL,
            created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_s1_product    (s1_product_id),
            INDEX idx_s1_variation  (s1_variation_id),
            INDEX idx_s2_product    (s2_product_id),
            INDEX idx_s2_variation  (s2_variation_id)
        ) {$charset};";

        $sql_queue = "CREATE TABLE IF NOT EXISTS {$queue} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            map_id          BIGINT UNSIGNED NOT NULL COMMENT 'FK to pie_stock_map',
            new_stock       BIGINT          NOT NULL COMMENT 'New stock quantity to set',
            direction       ENUM('s1_to_s2','s2_to_s1') NOT NULL COMMENT 'Which site pushes to which',
            status          ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
            retry_count     TINYINT UNSIGNED NOT NULL DEFAULT 0,
            retry_after     DATETIME        DEFAULT NULL COMMENT 'Earliest time for next retry',
            error_message   TEXT            DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_map_id        (map_id),
            INDEX idx_status_retry  (status, retry_after)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_map);
        dbDelta($sql_queue);
    }

    // -------------------------------------------------------------------------
    // Cron
    // -------------------------------------------------------------------------

    public function add_cron_interval($schedules) {
        $schedules['pie_every_15min'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => 'هر ۱۵ دقیقه (PIE Stock Sync)',
        ];
        return $schedules;
    }

    // -------------------------------------------------------------------------
    // Hooks موجودی
    // -------------------------------------------------------------------------

    /**
     * هنگام کاهش موجودی ناشی از سفارش
     * $order شیء WC_Order است
     */
    public function on_order_stock_reduced($order) {
        if (!($order instanceof WC_Order)) {
            return;
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            if ($product->managing_stock()) {
                $this->schedule_stock_push($product);
            } elseif ($product->get_parent_id()) {
                // متغیر - موجودی از parent خوانده می‌شود اگر variation manage نمی‌کند
                $parent = wc_get_product($product->get_parent_id());
                if ($parent && $parent->managing_stock()) {
                    $this->schedule_stock_push($parent);
                }
            }
        }
    }

    /**
     * هنگام تغییر دستی موجودی (ادمین)
     * $product شیء WC_Product یا WC_Product_Variation است
     */
    public function on_stock_changed($product) {
        if (!$product || !$product->managing_stock()) {
            return;
        }
        $this->schedule_stock_push($product);
    }

    /**
     * ایجاد یک push در صف
     * اگر صف قبلاً برای این map_id وجود داشت، آن را بروز کن (نه duplicate)
     */
    private function schedule_stock_push($product) {
        global $wpdb;

        $config = $this->settings->get_config();

        // تعیین direction بر اساس نقش این سایت
        if ($config['site_role'] === 'site1') {
            $direction        = 's1_to_s2';
            $local_product_id = $product->get_parent_id() ?: $product->get_id();
            $local_var_id     = $product->is_type('variation') ? $product->get_id() : null;
            $map_row = $this->get_map_by_s1($local_product_id, $local_var_id);
        } elseif ($config['site_role'] === 'site2') {
            $direction        = 's2_to_s1';
            $local_product_id = $product->get_parent_id() ?: $product->get_id();
            $local_var_id     = $product->is_type('variation') ? $product->get_id() : null;
            $map_row = $this->get_map_by_s2($local_product_id, $local_var_id);
        } else {
            return;
        }

        if (!$map_row) {
            // این محصول در mapping نیست - نادیده بگیر
            return;
        }

        // بررسی lock - اگر lock فعال باشد، این تغییر از طرف sync ماست (Ping-Pong)
        if ($map_row->is_locked && strtotime($map_row->locked_until) > time()) {
            return;
        }

        $new_stock = (int) $product->get_stock_quantity();
        $table_q   = $wpdb->prefix . self::TABLE_QUEUE;

        // اگر قبلاً در صف هست و هنوز pending، فقط موجودی را بروز کن
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_q} WHERE map_id = %d AND status = 'pending' AND direction = %s LIMIT 1",
            $map_row->id, $direction
        ));

        if ($existing) {
            $wpdb->update($table_q, ['new_stock' => $new_stock, 'retry_after' => null], ['id' => $existing->id]);
        } else {
            $wpdb->insert($table_q, [
                'map_id'      => $map_row->id,
                'new_stock'   => $new_stock,
                'direction'   => $direction,
                'status'      => 'pending',
                'retry_after' => null,
            ]);

            // push فوری
            $queue_id = $wpdb->insert_id;
            $this->process_single_queue_item($queue_id);
        }
    }

    // -------------------------------------------------------------------------
    // پردازش صف
    // -------------------------------------------------------------------------

    /**
     * پردازش صف retry (Cron)
     * فقط ردیف‌هایی که retry_after گذشته یا null است
     */
    public function process_retry_queue() {
        global $wpdb;

        $table_q = $wpdb->prefix . self::TABLE_QUEUE;
        $now     = current_time('mysql');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table_q}
             WHERE status IN ('pending','failed')
               AND (retry_after IS NULL OR retry_after <= %s)
             ORDER BY created_at ASC
             LIMIT 50",
            $now
        ));

        foreach ($rows as $row) {
            $this->process_single_queue_item($row->id);
        }
    }

    /**
     * پردازش یک آیتم از صف
     */
    private function process_single_queue_item($queue_id) {
        global $wpdb;

        $table_q   = $wpdb->prefix . self::TABLE_QUEUE;
        $table_map = $wpdb->prefix . self::TABLE_MAP;

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT q.*, m.s1_product_id, m.s2_product_id, m.s1_variation_id, m.s2_variation_id,
                    m.product_name, m.is_locked, m.locked_until
             FROM {$table_q} q
             JOIN {$table_map} m ON m.id = q.map_id
             WHERE q.id = %d",
            $queue_id
        ));

        if (!$item || $item->status === 'done') {
            return;
        }

        // علامت processing
        $wpdb->update($table_q, ['status' => 'processing'], ['id' => $queue_id]);

        $config = $this->settings->get_config();
        $result = $this->push_stock_to_remote($item, $config);

        if ($result['success']) {
            // ثبت lock در map برای جلوگیری از Ping-Pong
            $wpdb->update(
                $table_map,
                [
                    'is_locked'         => 1,
                    'locked_until'      => date('Y-m-d H:i:s', time() + self::LOCK_TTL),
                    'last_synced_stock'  => $item->new_stock,
                    'last_sync_at'      => current_time('mysql'),
                ],
                ['id' => $item->map_id]
            );

            $wpdb->update($table_q, ['status' => 'done'], ['id' => $queue_id]);

            $this->logging->log('stock_sync', 'success',
                "موجودی '{$item->product_name}' به {$item->new_stock} sync شد (map_id: {$item->map_id})"
            );

        } elseif (!empty($result['conflict'])) {
            // conflict: موجودی سایت مقابل کمتر بود - آن را مرجع قرار بده
            // موجودی local را هم اصلاح کن
            $authoritative = $result['authoritative_stock'];

            // تعیین product/variation local
            if ($item->direction === 's1_to_s2') {
                $local_id = $item->s1_variation_id ?: $item->s1_product_id;
            } else {
                $local_id = $item->s2_variation_id ?: $item->s2_product_id;
            }

            $local_product = wc_get_product($local_id);
            if ($local_product) {
                // lock قبل از set تا hook on_stock_changed دوباره push نزند
                $wpdb->update($table_map, [
                    'is_locked'    => 1,
                    'locked_until' => date('Y-m-d H:i:s', time() + self::LOCK_TTL),
                ], ['id' => $item->map_id]);

                wc_update_product_stock($local_product, $authoritative, 'set');
            }

            $wpdb->update($table_q, [
                'status'        => 'done',
                'error_message' => $result['error'],
            ], ['id' => $queue_id]);

            // به‌روز کردن last_synced_stock
            $wpdb->update($table_map, [
                'last_synced_stock' => $authoritative,
                'last_sync_at'      => current_time('mysql'),
            ], ['id' => $item->map_id]);

            $this->logging->log('stock_sync', 'success',
                "Conflict resolved برای '{$item->product_name}': موجودی مرجع {$authoritative} در هر دو سایت اعمال شد"
            );

        } else {
            // ناموفق - برنامه‌ریزی retry با exponential backoff
            $retry_count = intval($item->retry_count) + 1;
            $retry_after = date('Y-m-d H:i:s', time() + (self::RETRY_DELAY_MINUTES * 60 * min($retry_count, 4)));

            $wpdb->update($table_q, [
                'status'        => 'failed',
                'retry_count'   => $retry_count,
                'retry_after'   => $retry_after,
                'error_message' => $result['error'],
            ], ['id' => $queue_id]);

            $this->logging->log('stock_sync', 'failed',
                "خطا در sync موجودی '{$item->product_name}': {$result['error']} (retry #{$retry_count} در {$retry_after})"
            );
        }
    }

    /**
     * ارسال تغییر موجودی به سایت مقابل از طریق API
     * هنگام قطعی هاست: موجودی هر دو طرف خوانده شود، کمترین مرجع باشد
     */
    private function push_stock_to_remote($item, $config) {
        $remote_url    = rtrim($config['remote_site_url'], '/');
        $api_key       = $config['remote_api_key'];
        $api_secret    = $config['remote_api_secret'];

        if (empty($remote_url) || empty($api_key)) {
            return ['success' => false, 'error' => 'تنظیمات API ناقص است'];
        }

        // تعیین product_id و variation_id در سایت مقصد
        if ($item->direction === 's1_to_s2') {
            $remote_product_id   = $item->s2_product_id;
            $remote_variation_id = $item->s2_variation_id;
        } else {
            $remote_product_id   = $item->s1_product_id;
            $remote_variation_id = $item->s1_variation_id;
        }

        $endpoint = $remote_url . '/wp-json/pie/v1/update-stock';
        $payload  = [
            'product_id'   => $remote_product_id,
            'variation_id' => $remote_variation_id,
            'new_stock'    => intval($item->new_stock),
            'map_id'       => $item->map_id,
            'direction'    => $item->direction,
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 15,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("{$api_key}:{$api_secret}"),
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && !empty($body['success'])) {
            return ['success' => true];
        }

        // conflict 409: سایت مقابل موجودی کمتری دارد (در زمان قطعی هاست فروش داشته)
        // موجودی کمتر را به عنوان مرجع می‌گیریم و local را هم اصلاح می‌کنیم
        if ($code === 409 && isset($body['current_stock'])) {
            $authoritative_stock = intval($body['current_stock']);
            return [
                'success'            => false,
                'conflict'           => true,
                'authoritative_stock' => $authoritative_stock,
                'error'              => "Conflict resolved: موجودی مرجع {$authoritative_stock}",
            ];
        }

        $error_msg = $body['message'] ?? "HTTP {$code}";
        return ['success' => false, 'error' => $error_msg];
    }

    // -------------------------------------------------------------------------
    // Mapping helpers
    // -------------------------------------------------------------------------

    private function get_map_by_s1($product_id, $variation_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MAP;

        if ($variation_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE s1_product_id = %d AND s1_variation_id = %d LIMIT 1",
                $product_id, $variation_id
            ));
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE s1_product_id = %d AND s1_variation_id IS NULL LIMIT 1",
            $product_id
        ));
    }

    private function get_map_by_s2($product_id, $variation_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MAP;

        if ($variation_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE s2_product_id = %d AND s2_variation_id = %d LIMIT 1",
                $product_id, $variation_id
            ));
        }
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE s2_product_id = %d AND s2_variation_id IS NULL LIMIT 1",
            $product_id
        ));
    }

    /**
     * ثبت خودکار mapping هنگام ارسال محصول از سایت ۱ به ۲
     * این متد از Transfer.php فراخوانی می‌شود
     *
     * $s1_product_id  : ID محصول در سایت ۱
     * $s2_product_id  : ID محصول تازه ایجاد شده در سایت ۲ (از response API)
     * $s1_variation_id: ID متغیر در سایت ۱ (null برای محصول ساده)
     * $s2_variation_id: ID متغیر در سایت ۲ (null برای محصول ساده)
     */
    public function register_mapping($s1_product_id, $s2_product_id, $s1_variation_id = null, $s2_variation_id = null, $product_name = '', $variation_attrs = '') {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MAP;

        // بررسی وجود قبلی
        $existing = $this->get_map_by_s1($s1_product_id, $s1_variation_id);
        if ($existing) {
            // بروز رسانی s2 IDs (ممکن است محصول دوباره ارسال شده باشد)
            $wpdb->update($table, [
                's2_product_id'   => $s2_product_id,
                's2_variation_id' => $s2_variation_id,
                'product_name'    => $product_name,
                'variation_attrs' => $variation_attrs,
            ], ['id' => $existing->id]);
            return $existing->id;
        }

        $wpdb->insert($table, [
            's1_product_id'   => $s1_product_id,
            's2_product_id'   => $s2_product_id,
            's1_variation_id' => $s1_variation_id,
            's2_variation_id' => $s2_variation_id,
            'product_name'    => $product_name,
            'variation_attrs' => $variation_attrs,
        ]);

        return $wpdb->insert_id;
    }

    /**
     * بررسی و unlock کردن map هایی که lock منقضی شده
     */
    public function cleanup_expired_locks() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_MAP;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET is_locked=0, locked_until=NULL WHERE is_locked=1 AND locked_until < %s",
            current_time('mysql')
        ));
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /**
     * دریافت جدول mapping برای نمایش در پنل ادمین
     */
    public function ajax_get_map() {
        check_ajax_referer('pie_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی ندارید');
        }

        global $wpdb;
        $table_map   = $wpdb->prefix . self::TABLE_MAP;
        $table_queue = $wpdb->prefix . self::TABLE_QUEUE;

        $rows = $wpdb->get_results(
            "SELECT m.*,
                    (SELECT COUNT(*) FROM {$table_queue} q WHERE q.map_id=m.id AND q.status IN ('pending','failed')) as pending_count
             FROM {$table_map} m
             ORDER BY m.product_name ASC, m.variation_attrs ASC"
        );

        wp_send_json_success(['rows' => $rows]);
    }

    /**
     * افزودن دستی یک ردیف به mapping
     */
    public function ajax_add_map() {
        check_ajax_referer('pie_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی ندارید');
        }

        $s1_product_id   = intval($_POST['s1_product_id']   ?? 0);
        $s2_product_id   = intval($_POST['s2_product_id']   ?? 0);
        $s1_variation_id = intval($_POST['s1_variation_id'] ?? 0) ?: null;
        $s2_variation_id = intval($_POST['s2_variation_id'] ?? 0) ?: null;

        if (!$s1_product_id || !$s2_product_id) {
            wp_send_json_error('ID محصول الزامی است');
        }

        // خواندن نام محصول از WooCommerce
        $product      = wc_get_product($s1_product_id);
        $product_name = $product ? $product->get_name() : "محصول #{$s1_product_id}";

        $variation_attrs = '';
        if ($s1_variation_id) {
            $variation = wc_get_product($s1_variation_id);
            if ($variation) {
                $attrs = [];
                foreach ($variation->get_variation_attributes() as $attr => $val) {
                    $attrs[] = wc_attribute_label(str_replace('attribute_', '', $attr)) . ':' . $val;
                }
                $variation_attrs = implode('|', $attrs);
            }
        }

        $id = $this->register_mapping($s1_product_id, $s2_product_id, $s1_variation_id, $s2_variation_id, $product_name, $variation_attrs);

        wp_send_json_success(['id' => $id, 'message' => 'mapping ثبت شد']);
    }

    /**
     * حذف یک ردیف از mapping
     */
    public function ajax_delete_map() {
        check_ajax_referer('pie_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی ندارید');
        }

        $id = intval($_POST['map_id'] ?? 0);
        if (!$id) {
            wp_send_json_error('ID نامعتبر');
        }

        global $wpdb;
        $wpdb->delete($wpdb->prefix . self::TABLE_MAP,   ['id' => $id]);
        $wpdb->delete($wpdb->prefix . self::TABLE_QUEUE, ['map_id' => $id]);

        wp_send_json_success(['message' => 'mapping حذف شد']);
    }

    /**
     * force sync دستی یک ردیف
     */
    public function ajax_force_sync() {
        check_ajax_referer('pie_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی ندارید');
        }

        $map_id = intval($_POST['map_id'] ?? 0);
        if (!$map_id) {
            wp_send_json_error('map_id نامعتبر');
        }

        global $wpdb;
        $table_map = $wpdb->prefix . self::TABLE_MAP;
        $table_q   = $wpdb->prefix . self::TABLE_QUEUE;
        $map_row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_map} WHERE id=%d", $map_id));

        if (!$map_row) {
            wp_send_json_error('mapping پیدا نشد');
        }

        $config = $this->settings->get_config();

        // خواندن موجودی فعلی محصول local
        if ($config['site_role'] === 'site1') {
            $local_id  = $map_row->s1_variation_id ?: $map_row->s1_product_id;
            $direction = 's1_to_s2';
        } else {
            $local_id  = $map_row->s2_variation_id ?: $map_row->s2_product_id;
            $direction = 's2_to_s1';
        }

        $product = wc_get_product($local_id);
        if (!$product) {
            wp_send_json_error('محصول local پیدا نشد');
        }

        $new_stock = (int) $product->get_stock_quantity();

        // درج در صف و پردازش فوری
        $wpdb->insert($table_q, [
            'map_id'    => $map_id,
            'new_stock' => $new_stock,
            'direction' => $direction,
            'status'    => 'pending',
        ]);
        $queue_id = $wpdb->insert_id;
        $this->process_single_queue_item($queue_id);

        $item = $wpdb->get_row($wpdb->prepare("SELECT status, error_message FROM {$table_q} WHERE id=%d", $queue_id));

        if ($item->status === 'done') {
            wp_send_json_success(['message' => "sync موجودی {$new_stock} موفق بود"]);
        } else {
            wp_send_json_error($item->error_message ?? 'sync ناموفق بود');
        }
    }

    /**
     * دریافت لیست محصولات سایت مقابل از API (برای mapping دستی)
     */
    public function ajax_fetch_remote_products() {
        check_ajax_referer('pie_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی ندارید');
        }

        $config = $this->settings->get_config();
        $remote_url = rtrim($config['remote_site_url'], '/');
        $api_key    = $config['remote_api_key'];
        $api_secret = $config['remote_api_secret'];

        if (empty($remote_url) || empty($api_key)) {
            wp_send_json_error('تنظیمات API ناقص است');
        }

        $endpoint = $remote_url . '/wp-json/pie/v1/list-products';
        $response = wp_remote_get($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$api_key}:{$api_secret}"),
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['products'])) {
            wp_send_json_error('خطا در دریافت از سایت مقابل: HTTP ' . $code);
        }

        wp_send_json_success(['products' => $body['products']]);
    }

    // -------------------------------------------------------------------------
    // رندر صفحه ادمین mapping
    // -------------------------------------------------------------------------

    public function render_mapping_page() {
        $config = $this->settings->get_config();

        // لیست محصولات local
        $local_products = $this->get_local_products_for_select();
        ?>
        <div class="wrap">
            <h1>هماهنگ‌سازی موجودی</h1>
            <p style="color:#555;">
                این صفحه محصولات سایت ۱ و سایت ۲ را به هم نگاشت می‌کند.
                هر بار که موجودی در یک سایت تغییر کند، سایت مقابل به‌روز می‌شود.
            </p>

            <!-- افزودن mapping دستی -->
            <div style="border:1px solid #ddd;padding:20px;border-radius:5px;margin-bottom:25px;background:#fff;">
                <h2 style="margin-top:0;">افزودن نگاشت دستی</h2>
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="padding:8px;width:30%;">
                            <label><strong>محصول / متغیر - سایت ۱</strong></label><br>
                            <select id="pie-s1-product" style="width:100%;margin-top:4px;">
                                <option value="">انتخاب محصول...</option>
                                <?php foreach ($local_products as $p): ?>
                                    <option value="<?php echo esc_attr($p['product_id']); ?>"
                                            data-type="<?php echo esc_attr($p['type']); ?>">
                                        <?php echo esc_html($p['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="pie-s1-variation" style="width:100%;margin-top:6px;display:none;">
                                <option value="">انتخاب متغیر...</option>
                            </select>
                        </td>
                        <td style="padding:8px;width:10%;text-align:center;font-size:22px;color:#999;">
                            &#8644;
                        </td>
                        <td style="padding:8px;width:30%;">
                            <label><strong>محصول / متغیر - سایت ۲</strong></label><br>
                            <select id="pie-s2-product" style="width:100%;margin-top:4px;">
                                <option value="">ابتدا دریافت کنید...</option>
                            </select>
                            <select id="pie-s2-variation" style="width:100%;margin-top:6px;display:none;">
                                <option value="">انتخاب متغیر...</option>
                            </select>
                            <button id="pie-fetch-remote-btn" class="button" style="margin-top:6px;">
                                دریافت محصولات سایت ۲
                            </button>
                        </td>
                        <td style="padding:8px;width:30%;text-align:center;">
                            <button id="pie-add-map-btn" class="button button-primary" style="padding:8px 20px;">
                                ثبت نگاشت
                            </button>
                            <span id="pie-add-map-status" style="display:block;margin-top:8px;"></span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- جدول mapping‌های موجود -->
            <div style="border:1px solid #ddd;padding:20px;border-radius:5px;background:#fff;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                    <h2 style="margin:0;">جدول نگاشت‌ها</h2>
                    <button id="pie-reload-map-btn" class="button">بارگذاری مجدد</button>
                </div>
                <div id="pie-map-table-wrapper">
                    <p style="color:#999;">در حال بارگذاری...</p>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($) {
            const nonce = '<?php echo wp_create_nonce('pie_nonce'); ?>';

            // --- بارگذاری جدول mapping ---
            function loadMapTable() {
                $('#pie-map-table-wrapper').html('<p style="color:#999;">در حال بارگذاری...</p>');
                $.post(ajaxurl, { action: 'pie_stock_get_map', nonce }, function(res) {
                    if (!res.success) { $('#pie-map-table-wrapper').html('<p style="color:red;">خطا در بارگذاری</p>'); return; }
                    const rows = res.data.rows;
                    if (!rows.length) {
                        $('#pie-map-table-wrapper').html('<p style="color:#999;">هیچ نگاشتی ثبت نشده است.</p>');
                        return;
                    }
                    let html = `<table class="wp-list-table widefat fixed striped" style="border-collapse:collapse;">
                        <thead><tr>
                            <th>محصول</th>
                            <th>متغیر</th>
                            <th>سایت ۱ ID</th>
                            <th>سایت ۲ ID</th>
                            <th>آخرین sync</th>
                            <th>موجودی sync</th>
                            <th>صف</th>
                            <th>عملیات</th>
                        </tr></thead><tbody>`;
                    rows.forEach(function(r) {
                        const isVar = r.s1_variation_id;
                        const syncAt = r.last_sync_at ? r.last_sync_at.substring(0,16) : '-';
                        const pendingBadge = r.pending_count > 0
                            ? `<span style="background:#ff9800;color:#fff;padding:2px 7px;border-radius:10px;font-size:11px;">${r.pending_count}</span>`
                            : '<span style="color:#999;font-size:11px;">-</span>';
                        html += `<tr>
                            <td>${r.product_name || '-'}</td>
                            <td style="font-size:12px;color:#555;">${r.variation_attrs || (isVar ? 'متغیر' : 'ساده')}</td>
                            <td>${r.s1_product_id}${isVar ? ' / ' + r.s1_variation_id : ''}</td>
                            <td>${r.s2_product_id}${r.s2_variation_id ? ' / ' + r.s2_variation_id : ''}</td>
                            <td style="font-size:12px;">${syncAt}</td>
                            <td style="text-align:center;">${r.last_synced_stock !== null ? r.last_synced_stock : '-'}</td>
                            <td style="text-align:center;">${pendingBadge}</td>
                            <td>
                                <button class="button button-small pie-force-sync-btn" data-id="${r.id}" style="margin-left:5px;">sync</button>
                                <button class="button button-small pie-delete-map-btn" data-id="${r.id}" style="color:#c62828;border-color:#c62828;">حذف</button>
                            </td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    $('#pie-map-table-wrapper').html(html);
                });
            }
            loadMapTable();
            $('#pie-reload-map-btn').on('click', loadMapTable);

            // --- دریافت متغیرهای محصول سایت ۱ ---
            $('#pie-s1-product').on('change', function() {
                const pid = $(this).val();
                const type = $(this).find(':selected').data('type');
                $('#pie-s1-variation').hide().html('<option value="">انتخاب متغیر...</option>');
                if (!pid || type !== 'variable') return;
                $.post(ajaxurl, { action: 'pie_get_variations', product_id: pid, nonce }, function(res) {
                    if (!res.success) return;
                    res.data.variations.forEach(function(v) {
                        $('#pie-s1-variation').append(`<option value="${v.id}">${v.label}</option>`);
                    });
                    $('#pie-s1-variation').show();
                });
            });

            // --- دریافت محصولات سایت ۲ ---
            $('#pie-fetch-remote-btn').on('click', function() {
                $(this).prop('disabled', true).text('در حال دریافت...');
                $.post(ajaxurl, { action: 'pie_stock_fetch_remote', nonce }, function(res) {
                    $('#pie-fetch-remote-btn').prop('disabled', false).text('دریافت محصولات سایت ۲');
                    if (!res.success) { alert('خطا: ' + res.data); return; }
                    const products = res.data.products;
                    $('#pie-s2-product').html('<option value="">انتخاب محصول...</option>');
                    products.forEach(function(p) {
                        $('#pie-s2-product').append(`<option value="${p.product_id}" data-type="${p.type}">${p.label}</option>`);
                    });
                    // ذخیره در حافظه برای دسترسی به متغیرها
                    window._pie_remote_products = products;
                });
            });

            // --- نمایش متغیرهای سایت ۲ ---
            $('#pie-s2-product').on('change', function() {
                const pid = parseInt($(this).val());
                const type = $(this).find(':selected').data('type');
                $('#pie-s2-variation').hide().html('<option value="">انتخاب متغیر...</option>');
                if (!pid || type !== 'variable') return;
                const remoteProducts = window._pie_remote_products || [];
                const product = remoteProducts.find(p => p.product_id == pid);
                if (product && product.variations) {
                    product.variations.forEach(function(v) {
                        $('#pie-s2-variation').append(`<option value="${v.id}">${v.label}</option>`);
                    });
                    $('#pie-s2-variation').show();
                }
            });

            // --- ثبت mapping ---
            $('#pie-add-map-btn').on('click', function() {
                const s1_pid = $('#pie-s1-product').val();
                const s1_vid = $('#pie-s1-variation').is(':visible') ? $('#pie-s1-variation').val() : '';
                const s2_pid = $('#pie-s2-product').val();
                const s2_vid = $('#pie-s2-variation').is(':visible') ? $('#pie-s2-variation').val() : '';

                if (!s1_pid || !s2_pid) { alert('لطفا محصول هر دو سایت را انتخاب کنید'); return; }

                $(this).prop('disabled', true);
                $.post(ajaxurl, {
                    action: 'pie_stock_add_map', nonce,
                    s1_product_id: s1_pid, s1_variation_id: s1_vid,
                    s2_product_id: s2_pid, s2_variation_id: s2_vid,
                }, function(res) {
                    $('#pie-add-map-btn').prop('disabled', false);
                    if (res.success) {
                        $('#pie-add-map-status').html('<span style="color:green;">نگاشت ثبت شد</span>');
                        loadMapTable();
                    } else {
                        $('#pie-add-map-status').html('<span style="color:red;">' + res.data + '</span>');
                    }
                });
            });

            // --- force sync ---
            $(document).on('click', '.pie-force-sync-btn', function() {
                const id = $(this).data('id');
                $(this).prop('disabled', true).text('...');
                $.post(ajaxurl, { action: 'pie_stock_force_sync', map_id: id, nonce }, function(res) {
                    if (res.success) {
                        alert(res.data.message);
                    } else {
                        alert('خطا: ' + res.data);
                    }
                    loadMapTable();
                });
            });

            // --- حذف mapping ---
            $(document).on('click', '.pie-delete-map-btn', function() {
                if (!confirm('این نگاشت و صف مرتبط حذف شود؟')) return;
                const id = $(this).data('id');
                $.post(ajaxurl, { action: 'pie_stock_delete_map', map_id: id, nonce }, function(res) {
                    if (res.success) {
                        loadMapTable();
                    } else {
                        alert('خطا: ' + res.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * دریافت متغیرهای یک محصول variable برای dropdown
     */
    public function ajax_get_variations() {
        check_ajax_referer('pie_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی ندارید');
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        $product    = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error('محصول متغیر پیدا نشد');
        }

        $variations = [];
        foreach ($product->get_children() as $var_id) {
            $variation = wc_get_product($var_id);
            if (!$variation) continue;

            $attrs = [];
            foreach ($variation->get_variation_attributes() as $attr => $val) {
                $attrs[] = wc_attribute_label(str_replace('attribute_', '', $attr)) . ':' . $val;
            }

            $variations[] = [
                'id'    => $var_id,
                'label' => implode(' | ', $attrs) ?: "متغیر #{$var_id}",
                'stock' => $variation->get_stock_quantity(),
            ];
        }

        wp_send_json_success(['variations' => $variations]);
    }

    /**
     * لیست محصولات local برای dropdown
     */
    private function get_local_products_for_select() {
        $args = [
            'status'   => 'publish',
            'limit'    => 200,
            'type'     => ['simple', 'variable'],
            'orderby'  => 'title',
            'order'    => 'ASC',
        ];
        $products = wc_get_products($args);
        $result   = [];

        foreach ($products as $product) {
            $result[] = [
                'product_id' => $product->get_id(),
                'type'       => $product->get_type(),
                'label'      => "#{$product->get_id()} - {$product->get_name()} ({$product->get_type()})",
            ];
        }

        return $result;
    }
}

// فعال‌سازی
PIE_StockSync::get_instance();
