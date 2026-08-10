<?php
// dynamic_theme.php
//
// Included right after dashboard_shell.css on every customer page.
// Reads whatever the agent picked in agent_theme_settings.php (theme
// style + animation style) and outputs a small <style> block that
// overrides dashboard_shell.css's CSS variables + adds the chosen
// entrance animation. This is the ONE place theme logic lives --
// when the agent changes a setting, every page that includes this
// file updates automatically, nothing else needs editing.
//
// Requires $pdo to already be available (config.php already included
// by the calling page).

$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_theme_settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$theme = $settings['theme_style'] ?? 'elegant';
$animation = $settings['animation_style'] ?? 'fade_up';

$themes = [
    // Elegant -- warm cream/gold, the current default look.
    'elegant' => [
        '--c-bg' => '#faf7f1', '--c-text' => '#2b2620', '--c-muted' => '#9b8f78', '--c-muted-2' => '#8a7f6a',
        '--c-border' => '#ece4d4', '--c-card-bg' => '#fffdfa', '--c-accent' => '#b8912f', '--c-accent-2' => '#c9a24b',
        '--c-accent-grad-1' => '#d9b45a', '--c-accent-ink' => '#241f14', '--font-heading' => "'Georgia', serif",
    ],
    // Classic Elegant -- ivory/deep-navy with a slightly richer gold,
    // a more formal, traditional travel-agency look.
    'classic_elegant' => [
        '--c-bg' => '#fbfaf6', '--c-text' => '#1b2536', '--c-muted' => '#7c8494', '--c-muted-2' => '#6b7383',
        '--c-border' => '#e2e0d8', '--c-card-bg' => '#ffffff', '--c-accent' => '#8c6a1f', '--c-accent-2' => '#a9812c',
        '--c-accent-grad-1' => '#c79f42', '--c-accent-ink' => '#1b1608', '--font-heading' => "'Playfair Display', serif",
    ],
];
$vars = $themes[$theme] ?? $themes['elegant'];

// ---- 10 entrance-animation styles the agent can choose from ----
$animations = [
    'fade_up'    => ['keyframe' => '@keyframes themeAnim { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:translateY(0); } }', 'timing' => '0.6s ease forwards'],
    'fade_in'    => ['keyframe' => '@keyframes themeAnim { from { opacity:0; } to { opacity:1; } }', 'timing' => '0.6s ease forwards'],
    'slide_left' => ['keyframe' => '@keyframes themeAnim { from { opacity:0; transform:translateX(30px); } to { opacity:1; transform:translateX(0); } }', 'timing' => '0.6s ease forwards'],
    'slide_right'=> ['keyframe' => '@keyframes themeAnim { from { opacity:0; transform:translateX(-30px); } to { opacity:1; transform:translateX(0); } }', 'timing' => '0.6s ease forwards'],
    'zoom_in'    => ['keyframe' => '@keyframes themeAnim { from { opacity:0; transform:scale(0.9); } to { opacity:1; transform:scale(1); } }', 'timing' => '0.5s ease forwards'],
    'flip_up'    => ['keyframe' => '@keyframes themeAnim { from { opacity:0; transform:perspective(600px) rotateX(25deg); } to { opacity:1; transform:perspective(600px) rotateX(0); } }', 'timing' => '0.6s ease forwards'],
    'bounce_in'  => ['keyframe' => '@keyframes themeAnim { 0% { opacity:0; transform:scale(0.85); } 60% { opacity:1; transform:scale(1.03); } 100% { transform:scale(1); } }', 'timing' => '0.55s cubic-bezier(.34,1.56,.64,1) forwards'],
    'blur_in'    => ['keyframe' => '@keyframes themeAnim { from { opacity:0; filter:blur(6px); } to { opacity:1; filter:blur(0); } }', 'timing' => '0.6s ease forwards'],
    'rotate_in'  => ['keyframe' => '@keyframes themeAnim { from { opacity:0; transform:rotate(-3deg) translateY(14px); } to { opacity:1; transform:rotate(0) translateY(0); } }', 'timing' => '0.6s ease forwards'],
    'cascade'    => ['keyframe' => '@keyframes themeAnim { from { opacity:0; transform:translateY(22px); } to { opacity:1; transform:translateY(0); } }', 'timing' => '0.6s ease forwards'],
];
$anim = $animations[$animation] ?? $animations['fade_up'];
$is_cascade = ($animation === 'cascade');
?>
<style>
:root {
    <?php foreach ($vars as $k => $v) echo htmlspecialchars($k) . ': ' . htmlspecialchars($v) . '; '; ?>
}
<?php echo $anim['keyframe']; ?>
.stat-strip .cell, .dash-hero .hero-eyebrow, .dash-hero h1, .dash-hero .sub, .dash-hero .hero-cta-row,
.hero-eyebrow, .hero-main h1, .hero-main .sub, .hero-cta-row, .svc-card, tbody tr {
    opacity: 0;
    animation: themeAnim <?php echo $anim['timing']; ?>;
}
<?php if ($is_cascade): ?>
.stat-strip .cell:nth-child(1) { animation-delay: 0.05s; } .stat-strip .cell:nth-child(2) { animation-delay: 0.12s; }
.stat-strip .cell:nth-child(3) { animation-delay: 0.19s; } .stat-strip .cell:nth-child(4) { animation-delay: 0.26s; }
.svc-card:nth-child(1) { animation-delay: 0.1s; } .svc-card:nth-child(2) { animation-delay: 0.22s; } .svc-card:nth-child(3) { animation-delay: 0.34s; }
tbody tr:nth-child(1) { animation-delay: 0.03s; } tbody tr:nth-child(2) { animation-delay: 0.08s; } tbody tr:nth-child(3) { animation-delay: 0.13s; }
tbody tr:nth-child(4) { animation-delay: 0.18s; } tbody tr:nth-child(5) { animation-delay: 0.23s; } tbody tr:nth-child(n+6) { animation-delay: 0.28s; }
<?php else: ?>
.dash-hero .hero-eyebrow, .hero-main .hero-eyebrow { animation-delay: 0.05s; }
.dash-hero h1, .hero-main h1 { animation-delay: 0.15s; }
.dash-hero .sub, .hero-main .sub { animation-delay: 0.25s; }
.dash-hero .hero-cta-row, .hero-main .hero-cta-row, .hero-cta-row { animation-delay: 0.35s; }
<?php endif; ?>
@media (prefers-reduced-motion: reduce) { .stat-strip .cell, .svc-card, tbody tr, .hero-eyebrow, .hero-main h1, .hero-main .sub, .hero-cta-row { animation: none !important; opacity: 1 !important; } }
</style>