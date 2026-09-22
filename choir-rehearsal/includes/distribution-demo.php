<?php
/**
 * Present only in Demo builds (demo.rehearsal.compath.ee).
 * Unlocks Pro-like features under demo caps; no SureCart.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CHOIR_REHEARSAL_DISTRIBUTION' ) ) {
	define( 'CHOIR_REHEARSAL_DISTRIBUTION', 'demo' );
}
