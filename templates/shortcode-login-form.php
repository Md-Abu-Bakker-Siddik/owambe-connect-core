<?php
/**
 * Unified login template supporting both vendor and client (user) sign-in.
 * Tab state is in the URL: ?tab=vendor or ?tab=client
 *
 * @package OwambeConnect
 */
defined( 'ABSPATH' ) || exit;

$err = isset( $_GET['oc_error'] ) ? wp_unslash( $_GET['oc_error'] ) : '';

// Get active tab from URL, default to vendor
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'vendor';
if ( ! in_array( $active_tab, [ 'vendor', 'client' ], true ) ) {
	$active_tab = 'vendor';
}

$heading      = ! empty( $heading )      ? $heading      : __( 'Welcome back', 'owambe-connect-core' );
$subheading   = ! empty( $subheading )   ? $subheading   : __( 'Sign in to your account.', 'owambe-connect-core' );
$button_text  = ! empty( $button_text )  ? $button_text  : __( 'Log In', 'owambe-connect-core' );
$redirect_url = ! empty( $redirect_url ) ? $redirect_url : '';
if ( '' === $redirect_url && ! empty( $_GET['redirect_to'] ) ) {
	$redirect_url = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
}

$login_page_url = oc_page_url( 'vendor-login' );

// "Create account" target on the client tab → the dedicated client-login page in
// register mode, preserving any redirect round-trip. add_query_arg does not
// URL-encode, so the redirect value is rawurlencode()'d.
$client_register_url = add_query_arg(
	array_merge(
		$redirect_url ? [ 'redirect_to' => rawurlencode( $redirect_url ) ] : [],
		[ 'mode' => 'register' ]
	),
	oc_page_url( 'client-login' )
);

// Inline eye icon for the show/hide password toggles (stored once to avoid duplication).
$oc_eye_svg = '<svg class="oc-eye" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';

// OAuth 2.0 start URL for the static "Log in with Google" button (wired by JS
// below). Empty until a Google Client ID is saved in settings — button stays inert.
$google_oauth_url = ( class_exists( 'OC_Google_Auth' ) && OC_Google_Auth::is_configured() )
	? OC_Google_Auth::oauth_start_url( $redirect_url ?: oc_page_url( 'client-dashboard' ) )
	: '';
