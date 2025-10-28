<?php

/**
 * Plugin Name: ScoreZone Live
 * Description: عرض المباريات الحية، النتائج، الإحصائيات، الإشعارات، وتحليلات SEO.
 * Version: 1.0.0
 * Author: Ahme Ramadan
 */

defined('ABSPATH') || exit;

// عند التفعيل - إنشاء الجداول والإعدادات الأولية
register_activation_hook(__FILE__, 'scorezone_install');
function scorezone_install()
{
    global $wpdb;

    // إنشاء جدول المباريات
    $table_matches = $wpdb->prefix . 'scorezone_matches';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_matches (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        match_id VARCHAR(100) NOT NULL,
        team_home VARCHAR(100),
        team_away VARCHAR(100),
        score_home INT,
        score_away INT,
        match_date DATETIME,
        status VARCHAR(20),
        stats TEXT,
        tournament VARCHAR(100),
        updated_at DATETIME,
        PRIMARY KEY (id),
        UNIQUE KEY match_id (match_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);
    if ($result === false) {
        error_log('ScoreZone: Failed to create table.');
    }
}

// إضافة قائمة إدارة في لوحة التحكم
add_action('admin_menu', 'scorezone_admin_menu');
function scorezone_admin_menu()
{
    add_menu_page('ScoreZone Matches', 'ScoreZone', 'manage_options', 'scorezone-matches', 'scorezone_matches_page', 'dashicons-sports', 20);
    add_submenu_page('scorezone-matches', 'ScoreZone Admin Dashboard', 'Admin Dashboard', 'manage_options', 'scorezone-admin', 'scorezone_admin_dashboard_page');
    add_submenu_page('scorezone-matches', 'ScoreZone Settings', 'Settings', 'manage_options', 'scorezone-settings', 'scorezone_settings_page');
    add_submenu_page('scorezone-matches', 'Export Matches', 'Export Matches', 'manage_options', 'scorezone-export', 'scorezone_export_matches_to_csv');
}

// صفحة إدارة المباريات
function scorezone_matches_page()
{
    global $wpdb;
    $table_matches = $wpdb->prefix . 'scorezone_matches';

    // معالجة الإضافة/التحديث
    if (isset($_POST['submit_match'])) {
        if (!wp_verify_nonce($_POST['scorezone_nonce'], 'add_match')) {
            wp_die('Security check failed');
        }
        $match_id = sanitize_text_field($_POST['match_id']);
        $team_home = sanitize_text_field($_POST['team_home']);
        $team_away = sanitize_text_field($_POST['team_away']);
        $score_home = intval($_POST['score_home']);
        $score_away = intval($_POST['score_away']);
        $match_date = sanitize_text_field($_POST['match_date']);
        $status = sanitize_text_field($_POST['status']);
        $stats = sanitize_textarea_field($_POST['stats']);
        $tournament = sanitize_text_field($_POST['tournament']);

        if (empty($match_id)) {
            echo '<div class="error"><p>Match ID is required.</p></div>';
        } else {
            $result = $wpdb->replace($table_matches, [
                'match_id' => $match_id,
                'team_home' => $team_home,
                'team_away' => $team_away,
                'score_home' => $score_home,
                'score_away' => $score_away,
                'match_date' => $match_date,
                'status' => $status,
                'stats' => $stats,
                'tournament' => $tournament,
                'updated_at' => current_time('mysql')
            ]);
            if ($result === false) {
                echo '<div class="error"><p>Failed to save match.</p></div>';
            } else {
                echo '<div class="updated"><p>Match saved successfully.</p></div>';
            }
        }
    }

    // معالجة الحذف
    if (isset($_GET['delete']) && isset($_GET['nonce'])) {
        if (!wp_verify_nonce($_GET['nonce'], 'delete_match')) {
            wp_die('Security check failed');
        }
        $id = intval($_GET['delete']);
        $result = $wpdb->delete($table_matches, ['id' => $id]);
        if ($result === false) {
            echo '<div class="error"><p>Failed to delete match.</p></div>';
        } else {
            echo '<div class="updated"><p>Match deleted successfully.</p></div>';
        }
    }

    // عرض النموذج
    echo '<div class="wrap"><h1>Manage Matches</h1>';
    echo '<form method="post">';
    wp_nonce_field('add_match', 'scorezone_nonce');
    echo '<table class="form-table">';
    echo '<tr><th>Match ID</th><td><input type="text" name="match_id" required></td></tr>';
    echo '<tr><th>Home Team</th><td><input type="text" name="team_home"></td></tr>';
    echo '<tr><th>Away Team</th><td><input type="text" name="team_away"></td></tr>';
    echo '<tr><th>Home Score</th><td><input type="number" name="score_home"></td></tr>';
    echo '<tr><th>Away Score</th><td><input type="number" name="score_away"></td></tr>';
    echo '<tr><th>Match Date</th><td><input type="datetime-local" name="match_date"></td></tr>';
    echo '<tr><th>Status</th><td><input type="text" name="status"></td></tr>';
    echo '<tr><th>Stats</th><td><textarea name="stats"></textarea></td></tr>';
    echo '<tr><th>Tournament</th><td><input type="text" name="tournament"></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="submit_match" value="Save Match" class="button button-primary">';
    echo '</form>';

    // عرض المباريات
    $matches = $wpdb->get_results("SELECT * FROM $table_matches");
    echo '<h2>Existing Matches</h2><table class="wp-list-table widefat">';
    echo '<thead><tr><th>ID</th><th>Match ID</th><th>Home</th><th>Away</th><th>Score</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($matches as $match) {
        $delete_url = wp_nonce_url(admin_url('admin.php?page=scorezone-matches&delete=' . $match->id), 'delete_match', 'nonce');
        echo '<tr>';
        echo '<td>' . $match->id . '</td>';
        echo '<td>' . esc_html($match->match_id) . '</td>';
        echo '<td>' . esc_html($match->team_home) . '</td>';
        echo '<td>' . esc_html($match->team_away) . '</td>';
        echo '<td>' . $match->score_home . '-' . $match->score_away . '</td>';
        echo '<td>' . $match->match_date . '</td>';
        echo '<td>' . esc_html($match->status) . '</td>';
        echo '<td><a href="' . $delete_url . '" onclick="return confirm(\'Are you sure?\')">Delete</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

// إضافة حقل بيانات إضافية للمستخدم (مثل الفريق المفضل، اللاعب المفضل)
add_action('show_user_profile', 'scorezone_extra_user_profile_fields');
add_action('edit_user_profile', 'scorezone_extra_user_profile_fields');

function scorezone_extra_user_profile_fields($user)
{
?>
    <h3>إعدادات ScoreZone</h3>
    <table class="form-table">
        <tr>
            <th><label for="favorite_team">الفريق المفضل</label></th>
            <td>
                <input type="text" name="favorite_team" id="favorite_team" value="<?php echo esc_attr(get_user_meta($user->ID, 'favorite_team', true)); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label for="favorite_player">اللاعب المفضل</label></th>
            <td>
                <input type="text" name="favorite_player" id="favorite_player" value="<?php echo esc_attr(get_user_meta($user->ID, 'favorite_player', true)); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label for="verified">تم التحقق؟</label></th>
            <td>
                <input type="checkbox" name="verified" value="1" <?php checked(get_user_meta($user->ID, 'verified', true), 1); ?> />
            </td>
        </tr>
    </table>
    <?php wp_nonce_field('scorezone_user_profile', 'scorezone_nonce'); ?>
<?php
}

add_action('personal_options_update', 'scorezone_save_extra_user_profile_fields');
add_action('edit_user_profile_update', 'scorezone_save_extra_user_profile_fields');

function scorezone_save_extra_user_profile_fields($user_id)
{
    if (!wp_verify_nonce($_POST['scorezone_nonce'], 'scorezone_user_profile')) {
        return;
    }
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }
    update_user_meta($user_id, 'favorite_team', sanitize_text_field($_POST['favorite_team']));
    update_user_meta($user_id, 'favorite_player', sanitize_text_field($_POST['favorite_player']));
    update_user_meta($user_id, 'verified', isset($_POST['verified']) ? 1 : 0);
}

// اختبارات بسيطة
function scorezone_run_tests()
{
    // اختبار إنشاء المباراة
    global $wpdb;
    $table_matches = $wpdb->prefix . 'scorezone_matches';
    $test_match = [
        'match_id' => 'test123',
        'team_home' => 'Team A',
        'team_away' => 'Team B',
        'score_home' => 1,
        'score_away' => 0,
        'match_date' => current_time('mysql'),
        'status' => 'finished',
        'stats' => 'Test stats',
        'tournament' => 'Test League',
        'updated_at' => current_time('mysql')
    ];
    $result = $wpdb->insert($table_matches, $test_match);
    if ($result === false) {
        error_log('ScoreZone Test: Failed to insert test match.');
    } else {
        error_log('ScoreZone Test: Test match inserted successfully.');
        // حذف الاختبار
        $wpdb->delete($table_matches, ['match_id' => 'test123']);
    }
}
add_action('init', 'scorezone_run_tests');

// إضافة REST API endpoint للمباريات
add_action('rest_api_init', 'scorezone_register_api');
function scorezone_register_api()
{
    register_rest_route('scorezone/v1', '/matches', array(
        'methods' => 'GET',
        'callback' => 'scorezone_get_matches_api',
        'permission_callback' => '__return_true',
    ));
}

function scorezone_get_matches_api()
{
    global $wpdb;
    $table_matches = $wpdb->prefix . 'scorezone_matches';
    $matches = $wpdb->get_results("SELECT * FROM $table_matches ORDER BY match_date DESC", ARRAY_A);
    return $matches;
}

// إضافة shortcode لعرض المباريات
add_shortcode('scorezone_matches', 'scorezone_matches_shortcode');
function scorezone_matches_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'tournament' => '',
        'limit' => 10,
    ), $atts);

    global $wpdb;
    $table_matches = $wpdb->prefix . 'scorezone_matches';
    $where = '';
    if (!empty($atts['tournament'])) {
        $where = $wpdb->prepare("WHERE tournament = %s", $atts['tournament']);
    }
    $limit = intval($atts['limit']);
    $matches = $wpdb->get_results("SELECT * FROM $table_matches $where ORDER BY match_date DESC LIMIT $limit");

    ob_start();
