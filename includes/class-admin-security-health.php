<?php
/**
 * Owambe Connect — Security Health admin page.
 *
 * A single-screen overview of every plugin / wp-config / external-service
 * protection. Each row is a pass/warn/fail with a short remediation hint.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Security_Health {

	const PAGE = 'oc-security-health';

	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ], 11 );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Security Health', 'owambe-connect-core' ),
			__( 'Security Health', 'owambe-connect-core' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render' ],
			65
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$checks = $this->run_checks();

		$counts = [ 'pass' => 0, 'warn' => 0, 'fail' => 0 ];
		foreach ( $checks as $c ) $counts[ $c['status'] ] = ( $counts[ $c['status'] ] ?? 0 ) + 1;
		$score = max( 0, count( $checks ) - $counts['fail'] - ( $counts['warn'] * 0.5 ) );
		$pct   = count( $checks ) ? round( ( $score / count( $checks ) ) * 100 ) : 0;
		?>
		<div class="wrap oc-sh">
			<h1><?php esc_html_e( 'Security Health', 'owambe-connect-core' ); ?></h1>
			<p style="color:#6B6361;margin:0 0 18px"><?php esc_html_e( 'Live status of every plugin-level, WordPress-level, and integration protection. Wordfence + Cloudflare cover network/WAF; this checks the application-level surface.', 'owambe-connect-core' ); ?></p>

			<div class="oc-sh-summary">
				<div class="oc-sh-score" data-pct="<?php echo (int) $pct; ?>">
					<svg viewBox="0 0 36 36"><path stroke="#EFEAE2" stroke-width="3" fill="none" d="M18 2 a 16 16 0 0 1 0 32 a 16 16 0 0 1 0 -32"/><path stroke="<?php echo $pct >= 85 ? '#2E7D5B' : ( $pct >= 60 ? '#B8860B' : '#B0354F' ); ?>" stroke-width="3" fill="none" stroke-dasharray="<?php echo (int) $pct; ?>, 100" d="M18 2 a 16 16 0 0 1 0 32 a 16 16 0 0 1 0 -32"/></svg>
					<strong><?php echo (int) $pct; ?>%</strong>
				</div>
				<div class="oc-sh-tally">
					<div><span class="oc-sh-dot oc-sh-dot--pass"></span> <strong><?php echo (int) $counts['pass']; ?></strong> <?php esc_html_e( 'OK', 'owambe-connect-core' ); ?></div>
					<div><span class="oc-sh-dot oc-sh-dot--warn"></span> <strong><?php echo (int) $counts['warn']; ?></strong> <?php esc_html_e( 'Warning', 'owambe-connect-core' ); ?></div>
					<div><span class="oc-sh-dot oc-sh-dot--fail"></span> <strong><?php echo (int) $counts['fail']; ?></strong> <?php esc_html_e( 'Action needed', 'owambe-connect-core' ); ?></div>
				</div>
			</div>

			<?php
			$groups = [];
			foreach ( $checks as $c ) $groups[ $c['group'] ][] = $c;
			foreach ( $groups as $group => $items ) :
				?>
				<div class="oc-sh-card">
					<h2><?php echo esc_html( $group ); ?></h2>
					<?php foreach ( $items as $c ) : ?>
						<div class="oc-sh-row oc-sh-row--<?php echo esc_attr( $c['status'] ); ?>">
							<span class="oc-sh-pill oc-sh-pill--<?php echo esc_attr( $c['status'] ); ?>"><?php
								echo esc_html( $c['status'] === 'pass' ? __( 'OK', 'owambe-connect-core' ) : ( $c['status'] === 'warn' ? __( 'Warn', 'owambe-connect-core' ) : __( 'Fix', 'owambe-connect-core' ) ) );
							?></span>
							<div>
								<strong><?php echo esc_html( $c['label'] ); ?></strong>
								<p><?php echo wp_kses_post( $c['detail'] ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<style>
			.oc-sh h1 { color:#1F1B1A; font-family:Georgia, serif; }
			.oc-sh-summary { display:flex; align-items:center; gap:30px; background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:18px 22px; margin:0 0 18px; }
			.oc-sh-score { position:relative; width:90px; height:90px; }
			.oc-sh-score svg { width:90px; height:90px; transform:rotate(-90deg); }
			.oc-sh-score strong { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-family:Georgia, serif; font-size:1.5rem; color:#1F1B1A; }
			.oc-sh-tally { display:flex; gap:24px; font-size:13px; color:#6B6361; }
			.oc-sh-tally strong { color:#1F1B1A; font-size:1.1rem; }
			.oc-sh-dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:5px; vertical-align:middle; }
			.oc-sh-dot--pass { background:#2E7D5B; }
			.oc-sh-dot--warn { background:#B8860B; }
			.oc-sh-dot--fail { background:#B0354F; }

			.oc-sh-card { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:18px 22px; margin:0 0 14px; }
			.oc-sh-card h2 { font-family:Georgia, serif; color:#6E0F2C; font-size:1.05rem; margin:0 0 12px; padding-bottom:8px; border-bottom:2px solid #C9A961; }
			.oc-sh-row { display:flex; align-items:flex-start; gap:14px; padding:10px 0; border-bottom:1px solid #F4EFE6; }
			.oc-sh-row:last-child { border-bottom:0; }
			.oc-sh-row strong { color:#1F1B1A; }
			.oc-sh-row p { margin:3px 0 0; color:#6B6361; font-size:13px; }
			.oc-sh-row p code { background:#FAF7F2; padding:1px 6px; border-radius:3px; }

			.oc-sh-pill { display:inline-block; min-width:46px; text-align:center; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
			.oc-sh-pill--pass { background:#E9F5EF; color:#1F4D3A; }
			.oc-sh-pill--warn { background:#FAF1DE; color:#4A3700; }
			.oc-sh-pill--fail { background:#F9E5EA; color:#4A0A1E; }
		</style>
		<?php
	}

	private function run_checks() {
		$checks = [];

		$checks[] = $this->check( 'WordPress hardening', 'SSL active', is_ssl(), 'pass', __( 'Site is being served over HTTPS.', 'owambe-connect-core' ), __( 'Configure SSL on Hostinger / Cloudflare and force HTTPS.', 'owambe-connect-core' ) );
		$checks[] = $this->check( 'WordPress hardening', 'File editor disabled (DISALLOW_FILE_EDIT)', defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT, 'fail', __( 'Plugin/Theme file editor is disabled in wp-admin.', 'owambe-connect-core' ), __( 'Add <code>define(\'DISALLOW_FILE_EDIT\', true);</code> to wp-config.php.', 'owambe-connect-core' ) );
		$checks[] = $this->check( 'WordPress hardening', 'Force SSL admin (FORCE_SSL_ADMIN)', defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN, 'warn', __( 'wp-admin is locked to HTTPS.', 'owambe-connect-core' ), __( 'Add <code>define(\'FORCE_SSL_ADMIN\', true);</code> to wp-config.php.', 'owambe-connect-core' ) );
		$checks[] = $this->check( 'WordPress hardening', 'Auto-updates configured', defined( 'WP_AUTO_UPDATE_CORE' ) || get_site_option( 'auto_update_core_minor' ) === 'enabled', 'warn', __( 'WordPress core is auto-updating (at least minor).', 'owambe-connect-core' ), __( 'Add <code>define(\'WP_AUTO_UPDATE_CORE\', \'minor\');</code> to wp-config.php.', 'owambe-connect-core' ) );
		$checks[] = $this->check( 'WordPress hardening', 'Default admin username avoided', ! username_exists( 'admin' ), 'fail', __( 'No user named "admin" exists.', 'owambe-connect-core' ), __( 'Rename the user "admin" or delete it — bots target it directly.', 'owambe-connect-core' ) );
		$checks[] = $this->check( 'WordPress hardening', 'Anonymous registration off', ! get_option( 'users_can_register' ) || in_array( get_option( 'default_role' ), [ OC_ROLE, OC_CLIENT_ROLE ], true ), 'warn', __( 'Public WordPress registration is closed (or restricted to a plugin role).', 'owambe-connect-core' ), __( 'Settings → General → uncheck "Anyone can register".', 'owambe-connect-core' ) );

		$checks[] = $this->check( 'Plugin protections', 'XML-RPC disabled', ! apply_filters( 'xmlrpc_enabled', true ), 'pass', __( 'XML-RPC endpoint is disabled.', 'owambe-connect-core' ), __( 'Filter <code>xmlrpc_enabled</code> to false in OC_Security or via mu-plugin.', 'owambe-connect-core' ) );
		$checks[] = $this->check( 'Plugin protections', 'Author archive blocked', has_action( 'template_redirect' ), 'pass', __( '?author=N enumeration is redirected.', 'owambe-connect-core' ), '' );
		$checks[] = $this->check( 'Plugin protections', 'REST users endpoint locked', class_exists( 'OC_Security' ), 'pass', __( '/wp-json/wp/v2/users requires authentication.', 'owambe-connect-core' ), '' );
		$checks[] = $this->check( 'Plugin protections', 'Custom action throttling', class_exists( 'OC_Security' ), 'pass', __( 'Registration / login / contact endpoints are rate-limited per IP.', 'owambe-connect-core' ), '' );
		$checks[] = $this->check( 'Plugin protections', 'Image upload validated', function_exists( 'oc_handle_image_upload' ), 'pass', __( 'Vendor uploads validated by real MIME inspection.', 'owambe-connect-core' ), '' );
		$checks[] = $this->check( 'Plugin protections', 'Tracking endpoint throttled', class_exists( 'OC_Tracking' ), 'pass', __( 'Public view/click tracking endpoint is IP rate-limited with bot filtering.', 'owambe-connect-core' ), '' );
		$checks[] = $this->check( 'Plugin protections', 'Google sign-in verified server-side', class_exists( 'OC_Google_Auth' ), 'pass', __( 'Google ID tokens are verified against Google before any account is created (fails closed).', 'owambe-connect-core' ), '' );

		$stripe_wh = class_exists( 'OC_Stripe' ) && '' !== (string) oc_get_setting( 'stripe_webhook_secret', '' );
		$checks[]  = $this->check( 'Integrations', 'Stripe webhook signature', $stripe_wh, 'warn', __( 'Stripe webhook events are HMAC signature-verified.', 'owambe-connect-core' ), sprintf( __( 'Add the webhook signing secret at <a href="%s">Settings → Stripe</a> once the endpoint is registered in the Stripe dashboard.', 'owambe-connect-core' ), esc_url( admin_url( 'edit.php?post_type=' . OC_CPT . '&page=oc-settings' ) ) ) );

		$rc = oc_recaptcha_enabled();
		$checks[] = $this->check( 'Integrations', 'reCAPTCHA v3', $rc, 'fail', __( 'Spam protection on registration and contact form is active.', 'owambe-connect-core' ), sprintf( __( 'Add site/secret keys at <a href="%s">Settings → Integrations</a>.', 'owambe-connect-core' ), esc_url( admin_url( 'edit.php?post_type=' . OC_CPT . '&page=oc-settings' ) ) ) );

		// SMTP delivery is now handled by an external plugin (e.g. FluentSMTP / WP Mail SMTP).
		// We detect whether any known SMTP plugin is active.
		$smtp_plugin = defined( 'FLUENTMAIL' ) || defined( 'WPMS_PLUGIN_VER' ) || class_exists( 'FluentMail\\App\\App' ) || class_exists( 'WPMailSMTP\\Core' );
		$checks[] = $this->check( 'Integrations', 'SMTP delivery plugin', $smtp_plugin, 'warn', __( 'A dedicated SMTP plugin (FluentSMTP, WP Mail SMTP, etc.) is handling outgoing email.', 'owambe-connect-core' ), __( 'Install FluentSMTP or WP Mail SMTP and connect to Brevo / Postmark / SendGrid / SES — otherwise mail uses your server\'s default and lands in spam.', 'owambe-connect-core' ) );

		$wf = class_exists( '\\wfConfig' ) || defined( 'WORDFENCE_VERSION' );
		$checks[] = $this->check( 'External services', 'Wordfence active', $wf, 'fail', __( 'Wordfence Premium is installed and active.', 'owambe-connect-core' ), __( 'Install Wordfence Premium — covers WAF, malware scan, brute-force, 2FA.', 'owambe-connect-core' ) );

		$cf = ! empty( $_SERVER['HTTP_CF_RAY'] ) || class_exists( 'CF\\WordPress\\Plugin' ) || function_exists( 'cloudflare\\WordPress\\Plugin' );
		$checks[] = $this->check( 'External services', 'Cloudflare proxying', $cf, 'warn', __( 'Cloudflare is proxying traffic (CF-Ray header detected).', 'owambe-connect-core' ), __( 'Point DNS through Cloudflare and enable proxy (orange cloud).', 'owambe-connect-core' ) );

		$backup = is_plugin_active( 'updraftplus/updraftplus.php' ) || is_plugin_active( 'backwpup/backwpup.php' ) || is_plugin_active( 'duplicator/duplicator.php' );
		$checks[] = $this->check( 'External services', 'Backup plugin', $backup, 'fail', __( 'A backup plugin is active.', 'owambe-connect-core' ), __( 'Install UpdraftPlus (or BackWPup / Duplicator) and schedule daily off-site backups.', 'owambe-connect-core' ) );

		$cache = is_plugin_active( 'wp-rocket/wp-rocket.php' ) || is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) || is_plugin_active( 'wp-super-cache/wp-cache.php' );
		$checks[] = $this->check( 'Performance', 'Page caching', $cache, 'warn', __( 'A page-cache plugin is active.', 'owambe-connect-core' ), __( 'Install WP Rocket for full-page cache + asset minify.', 'owambe-connect-core' ) );

		$image_optim = is_plugin_active( 'wp-smushit/wp-smush.php' ) || is_plugin_active( 'shortpixel-image-optimiser/wp-shortpixel.php' ) || is_plugin_active( 'ewww-image-optimizer/ewww-image-optimizer.php' );
		$checks[] = $this->check( 'Performance', 'Image optimisation', $image_optim, 'warn', __( 'An image-optimisation plugin is active.', 'owambe-connect-core' ), __( 'Install ShortPixel or Smush — auto-WebP + lazy load.', 'owambe-connect-core' ) );

		return $checks;
	}

	/**
	 * Build a check row. $fail_severity is what status to apply when the
	 * check fails — `fail` (red) for hard requirements, `warn` (amber) for
	 * recommended-but-optional items.
	 */
	private function check( $group, $label, $passed, $fail_severity, $detail_pass, $detail_fail ) {
		return [
			'group'  => $group,
			'label'  => $label,
			'status' => $passed ? 'pass' : $fail_severity,
			'detail' => $passed ? $detail_pass : $detail_fail,
		];
	}
}
