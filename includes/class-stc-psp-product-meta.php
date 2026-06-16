<?php
/**
 * Product meta boxes: Catalogue PDF + advanced product data.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Product_Meta
 *
 * Adds meta boxes to the WooCommerce product editor and exposes helpers to
 * read the stored values from anywhere (widget, AJAX, etc.).
 */
class STC_PSP_Product_Meta {

	const NONCE_ACTION = 'stc_psp_save_product_meta';
	const NONCE_NAME   = 'stc_psp_product_meta_nonce';

	/**
	 * Meta keys used by the plugin.
	 *
	 * @var array<string,string>
	 */
	const META = array(
		'pdf_id'         => '_stc_psp_pdf_id',
		'pdf_url'        => '_stc_psp_pdf_url',
		'pdf_source'     => '_stc_psp_pdf_source', // media | upload | external | gdrive | dropbox.
		'brand'          => '_stc_psp_brand',
		'features'       => '_stc_psp_features',
		'specifications' => '_stc_psp_specifications',
		'applications'   => '_stc_psp_applications',
		'industries'     => '_stc_psp_industries',
		'downloads'      => '_stc_psp_downloads',
		'certificates'   => '_stc_psp_certificates',
		'approvals'      => '_stc_psp_approvals',
		'download_count' => '_stc_psp_download_count',
	);

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_product', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register the meta boxes on the product post type.
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'stc_psp_catalogue',
			__( 'STC – Catalogue PDF', 'stc-product-showcase-pro' ),
			array( $this, 'render_pdf_box' ),
			'product',
			'side',
			'default'
		);

		add_meta_box(
			'stc_psp_advanced',
			__( 'STC – Advanced Product Data', 'stc-product-showcase-pro' ),
			array( $this, 'render_advanced_box' ),
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Render the catalogue PDF meta box.
	 *
	 * @param WP_Post $post Current product post.
	 */
	public function render_pdf_box( $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$pdf_url    = (string) get_post_meta( $post->ID, self::META['pdf_url'], true );
		$pdf_id     = (int) get_post_meta( $post->ID, self::META['pdf_id'], true );
		$pdf_source = (string) get_post_meta( $post->ID, self::META['pdf_source'], true );
		$pdf_source = $pdf_source ?: 'media';
		?>
		<p>
			<label for="stc_psp_pdf_source"><strong><?php esc_html_e( 'PDF Source', 'stc-product-showcase-pro' ); ?></strong></label>
			<select id="stc_psp_pdf_source" name="stc_psp_pdf_source" class="widefat">
				<?php
				$sources = array(
					'media'    => __( 'Media Library', 'stc-product-showcase-pro' ),
					'upload'   => __( 'Uploaded File URL', 'stc-product-showcase-pro' ),
					'external' => __( 'External URL', 'stc-product-showcase-pro' ),
					'gdrive'   => __( 'Google Drive URL', 'stc-product-showcase-pro' ),
					'dropbox'  => __( 'Dropbox URL', 'stc-product-showcase-pro' ),
				);
				foreach ( $sources as $value => $label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $value ),
						selected( $pdf_source, $value, false ),
						esc_html( $label )
					);
				}
				?>
			</select>
		</p>
		<p>
			<input type="text" id="stc_psp_pdf_url" name="stc_psp_pdf_url" class="widefat"
				value="<?php echo esc_attr( $pdf_url ); ?>"
				placeholder="<?php esc_attr_e( 'https://… or select from media', 'stc-product-showcase-pro' ); ?>" />
			<input type="hidden" id="stc_psp_pdf_id" name="stc_psp_pdf_id" value="<?php echo esc_attr( (string) $pdf_id ); ?>" />
		</p>
		<p>
			<button type="button" class="button stc-psp-select-pdf"><?php esc_html_e( 'Select / Upload PDF', 'stc-product-showcase-pro' ); ?></button>
			<button type="button" class="button stc-psp-remove-pdf"><?php esc_html_e( 'Remove', 'stc-product-showcase-pro' ); ?></button>
		</p>
		<p class="description"><?php esc_html_e( 'This PDF is fetched automatically by the showcase Download Catalogue button.', 'stc-product-showcase-pro' ); ?></p>
		<?php
	}

	/**
	 * Render the advanced product data meta box.
	 *
	 * @param WP_Post $post Current product post.
	 */
	public function render_advanced_box( $post ): void {
		$fields = array(
			'brand'          => array( 'label' => __( 'Brand', 'stc-product-showcase-pro' ), 'type' => 'text' ),
			'features'       => array( 'label' => __( 'Features (one per line)', 'stc-product-showcase-pro' ), 'type' => 'textarea' ),
			'specifications' => array( 'label' => __( 'Technical Specifications (Label | Value per line)', 'stc-product-showcase-pro' ), 'type' => 'textarea' ),
			'applications'   => array( 'label' => __( 'Applications (one per line)', 'stc-product-showcase-pro' ), 'type' => 'textarea' ),
			'industries'     => array( 'label' => __( 'Industries Served (one per line)', 'stc-product-showcase-pro' ), 'type' => 'textarea' ),
			'downloads'      => array( 'label' => __( 'Downloads (Label | URL per line)', 'stc-product-showcase-pro' ), 'type' => 'textarea' ),
			'certificates'   => array( 'label' => __( 'Certificates (one per line)', 'stc-product-showcase-pro' ), 'type' => 'textarea' ),
			'approvals'      => array( 'label' => __( 'Approvals (one per line)', 'stc-product-showcase-pro' ), 'type' => 'textarea' ),
		);

		echo '<div class="stc-psp-meta-grid" style="display:grid;gap:16px;">';
		foreach ( $fields as $key => $field ) {
			$value = (string) get_post_meta( $post->ID, self::META[ $key ], true );
			echo '<p style="margin:0;">';
			printf( '<label for="stc_psp_%1$s"><strong>%2$s</strong></label><br/>', esc_attr( $key ), esc_html( $field['label'] ) );
			if ( 'textarea' === $field['type'] ) {
				printf(
					'<textarea id="stc_psp_%1$s" name="stc_psp_%1$s" class="widefat" rows="4">%2$s</textarea>',
					esc_attr( $key ),
					esc_textarea( $value )
				);
			} else {
				printf(
					'<input type="text" id="stc_psp_%1$s" name="stc_psp_%1$s" class="widefat" value="%2$s" />',
					esc_attr( $key ),
					esc_attr( $value )
				);
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * Persist meta box values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save( int $post_id, $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		// Skip autosaves / revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// PDF fields.
		$pdf_source = isset( $_POST['stc_psp_pdf_source'] ) ? sanitize_key( wp_unslash( $_POST['stc_psp_pdf_source'] ) ) : 'media';
		$pdf_url    = isset( $_POST['stc_psp_pdf_url'] ) ? esc_url_raw( wp_unslash( $_POST['stc_psp_pdf_url'] ) ) : '';
		$pdf_id     = isset( $_POST['stc_psp_pdf_id'] ) ? absint( wp_unslash( $_POST['stc_psp_pdf_id'] ) ) : 0;

		update_post_meta( $post_id, self::META['pdf_source'], $pdf_source );
		update_post_meta( $post_id, self::META['pdf_url'], $pdf_url );
		update_post_meta( $post_id, self::META['pdf_id'], $pdf_id );

		// Advanced fields.
		$text_field     = array( 'brand' );
		$textarea_field = array( 'features', 'specifications', 'applications', 'industries', 'downloads', 'certificates', 'approvals' );

		foreach ( $text_field as $key ) {
			$field = 'stc_psp_' . $key;
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, self::META[ $key ], sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		foreach ( $textarea_field as $key ) {
			$field = 'stc_psp_' . $key;
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, self::META[ $key ], sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Static read helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Get the catalogue PDF URL for a product, resolving media IDs.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_pdf_url( int $product_id ): string {
		$url = (string) get_post_meta( $product_id, self::META['pdf_url'], true );
		if ( $url ) {
			return $url;
		}

		$id = (int) get_post_meta( $product_id, self::META['pdf_id'], true );
		if ( $id ) {
			$attachment = wp_get_attachment_url( $id );
			if ( $attachment ) {
				return $attachment;
			}
		}

		return '';
	}

	/**
	 * Get the brand for a product (meta first, then product_brand/pa_brand taxonomy).
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_brand( int $product_id ): string {
		$brand = (string) get_post_meta( $product_id, self::META['brand'], true );
		if ( $brand ) {
			return $brand;
		}

		foreach ( array( 'product_brand', 'pa_brand', 'pwb-brand' ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					return implode( ', ', $terms );
				}
			}
		}

		return '';
	}

	/**
	 * Parse a "one item per line" textarea meta into an array.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $key        Meta key short name.
	 * @return array<int,string>
	 */
	public static function get_lines( int $product_id, string $key ): array {
		$raw = (string) get_post_meta( $product_id, self::META[ $key ] ?? '', true );
		if ( '' === $raw ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		return array_values( array_filter( array_map( 'trim', (array) $lines ) ) );
	}

	/**
	 * Parse "Label | Value" lines into key/value pairs.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $key        Meta key short name.
	 * @return array<int,array{label:string,value:string}>
	 */
	public static function get_pairs( int $product_id, string $key ): array {
		$out = array();
		foreach ( self::get_lines( $product_id, $key ) as $line ) {
			$parts   = array_map( 'trim', explode( '|', $line, 2 ) );
			$out[]   = array(
				'label' => $parts[0] ?? '',
				'value' => $parts[1] ?? '',
			);
		}

		return $out;
	}
}
