<?php
/**
 * STC Product Showcase Elementor widget.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

/**
 * Class STC_PSP_Widget_Showcase
 */
class STC_PSP_Widget_Showcase extends Widget_Base {

	/**
	 * Widget machine name.
	 */
	public function get_name(): string {
		return 'stc_product_showcase';
	}

	/**
	 * Widget display title.
	 */
	public function get_title(): string {
		return __( 'STC Product Showcase', 'stc-product-showcase-pro' );
	}

	/**
	 * Widget icon.
	 */
	public function get_icon(): string {
		return 'eicon-products';
	}

	/**
	 * Widget categories.
	 *
	 * @return array<int,string>
	 */
	public function get_categories(): array {
		return array( 'stc-psp' );
	}

	/**
	 * Search keywords.
	 *
	 * @return array<int,string>
	 */
	public function get_keywords(): array {
		return array( 'product', 'woocommerce', 'showcase', 'catalog', 'enquiry', 'stc' );
	}

	/**
	 * Frontend script dependencies.
	 *
	 * @return array<int,string>
	 */
	public function get_script_depends(): array {
		return array( 'stc-psp-frontend' );
	}

	/**
	 * Frontend style dependencies.
	 *
	 * @return array<int,string>
	 */
	public function get_style_depends(): array {
		return array( 'stc-psp-frontend' );
	}

	/**
	 * Register all controls.
	 */
	protected function register_controls(): void {
		$this->register_query_controls();
		$this->register_layout_controls();
		$this->register_elements_controls();
		$this->register_description_controls();
		$this->register_features_controls();
		$this->register_buttons_controls();
		$this->register_style_controls();
	}

