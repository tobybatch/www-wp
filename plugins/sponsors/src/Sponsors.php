<?php

namespace Sponsors;

class Sponsors
{
    public function __construct()
    {
        add_action('init', function () {
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
        });

        add_action('after_setup_theme', function () {
            add_theme_support('post-thumbnails', ['sponsor']);
        });
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_sponsor', [$this, 'saveSponsorLink']);

        add_filter('manage_sponsor_posts_columns', array($this, 'addAdminColumns'));
        add_action('manage_sponsor_posts_custom_column', array($this, 'renderAdminColumns'), 10, 2);


    }

    public function addAdminColumns($columns): array
    {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['link'] = 'Link';
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
    }

    public function renderSponsorMetaBox($post)
    {
        $sponsor_link = get_post_meta($post->ID, '_sponsor_link', true);
        wp_nonce_field('sponsor_link_nonce', 'sponsor_link_nonce_field');
        ?>
        <label for="sponsor_link">Sponsor Post URL:</label>
        <input type="url" name="sponsor_link" id="sponsor_link" value="<?php echo esc_attr($sponsor_link); ?>" style="width:100%;">
        <?php
    }
    public function saveSponsorLink($post_id)
    {
        if (
            !isset($_POST['sponsor_link_nonce_field']) ||
            !wp_verify_nonce($_POST['sponsor_link_nonce_field'], 'sponsor_link_nonce')
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
}
