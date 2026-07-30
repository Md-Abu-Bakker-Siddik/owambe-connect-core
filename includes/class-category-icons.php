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
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag #submit,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #edittag #submit {
				background: #6E0F2C !important;
				border-color: #6E0F2C !important;
				color: #fff !important;
				border-radius: 6px;
				padding: 8px 22px;
				font-weight: 600;
				box-shadow: 0 2px 4px rgba(110,15,44,.16);
				text-shadow: none;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag #submit:hover,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #edittag #submit:hover {
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

			/* Professional taxonomy-screen layout — scoped to Vendor Categories only. */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> {
				--oc-cat-accent: #6E0F2C;
				--oc-cat-accent-dark: #4A0A1E;
				--oc-cat-gold: #C9A961;
				--oc-cat-border: #E4DDD2;
				--oc-cat-muted: #6B6361;
				--oc-cat-surface: #FFFFFF;
				--oc-cat-soft: #FAF7F2;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #wpbody-content > .wrap {
				box-sizing: border-box;
				max-width: none;
				margin-right: 20px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > h1,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #wpbody-content > .wrap > h1 {
				margin: 14px 0 18px;
				font-size: 30px;
				line-height: 1.2;
			}

			/* Search toolbar */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form {
				box-sizing: border-box;
				display: flex;
				align-items: center;
				justify-content: flex-end;
				min-height: 62px;
				margin: 0 0 18px;
				padding: 10px 12px;
				border: 1px solid var(--oc-cat-border);
				border-radius: 10px;
				background: var(--oc-cat-surface);
				box-shadow: 0 2px 8px rgba(31,27,26,.04);
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form .search-box {
				display: flex;
				float: none;
				align-items: center;
				justify-content: flex-end;
				gap: 8px;
				margin: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form #tag-search-input {
				box-sizing: border-box;
				width: 280px;
				height: 40px;
				min-height: 40px;
				margin: 0;
				padding: 0 12px;
				border: 1px solid #CBD5E1;
				border-radius: 6px;
				background: #fff;
				box-shadow: none;
				font-size: 13px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form #tag-search-input:focus {
				border-color: var(--oc-cat-accent);
				box-shadow: 0 0 0 3px rgba(110,15,44,.12);
				outline: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form #search-submit {
				box-sizing: border-box;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				height: 40px;
				min-height: 40px;
				margin: 0;
				padding: 0 16px;
				border-color: var(--oc-cat-accent);
				border-radius: 6px;
				background: var(--oc-cat-accent);
				color: #fff;
				font-weight: 600;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form #search-submit:hover,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form #search-submit:focus {
				border-color: var(--oc-cat-accent-dark);
				background: var(--oc-cat-accent-dark);
				color: #fff;
			}

			/* Balanced add-form and list-table columns */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-container {
				display: grid;
				grid-template-columns: minmax(330px, 390px) minmax(0, 1fr);
				gap: 20px;
				align-items: start;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-left,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right {
				float: none;
				width: auto;
				min-width: 0;
				margin: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-left .col-wrap {
				box-sizing: border-box;
				margin: 0;
				padding: 22px 24px 24px;
				border: 1px solid var(--oc-cat-border);
				border-radius: 10px;
				background: var(--oc-cat-surface);
				box-shadow: 0 3px 12px rgba(31,27,26,.05);
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right .col-wrap {
				min-width: 0;
				margin: 0;
				padding: 0;
				border: 0;
				border-radius: 0;
				background: transparent;
				box-shadow: none;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-left .form-wrap {
				margin: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-left .form-wrap h2 {
				margin: 0 0 20px;
				padding: 0 0 12px;
				border-bottom: 2px solid var(--oc-cat-gold);
				font-size: 20px;
				line-height: 1.25;
			}

			/* Add-category field rhythm */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field {
				box-sizing: border-box;
				margin: 0 0 18px;
				padding: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field label {
				display: block;
				margin: 0 0 7px;
				color: #1F1B1A;
				font-size: 13px;
				font-weight: 600;
				line-height: 1.3;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field p {
				margin: 6px 0 0;
				color: var(--oc-cat-muted);
				font-size: 12px;
				line-height: 1.5;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field input[type="text"],
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field select,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field textarea {
				box-sizing: border-box;
				width: 100%;
				max-width: none;
				min-height: 42px;
				margin: 0;
				padding: 10px 12px;
				border: 1px solid #D8DEE8;
				border-radius: 7px;
				background-color: #fff;
				box-shadow: 0 1px 2px rgba(15,23,42,.03);
				color: #1F2937;
				font-size: 13px;
				line-height: 1.4;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field textarea {
				min-height: 112px;
				resize: vertical;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field select {
				padding-right: 40px;
				-webkit-appearance: none;
				-moz-appearance: none;
				appearance: none;
				background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5 6 6.5 11 1.5' fill='none' stroke='%236E0F2C' stroke-width='1.75' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
				background-repeat: no-repeat;
				background-position: right 14px center;
				background-size: 10px 7px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field input[type="text"]:focus,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field select:focus,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .form-field textarea:focus {
				border-color: var(--oc-cat-accent);
				box-shadow: 0 0 0 3px rgba(110,15,44,.11);
				outline: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .oc-cat-icon-field {
				margin-top: 2px;
				padding-top: 17px;
				border-top: 1px solid #EEE7DF;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag .oc-cat-icon-field input[type="text"] {
				width: 66px !important;
				min-height: 42px;
				padding: 6px 8px;
				font-size: 20px !important;
				line-height: 1;
				text-align: center !important;
			}

			/* Image picker */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .oc-cat-icon-picker {
				display: grid;
				grid-template-columns: 92px minmax(0, 1fr);
				gap: 12px;
				align-items: start;
				margin-top: 8px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .oc-cat-icon-preview {
				box-sizing: border-box;
				display: flex !important;
				grid-column: 1;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				width: 92px !important;
				height: 92px !important;
				margin: 0 !important;
				padding: 10px !important;
				overflow: hidden;
				border: 1px dashed #CBD5E1 !important;
				border-radius: 9px !important;
				background: #F8FAFC !important;
				color: #7C8798;
				text-align: center;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .oc-cat-icon-preview:empty::before {
				content: "\f128";
				display: block;
				margin-bottom: 5px;
				color: var(--oc-cat-accent);
				font-family: dashicons;
				font-size: 24px;
				line-height: 1;
				opacity: .75;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .oc-cat-icon-preview:empty::after {
				content: "No image";
				display: block;
				font-size: 10px;
				font-weight: 600;
				line-height: 1.2;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .oc-cat-icon-preview img {
				display: block;
				max-width: 76px !important;
				max-height: 76px !important;
				object-fit: contain;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .oc-cat-icon-picker__details {
				display: flex;
				min-width: 0;
				flex-direction: column;
				align-items: stretch;
				gap: 7px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> [data-oc-cat-icon-choose] {
				box-sizing: border-box;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 6px;
				width: 100%;
				min-height: 38px;
				margin: 0;
				padding: 0 14px;
				border-color: var(--oc-cat-accent);
				border-radius: 6px;
				background: #fff;
				color: var(--oc-cat-accent);
				font-weight: 600;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> [data-oc-cat-icon-choose]::before {
				content: "\f104";
				font-family: dashicons;
				font-size: 16px;
				line-height: 1;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> [data-oc-cat-icon-choose]:hover,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> [data-oc-cat-icon-choose]:focus {
				border-color: var(--oc-cat-accent);
				background: #FBEFF2;
				color: var(--oc-cat-accent);
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .oc-cat-icon-help {
				margin: 0 !important;
				color: var(--oc-cat-muted) !important;
				font-size: 11.5px !important;
				line-height: 1.4 !important;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> [data-oc-cat-icon-remove] {
				align-self: flex-start;
				margin: 0 !important;
				font-size: 12px;
				line-height: 1.4;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag p.submit {
				margin: 20px 0 0;
				padding: 18px 0 0;
				border-top: 1px solid #EEE7DF;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #addtag p.submit .button-primary {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 100%;
				min-height: 44px;
				margin: 0;
				padding: 0 20px;
			}

			/* List controls */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right .tablenav {
				box-sizing: border-box;
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 12px;
				width: 100%;
				height: auto;
				min-height: 52px;
				margin: 0 0 10px;
				padding: 8px 10px;
				border: 1px solid var(--oc-cat-border);
				border-radius: 9px;
				background: var(--oc-cat-surface);
				box-shadow: 0 2px 7px rgba(31,27,26,.035);
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right .tablenav.bottom {
				min-height: 40px;
				margin: 8px 0 0;
				padding: 0;
				border: 0;
				border-radius: 0;
				background: transparent;
				box-shadow: none;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right .tablenav .alignleft.actions {
				display: flex;
				float: none;
				align-items: center;
				gap: 8px;
				padding: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right .tablenav select {
				box-sizing: border-box;
				height: 36px;
				min-height: 36px;
				margin: 0;
				padding: 0 32px 0 10px;
				border: 1px solid #D8DEE8;
				border-radius: 6px;
				background-color: #fff;
				font-size: 12.5px;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right .tablenav .button {
				box-sizing: border-box;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				height: 36px;
				min-height: 36px;
				margin: 0;
				padding: 0 14px;
				border-color: var(--oc-cat-border);
				border-radius: 6px;
				background: #fff;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right .tablenav .tablenav-pages {
				display: flex;
				float: none;
				align-items: center;
				margin: 0;
				color: var(--oc-cat-muted);
			}

			/* Category table */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table {
				width: 100%;
				margin: 0;
				overflow: hidden;
				border: 1px solid var(--oc-cat-border);
				border-collapse: separate;
				border-spacing: 0;
				border-radius: 10px;
				background: #fff;
				box-shadow: 0 3px 12px rgba(31,27,26,.045);
				table-layout: fixed;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tfoot {
				display: none;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table thead th,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tfoot th {
				height: 48px;
				padding: 0 12px;
				vertical-align: middle;
				text-align: center;
				border-bottom: 1px solid var(--oc-cat-gold);
				background: var(--oc-cat-soft);
				color: #4B4542;
				font-size: 10.5px;
				font-weight: 700;
				letter-spacing: .075em;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody tr,
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody tr.alternate {
				background: #fff;
				transition: background-color .16s ease;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody tr:nth-child(even) {
				background: #FCFBF9;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody tr:hover {
				background: #F8F3ED;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody td {
				height: 58px;
				padding: 10px 12px;
				vertical-align: middle;
				text-align: center;
				border-bottom: 1px solid #EEE9E2;
				color: #4B5563;
				line-height: 1.4;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody th {
				height: 58px;
				vertical-align: middle;
				text-align: center;
				border-bottom: 1px solid #EEE9E2;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody tr:last-child td {
				border-bottom: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody tr:last-child th {
				border-bottom: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .check-column {
				box-sizing: border-box;
				width: 46px;
				padding-right: 10px !important;
				padding-left: 10px !important;
				padding-top: 0;
				text-align: center;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .check-column input[type="checkbox"] {
				display: block !important;
				float: none !important;
				margin: 0 auto !important;
				vertical-align: middle;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table thead #cb {
				position: relative;
				padding: 0 !important;
				vertical-align: middle !important;
				text-align: center !important;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table thead #cb #cb-select-all-1 {
				position: absolute !important;
				top: 50%;
				left: 50%;
				display: block !important;
				float: none !important;
				margin: 0 !important;
				transform: translate(-50%, -50%);
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .column-oc_cat_icon {
				width: 70px;
				padding-right: 8px;
				padding-left: 8px;
				text-align: center;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table td.column-oc_cat_icon img {
				box-sizing: border-box;
				display: block;
				width: 36px !important;
				height: 36px !important;
				margin: 0 auto;
				padding: 4px;
				border: 1px solid #E8E1D8;
				border-radius: 8px;
				background: #fff;
				object-fit: contain;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table td.column-oc_cat_icon > span {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 36px;
				height: 36px;
				border: 1px solid #E8E1D8;
				border-radius: 8px;
				background: #fff;
				font-size: 21px !important;
				line-height: 1 !important;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .column-name {
				width: 35%;
				white-space: nowrap;
				text-align: left;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody td.column-name > strong {
				display: inline-block;
				margin: 0;
				vertical-align: middle;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table tbody td.column-name > strong + br {
				display: none;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .column-description {
				width: 21%;
				color: #64748B;
				text-align: center;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .column-slug {
				width: 18%;
				color: #64748B;
				font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
				font-size: 12px;
				text-align: center;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .column-posts {
				width: 72px;
				text-align: center;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .column-posts a {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-width: 28px;
				height: 28px;
				padding: 0 7px;
				border: 1px solid #E7DDD0;
				border-radius: 999px;
				background: var(--oc-cat-soft);
				color: var(--oc-cat-accent);
				font-size: 11px;
				font-weight: 700;
				text-decoration: none;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .row-title {
				display: inline-block;
				color: var(--oc-cat-accent);
				font-size: 13px;
				font-weight: 650;
				line-height: 1.35;
				vertical-align: middle;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .row-actions {
				display: inline-block;
				margin: 0 0 0 8px;
				padding: 0;
				font-size: 11.5px;
				line-height: 1.4;
				white-space: nowrap;
				text-align: left;
				vertical-align: middle;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .row-actions a {
				text-decoration: none;
			}

			/* Edit-category form */
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #edittag {
				box-sizing: border-box;
				max-width: 940px;
				padding: 24px;
				border: 1px solid var(--oc-cat-border);
				border-radius: 10px;
				background: #fff;
				box-shadow: 0 3px 12px rgba(31,27,26,.05);
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #edittag .form-table {
				margin: 0;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #edittag .form-table th {
				width: 190px;
				padding: 17px 20px 17px 0;
				vertical-align: top;
			}
			body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #edittag .form-table td {
				padding: 12px 0 17px;
			}

			/* Responsive stacking */
			@media (max-width: 1180px) {
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-container {
					grid-template-columns: minmax(300px, 350px) minmax(0, 1fr);
					gap: 16px;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-left .col-wrap {
					padding: 20px;
				}
			}
			@media (max-width: 1100px) {
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-container {
					grid-template-columns: minmax(0, 1fr);
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right {
					overflow-x: auto;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table {
					min-width: 760px;
				}
			}
			@media (max-width: 782px) {
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #wpbody-content > .wrap {
					margin-right: 12px;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form {
					padding: 10px;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form .search-box {
					width: 100%;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> .wrap > .search-form #tag-search-input {
					flex: 1;
					width: auto;
					min-width: 0;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right .tablenav {
					flex-wrap: wrap;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .column-name {
					white-space: normal;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #col-right table.wp-list-table .row-actions {
					display: block;
					margin: 4px 0 0;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #edittag {
					padding: 18px;
				}
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #edittag .form-table th,
				body.taxonomy-<?php echo esc_attr( OC_TAX ); ?> #edittag .form-table td {
					display: block;
					width: auto;
					padding: 10px 0;
				}
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
				<div class="oc-cat-icon-picker__details">
					<button type="button" class="button" data-oc-cat-icon-choose><?php esc_html_e( 'Choose image', 'owambe-connect-core' ); ?></button>
					<p class="oc-cat-icon-help"><?php esc_html_e( 'Square SVG or PNG recommended. Used on category cards; falls back to the emoji above.', 'owambe-connect-core' ); ?></p>
					<button type="button" class="button-link-delete" data-oc-cat-icon-remove style="display:none;color:#b32d2e;"><?php esc_html_e( 'Remove', 'owambe-connect-core' ); ?></button>
				</div>
			</div>
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
					<div class="oc-cat-icon-picker__details">
						<button type="button" class="button" data-oc-cat-icon-choose><?php
							echo $has_image
								? esc_html__( 'Replace image', 'owambe-connect-core' )
								: esc_html__( 'Choose image', 'owambe-connect-core' );
						?></button>
						<p class="description oc-cat-icon-help"><?php esc_html_e( 'Square SVG or PNG. Used on category cards; falls back to the emoji or built-in default.', 'owambe-connect-core' ); ?></p>
						<button type="button" class="button-link-delete" data-oc-cat-icon-remove style="color:#b32d2e;<?php echo $has_image ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'owambe-connect-core' ); ?></button>
					</div>
				</div>
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
