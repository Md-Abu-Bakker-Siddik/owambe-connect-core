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
?>
<section class="oc-section oc-auth">
	<div class="oc-container oc-auth__container">
		<header class="oc-auth__head">
			<h1 class="oc-auth__title"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( $subheading ) : ?><p class="oc-auth__lead"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</header>

		<div class="oc-auth__body">

			<div class="oc-auth__form-wrap">
				<!-- Tab Navigation (segmented, inside the form panel) -->
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
							<label for="oc-log-vendor"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></label>
							<input id="oc-log-vendor" type="email" name="log" required autocomplete="email" />
						</div>
						<div class="oc-field">
							<label for="oc-pwd-vendor"><?php esc_html_e( 'Password', 'owambe-connect-core' ); ?></label>
							<input id="oc-pwd-vendor" type="password" name="pwd" required autocomplete="current-password" />
						</div>
						<div class="oc-field oc-field--row">
							<label class="oc-checkbox"><input type="checkbox" name="rememberme" value="1" /> <span><?php esc_html_e( 'Remember me', 'owambe-connect-core' ); ?></span></label>
							<a class="oc-link" href="<?php echo esc_url( wp_lostpassword_url( add_query_arg( 'tab', 'vendor', $login_page_url ) ) ); ?>"><?php esc_html_e( 'Forgot password?', 'owambe-connect-core' ); ?></a>
						</div>
						<div class="oc-form__actions">
							<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php echo esc_html( $button_text ); ?></button>
						</div>
						<p class="oc-help oc-help--center">
							<?php esc_html_e( 'New vendor?', 'owambe-connect-core' ); ?>
							<a href="<?php echo esc_url( oc_page_url( 'apply' ) ); ?>"><?php esc_html_e( 'Apply to join', 'owambe-connect-core' ); ?></a>
						</p>
					</form>
				</div>

				<!-- Client panel — Google sign-in + native email/password login and a
				     link to create an account (for clients without a Google account). -->
				<div class="oc-auth__panel" data-panel="client" <?php echo 'client' === $active_tab ? '' : 'hidden'; ?>>
					<div class="oc-auth__client-google">
						<div class="oc-auth__signin-card">
							<span class="oc-auth__signin-badge" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="30" height="30">
									<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
									<path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
									<path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
									<path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
								</svg>
							</span>
							<h2 class="oc-auth__signin-title"><?php esc_html_e( 'Continue with Google', 'owambe-connect-core' ); ?></h2>
							<p class="oc-auth__signin-sub"><?php esc_html_e( 'Sign in or create your account in one click — no password to remember.', 'owambe-connect-core' ); ?></p>

							<div class="oc-auth__google-wrapper">
<?php
$is_configured = class_exists( 'OC_Google_Auth' ) && OC_Google_Auth::is_configured();
if ( $is_configured ) {
	echo OC_Google_Auth::button_html( $redirect_url ?: oc_page_url( 'client-dashboard' ) ); // phpcs:ignore
} else {
	?>
	<button class="oc-auth__google-button oc-auth__google-button--placeholder" disabled type="button">
		<svg viewBox="0 0 24 24" width="20" height="20" style="margin-right: 8px; display: inline-block; vertical-align: middle;">
			<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
			<path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
			<path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
			<path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
		</svg>
		<span style="vertical-align: middle;">
			<?php esc_html_e( 'Sign in with Google', 'owambe-connect-core' ); ?>
		</span>
	</button>
	<p class="oc-auth__config-notice">
		<?php esc_html_e( 'Google Sign-In is being set up. Please try again shortly.', 'owambe-connect-core' ); ?>
	</p>
	<?php
}
?>
							</div>

							<p class="oc-auth__signin-foot"><?php esc_html_e( 'Free to join · We never post on your behalf', 'owambe-connect-core' ); ?></p>
						</div>
					</div>
					<div class="oc-auth__divider" aria-hidden="true"><span><?php esc_html_e( 'or use your email', 'owambe-connect-core' ); ?></span></div>
					<!-- Client panel — native email/password sign-in (alongside Google). -->
					<form class="oc-form oc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OC_Client::ACTION_LOGIN ); ?>" />
						<?php if ( $redirect_url ) : ?>
							<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>" />
						<?php endif; ?>
						<?php wp_nonce_field( OC_Client::ACTION_LOGIN, 'oc_client_login_nonce' ); ?>
						<div class="oc-field">
							<label for="oc-log-client"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></label>
							<input id="oc-log-client" type="email" name="log" required autocomplete="email" />
						</div>
						<div class="oc-field">
							<label for="oc-pwd-client"><?php esc_html_e( 'Password', 'owambe-connect-core' ); ?></label>
							<input id="oc-pwd-client" type="password" name="pwd" required autocomplete="current-password" />
						</div>
						<div class="oc-field oc-field--row">
							<label class="oc-checkbox"><input type="checkbox" name="rememberme" value="1" /> <span><?php esc_html_e( 'Remember me', 'owambe-connect-core' ); ?></span></label>
							<a class="oc-link" href="<?php echo esc_url( wp_lostpassword_url( add_query_arg( 'tab', 'client', $login_page_url ) ) ); ?>"><?php esc_html_e( 'Forgot password?', 'owambe-connect-core' ); ?></a>
						</div>
						<div class="oc-form__actions">
							<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php esc_html_e( 'Sign in', 'owambe-connect-core' ); ?></button>
						</div>
						<p class="oc-help oc-help--center">
							<?php esc_html_e( 'New to Owambe Connect?', 'owambe-connect-core' ); ?>
							<a href="<?php echo esc_url( $client_register_url ); ?>"><?php esc_html_e( 'Create an account', 'owambe-connect-core' ); ?></a>
						</p>
					</form>
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

