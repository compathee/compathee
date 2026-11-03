<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$css = (string) file_get_contents($root . '/public/css/appearance-wave.css');
$php = (string) file_get_contents($root . '/includes/class-frontend.php');
$appearance = (string) file_get_contents($root . '/includes/class-appearance.php');
$admin = (string) file_get_contents($root . '/includes/class-admin.php');
$js = (string) file_get_contents($root . '/public/js/song-list.js');
$player = (string) file_get_contents($root . '/public/js/player.js');
$plugin = (string) file_get_contents($root . '/choir-rehearsal.php');
$uninstall = (string) file_get_contents($root . '/uninstall.php');
$svg = (string) file_get_contents($root . '/public/css/appearance-wave-bg.svg');

$scoped = true;
$stripped = preg_replace('/\/\*.*?\*\//s', '', $css) ?? $css;
foreach (preg_split('/}\s*/', $stripped) ?: array() as $block) {
	$block = trim($block);
	if ($block === '') {
		continue;
	}
	if (str_starts_with($block, '@media') || str_starts_with($block, '@supports')) {
		if (!str_contains($block, 'choir-appearance-wave')) {
			$scoped = false;
		}
		continue;
	}
	if (!str_contains($block, 'choir-appearance-wave')) {
		$scoped = false;
	}
}

$locales = array(
	'et' => array(
		'Appearance' => 'Välimus',
		'Classic' => 'Klassikaline',
		'New' => 'Uus',
		'%d track' => '%d rada',
		'%d tracks' => '%d rada',
	),
	'et_EE' => array(
		'Appearance' => 'Välimus',
		'%d tracks' => '%d rada',
	),
	'ru_RU' => array(
		'Appearance' => 'Оформление',
		'Classic' => 'Классическое',
		'New' => 'Новое',
		'%d track' => '%d трек',
		'%d tracks' => '%d треков',
	),
);
$i18n_ok = true;
foreach ($locales as $locale => $needles) {
	$l10n = (string) file_get_contents($root . '/languages/compath-choir-rehearsal-' . $locale . '.l10n.php');
	foreach ($needles as $src => $translated) {
		if (!str_contains($l10n, var_export($src, true)) || !str_contains($l10n, $translated)) {
			$i18n_ok = false;
		}
	}
}

$checks = array(
	'default_classic' => str_contains($appearance, "'default'           => self::CLASSIC") && str_contains($appearance, "const CLASSIC   = 'classic'"),
	'sanitize_only_new' => str_contains($appearance, "return self::NEW_LOOK === \$value ? self::NEW_LOOK : self::CLASSIC"),
	'body_class_gated' => str_contains($appearance, "self::is_new()") && str_contains($appearance, 'choir-appearance-wave'),
	'settings_select' => str_contains($admin, 'choir_rehearsal_appearance') && str_contains($admin, 'Appearance') && str_contains($admin, '>Classic<') === false && str_contains($admin, "esc_html_e( 'Classic'") && str_contains($admin, "esc_html_e( 'New'"),
	'enqueue_conditional' => str_contains($php, 'appearance-wave.css') && str_contains($php, 'Choir_Rehearsal_Appearance::is_new()'),
	'wave_grid' => str_contains($css, 'grid-template-columns') && str_contains($css, '--choir-col-icon') && str_contains($css, 'choir-song-list--wave'),
	'line_clamp' => str_contains($css, '-webkit-line-clamp: 2') && str_contains($css, 'line-clamp: 2'),
	'title_attribute' => str_contains($php, 'class="choir-song-list__title"') && str_contains($php, 'title="<?php echo esc_attr( $title ); ?>"'),
	'empty_slot' => str_contains($php, 'choir-song-card__pdf') && str_contains($php, 'aria-hidden="true"'),
	'track_badge_a11y' => str_contains($php, 'aria-label="<?php echo esc_attr( $label ); ?>"') && str_contains($php, 'screen-reader-text') && str_contains($php, 'choir-track-count__num') && str_contains($php, 'function track_count_label'),
	'plural_forms' => str_contains($php, " _n( '%d track', '%d tracks', 1,") && str_contains($php, " _n( '%d track', '%d tracks', 2,") && str_contains($php, " _n( '%d track', '%d tracks', 5,") && str_contains($php, 'trackForms'),
	'outline_icons' => str_contains($php, 'stroke-width="1.65"') && str_contains($php, 'M7 2.8h6.1L19.2 8.9V19.2') && str_contains($php, 'choir-icon--youtube') && str_contains($js, 'M7 2.8h6.1L19.2 8.9V19.2') && str_contains($js, 'M10.5 9.2v5.6l4.8-2.8-4.8-2.8z'),
	'js_wave_branch' => str_contains($js, 'config.waveAppearance') && str_contains($js, 'link.title = song.title') && str_contains($js, 'aria-label') && str_contains($js, 'createTrackBadge'),
	'active_track' => str_contains($player, 'is-active') && str_contains($player, 'markActiveTrack') && str_contains($css, '.choir-track-item.is-active'),
	'css_scoped' => $scoped && !str_contains($css, 'url("data:image/') && !preg_match('/\.png|\.jpe?g|\.webp/', $css),
	'svg_vector' => str_contains($svg, '<svg') && !preg_match('/<image|data:image|\.png/', $svg),
	'mobile_columns' => str_contains($css, 'max-width: 480px') && str_contains($css, '--choir-col-icon: 32px'),
	'uninstall' => str_contains($uninstall, "delete_option( 'choir_rehearsal_appearance' )"),
	'version_untouched' => str_contains($plugin, "define( 'CHOIR_REHEARSAL_VERSION', '0.4.73' )"),
	'translations' => $i18n_ok,
	'mixer_button_kept' => str_contains($css, 'choir-mixer-launch') && str_contains($php, 'render_tracks_heading_action'),
);

$fail = 0;
foreach ($checks as $name => $ok) {
	echo ($ok ? 'PASS' : 'FAIL') . " $name\n";
	if (!$ok) {
		$fail++;
	}
}
exit($fail > 0 ? 1 : 0);
