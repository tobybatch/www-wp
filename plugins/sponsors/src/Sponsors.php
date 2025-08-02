<?php

namespace Sponsors;

class Sponsors
{
    public function __construct()
    {
        add_action(
            'init',
            function () {
                $labels = [
                'name'               => 'Sponsors',
                'singular_name'      => 'Sponsor',
                'menu_name'          => 'Sponsors',
                'name_admin_bar'     => 'Sponsor',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Sponsor',
                'new_item'           => 'New Sponsor',
                'edit_item'          => 'Edit Sponsor',
                'view_item'          => 'View Sponsor',
                'all_items'          => 'All Sponsors',
                'search_items'       => 'Search Sponsors',
                'not_found'          => 'No sponsors found.',
                ];

                $args = [
                'labels'              => $labels,
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'menu_icon'           => 'dashicons-groups',
                'supports'            => ['title', 'thumbnail'],
                'exclude_from_search' => true,
                'publicly_queryable'  => false,
                'has_archive'         => false,
                'rewrite'             => false,
                'capability_type'     => 'post',
                ];

                register_post_type('sponsor', $args);
            }
        );

        add_action(
            'after_setup_theme',
            function () {
                add_theme_support('post-thumbnails', ['sponsor']);
            }
        );
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_sponsor', [$this, 'saveSponsorLink']);
        add_action('save_post_sponsor', [$this, 'saveSponsorLevel']);
        add_action('save_post_sponsor', [$this, 'saveSponsorWeight']);

        add_filter('manage_sponsor_posts_columns', [$this, 'addAdminColumns']);
        add_action('manage_sponsor_posts_custom_column', [$this, 'renderAdminColumns'], 10, 2);
        add_filter('manage_edit-sponsor_sortable_columns', [$this, 'setSortableColumns']);
        add_action('pre_get_posts', [$this, 'sortByWeight']);
    }

    public function addAdminColumns($columns): array
    {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['link'] = 'Link';
                $new_columns['sponsor_level'] = 'Sponsor Level';
                $new_columns['sponsor_weight'] = 'Weight';
            }
        }
        return $new_columns;
    }

    public function renderAdminColumns($column, $post_id): void
    {
        static $seen = [];

        $key = $column . '-' . $post_id;

        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        if ($column === 'link') {
            if ($link = get_post_meta($post_id, '_sponsor_link', true)) {
                echo esc_html($link);
            } else {
                echo '—';
            }
        }

        if ($column === 'sponsor_level') {
            if ($level = get_post_meta($post_id, '_sponsor_level', true)) {
                echo esc_html(ucfirst($level));
            } else {
                echo '—';
            }
        }

        if ($column === 'sponsor_weight') {
            if ($weight = get_post_meta($post_id, '_sponsor_weight', true)) {
                echo esc_html($weight);
            } else {
                echo '—';
            }
        }
    }

    public function setSortableColumns($columns): array
    {
        $columns['sponsor_weight'] = 'sponsor_weight';
        return $columns;
    }

    public function sortByWeight($query)
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'sponsor') {
            return;
        }

        if ($query->get('orderby') === 'sponsor_weight') {
            $query->set('meta_key', '_sponsor_weight');
            $query->set('orderby', 'meta_value_num');
        }
    }

    public function addMetaBoxes()
    {
        add_meta_box(
            'sponsor_link',
            'Sponsor Post Link',
            [$this, 'renderSponsorMetaBox'],
            'sponsor',
            'normal'
        );

        add_meta_box(
            'sponsor_level',
            'Sponsor Level',
            [$this, 'renderSponsorLevelMetaBox'],
            'sponsor',
            'normal'
        );

        add_meta_box(
            'sponsor_weight',
            'Sponsor Weight',
            [$this, 'renderSponsorWeightMetaBox'],
            'sponsor',
            'normal'
        );
    }

    public function renderSponsorMetaBox($post)
    {
        $sponsor_link = get_post_meta($post->ID, '_sponsor_link', true);
        wp_nonce_field('sponsor_link_nonce', 'sponsor_link_nonce_field');
        ?>
        <label for="sponsor_link">Sponsor Post URL:</label>
        <input type="url" name="sponsor_link" id="sponsor_link" value="<?php echo
            esc_attr($sponsor_link);
        ?>" style="width:100%;">
        <?php
    }

    public function renderSponsorLevelMetaBox($post): void
    {
        $sponsor_level = get_post_meta($post->ID, '_sponsor_level', true);
        wp_nonce_field('sponsor_level_nonce', 'sponsor_level_nonce_field');
        ?>
        <label for="sponsor_level">Sponsor Level:</label>
        <select name="sponsor_level" id="sponsor_level" style="width:100%;">
            <option value="">Select a level</option>
            <option value="club" <?php selected($sponsor_level, 'club'); ?>>Club</option>
            <option value="gold" <?php selected($sponsor_level, 'gold'); ?>>Gold</option>
            <option value="silver" <?php selected($sponsor_level, 'silver'); ?>>Silver</option>
            <option value="bronze" <?php selected($sponsor_level, 'bronze'); ?>>Bronze</option>
            <option value="associate" <?php selected($sponsor_level, 'associate'); ?>>Associate</option>
        </select>
        <?php
    }

    public function renderSponsorWeightMetaBox($post): void
    {
        $sponsor_weight = get_post_meta($post->ID, '_sponsor_weight', true);
        wp_nonce_field('sponsor_weight_nonce', 'sponsor_weight_nonce_field');
        ?>
        <label for="sponsor_weight">Sponsor Weight (higher numbers appear first):</label>
        <input type="number" name="sponsor_weight" id="sponsor_weight" value="<?php echo
            esc_attr($sponsor_weight);
        ?>" style="width:100%;">
        <?php
    }

    public function saveSponsorLink($post_id): void
    {
        if (
            !isset($_POST['sponsor_link_nonce_field'])
            || !wp_verify_nonce($_POST['sponsor_link_nonce_field'], 'sponsor_link_nonce')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['sponsor_link'])) {
            update_post_meta($post_id, '_sponsor_link', esc_url_raw($_POST['sponsor_link']));
        }
    }

    public function saveSponsorLevel($post_id): void
    {
        if (
            !isset($_POST['sponsor_level_nonce_field'])
            || !wp_verify_nonce($_POST['sponsor_level_nonce_field'], 'sponsor_level_nonce')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (
            isset($_POST['sponsor_level']) &&
            in_array($_POST['sponsor_level'], ['club', 'gold', 'silver', 'bronze', 'associate', ''])
        ) {
            update_post_meta($post_id, '_sponsor_level', sanitize_text_field($_POST['sponsor_level']));
        }
    }

    public function saveSponsorWeight($post_id): void
    {
        if (
            !isset($_POST['sponsor_weight_nonce_field'])
            || !wp_verify_nonce($_POST['sponsor_weight_nonce_field'], 'sponsor_weight_nonce')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['sponsor_weight']) && is_numeric($_POST['sponsor_weight'])) {
            update_post_meta($post_id, '_sponsor_weight', intval($_POST['sponsor_weight']));
        } else {
            delete_post_meta($post_id, '_sponsor_weight');
        }
    }
}