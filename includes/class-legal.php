<?php
/**
 * Legal documents (H5, client change request Aug 2026).
 *
 * Seeds the three client-supplied legal documents as DRAFT pages (never
 * published automatically — they await legal sign-off and the Effective
 * Date) and resolves audience-correct legal URLs for the signup surfaces.
 *
 * Audience model (confirmed by the client): CLIENT terms and VENDOR terms
 * are different documents; the Privacy Policy and Community Guidelines are
 * shared by both. Vendor terms are NOT part of this package — the existing
 * "terms" page is kept as the vendor-terms source until the client supplies
 * the approved vendor document.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Legal {

	/** Bump to re-seed drafts once after deploy. */
	const SEED_VERSION = 2;
	const SEED_OPTION  = 'oc_legal_seed_version';

	/** Effective date the client confirmed (2026-08-04): 1 June 2026. */
	const DEFAULT_EFFECTIVE_DATE = '1 June 2026';

	/** Configurable effective date, printed in place of the [Insert Date] marker. */
	public static function effective_date() {
		$date = trim( (string) oc_get_setting( 'legal_effective_date', '' ) );
		return '' !== $date ? $date : self::DEFAULT_EFFECTIVE_DATE;
	}

	/**
	 * Load a bundled document's HTML and substitute the Effective Date. When
	 * a date is set the visible placeholder marker becomes the real date;
	 * with no date it stays a clearly-marked placeholder.
	 */
	public static function render_content( $slug ) {
		$docs = self::documents();
		if ( empty( $docs[ $slug ] ) ) {
			return '';
		}
		$path = OC_PLUGIN_DIR . 'includes/legal/' . $docs[ $slug ]['file'];
		if ( ! file_exists( $path ) ) {
			return '';
		}
		$html = file_get_contents( $path );
		$date = self::effective_date();
		if ( '' !== $date ) {
			$html = preg_replace(
				'~<mark class="oc-legal-placeholder">.*?</mark>~s',
				esc_html( $date ),
				$html
			);
		}
		return $html;
	}

	/**
	 * Draft documents shipped with the plugin. slug => [title, html file].
	 * Content lives in includes/legal/*.html (generated from the client's
	 * Markdown; mojibake cleaned, [Insert Date] placeholder marked).
	 */
	public static function documents() {
		return [
			'client-terms-and-conditions' => [ 'title' => __( 'Client Terms and Conditions', 'owambe-connect-core' ), 'file' => 'client-terms-and-conditions.html' ],
			'privacy-policy'              => [ 'title' => __( 'Privacy Policy', 'owambe-connect-core' ),              'file' => 'privacy-policy.html' ],
			'community-guidelines'        => [ 'title' => __( 'Community Guidelines', 'owambe-connect-core' ),        'file' => 'community-guidelines.html' ],
		];
	}

	public function register() {
		add_action( 'admin_init', [ __CLASS__, 'maybe_seed_drafts' ] );
	}

	/**
	 * Resolve an audience-correct legal URL by canonical key. Prefers a
	 * settings override, then the modern slug, then the legacy page — so
	 * links resolve to whatever is currently published while the new drafts
	 * await approval.
	 *
	 * @param string $key client-terms|vendor-terms|privacy|community-guidelines
	 * @return string
	 */
	public static function url( $key ) {
		$override_option = [
			'client-terms'         => 'client_terms_url',
			'vendor-terms'         => 'vendor_terms_url',
			'privacy'              => 'privacy_url',
			'community-guidelines' => 'community_guidelines_url',
		][ $key ] ?? '';
		if ( $override_option ) {
			$url = trim( (string) oc_get_setting( $override_option, '' ) );
			if ( '' !== $url ) {
				return $url;
			}
		}

		// slug candidates, most-specific first. Privacy prefers the client's
		// new /privacy-policy/ document over any legacy /privacy/ page so the
		// consent links always show the supplied copy.
		$candidates = [
			'client-terms'         => [ 'client-terms-and-conditions', 'terms' ],
			'vendor-terms'         => [ 'vendor-terms-and-conditions', 'terms' ],
			'privacy'              => [ 'privacy-policy', 'privacy' ],
			'community-guidelines' => [ 'community-guidelines' ],
		][ $key ] ?? [];

		foreach ( $candidates as $slug ) {
			$page = get_page_by_path( $slug );
			// Prefer a published page; fall back to a draft only if nothing
			// published matches (so links resolve to live content once the
			// drafts are published).
			if ( $page && 'publish' === $page->post_status ) {
				return get_permalink( $page );
			}
		}
		foreach ( $candidates as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				return get_permalink( $page );
			}
		}
		return home_url( '/' );
	}

	/**
	 * One-time (versioned) draft seed. Creates any missing document as a
	 * DRAFT. Never publishes, never overwrites an existing page.
	 */
	public static function maybe_seed_drafts() {
		if ( (int) get_option( self::SEED_OPTION, 0 ) >= self::SEED_VERSION ) {
			return;
		}
		self::seed_draft_pages();
		update_option( self::SEED_OPTION, self::SEED_VERSION );
	}

	/**
	 * @return array slug => status ('created-draft' | 'exists' | 'no-content')
	 */
	public static function seed_draft_pages() {
		$result = [];
		foreach ( self::documents() as $slug => $doc ) {
			$content = self::render_content( $slug ); // bundled HTML + effective date.
			if ( '' === $content ) {
				$result[ $slug ] = 'no-content';
				continue;
			}
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				// Refresh the CONTENT of a plugin-managed DRAFT (so a version
				// bump — e.g. the effective date being set — reaches the page)
				// or replace WordPress's "Suggested text:" boilerplate. Never
				// touch a published page or a draft a human has edited (one
				// that no longer contains our placeholder marker or date and
				// isn't the WP stub).
				$is_boilerplate = 'draft' === $existing->post_status
					&& ( '' === trim( $existing->post_content ) || false !== strpos( $existing->post_content, 'Suggested text:' ) );
				$is_ours = 'draft' === $existing->post_status
					&& ( false !== strpos( $existing->post_content, 'oc-legal-placeholder' )
						|| false !== strpos( $existing->post_content, (string) self::effective_date() ) );
				if ( $is_boilerplate || $is_ours ) {
					wp_update_post( [
						'ID'           => $existing->ID,
						'post_title'   => $doc['title'],
						'post_content' => $content,
					] );
					$result[ $slug ] = $is_boilerplate ? 'replaced-boilerplate-draft' : 'refreshed-draft';
				} else {
					$result[ $slug ] = 'exists';
				}
				continue;
			}
			$id = wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'draft', // never published automatically.
				'post_title'   => $doc['title'],
				'post_name'    => $slug,
				'post_content' => $content,
			] );
			$result[ $slug ] = ( $id && ! is_wp_error( $id ) ) ? 'created-draft' : 'error';
		}
		return $result;
	}
}
