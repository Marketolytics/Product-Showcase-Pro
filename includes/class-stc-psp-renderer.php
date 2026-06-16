<?php
/**
 * Renders product showcase cards for all layouts.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Renderer
 */
class STC_PSP_Renderer {

	/**
	 * Map of layout keys to human labels.
	 *
	 * @return array<string,string>
	 */
	public static function layouts(): array {
		return array(
			'layout-1' => __( 'Layout 1 – Image Left / Content Right', 'stc-product-showcase-pro' ),
			'layout-2' => __( 'Layout 2 – Image Right / Content Left', 'stc-product-showcase-pro' ),
			'layout-3' => __( 'Layout 3 – Compact Horizontal', 'stc-product-showcase-pro' ),
			'layout-4' => __( 'Layout 4 – Industrial Premium', 'stc-product-showcase-pro' ),
			'layout-5' => __( 'Layout 5 – Thermax Style', 'stc-product-showcase-pro' ),
			'layout-6' => __( 'Layout 6 – UKL Style', 'stc-product-showcase-pro' ),
			'layout-7' => __( 'Layout 7 – Minimal', 'stc-product-showcase-pro' ),
			'layout-8' => __( 'Layout 8 – Custom Builder', 'stc-product-showcase-pro' ),
		);
	}

	/**
	 * Render a grid/list of cards for a query.
	 *
	 * @param WP_Query            $query    Product query.
	 * @param array<string,mixed> $settings Widget settings.
	 * @return string
	 */
	public static function render_cards( WP_Query $query, array $settings ): string {
		$out = '';
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( $product instanceof WC_Product ) {
					$out .= self::render_card( $product, $settings );
				}
			}
			wp_reset_postdata();
		}

		return $out;
	}

	/**
	 * Render cards from the Elementor "Products" repeater (manual mode).
	 *
	 * Each repeater item may override the title, description, features and
	 * catalogue PDF of the selected product.
	 *
	 * @param array<int,array<string,mixed>> $items    Repeater items.
	 * @param array<string,mixed>            $settings Widget settings.
	 * @return string
	 */
	public static function render_repeater( array $items, array $settings ): string {
		$out = '';
		foreach ( $items as $item ) {
			$product_id = absint( $item['rep_product_id'] ?? 0 );
			if ( ! $product_id ) {
				continue;
			}
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$features = $item['rep_features'] ?? '';
			$overrides = array(
				'title'       => (string) ( $item['rep_title'] ?? '' ),
				'description' => (string) ( $item['rep_description'] ?? '' ),
				'features'    => array_values( array_filter( array_map( 'trim', (array) preg_split( '/\r\n|\r|\n/', (string) $features ) ) ) ),
				'pdf_url'     => (string) ( ( $item['rep_pdf'] ?? array() )['url'] ?? '' ),
			);

			$out .= self::render_card( $product, $settings, $overrides );
		}

		return $out;
	}

	/**
	 * Default body element render order.
	 *
	 * @return array<int,string>
	 */
	public static function element_keys(): array {
		return array( 'brand', 'category', 'name', 'sku', 'rating', 'description', 'features', 'applications', 'tags', 'footer', 'downloads' );
	}

	/**
	 * Human labels for the element ordering control.
	 *
	 * @return array<string,string>
	 */
	public static function element_labels(): array {
		return array(
			'brand'        => __( 'Brand', 'stc-product-showcase-pro' ),
			'category'     => __( 'Category', 'stc-product-showcase-pro' ),
			'name'         => __( 'Product Name', 'stc-product-showcase-pro' ),
			'sku'          => __( 'SKU', 'stc-product-showcase-pro' ),
			'rating'       => __( 'Rating', 'stc-product-showcase-pro' ),
			'description'  => __( 'Description', 'stc-product-showcase-pro' ),
			'features'     => __( 'Features', 'stc-product-showcase-pro' ),
			'applications' => __( 'Applications', 'stc-product-showcase-pro' ),
			'tags'         => __( 'Tags', 'stc-product-showcase-pro' ),
			'footer'       => __( 'Price + Buttons', 'stc-product-showcase-pro' ),
			'downloads'    => __( 'Downloads', 'stc-product-showcase-pro' ),
		);
	}

	/**
	 * Resolve the element render order from settings.
	 *
	 * @param array<string,mixed> $settings Widget settings.
	 * @return array<int,string>
	 */
	private static function get_element_order( array $settings ): array {
		$default = self::element_keys();

		if ( ( $settings['enable_custom_order'] ?? '' ) !== 'yes' ) {
			return $default;
		}

		$repeater = $settings['element_order'] ?? array();
		if ( ! is_array( $repeater ) || empty( $repeater ) ) {
			return $default;
		}

		$order = array();
		foreach ( $repeater as $row ) {
			$el = is_array( $row ) ? (string) ( $row['element'] ?? '' ) : (string) $row;
			if ( $el && in_array( $el, $default, true ) && ! in_array( $el, $order, true ) ) {
				$order[] = $el;
			}
		}

		// Append any elements not explicitly ordered so nothing disappears.
		foreach ( $default as $el ) {
			if ( ! in_array( $el, $order, true ) ) {
				$order[] = $el;
			}
		}

		return $order ?: $default;
	}

	/**
	 * Render a single product card.
	 *
	 * @param WC_Product          $product   Product.
	 * @param array<string,mixed> $settings  Widget settings.
	 * @param array<string,mixed> $overrides Optional per-item overrides (title, description, features, pdf_url).
	 * @return string
	 */
	public static function render_card( WC_Product $product, array $settings, array $overrides = array() ): string {
		$layout     = (string) ( $settings['layout'] ?? 'layout-1' );
		$product_id = $product->get_id();
		$show       = static fn( string $key ): bool => ! isset( $settings[ $key ] ) || 'yes' === $settings[ $key ];

		$aspect    = sanitize_html_class( (string) ( $settings['image_aspect'] ?? 'ratio-4-3' ) );
		$objectfit = sanitize_html_class( (string) ( $settings['image_fit'] ?? 'cover' ) );
		$hover     = sanitize_html_class( (string) ( $settings['image_hover'] ?? 'zoom' ) );

		// Whether the product image/title link to the product page.
		$link_enabled = ( $settings['enable_product_link'] ?? '' ) === 'yes';

		// Build each body block keyed by element name, then output by order.
		$blocks = array();

		if ( $show( 'show_brand' ) ) {
			$brand = STC_PSP_Product_Meta::get_brand( $product_id );
			if ( $brand ) {
				$blocks['brand'] = '<div class="stc-psp-brand">' . esc_html( $brand ) . '</div>';
			}
		}

		if ( $show( 'show_category' ) ) {
			$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
			$cats = is_wp_error( $cats ) ? array() : $cats;
			if ( ! empty( $cats ) ) {
				$blocks['category'] = '<div class="stc-psp-category">' . esc_html( implode( ', ', $cats ) ) . '</div>';
			}
		}

		if ( $show( 'show_name' ) ) {
			$title = '' !== (string) ( $overrides['title'] ?? '' ) ? (string) $overrides['title'] : $product->get_name();
			if ( $link_enabled ) {
				$blocks['name'] = '<h3 class="stc-psp-title"><a href="' . esc_url( get_permalink( $product_id ) ) . '">' . esc_html( $title ) . '</a></h3>';
			} else {
				$blocks['name'] = '<h3 class="stc-psp-title stc-psp-title-nolink">' . esc_html( $title ) . '</h3>';
			}
		}

		if ( $show( 'show_sku' ) && $product->get_sku() ) {
			$blocks['sku'] = '<div class="stc-psp-sku"><span>' . esc_html__( 'SKU:', 'stc-product-showcase-pro' ) . '</span> ' . esc_html( $product->get_sku() ) . '</div>';
		}

		if ( $show( 'show_rating' ) && function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() ) {
			$blocks['rating'] = '<div class="stc-psp-rating">' . wp_kses_post( wc_get_rating_html( (float) $product->get_average_rating(), (int) $product->get_rating_count() ) ) . '</div>';
		}

		if ( $show( 'show_description' ) ) {
			$blocks['description'] = self::render_description( $product, $settings, (string) ( $overrides['description'] ?? '' ) );
		}

		if ( $show( 'show_features' ) ) {
			$blocks['features'] = self::render_features( $product_id, $settings, (array) ( $overrides['features'] ?? array() ) );
		}

		if ( $show( 'show_applications' ) && ( $settings['show_applications'] ?? '' ) === 'yes' ) {
			$blocks['applications'] = self::render_applications( $product_id );
		}

		if ( $show( 'show_tags' ) ) {
			$tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
			$tags = is_wp_error( $tags ) ? array() : $tags;
			if ( ! empty( $tags ) ) {
				$tag_html = '<div class="stc-psp-tags">';
				foreach ( $tags as $tag ) {
					$tag_html .= '<span class="stc-psp-tag">' . esc_html( $tag ) . '</span>';
				}
				$tag_html .= '</div>';
				$blocks['tags'] = $tag_html;
			}
		}

		// Footer: price + action buttons.
		$footer  = '<div class="stc-psp-card-footer">';
		if ( $show( 'show_price' ) && $product->get_price_html() ) {
			$footer .= '<div class="stc-psp-price">' . wp_kses_post( $product->get_price_html() ) . '</div>';
		}
		$footer .= '<div class="stc-psp-actions">' . self::render_buttons( $product, $settings, $overrides ) . '</div>';
		$footer .= '</div>';
		$blocks['footer'] = $footer;

		// Multiple downloads block.
		if ( ( $settings['show_downloads'] ?? '' ) === 'yes' ) {
			$blocks['downloads'] = self::render_downloads_list( $product, $settings );
		}

		$order = self::get_element_order( $settings );

		ob_start();
		?>
		<article class="stc-psp-card stc-psp-<?php echo esc_attr( $layout ); ?>" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>">

			<?php if ( $show( 'show_image' ) ) : ?>
				<div class="stc-psp-card-media stc-psp-<?php echo esc_attr( $aspect ); ?> stc-psp-fit-<?php echo esc_attr( $objectfit ); ?> stc-psp-hover-<?php echo esc_attr( $hover ); ?>">
					<?php
					$img = $product->get_image( 'woocommerce_single' );
					$img = $img ? wp_kses_post( $img ) : wc_placeholder_img();
					if ( $link_enabled ) {
						echo '<a href="' . esc_url( get_permalink( $product_id ) ) . '" tabindex="-1">' . $img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo '<span class="stc-psp-media-inner">' . $img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
					<?php if ( $show( 'show_stock' ) ) : ?>
						<span class="stc-psp-stock stc-psp-stock-<?php echo esc_attr( $product->is_in_stock() ? 'in' : 'out' ); ?>">
							<?php echo esc_html( $product->is_in_stock() ? __( 'In Stock', 'stc-product-showcase-pro' ) : __( 'Out of Stock', 'stc-product-showcase-pro' ) ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="stc-psp-card-body">
				<?php
				foreach ( $order as $key ) {
					if ( ! empty( $blocks[ $key ] ) ) {
						echo $blocks[ $key ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				}
				?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the Applications block.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private static function render_applications( int $product_id ): string {
		$items = STC_PSP_Product_Meta::get_lines( $product_id, 'applications' );
		if ( empty( $items ) ) {
			return '';
		}

		$out  = '<div class="stc-psp-applications">';
		$out .= '<div class="stc-psp-block-title">' . esc_html__( 'Applications', 'stc-product-showcase-pro' ) . '</div>';
		$out .= '<ul>';
		foreach ( $items as $item ) {
			$out .= '<li>' . esc_html( $item ) . '</li>';
		}
		$out .= '</ul></div>';

		return $out;
	}

	/**
	 * Render the description with word/character limit and read more toggle.
	 *
	 * @param WC_Product          $product  Product.
	 * @param array<string,mixed> $settings Widget settings.
	 * @return string
	 */
	private static function render_description( WC_Product $product, array $settings, string $override = '' ): string {
		if ( '' !== trim( $override ) ) {
			$raw = $override;
		} else {
			$raw = $product->get_short_description();
			if ( '' === trim( (string) $raw ) ) {
				$raw = wp_trim_words( (string) $product->get_description(), 60 );
			}
		}
		$text = wp_strip_all_tags( (string) $raw );
		if ( '' === $text ) {
			return '';
		}

		$mode  = (string) ( $settings['desc_limit_type'] ?? 'words' ); // words | chars.
		$limit = (int) ( $settings['desc_limit'] ?? 20 );
		$limit = $limit > 0 ? $limit : 20;

		$full      = $text;
		$truncated = $text;
		$is_cut    = false;

		if ( 'chars' === $mode ) {
			if ( mb_strlen( $text ) > $limit ) {
				$truncated = mb_substr( $text, 0, $limit );
				$is_cut    = true;
			}
		} else {
			$words = preg_split( '/\s+/', $text );
			if ( is_array( $words ) && count( $words ) > $limit ) {
				$truncated = implode( ' ', array_slice( $words, 0, $limit ) );
				$is_cut    = true;
			}
		}

		$read_more = ! empty( $settings['enable_read_more'] ) && 'yes' === $settings['enable_read_more'];

		ob_start();
		echo '<div class="stc-psp-desc">';
		if ( $is_cut && $read_more ) {
			printf(
				'<span class="stc-psp-desc-short">%s…</span><span class="stc-psp-desc-full" hidden>%s</span> ',
				esc_html( $truncated ),
				esc_html( $full )
			);
			printf(
				'<button type="button" class="stc-psp-readmore" data-more="%s" data-less="%s">%s</button>',
				esc_attr( (string) ( $settings['read_more_text'] ?? __( 'Read More', 'stc-product-showcase-pro' ) ) ),
				esc_attr( (string) ( $settings['read_less_text'] ?? __( 'Read Less', 'stc-product-showcase-pro' ) ) ),
				esc_html( (string) ( $settings['read_more_text'] ?? __( 'Read More', 'stc-product-showcase-pro' ) ) )
			);
		} else {
			echo esc_html( $is_cut ? $truncated . '…' : $full );
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Render the features block.
	 *
	 * @param int                 $product_id Product ID.
	 * @param array<string,mixed> $settings   Widget settings.
	 * @return string
	 */
	private static function render_features( int $product_id, array $settings, array $override = array() ): string {
		$source = (string) ( $settings['features_source'] ?? 'woocommerce' );
		$style  = sanitize_html_class( (string) ( $settings['features_style'] ?? 'checkmark' ) );

		$items = array();

		if ( ! empty( $override ) ) {
			$items = $override;
		} else {
			switch ( $source ) {
			case 'custom_field':
				$field = (string) ( $settings['features_meta_key'] ?? '_stc_psp_features' );
				$raw   = (string) get_post_meta( $product_id, $field, true );
				$items = array_values( array_filter( array_map( 'trim', (array) preg_split( '/\r\n|\r|\n/', $raw ) ) ) );
				break;

			case 'acf':
				$field = (string) ( $settings['features_meta_key'] ?? 'features' );
				if ( function_exists( 'get_field' ) ) {
					$acf = get_field( $field, $product_id );
					if ( is_array( $acf ) ) {
						foreach ( $acf as $row ) {
							$items[] = is_array( $row ) ? (string) reset( $row ) : (string) $row;
						}
					} elseif ( is_string( $acf ) ) {
						$items = array_values( array_filter( array_map( 'trim', (array) preg_split( '/\r\n|\r|\n/', $acf ) ) ) );
					}
				}
				break;

			case 'woocommerce':
			default:
				$items = STC_PSP_Product_Meta::get_lines( $product_id, 'features' );
				break;
			}
		}

		$items = array_filter( $items );
		if ( empty( $items ) ) {
			return '';
		}

		$icon = sanitize_text_field( (string) ( $settings['features_icon'] ?? 'dashicons dashicons-yes' ) );

		ob_start();
		echo '<ul class="stc-psp-features stc-psp-features-' . esc_attr( $style ) . '">';
		foreach ( $items as $item ) {
			echo '<li>';
			if ( 'icon' === $style && $icon ) {
				echo '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i> ';
			}
			echo esc_html( $item );
			echo '</li>';
		}
		echo '</ul>';

		return (string) ob_get_clean();
	}

	/**
	 * Render the action buttons (Enquire Now + Download Catalogue).
	 *
	 * @param WC_Product          $product   Product.
	 * @param array<string,mixed> $settings  Widget settings.
	 * @param array<string,mixed> $overrides Optional per-item overrides (pdf_url).
	 * @return string
	 */
	private static function render_buttons( WC_Product $product, array $settings, array $overrides = array() ): string {
		$product_id = $product->get_id();
		$out        = '';

		// Enquire Now button.
		if ( empty( $settings['enable_enquiry_btn'] ) || 'yes' === $settings['enable_enquiry_btn'] ) {
			$anim = sanitize_html_class( (string) ( $settings['enquiry_anim'] ?? 'none' ) );
			$out .= STC_PSP_Enquiry_System::render_enquiry_button(
				$product_id,
				array(
					'text'          => (string) ( $settings['enquiry_button_text'] ?? STC_PSP_Settings::get( 'enquiry_button_text' ) ),
					'class'         => 'stc-psp-btn stc-psp-btn-enquire stc-psp-anim-' . $anim,
					'icon'          => (string) ( $settings['enquiry_icon'] ?? 'dashicons dashicons-email-alt' ),
					'icon_enabled'  => ! isset( $settings['enquiry_icon_enable'] ) || 'yes' === $settings['enquiry_icon_enable'],
					'icon_position' => (string) ( $settings['enquiry_icon_position'] ?? 'left' ),
					'icon_size'     => self::slider_size( $settings['enquiry_icon_size'] ?? null ),
					'icon_color'    => (string) ( $settings['enquiry_icon_color'] ?? '' ),
				)
			);
		}

		// Download Catalogue button (auto-show / auto-hide).
		$enable_download = empty( $settings['enable_download_btn'] ) || 'yes' === $settings['enable_download_btn'];
		if ( $enable_download ) {
			$pdf = '' !== (string) ( $overrides['pdf_url'] ?? '' )
				? (string) $overrides['pdf_url']
				: STC_PSP_Product_Meta::get_pdf_url( $product_id );

			if ( $pdf ) {
				$out .= self::render_download_button( $product, $pdf, $settings );
			} elseif ( ( $settings['download_fallback'] ?? '' ) === 'yes' ) {
				$msg  = (string) ( $settings['download_fallback_text'] ?? __( 'No catalogue available', 'stc-product-showcase-pro' ) );
				$out .= '<span class="stc-psp-no-catalogue">' . esc_html( $msg ) . '</span>';
			}
		}

		return $out;
	}

	/**
	 * Extract a pixel size from an Elementor slider value.
	 *
	 * @param mixed $value Slider value.
	 * @return int
	 */
	private static function slider_size( $value ): int {
		if ( is_array( $value ) && isset( $value['size'] ) ) {
			return (int) $value['size'];
		}

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Render the list of additional downloads (datasheet, brochure, certificate…).
	 *
	 * @param WC_Product          $product  Product.
	 * @param array<string,mixed> $settings Widget settings.
	 * @return string
	 */
	private static function render_downloads_list( WC_Product $product, array $settings ): string {
		$downloads = STC_PSP_Product_Meta::get_downloads( $product->get_id() );
		if ( empty( $downloads ) ) {
			return '';
		}

		$new_tab = 'yes' === STC_PSP_Settings::get( 'open_new_tab', 'yes' );
		$track   = 'yes' === STC_PSP_Settings::get( 'track_downloads', 'yes' );

		$out = '<div class="stc-psp-downloads">';
		foreach ( $downloads as $row ) {
			$out .= sprintf(
				'<a class="stc-psp-btn stc-psp-btn-download" href="%1$s" data-product-id="%2$s" data-product-name="%3$s" data-track="%4$s" %5$s><i class="stc-psp-btn-icon dashicons dashicons-media-document" aria-hidden="true"></i><span class="stc-psp-btn-text">%6$s</span></a>',
				esc_url( $row['url'] ),
				esc_attr( (string) $product->get_id() ),
				esc_attr( $product->get_name() ),
				esc_attr( $track ? '1' : '0' ),
				$new_tab ? 'target="_blank" rel="noopener"' : 'download',
				esc_html( $row['label'] )
			);
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * Render the download catalogue button.
	 *
	 * @param WC_Product          $product  Product.
	 * @param string              $pdf_url  PDF URL.
	 * @param array<string,mixed> $settings Widget settings.
	 * @return string
	 */
	private static function render_download_button( WC_Product $product, string $pdf_url, array $settings ): string {
		$new_tab    = 'yes' === STC_PSP_Settings::get( 'open_new_tab', 'yes' );
		$show_size  = 'yes' === STC_PSP_Settings::get( 'show_file_size', 'yes' );
		$show_icon  = 'yes' === STC_PSP_Settings::get( 'show_pdf_icon', 'yes' );
		$track      = 'yes' === STC_PSP_Settings::get( 'track_downloads', 'yes' );
		$text       = (string) ( $settings['download_button_text'] ?? STC_PSP_Settings::get( 'download_button_text' ) );
		$anim       = sanitize_html_class( (string) ( $settings['download_anim'] ?? 'none' ) );

		$size  = $show_size ? self::pdf_filesize( $product->get_id() ) : '';

		ob_start();
		?>
		<a class="stc-psp-btn stc-psp-btn-download stc-psp-anim-<?php echo esc_attr( $anim ); ?>"
			href="<?php echo esc_url( $pdf_url ); ?>"
			data-product-id="<?php echo esc_attr( (string) $product->get_id() ); ?>"
			data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
			data-track="<?php echo esc_attr( $track ? '1' : '0' ); ?>"
			<?php if ( $new_tab ) : ?>target="_blank" rel="noopener"<?php else : ?>download<?php endif; ?>>
			<?php if ( $show_icon ) : ?>
				<i class="stc-psp-btn-icon dashicons dashicons-pdf" aria-hidden="true"></i>
			<?php endif; ?>
			<span class="stc-psp-btn-text"><?php echo esc_html( $text ); ?></span>
			<?php if ( $size ) : ?>
				<span class="stc-psp-file-size">(<?php echo esc_html( $size ); ?>)</span>
			<?php endif; ?>
		</a>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Resolve the human-readable file size of the product PDF (media only).
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private static function pdf_filesize( int $product_id ): string {
		$id = (int) get_post_meta( $product_id, STC_PSP_Product_Meta::META['pdf_id'], true );
		if ( $id ) {
			$path = get_attached_file( $id );
			if ( $path && file_exists( $path ) ) {
				return size_format( (int) filesize( $path ) );
			}
		}

		return '';
	}
}
