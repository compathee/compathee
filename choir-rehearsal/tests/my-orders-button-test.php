<?php
/**
 * Pro License page links to the shop customer dashboard.
 *
 * Run: php choir-rehearsal/tests/my-orders-button-test.php
 */

declare(strict_types=1);

$admin    = (string) file_get_contents( __DIR__ . '/../includes/class-admin.php' );
$settings = (string) file_get_contents( dirname( __DIR__, 2 ) . '/choir-rehearsal-pro/licensing/src/Settings.php' );

$button_bits = array(
	'button button-secondary',
	'target="_blank"',
	'rel="noopener noreferrer"',
	"esc_url( 'https://shop.compath.ee/customer-dashboard/' )",
	"esc_html_e( 'My orders..', 'compath-choir-rehearsal' )",
);

$checks = array(
	'lite method renders orders button' => str_contains( $admin, 'function render_my_orders_button' ),
	'pro license page calls orders button' => str_contains( $settings, 'render_my_orders_button' ),
);

foreach ( $button_bits as $bit ) {
	$checks[ 'lite markup ' . $bit ] = str_contains( $admin, $bit );
	$checks[ 'pro markup ' . $bit ]  = str_contains( $settings, $bit );
}

$translations = array(
	'et'    => 'Minu tellimused..',
	'et_EE' => 'Minu tellimused..',
	'ru_RU' => 'Мои заказы..',
);

foreach ( $translations as $locale => $msgstr ) {
	$base = __DIR__ . '/../languages/compath-choir-rehearsal-' . $locale;
	$po   = (string) file_get_contents( $base . '.po' );
	$l10n = (string) file_get_contents( $base . '.l10n.php' );
	$mo   = (string) file_get_contents( $base . '.mo' );
	$checks[ $locale . ' po' ]   = str_contains( $po, 'msgid "My orders.."' ) && str_contains( $po, 'msgstr "' . $msgstr . '"' );
	$checks[ $locale . ' l10n' ] = str_contains( $l10n, "'My orders..' => '" . $msgstr . "'" );
	$checks[ $locale . ' mo' ]   = str_contains( $mo, 'My orders..' ) && str_contains( $mo, $msgstr );
}

define( 'ABSPATH', __DIR__ );

function esc_url( string $url ): string {
	return $url;
}

function esc_html_e( string $text, string $domain = '' ): void {
	unset( $domain );
	echo htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

require dirname( __DIR__ ) . '/includes/class-admin.php';

$html = '';
if ( method_exists( 'Choir_Rehearsal_Admin', 'render_my_orders_button' ) ) {
	ob_start();
	Choir_Rehearsal_Admin::render_my_orders_button();
	$html = (string) ob_get_clean();
}

$checks['rendered tag'] = str_contains( $html, '<a class="button button-secondary" href="https://shop.compath.ee/customer-dashboard/" target="_blank" rel="noopener noreferrer">My orders..</a>' );

$fail = 0;
foreach ( $checks as $name => $ok ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' ' . $name . "\n";
	if ( ! $ok ) {
		++$fail;
	}
}

exit( $fail > 0 ? 1 : 0 );
