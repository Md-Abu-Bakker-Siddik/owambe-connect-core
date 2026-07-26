<?php
/**
 * [oc_event_editor] — client-facing form to create/edit an event page.
 * Expects $event (WP_Post|null) in scope. Posts to admin-post.php (oc_save_event).
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

$event    = isset( $event ) && $event instanceof WP_Post ? $event : null;
$event_id = $event ? (int) $event->ID : 0;
$sections = oc_event_fields();

$notice = isset( $_GET['oc_notice'] ) ? sanitize_key( wp_unslash( $_GET['oc_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error  = isset( $_GET['oc_error'] )  ? sanitize_text_field( wp_unslash( $_GET['oc_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Current value for a field key.
$val = static function ( $key ) use ( $event_id ) {
	return $event_id ? (string) get_post_meta( $event_id, OC_Event_Editor::meta_key( $key ), true ) : '';
};
$title_val = $event ? $event->post_title : '';
$view_url  = ( $event_id && 'publish' === get_post_status( $event_id ) ) ? get_permalink( $event_id ) : '';
?>
<section class="oc-eved">
	<div class="oc-eved__inner">

		<header class="oc-eved__head">
			<h1 class="oc-eved__title"><?php echo $event_id ? esc_html__( 'Your event page', 'owambe-connect-core' ) : esc_html__( 'Create your event page', 'owambe-connect-core' ); ?></h1>
			<p class="oc-eved__sub"><?php esc_html_e( 'Fill in the sections below. Your page is private — only people you share the link with can see it.', 'owambe-connect-core' ); ?></p>
			<?php if ( $view_url ) : ?>
				<a class="oc-eved__view" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'View your event page →', 'owambe-connect-core' ); ?>
				</a>
			<?php endif; ?>
		</header>

		<?php if ( 'saved' === $notice ) : ?>
			<div class="oc-alert oc-alert--success" role="status"><?php esc_html_e( 'Saved. Your event page is up to date.', 'owambe-connect-core' ); ?></div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="oc-alert oc-alert--error" role="alert"><?php echo esc_html( $error ); ?></div>
		<?php endif; ?>

		<form class="oc-eved__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( OC_Event_Editor::ACTION ); ?>" />
			<?php wp_nonce_field( OC_Event_Editor::ACTION, OC_Event_Editor::NONCE_FIELD ); ?>
			<div class="oc-hp" aria-hidden="true">
				<label><?php esc_html_e( 'Leave this field empty', 'owambe-connect-core' ); ?>
					<input type="text" name="oc_hp" value="" tabindex="-1" autocomplete="off" />
				</label>
			</div>

			<!-- Event name lives on the post title. -->
			<div class="oc-eved__section">
				<h2 class="oc-eved__section-title"><?php esc_html_e( 'Event name', 'owambe-connect-core' ); ?></h2>
				<div class="oc-eved__field">
					<label for="oc-ev-title"><?php esc_html_e( 'Event name', 'owambe-connect-core' ); ?> <span class="oc-req" aria-hidden="true">*</span></label>
					<input id="oc-ev-title" type="text" name="event_title" required maxlength="160" value="<?php echo esc_attr( $title_val ); ?>" placeholder="<?php echo esc_attr__( 'e.g. Ada and Tunde&#8217;s Wedding', 'owambe-connect-core' ); ?>" />
				</div>
			</div>

			<?php foreach ( $sections as $skey => $section ) : ?>
				<div class="oc-eved__section">
					<h2 class="oc-eved__section-title"><?php echo esc_html( $section['title'] ); ?></h2>
					<?php foreach ( $section['fields'] as $fkey => $spec ) :
						$id   = 'oc-ev-' . esc_attr( $fkey );
						$type = isset( $spec['type'] ) ? $spec['type'] : 'text';
						$cur  = $val( $fkey );
						?>
						<div class="oc-eved__field">
							<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $spec['label'] ); ?></label>
							<?php if ( 'textarea' === $type ) : ?>
								<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $fkey ); ?>" rows="4"><?php echo esc_textarea( $cur ); ?></textarea>
							<?php else :
								$input_type = in_array( $type, [ 'date', 'time', 'url' ], true ) ? $type : 'text';
								?>
								<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( $fkey ); ?>" value="<?php echo esc_attr( $cur ); ?>" />
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>

			<div class="oc-eved__section oc-eved__section--soon">
				<h2 class="oc-eved__section-title"><?php esc_html_e( 'Photos & invitation', 'owambe-connect-core' ); ?></h2>
				<p class="oc-eved__soon"><?php esc_html_e( 'Cover photo, gallery and invitation uploads are coming next.', 'owambe-connect-core' ); ?></p>
			</div>

			<div class="oc-eved__actions">
				<button type="submit" class="oc-btn oc-btn-primary oc-btn-lg"><?php echo $event_id ? esc_html__( 'Save changes', 'owambe-connect-core' ) : esc_html__( 'Create event page', 'owambe-connect-core' ); ?></button>
			</div>
		</form>
	</div>
</section>

<style>
.oc-eved{--oc-b:#6E0F2C;--oc-g:#C9A961;margin:1.5rem 0;}
.oc-eved__inner{max-width:720px;margin:0 auto;padding:0 1rem;}
.oc-eved__head{margin:0 0 1.5rem;}
.oc-eved__title{font-size:clamp(1.6rem,4vw,2.2rem);color:var(--oc-b);margin:0 0 .35rem;font-weight:700;}
.oc-eved__sub{color:#6B6361;margin:0;line-height:1.55;}
.oc-eved__view{display:inline-block;margin-top:.6rem;color:var(--oc-b);font-weight:600;text-decoration:none;}
.oc-eved__view:hover{text-decoration:underline;}
.oc-eved__section{background:#fff;border:1px solid var(--oc-border,#E4DDD2);border-radius:14px;padding:20px 22px;margin:0 0 1rem;box-shadow:0 6px 18px rgba(110,15,44,.05);}
.oc-eved__section-title{font-size:1.05rem;color:var(--oc-b);margin:0 0 1rem;font-weight:700;}
.oc-eved__field{margin:0 0 1rem;}
.oc-eved__field:last-child{margin-bottom:0;}
.oc-eved__field label{display:block;font-weight:600;font-size:13.5px;color:#3A3330;margin:0 0 6px;}
.oc-req{color:#C0392B;font-weight:700;}
.oc-eved__field input,.oc-eved__field textarea{width:100%;box-sizing:border-box;padding:11px 13px;font-size:15px;color:#1F1B1A;background:#FAF8F5;border:1px solid var(--oc-border,#E4DDD2);border-radius:10px;transition:border-color .15s,box-shadow .15s,background .15s;}
.oc-eved__field input:focus,.oc-eved__field textarea:focus{outline:none;background:#fff;border-color:var(--oc-b);box-shadow:0 0 0 3px rgba(110,15,44,.10);}
.oc-eved__section--soon{background:#FAF7F2;border-style:dashed;}
.oc-eved__soon{margin:0;color:#9A938C;font-size:14px;}
.oc-eved__actions{position:sticky;bottom:0;background:linear-gradient(180deg,transparent,var(--oc-ground,#FBF8F5) 40%);padding:1rem 0;margin-top:.5rem;}
.oc-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden;}
.oc-alert{padding:11px 15px;border-radius:10px;margin:0 0 1rem;font-size:14px;}
.oc-alert--success{background:#E7F2EB;color:#1e7a42;border:1px solid #b8dcc6;}
.oc-alert--error{background:#fdecea;color:#b32d2e;border:1px solid #f5c6c2;}
</style>
