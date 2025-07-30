<?php
get_header();

$selected_year = isset($_GET['school_year']) ? sanitize_text_field($_GET['school_year']) : '';

// Define date ranges for school years
$year_ranges = [
    'senior_colt' => ['start' => '2007-09-01', 'end' => '2008-08-31'],
    'junior_colt' => ['start' => '2008-09-01', 'end' => '2009-08-31'],
    'all_colt' => ['start' => '2007-09-01', 'end' => '2009-08-31'],
    'under_16'  => ['start' => '2009-09-01', 'end' => '2010-08-31'],
];

$args = [
    'post_type' => 'player',
    'posts_per_page' => -1,
    'meta_key' => 'dob', // Date of Birth must be stored as Y-m-d or compatible
    'orderby' => 'title',
    'order' => 'ASC',
];

// Apply DOB filter if a school year is selected
if (isset($year_ranges[$selected_year])) {
    $range = $year_ranges[$selected_year];
    $args['meta_query'] = [
        [
            'key' => 'dob',
            'value' => [$range['start'], $range['end']],
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        ]
    ];
}

$players = new WP_Query($args);
?>

<div class="wrap">
    <h1>Academy Players</h1>

    <form method="GET" action="">
        <label for="school_year">Filter by School Year:</label>
        <select name="school_year" id="school_year" onchange="this.form.submit()">
            <option value="">All Years</option>
            <option value="all_colt" <?php selected($selected_year, 'all_colt'); ?>>All Colts (U18 + U17)</option>
            <option value="senior_colt" <?php selected($selected_year, 'senior_colt'); ?>>Senior Colt (U18)</option>
            <option value="junior_colt" <?php selected($selected_year, 'junior_colt'); ?>>Junior Colt (U17)</option>
            <option value="under_16"  <?php selected($selected_year, 'under_16'); ?>>Under 16</option>
        </select>
    </form>

    <div class="player-cards" style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 2rem;">
        <?php if ($players->have_posts()): ?>
            <?php while ($players->have_posts()): $players->the_post(); ?>
                <?php include plugin_dir_path(__FILE__) . 'player-card.php'; ?>
            <?php endwhile; wp_reset_postdata(); ?>
        <?php else: ?>
            <p>No players found for this school year.</p>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
