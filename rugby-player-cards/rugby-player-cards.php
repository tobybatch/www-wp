<?php
/*
Plugin Name: Rugby Player Cards
Description: Tracks player development with multiple dated cards
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

class RugbyPlayerCards
{

    public function __construct()
    {
        add_action('init', array($this, 'register_player_post_type'));
        //add_filter('manage_rugby_player_posts_columns', array($this, 'add_rfuid_column'));
        //add_action('manage_rugby_player_posts_custom_column', array($this, 'render_rfuid_column'), 10, 2);
        add_filter('manage_rugby_player_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_rugby_player_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
        add_filter('manage_edit-rugby_player_sortable_columns', array($this, 'make_dob_sortable'));
        add_action('pre_get_posts', array($this, 'handle_dob_sorting'));
        add_action('add_meta_boxes', array($this, 'add_rfuid_meta_box'));
        add_action('add_meta_boxes', array($this, 'add_player_meta_boxes'));
        add_action('save_post_rugby_player', array($this, 'save_player_meta'));
        add_action('init', array($this, 'add_player_rewrite_rule'));
        add_action('admin_menu', array($this, 'add_import_menu_item'));
        add_action('admin_post_rugby_player_import', array($this, 'handle_import_request'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_include', array($this, 'player_card_template'));
        // Increase max upload size for JSON files
        add_filter('upload_size_limit', function($size) {
    if (current_user_can('manage_options')) {
        return 2 * 1024 * 1024; // 2MB
    }
    return $size;
});

// Allow JSON uploads
        add_filter('upload_mimes', function($mimes) {
            $mimes['json'] = 'application/json';
            return $mimes;
        });
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function make_dob_sortable($columns)
    {
        $columns['dob'] = 'dob';
        return $columns;
    }

    public function handle_dob_sorting($query)
    {
        if (!is_admin() || !$query->is_main_query()) return;

        if ($orderby = $query->get('orderby')) {
            if ($orderby === 'dob') {
                $query->set('meta_key', '_player_dob');
                $query->set('orderby', 'meta_value');
            }
        }
    }

    public function add_admin_columns($columns)
    {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['dob'] = 'Age';
                $new_columns['rfuid'] = 'RFID';
            }
        }
        return $new_columns;
    }

    public function render_admin_columns($column, $post_id)
    {
        if ($column === 'dob') {
            if ($dob = get_post_meta($post_id, '_player_dob', true)) {
                $age = date_diff(date_create($dob), date_create('today'))->y;
                echo $age . ' years';
            } else {
                echo '—';
            }
        }
        if ($column === 'rfuid') {
            echo esc_html(get_post_meta($post_id, '_player_rfuid', true) ?: '—');
        }
    }

    public function handle_import_request() {
        check_admin_referer('rugby_player_import');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!isset($_FILES['idps_json']) || $_FILES['idps_json']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('edit.php?post_type=rugby_player&page=rugby-player-import&import=error'));
            exit;
        }

        // Verify file type
        $file_info = wp_check_filetype($_FILES['idps_json']['name']);
        if ($file_info['ext'] !== 'json') {
            wp_redirect(admin_url('edit.php?post_type=rugby_player&page=rugby-player-import&import=error'));
            exit;
        }

        // Get file contents
        $json_content = file_get_contents($_FILES['idps_json']['tmp_name']);
        $players = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(admin_url('edit.php?post_type=rugby_player&page=rugby-player-import&import=error'));
            exit;
        }

        $overwrite = isset($_POST['overwrite_existing']) && $_POST['overwrite_existing'] === '1';
        $import_result = $this->process_player_import($players, $overwrite);

        wp_redirect(admin_url('edit.php?post_type=rugby_player&page=rugby-player-import&import=' . ($import_result ? 'success' : 'error')));
        exit;
    }
    private function process_player_import($players, $overwrite = true) {
        $success_count = 0;

        foreach ($players as $player_id => $player_data) {
            // Get most recent evaluation date
            $latest_date = array_keys($player_data)[0];
            $evaluation = $player_data[$latest_date];

            // Prepare player data
            $player_post = array(
                'post_title'   => sanitize_text_field($evaluation['name']),
                'post_type'    => 'rugby_player',
                'post_status'  => 'publish',
                'meta_input'   => array(
                    '_player_rfuid' => sanitize_text_field($player_id)
                )
            );

            // Check if player exists
            $existing = get_posts(array(
                'post_type' => 'rugby_player',
                'meta_key' => '_player_rfuid',
                'meta_value' => $player_id,
                'posts_per_page' => 1
            ));

            // Skip if exists and not overwriting
            if ($existing && !$overwrite) {
                continue;
            }

            // Insert/update player
            if ($existing) {
                $player_post['ID'] = $existing[0]->ID;
                $post_id = wp_update_post($player_post);
            } else {
                $post_id = wp_insert_post($player_post);
            }

            if ($post_id && !is_wp_error($post_id)) {
                // Save positions
                if (!empty($evaluation['positions'])) {
                    update_post_meta($post_id, '_primary_position', sanitize_text_field($evaluation['positions'][0]));
                    update_post_meta($post_id, '_player_positions', array_map('sanitize_text_field', $evaluation['positions']));
                }

                // Import evaluation as a card
                global $wpdb;
                $wpdb->insert($wpdb->prefix . 'rugby_player_cards', array(
                    'player_id' => $post_id,
                    'card_date' => sanitize_text_field($latest_date),
                    'strengths' => sanitize_textarea_field($evaluation['strengths']),
                    'work_ons' => !empty($evaluation['areas_for_development']) ?
                        implode("\n", array_map('sanitize_text_field', $evaluation['areas_for_development'])) : '',
                    'positions' => !empty($evaluation['positions']) ?
                        implode(', ', array_map('sanitize_text_field', $evaluation['positions'])) : ''
                ));

                $success_count++;
            }
        }

        return $success_count > 0;
    }
    public function import_player_data()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $json_file = plugin_dir_path(__FILE__) . 'data/idps.json';

        if (!file_exists($json_file)) {
            wp_die('JSON file not found');
        }

        $json_data = file_get_contents($json_file);
        $players = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die('Invalid JSON data');
        }

        foreach ($players as $player_id => $player_data) {
            // Get most recent evaluation date
            $latest_date = array_keys($player_data)[0];
            $evaluation = $player_data[$latest_date];

            // Create/update player
            $player_post = array(
                'post_title' => $evaluation['name'],
                'post_type' => 'rugby_player',
                'post_status' => 'publish',
                'meta_input' => array(
                    '_player_rfuid' => $player_id,
                    '_player_dob' => ''
                )
            );

            // Insert or update player
            $existing = get_posts(array(
                'post_type' => 'rugby_player',
                'meta_key' => '_player_rfuid',
                'meta_value' => $player_id,
                'posts_per_page' => 1
            ));

            if ($existing) {
                $player_post['ID'] = $existing[0]->ID;
                $post_id = wp_update_post($player_post);
            } else {
                $post_id = wp_insert_post($player_post);
            }

            if ($post_id && !is_wp_error($post_id)) {
                // Save positions (for admin display)
                update_post_meta($post_id, '_primary_position', $evaluation['positions'][0]);
                update_post_meta($post_id, '_player_positions', $evaluation['positions']);

                // Import evaluation as a card
                global $wpdb;
                $wpdb->insert($wpdb->prefix . 'rugby_player_cards', array(
                    'player_id' => $post_id,
                    'card_date' => $latest_date,
                    'strengths' => $evaluation['strengths'],
                    'work_ons' => implode("\n", $evaluation['areas_for_development']),
                    'positions' => implode(', ', $evaluation['positions'])
                ));
            }
        }

        wp_redirect(admin_url('edit.php?post_type=rugby_player&import=success'));
        exit;
    }

    public function add_import_menu_item()
    {
        add_submenu_page(
            'edit.php?post_type=rugby_player',
            'Import Players',
            'Import Data',
            'manage_options',
            'rugby-player-import',
            array($this, 'render_import_page')
        );
    }

    public function render_import_page()
    {
        ?>
        public function render_import_page() {
        ?>
        <div class="wrap">
            <h1>Import Player Data</h1>

            <?php if (isset($_GET['import'])): ?>
                <div class="notice notice-<?php echo $_GET['import'] === 'success' ? 'success' : 'error'; ?>">
                    <p><?php echo $_GET['import'] === 'success' ? 'Import completed successfully!' : 'Import failed!'; ?></p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width:600px;">
                <h2>Upload JSON File</h2>
                <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="rugby_player_import">
                    <?php wp_nonce_field('rugby_player_import'); ?>

                    <p>
                        <label for="idps_json">Select IDPS JSON file:</label><br>
                        <input type="file" name="idps_json" id="idps_json" accept=".json" required>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" name="overwrite_existing" value="1" checked>
                            Overwrite existing player data
                        </label>
                    </p>

                    <button type="submit" class="button button-primary">Upload & Import</button>
                </form>
            </div>
        </div>
        <?php
    }

    public function add_rfuid_column($columns)
    {
        $columns['rfuid'] = 'RFID Tag';
        return $columns;
    }

    public function render_rfuid_column($column, $post_id)
    {
        if ($column === 'rfuid') {
            echo esc_html(get_post_meta($post_id, '_player_rfuid', true));
        }
    }

    public function activate()
    {
        $this->register_player_post_type();
        $this->create_cards_table();
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    public function create_cards_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rugby_player_cards';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                player_id mediumint(9) NOT NULL,
                card_date date NOT NULL,
                strengths text NOT NULL,
                work_ons text NOT NULL,
                positions varchar(255) NOT NULL,
                PRIMARY KEY (id),
                KEY player_id (player_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public function register_player_post_type()
    {
        register_post_type('rugby_player', [
            'labels' => [
                'name' => 'Rugby Players',
                'singular_name' => 'Rugby Player'
            ],
            'public' => true,
            'has_archive' => false,
            'supports' => ['title', 'thumbnail', 'editor'],
            'menu_icon' => 'dashicons-groups'
        ]);
    }

    public function add_rfuid_meta_box()
    {

        add_meta_box(
            'player_identification',  // Changed from 'player_rfuid'
            'Player Identification',
            array($this, 'render_identification_meta_box'),
            'rugby_player',
            'side',
            'default'
        );
    }

    public function add_player_meta_boxes()
    {
        add_meta_box(
            'player_cards',
            'Player Development Cards',
            array($this, 'render_cards_meta_box'),
            'rugby_player',
            'normal',
            'default'
        );
    }

    public function render_rfuid_meta_box($post)
    {

        wp_nonce_field('save_player_identification', 'player_identification_nonce');
        $rfuid = get_post_meta($post->ID, '_player_rfuid', true);
        $dob = get_post_meta($post->ID, '_player_dob', true);

        echo '<label for="player_rfuid" style="display:block;margin-bottom:5px;">RFID Tag ID:</label>';
        echo '<input type="text" name="player_rfuid" id="player_rfuid" value="' . esc_attr($rfuid) . '" style="width:100%;margin-bottom:15px;">';

        echo '<label for="player_dob" style="display:block;margin-bottom:5px;">Date of Birth:</label>';
        echo '<input type="date" name="player_dob" id="player_dob" value="' . esc_attr($dob) . '" style="width:100%">';
        echo '<p class="description">Format: YYYY-MM-DD</p>';
    }

    public function render_cards_meta_box($post)
    {
        wp_nonce_field('save_player_cards', 'player_cards_nonce');
        global $wpdb;

        $cards = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rugby_player_cards
             WHERE player_id = %d ORDER BY card_date DESC",
            $post->ID
        ));

        echo '<div class="new-card-form" style="padding:20px;background:#f5f5f5;margin-bottom:20px;">';
        echo '<h3>Add New Card</h3>';
        echo '<div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;"><strong>Date:</strong></label>';
        echo '<input type="date" name="new_card_date" value="' . date('Y-m-d') . '" style="width:100%;padding:8px;"></div>';

        echo '<div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;"><strong>Strengths:</strong></label>';
        echo '<textarea name="new_card_strengths" rows="3" style="width:100%;padding:8px;"></textarea></div>';

        echo '<div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;"><strong>Areas to Work On:</strong></label>';
        echo '<textarea name="new_card_work_ons" rows="3" style="width:100%;padding:8px;"></textarea></div>';

        $all_positions = ['Prop', 'Hooker', 'Lock', 'Flanker', 'Number 8', 'Scrum-half', 'Fly-half', 'Centre', 'Winger', 'Fullback'];
        echo '<div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;"><strong>Positions:</strong></label>';
        foreach ($all_positions as $pos) {
            echo '<label style="margin-right:10px;"><input type="checkbox" name="new_card_positions[]" value="' . esc_attr($pos) . '"> ' . esc_html($pos) . '</label>';
        }
        echo '</div>';

        echo '<button type="submit" name="add_new_card" class="button button-primary">Add Card</button>';
        echo '</div>';

        echo '<div class="card-history">';
        echo '<h3>Card History</h3>';
        if ($cards) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Date</th><th>Positions</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            foreach ($cards as $card) {
                echo '<tr>';
                echo '<td>' . date('M j, Y', strtotime($card->card_date)) . '</td>';
                echo '<td>' . esc_html($card->positions) . '</td>';
                echo '<td><a href="#" class="view-card" data-card-id="' . $card->id . '">View</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No cards recorded yet.</p>';
        }
        echo '</div>';
    }

    public function save_player_meta($post_id)
    {
        if (!isset($_POST['player_cards_nonce']) || !wp_verify_nonce($_POST['player_cards_nonce'], 'save_player_cards')) return;

        if (isset($_POST['add_new_card'])) {
            global $wpdb;

            $wpdb->insert($wpdb->prefix . 'rugby_player_cards', [
                'player_id' => $post_id,
                'card_date' => sanitize_text_field($_POST['new_card_date']),
                'strengths' => sanitize_textarea_field($_POST['new_card_strengths']),
                'work_ons' => sanitize_textarea_field($_POST['new_card_work_ons']),
                'positions' => isset($_POST['new_card_positions']) ?
                    implode(', ', array_map('sanitize_text_field', $_POST['new_card_positions'])) : ''
            ]);
        }

        if (isset($_POST['player_rfuid_nonce']) && wp_verify_nonce($_POST['player_rfuid_nonce'], 'save_player_rfuid')) {
            if (isset($_POST['player_rfuid'])) {
                update_post_meta($post_id, '_player_rfuid', sanitize_text_field($_POST['player_rfuid']));
            } else {
                delete_post_meta($post_id, '_player_rfuid');
            }
        }
    }

    public function add_player_rewrite_rule()
    {
        add_rewrite_rule('^players/([0-9]+)/?$', 'index.php?player_id=$matches[1]', 'top');
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'player_id';
        return $vars;
    }

    public function player_card_template($template)
    {
        if (get_query_var('player_id')) {
            return plugin_dir_path(__FILE__) . 'templates/player-card.php';
        }
        return $template;
    }
}

new RugbyPlayerCards();
