<?php
/**
 * In-admin Developer Guide — shortcodes, hooks, examples, paths.
 *
 * @package OwambeConnect
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Guide {

	const PAGE = 'oc-developer-guide';

	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . OC_CPT,
			__( 'Developer Guide', 'owambe-connect-core' ),
			__( 'Developer Guide', 'owambe-connect-core' ),
			'edit_posts',
			self::PAGE,
			[ $this, 'render' ],
			70
		);
	}

	private function shortcodes() {
		return [
			[
				'tag'      => 'oc_hero_search',
				'desc'     => __( 'Hero with category dropdown + location search. Submits to the directory page.', 'owambe-connect-core' ),
				'attrs'    => [],
				'used_on'  => __( 'Home', 'owambe-connect-core' ),
				'page'     => 'home',
			],
			[
				'tag'      => 'oc_category_grid',
				'desc'     => __( 'Visual grid of vendor categories with vendor counts.', 'owambe-connect-core' ),
				'attrs'    => [ [ 'count', '12', __( 'Maximum number of categories to show.', 'owambe-connect-core' ) ] ],
				'used_on'  => __( 'Home', 'owambe-connect-core' ),
				'page'     => 'home',
			],
			[
				'tag'      => 'oc_featured_vendors',
				'desc'     => __( 'Carousel of featured vendors. Falls back to most recent if none are flagged featured.', 'owambe-connect-core' ),
				'attrs'    => [ [ 'count', '6', __( 'Number of vendors to show.', 'owambe-connect-core' ) ] ],
				'used_on'  => __( 'Home', 'owambe-connect-core' ),
				'page'     => 'home',
			],
			[
				'tag'      => 'oc_directory',
				'desc'     => __( 'Filterable, paginated directory. Supports ?cat=, ?s=, ?location=, ?paged= URL params.', 'owambe-connect-core' ),
				'attrs'    => [],
				'used_on'  => __( 'Vendor Directory', 'owambe-connect-core' ),
				'page'     => 'vendors',
			],
			[
				'tag'      => 'oc_vendor_profile',
				'desc'     => __( 'Single vendor profile (auto-detects current vendor post). Used inside single-oc_vendor.php.', 'owambe-connect-core' ),
				'attrs'    => [],
				'used_on'  => __( 'Vendor profile template', 'owambe-connect-core' ),
				'page'     => '',
			],
			[
				'tag'      => 'oc_register_form',
				'desc'     => __( 'Multi-field vendor application form. Creates a pending vendor + user on submit.', 'owambe-connect-core' ),
				'attrs'    => [],
				'used_on'  => __( 'Vendor Application', 'owambe-connect-core' ),
				'page'     => 'apply',
			],
			[
				'tag'      => 'oc_login_form',
				'desc'     => __( 'Vendor login form. Redirects to the dashboard on success.', 'owambe-connect-core' ),
				'attrs'    => [],
				'used_on'  => __( 'Vendor Login', 'owambe-connect-core' ),
				'page'     => 'vendor-login',
			],
			[
				'tag'      => 'oc_vendor_dashboard',
				'desc'     => __( 'Frontend dashboard for the logged-in vendor — edit listing, change password, view status.', 'owambe-connect-core' ),
				'attrs'    => [],
				'used_on'  => __( 'Vendor Dashboard', 'owambe-connect-core' ),
				'page'     => 'vendor-dashboard',
			],
			[
				'tag'      => 'oc_become_a_vendor_cta',
				'desc'     => __( 'Conversion-focused landing block — hero, features, steps, CTA buttons.', 'owambe-connect-core' ),
				'attrs'    => [],
				'used_on'  => __( 'Become a Vendor', 'owambe-connect-core' ),
				'page'     => 'become-a-vendor',
			],
			[
				'tag'      => 'oc_contact_form',
				'desc'     => __( 'Contact form. Sends to the notification email set in Settings.', 'owambe-connect-core' ),
				'attrs'    => [],
				'used_on'  => __( 'Contact', 'owambe-connect-core' ),
				'page'     => 'contact',
			],
		];
	}

	private function hooks() {
		return [
			[
				'name'    => 'oc_after_vendor_registered',
				'type'    => 'action',
				'args'    => '$post_id, $user_id',
				'desc'    => __( 'Fires after a vendor signs up via the public form.', 'owambe-connect-core' ),
			],
			[
				'name'    => 'oc_after_vendor_approved',
				'type'    => 'action',
				'args'    => '$post_id',
				'desc'    => __( 'Fires after a vendor is approved by an admin (or auto-approved).', 'owambe-connect-core' ),
			],
			[
				'name'    => 'oc_after_vendor_rejected',
				'type'    => 'action',
				'args'    => '$post_id, $reason',
				'desc'    => __( 'Fires after a vendor is rejected.', 'owambe-connect-core' ),
			],
			[
				'name'    => 'oc_mail_from_name',
				'type'    => 'filter',
				'args'    => '$name',
				'desc'    => __( 'Override the From name on all transactional emails.', 'owambe-connect-core' ),
			],
			[
				'name'    => 'oc_mail_from_email',
				'type'    => 'filter',
				'args'    => '$email',
				'desc'    => __( 'Override the From email on all transactional emails.', 'owambe-connect-core' ),
			],
		];
	}

	public function render() {
		?>
		<div class="wrap oc-guide">
			<h1 style="margin-bottom:6px"><?php esc_html_e( 'Owambe Connect — Developer Guide', 'owambe-connect-core' ); ?></h1>
			<p style="margin:0 0 18px;color:#555"><?php esc_html_e( 'Quick reference for everything you can drop into a page or extend in code. The full long-form guide lives in DEVELOPER-GUIDE.md at the project root.', 'owambe-connect-core' ); ?></p>

			<nav class="oc-guide-nav">
				<a href="#shortcodes"><?php esc_html_e( 'Shortcodes', 'owambe-connect-core' ); ?></a>
				<a href="#hooks"><?php esc_html_e( 'Hooks', 'owambe-connect-core' ); ?></a>
				<a href="#data"><?php esc_html_e( 'Data Model', 'owambe-connect-core' ); ?></a>
				<a href="#paths"><?php esc_html_e( 'File Paths', 'owambe-connect-core' ); ?></a>
				<a href="#overrides"><?php esc_html_e( 'Template Overrides', 'owambe-connect-core' ); ?></a>
				<a href="#extending"><?php esc_html_e( 'Extending', 'owambe-connect-core' ); ?></a>
			</nav>

			<!-- ============== Shortcodes ============== -->
			<section id="shortcodes" class="oc-guide-section">
				<h2><?php esc_html_e( 'Shortcodes', 'owambe-connect-core' ); ?></h2>
				<p><?php esc_html_e( 'Drop any of these into a WordPress page or post. They each render a complete, branded section. To include in a theme template, use', 'owambe-connect-core' ); ?> <code>echo do_shortcode('[shortcode]')</code>.</p>

				<table class="widefat striped oc-shortcodes-table">
					<thead><tr>
						<th style="width:200px"><?php esc_html_e( 'Shortcode', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Description', 'owambe-connect-core' ); ?></th>
						<th style="width:200px"><?php esc_html_e( 'Attributes', 'owambe-connect-core' ); ?></th>
						<th style="width:160px"><?php esc_html_e( 'Used on page', 'owambe-connect-core' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $this->shortcodes() as $sc ) : ?>
						<tr>
							<td><code style="background:#FAF7F2;color:#6E0F2C;padding:4px 8px;border-radius:4px;font-weight:600">[<?php echo esc_html( $sc['tag'] ); ?>]</code></td>
							<td><?php echo esc_html( $sc['desc'] ); ?>
								<details style="margin-top:6px">
									<summary style="cursor:pointer;color:#6E0F2C;font-weight:500"><?php esc_html_e( 'Show usage example', 'owambe-connect-core' ); ?></summary>
									<pre style="background:#1F1B1A;color:#FAF7F2;padding:10px 14px;border-radius:6px;margin-top:8px;overflow-x:auto;font-size:13px"><code>[<?php echo esc_html( $sc['tag'] ); ?><?php
									if ( ! empty( $sc['attrs'] ) ) {
										foreach ( $sc['attrs'] as $a ) {
											echo ' ' . esc_html( $a[0] ) . '="' . esc_html( $a[1] ) . '"';
										}
									} ?>]</code></pre>
								</details>
							</td>
							<td>
								<?php if ( empty( $sc['attrs'] ) ) : ?>
									<span style="color:#999">—</span>
								<?php else : foreach ( $sc['attrs'] as $a ) : ?>
									<div style="margin-bottom:6px">
										<code><?php echo esc_html( $a[0] ); ?></code>
										<small style="color:#666">(default: <?php echo esc_html( $a[1] ); ?>)</small>
										<br><small><?php echo esc_html( $a[2] ); ?></small>
									</div>
								<?php endforeach; endif; ?>
							</td>
							<td>
								<?php if ( $sc['page'] ) :
									$page = get_page_by_path( $sc['page'] );
									if ( $page ) : ?>
										<a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . $page->ID ) ); ?>"><?php echo esc_html( $sc['used_on'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $sc['used_on'] ); ?>
									<?php endif; ?>
								<?php else : ?>
									<?php echo esc_html( $sc['used_on'] ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</section>

			<!-- ============== Hooks ============== -->
			<section id="hooks" class="oc-guide-section">
				<h2><?php esc_html_e( 'Hooks (actions & filters)', 'owambe-connect-core' ); ?></h2>
				<p><?php esc_html_e( 'Use these to extend behaviour without modifying the plugin.', 'owambe-connect-core' ); ?></p>
				<table class="widefat striped">
					<thead><tr>
						<th style="width:240px"><?php esc_html_e( 'Hook', 'owambe-connect-core' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'Type', 'owambe-connect-core' ); ?></th>
						<th style="width:200px"><?php esc_html_e( 'Arguments', 'owambe-connect-core' ); ?></th>
						<th><?php esc_html_e( 'Description', 'owambe-connect-core' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $this->hooks() as $h ) : ?>
						<tr>
							<td><code><?php echo esc_html( $h['name'] ); ?></code></td>
							<td><span style="color:<?php echo 'action' === $h['type'] ? '#6E0F2C' : '#A8893D'; ?>;font-weight:600;text-transform:uppercase;font-size:11px"><?php echo esc_html( $h['type'] ); ?></span></td>
							<td><code style="font-size:12px"><?php echo esc_html( $h['args'] ); ?></code></td>
							<td><?php echo esc_html( $h['desc'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<details style="margin-top:14px">
					<summary style="cursor:pointer;color:#6E0F2C;font-weight:600"><?php esc_html_e( 'Example: notify Slack on every new vendor', 'owambe-connect-core' ); ?></summary>
					<pre style="background:#1F1B1A;color:#FAF7F2;padding:14px 18px;border-radius:6px;margin-top:8px;overflow-x:auto"><code>add_action( 'oc_after_vendor_approved', function ( $post_id ) {
    $title = get_the_title( $post_id );
    wp_remote_post( 'https://hooks.slack.com/services/...', [
        'body' => json_encode( [ 'text' => "🎉 New vendor live: $title" ] ),
    ] );
} );</code></pre>
				</details>
			</section>

			<!-- ============== Data ============== -->
			<section id="data" class="oc-guide-section">
				<h2><?php esc_html_e( 'Data Model', 'owambe-connect-core' ); ?></h2>
				<table class="widefat">
					<thead><tr><th><?php esc_html_e( 'Object', 'owambe-connect-core' ); ?></th><th><?php esc_html_e( 'Slug / Key', 'owambe-connect-core' ); ?></th><th><?php esc_html_e( 'Notes', 'owambe-connect-core' ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Custom Post Type', 'owambe-connect-core' ); ?></td><td><code><?php echo esc_html( OC_CPT ); ?></code></td><td><?php esc_html_e( 'One post per vendor listing.', 'owambe-connect-core' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Taxonomy', 'owambe-connect-core' ); ?></td><td><code><?php echo esc_html( OC_TAX ); ?></code></td><td><?php esc_html_e( 'Hierarchical. Edit at Vendors → Categories.', 'owambe-connect-core' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'User role', 'owambe-connect-core' ); ?></td><td><code><?php echo esc_html( OC_ROLE ); ?></code></td><td><?php esc_html_e( 'Vendors. Cannot access wp-admin; use frontend dashboard.', 'owambe-connect-core' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Capability', 'owambe-connect-core' ); ?></td><td><code><?php echo esc_html( OC_CAP_EDIT_OWN ); ?></code></td><td><?php esc_html_e( 'Maps to ownership of the vendor post (post_author).', 'owambe-connect-core' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Status: pending', 'owambe-connect-core' ); ?></td><td><code><?php echo esc_html( OC_STATUS_PENDING ); ?></code></td><td><?php esc_html_e( 'Awaiting admin review (default on signup).', 'owambe-connect-core' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Status: approved', 'owambe-connect-core' ); ?></td><td><code><?php echo esc_html( OC_STATUS_APPROVED ); ?></code></td><td><?php esc_html_e( 'Public.', 'owambe-connect-core' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Status: rejected', 'owambe-connect-core' ); ?></td><td><code><?php echo esc_html( OC_STATUS_REJECTED ); ?></code></td><td><?php esc_html_e( 'Hidden; vendor sees rejection reason and can resubmit.', 'owambe-connect-core' ); ?></td></tr>
					</tbody>
				</table>

				<h3 style="margin-top:24px"><?php esc_html_e( 'Vendor profile meta keys', 'owambe-connect-core' ); ?></h3>
				<p><?php esc_html_e( 'Single source of truth: oc_vendor_fields() in includes/helpers.php. Add a key there and it auto-appears in the admin meta box.', 'owambe-connect-core' ); ?></p>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Meta key', 'owambe-connect-core' ); ?></th><th><?php esc_html_e( 'Label', 'owambe-connect-core' ); ?></th><th><?php esc_html_e( 'Type', 'owambe-connect-core' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( oc_vendor_fields() as $key => $f ) : ?>
						<tr>
							<td><code><?php echo esc_html( $key ); ?></code></td>
							<td><?php echo esc_html( $f['label'] ); ?></td>
							<td><?php echo esc_html( $f['type'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</section>

			<!-- ============== Paths ============== -->
			<section id="paths" class="oc-guide-section">
				<h2><?php esc_html_e( 'File Paths', 'owambe-connect-core' ); ?></h2>
				<table class="widefat">
					<tbody>
						<tr><td><?php esc_html_e( 'Plugin', 'owambe-connect-core' ); ?></td><td><code>wp-content/plugins/owambe-connect-core/</code></td></tr>
						<tr><td><?php esc_html_e( 'Theme', 'owambe-connect-core' ); ?></td><td><code>wp-content/themes/owambe-connect/</code></td></tr>
						<tr><td><?php esc_html_e( 'Templates', 'owambe-connect-core' ); ?></td><td><code>plugins/owambe-connect-core/templates/</code></td></tr>
						<tr><td><?php esc_html_e( 'Frontend CSS', 'owambe-connect-core' ); ?></td><td><code>plugins/owambe-connect-core/assets/css/oc-frontend.css</code></td></tr>
						<tr><td><?php esc_html_e( 'Frontend JS', 'owambe-connect-core' ); ?></td><td><code>plugins/owambe-connect-core/assets/js/oc-frontend.js</code></td></tr>
					</tbody>
				</table>
			</section>

			<!-- ============== Overrides ============== -->
			<section id="overrides" class="oc-guide-section">
				<h2><?php esc_html_e( 'Template Overrides', 'owambe-connect-core' ); ?></h2>
				<p><?php esc_html_e( 'Copy any plugin template into your theme to customise it without losing changes on plugin update.', 'owambe-connect-core' ); ?></p>
				<pre style="background:#1F1B1A;color:#FAF7F2;padding:14px 18px;border-radius:6px;overflow-x:auto"><code># From:
wp-content/plugins/owambe-connect-core/templates/partials/vendor-card.php

# To:
wp-content/themes/owambe-connect/owambe-connect/partials/vendor-card.php</code></pre>
				<p><?php esc_html_e( 'Same pattern as WooCommerce. The plugin auto-detects the theme override.', 'owambe-connect-core' ); ?></p>
			</section>

			<!-- ============== Extending ============== -->
			<section id="extending" class="oc-guide-section">
				<h2><?php esc_html_e( 'Adding a new vendor field', 'owambe-connect-core' ); ?></h2>
				<ol>
					<li><?php printf( wp_kses_post( __( 'Add the key to %s.', 'owambe-connect-core' ) ), '<code>oc_vendor_fields()</code> <small>(includes/helpers.php)</small>' ); ?></li>
					<li><?php esc_html_e( 'Add a field to the registration form template (templates/shortcode-register-form.php).', 'owambe-connect-core' ); ?></li>
					<li><?php esc_html_e( 'Add the same field to the dashboard form template (templates/shortcode-vendor-dashboard.php).', 'owambe-connect-core' ); ?></li>
					<li><?php esc_html_e( 'Persist on submit in OC_Registration::handle() and OC_Dashboard::update_listing().', 'owambe-connect-core' ); ?></li>
					<li><?php esc_html_e( 'Display on the profile (templates/shortcode-vendor-profile.php).', 'owambe-connect-core' ); ?></li>
				</ol>
				<p><?php esc_html_e( 'The admin meta box and quick-add form auto-pick up any field added to oc_vendor_fields().', 'owambe-connect-core' ); ?></p>
			</section>
		</div>
		<style>
			.oc-guide h1 { color:#6E0F2C; }
			.oc-guide-nav { display:flex; flex-wrap:wrap; gap:8px; margin:18px 0 30px; padding:14px 16px; background:#FAF7F2; border:1px solid #E4DDD2; border-radius:8px; }
			.oc-guide-nav a { padding:6px 14px; background:#fff; border:1px solid #E4DDD2; border-radius:999px; color:#6E0F2C; text-decoration:none; font-weight:500; font-size:13px; }
			.oc-guide-nav a:hover { background:#6E0F2C; color:#fff; border-color:#6E0F2C; }
			.oc-guide-section { background:#fff; border:1px solid #E4DDD2; border-radius:8px; padding:24px; margin-bottom:18px; }
			.oc-guide-section h2 { font-family:Georgia, serif; color:#6E0F2C; border-bottom:2px solid #C9A961; padding-bottom:10px; margin-top:0; }
			.oc-guide-section h3 { color:#6E0F2C; }
			.oc-guide-section table { background:#fff; }
			.oc-guide-section table th { background:#FAF7F2; }
			.oc-guide-section code { background:#FAF7F2; padding:2px 6px; border-radius:3px; font-size:13px; }
			.oc-guide-section pre code { background:transparent; color:inherit; padding:0; }
			.oc-guide-section details summary { user-select:none; }
		</style>
		<?php
	}
}