	/* ------------------------------------------------------------------ *
	 * QUERY CONTROLS
	 * ------------------------------------------------------------------ */
	private function register_query_controls(): void {
		$this->start_controls_section(
			'section_query',
			array( 'label' => __( 'Product Query', 'stc-product-showcase-pro' ) )
		);

		$this->add_control(
			'source',
			array(
				'label'   => __( 'Source', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'latest',
				'options' => array(
					'latest'       => __( 'Latest Products', 'stc-product-showcase-pro' ),
					'featured'     => __( 'Featured Products', 'stc-product-showcase-pro' ),
					'best_selling' => __( 'Best Selling', 'stc-product-showcase-pro' ),
					'category'     => __( 'By Category', 'stc-product-showcase-pro' ),
					'subcategory'  => __( 'By Subcategory', 'stc-product-showcase-pro' ),
					'brand'        => __( 'By Brand', 'stc-product-showcase-pro' ),
					'tags'         => __( 'By Tags', 'stc-product-showcase-pro' ),
					'sku'          => __( 'By SKU', 'stc-product-showcase-pro' ),
					'manual'       => __( 'Manual Selection', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_control(
			'categories',
			array(
				'label'       => __( 'Categories', 'stc-product-showcase-pro' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->get_terms_options( 'product_cat' ),
				'label_block' => true,
				'condition'   => array( 'source' => array( 'category', 'subcategory' ) ),
			)
		);

		$brand_tax = STC_PSP_Query::detect_brand_taxonomy();
		$this->add_control(
			'brands',
			array(
				'label'       => __( 'Brands', 'stc-product-showcase-pro' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $brand_tax ? $this->get_terms_options( $brand_tax ) : array(),
				'label_block' => true,
				'condition'   => array( 'source' => 'brand' ),
				'description' => $brand_tax ? '' : __( 'No brand taxonomy detected.', 'stc-product-showcase-pro' ),
			)
		);

		$this->add_control(
			'tags',
			array(
				'label'       => __( 'Tags', 'stc-product-showcase-pro' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->get_terms_options( 'product_tag' ),
				'label_block' => true,
				'condition'   => array( 'source' => 'tags' ),
			)
		);

		$this->add_control(
			'skus',
			array(
				'label'       => __( 'SKUs (comma separated)', 'stc-product-showcase-pro' ),
				'type'        => Controls_Manager::TEXTAREA,
				'label_block' => true,
				'condition'   => array( 'source' => 'sku' ),
			)
		);

		$this->add_control(
			'product_ids',
			array(
				'label'       => __( 'Select Products', 'stc-product-showcase-pro' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->get_product_options(),
				'label_block' => true,
				'condition'   => array( 'source' => 'manual' ),
			)
		);

		$this->add_control(
			'per_page',
			array(
				'label'   => __( 'Products Per Page', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 6,
				'min'     => 1,
				'max'     => 100,
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order By', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'date'       => __( 'Date', 'stc-product-showcase-pro' ),
					'title'      => __( 'Title', 'stc-product-showcase-pro' ),
					'price'      => __( 'Price', 'stc-product-showcase-pro' ),
					'popularity' => __( 'Popularity', 'stc-product-showcase-pro' ),
					'rating'     => __( 'Rating', 'stc-product-showcase-pro' ),
					'menu_order' => __( 'Menu Order', 'stc-product-showcase-pro' ),
					'rand'       => __( 'Random', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'DESC',
				'options' => array(
					'DESC' => __( 'Descending', 'stc-product-showcase-pro' ),
					'ASC'  => __( 'Ascending', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_control(
			'hide_out_of_stock',
			array(
				'label'        => __( 'Hide Out Of Stock', 'stc-product-showcase-pro' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'pagination_type',
			array(
				'label'   => __( 'Pagination', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'numbers',
				'options' => array(
					'none'      => __( 'None', 'stc-product-showcase-pro' ),
					'numbers'   => __( 'Numbered', 'stc-product-showcase-pro' ),
					'load_more' => __( 'Load More', 'stc-product-showcase-pro' ),
					'infinite'  => __( 'Infinite Scroll', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_control(
			'load_more_text',
			array(
				'label'     => __( 'Load More Text', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Load More', 'stc-product-showcase-pro' ),
				'condition' => array( 'pagination_type' => array( 'load_more', 'infinite' ) ),
			)
		);

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------ *
	 * LAYOUT CONTROLS
	 * ------------------------------------------------------------------ */
	private function register_layout_controls(): void {
		$this->start_controls_section(
			'section_layout',
			array( 'label' => __( 'Layout', 'stc-product-showcase-pro' ) )
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Card Layout', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'layout-1',
				'options' => STC_PSP_Renderer::layouts(),
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'stc-product-showcase-pro' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '1',
				'tablet_default' => '1',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
				),
				'selectors'      => array(
					'{{WRAPPER}} .stc-psp-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
				),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Gap', 'stc-product-showcase-pro' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'default'    => array( 'size' => 24, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .stc-psp-grid' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_height_mode',
			array(
				'label'   => __( 'Card Height', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto'   => __( 'Auto', 'stc-product-showcase-pro' ),
					'fixed'  => __( 'Fixed', 'stc-product-showcase-pro' ),
					'custom' => __( 'Custom', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_responsive_control(
			'card_height',
			array(
				'label'      => __( 'Height (px)', 'stc-product-showcase-pro' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vh' ),
				'range'      => array( 'px' => array( 'min' => 100, 'max' => 900 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .stc-psp-card' => 'height: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'card_height_mode' => array( 'fixed', 'custom' ) ),
			)
		);

		// Image controls.
		$this->add_control(
			'image_heading',
			array(
				'label'     => __( 'Image', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'image_aspect',
			array(
				'label'   => __( 'Aspect Ratio', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'ratio-4-3',
				'options' => array(
					'ratio-1-1'  => '1:1',
					'ratio-4-3'  => '4:3',
					'ratio-16-9' => '16:9',
					'ratio-auto' => __( 'Custom / Auto', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_control(
			'image_fit',
			array(
				'label'   => __( 'Object Fit', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'cover',
				'options' => array(
					'cover'   => __( 'Cover', 'stc-product-showcase-pro' ),
					'contain' => __( 'Contain', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_control(
			'image_hover',
			array(
				'label'   => __( 'Hover Effect', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'zoom',
				'options' => array(
					'none'   => __( 'None', 'stc-product-showcase-pro' ),
					'zoom'   => __( 'Zoom', 'stc-product-showcase-pro' ),
					'rotate' => __( 'Rotate', 'stc-product-showcase-pro' ),
					'scale'  => __( 'Scale', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------ *
	 * ELEMENT TOGGLES
	 * ------------------------------------------------------------------ */
	private function register_elements_controls(): void {
		$this->start_controls_section(
			'section_elements',
			array( 'label' => __( 'Elements', 'stc-product-showcase-pro' ) )
		);

		$toggles = array(
			'show_image'       => __( 'Product Image', 'stc-product-showcase-pro' ),
			'show_name'        => __( 'Product Name', 'stc-product-showcase-pro' ),
			'show_sku'         => __( 'SKU', 'stc-product-showcase-pro' ),
			'show_brand'       => __( 'Brand', 'stc-product-showcase-pro' ),
			'show_category'    => __( 'Category', 'stc-product-showcase-pro' ),
			'show_description' => __( 'Short Description', 'stc-product-showcase-pro' ),
			'show_features'    => __( 'Features', 'stc-product-showcase-pro' ),
			'show_price'       => __( 'Price', 'stc-product-showcase-pro' ),
			'show_rating'      => __( 'Rating', 'stc-product-showcase-pro' ),
			'show_tags'        => __( 'Tags', 'stc-product-showcase-pro' ),
			'show_stock'       => __( 'Stock Status', 'stc-product-showcase-pro' ),
		);

		foreach ( $toggles as $key => $label ) {
			$default = in_array( $key, array( 'show_sku', 'show_tags', 'show_rating' ), true ) ? '' : 'yes';
			$this->add_control(
				$key,
				array(
					'label'        => $label,
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => $default,
				)
			);
		}

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------ *
	 * DESCRIPTION CONTROLS
	 * ------------------------------------------------------------------ */
	private function register_description_controls(): void {
		$this->start_controls_section(
			'section_description',
			array(
				'label'     => __( 'Description', 'stc-product-showcase-pro' ),
				'condition' => array( 'show_description' => 'yes' ),
			)
		);

		$this->add_control(
			'desc_limit_type',
			array(
				'label'   => __( 'Limit By', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'words',
				'options' => array(
					'words' => __( 'Word Count', 'stc-product-showcase-pro' ),
					'chars' => __( 'Character Count', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_control(
			'desc_limit',
			array(
				'label'   => __( 'Limit', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 20,
				'min'     => 1,
				'max'     => 1000,
			)
		);

		$this->add_control(
			'enable_read_more',
			array(
				'label'        => __( 'Read More / Less', 'stc-product-showcase-pro' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'read_more_text',
			array(
				'label'     => __( 'Read More Text', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Read More', 'stc-product-showcase-pro' ),
				'condition' => array( 'enable_read_more' => 'yes' ),
			)
		);

		$this->add_control(
			'read_less_text',
			array(
				'label'     => __( 'Read Less Text', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Read Less', 'stc-product-showcase-pro' ),
				'condition' => array( 'enable_read_more' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------ *
	 * FEATURES CONTROLS
	 * ------------------------------------------------------------------ */
	private function register_features_controls(): void {
		$this->start_controls_section(
			'section_features',
			array(
				'label'     => __( 'Features', 'stc-product-showcase-pro' ),
				'condition' => array( 'show_features' => 'yes' ),
			)
		);

		$this->add_control(
			'features_source',
			array(
				'label'   => __( 'Source', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'woocommerce',
				'options' => array(
					'woocommerce'  => __( 'WooCommerce (STC meta)', 'stc-product-showcase-pro' ),
					'custom_field' => __( 'Custom Field', 'stc-product-showcase-pro' ),
					'acf'          => __( 'ACF Field', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_control(
			'features_meta_key',
			array(
				'label'     => __( 'Meta / Field Key', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '',
				'condition' => array( 'features_source' => array( 'custom_field', 'acf' ) ),
			)
		);

		$this->add_control(
			'features_style',
			array(
				'label'   => __( 'Display Style', 'stc-product-showcase-pro' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'checkmark',
				'options' => array(
					'bullet'    => __( 'Bullet List', 'stc-product-showcase-pro' ),
					'checkmark' => __( 'Checkmark List', 'stc-product-showcase-pro' ),
					'icon'      => __( 'Icon List', 'stc-product-showcase-pro' ),
				),
			)
		);

		$this->add_control(
			'features_icon',
			array(
				'label'     => __( 'Custom Icon Class', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => 'dashicons dashicons-yes',
				'condition' => array( 'features_style' => 'icon' ),
			)
		);

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------ *
	 * BUTTON CONTROLS
	 * ------------------------------------------------------------------ */
	private function register_buttons_controls(): void {
		$this->start_controls_section(
			'section_buttons',
			array( 'label' => __( 'Buttons', 'stc-product-showcase-pro' ) )
		);

		// Enquire.
		$this->add_control(
			'enable_enquiry_btn',
			array(
				'label'        => __( 'Enquire Now Button', 'stc-product-showcase-pro' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'enquiry_button_text',
			array(
				'label'     => __( 'Enquire Text', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Enquire Now', 'stc-product-showcase-pro' ),
				'condition' => array( 'enable_enquiry_btn' => 'yes' ),
			)
		);

		$this->add_control(
			'enquiry_icon',
			array(
				'label'     => __( 'Enquire Icon Class', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => 'dashicons dashicons-email-alt',
				'condition' => array( 'enable_enquiry_btn' => 'yes' ),
			)
		);

		$this->add_control(
			'enquiry_anim',
			array(
				'label'     => __( 'Enquire Animation', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'none',
				'options'   => $this->animation_options(),
				'condition' => array( 'enable_enquiry_btn' => 'yes' ),
			)
		);

		// Download.
		$this->add_control(
			'download_heading',
			array(
				'label'     => __( 'Download Catalogue', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'enable_download_btn',
			array(
				'label'        => __( 'Download Button', 'stc-product-showcase-pro' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'download_button_text',
			array(
				'label'     => __( 'Download Text', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Download Catalogue', 'stc-product-showcase-pro' ),
				'condition' => array( 'enable_download_btn' => 'yes' ),
			)
		);

		$this->add_control(
			'download_anim',
			array(
				'label'     => __( 'Download Animation', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'none',
				'options'   => $this->animation_options(),
				'condition' => array( 'enable_download_btn' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Reusable animation options.
	 *
	 * @return array<string,string>
	 */
	private function animation_options(): array {
		return array(
			'none'   => __( 'None', 'stc-product-showcase-pro' ),
			'fade'   => __( 'Fade', 'stc-product-showcase-pro' ),
			'scale'  => __( 'Scale', 'stc-product-showcase-pro' ),
			'pulse'  => __( 'Pulse', 'stc-product-showcase-pro' ),
			'bounce' => __( 'Bounce', 'stc-product-showcase-pro' ),
			'slide'  => __( 'Slide', 'stc-product-showcase-pro' ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * STYLE CONTROLS
	 * ------------------------------------------------------------------ */
	private function register_style_controls(): void {
		// Card style.
		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => __( 'Card', 'stc-product-showcase-pro' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'card_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .stc-psp-card',
			)
		);

		$this->add_control(
			'card_glass',
			array(
				'label'        => __( 'Glassmorphism', 'stc-product-showcase-pro' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'selectors'    => array(
					'{{WRAPPER}} .stc-psp-card' => 'backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); background-color: rgba(255,255,255,0.15);',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .stc-psp-card',
			)
		);

		$this->add_responsive_control(
			'card_radius',
			array(
				'label'      => __( 'Border Radius', 'stc-product-showcase-pro' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .stc-psp-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'stc-product-showcase-pro' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .stc-psp-card-body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .stc-psp-card',
			)
		);

		$this->end_controls_section();

		// Title style.
		$this->start_controls_section(
			'section_style_title',
			array(
				'label' => __( 'Title', 'stc-product-showcase-pro' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Color', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .stc-psp-title a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .stc-psp-title',
			)
		);

		$this->end_controls_section();

		// Buttons style.
		$this->start_controls_section(
			'section_style_buttons',
			array(
				'label' => __( 'Buttons', 'stc-product-showcase-pro' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'btn_text_color',
			array(
				'label'     => __( 'Text Color', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .stc-psp-btn' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'btn_bg_color',
			array(
				'label'     => __( 'Background Color', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .stc-psp-btn' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'btn_hover_color',
			array(
				'label'     => __( 'Hover Background', 'stc-product-showcase-pro' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .stc-psp-btn:hover' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'btn_radius',
			array(
				'label'      => __( 'Border Radius', 'stc-product-showcase-pro' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .stc-psp-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'btn_padding',
			array(
				'label'      => __( 'Padding', 'stc-product-showcase-pro' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .stc-psp-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'btn_typography',
				'selector' => '{{WRAPPER}} .stc-psp-btn',
			)
		);

		$this->end_controls_section();
	}

	/* ------------------------------------------------------------------ *
	 * RENDER
	 * ------------------------------------------------------------------ */

	/**
	 * Render widget output on the frontend.
	 */
	protected function render(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$widget_id = $this->get_id();

		$query = STC_PSP_Query::run( $settings, 1 );

		$pagination = (string) ( $settings['pagination_type'] ?? 'numbers' );
		$ajax_data  = array(
			'settings'    => $this->collect_query_settings( $settings ),
			'max_pages'   => (int) $query->max_num_pages,
			'pagination'  => $pagination,
			'widget_id'   => $widget_id,
		);

		?>
		<div class="stc-psp-wrapper stc-psp-pagination-<?php echo esc_attr( $pagination ); ?>"
			data-stc-psp='<?php echo esc_attr( (string) wp_json_encode( $ajax_data ) ); ?>'>

			<div class="stc-psp-grid" data-page="1">
				<?php echo STC_PSP_Renderer::render_cards( $query, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<?php if ( ! $query->have_posts() && 1 === (int) $query->max_num_pages && 0 === $query->post_count ) : ?>
				<p class="stc-psp-empty"><?php esc_html_e( 'No products found.', 'stc-product-showcase-pro' ); ?></p>
			<?php endif; ?>

			<?php $this->render_pagination( $query, $settings, $pagination ); ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Render pagination / load-more controls.
	 *
	 * @param WP_Query            $query      Query.
	 * @param array<string,mixed> $settings   Settings.
	 * @param string              $pagination Pagination mode.
	 */
	private function render_pagination( WP_Query $query, array $settings, string $pagination ): void {
		if ( (int) $query->max_num_pages <= 1 ) {
			return;
		}

		if ( 'numbers' === $pagination ) {
			echo '<nav class="stc-psp-pagination">';
			echo wp_kses_post(
				paginate_links(
					array(
						'total'     => (int) $query->max_num_pages,
						'current'   => 1,
						'type'      => 'list',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					)
				)
			);
			echo '</nav>';
		} elseif ( in_array( $pagination, array( 'load_more', 'infinite' ), true ) ) {
			printf(
				'<div class="stc-psp-loadmore-wrap"><button type="button" class="stc-psp-loadmore stc-psp-btn">%s</button><span class="stc-psp-spinner" hidden></span></div>',
				esc_html( (string) ( $settings['load_more_text'] ?? __( 'Load More', 'stc-product-showcase-pro' ) ) )
			);
		}
	}

	/**
	 * Reduce settings to only the keys needed for AJAX re-querying.
	 *
	 * @param array<string,mixed> $settings Full settings.
	 * @return array<string,mixed>
	 */
	private function collect_query_settings( array $settings ): array {
		$keys = array(
			'source', 'categories', 'brands', 'tags', 'skus', 'product_ids',
			'per_page', 'orderby', 'order', 'hide_out_of_stock', 'layout',
			'image_aspect', 'image_fit', 'image_hover',
			'show_image', 'show_name', 'show_sku', 'show_brand', 'show_category',
			'show_description', 'show_features', 'show_price', 'show_rating',
			'show_tags', 'show_stock',
			'desc_limit_type', 'desc_limit', 'enable_read_more', 'read_more_text', 'read_less_text',
			'features_source', 'features_meta_key', 'features_style', 'features_icon',
			'enable_enquiry_btn', 'enquiry_button_text', 'enquiry_icon', 'enquiry_anim',
			'enable_download_btn', 'download_button_text', 'download_anim',
		);

		$out = array();
		foreach ( $keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$out[ $key ] = $settings[ $key ];
			}
		}

		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * HELPERS for control option lists
	 * ------------------------------------------------------------------ */

	/**
	 * Get options for a taxonomy as id => name.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<int,string>
	 */
	private function get_terms_options( string $taxonomy ): array {
		$options = array();
		$terms   = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 300,
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->term_id ] = $term->name;
			}
		}

		return $options;
	}

	/**
	 * Get a limited list of products for manual selection.
	 *
	 * @return array<int,string>
	 */
	private function get_product_options(): array {
		$options  = array();
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		foreach ( $products as $pid ) {
			$options[ $pid ] = get_the_title( $pid ) . ' (#' . $pid . ')';
		}

		return $options;
	}
}
