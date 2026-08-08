<?php
/**
 * Versioned consent records (H6, client change request Aug 2026).
 *
 * Replaces the single `_oc_terms_accepted` timestamp with an append-only log
 * of per-document consent captured at signup. Each record stores who accepted
 * what, when (UTC), how, and which VERSION of each document — so a later
 * document revision can be tracked as a fresh acceptance.
 *
 * Storage: user meta `_oc_consent_records` = array of records. Legacy
 * `_oc_terms_accepted` is still written for backward compatibility.
 *
 * Never retro-marks existing users (records are only created at an explicit
 * accept action); never pre-checks a box (the templates render unchecked and
 * `required`, and the server rejects a missing tick).
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Consent {

	const META_RECORDS = '_oc_consent_records';
	const META_LEGACY  = '_oc_terms_accepted';

	public function register() {
		// Admin-only, read-only consent history on the user profile screen.
		add_action( 'show_user_profile', [ __CLASS__, 'render_profile_history' ] );
		add_action( 'edit_user_profile', [ __CLASS__, 'render_profile_history' ] );
	}

	/**
	 * Which legal documents each account type must accept.
	 *
	 * @param string $account_type 'client' | 'vendor'
	 * @return string[] OC_Legal canonical keys.
	 */
	public static function documents_for( $account_type ) {
		$map = [
			'client' => [ 'client-terms', 'privacy', 'community-guidelines' ],
			'vendor' => [ 'vendor-terms', 'privacy', 'community-guidelines' ],
		];
		return $map[ $account_type ] ?? $map['client'];
	}

	/**
	 * Snapshot of a document at acceptance time: page ID, URL, and the
	 * version (its effective date). Falls back gracefully if OC_Legal or the
	 * page is unavailable.
	 *
	 * @param string $key OC_Legal canonical key.
	 * @return array{key:string,id:int,url:string,version:string}
	 */
	public static function document_snapshot( $key ) {
		$url     = function_exists( 'oc_legal_url' ) ? oc_legal_url( $key ) : '';
		$id      = $url ? (int) url_to_postid( $url ) : 0;
		$version = class_exists( 'OC_Legal' ) ? OC_Legal::effective_date() : '';
		return [
			'key'     => $key,
			'id'      => $id,
			'url'     => $url,
			'version' => $version,
		];
	}

	/**
	 * Record a consent event at signup. Appends one record covering every
	 * document the account type must accept.
	 *
	 * @param int    $user_id      New user's ID.
	 * @param string $account_type 'client' | 'vendor'
	 * @param string $source       'native' | 'google'
	 * @return bool
	 */
	public static function record( $user_id, $account_type, $source = 'native' ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		$account_type = in_array( $account_type, [ 'client', 'vendor' ], true ) ? $account_type : 'client';
		$source       = in_array( $source, [ 'native', 'google' ], true ) ? $source : 'native';

		$documents = [];
		foreach ( self::documents_for( $account_type ) as $key ) {
			$documents[] = self::document_snapshot( $key );
		}

		$record = [
			'time'      => time(),               // UTC (WordPress stores/compares in UTC).
			'time_gmt'  => gmdate( 'c' ),         // ISO-8601 UTC, human-auditable.
			'account'   => $account_type,
			'source'    => $source,
			'ip'        => self::hashed_ip(),     // salted hash — proof-of-origin without storing PII.
			'documents' => $documents,
		];

		$records   = get_user_meta( $user_id, self::META_RECORDS, true );
		$records   = is_array( $records ) ? $records : [];
		$records[] = $record;
		update_user_meta( $user_id, self::META_RECORDS, $records );

		// Backward compatibility: keep the legacy timestamp populated.
		update_user_meta( $user_id, self::META_LEGACY, $record['time'] );

		return true;
	}

	/**
	 * All consent records for a user, newest first.
	 *
	 * @param int $user_id User ID.
	 * @return array[]
	 */
	public static function history( $user_id ) {
		$records = get_user_meta( (int) $user_id, self::META_RECORDS, true );
		$records = is_array( $records ) ? $records : [];
		usort( $records, function ( $a, $b ) {
			return (int) ( $b['time'] ?? 0 ) <=> (int) ( $a['time'] ?? 0 );
		} );
		return $records;
	}

	/** Salted one-way hash of the client IP (no raw PII stored). */
	private static function hashed_ip() {
		$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return '' !== $ip ? substr( wp_hash( $ip ), 0, 16 ) : '';
	}

	/* ── Admin view ───────────────────────────────────────────────────── */

	/**
	 * Read-only consent history on the user's profile screen (admins only).
	 *
	 * @param WP_User $user
	 */
	public static function render_profile_history( $user ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		$records = self::history( $user->ID );
		?>
		<h2><?php esc_html_e( 'Consent history', 'owambe-connect-core' ); ?></h2>
		<table class="form-table" role="presentation"><tr><td style="padding-left:0;">
		<?php if ( ! $records ) : ?>
			<p class="description">
				<?php
				// A user with the legacy timestamp but no versioned record
				// pre-dates this system — shown transparently, never fabricated.
				$legacy = (int) get_user_meta( $user->ID, self::META_LEGACY, true );
				if ( $legacy > 0 ) {
					printf(
						/* translators: %s: date */
						esc_html__( 'No versioned consent record. Legacy acceptance timestamp: %s (pre-dates the versioned system).', 'owambe-connect-core' ),
						esc_html( wp_date( 'j M Y, H:i', $legacy ) )
					);
				} else {
					esc_html_e( 'No consent record on file for this user.', 'owambe-connect-core' );
				}
				?>
			</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:820px;">
				<thead><tr>
					<th><?php esc_html_e( 'Accepted (UTC)', 'owambe-connect-core' ); ?></th>
					<th><?php esc_html_e( 'Account', 'owambe-connect-core' ); ?></th>
					<th><?php esc_html_e( 'Source', 'owambe-connect-core' ); ?></th>
					<th><?php esc_html_e( 'Documents (version)', 'owambe-connect-core' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $records as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r['time_gmt'] ?? gmdate( 'c', (int) ( $r['time'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( ucfirst( (string) ( $r['account'] ?? '' ) ) ); ?></td>
						<td><?php echo esc_html( ucfirst( (string) ( $r['source'] ?? 'native' ) ) ); ?></td>
						<td>
							<?php foreach ( (array) ( $r['documents'] ?? [] ) as $d ) : ?>
								<div>
									<?php if ( ! empty( $d['url'] ) ) : ?>
										<a href="<?php echo esc_url( $d['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $d['key'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $d['key'] ); ?>
									<?php endif; ?>
									<?php if ( ! empty( $d['version'] ) ) : ?>
										<span style="color:#6b6361;">(<?php echo esc_html( $d['version'] ); ?>)</span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		</td></tr></table>
		<?php
	}
}
