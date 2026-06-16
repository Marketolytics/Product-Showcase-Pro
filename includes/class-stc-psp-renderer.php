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
	 * Render a single product card.
	 *
	 * @param WC_Product          $product  Product.
	 * @param array<string,mixed> $settings Widget settings.
	 * @return string
	 */
	public static function render_card( WC_Product $product, array $settings ): string {
		$layout     = (string) ( $settings['layout'] ?? 'layout-1' );
		$product_id = $product->get_id();
		$show       = static fn( string $key ): bool => ! isset( $settings[ $key ] ) || 'yes' === $settings[ $key ];

		$aspect    = sanitize_html_class( (string) ( $settings['image_aspect'] ?? 'ratio-4-3' ) );
		$objectfit = sanitize_html_class( (string) ( $settings['image_fit'] ?? 'cover' ) );
		$hover     = sanitize_html_class( (string) ( $settings['image_hover'] ?? 'zoom' ) );

		ob_start();
		?>
		<article class="stc-psp-card stc-psp-<?php echo esc_attr( $layout ); ?>" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>">

			<?php if ( $show( 'show_image' ) ) : ?>
				<div class="stc-psp-card-media stc-psp-<?php echo esc_attr( $aspect ); ?> stc-psp-fit-<?php echo esc_attr( $objectfit ); ?> stc-psp-hover-<?php echo esc_attr( $hover ); ?>">
					<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" tabindex="-1">
						<?php
						$img = $product->get_image( 'woocommerce_single' );
						echo $img ? wp_kses_post( $img ) : wc_placeholder_img(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</a>
					<?php if ( $show( 'show_stock' ) ) : ?>
						<span class="stc-psp-stock stc-psp-stock-<?php echo esc_attr( $product->is_in_stock() ? 'in' : 'out' ); ?>">
							<?php echo esc_html( $product->is_in_stock() ? __( 'In Stock', 'stc-product-showcase-pro' ) : __( 'Out of Stock', 'stc-product-showcase-pro' ) ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="stc-psp-card-body">

				<?php if ( $show( 'show_brand' ) ) : ?>
					<?php $brand = STC_PSP_Product_Meta::get_brand( $product_id ); ?>
					<?php if ( $brand ) : ?>
						<div class="stc-psp-brand"><?php echo esc_html( $brand ); ?></div>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $show( 'show_category' ) ) : ?>
					<?php
					$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
					$cats = is_wp_error( $cats ) ? array() : $cats;
					?>
					<?php if ( ! empty( $cats ) ) : ?>
						<div class="stc-psp-category"><?php echo esc_html( implode( ', ', $cats ) ); ?></div>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $show( 'show_name' ) ) : ?>
					<h3 class="stc-psp-title">
						<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
					</h3>
				<?php endif; ?>

				<?php if ( $show( 'show_sku' ) && $product->get_sku() ) : ?>
					<div class="stc-psp-sku"><span><?php esc_html_e( 'SKU:', 'stc-product-showcase-pro' ); ?></span> <?php echo esc_html( $product->get_sku() ); ?></div>
				<?php endif; ?>

				<?php if ( $show( 'show_rating' ) && wc_review_ratings_enabled() ) : ?>
					<div class="stc-psp-rating"><?php echo wp_kses_post( wc_get_rating_html( (float) $product->get_average_rating(), (int) $product->get_rating_count() ) ); ?></div>
				<?php endif; ?>

				<?php if ( $show( 'show_description' ) ) : ?>
					<?php echo self::render_description( $product, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>

				<?php if ( $show( 'show_features' ) ) : ?>
					<?php echo self::render_features( $product_id, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>

				<?php if ( $show( 'show_tags' ) ) : ?>
					<?php
					$tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
					$tags = is_wp_error( $tags ) ? array() : $tags;
					?>
					<?php if ( ! empty( $tags ) ) : ?>
						<div class="stc-psp-tags">
							<?php foreach ( $tags as $tag ) : ?>
								<span class="stc-psp-tag"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<div class="stc-psp-card-footer">
					<?php if ( $show( 'show_price' ) && $product->get_price_html() ) : ?>
						<div class="stc-psp-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
					<?php endif; ?>

					<div class="stc-psp-actions">
						<?php echo self::render_buttons( $product, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>

			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the description with word/character limit and read more toggle.
	 *
	 * @param WC_Product          $product  Product.
	 * @param array<string,mixed> $settings Widget settings.
	 * @return string
	 */
	private static function render_description( WC_Product $product, array $settings ): string {
		$raw = $product->get_short_description();
		if ( '' === trim( (string) $raw ) ) {
			$raw = wp_trim_words( (string) $product->get_description(), 60 );
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
	private static function render_features( int $product_id, array $settings ): string {
		$source = (string) ( $settings['features_source'] ?? 'woocommerce' );
		$style  = sanitize_html_class( (string) ( $settings['features_style'] ?? 'checkmark' ) );

		$items = array();

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
	 * @param WC_Product          $product  Product.
	 * @param array<string,mixed> $settings Widget settings.
	 * @return string
	 */
	private static function render_buttons( WC_Product $product, array $settings ): string {
		$product_id = $product->get_id();
		$out        = '';

		// Enquire Now button.
		if ( empty( $settings['enable_enquiry_btn'] ) || 'yes' === $settings['enable_enquiry_btn'] ) {
			$anim = sanitize_html_class( (string) ( $settings['enquiry_anim'] ?? 'none' ) );
			$out .= STC_PSP_Enquiry_System::render_enquiry_button(
				$product_id,
				array(
					'text'  => (string) ( $settings['enquiry_button_text'] ?? STC_PSP_Settings::get( 'enquiry_button_text' ) ),
					'class' => 'stc-psp-btn stc-psp-btn-enquire stc-psp-anim-' . $anim,
					'icon'  => (string) ( $settings['enquiry_icon'] ?? 'dashicons dashicons-email-alt' ),
				)
			);
		}

		// Download Catalogue button.
		$enable_download = empty( $settings['enable_download_btn'] ) || 'yes' === $settings['enable_download_btn'];
		if ( $enable_download ) {
			$pdf = STC_PSP_Product_Meta::get_pdf_url( $product_id );
			if ( $pdf ) {
				$out .= self::render_download_button( $product, $pdf, $settings );
			}
		}

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
		$show_count = 'yes' === STC_PSP_Settings::get( 'show_download_count', 'yes' );
		$show_size  = 'yes' === STC_PSP_Settings::get( 'show_file_size', 'yes' );
		$show_icon  = 'yes' === STC_PSP_Settings::get( 'show_pdf_icon', 'yes' );
		$track      = 'yes' === STC_PSP_Settings::get( 'track_downloads', 'yes' );
		$text       = (string) ( $settings['download_button_text'] ?? STC_PSP_Settings::get( 'download_button_text' ) );
		$anim       = sanitize_html_class( (string) ( $settings['download_anim'] ?? 'none' ) );

		$count = $show_count ? STC_PSP_Download_Repository::count_for_product( $product->get_id() ) : 0;
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
			<?php if ( $show_count ) : ?>
				<span class="stc-psp-dl-count" title="<?php esc_attr_e( 'Downloads', 'stc-product-showcase-pro' ); ?>"><?php echo esc_html( (string) $count ); ?></span>
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
