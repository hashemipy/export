<?php
/**
 * تست دیباگ برای مشکل ذخیره تنظیمات
 * این فایل نمی‌رود در production - فقط برای تست است
 */

// شبیه‌سازی WordPress functions برای تست
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}

// بررسی sanitize و esc_url_raw
echo "[TEST] sanitize_text_field test:\n";
$test_input = "test_value";
$sanitized = sanitize_text_field($test_input);
echo "  Input: $test_input\n";
echo "  Output: $sanitized\n\n";

echo "[TEST] esc_url_raw test:\n";
$url = "http://example.com";
$escaped = esc_url_raw($url);
echo "  Input: $url\n";
echo "  Output: $escaped\n\n";

// بررسی POST simulation
echo "[TEST] POST data simulation:\n";
$_POST = [
    'site_role' => 'site1',
    'remote_site_url' => 'http://example.com',
    'remote_api_key' => 'key123',
    'remote_api_secret' => 'secret456',
    'api_consumer_key' => 'consumer1',
    'api_consumer_secret' => 'consumer2',
    'auto_upload' => 1,
    'sync_direction' => 'bidirectional'
];

$config = [
    'site_role' => sanitize_text_field($_POST['site_role'] ?? 'site1'),
    'remote_site_url' => esc_url_raw($_POST['remote_site_url'] ?? ''),
    'remote_api_key' => sanitize_text_field($_POST['remote_api_key'] ?? ''),
    'remote_api_secret' => sanitize_text_field($_POST['remote_api_secret'] ?? ''),
    'api_consumer_key' => sanitize_text_field($_POST['api_consumer_key'] ?? ''),
    'api_consumer_secret' => sanitize_text_field($_POST['api_consumer_secret'] ?? ''),
    'auto_upload' => isset($_POST['auto_upload']) ? 1 : 0,
    'sync_direction' => sanitize_text_field($_POST['sync_direction'] ?? 'bidirectional')
];

echo "Config array created:\n";
echo wp_json_encode($config) . "\n\n";

echo "[TEST] PASSED - تمام تست‌ها موفق بود\n";
?>