?>
    <div id="scorezone-matches"></div>
    <script src="<?php echo plugin_dir_url(__FILE__) . 'scorezone-frontend/build/static/js/main.js'; ?>"></script>
<?php
    return ob_get_clean();
}



function scorezone_settings_page()
{
    if (isset($_POST['submit_settings'])) {
        if (!wp_verify_nonce($_POST['scorezone_settings_nonce'], 'save_settings')) {
            wp_die('Security check failed');
        }
        update_option('scorezone_api_key', sanitize_text_field($_POST['api_key']));
        update_option('scorezone_cron_enabled', isset($_POST['cron_enabled']) ? 1 : 0);
        echo '<div class="updated"><p>Settings saved successfully.</p></div>';
    }

    $api_key = get_option('scorezone_api_key', '');
    $cron_enabled = get_option('scorezone_cron_enabled', 1);

    echo '<div class="wrap"><h1>ScoreZone Settings</h1>';
    echo '<form method="post">';
    wp_nonce_field('save_settings', 'scorezone_settings_nonce');
    echo '<table class="form-table">';
    echo '<tr><th>API Key</th><td><input type="text" name="api_key" value="' . esc_attr($api_key) . '" placeholder="Enter your Football-Data.org API key"></td></tr>';
    echo '<tr><th>Enable CRON</th><td><input type="checkbox" name="cron_enabled" value="1" ' . checked($cron_enabled, 1, false) . '></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="submit_settings" value="Save Settings" class="button button-primary">';
    echo '</form></div>';
}

