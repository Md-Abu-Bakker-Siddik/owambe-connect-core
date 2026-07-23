<?php
/**
 * Activation / deactivation routines.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Activator {

	public static function activate() {
		OC_CPT_Manager::register_post_type();
		OC_CPT_Manager::register_taxonomy();
		OC_CPT_Manager::register_role();
		if ( class_exists( 'OC_Client' ) ) {
			OC_Client::register_role();
		}
		self::create_tables();
		self::seed_categories();
		self::seed_pages();
		self::seed_legal_pages();
		self::backfill_vendor_numbers();
		self::upgrade_seeded_pages();
		flush_rewrite_rules();
	}

	/**
	 * Phase 2 metric stores. Counters and RSVPs must never live in the
	 * option-array logs (read-modify-write, capped) — these tables are the
	 * plugin's first persistent metric storage. dbDelta is idempotent.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_vendor_stats (
			vendor_id BIGINT(20) UNSIGNED NOT NULL,
			stat_date DATE NOT NULL,
			metric VARCHAR(32) NOT NULL,
			count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (vendor_id,stat_date,metric),
			KEY stat_date (stat_date)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}oc_rsvps (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT(20) UNSIGNED NOT NULL,
			guest_name VARCHAR(190) NOT NULL DEFAULT '',
			guest_email VARCHAR(190) NOT NULL DEFAULT '',
			attending TINYINT(1) NOT NULL DEFAULT 1,
			guest_count SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
			note TEXT NULL,
			created DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event_id (event_id)
		) {$charset};" );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	private static function seed_categories() {
		foreach ( oc_default_categories() as $cat ) {
			if ( ! term_exists( $cat['slug'], OC_TAX ) ) {
				wp_insert_term( $cat['name'], OC_TAX, [ 'slug' => $cat['slug'] ] );
			}
		}
	}

	/**
	 * Idempotent page seeding — only creates pages that don't exist yet.
	 */
	private static function seed_pages() {
		$pages = [
			[
				'slug'    => 'home',
				'title'   => __( 'Home', 'owambe-connect-core' ),
				// Browse-by-Category uses the horizontal-scroll layout per client feedback §6.6.
				'content' => "<!-- wp:shortcode -->[oc_hero_search]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[oc_category_grid layout=\"scroll\"]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[oc_featured_vendors count=\"6\"]<!-- /wp:shortcode -->",
				'is_home' => true,
			],
			[
				'slug'    => 'vendors',
				'title'   => __( 'Vendor Directory', 'owambe-connect-core' ),
				'content' => '[oc_directory]',
			],
			[
				'slug'    => 'become-a-vendor',
				'title'   => __( 'Become a Vendor', 'owambe-connect-core' ),
				'content' => '[oc_become_a_vendor_cta]',
			],
			[
				'slug'    => 'apply',
				'title'   => __( 'Vendor Application', 'owambe-connect-core' ),
				'content' => '[oc_register_form]',
				'parent'  => 'become-a-vendor',
			],
			[
				'slug'    => 'vendor-login',
				'title'   => __( 'Vendor Login', 'owambe-connect-core' ),
				'content' => '[oc_login_form]',
			],
			[
				'slug'    => 'vendor-dashboard',
				'title'   => __( 'Vendor Dashboard', 'owambe-connect-core' ),
				'content' => '[oc_vendor_dashboard]',
			],
			[
				'slug'    => 'forgot-password',
				'title'   => __( 'Forgot Password', 'owambe-connect-core' ),
				'content' => '[oc_forgot_password]',
			],
			[
				'slug'    => 'reset-password',
				'title'   => __( 'Reset Password', 'owambe-connect-core' ),
				'content' => '[oc_reset_password]',
			],
			[
				'slug'    => 'about',
				'title'   => __( 'About Owambe Connect', 'owambe-connect-core' ),
				// The Vision / Mission / Story copy lives as the default values
				// inside the about-blocks widget (sourced from the client feedback
				// xlsx). The page just needs to render the widget.
				'content' => '<!-- wp:shortcode -->[oc_about_blocks]<!-- /wp:shortcode -->',
			],
			[
				'slug'    => 'contact',
				'title'   => __( 'Contact', 'owambe-connect-core' ),
				'content' => '[oc_contact_form]',
			],
			[
				'slug'    => 'client-login',
				'title'   => __( 'Sign In', 'owambe-connect-core' ),
				'content' => '[oc_client_login]',
			],
			[
				'slug'    => 'client-dashboard',
				'title'   => __( 'My Dashboard', 'owambe-connect-core' ),
				'content' => '[oc_client_dashboard]',
			],
			[
				'slug'    => 'safety',
				'title'   => __( 'Website Safety', 'owambe-connect-core' ),
				'content' => '[oc_safety_info]',
			],
		];

		$slug_to_id = [];

		foreach ( $pages as $page ) {
			$existing = get_page_by_path( $page['slug'] );
			if ( $existing ) {
				$slug_to_id[ $page['slug'] ] = $existing->ID;
				continue;
			}

			$args = [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $page['title'],
				'post_name'    => $page['slug'],
				'post_content' => $page['content'],
			];

			if ( ! empty( $page['parent'] ) && isset( $slug_to_id[ $page['parent'] ] ) ) {
				$args['post_parent'] = $slug_to_id[ $page['parent'] ];
			}

			$id = wp_insert_post( $args );
			if ( $id && ! is_wp_error( $id ) ) {
				$slug_to_id[ $page['slug'] ] = $id;
				if ( ! empty( $page['is_home'] ) ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', $id );
				}
			}
		}
	}

	/**
	 * Seed /terms and /privacy pages with the real legal copy supplied by
	 * the client (May 2026). If the page already exists with the older
	 * placeholder content we upgrade it; if it's been customised we leave
	 * it alone. New installs get the full text on first activation.
	 */
	/**
	 * Public re-entry for self-heal: lets the runtime re-seed missing legal
	 * pages without forcing a plugin re-activation. Safe to call repeatedly
	 * because seed_legal_pages() is idempotent.
	 */
	public static function ensure_legal_pages() {
		self::seed_legal_pages();
	}

	private static function seed_legal_pages() {
		$pages = [
			'terms' => [
				'title'   => __( 'Vendor Terms & Conditions', 'owambe-connect-core' ),
				'content' => self::legal_terms_html(),
			],
			'privacy' => [
				'title'   => __( 'Privacy Policy', 'owambe-connect-core' ),
				'content' => self::legal_privacy_html(),
			],
		];
		foreach ( $pages as $slug => $p ) {
			$existing = get_page_by_path( $slug );
			if ( ! $existing ) {
				wp_insert_post( [
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $p['title'],
					'post_name'    => $slug,
					'post_content' => $p['content'],
				] );
				continue;
			}
			// Upgrade from the old placeholder string.
			$current = trim( (string) $existing->post_content );
			$placeholder_prefix = '<p><em>Owambe Connect';
			if ( strpos( $current, $placeholder_prefix ) === 0 ) {
				wp_update_post( [
					'ID'           => $existing->ID,
					'post_title'   => $p['title'],
					'post_content' => $p['content'],
				] );
			}
		}
	}

	/**
	 * Vendor Terms & Conditions (May 2026), client-supplied.
	 * Stored as raw HTML in post_content so the Block editor can re-render
	 * verbatim. If you ever amend the policy, treat THIS function as the
	 * source of truth and re-run the activator to refresh the page.
	 */
	private static function legal_terms_html() {
		ob_start();
		?>
<p><strong>Owambe Connect — Vendor Terms &amp; Conditions</strong><br>
<em>Last Updated: May 2026</em></p>

<p>Welcome to Owambe Connect ("Owambe Connect", "we", "our", or "us").</p>
<p>These Terms &amp; Conditions and our Privacy Policy govern the use of the Owambe Connect platform by vendors, suppliers, creatives, venues, service providers, and businesses ("Vendor", "you", or "your").</p>
<p>By registering as a Vendor on Owambe Connect, you confirm that you have read, understood, and agreed to these Terms and our <a href="/privacy/">Privacy Policy</a>.</p>

<h2>Section 1 — About Owambe Connect</h2>
<p>Owambe Connect is an online directory and marketplace platform designed to connect customers with event vendors and service providers for weddings, celebrations, parties, corporate events, cultural events, and other occasions.</p>
<p>The platform may allow vendors to:</p>
<ul><li>Create business listings</li><li>Receive customer enquiries</li><li>Promote services</li><li>Link social media accounts</li><li>Communicate with potential customers</li><li>Advertise services and products</li></ul>
<p>Unless expressly stated otherwise, Owambe Connect acts solely as a platform provider and is not a direct party to agreements between customers and vendors.</p>

<h2>Section 2 — Eligibility</h2>
<p>To register as a Vendor, you must:</p>
<ul><li>Be at least 18 years old</li><li>Have authority to act on behalf of the business being registered</li><li>Provide accurate and truthful information</li><li>Comply with all applicable laws and regulations relevant to your business and services</li></ul>
<p>We reserve the right to request verification documents or additional information at any time.</p>

<h2>Section 3 — Vendor Responsibilities</h2>
<p>By using the platform, you agree to:</p>
<ul><li>Maintain accurate business information</li><li>Respond professionally to enquiries</li><li>Deliver services honestly and professionally</li><li>Ensure advertised information is truthful and not misleading</li><li>Maintain all licences, permits, registrations, and insurance required for your services</li><li>Comply with all applicable UK laws and regulations</li><li>Respect customers and other vendors</li><li>Avoid fraudulent, discriminatory, abusive, or misleading behaviour</li></ul>
<p>You acknowledge that you are solely responsible for:</p>
<ul><li>Your services</li><li>Customer interactions</li><li>Pricing</li><li>Contracts</li><li>Refunds</li><li>Disputes</li><li>Bookings</li><li>Taxes and business compliance obligations</li></ul>

<h2>Section 4 — Legal, Tax &amp; Regulatory Compliance</h2>
<p>Each Vendor is solely responsible for ensuring compliance with all applicable UK laws and regulations relating to their business activities, including but not limited to:</p>
<ul><li>UK tax obligations</li><li>VAT obligations where applicable</li><li>Business registration requirements</li><li>Insurance requirements</li><li>Licensing and permits</li><li>Consumer protection laws</li><li>Employment laws</li><li>Health and safety regulations</li><li>Advertising regulations</li><li>Data protection obligations</li><li>Immigration and right-to-work requirements where applicable</li></ul>
<p>Vendors are responsible for accurately reporting and paying all taxes, VAT, duties, levies, and other governmental charges arising from their business activities.</p>
<p>Owambe Connect does not provide legal, tax, financial, or regulatory advice and accepts no responsibility for a Vendor's failure to comply with applicable laws or regulations.</p>
<p>We reserve the right to suspend or remove any Vendor where we reasonably believe breaches of law, regulation, or platform rules may have occurred.</p>

<h2>Section 5 — Vendor Verification</h2>
<p>Owambe Connect may provide optional or mandatory verification processes, including:</p>
<ul><li>Email verification</li><li>Phone verification</li><li>Identity verification</li><li>Social media verification</li><li>Business verification</li><li>Payment verification</li></ul>
<p>Verification status or badges do not constitute endorsement, recommendation, guarantee, or certification by Owambe Connect.</p>
<p>We reserve the right to revoke verification status at any time.</p>

<h2>Section 6 — Listings &amp; Content</h2>
<p>You retain ownership of the content you upload to the platform, including:</p>
<ul><li>Images</li><li>Logos</li><li>Videos</li><li>Business descriptions</li><li>Promotional materials</li></ul>
<p>By uploading content, you grant Owambe Connect a non-exclusive, worldwide, royalty-free licence to:</p>
<ul><li>Display your content on the platform</li><li>Use your content in marketing and promotional materials</li><li>Share your content on social media and advertising channels for promotional purposes</li></ul>
<p>You confirm that:</p>
<ul><li>You own or have permission to use all uploaded content</li><li>Your content does not infringe intellectual property rights</li><li>Your content is lawful, accurate, and appropriate</li></ul>
<p>We reserve the right to edit, reject, suspend, or remove content or listings that breach these Terms or applicable laws.</p>

<h2>Section 7 — Customer Relationships &amp; Bookings</h2>
<p>Any agreement, booking, transaction, or dispute between a Vendor and customer is directly between those parties unless otherwise stated.</p>
<p>Owambe Connect is not responsible for:</p>
<ul><li>Service quality</li><li>Vendor performance</li><li>Missed bookings</li><li>Refund disputes</li><li>Customer conduct</li><li>Financial losses</li><li>Cancellation disputes</li><li>Miscommunication between parties</li></ul>
<p>We may investigate complaints and take action where appropriate to maintain platform standards and safety.</p>

<h2>Section 8 — Payments, Fees &amp; Subscriptions</h2>
<p>Some platform features may become paid services in the future, including:</p>
<ul><li>Featured listings</li><li>Advertising placements</li><li>Subscription plans</li><li>Premium visibility features</li><li>Promotional packages</li></ul>
<p>Where charges apply, pricing and billing information will be communicated clearly before payment is required.</p>
<p>Unless otherwise stated, fees paid are non-refundable.</p>

<h2>Section 9 — Reviews &amp; Ratings</h2>
<p>Customers may leave reviews based on genuine experiences.</p>
<p>Vendors agree not to:</p>
<ul><li>Post fake reviews</li><li>Manipulate ratings</li><li>Offer incentives for misleading reviews</li><li>Harass customers regarding reviews</li></ul>
<p>We reserve the right to remove reviews or ratings that breach platform rules, applicable laws, or our moderation standards.</p>

<h2>Section 10 — Prohibited Activities</h2>
<p>Vendors must not:</p>
<ul><li>Provide illegal services</li><li>Upload false or misleading information</li><li>Use the platform for spam or unsolicited marketing</li><li>Circumvent platform systems</li><li>Impersonate another business or individual</li><li>Upload harmful software or malicious code</li><li>Engage in discriminatory, abusive, or offensive conduct</li><li>Infringe intellectual property rights</li></ul>
<p>Breaches may result in suspension, removal, or legal action where necessary.</p>

<h2>Section 11 — Intellectual Property</h2>
<p>All platform branding, logos, graphics, website content, software, and materials belonging to Owambe Connect remain our intellectual property unless otherwise stated.</p>
<p>You may not reproduce, copy, distribute, or exploit platform materials without written permission.</p>

<h2>Section 12 — Limitation of Liability</h2>
<p>To the fullest extent permitted under UK law:</p>
<ul><li>Owambe Connect provides the platform on an "as is" and "as available" basis</li><li>We do not guarantee uninterrupted or error-free operation</li><li>We are not liable for indirect, incidental, or consequential losses arising from platform use</li></ul>
<p>Our total liability to any Vendor shall not exceed any fees paid by that Vendor to Owambe Connect within the preceding 12 months.</p>
<p>Nothing in these Terms excludes liability where exclusion would be unlawful under applicable law.</p>

<h2>Section 13 — Suspension &amp; Termination</h2>
<p>We reserve the right to suspend, restrict, or terminate Vendor accounts where:</p>
<ul><li>These Terms are breached</li><li>Fraudulent activity is suspected</li><li>Safety concerns arise</li><li>Legal or regulatory issues arise</li><li>Platform misuse occurs</li></ul>
<p>Vendors may request account closure at any time.</p>

<h2>Section 15 — Changes to These Terms</h2>
<p>We may update these Terms and Privacy Policy from time to time.</p>
<p>Updated versions will be published on the platform and continued use of the platform constitutes acceptance of revised terms.</p>

<h2>Section 16 — Governing Law</h2>
<p>These Terms shall be governed by and interpreted in accordance with the laws of England and Wales.</p>
<p>Any disputes arising from use of the platform shall be subject to the jurisdiction of the courts of England and Wales.</p>

<h2>Section 17 — Contact Information</h2>
<p>For support, legal enquiries, or privacy-related requests, please contact:</p>
<p>Email: <a href="mailto:info@owambeconnect.com">info@owambeconnect.com</a><br>
Website: <a href="https://www.owambeconnect.com">www.owambeconnect.com</a></p>

<p><em>For data-protection-specific matters, see our <a href="/privacy/">Privacy Policy</a>.</em></p>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Privacy Policy (May 2026), client-supplied. Standalone version of
	 * Section 14 with the surrounding contact + governance boilerplate, so
	 * /privacy is a complete, link-shareable legal document.
	 */
	private static function legal_privacy_html() {
		ob_start();
		?>
<p><strong>Owambe Connect — Privacy Policy</strong><br>
<em>Last Updated: May 2026</em></p>

<p>This Privacy Policy explains how Owambe Connect ("we", "our", or "us") collects, uses, and protects personal data. It forms part of, and should be read alongside, our <a href="/terms/">Vendor Terms &amp; Conditions</a>.</p>

<h2>1. Information We Collect</h2>
<p>We may collect:</p>
<ul><li>Business names</li><li>Contact information</li><li>Email addresses</li><li>Phone numbers</li><li>Social media handles</li><li>Vendor profile content</li><li>Uploaded images and media</li><li>Verification information</li><li>Customer enquiry information</li><li>Technical data including IP address and browser/device information</li></ul>

<h2>2. How We Use Information</h2>
<p>We may use information to:</p>
<ul><li>Operate and improve the platform</li><li>Display vendor listings</li><li>Facilitate customer enquiries</li><li>Verify accounts</li><li>Prevent fraud and misuse</li><li>Improve platform safety and security</li><li>Send service-related communications</li><li>Improve user experience</li><li>Comply with legal obligations</li></ul>
<p>We only process personal data where lawful under UK GDPR and applicable UK data protection laws.</p>

<h2>3. Public Vendor Information</h2>
<p>Vendor profile information may be publicly displayed, including:</p>
<ul><li>Business name</li><li>Images</li><li>Service descriptions</li><li>Social media links</li><li>Contact details where enabled</li></ul>
<p>Vendors are responsible for ensuring publicly displayed information is appropriate and lawful.</p>

<h2>4. Sharing Information</h2>
<p>We do not sell personal data.</p>
<p>We may share information with:</p>
<ul><li>Service providers supporting platform operations</li><li>Payment processors</li><li>Verification providers</li><li>Legal or regulatory authorities where required by law</li></ul>

<h2>5. Data Security</h2>
<p>We implement reasonable technical and organisational security measures to protect personal information.</p>
<p>However, no online platform can guarantee absolute security.</p>

<h2>6. Cookies &amp; Analytics</h2>
<p>We may use cookies and analytics technologies to:</p>
<ul><li>Improve website functionality</li><li>Understand user behaviour</li><li>Measure engagement and traffic</li><li>Remember preferences</li><li>Enhance user experience</li></ul>
<p>Users may manage cookie preferences through browser settings.</p>

<h2>7. Your Rights</h2>
<p>Under UK GDPR, users may have rights including:</p>
<ul><li>Access to personal data</li><li>Correction of inaccurate information</li><li>Deletion of personal data</li><li>Restriction of processing</li><li>Objection to processing</li><li>Data portability</li></ul>
<p>Requests may be submitted using the contact information below.</p>

<h2>8. Data Retention</h2>
<p>We retain information only for as long as reasonably necessary for operational, legal, security, and compliance purposes.</p>

<h2>9. Third-Party Links</h2>
<p>Vendor profiles may contain links to third-party websites including Instagram, WhatsApp, TikTok, booking systems, or other external platforms.</p>
<p>Owambe Connect is not responsible for third-party privacy practices or content.</p>

<h2>10. Children</h2>
<p>The platform is not intended for individuals under 18 years old.</p>

<h2>11. Changes to This Policy</h2>
<p>We may update this Privacy Policy from time to time.</p>
<p>Updated versions will be published on the platform and continued use of the platform constitutes acceptance of revised terms.</p>

<h2>12. Governing Law</h2>
<p>This Privacy Policy is governed by the laws of England and Wales.</p>

<h2>13. Contact</h2>
<p>For privacy-related requests, please contact:</p>
<p>Email: <a href="mailto:info@owambeconnect.com">info@owambeconnect.com</a><br>
Website: <a href="https://www.owambeconnect.com">www.owambeconnect.com</a></p>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Idempotent: assign OC####VRN to every vendor post that doesn't have
	 * one yet. Safe to re-run on activation; new vendors get theirs at
	 * registration via the helpers.php hook.
	 */
	private static function backfill_vendor_numbers() {
		if ( function_exists( 'oc_backfill_vendor_numbers' ) ) {
			oc_backfill_vendor_numbers();
		}
	}

	/**
	 * Upgrade previously-seeded pages whose content still matches an old
	 * default. Targeted, idempotent, never overwrites a customised page —
	 * we compare the existing post_content against the prior shipped seed
	 * and only swap when it matches exactly.
	 */
	private static function upgrade_seeded_pages() {
		$migrations = [
			// Home: bump category grid to horizontal scroll per §6.6.
			'home' => [
				'from' => [
					"<!-- wp:shortcode -->[oc_hero_search]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[oc_category_grid]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[oc_featured_vendors count=\"6\"]<!-- /wp:shortcode -->",
				],
				'to'   => "<!-- wp:shortcode -->[oc_hero_search]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[oc_category_grid layout=\"scroll\"]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[oc_featured_vendors count=\"6\"]<!-- /wp:shortcode -->",
			],

			// About: swap to the shortcode that carries the client's Vision/Mission/Story copy.
			'about' => [
				'from' => [
					'<p>Owambe Connect is the UK\'s home for finding event service vendors who understand the cultures we celebrate. From Nigerian weddings to Pakistani mehndis, Indian sangeets to Chinese banquets — we connect planners with caterers, photographers, decorators, MUAs and more, all in one place.</p>',
				],
				'to'   => '<!-- wp:shortcode -->[oc_about_blocks]<!-- /wp:shortcode -->',
			],
		];

		foreach ( $migrations as $slug => $migration ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				continue;
			}
			$current = trim( (string) $page->post_content );
			$matches_old = false;
			foreach ( $migration['from'] as $old ) {
				if ( $current === trim( $old ) ) {
					$matches_old = true;
					break;
				}
			}
			if ( ! $matches_old ) {
				// Page was customised — leave it alone.
				continue;
			}
			wp_update_post( [
				'ID'           => $page->ID,
				'post_content' => $migration['to'],
			] );
		}
	}
}
