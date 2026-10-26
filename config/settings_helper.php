<?php
/**
 * settings_helper.php
 * Loads all settings into an array, with typed getters.
 *
 * Usage:
 *   require_once __DIR__ . '/settings_helper.php';
 *   $SETTINGS = load_settings($conn);
 *   echo $SETTINGS['org_name'];
 *   echo get_setting($conn, 'tax_rate');
 */
if (!function_exists('load_settings')) {

    function load_settings(mysqli $conn): array {
        static $cache = null;
        if ($cache !== null) return $cache;

        $cache = [];
        $res = $conn->query("SELECT setting_key, setting_value, setting_type FROM settings");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $val = $row['setting_value'];
                switch ($row['setting_type']) {
                    case 'int':   $val = (int)$val; break;
                    case 'float': $val = (float)$val; break;
                    case 'bool':  $val = $val === '1' || $val === 'true'; break;
                    case 'json':  $val = json_decode($val, true); break;
                }
                $cache[$row['setting_key']] = $val;
            }
        }
        return $cache;
    }

    function get_setting(mysqli $conn, string $key, $default = null) {
        $all = load_settings($conn);
        return $all[$key] ?? $default;
    }

    function save_setting(mysqli $conn, string $key, $value, string $type = 'string', ?int $userId = null): bool {
        if (is_bool($value))   { $value = $value ? '1' : '0'; $type = 'bool'; }
        if (is_array($value))  { $value = json_encode($value); $type = 'json'; }
        $value = (string)$value;

        $stmt = $conn->prepare(
            "INSERT INTO settings (setting_key, setting_value, setting_type, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                     setting_type  = VALUES(setting_type),
                                     updated_by    = VALUES(updated_by)"
        );
        $stmt->bind_param("sssi", $key, $value, $type, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}