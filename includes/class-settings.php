<?php
/**
 * Owambe Connect — Settings page (Settings API).
 * Single option `oc_settings` holds everything.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Settings {

	const OPTION = 'oc_settings';

	public static function defaults() {
		return [
			'tagline'             => 'Celebrating Culture. Connecting Communities.',
			'from_name'           => 'Owambe Connect',
			'from_email'          => '',
			'notification_email'  => '',
			'directory_per_page'  => 12,
			'featured_count'      => 6,
			'logo_max_mb'         => 2,
			'banner_max_mb'       => 5,
			'gallery_max_images'  => 6,
			'gallery_max_mb'      => 3,
			'auto_approve'        => 0,
			'languages'           => 'English, Yoruba, Igbo, Hausa, Pidgin, Urdu, Hindi, Punjabi, Bengali, Mandarin, Cantonese, Arabic, French, Spanish',
			'primary_color'       => '#6E0F2C',
			'accent_color'        => '#C9A961',

			// Integrations — populated from the Settings page, no constants required.
			'recaptcha_site_key'   => '',
			'recaptcha_secret_key' => '',
			'recaptcha_threshold'  => '0.5',
			'mailchimp_enabled'     => 0,
			'mailchimp_api_key'     => '',
			'mailchimp_audience_id' => '',
			'mailchimp_status'      => 'subscribed', // subscribed | pending (double opt-in)
			'mailchimp_default_tag' => '',
			'maps_api_key'         => '',
			'analytics_id'         => '',
			'cloudflare_api_token' => '',
			'cloudflare_zone_id'   => '',

			// Phase 2 — client accounts, tracking, payments.
			'google_client_id'          => '',
			'google_client_secret'      => '',
			'vendor_analytics_enabled'  => 0,
			'whatsapp_prefill_template' => 'Hi {business}, I found your profile on Owambe Connect and would like to enquire about your services for an upcoming event.',
			'stripe_pk'                 => '',
			'stripe_sk'                 => '',
			'stripe_webhook_secret'     => '',
			'billing_enabled'           => 0,

			// Legal — client-facing Terms & Conditions URL used by the client
			// signup/login consent links. Empty falls back to the /terms/ page.
			'client_terms_url'          => '',

			// Website Safety — optional intro/notice shown atop the [oc_safety_info]
			// page. Empty shows just the default safety tips.
			'safety_intro'              => '',
		];
	}

	public static function get_all() {
		$saved = get_option( self::OPTION, [] );
		return wp_parse_args( is_array( $saved ) ? $saved : [], self::defaults() );
	}

	public static function get( $key, $fallback = null ) {
		$all = self::get_all();
		if ( isset( $all[ $key ] ) && '' !== $all[ $key ] ) {
			return $all[ $key ];
		}
		return null !== $fallback ? $fallback : ( self::defaults()[ $key ] ?? '' );
	}

	public function register() {
		add_action( 'admin_menu',          [ $this, 'menu' ] );
		add_action( 'admin_init',          [ $this, 'register_settings' ] );
		add_action( 'wp_head',             [ $this, 'inject_brand_css' ], 5 );
		add_action( 'wp_ajax_oc_save_settings', [ $this, 'ajax_save' ] );
	}

	/**
	 * Per-section AJAX save. Merges the submitted fields over the current
	 * settings, then runs the full sanitizer so untouched sections keep their
	 * stored values. Posts to admin-ajax.php (small payload) instead of the big
	 * options.php form — no page reload, and it dodges hosts/WAFs that choke on
	 * the large single-form submit.
	 */
	public function ajax_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to do this.', 'owambe-connect-core' ) ], 403 );
		}
		check_ajax_referer( 'oc_save_settings', 'nonce' );

		// Raw section fields; each value is run through self::sanitize() below.
		$posted = isset( $_POST['oc_settings'] ) && is_array( $_POST['oc_settings'] )
			? wp_unslash( $_POST['oc_settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: [];

		$merged = array_merge( self::get_all(), $posted );
		update_option( self::OPTION, $this->sanitize( $merged ) );

		wp_send_json_success( [ 'message' => __( 'Saved', 'owambe-connect-core' ) ] );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Owambe Connect Settings', 'owambe-connect-core' ),
			__( 'Settings', 'owambe-connect-core' ),
			'manage_options',
			'oc-settings',
			[ $this, 'render' ],
			60
		);
	}

	public function register_settings() {
		register_setting( 'oc_settings_group', self::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize' ],
			'default'           => self::defaults(),
		] );
	}

	public function sanitize( $input ) {
		$d = self::defaults();
		$out = self::get_all();
		$input = is_array( $input ) ? $input : [];

		$out['tagline']            = isset( $input['tagline'] )            ? sanitize_text_field( $input['tagline'] ) : $d['tagline'];
		$out['from_name']          = isset( $input['from_name'] )          ? sanitize_text_field( $input['from_name'] ) : $d['from_name'];
		$out['from_email']         = isset( $input['from_email'] )         ? sanitize_email( $input['from_email'] ) : '';
		$out['notification_email'] = isset( $input['notification_email'] ) ? sanitize_email( $input['notification_email'] ) : '';
		$out['directory_per_page'] = isset( $input['directory_per_page'] ) ? max( 3, min( 60, (int) $input['directory_per_page'] ) ) : $d['directory_per_page'];
		$out['featured_count']     = isset( $input['featured_count'] )     ? max( 1, min( 24, (int) $input['featured_count'] ) ) : $d['featured_count'];
		$out['logo_max_mb']        = isset( $input['logo_max_mb'] )        ? max( 1, min( 10, (int) $input['logo_max_mb'] ) ) : $d['logo_max_mb'];
		$out['banner_max_mb']      = isset( $input['banner_max_mb'] )      ? max( 1, min( 20, (int) $input['banner_max_mb'] ) ) : $d['banner_max_mb'];
		$out['gallery_max_images'] = isset( $input['gallery_max_images'] ) ? max( 0, min( 24, (int) $input['gallery_max_images'] ) ) : $d['gallery_max_images'];
		$out['gallery_max_mb']     = isset( $input['gallery_max_mb'] )     ? max( 1, min( 10, (int) $input['gallery_max_mb'] ) ) : $d['gallery_max_mb'];
		$out['auto_approve']       = ! empty( $input['auto_approve'] ) ? 1 : 0;
		$out['languages']          = isset( $input['languages'] )          ? sanitize_text_field( $input['languages'] ) : $d['languages'];
		$out['primary_color']      = isset( $input['primary_color'] )      ? $this->sanitize_hex( $input['primary_color'], $d['primary_color'] ) : $d['primary_color'];
		$out['accent_color']       = isset( $input['accent_color'] )       ? $this->sanitize_hex( $input['accent_color'], $d['accent_color'] )  : $d['accent_color'];

		// Integrations.
		$out['recaptcha_site_key']   = isset( $input['recaptcha_site_key'] )   ? sanitize_text_field( $input['recaptcha_site_key'] )   : '';
		$out['recaptcha_secret_key'] = isset( $input['recaptcha_secret_key'] ) ? sanitize_text_field( $input['recaptcha_secret_key'] ) : '';
		$out['recaptcha_threshold']  = isset( $input['recaptcha_threshold'] )  ? (string) max( 0.1, min( 0.9, (float) $input['recaptcha_threshold'] ) ) : '0.5';

		$out['mailchimp_enabled']     = ! empty( $input['mailchimp_enabled'] ) ? 1 : 0;
		$out['mailchimp_api_key']     = isset( $input['mailchimp_api_key'] )     ? sanitize_text_field( $input['mailchimp_api_key'] )     : '';
		$out['mailchimp_audience_id'] = isset( $input['mailchimp_audience_id'] ) ? sanitize_text_field( $input['mailchimp_audience_id'] ) : '';
		$mc_status                    = isset( $input['mailchimp_status'] ) ? sanitize_key( $input['mailchimp_status'] ) : 'subscribed';
		$out['mailchimp_status']      = in_array( $mc_status, [ 'subscribed', 'pending' ], true ) ? $mc_status : 'subscribed';
		$out['mailchimp_default_tag'] = isset( $input['mailchimp_default_tag'] ) ? sanitize_text_field( $input['mailchimp_default_tag'] ) : '';

		$out['maps_api_key']         = isset( $input['maps_api_key'] )         ? sanitize_text_field( $input['maps_api_key'] )         : '';
		$out['analytics_id']         = isset( $input['analytics_id'] )         ? sanitize_text_field( $input['analytics_id'] )         : '';
		$out['cloudflare_api_token'] = isset( $input['cloudflare_api_token'] ) ? sanitize_text_field( $input['cloudflare_api_token'] ) : '';
		$out['cloudflare_zone_id']   = isset( $input['cloudflare_zone_id'] )   ? sanitize_text_field( $input['cloudflare_zone_id'] )   : '';

		// Phase 2 — client accounts, tracking, payments.
		$out['google_client_id']          = isset( $input['google_client_id'] )     ? sanitize_text_field( $input['google_client_id'] )     : '';
		$out['google_client_secret']      = isset( $input['google_client_secret'] ) ? sanitize_text_field( $input['google_client_secret'] ) : '';
		$out['vendor_analytics_enabled']  = ! empty( $input['vendor_analytics_enabled'] ) ? 1 : 0;
		$out['whatsapp_prefill_template'] = isset( $input['whatsapp_prefill_template'] ) ? sanitize_text_field( $input['whatsapp_prefill_template'] ) : $d['whatsapp_prefill_template'];
		$out['stripe_pk']                 = isset( $input['stripe_pk'] )             ? sanitize_text_field( $input['stripe_pk'] )             : '';
		$out['stripe_sk']                 = isset( $input['stripe_sk'] )             ? sanitize_text_field( $input['stripe_sk'] )             : '';
		$out['stripe_webhook_secret']     = isset( $input['stripe_webhook_secret'] ) ? sanitize_text_field( $input['stripe_webhook_secret'] ) : '';
		$out['billing_enabled']           = ! empty( $input['billing_enabled'] ) ? 1 : 0;

		// Legal.
		$out['client_terms_url'] = isset( $input['client_terms_url'] ) ? esc_url_raw( trim( (string) $input['client_terms_url'] ) ) : '';

		// Website safety — allow basic HTML (links/formatting) in the intro notice.
		$out['safety_intro'] = isset( $input['safety_intro'] ) ? wp_kses_post( trim( (string) $input['safety_intro'] ) ) : '';

		return $out;
	}

	private function sanitize_hex( $value, $fallback ) {
		$value = trim( (string) $value );
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? $value : $fallback;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = self::get_all();
		?>
		<div class="wrap oc-settings">
			<h1 style="margin-bottom:6px"><?php esc_html_e( 'Owambe Connect Settings', 'owambe-connect-core' ); ?></h1>
			<p style="margin:0 0 18px;color:#555"><?php esc_html_e( 'Configure your marketplace. Changes apply immediately.', 'owambe-connect-core' ); ?></p>

			<form method="post" action="options.php" class="oc-settings-form">
				<?php settings_fields( 'oc_settings_group' ); ?>

				<h2 class="oc-section-h"><?php esc_html_e( 'General', 'owambe-connect-core' ); ?></h2>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-tagline"><?php esc_html_e( 'Tagline', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-tagline" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[tagline]" value="<?php echo esc_attr( $s['tagline'] ); ?>"/>
							<p class="description"><?php esc_html_e( 'Shown on the hero and in emails.', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-approve new vendors', 'owambe-connect-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[auto_approve]" value="1" <?php checked( $s['auto_approve'], 1 ); ?>/> <?php esc_html_e( 'Skip manual review and publish vendors as soon as they apply', 'owambe-connect-core' ); ?></label>
							<p class="description"><?php esc_html_e( 'Off by default — recommended to keep on review during early launch.', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Vendor-facing analytics', 'owambe-connect-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[vendor_analytics_enabled]" value="1" <?php checked( $s['vendor_analytics_enabled'], 1 ); ?>/> <?php esc_html_e( 'Show each vendor an Analytics tab (their profile views and contact clicks) in their dashboard', 'owambe-connect-core' ); ?></label>
							<p class="description"><?php esc_html_e( 'Keep OFF until vendors are getting meaningful traffic — tracking still records either way; only the vendor-facing view is hidden.', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-wa-tpl"><?php esc_html_e( 'WhatsApp enquiry message', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-wa-tpl" type="text" class="large-text" name="<?php echo esc_attr( self::OPTION ); ?>[whatsapp_prefill_template]" value="<?php echo esc_attr( $s['whatsapp_prefill_template'] ); ?>"/>
							<p class="description"><?php esc_html_e( 'Pre-filled when a visitor taps a vendor\'s WhatsApp button — this is how vendors know a lead came from Owambe Connect. {business} is replaced with the vendor\'s business name.', 'owambe-connect-core' ); ?></p></td>
					</tr>
				</tbody></table>

				<h2 class="oc-section-h"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></h2>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-fn"><?php esc_html_e( 'From name', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-fn" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-fe"><?php esc_html_e( 'From email', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-fe" type="email" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"/>
							<p class="description"><?php esc_html_e( 'Leave blank to use the site admin email.', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-ne"><?php esc_html_e( 'Notification email', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-ne" type="email" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[notification_email]" value="<?php echo esc_attr( $s['notification_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"/>
							<p class="description"><?php esc_html_e( 'Receives new applications and contact-form messages.', 'owambe-connect-core' ); ?></p></td>
					</tr>
				</tbody></table>

				<h2 class="oc-section-h"><?php esc_html_e( 'Directory', 'owambe-connect-core' ); ?></h2>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-pp"><?php esc_html_e( 'Vendors per page', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-pp" type="number" min="3" max="60" name="<?php echo esc_attr( self::OPTION ); ?>[directory_per_page]" value="<?php echo esc_attr( $s['directory_per_page'] ); ?>"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-fc"><?php esc_html_e( 'Featured vendors on home', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-fc" type="number" min="1" max="24" name="<?php echo esc_attr( self::OPTION ); ?>[featured_count]" value="<?php echo esc_attr( $s['featured_count'] ); ?>"/>
							<p class="description"><?php esc_html_e( 'Default number of vendors shown by [oc_featured_vendors] when no count attribute is given.', 'owambe-connect-core' ); ?></p></td>
					</tr>
				</tbody></table>

				<h2 class="oc-section-h"><?php esc_html_e( 'Uploads', 'owambe-connect-core' ); ?></h2>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-lm"><?php esc_html_e( 'Logo max size (MB)', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-lm" type="number" min="1" max="10" name="<?php echo esc_attr( self::OPTION ); ?>[logo_max_mb]" value="<?php echo esc_attr( $s['logo_max_mb'] ); ?>"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-bm"><?php esc_html_e( 'Banner max size (MB)', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-bm" type="number" min="1" max="20" name="<?php echo esc_attr( self::OPTION ); ?>[banner_max_mb]" value="<?php echo esc_attr( $s['banner_max_mb'] ); ?>"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-gn"><?php esc_html_e( 'Gallery — max images', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-gn" type="number" min="0" max="24" name="<?php echo esc_attr( self::OPTION ); ?>[gallery_max_images]" value="<?php echo esc_attr( $s['gallery_max_images'] ); ?>" style="width:100px"/>
							<p class="description"><?php esc_html_e( 'How many portfolio images each vendor can upload. Set to 0 to disable galleries.', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-gimg"><?php esc_html_e( 'Gallery — max size per image (MB)', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-gimg" type="number" min="1" max="10" name="<?php echo esc_attr( self::OPTION ); ?>[gallery_max_mb]" value="<?php echo esc_attr( $s['gallery_max_mb'] ); ?>" style="width:100px"/></td>
					</tr>
				</tbody></table>

				<h2 class="oc-section-h"><?php esc_html_e( 'Languages', 'owambe-connect-core' ); ?></h2>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-lang"><?php esc_html_e( 'Supported languages', 'owambe-connect-core' ); ?></label></th>
						<td><textarea id="oc-lang" rows="3" class="large-text" name="<?php echo esc_attr( self::OPTION ); ?>[languages]"><?php echo esc_textarea( $s['languages'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Comma-separated. Appears as checkboxes on the application & dashboard forms.', 'owambe-connect-core' ); ?></p></td>
					</tr>
				</tbody></table>

				<h2 class="oc-section-h"><?php esc_html_e( 'Integrations', 'owambe-connect-core' ); ?></h2>
				<p class="oc-section-desc"><?php esc_html_e( 'Paste keys from third-party services here — no code edits needed. Leave any section blank to disable that integration.', 'owambe-connect-core' ); ?></p>

				<h3 class="oc-sub-h">
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e( 'Google reCAPTCHA v3', 'owambe-connect-core' ); ?>
					<small><?php esc_html_e( 'Spam protection on registration & contact forms', 'owambe-connect-core' ); ?></small>
				</h3>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-rc-site"><?php esc_html_e( 'Site key', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-rc-site" type="text" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_site_key]" value="<?php echo esc_attr( $s['recaptcha_site_key'] ); ?>" placeholder="6Lc..."/>
							<p class="description"><?php printf( esc_html__( 'Get keys at %s — choose reCAPTCHA v3.', 'owambe-connect-core' ), '<a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">google.com/recaptcha/admin</a>' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-rc-sec"><?php esc_html_e( 'Secret key', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-rc-sec" type="password" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_secret_key]" value="<?php echo esc_attr( $s['recaptcha_secret_key'] ); ?>" autocomplete="new-password"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-rc-t"><?php esc_html_e( 'Pass threshold', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-rc-t" type="number" min="0.1" max="0.9" step="0.1" name="<?php echo esc_attr( self::OPTION ); ?>[recaptcha_threshold]" value="<?php echo esc_attr( $s['recaptcha_threshold'] ); ?>" style="width:80px"/>
							<p class="description"><?php esc_html_e( '0.1 (lenient) – 0.9 (strict). 0.5 is a good default.', 'owambe-connect-core' ); ?></p></td>
					</tr>
				</tbody></table>

				<h3 class="oc-sub-h">
					<span class="dashicons dashicons-email"></span>
					<?php esc_html_e( 'Mailchimp — marketing list sync', 'owambe-connect-core' ); ?>
					<small><?php esc_html_e( 'Auto-pushes approved vendors to a Mailchimp audience', 'owambe-connect-core' ); ?></small>
				</h3>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable sync', 'owambe-connect-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[mailchimp_enabled]" value="1" <?php checked( $s['mailchimp_enabled'], 1 ); ?>/> <?php esc_html_e( 'When admin approves a vendor, push their email + business name + category to Mailchimp.', 'owambe-connect-core' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-mc-key"><?php esc_html_e( 'API key', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-mc-key" type="password" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[mailchimp_api_key]" value="<?php echo esc_attr( $s['mailchimp_api_key'] ); ?>" placeholder="abcdef1234567890-us17" autocomplete="new-password"/>
							<p class="description"><?php esc_html_e( 'Mailchimp → Profile → Extras → API keys → Create a key. The "-usXX" suffix is required (it identifies your data center).', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-mc-aud"><?php esc_html_e( 'Audience ID', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-mc-aud" type="text" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[mailchimp_audience_id]" value="<?php echo esc_attr( $s['mailchimp_audience_id'] ); ?>" placeholder="a1b2c3d4e5"/>
							<p class="description"><?php esc_html_e( 'Mailchimp → Audience → Settings → Audience name and defaults → "Audience ID" (10-char string, NOT the audience name).', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-mc-status"><?php esc_html_e( 'Default status', 'owambe-connect-core' ); ?></label></th>
						<td><select id="oc-mc-status" name="<?php echo esc_attr( self::OPTION ); ?>[mailchimp_status]">
								<option value="subscribed" <?php selected( $s['mailchimp_status'], 'subscribed' ); ?>><?php esc_html_e( 'Subscribed (no opt-in email)', 'owambe-connect-core' ); ?></option>
								<option value="pending"    <?php selected( $s['mailchimp_status'], 'pending' );    ?>><?php esc_html_e( 'Pending (Mailchimp sends double opt-in)', 'owambe-connect-core' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'For approved vendors who have already accepted terms during signup, "Subscribed" is fine. Pick "Pending" if your jurisdiction requires explicit re-opt-in.', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-mc-tag"><?php esc_html_e( 'Default tag (optional)', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-mc-tag" type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[mailchimp_default_tag]" value="<?php echo esc_attr( $s['mailchimp_default_tag'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. owambe-vendor', 'owambe-connect-core' ); ?>"/>
							<p class="description"><?php esc_html_e( 'Added on top of "Vendor" and the category tag. Useful for filtering campaigns to people who came through this site.', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<?php if ( $s['mailchimp_enabled'] && $s['mailchimp_api_key'] && $s['mailchimp_audience_id'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sync existing vendors', 'owambe-connect-core' ); ?></th>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Push every existing approved vendor to Mailchimp now? Safe to repeat — existing subscribers will be updated, not duplicated.', 'owambe-connect-core' ) ); ?>');">
								<input type="hidden" name="action" value="oc_mailchimp_backfill" />
								<?php wp_nonce_field( 'oc_mailchimp_backfill', 'oc_mc_backfill_nonce' ); ?>
								<button type="submit" class="button button-secondary"><?php esc_html_e( 'Backfill all approved vendors now', 'owambe-connect-core' ); ?></button>
							</form>
							<p class="description"><?php esc_html_e( 'Idempotent — Mailchimp updates the subscriber if their email already exists. Use after enabling sync for the first time on a site that already has approved vendors.', 'owambe-connect-core' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
				</tbody></table>

				<h3 class="oc-sub-h">
					<span class="dashicons dashicons-google"></span>
					<?php esc_html_e( 'Google Sign-In — client accounts', 'owambe-connect-core' ); ?>
					<small><?php esc_html_e( 'One-tap sign-in for event hosts (and vendors)', 'owambe-connect-core' ); ?></small>
				</h3>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-gcid"><?php esc_html_e( 'OAuth Client ID', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-gcid" type="text" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[google_client_id]" value="<?php echo esc_attr( $s['google_client_id'] ); ?>" placeholder="1234567890-abc.apps.googleusercontent.com"/>
							<p class="description"><?php printf( esc_html__( 'Google Cloud Console → APIs & Services → Credentials → OAuth client ID (Web application). Add %s to "Authorised JavaScript origins".', 'owambe-connect-core' ), '<code>' . esc_html( home_url() ) . '</code>' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-gcsec"><?php esc_html_e( 'OAuth Client secret', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-gcsec" type="password" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[google_client_secret]" value="<?php echo esc_attr( $s['google_client_secret'] ); ?>" autocomplete="new-password"/>
							<p class="description"><?php esc_html_e( 'Not needed for the sign-in button itself — stored for future server-side OAuth flows.', 'owambe-connect-core' ); ?></p></td>
					</tr>
						<tr>
							<th scope="row"><label for="oc-cterms"><?php esc_html_e( 'Client Terms &amp; Conditions URL', 'owambe-connect-core' ); ?></label></th>
							<td><input id="oc-cterms" type="url" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[client_terms_url]" value="<?php echo esc_attr( $s['client_terms_url'] ); ?>" placeholder="<?php echo esc_attr( function_exists( 'oc_page_url' ) ? oc_page_url( 'terms' ) : home_url( '/terms/' ) ); ?>"/>
								<p class="description"><?php esc_html_e( 'The client signup/login "I accept the Terms & Conditions" links point here. Leave blank to use the built-in /terms/ page.', 'owambe-connect-core' ); ?></p></td>
						</tr>
				</tbody></table>

								<h3 class="oc-sub-h">
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e( 'Website safety', 'owambe-connect-core' ); ?>
					<small><?php esc_html_e( 'Intro shown on the [oc_safety_info] page', 'owambe-connect-core' ); ?></small>
				</h3>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-safety-intro"><?php esc_html_e( 'Safety notice / intro', 'owambe-connect-core' ); ?></label></th>
						<td><textarea id="oc-safety-intro" class="large-text" rows="4" name="<?php echo esc_attr( self::OPTION ); ?>[safety_intro]"><?php echo esc_textarea( $s['safety_intro'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional intro shown at the top of the Website Safety Information page (basic HTML allowed). Leave blank to show just the default safety tips.', 'owambe-connect-core' ); ?></p></td>
					</tr>
				</tbody></table>

				<h3 class="oc-sub-h">
					<span class="dashicons dashicons-money-alt"></span>
					<?php esc_html_e( 'Stripe — payments', 'owambe-connect-core' ); ?>
					<small><?php esc_html_e( 'Subscriptions & featured listings (built, switched off)', 'owambe-connect-core' ); ?></small>
				</h3>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-st-pk"><?php esc_html_e( 'Publishable key', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-st-pk" type="text" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[stripe_pk]" value="<?php echo esc_attr( $s['stripe_pk'] ); ?>" placeholder="pk_test_..."/>
							<p class="description"><?php esc_html_e( 'Use TEST keys while building — swap to live keys only at go-live.', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-st-sk"><?php esc_html_e( 'Secret key', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-st-sk" type="password" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[stripe_sk]" value="<?php echo esc_attr( $s['stripe_sk'] ); ?>" placeholder="sk_test_..." autocomplete="new-password"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-st-wh"><?php esc_html_e( 'Webhook signing secret', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-st-wh" type="password" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[stripe_webhook_secret]" value="<?php echo esc_attr( $s['stripe_webhook_secret'] ); ?>" placeholder="whsec_..." autocomplete="new-password"/>
							<p class="description"><?php printf( esc_html__( 'Stripe Dashboard → Developers → Webhooks → Add endpoint: %s', 'owambe-connect-core' ), '<code>' . esc_html( rest_url( 'oc/v1/stripe-webhook' ) ) . '</code>' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Billing master switch', 'owambe-connect-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[billing_enabled]" value="1" <?php checked( $s['billing_enabled'], 1 ); ?>/> <strong><?php esc_html_e( 'Enable billing (charges vendors when subscriptions launch)', 'owambe-connect-core' ); ?></strong></label>
							<p class="description"><?php esc_html_e( 'Keep OFF — no vendor is charged and no billing UI shows anywhere while this is off. Free-period clocks run regardless.', 'owambe-connect-core' ); ?></p></td>
					</tr>
				</tbody></table>

				<h3 class="oc-sub-h">
					<span class="dashicons dashicons-location"></span>
					<?php esc_html_e( 'Google Maps', 'owambe-connect-core' ); ?>
					<small><?php esc_html_e( 'Vendor map display (Phase 2)', 'owambe-connect-core' ); ?></small>
				</h3>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-gm"><?php esc_html_e( 'Maps API key', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-gm" type="text" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[maps_api_key]" value="<?php echo esc_attr( $s['maps_api_key'] ); ?>" placeholder="AIza..."/>
							<p class="description"><?php printf( esc_html__( 'Restrict the key by HTTP referrer to %s in Google Cloud Console.', 'owambe-connect-core' ), '<code>' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '/*</code>' ); ?></p></td>
					</tr>
				</tbody></table>

				<h3 class="oc-sub-h">
					<span class="dashicons dashicons-chart-bar"></span>
					<?php esc_html_e( 'Google Analytics 4', 'owambe-connect-core' ); ?>
					<small><?php esc_html_e( 'Public-site visitor tracking', 'owambe-connect-core' ); ?></small>
				</h3>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-ga"><?php esc_html_e( 'Measurement ID', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-ga" type="text" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[analytics_id]" value="<?php echo esc_attr( $s['analytics_id'] ); ?>" placeholder="G-XXXXXXX"/></td>
					</tr>
				</tbody></table>

				<h3 class="oc-sub-h">
					<span class="dashicons dashicons-cloud"></span>
					<?php esc_html_e( 'Cloudflare', 'owambe-connect-core' ); ?>
					<small><?php esc_html_e( 'Cache purge on vendor approval (optional)', 'owambe-connect-core' ); ?></small>
				</h3>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-cf-t"><?php esc_html_e( 'API token', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-cf-t" type="password" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[cloudflare_api_token]" value="<?php echo esc_attr( $s['cloudflare_api_token'] ); ?>" autocomplete="new-password"/>
							<p class="description"><?php esc_html_e( 'Create a scoped token with Zone → Cache Purge permission only.', 'owambe-connect-core' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-cf-z"><?php esc_html_e( 'Zone ID', 'owambe-connect-core' ); ?></label></th>
						<td><input id="oc-cf-z" type="text" class="regular-text code" name="<?php echo esc_attr( self::OPTION ); ?>[cloudflare_zone_id]" value="<?php echo esc_attr( $s['cloudflare_zone_id'] ); ?>"/></td>
					</tr>
				</tbody></table>

				<h2 class="oc-section-h"><?php esc_html_e( 'Branding', 'owambe-connect-core' ); ?></h2>
				<table class="form-table"><tbody>
					<tr>
						<th scope="row"><label for="oc-pc"><?php esc_html_e( 'Primary colour', 'owambe-connect-core' ); ?></label></th>
						<td>
							<input id="oc-pc" type="color" name="<?php echo esc_attr( self::OPTION ); ?>[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" style="width:60px;height:40px;vertical-align:middle"/>
							<input type="text" class="regular-text" value="<?php echo esc_attr( $s['primary_color'] ); ?>" oninput="document.getElementById('oc-pc').value=this.value" style="margin-left:8px;width:130px" readonly tabindex="-1" aria-hidden="true"/>
							<p class="description"><?php esc_html_e( 'Used for headings, primary buttons, links. Default: #6E0F2C', 'owambe-connect-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oc-ac"><?php esc_html_e( 'Accent colour', 'owambe-connect-core' ); ?></label></th>
						<td>
							<input id="oc-ac" type="color" name="<?php echo esc_attr( self::OPTION ); ?>[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>" style="width:60px;height:40px;vertical-align:middle"/>
							<input type="text" class="regular-text" value="<?php echo esc_attr( $s['accent_color'] ); ?>" style="margin-left:8px;width:130px" readonly tabindex="-1" aria-hidden="true"/>
							<p class="description"><?php esc_html_e( 'Used for dividers, eyebrow text, focus rings. Default: #C9A961', 'owambe-connect-core' ); ?></p>
						</td>
					</tr>
				</tbody></table>

				<?php submit_button(); ?>
			</form>

			<?php if ( ! empty( $s['stripe_sk'] ) ) : ?>
				<?php // Deliberately OUTSIDE the options.php form — nesting forms is invalid HTML. ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:-8px 0 20px;">
					<input type="hidden" name="action" value="oc_stripe_test" />
					<?php wp_nonce_field( 'oc_stripe_test' ); ?>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Test Stripe connection', 'owambe-connect-core' ); ?></button>
					<?php if ( isset( $_GET['oc_stripe'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
						<?php if ( 'ok' === $_GET['oc_stripe'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
							<span style="color:#1a7a2e;margin-left:10px;font-weight:600"><?php esc_html_e( '✓ Stripe connection working', 'owambe-connect-core' ); ?></span>
						<?php else : ?>
							<span style="color:#b32d2e;margin-left:10px;font-weight:600"><?php echo esc_html( sprintf( __( '✗ Stripe connection failed: %s', 'owambe-connect-core' ), sanitize_text_field( wp_unslash( $_GET['oc_stripe_msg'] ?? '' ) ) ) ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</form>
			<?php endif; ?>

			<hr style="margin:30px 0">
			<div class="oc-side-cards">
				<div class="oc-side-card">
					<h3><?php esc_html_e( 'Quick links', 'owambe-connect-core' ); ?></h3>
					<ul>
						<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . OC_CPT ) ); ?>"><?php esc_html_e( 'All Vendors', 'owambe-connect-core' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . OC_TAX . '&post_type=' . OC_CPT ) ); ?>"><?php esc_html_e( 'Categories', 'owambe-connect-core' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=oc-add-vendor' ) ); ?>"><?php esc_html_e( 'Add Vendor (quick form)', 'owambe-connect-core' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=oc-developer-guide' ) ); ?>"><?php esc_html_e( 'Developer Guide', 'owambe-connect-core' ); ?></a></li>
					</ul>
				</div>
			</div>
		</div>
		<style>
			.oc-section-h { margin-top:30px; padding-bottom:8px; border-bottom:2px solid #C9A961; color:#6E0F2C; font-family:Georgia, serif; }
			.oc-section-desc { color:#6B6361; margin:6px 0 0; font-size:13px; max-width:780px; }
			.oc-sub-h { margin:24px 0 4px; font-family:Georgia, serif; color:#1F1B1A; font-size:15px; display:flex; align-items:center; gap:8px; padding-bottom:6px; border-bottom:1px dashed #E4DDD2; }
			.oc-sub-h .dashicons { color:#A8893D; }
			.oc-sub-h small { font-weight:400; color:#6B6361; font-size:12px; margin-left:auto; }
			.oc-side-cards { display:grid; grid-template-columns:1fr; gap:16px; max-width:600px; }
			.oc-side-card { background:#fff; border:1px solid #e4ddd2; padding:18px 22px; border-radius:8px; }
			.oc-side-card h3 { margin-top:0; color:#6E0F2C; }
			.oc-side-card ul { margin:0; padding-left:18px; }
			.oc-side-card li { margin-bottom:6px; }
			.oc-sec-save { display:flex; align-items:center; gap:12px; margin:2px 0 20px; padding-top:4px; }
			.oc-sec-status { font-weight:600; font-size:13px; }
			.oc-sec-status.is-saving { color:#6B6361; }
			.oc-sec-status.is-ok { color:#1a7a2e; }
			.oc-sec-status.is-err { color:#b32d2e; }
			.oc-settings-form .form-table { margin-bottom:2px; }
		</style>
		<script>
		( function () {
			var form = document.querySelector( '.oc-settings-form' );
			if ( ! form ) { return; }

			var ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'oc_save_settings' ) ); ?>;
			var T = {
				save:   <?php echo wp_json_encode( __( 'Save', 'owambe-connect-core' ) ); ?>,
				saveAll:<?php echo wp_json_encode( __( 'Save all settings', 'owambe-connect-core' ) ); ?>,
				saving: <?php echo wp_json_encode( __( 'Saving…', 'owambe-connect-core' ) ); ?>,
				ok:     <?php echo wp_json_encode( __( 'Saved ✓', 'owambe-connect-core' ) ); ?>,
				err:    <?php echo wp_json_encode( __( 'Save failed — try again', 'owambe-connect-core' ) ); ?>
			};

			// Serialise only oc_settings[*] fields within a scope. Checkboxes send
			// an explicit 1/0 so a section save can also *uncheck* them.
			function collect( scope ) {
				var data = 'action=oc_save_settings&nonce=' + encodeURIComponent( nonce );
				var els = scope.querySelectorAll( 'input[name^="oc_settings"], select[name^="oc_settings"], textarea[name^="oc_settings"]' );
				els.forEach( function ( el ) {
					var val;
					if ( el.type === 'checkbox' ) { val = el.checked ? ( el.value || '1' ) : '0'; }
					else if ( el.type === 'radio' ) { if ( ! el.checked ) { return; } val = el.value; }
					else { val = el.value; }
					data += '&' + encodeURIComponent( el.name ) + '=' + encodeURIComponent( val );
				} );
				return data;
			}

			function send( scope, status, btn ) {
				status.textContent = T.saving;
				status.className = 'oc-sec-status is-saving';
				btn.disabled = true;
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', ajaxurl, true );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
				xhr.onreadystatechange = function () {
					if ( xhr.readyState !== 4 ) { return; }
					btn.disabled = false;
					var ok = false;
					try { ok = xhr.status === 200 && JSON.parse( xhr.responseText ).success; } catch ( e ) {}
					status.textContent = ok ? T.ok : T.err;
					status.className = 'oc-sec-status ' + ( ok ? 'is-ok' : 'is-err' );
					if ( ok ) { setTimeout( function () { status.textContent = ''; status.className = 'oc-sec-status'; }, 2500 ); }
				};
				xhr.send( collect( scope ) );
			}

			// One Save button per section (after each form-table).
			form.querySelectorAll( '.form-table' ).forEach( function ( table ) {
				var row = document.createElement( 'p' );
				row.className = 'oc-sec-save';
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'button button-primary';
				btn.textContent = T.save;
				var status = document.createElement( 'span' );
				status.className = 'oc-sec-status';
				row.appendChild( btn );
				row.appendChild( status );
				table.parentNode.insertBefore( row, table.nextSibling );
				btn.addEventListener( 'click', function () { send( table, status, btn ); } );
			} );

			// Repurpose the global submit into an AJAX "save all" — no reload.
			var submitP = form.querySelector( 'p.submit' );
			if ( submitP ) {
				var submitBtn = submitP.querySelector( 'input[type=submit], button[type=submit]' );
				if ( submitBtn && submitBtn.tagName === 'INPUT' ) { submitBtn.value = T.saveAll; }
				var gStatus = document.createElement( 'span' );
				gStatus.className = 'oc-sec-status';
				gStatus.style.marginLeft = '10px';
				submitP.appendChild( gStatus );
			}
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var p = form.querySelector( 'p.submit' );
				var btn = p ? p.querySelector( 'input[type=submit], button[type=submit]' ) : null;
				var status = p ? p.querySelector( '.oc-sec-status' ) : null;
				if ( btn && status ) { send( form, status, btn ); }
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Inject brand colors as CSS custom properties so the front-end picks up
	 * Settings → Branding values without recompiling CSS.
	 */
	public function inject_brand_css() {
		$primary = self::get( 'primary_color' );
		$accent  = self::get( 'accent_color' );
		if ( '#6E0F2C' === $primary && '#C9A961' === $accent ) {
			return; // Defaults — no override needed.
		}
		$dark = $this->shade( $primary, -20 );
		printf(
			'<style id="oc-brand-vars">:root{--oc-burgundy:%s;--oc-burgundy-dark:%s;--oc-gold:%s;--oc-gold-dark:%s}</style>' . "\n",
			esc_attr( $primary ),
			esc_attr( $dark ),
			esc_attr( $accent ),
			esc_attr( $this->shade( $accent, -20 ) )
		);
	}

	private function shade( $hex, $percent ) {
		$hex = ltrim( $hex, '#' );
		if ( 6 !== strlen( $hex ) ) return '#' . $hex;
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$adj = static function ( $c ) use ( $percent ) {
			$c = (int) round( $c * ( 100 + $percent ) / 100 );
			return max( 0, min( 255, $c ) );
		};
		return sprintf( '#%02x%02x%02x', $adj( $r ), $adj( $g ), $adj( $b ) );
	}
}