// 1. إضافة مهمة CRON لجلب البيانات
function scorezone_schedule_cron()
{
    if (get_option('scorezone_cron_enabled', 1) && !wp_next_scheduled('scorezone_fetch_match_data')) {
        wp_schedule_event(time(), 'every_minute', 'scorezone_fetch_match_data');
    } elseif (!get_option('scorezone_cron_enabled', 1) && wp_next_scheduled('scorezone_fetch_match_data')) {
        wp_clear_scheduled_hook('scorezone_fetch_match_data');
    }
}
add_action('wp', 'scorezone_schedule_cron');

// 2. إنشاء خطوة CRON مخصصة "every_minute"
function scorezone_add_cron_schedule($schedules)
{
    $schedules['every_minute'] = [
        'interval' => 60, // 1 دقيقة
        'display' => 'Every minute'
    ];
    return $schedules;
}
add_filter('cron_schedules', 'scorezone_add_cron_schedule');

// 3. استدعاء API بشكل دوري
add_action('scorezone_fetch_match_data', 'scorezone_fetch_match_data_function');
function scorezone_fetch_match_data_function()
{
    $api_key = get_option('scorezone_api_key', '');
    if (empty($api_key)) {
        error_log('ScoreZone: API key not set.');
        return;
    }

    $api_url = 'https://api.football-data.org/v4/matches';

    $response = wp_remote_get($api_url, [
        'headers' => [
            'X-Auth-Token' => $api_key
        ]
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_mail(get_option('admin_email'), 'API Error - ScoreZone', 'Error fetching data: ' . $error_message . "\nTime: " . current_time('mysql'));
        error_log('ScoreZone API Error: ' . $error_message);
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($data['matches'])) {
        global $wpdb;
        $table_matches = $wpdb->prefix . 'scorezone_matches';
        foreach ($data['matches'] as $match) {
            $wpdb->replace($table_matches, [
                'match_id' => sanitize_text_field($match['id']),
                'team_home' => sanitize_text_field($match['homeTeam']['name']),
                'team_away' => sanitize_text_field($match['awayTeam']['name']),
                'score_home' => intval($match['score']['fullTime']['home'] ?? 0),
                'score_away' => intval($match['score']['fullTime']['away'] ?? 0),
                'match_date' => sanitize_text_field($match['utcDate']),
                'status' => sanitize_text_field($match['status']),
                'stats' => json_encode($match['score']),
                'tournament' => sanitize_text_field($match['competition']['name'] ?? ''),
                'updated_at' => current_time('mysql')
            ]);
        }
    }
}

// 4. إلغاء CRON عند إلغاء تفعيل البلوجن
register_deactivation_hook(__FILE__, 'scorezone_deactivate_cron');
function scorezone_deactivate_cron()
{
    wp_clear_scheduled_hook('scorezone_fetch_match_data');
}

// تصدير المباريات إلى CSV
add_action('admin_menu', function () {
    add_submenu_page('scorezone-matches', 'Export Matches', 'Export Matches', 'manage_options', 'scorezone-export', 'scorezone_export_matches_to_csv');
});

function scorezone_export_matches_to_csv()
{
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }

    global $wpdb;
    $table_matches = $wpdb->prefix . 'scorezone_matches';
    $tournament_filter = isset($_GET['tournament']) ? sanitize_text_field($_GET['tournament']) : '';
    $query = "SELECT * FROM $table_matches";
    if (!empty($tournament_filter)) {
        $query .= $wpdb->prepare(" WHERE tournament = %s", $tournament_filter);
    }
    $matches = $wpdb->get_results($query);

    if (empty($matches)) {
        wp_die('No matches to export');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="matches-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Match ID', 'Home Team', 'Away Team', 'Score Home', 'Score Away', 'Match Date', 'Status', 'Tournament']);

    foreach ($matches as $match) {
        fputcsv($output, [
            $match->match_id,
            $match->team_home,
            $match->team_away,
            $match->score_home,
            $match->score_away,
            $match->match_date,
            $match->status,
            $match->tournament
        ]);
    }

    fclose($output);
    exit;
}

// تحديث صفحة Admin Dashboard مع فلترة البطولة
function scorezone_admin_dashboard_page()
{
    global $wpdb;
    $table_matches = $wpdb->prefix . 'scorezone_matches';

    $tournament_filter = isset($_GET['tournament']) ? sanitize_text_field($_GET['tournament']) : '';
    $query = "SELECT * FROM $table_matches";
    if (!empty($tournament_filter)) {
        $query .= $wpdb->prepare(" WHERE tournament = %s", $tournament_filter);
    }
    $query .= " ORDER BY match_date DESC";
    $matches = $wpdb->get_results($query);

    echo '<div class="wrap"><h1>ScoreZone Admin Dashboard</h1>';

    // فلترة البطولة
    $tournaments = $wpdb->get_col("SELECT DISTINCT tournament FROM $table_matches WHERE tournament != ''");
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="scorezone-admin">';
    echo '<label for="tournament">Filter by Tournament:</label>';
    echo '<select name="tournament" id="tournament">';
    echo '<option value="">All Tournaments</option>';
    foreach ($tournaments as $tournament) {
        echo '<option value="' . esc_attr($tournament) . '" ' . selected($tournament_filter, $tournament, false) . '>' . esc_html($tournament) . '</option>';
    }
    echo '</select>';
    echo '<input type="submit" value="Filter" class="button">';
    echo '</form><br>';

    // إحصائيات سريعة
    $total_matches = count($matches);
    $live_matches = count(array_filter($matches, function ($match) {
        return $match->status === 'LIVE';
    }));
    $finished_matches = count(array_filter($matches, function ($match) {
        return $match->status === 'FINISHED';
    }));

    echo '<div class="scorezone-stats" style="display: flex; gap: 20px; margin-bottom: 20px;">';
    echo '<div class="stat-box" style="background: #f1f1f1; padding: 10px; border-radius: 5px;"><strong>Total Matches:</strong> ' . $total_matches . '</div>';
    echo '<div class="stat-box" style="background: #e8f5e8; padding: 10px; border-radius: 5px;"><strong>Live Matches:</strong> ' . $live_matches . '</div>';
    echo '<div class="stat-box" style="background: #f5e8e8; padding: 10px; border-radius: 5px;"><strong>Finished Matches:</strong> ' . $finished_matches . '</div>';
    echo '</div>';

    // جدول المباريات
    echo '<h2>Recent Matches</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Match ID</th><th>Home Team</th><th>Away Team</th><th>Score</th><th>Date</th><th>Status</th><th>Tournament</th></tr></thead><tbody>';

    if ($matches) {
        foreach ($matches as $match) {
            $status_class = '';
            if ($match->status === 'LIVE') {
                $status_class = 'style="color: green; font-weight: bold;"';
            } elseif ($match->status === 'FINISHED') {
                $status_class = 'style="color: red;"';
            }

            echo '<tr>';
            echo '<td>' . $match->id . '</td>';
            echo '<td>' . esc_html($match->match_id) . '</td>';
            echo '<td>' . esc_html($match->team_home) . '</td>';
            echo '<td>' . esc_html($match->team_away) . '</td>';
            echo '<td>' . $match->score_home . ' - ' . $match->score_away . '</td>';
            echo '<td>' . esc_html($match->match_date) . '</td>';
            echo '<td ' . $status_class . '>' . esc_html($match->status) . '</td>';
            echo '<td>' . esc_html($match->tournament) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="8">No matches found.</td></tr>';
    }

    echo '</tbody></table></div>';
}