/* The active panel grows to fill the card and centres its content, so the
   short login form sits balanced against the taller info card. */
.oc-auth__panel:not([hidden]) {
	flex: 1 1 auto;
	display: flex;
	flex-direction: column;
	justify-content: center;
}
.oc-auth__panel[hidden],
.oc-auth__info-panel[hidden] { display: none; }

/* Client tab: form-wrap is the card now, so flatten the inner sign-in card. */
.oc-auth .oc-auth__client-google { padding: 0; }
.oc-auth .oc-auth__signin-card {
	background: transparent;
	border: 0;
	box-shadow: none;
	border-radius: 0;
	padding: 0;
	max-width: none;
}

/* Client Google-Only Section */
.oc-auth__client-google {
	text-align: center;
	padding: 1rem 0;
	display: flex;
	justify-content: center;
}

/* Dominant, elevated sign-in card */
.oc-auth__signin-card {
	width: 100%;
	max-width: 460px;
	margin: 0 auto;
	padding: 2.75rem 2.25rem 2.25rem;
	background: #fff;
	border: 1px solid var(--oc-border, #E4DDD2);
	border-top: 4px solid var(--oc-burgundy, #6E0F2C);
	border-radius: 18px;
	box-shadow: 0 18px 40px rgba(110, 15, 44, 0.10);
	text-align: center;
}

.oc-auth__signin-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 68px;
	height: 68px;
	border-radius: 50%;
	background: #FAF7F2;
	border: 1px solid var(--oc-border, #E4DDD2);
	margin-bottom: 1.25rem;
	box-shadow: 0 2px 6px rgba(31, 27, 26, 0.06);
}

.oc-auth__signin-title {
	font-size: 1.7rem;
	line-height: 1.2;
	color: var(--oc-burgundy, #6E0F2C);
	margin: 0 0 0.5rem;
	font-weight: 700;
}

.oc-auth__signin-sub {
	font-size: 15px;
	color: #6B6361;
	line-height: 1.6;
	margin: 0 auto 1.75rem;
	max-width: 320px;
}

.oc-auth__signin-foot {
	font-size: 13px;
	color: #9A938C;
	margin: 1.25rem 0 0;
}

.oc-auth__google-wrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 1rem;
	margin: 0;
	min-height: 50px;
}

/* Ensure Google button from OC_Google_Auth displays properly */
.oc-auth__google-wrapper button,
.oc-auth__google-wrapper a[role="button"] {
	display: inline-flex !important;
	align-items: center;
	justify-content: center;
	padding: 12px 24px !important;
	min-width: 280px;
	background: white !important;
	border: 1px solid #dadce0 !important;
	border-radius: 4px !important;
	font-size: 16px;
	font-weight: 500;
	color: #3c4043 !important;
	cursor: pointer;
	transition: all 0.2s ease;
	text-decoration: none !important;
	white-space: nowrap;
}

.oc-auth__google-button {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	padding: 12px 24px !important;
	min-width: 280px !important;
	background: white !important;
	border: 1px solid #dadce0 !important;
	border-radius: 4px !important;
	font-size: 16px !important;
	font-weight: 500 !important;
	color: #3c4043 !important;
	transition: all 0.2s ease;
	white-space: nowrap !important;
	position: relative !important;
	z-index: 10 !important;
	visibility: visible !important;
}

.oc-auth__google-button:not(:disabled):hover,
.oc-auth__google-wrapper button:not(:disabled):hover {
	background: #f8f9fa !important;
	box-shadow: 0 1px 2px rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
}

.oc-auth__google-button:focus-visible {
	outline: 2px solid var(--oc-gold, #C9A961);
	outline-offset: 2px;
}

.oc-auth__google-button--placeholder:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}

.oc-auth__config-notice {
	font-size: 13px;
	color: #999;
	margin: 0.5rem 0 0;
}

/* Mobile responsive */
@media (max-width: 600px) {
	.oc-auth__tabs-nav {
		margin-bottom: 1.5rem;
	}

	.oc-auth__tab-link {
		padding: 0.875rem 0.5rem;
		font-size: 14px;
	}

	.oc-auth__client-google {
		padding: 0.5rem 0;
	}

	.oc-auth__signin-card {
		padding: 2rem 1.5rem;
		border-radius: 14px;
	}

	.oc-auth__signin-title {
		font-size: 1.45rem;
	}
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
} )();
</script>
