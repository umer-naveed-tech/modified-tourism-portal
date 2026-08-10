<?php
// card_frames.php
//
// 10 elegant "frame" styles that can attach to any listing card's
// boundary (hotel cards, visa cards, etc). Shared so the picker in
// agent_theme_settings.php and the actual rendering in services.php
// stay in sync -- one place defines what "frame_gold_line" etc. means.

function cardFrameOptions() {
    return [
        'none' => 'No Frame (Default)',
        'gold_line' => 'Gold Line',
        'double_border' => 'Double Border',
        'corner_ornament' => 'Corner Ornament',
        'thick_cream' => 'Thick Cream Mat',
        'shadow_lift' => 'Shadow Lift',
        'dotted_gold' => 'Dotted Gold',
        'rounded_soft' => 'Rounded Soft',
        'vintage_sepia' => 'Vintage Sepia',
        'minimal_hairline' => 'Minimal Hairline',
        'ornate_gold' => 'Ornate Gold',
    ];
}

function cardFrameCSS() {
    ?>
    <style>
        /* Applied via class="card-frame-{style}" on any card element
           that already has a photo/background of its own -- these
           only add a border/frame treatment on top, nothing else
           about the card changes. */
        .card-frame-gold_line { border: 2px solid #d4af37; }
        .card-frame-double_border { border: 3px double #c9a24b; padding: 3px; background-clip: padding-box; }
        .card-frame-corner_ornament { position: relative; border: 1px solid rgba(212,175,55,0.3); }
        .card-frame-corner_ornament::before, .card-frame-corner_ornament::after {
            content: ''; position: absolute; width: 22px; height: 22px; border: 2px solid #d4af37; z-index: 2; pointer-events: none;
        }
        .card-frame-corner_ornament::before { top: 8px; left: 8px; border-right: none; border-bottom: none; }
        .card-frame-corner_ornament::after { bottom: 8px; right: 8px; border-left: none; border-top: none; }
        .card-frame-thick_cream { border: 10px solid #fffdfa; background-origin: content-box; background-clip: content-box, border-box; }
        .card-frame-shadow_lift { border: none; box-shadow: 0 20px 45px rgba(60,45,20,0.28), 0 0 0 1px rgba(212,175,55,0.15); }
        .card-frame-dotted_gold { border: 3px dotted #c9a24b; padding: 4px; background-clip: padding-box; }
        .card-frame-rounded_soft { border: 6px solid rgba(255,253,250,0.9); border-radius: 26px !important; background-origin: content-box; background-clip: content-box, border-box; }
        .card-frame-vintage_sepia { border: 8px solid #e8dcc0; box-shadow: inset 0 0 0 1px #b8912f; background-origin: content-box; background-clip: content-box, border-box; }
        .card-frame-minimal_hairline { border: 1px solid rgba(212,175,55,0.5); }
        .card-frame-ornate_gold { border: 4px solid #d4af37; box-shadow: 0 0 0 1px #d4af37, inset 0 0 0 4px rgba(255,253,250,0.85); }
    </style>
    <?php
}