?>
<section class="oc-section oc-auth">
	<div class="oc-container oc-auth__container">

		<div class="oc-auth__body">

			<div class="oc-auth__form-wrap">
				<!-- Tab Navigation (segmented, at the very top) -->
				<div class="oc-auth__tabs-nav" role="tablist">
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'vendor', $login_page_url ) ); ?>"
						class="oc-auth__tab-link <?php echo 'vendor' === $active_tab ? 'is-active' : ''; ?>"
						role="tab" aria-selected="<?php echo 'vendor' === $active_tab ? 'true' : 'false'; ?>"
						data-tab="vendor">
						<?php esc_html_e( 'Vendor', 'owambe-connect-core' ); ?>
					</a>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'client', $login_page_url ) ); ?>"
						class="oc-auth__tab-link <?php echo 'client' === $active_tab ? 'is-active' : ''; ?>"
						role="tab" aria-selected="<?php echo 'client' === $active_tab ? 'true' : 'false'; ?>"
						data-tab="client">
						<?php esc_html_e( 'Client', 'owambe-connect-core' ); ?>
					</a>
				</div>

				<!-- Title + subtitle (shared across both tabs) -->
				<div class="oc-auth__intro">
					<h2 class="oc-auth__form-title"><?php esc_html_e( 'Sign in to your Account', 'owambe-connect-core' ); ?></h2>
					<p class="oc-auth__form-sub"><?php esc_html_e( 'Use your email to log in to your account', 'owambe-connect-core' ); ?></p>
				</div>

				<?php if ( $err ) : ?>
					<div class="oc-alert oc-alert--error" role="alert"><?php echo esc_html( $err ); ?></div>
				<?php endif; ?>

				<!-- Vendor panel — email/password sign-in (Google is client-only) -->
				<div class="oc-auth__panel" data-panel="vendor" <?php echo 'vendor' === $active_tab ? '' : 'hidden'; ?>>
					<form class="oc-form oc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OC_Dashboard::ACTION_LOGIN ); ?>" />
						<?php if ( $redirect_url ) : ?>
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>" />
						<?php endif; ?>
						<?php wp_nonce_field( OC_Dashboard::ACTION_LOGIN, 'oc_login_nonce' ); ?>

						<div class="oc-field">
							<label for="oc-log-vendor"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?> <span class="oc-req" aria-hidden="true">*</span></label>
							<input id="oc-log-vendor" type="email" name="log" required autocomplete="email" />
						</div>
						<div class="oc-field">
							<label for="oc-pwd-vendor"><?php esc_html_e( 'Password', 'owambe-connect-core' ); ?> <span class="oc-req" aria-hidden="true">*</span></label>
							<div class="oc-pwd">
								<input id="oc-pwd-vendor" type="password" name="pwd" required autocomplete="current-password" />
								<button type="button" class="oc-pwd-toggle" data-oc-pwd-toggle aria-label="<?php esc_attr_e( 'Show password', 'owambe-connect-core' ); ?>"><?php echo $oc_eye_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
							</div>
						</div>
						<div class="oc-field oc-field--row">
							<label class="oc-checkbox"><input type="checkbox" name="rememberme" value="1" /> <span><?php esc_html_e( 'Remember me', 'owambe-connect-core' ); ?></span></label>
							<a class="oc-link" href="<?php echo esc_url( wp_lostpassword_url( add_query_arg( 'tab', 'vendor', $login_page_url ) ) ); ?>"><?php esc_html_e( 'Forgot password?', 'owambe-connect-core' ); ?></a>
						</div>
						<div class="oc-form__actions">
							<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php esc_html_e( 'Login', 'owambe-connect-core' ); ?></button>
						</div>
						<p class="oc-help oc-help--center">
							<?php esc_html_e( 'New vendor?', 'owambe-connect-core' ); ?>
							<a href="<?php echo esc_url( oc_page_url( 'apply' ) ); ?>"><?php esc_html_e( 'Apply to join', 'owambe-connect-core' ); ?></a>
						</p>
					</form>
				</div>

				<!-- Client panel — email/password sign-in, then Google, then a
				     link to create an account (for clients without a Google account). -->
				<div class="oc-auth__panel" data-panel="client" <?php echo 'client' === $active_tab ? '' : 'hidden'; ?>>
					<form class="oc-form oc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OC_Client::ACTION_LOGIN ); ?>" />
						<?php if ( $redirect_url ) : ?>
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>" />
						<?php endif; ?>
						<?php wp_nonce_field( OC_Client::ACTION_LOGIN, 'oc_client_login_nonce' ); ?>
						<div class="oc-field">
							<label for="oc-log-client"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?> <span class="oc-req" aria-hidden="true">*</span></label>
							<input id="oc-log-client" type="email" name="log" required autocomplete="email" />
						</div>
						<div class="oc-field">
							<label for="oc-pwd-client"><?php esc_html_e( 'Password', 'owambe-connect-core' ); ?> <span class="oc-req" aria-hidden="true">*</span></label>
							<div class="oc-pwd">
								<input id="oc-pwd-client" type="password" name="pwd" required autocomplete="current-password" />
								<button type="button" class="oc-pwd-toggle" data-oc-pwd-toggle aria-label="<?php esc_attr_e( 'Show password', 'owambe-connect-core' ); ?>"><?php echo $oc_eye_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
							</div>
						</div>
						<div class="oc-field oc-field--row">
							<label class="oc-checkbox"><input type="checkbox" name="rememberme" value="1" /> <span><?php esc_html_e( 'Remember me', 'owambe-connect-core' ); ?></span></label>
							<a class="oc-link" href="<?php echo esc_url( wp_lostpassword_url( add_query_arg( 'tab', 'client', $login_page_url ) ) ); ?>"><?php esc_html_e( 'Forgot password?', 'owambe-connect-core' ); ?></a>
						</div>
						<div class="oc-form__actions">
							<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php esc_html_e( 'Login', 'owambe-connect-core' ); ?></button>
						</div>
					</form>

					<!-- Static "Log in with Google" button (standard HTML — no GIS widget). -->
					<button type="button" id="oc-google-login-btn" class="oc-btn-google">
						<span class="oc-btn-google__icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
								<path fill="#4285F4" d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>
								<path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.859-3.048.859-2.344 0-4.328-1.583-5.036-3.71H.957v2.332A9 9 0 0 0 9 18z"/>
								<path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A9 9 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>
								<path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.346l2.582-2.581C13.463.891 11.426 0 9 0A9 9 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>
							</svg>
						</span>
						<span class="oc-btn-google__label"><?php esc_html_e( 'Log in with Google', 'owambe-connect-core' ); ?></span>
					</button>

					<p class="oc-help oc-help--center oc-auth__create">
						<a href="<?php echo esc_url( $client_register_url ); ?>"><?php esc_html_e( 'Create an account Signup', 'owambe-connect-core' ); ?></a>
					</p>
				</div>
			</div>

			<!-- Sidebar Info (toggles with the active tab, in sync with the panels) -->
			<aside class="oc-auth__info">
				<div class="oc-auth__info-panel" data-info="vendor" <?php echo 'vendor' === $active_tab ? '' : 'hidden'; ?>>
					<h2 class="oc-auth__info-title"><?php esc_html_e( 'Manage your vendor profile', 'owambe-connect-core' ); ?></h2>
					<p class="oc-auth__info-lead"><?php esc_html_e( 'Sign in to update your listing, respond to enquiries and grow your business.', 'owambe-connect-core' ); ?></p>
					<ul class="oc-auth__perks">
						<li>
							<span class="oc-auth__perk-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h7v7H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 14h7v7H3z"/></svg>
							</span>
							<span>
								<strong><?php esc_html_e( 'Live dashboard', 'owambe-connect-core' ); ?></strong>
								<span><?php esc_html_e( 'Edit your bio, photos, contact details and categories.', 'owambe-connect-core' ); ?></span>
							</span>
						</li>
						<li>
							<span class="oc-auth__perk-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
							</span>
							<span>
								<strong><?php esc_html_e( 'Direct enquiries', 'owambe-connect-core' ); ?></strong>
								<span><?php esc_html_e( 'WhatsApp, Instagram or email — no middleman.', 'owambe-connect-core' ); ?></span>
							</span>
						</li>
						<li>
							<span class="oc-auth__perk-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
							</span>
							<span>
								<strong><?php esc_html_e( 'Trusted', 'owambe-connect-core' ); ?></strong>
								<span><?php esc_html_e( 'Quality over volume — reviewed before going live.', 'owambe-connect-core' ); ?></span>
							</span>
						</li>
					</ul>
				</div>
				<div class="oc-auth__info-panel" data-info="client" <?php echo 'client' === $active_tab ? '' : 'hidden'; ?>>
					<h2 class="oc-auth__info-title"><?php esc_html_e( 'Plan your event', 'owambe-connect-core' ); ?></h2>
					<p class="oc-auth__info-lead"><?php esc_html_e( 'Sign in with one click and start planning your perfect event.', 'owambe-connect-core' ); ?></p>
					<ul class="oc-auth__perks">
						<li>
							<span class="oc-auth__perk-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4m0 0h4m-4 0v6m4-6h4a2 2 0 012 2v14a2 2 0 01-2 2h-4m0 0v-6m0 6H9"/></svg>
							</span>
							<span>
								<strong><?php esc_html_e( 'Instant access', 'owambe-connect-core' ); ?></strong>
								<span><?php esc_html_e( 'One-click login with your Google account.', 'owambe-connect-core' ); ?></span>
							</span>
						</li>
						<li>
							<span class="oc-auth__perk-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/></svg>
							</span>
							<span>
								<strong><?php esc_html_e( 'Saved vendors', 'owambe-connect-core' ); ?></strong>
								<span><?php esc_html_e( 'Save your favourite vendors for easy access.', 'owambe-connect-core' ); ?></span>
							</span>
						</li>
						<li>
							<span class="oc-auth__perk-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
							</span>
							<span>
								<strong><?php esc_html_e( 'Event planning', 'owambe-connect-core' ); ?></strong>
								<span><?php esc_html_e( 'Organize every detail of your celebration.', 'owambe-connect-core' ); ?></span>
							</span>
						</li>
					</ul>
				</div>
			</aside>

		</div>
	</div>
