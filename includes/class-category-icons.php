<?php
/**
 * Per-category icon picker — Vendors → Categories.
 *
 * Lets admin set a custom icon for every taxonomy term in two flavours:
 *
 *   1. Emoji (or short glyph) — single character / grapheme. Used by the
 *      hero-search dropdown because `<option>` can't render images.
 *   2. Image (SVG / PNG) — chosen from the media library. Used on the
 *      Browse-by-Category cards for premium look.
 *
 * Resolution priority at render time (handled by oc_get_category_icon):
 *   image attachment → emoji term-meta → hardcoded default for known slug
 *   → generic star fallback.
 *
 * Why two fields and not just "icon" — letting the admin choose per term
 * means new categories (e.g. "Florist") get an icon without a plugin
 * release, and existing slugs can override the hardcoded defaults.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Category_Icons {

	const META_EMOJI    = '_oc_cat_icon_emoji';
	const META_IMAGE_ID = '_oc_cat_icon_image_id';

	public function register() {
		// Form fields on the Add / Edit screens.
		add_action( OC_TAX . '_add_form_fields',  [ $this, 'add_form_fields' ] );
		add_action( OC_TAX . '_edit_form_fields', [ $this, 'edit_form_fields' ], 10, 2 );

		// Save handlers.
		add_action( 'created_' . OC_TAX, [ $this, 'save_meta' ] );
		add_action( 'edited_'  . OC_TAX, [ $this, 'save_meta' ] );

		// Icon column in the category list table.
		add_filter( 'manage_edit-' . OC_TAX . '_columns',          [ $this, 'list_columns' ] );
		add_filter( 'manage_' . OC_TAX . '_custom_column',         [ $this, 'list_column_value' ], 10, 3 );

		// Media library scripts on the taxonomy pages only.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Brand the WP-native taxonomy edit screens so Vendors → Categories
		// feels like the rest of the OC admin (burgundy + Georgia headings,
		// soft cream backgrounds, gold accents).
		add_action( 'admin_head-edit-tags.php', [ $this, 'inject_brand_css' ] );
		add_action( 'admin_head-term.php',      [ $this, 'inject_brand_css' ] );
	}

	// ─────────────────────────────────────────────────────────
	//  Asset loading
	// ─────────────────────────────────────────────────────────
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, [ 'edit-tags.php', 'term.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->taxonomy !== OC_TAX ) {
			return;
		}
		wp_enqueue_media();
	}

	/**
	 * Inject OC-branded CSS on the taxonomy add/edit screens. Only emits
	 * markup when the current screen is for our taxonomy — admin doesn't
	 * see this on the default WP categories / tags.
	 */
	public function inject_brand_css() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->taxonomy !== OC_TAX ) {
			return;
		}
		?>
		<style id="oc-cat-admin-brand">
			/* Page heading + intro */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > h1,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #wpbody-content > .wrap > h1 {
				font-family: Georgia, serif;
				color: #6E0F2C;
				font-size: 1.7rem;
				margin: 8px 0 4px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap .page-title-action {
				background: #6E0F2C;
				border-color: #6E0F2C;
				color: #fff;
				border-radius: 6px;
				padding: 6px 14px;
				font-weight: 600;
				font-size: 12px;
				text-transform: uppercase;
				letter-spacing: .06em;
				box-shadow: 0 2px 4px rgba(110,15,44,.16);
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap .page-title-action:hover {
				background: #4A0A1E;
				border-color: #4A0A1E;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap .subtitle {
				color: #6B6361;
			}

			/* Add / Edit form columns — give them a card feel */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .col-wrap {
				background: #fff;
				border: 1px solid #E4DDD2;
				border-radius: 10px;
				padding: 22px 24px;
				margin-bottom: 18px;
				box-shadow: 0 1px 3px rgba(31,27,26,.04);
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .col-wrap h2 {
				font-family: Georgia, serif;
				color: #6E0F2C;
				font-size: 1.15rem;
				margin: 0 0 14px;
				padding-bottom: 10px;
				border-bottom: 2px solid #C9A961;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-field label,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-table th label {
				font-weight: 600;
				color: #1F1B1A;
				font-size: 13.5px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-field p,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-table .description {
				color: #6B6361;
				font-size: 12.5px;
				font-style: normal;
				margin-top: 5px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-field input[type="text"],
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-field textarea,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-field select,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-table input[type="text"],
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-table textarea,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-table select {
				border: 1px solid #E4DDD2;
				border-radius: 6px;
				padding: 9px 12px;
				font-size: 14px;
				background: #fff;
				transition: border-color .15s ease, box-shadow .15s ease;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-field input[type="text"]:focus,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-field textarea:focus,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-field select:focus,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-table input[type="text"]:focus,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-table textarea:focus,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .form-table select:focus {
				border-color: #6E0F2C;
				box-shadow: 0 0 0 3px rgba(110,15,44,.12);
				outline: none;
			}

			/* Primary submit buttons */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .button-primary,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> input[type="submit"].button-primary {
				background: #6E0F2C !important;
				border-color: #6E0F2C !important;
				color: #fff !important;
				border-radius: 6px;
				padding: 8px 22px;
				font-weight: 600;
				box-shadow: 0 2px 4px rgba(110,15,44,.16);
				text-shadow: none;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .button-primary:hover,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> input[type="submit"].button-primary:hover {
				background: #4A0A1E !important;
				border-color: #4A0A1E !important;
				box-shadow: 0 4px 8px rgba(110,15,44,.22);
			}

			/* List table — branded header + row hover */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table {
				border: 1px solid #E4DDD2;
				border-radius: 10px;
				overflow: hidden;
				background: #fff;
				box-shadow: 0 1px 3px rgba(31,27,26,.04);
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table thead th,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table tfoot th {
				background: #FAF7F2;
				color: #6B6361;
				font-size: 11px;
				text-transform: uppercase;
				letter-spacing: .08em;
				font-weight: 700;
				border-bottom: 2px solid #C9A961;
				padding: 12px 14px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table thead th a,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table tfoot th a {
				color: #1F1B1A;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table tbody tr:hover {
				background: #FAF7F2;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table td {
				padding: 12px 14px;
				vertical-align: middle;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table .row-title {
				color: #6E0F2C;
				font-weight: 600;
				font-size: 14px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table .row-title:hover {
				color: #4A0A1E;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> table.wp-list-table .row-actions a:hover {
				color: #6E0F2C;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .column-oc_cat_icon {
				width: 64px;
				text-align: center;
			}

			/* Pagination + bulk actions row */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .tablenav .button,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .tablenav .button:hover {
				border-color: #E4DDD2;
				color: #6B6361;
				background: #fff;
				border-radius: 4px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .tablenav .button:hover {
				border-color: #6E0F2C;
				color: #6E0F2C;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .tablenav .tablenav-pages .current,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .tablenav .tablenav-pages a {
				border-color: #E4DDD2;
				border-radius: 4px;
				padding: 4px 9px;
				color: #6B6361;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .tablenav .tablenav-pages .current {
				background: #6E0F2C;
				border-color: #6E0F2C;
				color: #fff;
				font-weight: 600;
			}

			/* Search box on the right of the table */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #posts-filter .search-box input[type="search"] {
				border: 1px solid #E4DDD2;
				border-radius: 6px;
				padding: 6px 12px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #posts-filter .search-box input[type="search"]:focus {
				border-color: #6E0F2C;
				box-shadow: 0 0 0 3px rgba(110,15,44,.12);
				outline: none;
			}

			/* The icon picker preview field on the edit form */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .oc-cat-icon-preview {
				background: #FAF7F2 !important;
				border-color: #E4DDD2 !important;
				border-radius: 8px !important;
			}
		</style>
		<?php
	}

	// ─────────────────────────────────────────────────────────
	//  Form fields — "Add New Category"
	// ─────────────────────────────────────────────────────────
	public function add_form_fields( $taxonomy ) {
		?>
		<div class="form-field oc-cat-icon-field">
			<label for="oc_cat_icon_emoji"><?php esc_html_e( 'Icon (emoji / glyph)', 'owambe-connect-core' ); ?></label>
			<input id="oc_cat_icon_emoji" type="text" name="<?php echo esc_attr( self::META_EMOJI ); ?>" maxlength="8" value="" style="width:auto;font-size:18px;text-align:center;width:60px;"/>
			<p><?php esc_html_e( 'Paste a single emoji (e.g. 🍽️, 📸, 💐). Shown in the hero-search dropdown and as a fallback on the Browse-by-Category grid.', 'owambe-connect-core' ); ?></p>
		</div>
		<div class="form-field oc-cat-icon-field oc-cat-icon-field--image">
			<label><?php esc_html_e( 'Icon image (optional)', 'owambe-connect-core' ); ?></label>
			<div class="oc-cat-icon-picker" data-oc-cat-icon-picker>
				<input type="hidden" name="<?php echo esc_attr( self::META_IMAGE_ID ); ?>" value="" data-oc-cat-icon-input/>
				<div class="oc-cat-icon-preview" data-oc-cat-icon-preview style="display:none;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:8px;margin:6px 0;width:96px;height:96px;display:flex;align-items:center;justify-content:center;"></div>
				<button type="button" class="button" data-oc-cat-icon-choose><?php esc_html_e( 'Choose image', 'owambe-connect-core' ); ?></button>
				<button type="button" class="button-link-delete" data-oc-cat-icon-remove style="margin-left:8px;display:none;color:#b32d2e;"><?php esc_html_e( 'Remove', 'owambe-connect-core' ); ?></button>
			</div>
			<p><?php esc_html_e( 'Square SVG or PNG recommended. Used on the Browse-by-Category grid cards. Falls back to the emoji above if not set.', 'owambe-connect-core' ); ?></p>
		</div>
		<?php $this->print_picker_script(); ?>
		<?php
	}

	// ─────────────────────────────────────────────────────────
	//  Form fields — "Edit Category"
	// ─────────────────────────────────────────────────────────
	public function edit_form_fields( $term, $taxonomy ) {
		$emoji    = (string) get_term_meta( $term->term_id, self::META_EMOJI,    true );
		$image_id = (int)    get_term_meta( $term->term_id, self::META_IMAGE_ID, true );
		$image_html = '';
		$has_image  = false;
		if ( $image_id && wp_attachment_is_image( $image_id ) ) {
			$has_image  = true;
			$image_html = wp_get_attachment_image( $image_id, [ 80, 80 ], false, [ 'style' => 'width:80px;height:80px;object-fit:contain;' ] );
		}

		// Show the hardcoded fallback so the admin sees what's rendering today.
		$fallback_emoji = self::default_emoji_for_slug( $term->slug );
		?>
		<tr class="form-field oc-cat-icon-field">
			<th scope="row"><label for="oc_cat_icon_emoji"><?php esc_html_e( 'Icon (emoji / glyph)', 'owambe-connect-core' ); ?></label></th>
			<td>
				<input id="oc_cat_icon_emoji" type="text" name="<?php echo esc_attr( self::META_EMOJI ); ?>" maxlength="8" value="<?php echo esc_attr( $emoji ); ?>" style="font-size:20px;text-align:center;width:70px;"/>
				<?php if ( '' === $emoji && '' !== $fallback_emoji ) : ?>
					<span style="margin-left:10px;color:#6B6361;font-size:13px;">
						<?php
						/* translators: %s: emoji glyph */
						printf( esc_html__( 'Currently using built-in default: %s — type something here to override.', 'owambe-connect-core' ), esc_html( $fallback_emoji ) );
						?>
					</span>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Single emoji shown in the hero-search dropdown and as a fallback on the category grid.', 'owambe-connect-core' ); ?></p>
			</td>
		</tr>
		<tr class="form-field oc-cat-icon-field oc-cat-icon-field--image">
			<th scope="row"><?php esc_html_e( 'Icon image (optional)', 'owambe-connect-core' ); ?></th>
			<td>
				<div class="oc-cat-icon-picker" data-oc-cat-icon-picker>
					<input type="hidden" name="<?php echo esc_attr( self::META_IMAGE_ID ); ?>" value="<?php echo esc_attr( $image_id ); ?>" data-oc-cat-icon-input/>
					<div class="oc-cat-icon-preview" data-oc-cat-icon-preview style="<?php echo $has_image ? '' : 'display:none;'; ?>background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:8px;margin:6px 0 10px;width:96px;height:96px;align-items:center;justify-content:center;">
						<?php echo $image_html; ?>
					</div>
					<button type="button" class="button" data-oc-cat-icon-choose><?php
						echo $has_image
							? esc_html__( 'Replace image', 'owambe-connect-core' )
							: esc_html__( 'Choose image', 'owambe-connect-core' );
					?></button>
					<button type="button" class="button-link-delete" data-oc-cat-icon-remove style="margin-left:8px;color:#b32d2e;<?php echo $has_image ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'owambe-connect-core' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Square SVG or PNG. Used on the Browse-by-Category grid cards. If left empty, the emoji above (or built-in default) is used instead.', 'owambe-connect-core' ); ?></p>
			</td>
		</tr>
		<?php $this->print_picker_script(); ?>
		<?php
	}

	// ─────────────────────────────────────────────────────────
	//  Save
	// ─────────────────────────────────────────────────────────
	public function save_meta( $term_id ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		// Emoji: keep it tight — single emoji or short glyph, max 8 chars
		// after sanitisation. Empty string clears the meta so the fallback kicks in.
		if ( isset( $_POST[ self::META_EMOJI ] ) ) {
			$raw = (string) wp_unslash( $_POST[ self::META_EMOJI ] );
			$raw = sanitize_text_field( $raw );
			$raw = function_exists( 'mb_substr' ) ? mb_substr( $raw, 0, 8 ) : substr( $raw, 0, 8 );
			update_term_meta( $term_id, self::META_EMOJI, $raw );
		}
		// Image: integer attachment ID, validated as an actual image post.
		if ( isset( $_POST[ self::META_IMAGE_ID ] ) ) {
			$id = (int) $_POST[ self::META_IMAGE_ID ];
			if ( $id > 0 && wp_attachment_is_image( $id ) ) {
				update_term_meta( $term_id, self::META_IMAGE_ID, $id );
			} else {
				delete_term_meta( $term_id, self::META_IMAGE_ID );
			}
		}
	}

	// ─────────────────────────────────────────────────────────
	//  Category list table — show a tiny icon column up front
	// ─────────────────────────────────────────────────────────
	public function list_columns( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'cb' === $key ) {
				$new['oc_cat_icon'] = __( 'Icon', 'owambe-connect-core' );
			}
		}
		return $new;
	}

	public function list_column_value( $content, $column_name, $term_id ) {
		if ( 'oc_cat_icon' !== $column_name ) {
			return $content;
		}
		$term = get_term( $term_id, OC_TAX );
		if ( ! $term || is_wp_error( $term ) ) {
			return $content;
		}
		$icon = oc_get_category_icon( $term );
		if ( ! empty( $icon['image_url'] ) ) {
			return '<img src="' . esc_url( $icon['image_url'] ) . '" alt="" style="width:28px;height:28px;object-fit:contain;vertical-align:middle;"/>';
		}
		if ( ! empty( $icon['emoji'] ) ) {
			return '<span style="font-size:22px;line-height:1;">' . esc_html( $icon['emoji'] ) . '</span>';
		}
		return '<span style="color:#999;">—</span>';
	}

	// ─────────────────────────────────────────────────────────
	//  Media picker JS — kept inline so the class is self-contained
	// ─────────────────────────────────────────────────────────
	private function print_picker_script() {
		static $printed = false;
		if ( $printed ) return; // each page renders both add + edit, only emit once
		$printed = true;
		?>
		<script>
		(function () {
			if (window.__ocCatIconWired) return;
			window.__ocCatIconWired = true;
			document.addEventListener('click', function (e) {
				var pickerEl = e.target.closest('[data-oc-cat-icon-picker]');
				if (!pickerEl) return;
				var input   = pickerEl.querySelector('[data-oc-cat-icon-input]');
				var preview = pickerEl.querySelector('[data-oc-cat-icon-preview]');
				var removeBtn = pickerEl.querySelector('[data-oc-cat-icon-remove]');

				if (e.target.matches('[data-oc-cat-icon-choose]')) {
					e.preventDefault();
					if (typeof wp === 'undefined' || !wp.media) return;
					var frame = wp.media({
						title: <?php echo wp_json_encode( __( 'Choose category icon', 'owambe-connect-core' ) ); ?>,
						multiple: false,
						library: { type: 'image' },
						button: { text: <?php echo wp_json_encode( __( 'Use this image', 'owambe-connect-core' ) ); ?> }
					});
					frame.on('select', function () {
						var att = frame.state().get('selection').first().toJSON();
						if (!att || !att.id) return;
						input.value = att.id;
						preview.innerHTML = '<img src="' + att.url + '" alt="" style="width:80px;height:80px;object-fit:contain;">';
						preview.style.display = 'flex';
						if (removeBtn) removeBtn.style.display = '';
					});
					frame.open();
				}
				if (e.target.matches('[data-oc-cat-icon-remove]')) {
					e.preventDefault();
					input.value = '';
					preview.innerHTML = '';
					preview.style.display = 'none';
					if (removeBtn) removeBtn.style.display = 'none';
				}
			});
		})();
		</script>
		<?php
	}

	// ─────────────────────────────────────────────────────────
	//  Built-in fallback emoji map for known slugs
	// ─────────────────────────────────────────────────────────
	public static function default_emoji_for_slug( $slug ) {
		$map = self::default_emoji_map();
		return $map[ $slug ] ?? '';
	}

	public static function default_emoji_map() {
		return apply_filters( 'oc_default_category_emoji', [
			'catering'    => '🍽️',
			'photography' => '📸',
			'videography' => '🎥',
			'decor'       => '✨',
			'dj-music'    => '🎵',
			'venues'      => '🏛️',
			'mua'         => '💄',
			'cakes'       => '🎂',
			'planners'    => '📋',
			'attire'      => '👗',
			'transport'   => '🚗',
		] );
	}
}
