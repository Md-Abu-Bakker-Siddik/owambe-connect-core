<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

class OC_Widget_About_Blocks extends Widget_Base {

	public function get_name()       { return 'oc_about_blocks'; }
	public function get_title()      { return __( 'OC About Page', 'owambe-connect-core' ); }
	public function get_icon()       { return 'eicon-info-circle'; }
	public function get_categories() { return [ 'owambe-connect' ]; }
	public function get_keywords()   { return [ 'about', 'story', 'mission', 'values', 'owambe' ]; }
	public function get_script_depends() { return [ 'oc-frontend' ]; }

	protected function register_controls() {

		// ── Story ──────────────────────────────────────
		$this->start_controls_section( 'section_story', [
			'label' => __( 'Story Section', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_story', [
			'label'        => __( 'Show Story Section', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'story_eyebrow', [
			'label'     => __( 'Eyebrow', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Our story', 'owambe-connect-core' ),
			'condition' => [ 'show_story' => 'yes' ],
		] );

		$this->add_control( 'story_heading', [
			'label'     => __( 'Heading', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Our story', 'owambe-connect-core' ),
			'condition' => [ 'show_story' => 'yes' ],
		] );

		// Default body copy is the client-supplied Story text from the feedback xlsx.
		$this->add_control( 'story_body', [
			'label'     => __( 'Body Text', 'owambe-connect-core' ),
			'type'      => Controls_Manager::WYSIWYG,
			'default'   => __( "At Owambe Connect, we believe culturally rich celebrations deserve to be seen, valued, and beautifully represented.\n\nWe created Owambe Connect to make it easier for people planning weddings, parties, corporate events, traditional ceremonies, birthdays, and community celebrations to discover trusted vendors who truly understand the beauty of multicultural events.\n\nFrom photographers and caterers to decorators, DJs, makeup artists, event planners, venues, and beyond, our platform connects clients with vendors who bring culture, creativity, and unforgettable experiences to life.\n\nBut Owambe Connect is more than just a directory. It is a growing community built to support visibility, connection, collaboration, and growth for vendors across the UK's diverse event industry.", 'owambe-connect-core' ),
			'condition' => [ 'show_story' => 'yes' ],
		] );

		$this->add_control( 'story_image', [
			'label'     => __( 'Story Image (optional)', 'owambe-connect-core' ),
			'type'      => Controls_Manager::MEDIA,
			'condition' => [ 'show_story' => 'yes' ],
		] );

		$this->end_controls_section();

		// ── Mission / Vision ───────────────────────────
		$this->start_controls_section( 'section_mission', [
			'label' => __( 'Mission & Vision', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_mission', [
			'label'        => __( 'Show Mission & Vision', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'mission_title', [
			'label'     => __( 'Mission Title', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Our mission', 'owambe-connect-core' ),
			'condition' => [ 'show_mission' => 'yes' ],
		] );

		// Default Mission copy is the client-supplied text from the feedback xlsx.
		$this->add_control( 'mission_text', [
			'label'     => __( 'Mission Text', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXTAREA,
			'default'   => __( 'To connect people with trusted vendors, simplify event planning, and help businesses grow within the communities they serve.', 'owambe-connect-core' ),
			'condition' => [ 'show_mission' => 'yes' ],
		] );

		$this->add_control( 'vision_title', [
			'label'     => __( 'Vision Title', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Our vision', 'owambe-connect-core' ),
			'condition' => [ 'show_mission' => 'yes' ],
		] );

		// Default Vision copy is the client-supplied text from the feedback xlsx.
		$this->add_control( 'vision_text', [
			'label'     => __( 'Vision Text', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXTAREA,
			'default'   => __( "Owambe Connect was created to give culturally rich events the visibility, elegance, and trusted vendor network they deserve. Our vision is to build the UK's leading platform for planning, discovering and connecting with exceptional event vendors across African, Caribbean, South Asian, multicultural, luxury, and contemporary celebrations.", 'owambe-connect-core' ),
			'condition' => [ 'show_mission' => 'yes' ],
		] );

		$this->end_controls_section();

		// ── Values ─────────────────────────────────────
		$this->start_controls_section( 'section_values', [
			'label' => __( 'Values Grid', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_values', [
			'label'        => __( 'Show Values Grid', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'values_heading', [
			'label'     => __( 'Heading', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'What we stand for', 'owambe-connect-core' ),
			'condition' => [ 'show_values' => 'yes' ],
		] );

		$repeater = new Repeater();
		$repeater->add_control( 'icon', [
			'label'   => __( 'Icon / Emoji', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => '🤝',
		] );
		$repeater->add_control( 'title', [
			'label'   => __( 'Title', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXT,
			'default' => __( 'A value', 'owambe-connect-core' ),
		] );
		$repeater->add_control( 'description', [
			'label'   => __( 'Description', 'owambe-connect-core' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => __( 'A short paragraph about why this value matters.', 'owambe-connect-core' ),
		] );

		$this->add_control( 'values_items', [
			'label'       => __( 'Values', 'owambe-connect-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => '{{{ title }}}',
			'condition'   => [ 'show_values' => 'yes' ],
			'default'     => [
				[ 'icon' => '🤝', 'title' => __( 'Cultural fluency', 'owambe-connect-core' ),
				  'description' => __( 'Every vendor genuinely understands the communities they serve.', 'owambe-connect-core' ) ],
				[ 'icon' => '💬', 'title' => __( 'Direct, no middlemen', 'owambe-connect-core' ),
				  'description' => __( 'You message vendors on their channels — WhatsApp, Instagram, email. We don\'t sit between you and your booking.', 'owambe-connect-core' ) ],
				[ 'icon' => '✨', 'title' => __( 'Quality over volume', 'owambe-connect-core' ),
				  'description' => __( 'Every listing is reviewed before going live. We\'d rather have 50 trusted vendors than 5,000 noisy ones.', 'owambe-connect-core' ) ],
				[ 'icon' => '🌍', 'title' => __( 'Built for UK\'s real mix', 'owambe-connect-core' ),
				  'description' => __( 'Built for UK\'s real mix, celebrating African, Caribbean, South Asian, multicultural, luxury, and contemporary events.', 'owambe-connect-core' ) ],
			],
		] );

		$this->end_controls_section();

		// ── Timeline ───────────────────────────────────
		$this->start_controls_section( 'section_timeline', [
			'label' => __( 'Milestones / Timeline', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_timeline', [
			'label'        => __( 'Show Milestones', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'no',
		] );

		$this->add_control( 'timeline_heading', [
			'label'     => __( 'Heading', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'How we got here', 'owambe-connect-core' ),
			'condition' => [ 'show_timeline' => 'yes' ],
		] );

		$tl = new Repeater();
		$tl->add_control( 'date', [ 'label' => __( 'Date / Label', 'owambe-connect-core' ), 'type' => Controls_Manager::TEXT, 'default' => __( '2024', 'owambe-connect-core' ) ] );
		$tl->add_control( 'title', [ 'label' => __( 'Title', 'owambe-connect-core' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'A milestone', 'owambe-connect-core' ) ] );
		$tl->add_control( 'description', [ 'label' => __( 'Description', 'owambe-connect-core' ), 'type' => Controls_Manager::TEXTAREA, 'default' => __( 'What happened.', 'owambe-connect-core' ) ] );

		$this->add_control( 'timeline_items', [
			'label'       => __( 'Milestones', 'owambe-connect-core' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $tl->get_controls(),
			'title_field' => '{{{ date }}} — {{{ title }}}',
			'condition'   => [ 'show_timeline' => 'yes' ],
			'default'     => [
				[ 'date' => __( '2024', 'owambe-connect-core' ), 'title' => __( 'The idea', 'owambe-connect-core' ), 'description' => __( 'Born out of frustration planning a family wedding and finding zero culturally fluent vendors in one place.', 'owambe-connect-core' ) ],
				[ 'date' => __( 'Early 2025', 'owambe-connect-core' ), 'title' => __( 'First vendors onboarded', 'owambe-connect-core' ), 'description' => __( 'Hand-selected initial vendors across catering, photography and decor — all serving real communities, not stock-photo "diversity".', 'owambe-connect-core' ) ],
				[ 'date' => __( '2025', 'owambe-connect-core' ), 'title' => __( 'Public launch', 'owambe-connect-core' ), 'description' => __( 'Opened the directory to the public, free for both vendors and planners during our MVP.', 'owambe-connect-core' ) ],
			],
		] );

		$this->end_controls_section();

		// ── CTA ────────────────────────────────────────
		$this->start_controls_section( 'section_cta', [
			'label' => __( 'CTA Section', 'owambe-connect-core' ),
		] );

		$this->add_control( 'show_cta', [
			'label'        => __( 'Show CTA Section', 'owambe-connect-core' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'cta_heading', [
			'label'     => __( 'CTA Heading', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Ready to find your vendors?', 'owambe-connect-core' ),
			'condition' => [ 'show_cta' => 'yes' ],
		] );

		$this->add_control( 'cta_text', [
			'label'     => __( 'CTA Text', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXTAREA,
			'default'   => __( 'Browse hundreds of trusted event vendors across the UK — or list your business with us.', 'owambe-connect-core' ),
			'condition' => [ 'show_cta' => 'yes' ],
		] );

		$this->add_control( 'cta_primary_text', [
			'label'     => __( 'Primary Button Label', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'Find vendors', 'owambe-connect-core' ),
			'condition' => [ 'show_cta' => 'yes' ],
		] );

		$this->add_control( 'cta_primary_url', [
			'label'     => __( 'Primary Button URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'show_cta' => 'yes' ],
		] );

		$this->add_control( 'cta_secondary_text', [
			'label'     => __( 'Secondary Button Label', 'owambe-connect-core' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => __( 'List your business', 'owambe-connect-core' ),
			'condition' => [ 'show_cta' => 'yes' ],
		] );

		$this->add_control( 'cta_secondary_url', [
			'label'     => __( 'Secondary Button URL', 'owambe-connect-core' ),
			'type'      => Controls_Manager::URL,
			'condition' => [ 'show_cta' => 'yes' ],
		] );

		$this->end_controls_section();

		// ── Style ──────────────────────────────────────
		$this->start_controls_section( 'section_style', [
			'label' => __( 'Style', 'owambe-connect-core' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );
		$this->add_responsive_control( 'section_padding', [
			'label'      => __( 'Section Padding', 'owambe-connect-core' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .oc-about' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$payload = [
			'show_story'    => $s['show_story']    ?? 'yes',
			'story_eyebrow' => $s['story_eyebrow'] ?? '',
			'story_heading' => $s['story_heading'] ?? '',
			'story_body'    => $s['story_body']    ?? '',
			'story_image'   => isset( $s['story_image']['url'] ) ? $s['story_image']['url'] : '',

			'show_mission'  => $s['show_mission']  ?? 'yes',
			'mission_title' => $s['mission_title'] ?? '',
			'mission_text'  => $s['mission_text']  ?? '',
			'vision_title'  => $s['vision_title']  ?? '',
			'vision_text'   => $s['vision_text']   ?? '',

			'show_values'    => $s['show_values']    ?? 'yes',
			'values_heading' => $s['values_heading'] ?? '',
			'values_items'   => $s['values_items']   ?? [],

			'show_timeline'    => $s['show_timeline']    ?? 'no',
			'timeline_heading' => $s['timeline_heading'] ?? '',
			'timeline_items'   => $s['timeline_items']   ?? [],

			'show_cta'           => $s['show_cta']           ?? 'yes',
			'cta_heading'        => $s['cta_heading']        ?? '',
			'cta_text'           => $s['cta_text']           ?? '',
			'cta_primary_text'   => $s['cta_primary_text']   ?? '',
			'cta_primary_url'    => isset( $s['cta_primary_url']['url'] )   ? $s['cta_primary_url']['url']   : '',
			'cta_secondary_text' => $s['cta_secondary_text'] ?? '',
			'cta_secondary_url'  => isset( $s['cta_secondary_url']['url'] ) ? $s['cta_secondary_url']['url'] : '',
		];

		echo oc_get_template( 'shortcode-about-blocks.php', $payload );
	}
}
