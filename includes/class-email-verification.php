<?php
/**
 * Email verification for vendor accounts.
 *
 * Self-contained — no external SMTP plugin required. Generates a single-use
 * token at signup, mails the vendor a confirmation link, and verifies the
 * token on click. Verified state lives on the vendor post as
 * `_oc_email_verified` (0 / 1) so the profile-completion checklist and the
 * "Submit for review" gate can read it in one place.
 *
 * Why on the vendor post and not the user object: the dashboard already
 * keys all per-listing state off the post (rejection note, submitted-for-
 * review flag, completion percent), so it's cheaper for queries.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Email_Verification {

	const META_TOKEN_HASH = '_oc_email_verify_token';
	const META_TOKEN_EXP  = '_oc_email_verify_expires';
	const META_VERIFIED   = '_oc_email_verified';
	const META_LAST_SEND  = '_oc_email_verify_last_sent';

	const ACTION_VERIFY   = 'oc_verify_email';
	const ACTION_RESEND   = 'oc_email_verify_resend';

	const TOKEN_TTL       = 7 * DAY_IN_SECONDS;
	const RESEND_THROTTLE = 60; // seconds

	public function register() {
		// Issue + send a token whenever a vendor account is created.
		add_action( 'oc_after_vendor_registered', [ __CLASS__, 'issue_token_for_new_vendor' ], 20, 2 );

		// Click-through verification endpoint (plain GET on admin-post.php so
		// the link survives email-client preview behaviour without needing JS).
		add_action( 'admin_post_'        . self::ACTION_VERIFY, [ __CLASS__, 'handle_verify' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_VERIFY, [ __CLASS__, 'handle_verify' ] );

		// "Resend verification" button on the vendor dashboard Account tab.
		add_action( 'admin_post_' . self::ACTION_RESEND, [ __CLASS__, 'handle_resend' ] );
	}

	/**
	 * Hook target for new-vendor registration. Marks them unverified
	 * (explicit `0` — distinct from the legacy missing-meta state that's
	 * grandfathered) and dispatches the verification email.
	 */
	public static function issue_token_for_new_vendor( $post_id, $user_id ) {
		update_post_meta( $post_id, self::META_VERIFIED, 0 );
		self::send_email( $post_id, $user_id );
	}

	/**
	 * Issue a fresh token + send the email. Returns true on send, false on
	 * throttle / missing post. Hashing the token before storage means a
	 * DB-only attacker still can't replay verification.
	 */
	public static function send_email( $post_id, $user_id = 0 ) {
		$post_id = (int) $post_id;
		$user_id = (int) $user_id;
		if ( ! $post_id ) {
			return false;
		}
		if ( ! $user_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return false;
			}
			$user_id = (int) $post->post_author;
		}
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		// Anti-abuse throttle on the resend button.
		$last_sent = (int) get_post_meta( $post_id, self::META_LAST_SEND, true );
		if ( $last_sent && ( time() - $last_sent ) < self::RESEND_THROTTLE ) {
			return false;
		}

		$token = wp_generate_password( 32, false, false );
		update_post_meta( $post_id, self::META_TOKEN_HASH, wp_hash( $token ) );
		update_post_meta( $post_id, self::META_TOKEN_EXP,  time() + self::TOKEN_TTL );
		update_post_meta( $post_id, self::META_LAST_SEND,  time() );

		$verify_url = add_query_arg( [
			'action'  => self::ACTION_VERIFY,
			'post_id' => $post_id,
			'token'   => rawurlencode( $token ),
		], admin_url( 'admin-post.php' ) );

		$site      = get_bloginfo( 'name' );
		$logo_url  = function_exists( 'get_custom_logo' ) ? '' : ''; // future hook point
		$subject   = sprintf( __( 'Confirm your email for %s', 'owambe-connect-core' ), $site );
		$display   = $user->display_name ?: $user->user_login;

		// Inline email styles — kept dead simple so it renders sanely
		// across Gmail, Outlook, Apple Mail without needing the full
		// templates/emails/ chrome (which is overkill for a single CTA).
		$html  = '<div style="font-family:Inter,Arial,Helvetica,sans-serif;color:#1F1B1A;line-height:1.55;max-width:520px;margin:0 auto;">';
		$html .= '<h2 style="color:#6E0F2C;margin:0 0 14px;font-family:Georgia,serif;">' . esc_html__( 'One step left — verify your email', 'owambe-connect-core' ) . '</h2>';
		$html .= '<p>' . esc_html( sprintf(
			/* translators: %1$s vendor display name, %2$s site name */
			__( 'Hi %1$s, thanks for joining %2$s. Click the button below to confirm this email address.', 'owambe-connect-core' ),
			$display,
			$site
		) ) . '</p>';
		$html .= '<p style="margin:24px 0;"><a href="' . esc_url( $verify_url ) . '" style="background:#6E0F2C;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;display:inline-block;font-weight:600;">' . esc_html__( 'Confirm my email', 'owambe-connect-core' ) . '</a></p>';
		$html .= '<p style="font-size:13px;color:#6B6361;">' . esc_html__( 'Or paste this link into your browser:', 'owambe-connect-core' ) . '<br><span style="word-break:break-all;">' . esc_html( $verify_url ) . '</span></p>';
		$html .= '<p style="font-size:13px;color:#6B6361;">' . esc_html__( 'The link expires in 7 days. If you didn\'t create an account, you can ignore this email.', 'owambe-connect-core' ) . '</p>';
		$html .= '</div>';

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', class_exists( 'OC_Mail' ) ? OC_Mail::from_name() : $site, class_exists( 'OC_Mail' ) ? OC_Mail::from_email() : get_option( 'admin_email' ) ),
		];

		return wp_mail( $user->user_email, $subject, $html, $headers );
	}

	/**
	 * Verify a click-through link. Constant-time compare on the hashed
	 * token so a timing oracle can't enumerate post IDs.
	 */
	public static function handle_verify() {
		$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		$token   = isset( $_GET['token'] )   ? (string) wp_unslash( $_GET['token'] ) : '';
		$ref     = function_exists( 'oc_page_url' ) ? oc_page_url( 'vendor-dashboard' ) : home_url( '/' );

		if ( ! $post_id || '' === $token ) {
			wp_safe_redirect( add_query_arg( 'oc_verify', 'invalid', $ref ) );
			exit;
		}

		$stored = (string) get_post_meta( $post_id, self::META_TOKEN_HASH, true );
		$expiry = (int) get_post_meta( $post_id, self::META_TOKEN_EXP, true );

		if ( ! $stored || ! $expiry || $expiry < time() ) {
			wp_safe_redirect( add_query_arg( 'oc_verify', 'expired', $ref ) );
			exit;
		}
		if ( ! hash_equals( $stored, wp_hash( $token ) ) ) {
			wp_safe_redirect( add_query_arg( 'oc_verify', 'invalid', $ref ) );
			exit;
		}

		update_post_meta( $post_id, self::META_VERIFIED, 1 );
		delete_post_meta( $post_id, self::META_TOKEN_HASH );
		delete_post_meta( $post_id, self::META_TOKEN_EXP );

		// Refresh the cached completion % so the new tick shows up immediately.
		if ( function_exists( 'oc_profile_completion_save' ) ) {
			oc_profile_completion_save( $post_id );
		}

		do_action( 'oc_after_email_verified', $post_id );

		// Auto-login the user on click — they've proven inbox ownership, no
		// reason to make them re-enter credentials. We only do this when
		// they're not already logged in as someone else.
		$post = get_post( $post_id );
		if ( $post && ! is_user_logged_in() ) {
			wp_set_current_user( (int) $post->post_author );
			wp_set_auth_cookie( (int) $post->post_author, true );
		}

		wp_safe_redirect( add_query_arg( [
			'oc_verify' => 'ok',
			'tab'       => 'overview',
		], $ref ) );
		exit;
	}

	/**
	 * Vendor clicked the "Resend verification email" button on the
	 * dashboard Account tab. Requires nonce + an authenticated session.
	 */
	public static function handle_resend() {
		$ref = function_exists( 'oc_page_url' ) ? oc_page_url( 'vendor-dashboard' ) : home_url( '/' );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( add_query_arg( 'oc_verify', 'login', $ref ) );
			exit;
		}
		if ( ! isset( $_POST['oc_resend_nonce'] ) || ! wp_verify_nonce( $_POST['oc_resend_nonce'], self::ACTION_RESEND ) ) {
			wp_safe_redirect( add_query_arg( 'oc_verify', 'invalid', $ref ) );
			exit;
		}
		$user_id = get_current_user_id();
		$post    = function_exists( 'oc_get_current_vendor_post' ) ? oc_get_current_vendor_post() : null;
		if ( ! $post ) {
			wp_safe_redirect( add_query_arg( 'oc_verify', 'no_listing', $ref ) );
			exit;
		}
		$sent = self::send_email( $post->ID, $user_id );
		wp_safe_redirect( add_query_arg( [
			'oc_verify' => $sent ? 'resent' : 'throttled',
			'tab'       => 'account',
		], $ref ) );
		exit;
	}

	/**
	 * Convenience accessor — true when the vendor has verified
	 * (explicit `1`) or pre-dates this feature (empty meta).
	 */
	public static function is_verified( $post_id ) {
		$raw = get_post_meta( (int) $post_id, self::META_VERIFIED, true );
		if ( '' === $raw || null === $raw ) {
			return true;
		}
		return (int) $raw === 1;
	}
}