</section>

<style>
/* Unified Login Tab Styles — segmented pill control */
.oc-auth__tabs-nav {
	display: flex;
	gap: 4px;
	margin: 0 0 1.75rem;
	padding: 5px;
	background: #F4ECE1;
	border: 1px solid var(--oc-border, #E4DDD2);
	border-radius: 12px;
}

/* !important beats the theme's .oc-auth__form-wrap a:not(.oc-btn) link colour,
   which otherwise paints the active tab burgundy-on-burgundy (invisible). */
.oc-auth__tab-link {
	flex: 1;
	padding: 0.72rem 1rem;
	text-align: center;
	color: #6B6361 !important;
	font-weight: 600;
	font-size: 15px;
	text-decoration: none;
	border-radius: 9px;
	transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}

.oc-auth__tab-link:hover:not(.is-active) {
	color: var(--oc-burgundy, #6E0F2C) !important;
	background: rgba(110, 15, 44, 0.06);
}

.oc-auth__tab-link.is-active {
	background: var(--oc-burgundy, #6E0F2C);
	color: #fff !important;
	box-shadow: 0 2px 8px rgba(110, 15, 44, 0.28);
}

.oc-auth__tab-link:focus-visible {
	outline: 2px solid var(--oc-gold, #C9A961);
	outline-offset: 2px;
}

/* ── Symmetry: make the left column a single white card (tabs + form inside),
   the same height as the right info card. ─────────────────────────────── */
.oc-auth .oc-auth__body { align-items: stretch; }

.oc-auth .oc-auth__form-wrap {
	background: #fff;
	border: 1px solid var(--oc-border, #E4DDD2);
	border-radius: 16px;
	box-shadow: 0 10px 30px rgba(110, 15, 44, 0.06);
	padding: 24px;
	max-width: none;
	display: flex;
	flex-direction: column;
}

/* Drop the inner form's own card (from marketplace.css .oc-form) — the
   form-wrap is the card now, so we avoid a card-in-card. */
.oc-auth .oc-form.oc-auth__form {
	background: transparent;
	border: 0;
	box-shadow: none;
	padding: 0;
	margin: 0;
}

/* The active panel fills the card; content is top-aligned under the shared
   title/subtitle for a clean, standard sign-in flow. */
.oc-auth__panel:not([hidden]) {
	flex: 1 1 auto;
	display: flex;
	flex-direction: column;
	justify-content: flex-start;
}
.oc-auth__panel[hidden],
.oc-auth__info-panel[hidden] { display: none; }

/* Shared title + subtitle, directly beneath the Vendor/Client switcher. */
.oc-auth__intro {
	text-align: center;
	margin: 0 0 1.5rem;
}
.oc-auth__form-title {
	font-size: 1.5rem;
	line-height: 1.25;
	font-weight: 700;
	color: var(--oc-ink, #1F1B1A);
	margin: 0 0 0.35rem;
}
.oc-auth__form-sub {
	font-size: 14.5px;
	color: #6B6361;
	line-height: 1.5;
	margin: 0;
}

/* Required-field asterisk on labels. */
.oc-req { color: #C0392B; font-weight: 700; }

/* Password field with an inline show/hide toggle. */
.oc-pwd { position: relative; display: block; }
.oc-pwd > input { width: 100%; padding-right: 46px; }
.oc-pwd-toggle {
	position: absolute;
	top: 50%;
	right: 6px;
	transform: translateY(-50%);
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 34px;
	height: 34px;
	padding: 0;
	margin: 0;
	background: transparent;
	border: 0;
	border-radius: 8px;
	color: #8A827C;
	cursor: pointer;
	transition: color 0.15s ease, background 0.15s ease;
}
.oc-pwd-toggle:hover { color: var(--oc-burgundy, #6E0F2C); background: rgba(110, 15, 44, 0.06); }
.oc-pwd-toggle:focus-visible { outline: 2px solid var(--oc-gold, #C9A961); outline-offset: 1px; }
.oc-pwd-toggle.is-on { color: var(--oc-burgundy, #6E0F2C); }

/* ── Modern form fields ─────────────────────────────────────────────────── */
.oc-auth .oc-field { margin: 0 0 1.05rem; }
.oc-auth .oc-field > label {
	display: block;
	font-weight: 600;
	font-size: 13.5px;
	color: #3A3330;
	margin: 0 0 6px;
}
.oc-auth .oc-field input[type="email"],
.oc-auth .oc-field input[type="password"],
.oc-auth .oc-field input[type="text"] {
	width: 100%;
	box-sizing: border-box;
	padding: 12px 14px;
	font-size: 15px;
	line-height: 1.3;
	color: #1F1B1A;
	background: #FAF8F5;
	border: 1px solid var(--oc-border, #E4DDD2);
	border-radius: 10px;
	transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.oc-auth .oc-field input::placeholder { color: #B4ABA3; }
.oc-auth .oc-field input:focus {
	outline: none;
	background: #fff;
	border-color: var(--oc-burgundy, #6E0F2C);
	box-shadow: 0 0 0 3px rgba(110, 15, 44, 0.10);
}

/* Remember me (left) / Forgot password (right) row */
.oc-auth .oc-field--row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 1.35rem;
}

/* Primary "Login" button — full width, modern radius */
.oc-auth .oc-form__actions { margin: 0 0 0.9rem; }
.oc-auth .oc-btn-primary {
	border-radius: 10px;
	font-weight: 600;
}

/* Secondary "Log in with Google" — static button, full-width, white/bordered,
   matching the inputs and primary button. Colour G icon left, text centred. */
.oc-btn-google {
	position: relative;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	box-sizing: border-box;
	min-height: 48px;
	padding: 10px 16px;
	background: #FFFFFF;
	border: 1px solid #CBD5E1;
	border-radius: 10px;
	color: #3A3330;
	font-size: 15px;
	font-weight: 600;
	line-height: 1.3;
	cursor: pointer;
	transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
.oc-btn-google:hover {
	background: #F8FAFC;
	border-color: #B7C2D0;
	box-shadow: 0 1px 3px rgba(31, 27, 26, 0.08);
}
.oc-btn-google:focus-visible {
	outline: 2px solid var(--oc-gold, #C9A961);
	outline-offset: 2px;
}
.oc-btn-google__icon {
	position: absolute;
	left: 16px;
	top: 50%;
	transform: translateY(-50%);
	display: inline-flex;
	align-items: center;
}
.oc-btn-google__label { text-align: center; }

.oc-auth__create { margin-top: 1.5rem; margin-bottom: 0; }

/* Mobile responsive */
@media (max-width: 600px) {
	.oc-auth__tabs-nav { margin-bottom: 1.5rem; }
	.oc-auth__tab-link {
		padding: 0.875rem 0.5rem;
		font-size: 14px;
	}
	.oc-auth__form-title { font-size: 1.35rem; }
}
</style>

<script>
/* Client-side tab switching: both panels are already in the DOM, so we just
   toggle visibility — no page reload, no flash, no layout jump. Falls back to
   the plain ?tab= links if JS is disabled. */
( function () {
	var root = document.querySelector( '.oc-auth' );
	if ( ! root ) { return; }
	var tabs = root.querySelectorAll( '.oc-auth__tab-link' );

	function show( tab ) {
		tabs.forEach( function ( t ) {
			var on = t.getAttribute( 'data-tab' ) === tab;
			t.classList.toggle( 'is-active', on );
			t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );
		root.querySelectorAll( '[data-panel]' ).forEach( function ( p ) {
			p.hidden = ( p.getAttribute( 'data-panel' ) !== tab );
		} );
		root.querySelectorAll( '[data-info]' ).forEach( function ( p ) {
			p.hidden = ( p.getAttribute( 'data-info' ) !== tab );
		} );
	}

	tabs.forEach( function ( t ) {
		t.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var tab = t.getAttribute( 'data-tab' );
			show( tab );
			if ( window.history && history.replaceState ) {
				history.replaceState( null, '', t.getAttribute( 'href' ) );
			}
		} );
	} );

	/* Show/hide password toggles. */
	root.querySelectorAll( '[data-oc-pwd-toggle]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var wrap = btn.closest( '.oc-pwd' );
			var input = wrap ? wrap.querySelector( 'input' ) : null;
			if ( ! input ) { return; }
			var reveal = input.type === 'password';
			input.type = reveal ? 'text' : 'password';
			btn.classList.toggle( 'is-on', reveal );
			btn.setAttribute( 'aria-label', reveal ? 'Hide password' : 'Show password' );
		} );
	} );

	/* Static "Log in with Google" button → OAuth 2.0 authorization-code flow.
	   URL is empty (button inert) until a Google Client ID is saved in settings. */
	var gBtn = document.getElementById( 'oc-google-login-btn' );
	var gUrl = <?php echo wp_json_encode( $google_oauth_url ); ?>;
	if ( gBtn && gUrl ) {
		gBtn.addEventListener( 'click', function () { window.location.href = gUrl; } );
	}
} )();
</script>
