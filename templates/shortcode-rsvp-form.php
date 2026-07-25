<?php
/**
 * [oc_rsvp_form] — public RSVP form for an oc_event.
 *
 * Posts to admin-post.php (action=oc_submit_rsvp → OC_Event_RSVP::handle()).
 * Expects $event_id, $heading, $subheading in scope (from oc_get_template).
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

$event_id   = isset( $event_id ) ? absint( $event_id ) : 0;
$heading    = ! empty( $heading )    ? $heading    : __( 'RSVP to this event', 'owambe-connect-core' );
$subheading = ! empty( $subheading ) ? $subheading : __( 'Let the host know if you can make it.', 'owambe-connect-core' );

// The form only makes sense for a real, published event.
$event = $event_id ? get_post( $event_id ) : null;
if ( ! $event || 'oc_event' !== $event->post_type || 'publish' !== $event->post_status ) {
	echo '<div class="oc-notice oc-notice--warn">' . esc_html__( 'RSVPs are not available for this event.', 'owambe-connect-core' ) . '</div>';
	return;
}

// Success / error feedback from the handler's redirect (?oc_rsvp=ok|error).
$feedback = isset( $_GET['oc_rsvp'] ) ? sanitize_key( wp_unslash( $_GET['oc_rsvp'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<section class="oc-rsvp">
	<div class="oc-rsvp__card">

		<?php if ( 'ok' === $feedback ) : ?>
			<div class="oc-alert oc-alert--success" role="status">
				<?php esc_html_e( 'Thank you! Your RSVP has been received.', 'owambe-connect-core' ); ?>
			</div>
		<?php elseif ( 'error' === $feedback ) : ?>
			<div class="oc-alert oc-alert--error" role="alert">
				<?php esc_html_e( 'Sorry, we could not save your RSVP. Please check your details and try again.', 'owambe-connect-core' ); ?>
			</div>
		<?php endif; ?>

		<header class="oc-rsvp__head">
			<h2 class="oc-rsvp__title"><?php echo esc_html( $heading ); ?></h2>
			<?php if ( $subheading ) : ?><p class="oc-rsvp__sub"><?php echo esc_html( $subheading ); ?></p><?php endif; ?>
		</header>

		<form class="oc-rsvp__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oc_submit_rsvp" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<?php wp_nonce_field( 'oc_submit_rsvp', 'oc_rsvp_nonce' ); ?>

			<?php // Honeypot: hidden from humans; bots that fill it are rejected server-side. ?>
			<div class="oc-hp" aria-hidden="true">
				<label><?php esc_html_e( 'Leave this field empty', 'owambe-connect-core' ); ?>
					<input type="text" name="oc_hp" value="" tabindex="-1" autocomplete="off" />
				</label>
			</div>

			<div class="oc-rsvp__field">
				<label for="oc-rsvp-name"><?php esc_html_e( 'Your name', 'owambe-connect-core' ); ?> <span class="oc-req" aria-hidden="true">*</span></label>
				<input id="oc-rsvp-name" type="text" name="guest_name" required maxlength="190" autocomplete="name" />
			</div>

			<div class="oc-rsvp__field">
				<label for="oc-rsvp-email"><?php esc_html_e( 'Email', 'owambe-connect-core' ); ?></label>
				<input id="oc-rsvp-email" type="email" name="guest_email" maxlength="190" autocomplete="email" />
			</div>

			<div class="oc-rsvp__field">
				<span class="oc-rsvp__label"><?php esc_html_e( 'Will you attend?', 'owambe-connect-core' ); ?> <span class="oc-req" aria-hidden="true">*</span></span>
				<div class="oc-rsvp__choices" role="radiogroup">
					<label class="oc-rsvp__choice"><input type="radio" name="attending" value="yes" checked /> <span><?php esc_html_e( 'Yes', 'owambe-connect-core' ); ?></span></label>
					<label class="oc-rsvp__choice"><input type="radio" name="attending" value="no" /> <span><?php esc_html_e( 'No', 'owambe-connect-core' ); ?></span></label>
					<label class="oc-rsvp__choice"><input type="radio" name="attending" value="maybe" /> <span><?php esc_html_e( 'Maybe', 'owambe-connect-core' ); ?></span></label>
				</div>
			</div>

			<div class="oc-rsvp__field">
				<label for="oc-rsvp-count"><?php esc_html_e( 'Number of guests (including you)', 'owambe-connect-core' ); ?></label>
				<input id="oc-rsvp-count" type="number" name="guest_count" value="1" min="1" max="99" step="1" inputmode="numeric" />
			</div>

			<div class="oc-rsvp__field">
				<label for="oc-rsvp-note"><?php esc_html_e( 'Note for the host (optional)', 'owambe-connect-core' ); ?></label>
				<textarea id="oc-rsvp-note" name="note" rows="3" maxlength="1000" placeholder="<?php esc_attr_e( 'Dietary needs, questions, well-wishes…', 'owambe-connect-core' ); ?>"></textarea>
			</div>

			<div class="oc-rsvp__actions">
				<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg oc-btn-block"><?php esc_html_e( 'Send RSVP', 'owambe-connect-core' ); ?></button>
			</div>
		</form>
	</div>
</section>

<style>
.oc-rsvp { margin: 1.5rem 0; }
.oc-rsvp__card {
	max-width: 520px;
	margin: 0 auto;
	background: #fff;
	border: 1px solid var(--oc-border, #E4DDD2);
	border-radius: 16px;
	box-shadow: 0 10px 30px rgba(110, 15, 44, 0.06);
	padding: 28px;
}
.oc-rsvp__head { text-align: center; margin: 0 0 1.25rem; }
.oc-rsvp__title { font-size: 1.5rem; font-weight: 700; color: var(--oc-ink, #1F1B1A); margin: 0 0 0.35rem; }
.oc-rsvp__sub { font-size: 14.5px; color: #6B6361; margin: 0; line-height: 1.5; }

.oc-rsvp__field { margin: 0 0 1.05rem; }
.oc-rsvp__field > label,
.oc-rsvp__label { display: block; font-weight: 600; font-size: 13.5px; color: #3A3330; margin: 0 0 6px; }
.oc-req { color: #C0392B; font-weight: 700; }

.oc-rsvp__field input[type="text"],
.oc-rsvp__field input[type="email"],
.oc-rsvp__field input[type="number"],
.oc-rsvp__field textarea {
	width: 100%;
	box-sizing: border-box;
	padding: 12px 14px;
	font-size: 15px;
	color: #1F1B1A;
	background: #FAF8F5;
	border: 1px solid var(--oc-border, #E4DDD2);
	border-radius: 10px;
	transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.oc-rsvp__field input:focus,
.oc-rsvp__field textarea:focus {
	outline: none;
	background: #fff;
	border-color: var(--oc-burgundy, #6E0F2C);
	box-shadow: 0 0 0 3px rgba(110, 15, 44, 0.10);
}

.oc-rsvp__choices { display: flex; gap: 10px; flex-wrap: wrap; }
.oc-rsvp__choice {
	display: inline-flex;
	align-items: center;
	gap: 7px;
	padding: 9px 16px;
	background: #FAF8F5;
	border: 1px solid var(--oc-border, #E4DDD2);
	border-radius: 10px;
	font-weight: 600;
	font-size: 14px;
	color: #3A3330;
	cursor: pointer;
	transition: border-color 0.15s ease, background 0.15s ease;
}
.oc-rsvp__choice:hover { border-color: #C9A961; }
.oc-rsvp__choice input { accent-color: var(--oc-burgundy, #6E0F2C); }

.oc-rsvp__actions { margin-top: 0.5rem; }

/* Honeypot — visually hidden but present in the DOM for bots. */
.oc-hp {
	position: absolute !important;
	left: -9999px !important;
	top: auto;
	width: 1px;
	height: 1px;
	overflow: hidden;
}
</style>
