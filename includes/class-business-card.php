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

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			wp_die( esc_html__( 'Image functions unavailable on this server.', 'owambe-connect-core' ) );
		}

		$img  = $this->render( $post->ID );
		$slug = sanitize_file_name( $post->post_name ? $post->post_name : 'vendor-' . $post->ID );

		if ( 'pdf' === $format ) {
			$this->output_pdf( $img, $slug );
		}
		$this->output_png( $img, $slug );
	}

	/* ------------------------------------------------------------------ */
	/* Rendering                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Draw the full card and return the GD image resource.
	 *
	 * Premium layout: a clean, solid HORIZONTAL burgundy header band
	 * (#800020) across the full width — no patterns, no gold overlays on it —
	 * carrying the white logo plate, business name and category/location.
	 * A slim gold hairline sits just BELOW the band (in the white zone, never
	 * over the burgundy), then a white body holds the contact rows on the left
	 * and the QR bottom-right.
	 *
	 * @param int $post_id Vendor post ID.
	 * @return GdImage
	 */
	public function render( $post_id ) {
		$img = imagecreatetruecolor( self::W, self::H );

		$white    = imagecolorallocate( $img, 255, 255, 255 );
		$gold     = imagecolorallocate( $img, 201, 169, 97 );  // #C9A961
		$burgundy = imagecolorallocate( $img, 128, 0, 32 );    // #800020
		$cream    = imagecolorallocate( $img, 232, 221, 201 ); // warm off-white for text on burgundy
		$gray     = imagecolorallocate( $img, 107, 107, 107 ); // #6b6b6b
		$dark     = imagecolorallocate( $img, 51, 51, 51 );    // #333

		$band_h = 250; // height of the solid horizontal burgundy base.

		// White body + solid horizontal burgundy header + slim gold separator.
		// The burgundy is one flat block — nothing is drawn over it in gold.
		imagefilledrectangle( $img, 0, 0, self::W - 1, self::H - 1, $white );
		imagefilledrectangle( $img, 0, 0, self::W - 1, $band_h - 1, $burgundy );
		imagefilledrectangle( $img, 0, $band_h, self::W - 1, $band_h + 3, $gold );

		$fonts = $this->fonts();
		$name  = wp_specialchars_decode( get_the_title( $post_id ), ENT_QUOTES );

		// ------------------------------------------------ Header: white logo plate (left).
		$plate   = 180;
		$plate_x = 56;
		$plate_y = (int) round( ( $band_h - $plate ) / 2 );
		imagefilledrectangle( $img, $plate_x, $plate_y, $plate_x + $plate - 1, $plate_y + $plate - 1, $white );

		$logo = $this->load_logo( $post_id );
		if ( $logo ) {
			// Logo fitted inside the plate with padding, aspect ratio preserved.
			$sw    = imagesx( $logo );
			$sh    = imagesy( $logo );
			$inner = $plate - 40;
			$scale = min( $inner / max( 1, $sw ), $inner / max( 1, $sh ) );
			$dw    = max( 1, (int) round( $sw * $scale ) );
			$dh    = max( 1, (int) round( $sh * $scale ) );
			$dx    = $plate_x + (int) round( ( $plate - $dw ) / 2 );
			$dy    = $plate_y + (int) round( ( $plate - $dh ) / 2 );
			imagecopyresampled( $img, $logo, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh );
			imagedestroy( $logo );
		} else {
			// Monogram — burgundy on the white plate (keeps the burgundy band
			// itself clean; no gold sits on the burgundy).
			$mono = $this->monogram( $name );
			if ( '' !== $mono ) {
				$size = 88;
				while ( $size > 28 && $this->text_width( $mono, $fonts['display'], $size ) > $plate - 48 ) {
					$size -= 4;
				}
				$mw = $this->text_width( $mono, $fonts['display'], $size );
				$cx = $plate_x + (int) round( $plate / 2 );
				$cy = $plate_y + (int) round( $plate / 2 );
				$this->text( $img, $size, $cx - (int) round( $mw / 2 ), $cy + (int) round( $size * 0.36 ), $burgundy, $mono, $fonts['display'] );
			}
		}

		// ------------------------------------------------ Header: name + meta (right of plate).
		$text_x = $plate_x + $plate + 44;
		$text_w = self::W - $text_x - 56;

		$terms    = get_the_terms( $post_id, OC_TAX );
		$category = ( $terms && ! is_wp_error( $terms ) ) ? (string) reset( $terms )->name : '';
		$location = (string) get_post_meta( $post_id, '_oc_location', true );
		$meta     = implode( ' • ', array_filter( [ $category, $location ] ) );

		list( $lines, $ns ) = $this->wrap_fit( $name, $fonts['display'], 48, $text_w, 2, 26 );
		$line_h    = (int) round( $ns * 1.34 );
		$meta_size = 20;
		$meta_gap  = 34;
		$block_h   = count( $lines ) * $line_h + ( '' !== $meta ? ( $meta_gap + $meta_size ) : 0 );
		$top       = (int) round( ( $band_h - $block_h ) / 2 );
		if ( $top < 30 ) {
			$top = 30;
		}

		$baseline = $top + $ns;
		foreach ( $lines as $line ) {
			$this->text( $img, $ns, $text_x, $baseline, $white, $line, $fonts['display'] );
			$baseline += $line_h;
		}
		if ( '' !== $meta ) {
			$meta_y = $baseline - $line_h + $meta_gap + (int) round( $meta_size * 0.5 );
			$this->text( $img, $meta_size, $text_x, $meta_y, $cream, $this->fit_ellipsis( $meta, $fonts['regular'], $meta_size, $text_w ), $fonts['regular'] );
		}

		// ------------------------------------------------ Body: contact rows (left).
		$permalink = get_permalink( $post_id );
		$rows      = [
			[ 'WhatsApp', (string) get_post_meta( $post_id, '_oc_whatsapp', true ) ],
			[ 'Email', (string) get_post_meta( $post_id, '_oc_public_email', true ) ],
			[ 'Web', $this->display_url( $permalink ) ],
		];
		$rows = apply_filters( 'oc_business_card_fields', $rows, $post_id );

		$col_x = 60;

		// Eyebrow + slim gold rule above the contact block (white zone, not on burgundy).
		$this->text( $img, 13, $col_x, 312, $gray, 'GET IN TOUCH', $fonts['bold'] );
		imagefilledrectangle( $img, $col_x, 328, $col_x + 700, 329, $gold );

		$y = 372;
		foreach ( (array) $rows as $row ) {
			$label = isset( $row[0] ) ? (string) $row[0] : '';
			$value = isset( $row[1] ) ? trim( (string) $row[1] ) : '';
			if ( '' === $value ) {
				continue; // skip empty rows.
			}
			imagefilledellipse( $img, $col_x + 7, (int) $y - 6, 10, 10, $burgundy );
			$this->text( $img, 16, $col_x + 28, (int) $y, $dark, $label, $fonts['bold'] );
			$lx = $col_x + 28 + $this->text_width( $label, $fonts['bold'], 16 ) + 14;
			// Values stop short of the QR column (x 814) so they never collide.
			$this->text( $img, 16, (int) $lx, (int) $y, $dark, $this->fit_ellipsis( $value, $fonts['regular'], 16, max( 120, 780 - (int) $lx ) ), $fonts['regular'] );
			$y += 52;
		}

		// Vendor number — subtle caption at the bottom-left of the body.
		$number = function_exists( 'oc_get_vendor_number' ) ? oc_get_vendor_number( $post_id ) : '';
		if ( '' !== $number ) {
			$this->text( $img, 13, $col_x, 566, $gray, 'Vendor #' . $number, $fonts['regular'] );
		}

		// ------------------------------------------------ Body: QR bottom-right + caption.
		$qr = 180;
		$qx = self::W - 56 - $qr;
		$qy = $band_h + (int) round( ( ( self::H - $band_h ) - $qr ) / 2 ) - 14;
		$this->draw_qr( $img, (string) $permalink, $qx, $qy, $qr, $gold, $gray, $fonts );
		$caption = 'Scan to view profile';
		$cw      = $this->text_width( $caption, $fonts['regular'], 12 );
		$this->text( $img, 12, $qx + (int) round( $qr / 2 ) - (int) round( $cw / 2 ), $qy + $qr + 26, $gray, $caption, $fonts['regular'] );

		return $img;
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

	private function output_png( $img, $slug ) {
		nocache_headers();
		header( 'Content-Type: image/png' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '-business-card.png"' );
		imagepng( $img );
		imagedestroy( $img );
		exit;
	}

	private function output_pdf( $img, $slug ) {
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
