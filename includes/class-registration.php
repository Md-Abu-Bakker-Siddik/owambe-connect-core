<?php
/**
 * Vendor registration handler.
 *
 * Minimal signup: email + password + business name. The vendor lands on the
 * dashboard with a profile-completion checklist; admin is only notified once
 * the vendor explicitly submits their listing for review.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Registration {

	const ACTION = 'oc_register_vendor';
	const NONCE  = 'oc_register_nonce';

	public function register() {
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_'        . self::ACTION, [ $this, 'handle' ] );
	}

	public function handle() {
		$ref  = wp_get_referer() ?: oc_page_url( 'apply' );
		$post = $_POST;

		$email         = isset( $post['email'] )         ? sanitize_email( wp_unslash( $post['email'] ) )                : '';
		$password      = isset( $post['password'] )      ? (string) wp_unslash( $post['password'] )                      : '';
		$business_name = isset( $post['business_name'] ) ? sanitize_text_field( wp_unslash( $post['business_name'] ) )   : '';
		$consent       = ! empty( $post['terms_consent'] );

		// Anything we send back on error so the user doesn't retype it.
		// Password is intentionally not preserved.
		$preserved = [
			'oc_email'         => $email,
			'oc_business_name' => $business_name,
		];

		if ( ! isset( $post[ self::NONCE ] ) || ! wp_verify_nonce( $post[ self::NONCE ], self::ACTION ) ) {
			$this->redirect_with_error( $ref, __( 'Security check failed. Please try again.', 'owambe-connect-core' ), $preserved );
		}

		$rc_token = isset( $post['oc_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $post['oc_recaptcha_token'] ) ) : '';
		if ( ! oc_verify_recaptcha( $rc_token ) ) {
			$this->redirect_with_error( $ref, __( 'Spam check failed. Please try again.', 'owambe-connect-core' ), $preserved );
		}

		$errors = [];
		if ( ! is_email( $email ) )    $errors[] = __( 'A valid email address is required.', 'owambe-connect-core' );
		if ( strlen( $password ) < 8 ) $errors[] = __( 'Password must be at least 8 characters.', 'owambe-connect-core' );
		if ( '' === $business_name )   $errors[] = __( 'Business name is required.', 'owambe-connect-core' );
		if ( ! $consent )              $errors[] = __( 'Please accept the terms to continue.', 'owambe-connect-core' );

		if ( $email && ( email_exists( $email ) || username_exists( $email ) ) ) {
			$errors[] = __( 'An account with this email already exists. Please log in instead.', 'owambe-connect-core' );
		}

		if ( $errors ) {
			$this->redirect_with_error( $ref, implode( ' ', $errors ), $preserved );
		}

		$user_id = wp_insert_user( [
			'user_login'   => $email,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $business_name,
			'role'         => OC_ROLE,
		] );
		if ( is_wp_error( $user_id ) ) {
			$this->redirect_with_error( $ref, $user_id->get_error_message(), $preserved );
		}

		// Vendor post starts in pending status but flagged as a draft (not yet
		// submitted for review). The dashboard hides it from admin until the
		// vendor explicitly submits.
		$post_id = wp_insert_post( [
			'post_type'    => OC_CPT,
			'post_status'  => OC_STATUS_PENDING,
			'post_title'   => $business_name,
			'post_content' => '',
			'post_author'  => $user_id,
		], true );

		if ( is_wp_error( $post_id ) ) {
			wp_delete_user( $user_id );
			$this->redirect_with_error( $ref, $post_id->get_error_message(), $preserved );
		}

		update_post_meta( $post_id, '_oc_business_name',         $business_name );
		update_post_meta( $post_id, '_oc_submitted_for_review',  0 );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		do_action( 'oc_after_vendor_registered', $post_id, $user_id );

		// Admin notification is deferred until the vendor submits for review.
		// Vendor gets a welcome email immediately.
		OC_Mail::application_received( $post_id );

		$dashboard = oc_page_url( 'vendor-dashboard' );
		wp_safe_redirect( add_query_arg( 'oc_welcome', '1', $dashboard ) );
		exit;
	}

	private function redirect_with_error( $url, $message, array $extra = [] ) {
		$args = array_merge( $extra, [ 'oc_error' => rawurlencode( $message ) ] );
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}
}
