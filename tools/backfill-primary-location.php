<?php
/**
 * N2 — Backfill `_oc_primary_location` from the first `_oc_location_areas`.
 *
 * Usage:
 *   WP-CLI:  wp eval-file wp-content/plugins/owambe-connect-core/tools/backfill-primary-location.php
 *   PHP:     php wp-content/plugins/owambe-connect-core/tools/backfill-primary-location.php
 *
 * Idempotent + versioned: it never overwrites a vendor-set primary. The plugin
 * also runs this automatically once on the first wp-admin load after deploy
 * (OC_Geo::maybe_backfill_primary_location) — this script is for manual / CI
 * runs or to re-verify the state.
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Not under WP-CLI: locate and load wp-load.php by walking up the tree.
	$dir    = __DIR__;
	$loaded = false;
	for ( $i = 0; $i < 8; $i++ ) {
		$dir = dirname( $dir );
		if ( file_exists( $dir . '/wp-load.php' ) ) {
			require $dir . '/wp-load.php';
			$loaded = true;
			break;
		}
	}
	if ( ! $loaded ) {
		fwrite( STDERR, "Could not locate wp-load.php — run this from inside the WordPress install.\n" );
		exit( 1 );
	}
}

if ( ! class_exists( 'OC_Geo' ) || ! method_exists( 'OC_Geo', 'backfill_primary_location' ) ) {
	fwrite( STDERR, "OC_Geo::backfill_primary_location() unavailable — is Owambe Connect Core active?\n" );
	exit( 1 );
}

$updated = (int) OC_Geo::backfill_primary_location();
$message = sprintf( 'Primary-location backfill complete: %d vendor(s) updated.', $updated );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::success( $message );
} else {
	echo $message . "\n";
}
