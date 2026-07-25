<?php
/**
 * Vendor business card generator (PNG + PDF, with QR code).
 *
 * Renders a 1050x600 card entirely with GD — no external APIs. Optional
 * assets degrade gracefully: missing TTF fonts fall back to GD's built-in
 * bitmap font, a missing phpqrcode library falls back to a "View online"
 * box, and a missing logo falls back to a monogram. The PDF variant wraps
 * the rendered card as a JPEG inside a hand-built single-page PDF-1.4.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Business_Card {

	const ACTION = 'oc_business_card';

	/** Canvas size (px). */
	const W = 1050;
	const H = 600;

	public function register() {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
		// Nopriv hook only exists so logged-out visitors get a friendly
		// wp_die() instead of admin-post.php's blank "0" response.
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * GET handler — ?action=oc_business_card&format=png|pdf&_wpnonce=…
	 * Ownership resolves through oc_get_current_vendor_post(); admins may
	 * target any vendor with &vendor_id=.
	 */
	public function handle() {
		if ( ! is_user_logged_in() ) {
			wp_die(
				esc_html__( 'You must be logged in to download a business card.', 'owambe-connect-core' ),
				'',
				[ 'response' => 403 ]
			);
		}
		check_admin_referer( self::ACTION );

		$post = function_exists( 'oc_get_current_vendor_post' ) ? oc_get_current_vendor_post() : null;

		// Admin override — lets support/admin download any vendor's card.
		if ( current_user_can( 'manage_options' ) && isset( $_GET['vendor_id'] ) ) {
			$maybe = get_post( absint( wp_unslash( $_GET['vendor_id'] ) ) );
			if ( $maybe instanceof WP_Post && OC_CPT === $maybe->post_type ) {
				$post = $maybe;
			}
		}

		if ( ! $post instanceof WP_Post ) {
			wp_die( esc_html__( 'No vendor profile found.', 'owambe-connect-core' ) );
		}

		$format = 'png';
		if ( isset( $_GET['format'] ) && 'pdf' === sanitize_key( wp_unslash( $_GET['format'] ) ) ) {
			$format = 'pdf';
		}

		// Inline preview mode — serves the PNG without the attachment header so
		// the dashboard's Business Card panel can show it in an <img>.
		$preview = ! empty( $_GET['preview'] );

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			wp_die( esc_html__( 'Image functions unavailable on this server.', 'owambe-connect-core' ) );
		}

		// Black-&-white variant via ?variant=bw (or ?bw=1).
		$variant = 'color';
		if ( ( isset( $_GET['variant'] ) && 'bw' === sanitize_key( wp_unslash( $_GET['variant'] ) ) ) || ! empty( $_GET['bw'] ) ) {
			$variant = 'bw';
		}

		$img  = $this->render( $post->ID, $variant );
		$slug = sanitize_file_name( $post->post_name ? $post->post_name : 'vendor-' . $post->ID );
		if ( 'bw' === $variant ) {
			$slug .= '-bw';
		}

		if ( 'pdf' === $format && ! $preview ) {
			$this->output_pdf( $img, $slug );
		}
		$this->output_png( $img, $slug, $preview );
	}

	/* ------------------------------------------------------------------ */
	/* Rendering                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Draw the full card and return the GD image resource.
	 *
	 * Premium "framed" look modelled on the client's reference: a cream body
	 * with burgundy top/bottom bars and a burgundy right column, a gold arc
	 * sweeping the top-right corner, a gold-framed rounded logo box, the
	 * business name in burgundy serif over a gold letterspaced category line
	 * and a location pin, contact rows as burgundy circular icons with gold
	 * hairline dividers, and the right column stacking a phone glyph,
	 * "SCAN TO VIEW PROFILE", a white rounded QR panel, the vendor ID and
	 * the "LISTED ON Owambe Connect" footer. Card corners are rounded with
	 * true transparency (the PDF path flattens onto white).
	 *
	 * @param int    $post_id Vendor post ID.
	 * @param string $variant 'color' (cream + burgundy + gold) or 'bw'
	 *                        (white + near-black, monochrome).
	 * @return GdImage
	 */
	public function render( $post_id, $variant = 'color' ) {
		$img = imagecreatetruecolor( self::W, self::H );
		imagesavealpha( $img, true );
		imagealphablending( $img, false );
		$clear = imagecolorallocatealpha( $img, 0, 0, 0, 127 );
		imagefilledrectangle( $img, 0, 0, self::W - 1, self::H - 1, $clear );
		imagealphablending( $img, true );

		// ── Palette ──────────────────────────────────────────────────────
		// One layout, two skins: cream/burgundy/gold, or white/near-black.
		$bw = ( 'bw' === $variant );

		$white = imagecolorallocate( $img, 255, 255, 255 );
		$gray  = imagecolorallocate( $img, 150, 150, 150 ); // QR placeholder text

		if ( $bw ) {
			$body     = $white;                                      // card body
			$panel    = imagecolorallocate( $img, 28, 28, 28 );      // bars + right column
			$gold     = imagecolorallocate( $img, 148, 148, 148 );   // accents → mid gray
			$golddeep = imagecolorallocate( $img, 96, 96, 96 );      // tagline / dividers
			$goldlt   = imagecolorallocate( $img, 214, 214, 214 );   // vendor number on panel
			$creamtx  = imagecolorallocate( $img, 210, 210, 210 );   // captions on panel
			$dimtx    = imagecolorallocate( $img, 150, 150, 150 );   // "LISTED ON"
			$ink      = imagecolorallocate( $img, 26, 26, 26 );      // primary text on body
			$muted    = imagecolorallocate( $img, 106, 106, 106 );   // row values
			$namec    = $ink;
			$circle   = [ 28, 28, 28 ];                              // contact circle fill
			$divc     = imagecolorallocatealpha( $img, 96, 96, 96, 80 );
		} else {
			$body     = imagecolorallocate( $img, 247, 240, 228 );   // warm ivory
			$panel    = imagecolorallocate( $img, 110, 15, 44 );     // #6E0F2C burgundy
			$gold     = imagecolorallocate( $img, 201, 169, 97 );    // #C9A961
			$golddeep = imagecolorallocate( $img, 176, 138, 62 );    // deeper gold text
			$goldlt   = imagecolorallocate( $img, 226, 199, 143 );   // vendor number
			$creamtx  = imagecolorallocate( $img, 240, 228, 208 );   // captions on burgundy
			$dimtx    = imagecolorallocate( $img, 205, 170, 178 );   // dusty rose caption
			$ink      = imagecolorallocate( $img, 44, 36, 33 );      // primary text on cream
			$muted    = imagecolorallocate( $img, 122, 106, 95 );    // row values
			$namec    = $panel;                                      // name in burgundy
			$circle   = [ 110, 15, 44 ];
			$divc     = imagecolorallocatealpha( $img, 176, 138, 62, 70 );
		}

		// ── Card structure ───────────────────────────────────────────────
		// Burgundy rounded slab first (its corners become the card corners),
		// then the cream body strip, then the right column back on top.
		$this->fill_round_rect( $img, 0, 0, self::W - 1, self::H - 1, 36, $panel );
		imagefilledrectangle( $img, 0, 44, self::W - 1, 551, $body );
		$panel_x = 762;
		imagefilledrectangle( $img, $panel_x, 44, self::W - 1, 551, $panel );

		// Gold arc sweeping the top-right corner (a ring: gold disc overdrawn
		// by a panel-coloured disc), tucked tight so it clears the column text.
		imagefilledellipse( $img, 1120, -30, 440, 440, $gold );
		imagefilledellipse( $img, 1120, -30, 384, 384, $panel );

		// Bottom-bar ornament: centre diamond + short hairlines either side.
		$ocx = (int) round( self::W / 2 ); $ocy = 576;
		$this->fill_poly( $img, [ $ocx, $ocy - 6, $ocx + 6, $ocy, $ocx, $ocy + 6, $ocx - 6, $ocy ], $gold );
		imagefilledrectangle( $img, $ocx - 60, $ocy, $ocx - 14, $ocy, $gold );
		imagefilledrectangle( $img, $ocx + 14, $ocy, $ocx + 60, $ocy, $gold );

		// Re-cut the rounded corners (the arc ring may have spilled past them).
		$this->round_corners( $img, 36 );

		$fonts = $this->fonts();
		$name  = wp_specialchars_decode( get_the_title( $post_id ), ENT_QUOTES );

		// ------------------------------------------------ Logo box (gold-framed, white, rounded).
		$px = 56; $py = 86; $ps = 168;
		$this->fill_round_rect( $img, $px - 2, $py - 2, $px + $ps + 1, $py + $ps + 1, 24, $gold );
		$this->fill_round_rect( $img, $px, $py, $px + $ps - 1, $py + $ps - 1, 22, $white );

		$logo = $this->load_logo( $post_id );
		if ( $logo ) {
			$sw = imagesx( $logo );
			$sh = imagesy( $logo );

			// Flatten onto a white canvas at source resolution first. This
			// converts palette/transparent PNGs to truecolor and matches the
			// white plate, so the resample stays crisp with no dark fringe/halo
			// around transparent-logo edges.
			$flat = imagecreatetruecolor( $sw, $sh );
			imagealphablending( $flat, true );
			imagefilledrectangle( $flat, 0, 0, $sw, $sh, imagecolorallocate( $flat, 255, 255, 255 ) );
			imagecopy( $flat, $logo, 0, 0, 0, 0, $sw, $sh );
			imagedestroy( $logo );

			// B&W variant → desaturate the logo so the whole card is monochrome.
			if ( $bw && function_exists( 'imagefilter' ) ) {
				imagefilter( $flat, IMG_FILTER_GRAYSCALE );
			}

			// Contain-fit: preserve aspect ratio and pad evenly — the logo is
			// always fully visible (never cropped) and never stretched. Padding
			// is modest so it fills the plate cleanly without touching the edge.
			$pad   = 16;
			$inner = max( 1, $ps - $pad * 2 );
			$scale = min( $inner / max( 1, $sw ), $inner / max( 1, $sh ) );
			$dw    = max( 1, (int) round( $sw * $scale ) );
			$dh    = max( 1, (int) round( $sh * $scale ) );
			$dx    = $px + (int) round( ( $ps - $dw ) / 2 );
			$dy    = $py + (int) round( ( $ps - $dh ) / 2 );
			imagecopyresampled( $img, $flat, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh );
			imagedestroy( $flat );
		} else {
			$mono = $this->monogram( $name );
			if ( '' !== $mono ) {
				$size = 76;
				while ( $size > 26 && $this->text_width( $mono, $fonts['display'], $size ) > $ps - 44 ) { $size -= 4; }
				$mw = $this->text_width( $mono, $fonts['display'], $size );
				$this->text( $img, $size, $px + (int) round( $ps / 2 ) - (int) round( $mw / 2 ), $py + (int) round( $ps / 2 ) + (int) round( $size * 0.36 ), $namec, $mono, $fonts['display'] );
			}
		}

		// ------------------------------------------------ Name + category + location.
		$tx = 260; $tw = $panel_x - $tx - 26;
		$terms    = get_the_terms( $post_id, OC_TAX );
		$category = ( $terms && ! is_wp_error( $terms ) ) ? (string) reset( $terms )->name : '';
		$category = html_entity_decode( $category, ENT_QUOTES ); // term names store "&" as &amp;
		// Location: a single region → "Region, United Kingdom"; several regions
		// (or none) → just "United Kingdom". Falls back to covered cities/areas.
		$as_list = static function ( $raw ) {
			if ( is_string( $raw ) ) {
				$raw = ( '' === $raw ) ? [] : explode( ',', $raw );
			}
			return array_values( array_filter( array_map( 'trim', (array) $raw ) ) );
		};
		$scope = $as_list( get_post_meta( $post_id, '_oc_location_regions', true ) );
		if ( empty( $scope ) ) {
			$scope = $as_list( get_post_meta( $post_id, '_oc_location_areas', true ) );
		}
		$location = ( 1 === count( $scope ) ) ? $scope[0] . ', United Kingdom' : 'United Kingdom';

		list( $lines, $ns ) = $this->wrap_fit( $name, $fonts['display'], 44, $tw, 2, 26 );
		$lh = (int) round( $ns * 1.22 );

		// Vertical block: name lines + tagline + location, centred on the logo box.
		$tag_gap = 30; $loc_gap = 44;
		$bh  = count( $lines ) * $lh + ( '' !== $category ? $tag_gap : 0 ) + $loc_gap;
		$top = $py + (int) round( ( $ps - $bh ) / 2 );
		if ( $top < 66 ) { $top = 66; }
		$base = $top + $ns;
		foreach ( $lines as $line ) {
			$this->text( $img, $ns, $tx, $base, $namec, $line, $fonts['display'] );
			$base += $lh;
		}
		$base -= $lh;
		if ( '' !== $category ) {
			$base += $tag_gap;
			$this->text_tracked( $img, 15, $tx, $base, $golddeep, strtoupper( $category ), $fonts['bold'], 4 );
			// Thin gold divider with a centred diamond, per the reference design.
			$dy = $base + 17;
			imagefilledrectangle( $img, $tx, $dy, $tx + 64, $dy, $gold );
			$this->fill_poly( $img, [ $tx + 78, $dy - 4, $tx + 82, $dy, $tx + 78, $dy + 4, $tx + 74, $dy ], $gold );
			imagefilledrectangle( $img, $tx + 92, $dy, $tx + 156, $dy, $gold );
		}
		$base += $loc_gap;
		$this->draw_pin( $img, $tx + 7, $base - 6, $circle, $white );
		$this->text( $img, 14, $tx + 22, $base, $ink, $this->fit_ellipsis( $location, $fonts['regular'], 14, $tw - 22 ), $fonts['regular'] );

		// ------------------------------------------------ Contact rows.
		$permalink = get_permalink( $post_id );
		$ig        = trim( (string) get_post_meta( $post_id, '_oc_instagram', true ) );
		$ig        = '' !== $ig ? '@' . ltrim( $ig, '@' ) : '';
		$rows      = [
			[ 'WhatsApp',  (string) get_post_meta( $post_id, '_oc_whatsapp', true ) ],
			[ 'Email',     (string) get_post_meta( $post_id, '_oc_public_email', true ) ],
			[ 'Instagram', $ig ],
			[ 'Website',   $this->display_url( $permalink ) ],
		];
		$rows = apply_filters( 'oc_business_card_fields', $rows, $post_id );
		$rows = array_values( array_filter( (array) $rows, static function ( $row ) {
			return '' !== trim( (string) ( $row[1] ?? '' ) );
		} ) );

		// Fit however many rows survive (max 4 shown) into y 300..545.
		$rows  = array_slice( $rows, 0, 4 );
		$pitch = ( count( $rows ) > 3 ) ? 58 : 66;
		$cy    = 316;
		foreach ( $rows as $row ) {
			$label = (string) $row[0];
			$value = trim( (string) $row[1] );
			// Pick the icon from the heading; filter-added rows get a neutral node.
			$key = strtolower( preg_replace( '/[^a-z]/i', '', $label ) );
			if ( in_array( $key, [ 'website', 'web', 'url', 'site' ], true ) ) {
				$icon = 'web';
			} elseif ( in_array( $key, [ 'whatsapp', 'wa' ], true ) ) {
				$icon = 'whatsapp';
			} elseif ( in_array( $key, [ 'email', 'mail' ], true ) ) {
				$icon = 'email';
			} elseif ( in_array( $key, [ 'instagram', 'insta', 'ig' ], true ) ) {
				$icon = 'instagram';
			} else {
				$icon = 'node';
			}
			// Burgundy circle with a white glyph knocked into it.
			imagefilledellipse( $img, 86, $cy, 40, 40, $panel );
			$this->draw_contact_icon( $img, $icon, 86, $cy, [ 255, 255, 255 ], $bw );

			// Thin gold vertical separator between the icon and the text, per reference.
			imagefilledrectangle( $img, 114, $cy - 15, 114, $cy + 15, $divc );

			$this->text( $img, 15, 128, $cy - 2, $ink, $label, $fonts['bold'] );
			$this->text( $img, 13, 128, $cy + 17, $muted, $this->fit_ellipsis( $value, $fonts['regular'], 13, $panel_x - 128 - 40 ), $fonts['regular'] );
			imagefilledrectangle( $img, 128, $cy + 29, $panel_x - 60, $cy + 29, $divc );
			$cy += $pitch;
		}

		// ------------------------------------------------ Right column (burgundy panel).
		$number = function_exists( 'oc_get_vendor_number' ) ? (string) oc_get_vendor_number( $post_id ) : '';
		$ccx    = (int) round( ( $panel_x + self::W ) / 2 );

		// Small phone glyph above the caption.
		$this->fill_round_rect( $img, $ccx - 12, 74, $ccx + 12, 112, 7, $gold );
		$this->fill_round_rect( $img, $ccx - 9, 79, $ccx + 9, 103, 4, $panel );
		imagefilledellipse( $img, $ccx, 107, 4, 4, $panel );

		$this->text_tracked_centered( $img, 12, $ccx, 146, $creamtx, 'SCAN TO VIEW PROFILE', $fonts['bold'], 3 );

		// White rounded QR panel.
		$qs = 176; $qx = $ccx - (int) round( $qs / 2 ); $qy = 182;
		$this->fill_round_rect( $img, $qx - 16, $qy - 16, $qx + $qs + 15, $qy + $qs + 15, 18, $white );
		$this->draw_qr( $img, (string) $permalink, $qx, $qy, $qs, $gold, $gray, $fonts );

		// Gold divider + diamond between the QR panel and the vendor ID.
		$oy = 404;
		imagefilledrectangle( $img, $ccx - 52, $oy, $ccx - 14, $oy, $gold );
		$this->fill_poly( $img, [ $ccx, $oy - 4, $ccx + 4, $oy, $ccx, $oy + 4, $ccx - 4, $oy ], $gold );
		imagefilledrectangle( $img, $ccx + 14, $oy, $ccx + 52, $oy, $gold );

		$this->text_tracked_centered( $img, 12, $ccx, 432, $creamtx, 'VENDOR ID', $fonts['regular'], 3 );
		if ( '' !== $number ) {
			$nw = $this->text_width( $number, $fonts['bold'], 24 );
			$this->text( $img, 24, $ccx - (int) round( $nw / 2 ), 466, $goldlt, $number, $fonts['bold'] );
		}
		$this->text_tracked_centered( $img, 10, $ccx, 502, $dimtx, 'LISTED ON', $fonts['regular'], 3 );
		$br  = 'Owambe Connect';
		$brw = $this->text_width( $br, $fonts['display'], 21 );
		$this->text( $img, 21, $ccx - (int) round( $brw / 2 ), 532, $gold, $br, $fonts['display'] );

		return $img;
	}

	/**
	 * Cut the four card corners back to transparent outside a radius — used
	 * after decorative fills (the gold arc) that may spill past the rounded
	 * slab drawn first.
	 */
	private function round_corners( $img, $r ) {
		imagealphablending( $img, false );
		$clear   = imagecolorallocatealpha( $img, 0, 0, 0, 127 );
		$centers = [
			[ $r, $r ],
			[ self::W - 1 - $r, $r ],
			[ $r, self::H - 1 - $r ],
			[ self::W - 1 - $r, self::H - 1 - $r ],
		];
		foreach ( $centers as $c ) {
			$x0 = ( $c[0] <= $r ) ? 0 : self::W - $r;
			$y0 = ( $c[1] <= $r ) ? 0 : self::H - $r;
			for ( $x = $x0; $x < $x0 + $r; $x++ ) {
				for ( $y = $y0; $y < $y0 + $r; $y++ ) {
					$dx = $x - $c[0];
					$dy = $y - $c[1];
					if ( ( $dx * $dx + $dy * $dy ) > ( $r * $r ) ) {
						imagesetpixel( $img, $x, $y, $clear );
					}
				}
			}
		}
		imagealphablending( $img, true );
	}

	/**
	 * Letterspaced text at a baseline — GD has no tracking, so draw each
	 * character advancing by its measured width plus $tracking px.
	 */
	private function text_tracked( $img, $size, $x, $y, $color, $text, $font, $tracking = 3 ) {
		if ( ! $this->can_ttf( $font ) ) {
			$this->text( $img, $size, $x, $y, $color, $text, $font );
			return;
		}
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = function_exists( 'mb_substr' ) ? mb_substr( $text, $i, 1 ) : substr( $text, $i, 1 );
			$this->text( $img, $size, $x, $y, $color, $ch, $font );
			$x += $this->text_width( $ch, $font, $size ) + $tracking;
		}
	}

	/** Width of a letterspaced string (matches text_tracked's advance). */
	private function tracked_width( $text, $font, $size, $tracking = 3 ) {
		if ( ! $this->can_ttf( $font ) ) {
			return $this->text_width( $text, $font, $size );
		}
		$w   = 0;
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = function_exists( 'mb_substr' ) ? mb_substr( $text, $i, 1 ) : substr( $text, $i, 1 );
			$w += $this->text_width( $ch, $font, $size ) + $tracking;
		}
		return max( 0, $w - $tracking );
	}

	/** Letterspaced text centred on $cx. */
	private function text_tracked_centered( $img, $size, $cx, $y, $color, $text, $font, $tracking = 3 ) {
		$w = $this->tracked_width( $text, $font, $size, $tracking );
		$this->text_tracked( $img, $size, $cx - (int) round( $w / 2 ), $y, $color, $text, $font, $tracking );
	}

	/**
	 * Small map-pin glyph (filled teardrop with a knocked-out dot), centred
	 * horizontally on $cx with its point at roughly $cy + 8.
	 */
	private function draw_pin( $img, $cx, $cy, $rgb, $hole ) {
		$c = imagecolorallocate( $img, (int) $rgb[0], (int) $rgb[1], (int) $rgb[2] );
		imagefilledellipse( $img, $cx, $cy - 2, 14, 14, $c );
		$this->fill_poly( $img, [ $cx - 6, $cy, $cx + 6, $cy, $cx, $cy + 10 ], $c );
		imagefilledellipse( $img, $cx, $cy - 2, 6, 6, $hole );
	}

	/**
	 * Fill a rounded rectangle — centre cross plus four corner discs. Used to
	 * give the QR frame/panel soft, blunt corners instead of sharp squares.
	 */
	private function fill_round_rect( $img, $x1, $y1, $x2, $y2, $r, $color ) {
		$r = (int) max( 0, min( $r, ( $x2 - $x1 ) / 2, ( $y2 - $y1 ) / 2 ) );
		imagefilledrectangle( $img, $x1 + $r, $y1, $x2 - $r, $y2, $color );
		imagefilledrectangle( $img, $x1, $y1 + $r, $x2, $y2 - $r, $color );
		$d = $r * 2;
		imagefilledellipse( $img, $x1 + $r, $y1 + $r, $d, $d, $color );
		imagefilledellipse( $img, $x2 - $r, $y1 + $r, $d, $d, $color );
		imagefilledellipse( $img, $x1 + $r, $y2 - $r, $d, $d, $color );
		imagefilledellipse( $img, $x2 - $r, $y2 - $r, $d, $d, $color );
	}

	/** Version-safe filled polygon (imagefilledpolygon dropped its count arg in PHP 8.1). */
	private function fill_poly( $img, array $points, $color ) {
		if ( PHP_VERSION_ID >= 80100 ) {
			imagefilledpolygon( $img, $points, $color );
		} else {
			imagefilledpolygon( $img, $points, (int) ( count( $points ) / 2 ), $color );
		}
	}

	/**
	 * Draw a crisp, solid contact icon centred at ($cx,$cy) for the given
	 * channel. Each icon is a gold silhouette with its detail "knocked out"
	 * (transparent, so the card shows through), rendered at 4x on a buffer and
	 * downsampled for smooth, anti-aliased edges. Unknown channels get a dot.
	 */
	private function draw_contact_icon( $img, $type, $cx, $cy, $color, $outline = false ) {
		$box = 28;
		$rgb = is_array( $color ) ? $color : [ 201, 169, 97 ];

		// Prefer a bundled, crisp icon PNG (black glyph on transparent) tinted to
		// the accent colour; fall back to the GD-drawn glyph if the file is absent.
		$file = OC_PLUGIN_DIR . 'assets/icons/oc-' . preg_replace( '/[^a-z]/', '', (string) $type ) . '.png';
		if ( function_exists( 'imagecreatefrompng' ) && function_exists( 'imagefilter' ) && is_file( $file ) ) {
			$ico = @imagecreatefrompng( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( $ico ) {
				imagealphablending( $ico, false );
				imagesavealpha( $ico, true );
				imagefilter( $ico, IMG_FILTER_COLORIZE, (int) $rgb[0], (int) $rgb[1], (int) $rgb[2] ); // black → accent
				imagealphablending( $img, true );
				imagecopyresampled( $img, $ico, (int) round( $cx - $box / 2 ), (int) round( $cy - $box / 2 ), 0, 0, $box, $box, imagesx( $ico ), imagesy( $ico ) );
				imagedestroy( $ico );
				return;
			}
		}

		$ss = 4;
		$b  = $box * $ss; // buffer size

		$buf = imagecreatetruecolor( $b, $b );
		imagesavealpha( $buf, true );
		imagealphablending( $buf, false );
		$clear = imagecolorallocatealpha( $buf, 0, 0, 0, 127 );
		imagefilledrectangle( $buf, 0, 0, $b, $b, $clear ); // fully transparent
		imagealphablending( $buf, true );
		$rgb = is_array( $color ) ? $color : [ 201, 169, 97 ];
		$g   = imagecolorallocate( $buf, (int) $rgb[0], (int) $rgb[1], (int) $rgb[2] ); // icon colour

		switch ( $type ) {
			case 'email': // solid envelope with a knocked-out flap crease
				$this->fill_round_rect( $buf, 14, 34, 98, 78, 9, $g );
				imagealphablending( $buf, false );
				imagesetthickness( $buf, 8 );
				imageline( $buf, 16, 37, 56, 62, $clear );
				imageline( $buf, 96, 37, 56, 62, $clear );
				imagesetthickness( $buf, 1 );
				imagealphablending( $buf, true );
				break;

			case 'whatsapp':
				// Speech bubble (outline ring + tail) with a solid phone receiver
				// inside — the familiar WhatsApp glyph, in both variants.
				imagesetthickness( $buf, 7 );
				imagearc( $buf, 54, 50, 80, 80, 0, 360, $g );   // bubble ring
				imagesetthickness( $buf, 1 );
				$this->fill_poly( $buf, [ 20, 82, 44, 76, 26, 101 ], $g ); // tail (bottom-left)
				imagesetthickness( $buf, 12 );
				imagearc( $buf, 70, 70, 56, 56, 180, 270, $g ); // curved receiver handle
				imagesetthickness( $buf, 1 );
				imagefilledellipse( $buf, 70, 42, 22, 22, $g ); // earpiece (top-right)
				imagefilledellipse( $buf, 42, 70, 22, 22, $g ); // mouthpiece (bottom-left)
				break;

			case 'instagram': // solid camera body, lens ring + viewfinder knockouts
				$this->fill_round_rect( $buf, 18, 18, 94, 94, 20, $g );
				imagealphablending( $buf, false );
				imagefilledellipse( $buf, 56, 56, 62, 62, $clear ); // lens gap
				imagealphablending( $buf, true );
				imagefilledellipse( $buf, 56, 56, 40, 40, $g );      // lens centre → gold ring
				imagealphablending( $buf, false );
				imagefilledellipse( $buf, 80, 32, 11, 11, $clear );  // viewfinder dot
				imagealphablending( $buf, true );
				break;

			case 'web': // solid globe with knocked-out grid
				imagefilledellipse( $buf, 56, 56, 78, 78, $g );
				imagealphablending( $buf, false );
				imagesetthickness( $buf, 5 );
				imageline( $buf, 56, 17, 56, 95, $clear );           // meridian
				imageline( $buf, 17, 56, 95, 56, $clear );           // equator
				imageellipse( $buf, 56, 56, 38, 78, $clear );        // longitude curve
				imageellipse( $buf, 56, 56, 78, 44, $clear );        // latitude curve
				imagesetthickness( $buf, 1 );
				imagealphablending( $buf, true );
				break;

			default: // neutral node
				imagefilledellipse( $buf, 56, 56, 22, 22, $g );
		}

		imagealphablending( $img, true );
		imagecopyresampled( $img, $buf, (int) round( $cx - $box / 2 ), (int) round( $cy - $box / 2 ), 0, 0, $box, $box, $b, $b );
		imagedestroy( $buf );
	}

	/**
	 * Attempt to load the vendor logo as a GD image. Any failure (missing
	 * meta, deleted file, unreadable/corrupt image) returns null so the
	 * caller falls back to the monogram.
	 *
	 * @return GdImage|null
	 */
	private function load_logo( $post_id ) {
		$logo_id = (int) get_post_meta( $post_id, '_oc_logo_id', true );
		if ( ! $logo_id ) {
			return null;
		}
		$file = get_attached_file( $logo_id );
		if ( ! $file || ! file_exists( $file ) || ! is_readable( $file ) ) {
			return null;
		}
		$data = file_get_contents( $file );
		if ( false === $data || '' === $data ) {
			return null;
		}
		$gd = @imagecreatefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- corrupt uploads must degrade, not fatal.
		return $gd ?: null;
	}

	/**
	 * First letters of up to two words of the business name, uppercased.
	 */
	private function monogram( $name ) {
		$words = preg_split( '/\s+/', trim( (string) $name ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $words ) {
			return '';
		}
		$mono = '';
		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			$mono .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
		}
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $mono ) : strtoupper( $mono );
	}

	/**
	 * Human display string for the profile URL — host + path, no scheme,
	 * no tracking params.
	 */
	private function display_url( $url ) {
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( '' === $host ) {
			return '';
		}
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		return $host . untrailingslashit( $path );
	}

	/* ------------------------------------------------------------------ */
	/* Text helpers — every draw goes through text() so the whole card      */
	/* still renders with ZERO font files present (GD built-in font 5).     */
	/* ------------------------------------------------------------------ */

	/**
	 * Font file paths. Files may or may not exist — text()/text_width()
	 * check per call.
	 */
	private function fonts() {
		$dir = OC_PLUGIN_DIR . 'assets/fonts/';
		return [
			'regular' => $dir . 'Inter-Regular.ttf',
			'bold'    => $dir . 'Inter-Bold.ttf',
			'display' => $dir . 'PlayfairDisplay-Bold.ttf',
		];
	}

	private function can_ttf( $font ) {
		return $font && function_exists( 'imagettftext' ) && function_exists( 'imagettfbbox' ) && file_exists( $font );
	}

	/**
	 * Draw text at a BASELINE coordinate. Uses the TTF only when both the
	 * file and FreeType support exist; otherwise falls back to
	 * imagestring() font 5 (baseline approximated, non-ASCII stripped —
	 * the built-in font is latin-only).
	 */
	private function text( $img, $size, $x, $y, $color, $text, $font ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return;
		}
		if ( $this->can_ttf( $font ) ) {
			imagettftext( $img, $size, 0, (int) $x, (int) $y, $color, $font, $text );
			return;
		}
		$ascii = str_replace(
			[ '•', '–', '—', '’', '‘', '“', '”', '…' ],
			[ '*', '-', '-', "'", "'", '"', '"', '...' ],
			$text
		);
		$ascii = preg_replace( '/[^\x20-\x7E]/', '', $ascii );
		imagestring( $img, 5, (int) $x, (int) $y - 13, $ascii, $color );
	}

	/**
	 * Pixel width of a string at a size — imagettfbbox when possible,
	 * fixed-width estimate for the bitmap fallback.
	 */
	private function text_width( $text, $font, $size ) {
		$text = (string) $text;
		if ( $this->can_ttf( $font ) ) {
			$box = imagettfbbox( $size, 0, $font, $text );
			if ( is_array( $box ) ) {
				return abs( $box[2] - $box[0] );
			}
		}
		return strlen( $text ) * imagefontwidth( 5 );
	}

	/**
	 * Word-wrap $text to at most $max_lines within $max_width, shrinking
	 * the font size until it fits. Returns [ lines[], size ].
	 */
	private function wrap_fit( $text, $font, $size, $max_width, $max_lines = 2, $min_size = 22 ) {
		for ( $s = $size; $s >= $min_size; $s -= 2 ) {
			$lines = $this->wrap( $text, $font, $s, $max_width );
			if ( count( $lines ) > $max_lines ) {
				continue;
			}
			$fits = true;
			foreach ( $lines as $line ) {
				if ( $this->text_width( $line, $font, $s ) > $max_width ) {
					$fits = false;
					break;
				}
			}
			if ( $fits ) {
				return [ $lines, $s ];
			}
		}
		// Last resort: minimum size, hard-capped line count.
		$lines = array_slice( $this->wrap( $text, $font, $min_size, $max_width ), 0, $max_lines );
		return [ $lines ?: [ '' ], $min_size ];
	}

	/**
	 * Greedy word wrap using measured widths (single overlong words stay
	 * on their own line — wrap_fit()'s shrink loop handles them).
	 */
	private function wrap( $text, $font, $size, $max_width ) {
		$words = preg_split( '/\s+/', trim( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $words ) {
			return [ '' ];
		}
		$lines   = [];
		$current = '';
		foreach ( $words as $word ) {
			$try = ( '' === $current ) ? $word : $current . ' ' . $word;
			if ( '' !== $current && $this->text_width( $try, $font, $size ) > $max_width ) {
				$lines[] = $current;
				$current = $word;
			} else {
				$current = $try;
			}
		}
		if ( '' !== $current ) {
			$lines[] = $current;
		}
		return $lines;
	}

	/**
	 * Truncate a single line with an ellipsis so it never exceeds $max_width.
	 */
	private function fit_ellipsis( $text, $font, $size, $max_width ) {
		$text = (string) $text;
		if ( $this->text_width( $text, $font, $size ) <= $max_width ) {
			return $text;
		}
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		while ( $len > 4 ) {
			$len--;
			$cut = ( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $len ) : substr( $text, 0, $len ) ) . '...';
			if ( $this->text_width( $cut, $font, $size ) <= $max_width ) {
				return $cut;
			}
		}
		return $text;
	}

	/* ------------------------------------------------------------------ */
	/* QR code                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Draw the QR for $url into the card at ($x,$y) sized $size. Uses the
	 * bundled phpqrcode when present; on ANY failure draws a gold-bordered
	 * "View online" placeholder so the card still ships.
	 */
	private function draw_qr( $img, $url, $x, $y, $size, $gold, $gray, $fonts ) {
		if ( file_exists( OC_PLUGIN_DIR . 'includes/lib/phpqrcode.php' ) && $this->qr_from_library( $img, $url, $x, $y, $size ) ) {
			return;
		}

		// Placeholder — gold 3px bordered box with "View online" + host.
		$white = imagecolorallocate( $img, 255, 255, 255 );
		imagefilledrectangle( $img, $x, $y, $x + $size - 1, $y + $size - 1, $gold );
		imagefilledrectangle( $img, $x + 3, $y + 3, $x + $size - 4, $y + $size - 4, $white );

		$line1 = 'View online';
		$line2 = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
		$cx    = $x + (int) round( $size / 2 );
		$w1    = $this->text_width( $line1, $fonts['bold'], 12 );
		$this->text( $img, 12, $cx - (int) round( $w1 / 2 ), $y + (int) round( $size / 2 ) - 8, $gray, $line1, $fonts['bold'] );
		if ( '' !== $line2 ) {
			$line2 = $this->fit_ellipsis( $line2, $fonts['regular'], 11, $size - 20 );
			$w2    = $this->text_width( $line2, $fonts['regular'], 11 );
			$this->text( $img, 11, $cx - (int) round( $w2 / 2 ), $y + (int) round( $size / 2 ) + 16, $gray, $line2, $fonts['regular'] );
		}
	}

	/**
	 * Generate the QR via the bundled phpqrcode and copy it into the card.
	 * The library predates PHP 8 and spits deprecation notices at compile
	 * AND run time — with display_errors on, that output would land before
	 * our headers and corrupt the download. So the whole excursion runs
	 * with deprecations muted and inside an output buffer that is always
	 * discarded. Returns false on any failure (caller draws a placeholder).
	 */
	private function qr_from_library( $img, $url, $x, $y, $size ) {
		$previous = error_reporting();
		error_reporting( $previous & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING );
		ob_start();
		$tmp = '';
		$ok  = false;
		try {
			require_once OC_PLUGIN_DIR . 'includes/lib/phpqrcode.php';
			if ( class_exists( 'QRcode' ) ) {
				$tmp = get_temp_dir() . uniqid( 'oc_qr_' ) . '.png';
				QRcode::png( $url, $tmp, defined( 'QR_ECLEVEL_M' ) ? QR_ECLEVEL_M : 0, 7, 2 );
				if ( file_exists( $tmp ) ) {
					$qr = @imagecreatefrompng( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( $qr ) {
						imagecopyresampled( $img, $qr, $x, $y, 0, 0, $size, $size, imagesx( $qr ), imagesy( $qr ) );
						imagedestroy( $qr );
						$ok = true;
					}
				}
			}
		} catch ( Throwable $e ) {
			$ok = false;
		} finally {
			if ( $tmp && file_exists( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			ob_end_clean();
			error_reporting( $previous );
		}
		return $ok;
	}

	/* ------------------------------------------------------------------ */
	/* Output                                                              */
	/* ------------------------------------------------------------------ */

	private function output_png( $img, $slug, $inline = false ) {
		nocache_headers();
		header( 'Content-Type: image/png' );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $slug . '-business-card.png"' );
		imagepng( $img );
		imagedestroy( $img );
		exit;
	}

	private function output_pdf( $img, $slug ) {
		// The card's rounded corners are transparent; JPEG has no alpha, so
		// flatten onto white first or the corners come out black in the PDF.
		$flat  = imagecreatetruecolor( self::W, self::H );
		imagefilledrectangle( $flat, 0, 0, self::W, self::H, imagecolorallocate( $flat, 255, 255, 255 ) );
		imagealphablending( $flat, true );
		imagecopy( $flat, $img, 0, 0, 0, 0, self::W, self::H );
		imagedestroy( $img );
		$img = $flat;

		ob_start();
		imagejpeg( $img, null, 90 );
		$jpeg = ob_get_clean();
		imagedestroy( $img );

		$pdf = $this->build_pdf( $jpeg, self::W, self::H );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '-business-card.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput -- binary PDF stream, not HTML.
		exit;
	}

	/**
	 * Hand-built single-page PDF-1.4 embedding the card as a DCTDecode
	 * (JPEG) image XObject. Page is 3.5in x 2in (371.25 x 212.14 pt) —
	 * the 1050x600 ratio at print size. Byte offsets are recorded on the
	 * running buffer BEFORE each object is appended so the xref table is
	 * exact (Preview/Acrobat both validate it).
	 *
	 * @param string $jpeg Raw JPEG bytes.
	 * @param int    $w    Image pixel width.
	 * @param int    $h    Image pixel height.
	 * @return string Complete PDF byte stream.
	 */
	private function build_pdf( $jpeg, $w, $h ) {
		$page_w = '371.25';
		$page_h = '212.14';

		$pdf     = "%PDF-1.4\n";
		$offsets = [];

		$offsets[1] = strlen( $pdf );
		$pdf       .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

		$offsets[2] = strlen( $pdf );
		$pdf       .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

		$offsets[3] = strlen( $pdf );
		$pdf       .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$page_w} {$page_h}] "
			. "/Resources << /ProcSet [/PDF /ImageC] /XObject << /Im1 4 0 R >> >> "
			. "/Contents 5 0 R >>\nendobj\n";

		$offsets[4] = strlen( $pdf );
		$pdf       .= "4 0 obj\n<< /Type /XObject /Subtype /Image /Width {$w} /Height {$h} "
			. '/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $jpeg ) . " >>\n"
			. "stream\n" . $jpeg . "\nendstream\nendobj\n";

		$stream     = "q\n{$page_w} 0 0 {$page_h} 0 0 cm\n/Im1 Do\nQ\n";
		$offsets[5] = strlen( $pdf );
		$pdf       .= "5 0 obj\n<< /Length " . strlen( $stream ) . " >>\nstream\n" . $stream . "endstream\nendobj\n";

		$xref = strlen( $pdf );
		$pdf .= "xref\n0 6\n";
		$pdf .= "0000000000 65535 f \n";
		for ( $i = 1; $i <= 5; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

		return $pdf;
	}
}
