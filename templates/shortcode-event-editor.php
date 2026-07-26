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

// Saved attachments.
$cover_id      = $event_id ? (int) get_post_meta( $event_id, OC_Event_Editor::META_COVER, true ) : 0;
$invitation_id = $event_id ? (int) get_post_meta( $event_id, OC_Event_Editor::META_INVITATION, true ) : 0;
$gallery_ids   = $event_id ? array_map( 'intval', (array) get_post_meta( $event_id, OC_Event_Editor::META_GALLERY, true ) ) : [];

// Render one preview tile (image thumbnail, or a non-image doc card for PDFs).
$render_preview = static function ( $att_id, $field_name ) {
	$att_id = (int) $att_id;
	if ( ! $att_id || 'attachment' !== get_post_type( $att_id ) ) {
		return '';
	}
	$is_image = (bool) wp_attachment_is_image( $att_id );
	$name     = get_the_title( $att_id );
	$name     = '' !== $name ? $name : basename( (string) wp_get_attachment_url( $att_id ) );
	ob_start();
	?>
	<div class="oc-eved__preview<?php echo $is_image ? '' : ' oc-eved__preview--doc'; ?>" data-id="<?php echo esc_attr( $att_id ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $att_id ); ?>" />
		<?php if ( $is_image ) :
			$src = wp_get_attachment_image_src( $att_id, 'medium' );
			?>
			<span class="oc-eved__preview-media"><img src="<?php echo esc_url( $src ? $src[0] : (string) wp_get_attachment_url( $att_id ) ); ?>" alt="" /></span>
		<?php else : ?>
			<span class="oc-eved__preview-media oc-eved__preview-media--doc" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
			</span>
			<span class="oc-eved__preview-name"><?php echo esc_html( $name ); ?></span>
		<?php endif; ?>
		<button type="button" class="oc-eved__preview-remove" aria-label="<?php esc_attr_e( 'Remove', 'owambe-connect-core' ); ?>">&times;</button>
	</div>
	<?php
	return ob_get_clean();
};
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

			<!-- Cover photo (single image). -->
			<div class="oc-eved__section">
				<h2 class="oc-eved__section-title"><?php esc_html_e( 'Cover photo', 'owambe-connect-core' ); ?></h2>
				<div class="oc-eved__upload" data-oc-upload data-slot="cover" data-multi="0" data-name="cover_id">
					<div class="oc-eved__previews"><?php echo $render_preview( $cover_id, 'cover_id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<label class="oc-eved__uploadbtn">
						<input type="file" accept="image/jpeg,image/png,image/webp" hidden />
						<span><?php esc_html_e( '+ Add cover photo', 'owambe-connect-core' ); ?></span>
					</label>
					<p class="oc-eved__uploadmsg" aria-live="polite"></p>
				</div>
			</div>

			<!-- Gallery (multiple images). -->
			<div class="oc-eved__section">
				<h2 class="oc-eved__section-title"><?php esc_html_e( 'Photo gallery', 'owambe-connect-core' ); ?></h2>
				<div class="oc-eved__upload" data-oc-upload data-slot="gallery" data-multi="1" data-name="gallery_ids[]">
					<div class="oc-eved__previews oc-eved__previews--grid">
						<?php foreach ( $gallery_ids as $gid ) { echo $render_preview( $gid, 'gallery_ids[]' ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<label class="oc-eved__uploadbtn">
						<input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden />
						<span><?php esc_html_e( '+ Add photos', 'owambe-connect-core' ); ?></span>
					</label>
					<p class="oc-eved__uploadmsg" aria-live="polite"></p>
				</div>
			</div>

			<!-- Invitation card (image or PDF). -->
			<div class="oc-eved__section">
				<h2 class="oc-eved__section-title"><?php esc_html_e( 'Invitation card', 'owambe-connect-core' ); ?></h2>
				<div class="oc-eved__upload" data-oc-upload data-slot="invitation" data-multi="0" data-name="invitation_id">
					<div class="oc-eved__previews"><?php echo $render_preview( $invitation_id, 'invitation_id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<label class="oc-eved__uploadbtn">
						<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" hidden />
						<span><?php esc_html_e( '+ Add invitation (image or PDF)', 'owambe-connect-core' ); ?></span>
					</label>
					<p class="oc-eved__uploadmsg" aria-live="polite"></p>
				</div>
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

/* ── Uploads ──────────────────────────────────────────────────────────── */
.oc-eved__upload{display:flex;flex-direction:column;gap:12px;}
.oc-eved__previews{display:flex;flex-wrap:wrap;gap:12px;}
.oc-eved__previews:empty{display:none;}
.oc-eved__preview{position:relative;width:120px;border:1px solid var(--oc-border,#E4DDD2);border-radius:12px;overflow:hidden;background:#FAF8F5;}
.oc-eved__preview-media{display:flex;align-items:center;justify-content:center;width:120px;height:96px;background:#F1E9EA;color:var(--oc-b);}
.oc-eved__preview-media img{width:100%;height:100%;object-fit:cover;display:block;}
.oc-eved__preview-media--doc{flex-direction:column;}
.oc-eved__preview-name{display:block;padding:6px 8px;font-size:11.5px;color:#6B6361;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.oc-eved__preview-remove{position:absolute;top:5px;right:5px;width:22px;height:22px;line-height:1;border:0;border-radius:50%;background:rgba(31,27,26,.62);color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.oc-eved__preview-remove:hover{background:rgba(178,45,46,.9);}
.oc-eved__uploadbtn{align-self:flex-start;display:inline-flex;align-items:center;padding:9px 16px;border:1px dashed rgba(110,15,44,.4);border-radius:10px;background:rgba(110,15,44,.03);color:var(--oc-b);font-size:13.5px;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s;}
.oc-eved__uploadbtn:hover{background:rgba(110,15,44,.07);border-color:var(--oc-b);}
.oc-eved__uploadmsg{margin:0;font-size:13px;color:#8a5a0a;}
.oc-eved__uploadmsg:empty{display:none;}

/* Stack the date/time pair on narrow screens. */
@media (max-width:560px){
	.oc-eved__fields{grid-template-columns:1fr;}
	.oc-eved__field--half{grid-column:1 / -1;}
	.oc-eved__section{padding:22px 20px;}
}
</style>

<script>
( function () {
	var CFG = {
		ajax:   <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
		action: <?php echo wp_json_encode( OC_Event_Editor::ACTION_UPLOAD ); ?>,
		nonce:  <?php echo wp_json_encode( wp_create_nonce( OC_Event_Editor::ACTION_UPLOAD ) ); ?>
	};
	var root = document.querySelector( '.oc-eved' );
	if ( ! root ) { return; }

	// Delegated remove — works for server-rendered and freshly-added tiles.
	root.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.oc-eved__preview-remove' ) : null;
		if ( ! btn ) { return; }
		var tile = btn.closest( '.oc-eved__preview' );
		if ( tile ) { tile.parentNode.removeChild( tile ); }
	} );

	root.querySelectorAll( '[data-oc-upload]' ).forEach( function ( zone ) {
		var multi     = zone.getAttribute( 'data-multi' ) === '1';
		var slot      = zone.getAttribute( 'data-slot' );
		var fieldName = zone.getAttribute( 'data-name' );
		var input     = zone.querySelector( 'input[type=file]' );
		var previews  = zone.querySelector( '.oc-eved__previews' );
		var msg       = zone.querySelector( '.oc-eved__uploadmsg' );
		if ( ! input ) { return; }

		input.addEventListener( 'change', function () {
			var files = Array.prototype.slice.call( input.files || [] );
			input.value = '';
			if ( ! multi ) { files = files.slice( 0, 1 ); }
			files.forEach( uploadOne );
		} );

		function uploadOne( file ) {
			if ( ! multi ) { previews.innerHTML = ''; } // single slot: replace.
			msg.textContent = '<?php echo esc_js( __( 'Uploading…', 'owambe-connect-core' ) ); ?>';
			var fd = new FormData();
			fd.append( 'action', CFG.action );
			fd.append( '_nonce', CFG.nonce );
			fd.append( 'slot', slot );
			fd.append( 'file', file );
			fetch( CFG.ajax, { method: 'POST', credentials: 'same-origin', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( ! res || ! res.success ) {
						msg.textContent = ( res && res.data && res.data.message ) || '<?php echo esc_js( __( 'Upload failed.', 'owambe-connect-core' ) ); ?>';
						return;
					}
					msg.textContent = '';
					previews.appendChild( makeTile( res.data ) );
				} )
				.catch( function () { msg.textContent = '<?php echo esc_js( __( 'Upload failed.', 'owambe-connect-core' ) ); ?>'; } );
		}

		function makeTile( d ) {
			var el = document.createElement( 'div' );
			el.className = 'oc-eved__preview' + ( d.is_image ? '' : ' oc-eved__preview--doc' );
			el.setAttribute( 'data-id', d.id );

			var hid = document.createElement( 'input' );
			hid.type = 'hidden'; hid.name = fieldName; hid.value = d.id;
			el.appendChild( hid );

			var media = document.createElement( 'span' );
			media.className = 'oc-eved__preview-media' + ( d.is_image ? '' : ' oc-eved__preview-media--doc' );
			if ( d.is_image ) {
				var img = document.createElement( 'img' );
				img.src = d.thumb_url || d.url; img.alt = '';
				media.appendChild( img );
			} else {
				media.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>';
			}
			el.appendChild( media );

			if ( ! d.is_image ) {
				var nm = document.createElement( 'span' );
				nm.className = 'oc-eved__preview-name';
				nm.textContent = d.filename || 'Document';
				el.appendChild( nm );
			}

			var rm = document.createElement( 'button' );
			rm.type = 'button'; rm.className = 'oc-eved__preview-remove';
			rm.setAttribute( 'aria-label', 'Remove' ); rm.innerHTML = '&times;';
			el.appendChild( rm );

			return el;
		}
	} );
} )();
</script>
