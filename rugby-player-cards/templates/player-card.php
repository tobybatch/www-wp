<?php
global $wpdb;
$player_id = get_query_var('player_id');
$player = get_post($player_id);

if (!$player || $player->post_type !== 'rugby_player') {
    status_header(404);
    get_template_part('404');
    exit;
}

$cards = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}rugby_player_cards
     WHERE player_id = %d ORDER BY card_date DESC",
    $player_id
));

get_header();
?>

    <div class="rugby-player-container" style="max-width:1200px; margin:0 auto; padding:20px;">
        <div class="player-header" style="text-align:center; margin-bottom:30px;">
            <h1><?php echo esc_html($player->post_title); ?></h1>
            <?php if (has_post_thumbnail($player_id)): ?>
                <div style="width:200px; height:200px; border-radius:50%; overflow:hidden; margin:0 auto 20px;">
                    <?php echo get_the_post_thumbnail($player_id, 'medium'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="player-header" style="text-align:center; margin-bottom:30px;">
            <h1><?php echo esc_html($player->post_title); ?></h1>
            <?php if ($rfuid = get_post_meta($player_id, '_player_rfuid', true)) : ?>
                <div class="rfuid-tag" style="margin:10px 0; padding:5px 10px; background:#e3f2fd; display:inline-block; border-radius:4px;">
                    <small>RFID: <?php echo esc_html($rfuid); ?></small>
                </div>
            <?php endif; ?>
    <?php if ($dob = get_post_meta($player_id, '_player_dob', true)) :
        $age = date_diff(date_create($dob), date_create('today'))->y;
    ?>
        <div class="player-dob" style="margin:5px 0; color:#666;">
            <small>DOB: <?php echo esc_html(date('j M Y', strtotime($dob))); ?> (Age: <?php echo $age; ?>)</small>
        </div>
    <?php endif; ?>
            <?php if (has_post_thumbnail($player_id)): ?>
                <div style="width:200px; height:200px; border-radius:50%; overflow:hidden; margin:0 auto 20px;">
                    <?php echo get_the_post_thumbnail($player_id, 'medium'); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($cards): ?>
            <div class="player-cards" style="display:grid; grid-template-columns:1fr 1fr; gap:30px;">
                <div class="current-card" style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                    <h2 style="border-bottom:2px solid #eee; padding-bottom:10px; margin-top:0;">
                        Current Evaluation (<?php echo date('M j, Y', strtotime($cards[0]->card_date)); ?>)
                    </h2>
                    <div class="positions" style="margin-bottom:20px;">
                        <strong>Primary Positions:</strong> <?php echo esc_html($cards[0]->positions); ?>
                    </div>
                    <div class="strengths" style="background:#e8f5e9; padding:15px; border-radius:5px; margin-bottom:20px;">
                        <h3 style="margin-top:0;">Strengths</h3>
                        <?php echo wpautop(esc_html($cards[0]->strengths)); ?>
                    </div>
                    <div class="work-ons" style="background:#fff8e1; padding:15px; border-radius:5px;">
                        <h3 style="margin-top:0;">Areas to Work On</h3>
                        <?php echo wpautop(esc_html($cards[0]->work_ons)); ?>
                    </div>
                </div>

                <div class="card-history" style="background:#f9f9f9; padding:20px; border-radius:8px;">
                    <h2 style="margin-top:0;">Evaluation History</h2>
                    <div style="max-height:600px; overflow-y:auto;">
                        <?php foreach ($cards as $index => $card): ?>
                            <?php if ($index === 0) continue; ?>
                            <div style="padding:15px; margin-bottom:15px; background:#fff; border-radius:5px; border-left:4px solid #1e88e5;">
                                <h3 style="margin-top:0; margin-bottom:5px;">
                                    <?php echo date('M j, Y', strtotime($card->card_date)); ?>
                                </h3>
                                <p style="margin:5px 0; color:#666;">
                                    <strong>Positions:</strong> <?php echo esc_html($card->positions); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="no-cards" style="text-align:center; padding:40px; background:#f5f5f5; border-radius:8px;">
                <p>No evaluation cards have been created for this player yet.</p>
            </div>
        <?php endif; ?>
    </div>

<?php get_footer(); ?>
