<?php
/**
 * Transactional email sender.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Mail {

	public static function from_name() {
		$name = oc_get_setting( 'from_name', 'Owambe Connect' );
		return apply_filters( 'oc_mail_from_name', $name ?: 'Owambe Connect' );
	}

	public static function from_email() {
		$email = oc_get_setting( 'from_email' );
		if ( ! $email || ! is_email( $email ) ) {
			$email = get_option( 'admin_email' );
		}
		return apply_filters( 'oc_mail_from_email', $email );
	}

	public static function notification_recipient() {
		$email = oc_get_setting( 'notification_email' );
		if ( ! $email || ! is_email( $email ) ) {
			$email = get_option( 'admin_email' );
		}
		return apply_filters( 'oc_mail_notification_recipient', $email );
	}

	/**
	 * Shared inbox to CC on every admin-bound notification. Reads the public
	 * contact email from the Customizer; falls back to info@owambeconnect.com
	 * so the brand inbox always sees a copy alongside whoever the admin
	 * email is set to. Returns '' when the CC would duplicate the primary
	 * recipient (so admin doesn't get the same mail twice).
	 */
	public static function notification_cc() {
		$cc = '';
		if ( function_exists( 'oc_get_contact_email' ) ) {
			$cc = (string) oc_get_contact_email();
			if ( $cc === get_option( 'admin_email' ) ) {
				$cc = ''; // helper falls back to admin_email when unset — treat as absent
			}
		}
		if ( ! $cc || ! is_email( $cc ) ) {
			$cc = 'info@owambeconnect.com';
		}
		$cc = apply_filters( 'oc_mail_notification_cc', $cc );
		if ( ! $cc || ! is_email( $cc ) ) {
			return '';
		}
		if ( strcasecmp( $cc, (string) self::notification_recipient() ) === 0 ) {
			return '';
		}
		return $cc;
	}

	/** Base headers + optional shared-inbox CC for admin-bound notifications. */
	private static function admin_headers( array $extra = [] ) {
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', self::from_name(), self::from_email() ),
		];
		$cc = self::notification_cc();
		if ( $cc ) {
			$headers[] = 'Cc: ' . $cc;
		}
		return array_merge( $headers, $extra );
	}

	private static function send( $to, $subject, $body ) {
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', self::from_name(), self::from_email() ),
		];
		wp_mail( $to, $subject, $body, $headers );
	}

	private static function send_admin( $subject, $body, array $extra_headers = [] ) {
		wp_mail( self::notification_recipient(), $subject, $body, self::admin_headers( $extra_headers ) );
	}

	private static function render( $template, array $args = [] ) {
		$path = OC_TEMPLATE_DIR . 'emails/' . $template . '.php';
		if ( ! file_exists( $path ) ) {
			return '';
		}
		extract( $args, EXTR_SKIP );
		$header = OC_TEMPLATE_DIR . 'emails/html-email-header.php';
		$footer = OC_TEMPLATE_DIR . 'emails/html-email-footer.php';
		ob_start();
		if ( file_exists( $header ) ) include $header;
		include $path;
		if ( file_exists( $footer ) ) include $footer;
		return ob_get_clean();
	}

	public static function application_received( $vendor_post_id ) {
		$post = get_post( $vendor_post_id );
		if ( ! $post ) return;
		$user = get_user_by( 'id', $post->post_author );
		if ( ! $user ) return;
		$body = self::render( 'application-received', [
			'post' => $post, 'user' => $user, 'site_name' => get_bloginfo( 'name' ),
		] );
		self::send( $user->user_email, sprintf( __( 'We received your %s application', 'owambe-connect-core' ), get_bloginfo( 'name' ) ), $body );
	}

	public static function admin_new_application( $vendor_post_id ) {
		$post = get_post( $vendor_post_id );
		if ( ! $post ) return;
		$body = self::render( 'admin-new-application', [
			'post' => $post, 'edit_url' => admin_url( 'post.php?action=edit&post=' . $post->ID ),
		] );
		self::send_admin( sprintf( __( 'New vendor application — %s', 'owambe-connect-core' ), $post->post_title ), $body );
	}

	/**
	 * Branded "reset your password" email. Sent from the /forgot-password/
	 * handler with a link that lands on /reset-password/?key=…&login=… —
	 * the branded reset page processes the rest.
	 */
	public static function password_reset( $user, $reset_url ) {
		if ( ! ( $user instanceof WP_User ) ) return false;
		$body = self::render( 'password-reset', [
			'user'      => $user,
			'reset_url' => $reset_url,
			'site_name' => get_bloginfo( 'name' ),
		] );
		return wp_mail(
			$user->user_email,
			sprintf( __( 'Reset your %s password', 'owambe-connect-core' ), get_bloginfo( 'name' ) ?: 'Owambe Connect' ),
			$body,
			[
				'Content-Type: text/html; charset=UTF-8',
				sprintf( 'From: %s <%s>', self::from_name(), self::from_email() ),
			]
		);
	}

	public static function vendor_approved( $vendor_post_id ) {
		$post = get_post( $vendor_post_id );
		if ( ! $post ) return;
		$user = get_user_by( 'id', $post->post_author );
		if ( ! $user ) return;
		$body = self::render( 'vendor-approved', [
			'post' => $post, 'user' => $user, 'profile_url' => get_permalink( $post ),
		] );
		self::send( $user->user_email, __( 'Your Owambe Connect listing is live', 'owambe-connect-core' ), $body );
	}

	/**
	 * Phase 2 — welcome email for a client (event host) who just signed in
	 * with Google for the first time. Client-facing: no admin CC.
	 * Subject stays plain ASCII (see the subject-line rules below).
	 */
	public static function client_welcome( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) return;
		$body = self::render( 'client-welcome', [
			'first_name'    => $user->first_name ?: $user->display_name,
			'dashboard_url' => oc_page_url( 'client-dashboard' ),
			'vendors_url'   => oc_page_url( 'vendors' ),
		] );
		self::send( $user->user_email, sprintf( __( 'Welcome to %s', 'owambe-connect-core' ), get_bloginfo( 'name' ) ?: 'Owambe Connect' ), $body );
	}

	/**
	 * Phase 2 — tells the vendor an admin-approved review is now live on
	 * their profile. Vendor-facing: no admin CC. ASCII-only subject.
	 */
	public static function review_approved( $review_post_id ) {
		$review = get_post( $review_post_id );
		if ( ! $review || 'oc_review' !== $review->post_type ) return;
		$vendor = get_post( (int) get_post_meta( $review->ID, '_oc_review_vendor_id', true ) );
		if ( ! $vendor ) return;
		$user = get_user_by( 'id', $vendor->post_author );
		if ( ! $user ) return;
		$reviewer = get_user_by( 'id', $review->post_author );
		$body     = self::render( 'review-approved', [
			'business_name' => get_post_meta( $vendor->ID, '_oc_business_name', true ) ?: $vendor->post_title,
			'reviewer_name' => $reviewer ? ( $reviewer->first_name ?: $reviewer->display_name ) : __( 'A client', 'owambe-connect-core' ),
			'rating'        => (int) get_post_meta( $review->ID, '_oc_review_rating', true ),
			'review_text'   => $review->post_content,
			'profile_url'   => get_permalink( $vendor ) . '#reviews',
			'dashboard_url' => oc_page_url( 'vendor-dashboard' ) . '?tab=reviews',
		] );
		self::send( $user->user_email, __( 'You have a new review on Owambe Connect', 'owambe-connect-core' ), $body );
	}

	public static function vendor_rejected( $vendor_post_id, $reason = '' ) {
		$post = get_post( $vendor_post_id );
		if ( ! $post ) return;
		$user = get_user_by( 'id', $post->post_author );
		if ( ! $user ) return;
		$body = self::render( 'vendor-rejected', [
			'post' => $post, 'user' => $user, 'reason' => $reason,
			'dashboard_url' => oc_page_url( 'vendor-dashboard' ),
		] );
		self::send( $user->user_email, __( 'Your Owambe Connect listing needs changes', 'owambe-connect-core' ), $body );
	}

	public static function contact_message( $name, $email, $message ) {
		$body  = self::render( 'contact-message', compact( 'name', 'email', 'message' ) );
		$extra = is_email( $email ) ? [ 'Reply-To: ' . $email ] : [];
		return wp_mail( self::notification_recipient(), sprintf( __( 'New contact message from %s', 'owambe-connect-core' ), $name ), $body, self::admin_headers( $extra ) );
	}

	/**
	 * Vendor → admin support ticket. Distinct subject line so admin can
	 * triage these separately from feedback / vendor-request submissions.
	 */
	public static function support_ticket( $vendor_post_id, $subject, $message ) {
		$post  = get_post( $vendor_post_id );
		$user  = $post ? get_user_by( 'id', $post->post_author ) : null;
		$vnum  = $post ? (string) get_post_meta( $post->ID, '_oc_vendor_number', true ) : '';

		$body  = '<p><strong>' . esc_html__( 'New support ticket from a vendor', 'owambe-connect-core' ) . '</strong></p>';
		$body .= '<table style="font-family:Arial,sans-serif;font-size:14px;border-collapse:collapse;">';
		if ( $post ) {
			$body .= '<tr><td style="padding:4px 12px 4px 0;color:#6B6361;">' . esc_html__( 'Vendor', 'owambe-connect-core' ) . '</td><td>' . esc_html( $post->post_title ) . ( $vnum ? ' <code>' . esc_html( $vnum ) . '</code>' : '' ) . '</td></tr>';
		}
		if ( $user ) {
			$body .= '<tr><td style="padding:4px 12px 4px 0;color:#6B6361;">' . esc_html__( 'From', 'owambe-connect-core' ) . '</td><td>' . esc_html( $user->display_name ) . ' &lt;' . esc_html( $user->user_email ) . '&gt;</td></tr>';
		}
		$body .= '<tr><td style="padding:4px 12px 4px 0;color:#6B6361;">' . esc_html__( 'Subject', 'owambe-connect-core' ) . '</td><td>' . esc_html( $subject ) . '</td></tr>';
		$body .= '</table>';
		$body .= '<hr><div style="white-space:pre-wrap;font-family:Arial,sans-serif;font-size:14px;">' . esc_html( $message ) . '</div>';

		$extra = ( $user && is_email( $user->user_email ) ) ? [ 'Reply-To: ' . $user->user_email ] : [];
		wp_mail(
			self::notification_recipient(),
			sprintf( __( '[Support] %s', 'owambe-connect-core' ), $subject ),
			$body,
			self::admin_headers( $extra )
		);
	}

	/**
	 * Vendor → admin product feedback / suggestion. Tagged with [Feedback]
	 * so admin can filter / route separately from urgent support tickets.
	 */
	public static function vendor_feedback( $vendor_post_id, $topic, $message ) {
		$post  = get_post( $vendor_post_id );
		$user  = $post ? get_user_by( 'id', $post->post_author ) : null;
		$vnum  = $post ? (string) get_post_meta( $post->ID, '_oc_vendor_number', true ) : '';

		$body  = '<p><strong>' . esc_html__( 'New feedback / suggestion from a vendor', 'owambe-connect-core' ) . '</strong></p>';
		$body .= '<table style="font-family:Arial,sans-serif;font-size:14px;border-collapse:collapse;">';
		if ( $post ) {
			$body .= '<tr><td style="padding:4px 12px 4px 0;color:#6B6361;">' . esc_html__( 'Vendor', 'owambe-connect-core' ) . '</td><td>' . esc_html( $post->post_title ) . ( $vnum ? ' <code>' . esc_html( $vnum ) . '</code>' : '' ) . '</td></tr>';
		}
		if ( $user ) {
			$body .= '<tr><td style="padding:4px 12px 4px 0;color:#6B6361;">' . esc_html__( 'From', 'owambe-connect-core' ) . '</td><td>' . esc_html( $user->display_name ) . ' &lt;' . esc_html( $user->user_email ) . '&gt;</td></tr>';
		}
		$body .= '<tr><td style="padding:4px 12px 4px 0;color:#6B6361;">' . esc_html__( 'Topic', 'owambe-connect-core' ) . '</td><td>' . esc_html( $topic ) . '</td></tr>';
		$body .= '</table>';
		$body .= '<hr><div style="white-space:pre-wrap;font-family:Arial,sans-serif;font-size:14px;">' . esc_html( $message ) . '</div>';

		$extra = ( $user && is_email( $user->user_email ) ) ? [ 'Reply-To: ' . $user->user_email ] : [];
		wp_mail(
			self::notification_recipient(),
			sprintf( __( '[Feedback] %s', 'owambe-connect-core' ), $topic ),
			$body,
			self::admin_headers( $extra )
		);
	}

	/**
	 * Public "request a vendor" enquiry (floating button on every page).
	 * Sender has no account — Reply-To is set to whatever email they typed.
	 */
	public static function vendor_request( array $data ) {
		$name        = (string) ( $data['name']         ?? '' );
		$email       = (string) ( $data['email']        ?? '' );
		$phone       = (string) ( $data['phone']        ?? '' );
		$event_date  = (string) ( $data['event_date']   ?? '' );
		$event_type  = (string) ( $data['event_type']   ?? '' );
		$location    = (string) ( $data['location']     ?? '' );
		$budget      = (string) ( $data['budget']       ?? '' );
		$description = (string) ( $data['description']  ?? '' );

		// Build the body using the shared header/footer template so this
		// notification matches the brand styling of every other admin email
		// (contact form, new application, etc.).
		ob_start();
		$header = OC_TEMPLATE_DIR . 'emails/html-email-header.php';
		$footer = OC_TEMPLATE_DIR . 'emails/html-email-footer.php';
		if ( file_exists( $header ) ) include $header;
		?>
		<h2 style="font-family:Georgia,'Times New Roman',serif;color:#6E0F2C;font-size:20px;margin:0 0 14px;"><?php esc_html_e( 'New vendor request', 'owambe-connect-core' ); ?></h2>
		<p style="color:#3D3735;font-size:14px;line-height:1.55;margin:0 0 16px;"><?php esc_html_e( 'Someone just submitted the floating "Request a Vendor" form. Their details are below.', 'owambe-connect-core' ); ?></p>
		<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="font-family:Arial,sans-serif;font-size:14px;border-collapse:collapse;width:100%;">
			<?php
			$rows = [
				__( 'Name',        'owambe-connect-core' ) => $name,
				__( 'Email',       'owambe-connect-core' ) => $email,
				__( 'Phone',       'owambe-connect-core' ) => $phone,
				__( 'Event date',  'owambe-connect-core' ) => $event_date,
				__( 'Event type',  'owambe-connect-core' ) => $event_type,
				__( 'Location',    'owambe-connect-core' ) => $location,
				__( 'Budget',      'owambe-connect-core' ) => $budget,
			];
			foreach ( $rows as $label => $value ) {
				if ( '' === (string) $value ) continue;
				?>
				<tr>
					<td style="padding:6px 14px 6px 0;color:#6B6361;width:120px;vertical-align:top;"><?php echo esc_html( $label ); ?></td>
					<td style="padding:6px 0;color:#1F1B1A;"><?php echo esc_html( $value ); ?></td>
				</tr>
				<?php
			}
			?>
		</table>
		<?php if ( $description ) : ?>
			<div style="margin-top:18px;padding-top:16px;border-top:1px solid #EFEAE2;">
				<p style="font-family:'Inter',Arial,sans-serif;font-size:11px;font-weight:700;color:#6B6361;text-transform:uppercase;letter-spacing:0.08em;margin:0 0 8px;"><?php esc_html_e( 'What they need', 'owambe-connect-core' ); ?></p>
				<div style="white-space:pre-wrap;font-family:Arial,sans-serif;font-size:14px;color:#1F1B1A;line-height:1.55;"><?php echo esc_html( $description ); ?></div>
			</div>
		<?php endif; ?>
		<?php
		if ( file_exists( $footer ) ) include $footer;
		$body = ob_get_clean();

		// Subject: ASCII-only, no [brackets] or em-dash. Mailgun and several
		// inbox-side spam filters silently drop messages with bracketed
		// subjects or non-ASCII characters in the Subject header. The plain
		// "Vendor request from {Name}" form is what we know gets delivered.
		$subject_name = $name !== '' ? $name : __( 'website visitor', 'owambe-connect-core' );
		$subject      = sprintf( __( 'Vendor request from %s', 'owambe-connect-core' ), $subject_name );

		$extra = is_email( $email ) ? [ 'Reply-To: ' . $email ] : [];
		return wp_mail(
			self::notification_recipient(),
			$subject,
			$body,
			self::admin_headers( $extra )
		);
	}
}
