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

		<header class="oc-eved__hero">
			<span class="oc-eved__eyebrow"><?php esc_html_e( 'Event page', 'owambe-connect-core' ); ?></span>
			<h1 class="oc-eved__title"><?php echo esc_html( ( $event_id && '' !== trim( (string) $title_val ) ) ? $title_val : __( 'Create your event page', 'owambe-connect-core' ) ); ?></h1>
			<p class="oc-eved__sub"><?php esc_html_e( 'Fill in the sections below. Your page is private — only people you share the link with can see it.', 'owambe-connect-core' ); ?></p>
			<?php if ( $view_url ) : ?>
				<a class="oc-eved__view" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">
					<span><?php esc_html_e( 'View your event page', 'owambe-connect-core' ); ?></span>
					<svg class="oc-eved__view-arrow" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
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
					<div class="oc-eved__fields">
						<?php foreach ( $section['fields'] as $fkey => $spec ) :
							$id   = 'oc-ev-' . esc_attr( $fkey );
							$type = isset( $spec['type'] ) ? $spec['type'] : 'text';
							$cur  = $val( $fkey );
							// Event date + start time sit side-by-side in the basics row.
							$half = ( 'basics' === $skey && in_array( $fkey, [ 'date', 'time' ], true ) );
							?>
							<div class="oc-eved__field<?php echo $half ? ' oc-eved__field--half' : ''; ?>">
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
				</div>
			<?php endforeach; ?>

			<div class="oc-eved__section oc-eved__section--soon">
				<h2 class="oc-eved__section-title"><?php esc_html_e( 'Photos & invitation', 'owambe-connect-core' ); ?></h2>
				<p class="oc-eved__soon"><?php esc_html_e( 'Cover photo, gallery and invitation uploads are coming next.', 'owambe-connect-core' ); ?></p>
			</div>

			<div class="oc-eved__actions">
				<button type="submit" class="oc-eved__save"><?php echo $event_id ? esc_html__( 'Save changes', 'owambe-connect-core' ) : esc_html__( 'Create event page', 'owambe-connect-core' ); ?></button>
			</div>
		</form>
	</div>
</section>

<style>
.oc-eved{--oc-b:#6E0F2C;--oc-g:#C9A961;margin:2rem 0;
	font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}
/* Centred, tight reading column. */
.oc-eved__inner{max-width:820px;margin:0 auto;padding:0 1.25rem;}
.oc-eved__hero{text-align:left;margin:0 0 1.75rem;}
.oc-eved__eyebrow{display:inline-block;font-size:11.5px;letter-spacing:.18em;text-transform:uppercase;font-weight:700;color:var(--oc-g);margin:0 0 .5rem;}
.oc-eved__title{font-family:Georgia,"Playfair Display","Times New Roman",serif;color:var(--oc-b);font-size:clamp(1.9rem,5vw,2.7rem);line-height:1.1;font-weight:700;margin:0 0 .5rem;max-width:24ch;letter-spacing:-.01em;text-wrap:balance;}
.oc-eved__sub{color:#6B6361;margin:0;line-height:1.6;font-size:15px;max-width:56ch;}
.oc-eved__view{display:inline-flex;align-items:center;justify-content:center;gap:8px;margin-top:1rem;padding:8px 10px 10px 16px;color:var(--oc-b);font-weight:600;font-size:13.5px;line-height:1;text-decoration:none;border:1px solid rgba(110,15,44,.22);border-radius:10px;background:rgba(110,15,44,.04);transition:background .15s,border-color .15s;}
.oc-eved__view:hover{background:rgba(110,15,44,.09);border-color:rgba(110,15,44,.4);}
.oc-eved__view-arrow{flex:0 0 auto;display:inline-block;transform:rotate(-45deg);transition:transform 0.2s;position:relative;top:2px;}
.oc-eved__view:hover .oc-eved__view-arrow{transform:rotate(-45deg) translate(2px,-2px);}

/* Section cards. */
.oc-eved__section{background:#fff;border:1px solid var(--oc-border,#E4DDD2);border-radius:16px;
	padding:26px 28px;margin:0 0 1.35rem;box-shadow:0 8px 24px rgba(110,15,44,.05);}
/* Clean tracked label — sans, small, not a serif heading. */
.oc-eved__section-title{font-family:inherit;font-size:11.5px;color:#9A6B77;margin:0 0 1.15rem;
	font-weight:700;letter-spacing:.1em;text-transform:uppercase;}

/* Field grid — two columns; fields span full width unless flagged --half. */
.oc-eved__fields{display:grid;grid-template-columns:1fr 1fr;gap:1.1rem 1.25rem;}
.oc-eved__fields > .oc-eved__field{grid-column:1 / -1;margin:0;}
.oc-eved__field--half{grid-column:auto;}

.oc-eved__field label{display:block;font-weight:600;font-size:13px;color:#3A3330;margin:0 0 7px;letter-spacing:.01em;}
.oc-req{color:#C0392B;font-weight:700;}
.oc-eved__field input,.oc-eved__field textarea{width:100%;box-sizing:border-box;padding:12px 15px;
	font-size:15px;line-height:1.4;color:#1F1B1A;background:#FAF8F5;border:1px solid var(--oc-border,#E4DDD2);
	border-radius:11px;transition:border-color .15s,box-shadow .15s,background .15s;}
.oc-eved__field textarea{resize:vertical;min-height:96px;}
.oc-eved__field input:focus,.oc-eved__field textarea:focus{outline:none;background:#fff;
	border-color:var(--oc-b);box-shadow:0 0 0 3px rgba(110,15,44,.10);}

/* Event-name field sits directly in its section (outside the grid) — keep it tidy. */
.oc-eved__section > .oc-eved__field{margin:0;}

.oc-eved__section--soon{background:#FAF7F2;border-style:dashed;}
.oc-eved__soon{margin:0;color:#9A938C;font-size:14px;}

/* Save button — left-aligned with the cards, self-contained primary style. */
.oc-eved__actions{display:flex;justify-content:flex-start;margin-top:24px;}
.oc-eved__save{display:inline-flex;align-items:center;justify-content:center;min-width:180px;
	padding:13px 30px;background:#600E26;color:#fff;font-weight:600;font-size:15px;line-height:1;
	border:0;border-radius:11px;cursor:pointer;
	transition:background .18s ease,box-shadow .18s ease,transform .06s ease;
	box-shadow:0 6px 16px rgba(96,14,38,.22);}
.oc-eved__save:hover{background:#7A1332;box-shadow:0 9px 22px rgba(96,14,38,.30);}
.oc-eved__save:active{transform:translateY(1px);}
.oc-eved__save:focus-visible{outline:2px solid var(--oc-g);outline-offset:2px;}

.oc-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden;}
.oc-alert{padding:12px 16px;border-radius:11px;margin:0 0 1.25rem;font-size:14px;}
.oc-alert--success{background:#E7F2EB;color:#1e7a42;border:1px solid #b8dcc6;}
.oc-alert--error{background:#fdecea;color:#b32d2e;border:1px solid #f5c6c2;}

/* Stack the date/time pair on narrow screens. */
@media (max-width:560px){
	.oc-eved__fields{grid-template-columns:1fr;}
	.oc-eved__field--half{grid-column:1 / -1;}
	.oc-eved__section{padding:22px 20px;}
}
</style>
