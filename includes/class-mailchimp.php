<?php
/**
 * Mailchimp integration — direct API sync of approved vendors.
 *
 * Pushes vendor data (email, business name, category, location) to a configured
 * Mailchimp audience as soon as the admin approves a listing. Uses Mailchimp's
 * REST API v3.0 with PUT-upsert so re-running on an existing subscriber updates
 * rather than erroring.
 *
 * No third-party libraries — pure WP HTTP API.
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

class OC_Mailchimp {

	const ACTION_BACKFILL = 'oc_mailchimp_backfill';

	public function register() {
		// Auto-sync on vendor approval (and on dashboard updates of approved vendors).
		add_action( 'oc_after_vendor_approved', [ $this, 'sync_vendor' ] );
		add_action( 'oc_after_vendor_updated',  [ $this, 'maybe_sync_on_update' ] );

		// Admin: manual backfill endpoint for existing approved vendors.
		add_action( 'admin_post_' . self::ACTION_BACKFILL, [ $this, 'handle_backfill' ] );
	}

	/**
	 * Idempotent push of a single vendor to Mailchimp.
	 */
	public function sync_vendor( $post_id ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post || OC_CPT !== $post->post_type ) {
			return false;
		}

		// Only sync approved vendors.
		if ( get_post_status( $post_id ) !== OC_STATUS_APPROVED ) {
			return false;
		}

		$user = get_userdata( (int) $post->post_author );
		if ( ! $user || empty( $user->user_email ) || ! is_email( $user->user_email ) ) {
			return false;
		}

		$business  = get_post_meta( $post_id, '_oc_business_name', true ) ?: $post->post_title;
		$instagram = (string) get_post_meta( $post_id, '_oc_instagram', true );
		$website   = (string) get_post_meta( $post_id, '_oc_website',   true );
		$about     = wp_strip_all_tags( (string) get_post_meta( $post_id, '_oc_bio',      true ) );
		$services  = wp_strip_all_tags( (string) get_post_meta( $post_id, '_oc_services', true ) );
		$whatsapp  = (string) get_post_meta( $post_id, '_oc_whatsapp',  true );
		$areas_raw = get_post_meta( $post_id, '_oc_location_areas', true );
		$areas     = is_array( $areas_raw ) ? implode( ', ', array_filter( array_map( 'strval', $areas_raw ) ) ) : (string) $areas_raw;
		$is_founding = (int) get_post_meta( $post_id, '_oc_founding_vendor', true ) === 1;

		// Category names from the vendor taxonomy (joined; first = primary).
		$cat   = '';
		$terms = wp_get_object_terms( $post_id, OC_TAX, [ 'fields' => 'names' ] );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$cat = implode( ', ', $terms );
		}

		$status = (string) OC_Settings::get( 'mailchimp_status', 'subscribed' );
		if ( ! in_array( $status, [ 'subscribed', 'pending' ], true ) ) {
			$status = 'subscribed';
		}

		// Merge-field keys MUST match the Owambeconnect audience's real tags:
		//   MMERGE19 Business name · MMERGE8 Vendor Category · MMERGE7 Instagram
		//   MMERGE16 Website · MMERGE17 About · MMERGE14 Services Offered
		//   MMERGE11 Areas covered · PHONE Phone.
		// We deliberately do NOT write FNAME (First Name holds the contact's
		// person-name from the original import — the business name belongs in
		// MMERGE19) or MMERGE9 (City — the site only has coarse legacy location
		// text, which would downgrade the real city already on file).
		// array_filter drops empties, so we never overwrite a populated field
		// with a blank on re-sync.
		$payload = [
			'email_address' => $user->user_email,
			'status_if_new' => $status,
			'merge_fields'  => array_filter( [
				'MMERGE19' => $business,
				'MMERGE8'  => $cat,
				'MMERGE7'  => $instagram,
				'MMERGE16' => $website,
				'MMERGE17' => $about,
				'MMERGE14' => $services,
				'MMERGE11' => $areas,
				'PHONE'    => $whatsapp,
			] ),
		];

		$response = $this->request_put_member( $user->user_email, $payload );
		if ( is_wp_error( $response ) || ! $this->is_ok( $response ) ) {
			$this->log_error( 'sync_vendor failed for post ' . $post_id, $response );
			return false;
		}

		// Segmentation tags. "Owambe Vendor" = every synced vendor; the
		// "Founding Vendors" tag mirrors the _oc_founding_vendor admin flag so
		// the website stays the single source of truth for founder status.
		// (Category is segmented via the MMERGE8 field, not a tag, to keep the
		// tag list clean.)
		$tags = array_values( array_filter( [
			'Owambe Vendor',
			$is_founding ? 'Founding Vendors' : '',
			(string) OC_Settings::get( 'mailchimp_default_tag', '' ),
		] ) );
		$this->add_tags( $user->user_email, $tags );

		return true;
	}

	/**
	 * If a vendor is already approved and updates their profile, re-sync to refresh
	 * the merge fields (business name change, new location, etc.).
	 */
	public function maybe_sync_on_update( $post_id ) {
		if ( get_post_status( $post_id ) === OC_STATUS_APPROVED ) {
			$this->sync_vendor( $post_id );
		}
	}

	/**
	 * Backfill — sync every approved vendor that already exists.
	 * Triggered via Settings page "Sync existing vendors" button.
	 */
	public function handle_backfill() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'owambe-connect-core' ) );
		}
		check_admin_referer( self::ACTION_BACKFILL, 'oc_mc_backfill_nonce' );

		$ref = wp_get_referer() ?: admin_url();

		if ( ! $this->is_enabled() ) {
			wp_safe_redirect( add_query_arg( 'oc_mc_error', rawurlencode( __( 'Mailchimp is not enabled or missing API key / audience ID.', 'owambe-connect-core' ) ), $ref ) );
			exit;
		}

		$approved = get_posts( [
			'post_type'      => OC_CPT,
			'post_status'    => OC_STATUS_APPROVED,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$synced = 0;
		$failed = 0;
		foreach ( $approved as $id ) {
			if ( $this->sync_vendor( $id ) ) {
				$synced++;
			} else {
				$failed++;
			}
		}

		$msg = sprintf(
			/* translators: %1$d synced, %2$d failed */
			__( 'Mailchimp backfill complete. Synced: %1$d · failed: %2$d.', 'owambe-connect-core' ),
			$synced,
			$failed
		);
		wp_safe_redirect( add_query_arg( 'oc_mc_notice', rawurlencode( $msg ), $ref ) );
		exit;
	}

	// ───────────────────────── helpers ─────────────────────────

	private function is_enabled() {
		if ( ! class_exists( 'OC_Settings' ) ) return false;
		if ( ! OC_Settings::get( 'mailchimp_enabled' ) )  return false;
		if ( ! OC_Settings::get( 'mailchimp_api_key' ) )  return false;
		if ( ! OC_Settings::get( 'mailchimp_audience_id' ) ) return false;
		return true;
	}

	private function parse_server( $api_key ) {
		// Mailchimp API keys end with a "-usXX" suffix indicating the data center.
		$parts = explode( '-', (string) $api_key );
		$tail  = end( $parts );
		return preg_match( '/^us\d+$/', $tail ) ? $tail : '';
	}

	private function subscriber_hash( $email ) {
		return md5( strtolower( trim( (string) $email ) ) );
	}

	private function api_base() {
		$key    = (string) OC_Settings::get( 'mailchimp_api_key' );
		$server = $this->parse_server( $key );
		return $server ? "https://{$server}.api.mailchimp.com/3.0" : '';
	}

	private function auth_header() {
		$key = (string) OC_Settings::get( 'mailchimp_api_key' );
		return 'Basic ' . base64_encode( 'oc:' . $key );
	}

	private function request_put_member( $email, array $payload ) {
		$base = $this->api_base();
		$list = (string) OC_Settings::get( 'mailchimp_audience_id' );
		if ( ! $base || ! $list ) return new WP_Error( 'oc_mc_config', 'Mailchimp config incomplete' );

		$url = $base . '/lists/' . rawurlencode( $list ) . '/members/' . $this->subscriber_hash( $email );

		return wp_remote_request( $url, [
			'method'  => 'PUT',
			'timeout' => 10,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => $this->auth_header(),
			],
			'body'    => wp_json_encode( $payload ),
		] );
	}

	private function add_tags( $email, array $tag_names ) {
		if ( empty( $tag_names ) ) return;
		$base = $this->api_base();
		$list = (string) OC_Settings::get( 'mailchimp_audience_id' );
		if ( ! $base || ! $list ) return;

		$url = $base . '/lists/' . rawurlencode( $list ) . '/members/' . $this->subscriber_hash( $email ) . '/tags';

		$tags = [];
		foreach ( $tag_names as $name ) {
			$tags[] = [ 'name' => (string) $name, 'status' => 'active' ];
		}

		wp_remote_post( $url, [
			'timeout' => 10,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => $this->auth_header(),
			],
			'body'    => wp_json_encode( [ 'tags' => $tags ] ),
		] );
	}

	private function is_ok( $response ) {
		$code = (int) wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	private function log_error( $context, $response ) {
		if ( is_wp_error( $response ) ) {
			error_log( 'OC Mailchimp: ' . $context . ' → ' . $response->get_error_message() );
			return;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		error_log( "OC Mailchimp: {$context} → HTTP {$code} → {$body}" );
	}
}
