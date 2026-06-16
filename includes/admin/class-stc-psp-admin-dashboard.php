<?php
/**
 * Admin dashboard: menus, list views, CSV export, settings & templates.
 *
 * @package STC_Product_Showcase_Pro
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class STC_PSP_Admin_Dashboard
 */
class STC_PSP_Admin_Dashboard {

	const CAP  = 'manage_options';
	const SLUG = 'stc-psp';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_init', array( $this, 'maybe_export_csv' ) );
		add_action( 'admin_init', array( $this, 'maybe_export_downloads_csv' ) );
	}

	/**
	 * Register the top-level menu and submenus.
	 */
	public function register_menu(): void {
		$counts = STC_PSP_Enquiry_Repository::counts_by_status();
		$new    = (int) ( $counts['new'] ?? 0 );
		$bubble = $new > 0 ? ' <span class="awaiting-mod">' . esc_html( (string) $new ) . '</span>' : '';

		add_menu_page(
			__( 'STC Product Showcase', 'stc-product-showcase-pro' ),
			__( 'STC Showcase', 'stc-product-showcase-pro' ) . $bubble,
			self::CAP,
			self::SLUG,
			array( $this, 'render_enquiries_page' ),
			'dashicons-products',
			56
		);

		add_submenu_page(
			self::SLUG,
			__( 'Enquiries', 'stc-product-showcase-pro' ),
			__( 'Enquiries', 'stc-product-showcase-pro' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render_enquiries_page' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Downloads', 'stc-product-showcase-pro' ),
			__( 'Downloads', 'stc-product-showcase-pro' ),
			self::CAP,
			self::SLUG . '-downloads',
			array( $this, 'render_downloads_page' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Settings', 'stc-product-showcase-pro' ),
			__( 'Settings', 'stc-product-showcase-pro' ),
			self::CAP,
			self::SLUG . '-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Templates', 'stc-product-showcase-pro' ),
			__( 'Templates', 'stc-product-showcase-pro' ),
			self::CAP,
			self::SLUG . '-templates',
			array( $this, 'render_templates_page' )
		);
	}

	/* ------------------------------------------------------------------ *
	 * Action handling (delete enquiry, status, templates)
	 * ------------------------------------------------------------------ */
	public function handle_actions(): void {
		if ( ! isset( $_GET['page'] ) || strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), self::SLUG ) !== 0 ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// Delete enquiry.
		if ( isset( $_GET['stc_action'], $_GET['id'] ) && 'delete_enquiry' === $_GET['stc_action'] ) {
			check_admin_referer( 'stc_psp_delete_enquiry' );
			STC_PSP_Enquiry_Repository::delete( absint( wp_unslash( $_GET['id'] ) ) );
			$this->redirect_with_notice( self::SLUG, 'deleted' );
		}

		// Update enquiry status.
		if ( isset( $_POST['stc_psp_update_status'], $_POST['enquiry_id'], $_POST['status'] ) ) {
			check_admin_referer( 'stc_psp_update_status' );
			STC_PSP_Enquiry_Repository::update_status(
				absint( wp_unslash( $_POST['enquiry_id'] ) ),
				sanitize_key( wp_unslash( $_POST['status'] ) )
			);
			$this->redirect_with_notice( self::SLUG, 'updated' );
		}

		// Save template.
		if ( isset( $_POST['stc_psp_save_template'] ) ) {
			check_admin_referer( 'stc_psp_save_template' );
			$settings_json = isset( $_POST['template_settings'] ) ? wp_unslash( $_POST['template_settings'] ) : '{}';
			STC_PSP_Template_Repository::save(
				array(
					'id'        => isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0,
					'name'      => isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '',
					'is_global' => ! empty( $_POST['template_global'] ),
					'settings'  => json_decode( (string) $settings_json, true ) ?: array(),
				)
			);
			$this->redirect_with_notice( self::SLUG . '-templates', 'saved' );
		}

		// Delete template.
		if ( isset( $_GET['stc_action'], $_GET['id'] ) && 'delete_template' === $_GET['stc_action'] ) {
			check_admin_referer( 'stc_psp_delete_template' );
			STC_PSP_Template_Repository::delete( absint( wp_unslash( $_GET['id'] ) ) );
			$this->redirect_with_notice( self::SLUG . '-templates', 'deleted' );
		}
	}

	/**
	 * Redirect back to a page with a status flag.
	 *
	 * @param string $page   Page slug.
	 * @param string $notice Notice key.
	 */
	private function redirect_with_notice( string $page, string $notice ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => $page, 'stc_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render a stored notice if present.
	 */
	private function maybe_render_notice(): void {
		if ( empty( $_GET['stc_notice'] ) ) {
			return;
		}
		$map = array(
			'deleted' => __( 'Item deleted.', 'stc-product-showcase-pro' ),
			'updated' => __( 'Status updated.', 'stc-product-showcase-pro' ),
			'saved'   => __( 'Saved successfully.', 'stc-product-showcase-pro' ),
		);
		$key = sanitize_key( wp_unslash( $_GET['stc_notice'] ) );
		if ( isset( $map[ $key ] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $map[ $key ] ) );
		}
	}

	/* ------------------------------------------------------------------ *
	 * CSV export
	 * ------------------------------------------------------------------ */
	public function maybe_export_csv(): void {
		if ( ! isset( $_GET['stc_action'] ) || 'export_enquiries' !== $_GET['stc_action'] ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stc-product-showcase-pro' ) );
		}
		check_admin_referer( 'stc_psp_export' );

		$result = STC_PSP_Enquiry_Repository::query( array( 'per_page' => 100000, 'page' => 1 ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=stc-enquiries-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out     = fopen( 'php://output', 'w' );
		$columns = array( 'ID', 'Date', 'Product', 'SKU', 'Category', 'URL', 'Name', 'Email', 'Mobile', 'Company', 'City', 'Country', 'Industry', 'Message', 'Status' );
		fputcsv( $out, $columns );

		foreach ( $result['items'] as $row ) {
			fputcsv(
				$out,
				array(
					$row['id'],
					$row['created_at'],
					$row['product_name'],
					$row['product_sku'],
					$row['product_category'],
					$row['product_url'],
					$row['customer_name'],
					$row['customer_email'],
					$row['customer_mobile'],
					$row['customer_company'],
					$row['customer_city'],
					$row['customer_country'],
					$row['customer_industry'],
					$row['message'],
					$row['status'],
				)
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Export download records to CSV.
	 */
	public function maybe_export_downloads_csv(): void {
		if ( ! isset( $_GET['stc_action'] ) || 'export_downloads' !== $_GET['stc_action'] ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stc-product-showcase-pro' ) );
		}
		check_admin_referer( 'stc_psp_export_downloads' );

		$rows = STC_PSP_Download_Repository::all();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=stc-downloads-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Date', 'Time', 'Product Name', 'Downloaded File', 'File URL', 'User IP', 'User ID' ) );

		foreach ( $rows as $row ) {
			$ts = strtotime( (string) $row['created_at'] );
			fputcsv(
				$out,
				array(
					$row['id'],
					gmdate( 'Y-m-d', $ts ),
					gmdate( 'H:i:s', $ts ),
					$row['product_name'],
					$row['file_name'],
					$row['file_url'],
					$row['ip_address'],
					$row['user_id'],
				)
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Enquiries list / single view page.
	 */
	public function render_enquiries_page(): void {
		$single_id = isset( $_GET['view'] ) ? absint( wp_unslash( $_GET['view'] ) ) : 0;
		echo '<div class="wrap stc-psp-admin">';
		$this->maybe_render_notice();

		if ( $single_id ) {
			$this->render_enquiry_single( $single_id );
		} else {
			$this->render_enquiries_list();
		}
		echo '</div>';
	}

	/**
	 * The enquiries table.
	 */
	private function render_enquiries_list(): void {
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$per_page = 20;

		$result = STC_PSP_Enquiry_Repository::query(
			array(
				'status'   => $status,
				'search'   => $search,
				'page'     => $paged,
				'per_page' => $per_page,
			)
		);
		$counts = STC_PSP_Enquiry_Repository::counts_by_status();
		$total  = $result['total'];
		$pages  = (int) ceil( $total / $per_page );

		$export_url = wp_nonce_url(
			add_query_arg( array( 'page' => self::SLUG, 'stc_action' => 'export_enquiries' ), admin_url( 'admin.php' ) ),
			'stc_psp_export'
		);
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Enquiries', 'stc-product-showcase-pro' ); ?></h1>
		<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'stc-product-showcase-pro' ); ?></a>
		<hr class="wp-header-end" />

		<ul class="subsubsub">
			<?php
			$statuses = array(
				''         => __( 'All', 'stc-product-showcase-pro' ),
				'new'      => __( 'New', 'stc-product-showcase-pro' ),
				'read'     => __( 'Read', 'stc-product-showcase-pro' ),
				'replied'  => __( 'Replied', 'stc-product-showcase-pro' ),
				'closed'   => __( 'Closed', 'stc-product-showcase-pro' ),
			);
			$links = array();
			foreach ( $statuses as $key => $label ) {
				$url     = add_query_arg(
					array( 'page' => self::SLUG, 'status' => $key ),
					admin_url( 'admin.php' )
				);
				$count   = '' === $key ? array_sum( $counts ) : (int) ( $counts[ $key ] ?? 0 );
				$current = $status === $key ? ' class="current"' : '';
				$links[] = sprintf(
					'<li><a href="%s"%s>%s <span class="count">(%d)</span></a></li>',
					esc_url( $url ),
					$current, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_html( $label ),
					$count
				);
			}
			echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</ul>

		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
			<p class="search-box">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search enquiries', 'stc-product-showcase-pro' ); ?>" />
				<button class="button"><?php esc_html_e( 'Search', 'stc-product-showcase-pro' ); ?></button>
			</p>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'stc-product-showcase-pro' ); ?></th>
					<th><?php esc_html_e( 'Date', 'stc-product-showcase-pro' ); ?></th>
					<th><?php esc_html_e( 'Product', 'stc-product-showcase-pro' ); ?></th>
					<th><?php esc_html_e( 'Name', 'stc-product-showcase-pro' ); ?></th>
					<th><?php esc_html_e( 'Mobile', 'stc-product-showcase-pro' ); ?></th>
					<th><?php esc_html_e( 'Email', 'stc-product-showcase-pro' ); ?></th>
					<th><?php esc_html_e( 'Status', 'stc-product-showcase-pro' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'stc-product-showcase-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $result['items'] ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No enquiries found.', 'stc-product-showcase-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $result['items'] as $row ) : ?>
						<?php
						$view_url   = add_query_arg( array( 'page' => self::SLUG, 'view' => (int) $row['id'] ), admin_url( 'admin.php' ) );
						$delete_url = wp_nonce_url(
							add_query_arg( array( 'page' => self::SLUG, 'stc_action' => 'delete_enquiry', 'id' => (int) $row['id'] ), admin_url( 'admin.php' ) ),
							'stc_psp_delete_enquiry'
						);
						?>
						<tr>
							<td>#<?php echo esc_html( (string) $row['id'] ); ?></td>
							<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $row['created_at'] ) ); ?></td>
							<td><?php echo esc_html( $row['product_name'] ); ?></td>
							<td><?php echo esc_html( $row['customer_name'] ); ?></td>
							<td><?php echo esc_html( $row['customer_mobile'] ); ?></td>
							<td><a href="mailto:<?php echo esc_attr( $row['customer_email'] ); ?>"><?php echo esc_html( $row['customer_email'] ); ?></a></td>
							<td><span class="stc-psp-status stc-psp-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span></td>
							<td>
								<a href="<?php echo esc_url( $view_url ); ?>" class="button button-small"><?php esc_html_e( 'View', 'stc-product-showcase-pro' ); ?></a>
								<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this enquiry?', 'stc-product-showcase-pro' ) ); ?>');"><?php esc_html_e( 'Delete', 'stc-product-showcase-pro' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'total'     => $pages,
							'current'   => $paged,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Single enquiry detail view.
	 *
	 * @param int $id Enquiry ID.
	 */
	private function render_enquiry_single( int $id ): void {
		$row = STC_PSP_Enquiry_Repository::get( $id );
		if ( ! $row ) {
			echo '<h1>' . esc_html__( 'Enquiry not found', 'stc-product-showcase-pro' ) . '</h1>';
			return;
		}

		// Mark as read on view.
		if ( 'new' === $row['status'] ) {
			STC_PSP_Enquiry_Repository::update_status( $id, 'read' );
			$row['status'] = 'read';
		}

		$extra = json_decode( (string) $row['extra_fields'], true );
		$extra = is_array( $extra ) ? $extra : array();
		?>
		<h1><?php printf( esc_html__( 'Enquiry #%d', 'stc-product-showcase-pro' ), (int) $id ); ?></h1>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::SLUG ), admin_url( 'admin.php' ) ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back', 'stc-product-showcase-pro' ); ?></a>

		<table class="form-table">
			<?php
			$pairs = array(
				__( 'Date', 'stc-product-showcase-pro' )        => mysql2date( 'Y-m-d H:i', $row['created_at'] ),
				__( 'Product', 'stc-product-showcase-pro' )     => $row['product_name'],
				__( 'SKU', 'stc-product-showcase-pro' )         => $row['product_sku'],
				__( 'Category', 'stc-product-showcase-pro' )    => $row['product_category'],
				__( 'Product URL', 'stc-product-showcase-pro' ) => $row['product_url'],
				__( 'Name', 'stc-product-showcase-pro' )        => $row['customer_name'],
				__( 'Email', 'stc-product-showcase-pro' )       => $row['customer_email'],
				__( 'Mobile', 'stc-product-showcase-pro' )      => $row['customer_mobile'],
				__( 'Company', 'stc-product-showcase-pro' )     => $row['customer_company'],
				__( 'City', 'stc-product-showcase-pro' )        => $row['customer_city'],
				__( 'Country', 'stc-product-showcase-pro' )     => $row['customer_country'],
				__( 'Industry', 'stc-product-showcase-pro' )    => $row['customer_industry'],
				__( 'Message', 'stc-product-showcase-pro' )     => $row['message'],
				__( 'IP Address', 'stc-product-showcase-pro' )  => $row['ip_address'],
			);
			foreach ( array_merge( $pairs, $extra ) as $label => $value ) {
				if ( '' === trim( (string) $value ) ) {
					continue;
				}
				printf(
					'<tr><th scope="row">%s</th><td>%s</td></tr>',
					esc_html( (string) $label ),
					esc_html( (string) $value )
				);
			}
			?>
		</table>

		<form method="post">
			<?php wp_nonce_field( 'stc_psp_update_status' ); ?>
			<input type="hidden" name="enquiry_id" value="<?php echo esc_attr( (string) $id ); ?>" />
			<label for="stc_psp_status"><strong><?php esc_html_e( 'Status', 'stc-product-showcase-pro' ); ?></strong></label>
			<select name="status" id="stc_psp_status">
				<?php
				foreach ( array( 'new', 'read', 'replied', 'closed' ) as $st ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $st ),
						selected( $row['status'], $st, false ),
						esc_html( ucfirst( $st ) )
					);
				}
				?>
			</select>
			<button type="submit" name="stc_psp_update_status" value="1" class="button button-primary"><?php esc_html_e( 'Update', 'stc-product-showcase-pro' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Downloads report page.
	 */
	public function render_downloads_page(): void {
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$per_page = 20;
		$result   = STC_PSP_Download_Repository::query( array( 'page' => $paged, 'per_page' => $per_page ) );
		$top       = STC_PSP_Download_Repository::top_products( 10 );
		$top_files = STC_PSP_Download_Repository::top_files( 10 );
		$pages    = (int) ceil( $result['total'] / $per_page );

		$export_url = wp_nonce_url(
			add_query_arg( array( 'page' => self::SLUG . '-downloads', 'stc_action' => 'export_downloads' ), admin_url( 'admin.php' ) ),
			'stc_psp_export_downloads'
		);
		?>
		<div class="wrap stc-psp-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Download Tracking', 'stc-product-showcase-pro' ); ?></h1>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'stc-product-showcase-pro' ); ?></a>
			<hr class="wp-header-end" />

			<div class="stc-psp-report-grid" style="display:flex;gap:24px;flex-wrap:wrap;">
				<div style="flex:1 1 360px;">
					<h2><?php esc_html_e( 'Top Downloaded Products', 'stc-product-showcase-pro' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th><?php esc_html_e( 'Product', 'stc-product-showcase-pro' ); ?></th><th><?php esc_html_e( 'Downloads', 'stc-product-showcase-pro' ); ?></th></tr></thead>
						<tbody>
							<?php if ( empty( $top ) ) : ?>
								<tr><td colspan="2"><?php esc_html_e( 'No downloads recorded yet.', 'stc-product-showcase-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $top as $row ) : ?>
									<tr><td><?php echo esc_html( $row['product_name'] ); ?></td><td><?php echo esc_html( (string) $row['total'] ); ?></td></tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<div style="flex:1 1 360px;">
					<h2><?php esc_html_e( 'Top Downloaded Catalogues', 'stc-product-showcase-pro' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead><tr><th><?php esc_html_e( 'File', 'stc-product-showcase-pro' ); ?></th><th><?php esc_html_e( 'Product', 'stc-product-showcase-pro' ); ?></th><th><?php esc_html_e( 'Downloads', 'stc-product-showcase-pro' ); ?></th></tr></thead>
						<tbody>
							<?php if ( empty( $top_files ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No downloads recorded yet.', 'stc-product-showcase-pro' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $top_files as $row ) : ?>
									<tr><td><?php echo esc_html( $row['file_name'] ); ?></td><td><?php echo esc_html( $row['product_name'] ); ?></td><td><?php echo esc_html( (string) $row['total'] ); ?></td></tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<h2><?php esc_html_e( 'Recent Downloads', 'stc-product-showcase-pro' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'stc-product-showcase-pro' ); ?></th>
						<th><?php esc_html_e( 'Product', 'stc-product-showcase-pro' ); ?></th>
						<th><?php esc_html_e( 'File', 'stc-product-showcase-pro' ); ?></th>
						<th><?php esc_html_e( 'User IP', 'stc-product-showcase-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No downloads recorded yet.', 'stc-product-showcase-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $row['created_at'] ) ); ?></td>
								<td><?php echo esc_html( $row['product_name'] ); ?></td>
								<td><?php echo esc_html( $row['file_name'] ); ?></td>
								<td><?php echo esc_html( $row['ip_address'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'    => add_query_arg( 'paged', '%#%' ),
								'format'  => '',
								'total'   => $pages,
								'current' => $paged,
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Settings page (general + email + form/popup builder).
	 */
	public function render_settings_page(): void {
		echo '<div class="wrap stc-psp-admin">';
		echo '<h1>' . esc_html__( 'STC Showcase Settings', 'stc-product-showcase-pro' ) . '</h1>';
		$this->maybe_render_notice();

		settings_errors();
		$settings = STC_PSP_Settings::all();
		?>
		<form method="post" action="options.php" id="stc-psp-settings-form">
			<?php settings_fields( 'stc_psp_settings_group' ); ?>

			<h2 class="nav-tab-wrapper stc-psp-tabs">
				<a href="#tab-general" class="nav-tab nav-tab-active"><?php esc_html_e( 'General', 'stc-product-showcase-pro' ); ?></a>
				<a href="#tab-email" class="nav-tab"><?php esc_html_e( 'Email', 'stc-product-showcase-pro' ); ?></a>
				<a href="#tab-downloads" class="nav-tab"><?php esc_html_e( 'Downloads', 'stc-product-showcase-pro' ); ?></a>
				<a href="#tab-form" class="nav-tab"><?php esc_html_e( 'Form Builder', 'stc-product-showcase-pro' ); ?></a>
			</h2>

			<div id="tab-general" class="stc-psp-tab-panel">
				<table class="form-table">
					<?php
					$this->switch_row( 'remove_add_to_cart', __( 'Remove Add To Cart', 'stc-product-showcase-pro' ), $settings );
					$this->switch_row( 'enable_enquiry', __( 'Enable Enquiry System', 'stc-product-showcase-pro' ), $settings );
					$this->text_row( 'enquiry_button_text', __( 'Enquiry Button Text', 'stc-product-showcase-pro' ), $settings );
					$this->text_row( 'download_button_text', __( 'Download Button Text', 'stc-product-showcase-pro' ), $settings );
					$this->text_row( 'popup_title', __( 'Popup Title', 'stc-product-showcase-pro' ), $settings );
					$this->text_row( 'popup_submit_text', __( 'Submit Button Text', 'stc-product-showcase-pro' ), $settings );
					$this->textarea_row( 'popup_success_msg', __( 'Success Message', 'stc-product-showcase-pro' ), $settings );
					$this->switch_row( 'purge_on_uninstall', __( 'Delete All Data On Uninstall', 'stc-product-showcase-pro' ), $settings );
					?>
				</table>
			</div>

			<div id="tab-email" class="stc-psp-tab-panel" style="display:none;">
				<table class="form-table">
					<?php
					$this->text_row( 'admin_email', __( 'Recipient Email', 'stc-product-showcase-pro' ), $settings );
					$this->text_row( 'email_cc', __( 'CC (comma separated)', 'stc-product-showcase-pro' ), $settings );
					$this->text_row( 'email_subject', __( 'Email Subject', 'stc-product-showcase-pro' ), $settings );
					$this->text_row( 'from_name', __( 'From Name', 'stc-product-showcase-pro' ), $settings );
					$this->text_row( 'from_email', __( 'From Email', 'stc-product-showcase-pro' ), $settings );
					$this->switch_row( 'send_copy_to_user', __( 'Send Copy To Customer', 'stc-product-showcase-pro' ), $settings );
					?>
				</table>
			</div>

			<div id="tab-downloads" class="stc-psp-tab-panel" style="display:none;">
				<table class="form-table">
					<?php
					$this->switch_row( 'track_downloads', __( 'Track Downloads', 'stc-product-showcase-pro' ), $settings );
					$this->switch_row( 'show_download_count', __( 'Show Download Counter', 'stc-product-showcase-pro' ), $settings );
					$this->switch_row( 'show_file_size', __( 'Show File Size', 'stc-product-showcase-pro' ), $settings );
					$this->switch_row( 'show_pdf_icon', __( 'Show PDF Icon', 'stc-product-showcase-pro' ), $settings );
					$this->switch_row( 'open_new_tab', __( 'Open In New Tab', 'stc-product-showcase-pro' ), $settings );
					?>
				</table>
			</div>

			<div id="tab-form" class="stc-psp-tab-panel" style="display:none;">
				<p class="description"><?php esc_html_e( 'Drag to reorder. Toggle fields on/off and mark required.', 'stc-product-showcase-pro' ); ?></p>
				<?php $this->render_form_builder( $settings['form_fields'] ); ?>
			</div>

			<?php submit_button(); ?>
		</form>
		</div>
		<?php
	}

	/**
	 * Render the drag-and-drop form builder UI.
	 *
	 * @param array<int,array<string,mixed>> $fields Field definitions.
	 */
	private function render_form_builder( array $fields ): void {
		$types = STC_PSP_Form_Manager::field_types();
		$opt   = STC_PSP_Settings::OPTION;
		?>
		<div id="stc-psp-form-builder" class="stc-psp-form-builder">
			<div class="stc-psp-fb-list">
				<?php foreach ( $fields as $i => $field ) : ?>
					<?php $this->render_builder_row( $i, $field, $types, $opt ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button stc-psp-fb-add"><?php esc_html_e( '+ Add Field', 'stc-product-showcase-pro' ); ?></button>

			<script type="text/template" id="stc-psp-fb-template">
				<?php
				$this->render_builder_row(
					'__INDEX__',
					array( 'key' => '', 'type' => 'text', 'label' => '', 'enabled' => true, 'required' => false, 'options' => array() ),
					$types,
					$opt
				);
				?>
			</script>
		</div>
		<?php
	}

	/**
	 * Render one builder row.
	 *
	 * @param int|string          $i     Row index.
	 * @param array<string,mixed> $field Field.
	 * @param array<string,string> $types Field types.
	 * @param string              $opt   Option name.
	 */
	private function render_builder_row( $i, array $field, array $types, string $opt ): void {
		$base    = $opt . '[form_fields][' . $i . ']';
		$options = implode( "\n", (array) ( $field['options'] ?? array() ) );
		?>
		<div class="stc-psp-fb-row" data-index="<?php echo esc_attr( (string) $i ); ?>">
			<span class="stc-psp-fb-handle dashicons dashicons-move" title="<?php esc_attr_e( 'Drag to reorder', 'stc-product-showcase-pro' ); ?>"></span>
			<input type="text" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Label', 'stc-product-showcase-pro' ); ?>" class="stc-psp-fb-label" />
			<input type="text" name="<?php echo esc_attr( $base ); ?>[key]" value="<?php echo esc_attr( (string) ( $field['key'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'key', 'stc-product-showcase-pro' ); ?>" class="stc-psp-fb-key" />
			<select name="<?php echo esc_attr( $base ); ?>[type]" class="stc-psp-fb-type">
				<?php foreach ( $types as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $field['type'] ?? 'text', $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="<?php echo esc_attr( $base ); ?>[options]" value="<?php echo esc_attr( str_replace( "\n", '|', $options ) ); ?>" placeholder="<?php esc_attr_e( 'opt1|opt2', 'stc-product-showcase-pro' ); ?>" class="stc-psp-fb-options" />
			<label><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[enabled]" value="1" <?php checked( ! empty( $field['enabled'] ) ); ?> /> <?php esc_html_e( 'On', 'stc-product-showcase-pro' ); ?></label>
			<label><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?> /> <?php esc_html_e( 'Req', 'stc-product-showcase-pro' ); ?></label>
			<button type="button" class="button stc-psp-fb-remove" title="<?php esc_attr_e( 'Remove', 'stc-product-showcase-pro' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Templates management page.
	 */
	public function render_templates_page(): void {
		$templates = STC_PSP_Template_Repository::all();
		echo '<div class="wrap stc-psp-admin">';
		echo '<h1>' . esc_html__( 'Card Templates', 'stc-product-showcase-pro' ) . '</h1>';
		$this->maybe_render_notice();
		?>
		<p class="description"><?php esc_html_e( 'Save reusable showcase card configurations. Export/Import the JSON to move between sites.', 'stc-product-showcase-pro' ); ?></p>

		<h2><?php esc_html_e( 'Saved Templates', 'stc-product-showcase-pro' ); ?></h2>
		<table class="wp-list-table widefat fixed striped" style="max-width:800px;">
			<thead><tr>
				<th><?php esc_html_e( 'Name', 'stc-product-showcase-pro' ); ?></th>
				<th><?php esc_html_e( 'Global', 'stc-product-showcase-pro' ); ?></th>
				<th><?php esc_html_e( 'Export', 'stc-product-showcase-pro' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'stc-product-showcase-pro' ); ?></th>
			</tr></thead>
			<tbody>
				<?php if ( empty( $templates ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No templates saved yet.', 'stc-product-showcase-pro' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $templates as $tpl ) : ?>
						<?php
						$delete_url = wp_nonce_url(
							add_query_arg( array( 'page' => self::SLUG . '-templates', 'stc_action' => 'delete_template', 'id' => (int) $tpl['id'] ), admin_url( 'admin.php' ) ),
							'stc_psp_delete_template'
						);
						?>
						<tr>
							<td><?php echo esc_html( $tpl['name'] ); ?></td>
							<td><?php echo $tpl['is_global'] ? esc_html__( 'Yes', 'stc-product-showcase-pro' ) : '&mdash;'; ?></td>
							<td><textarea readonly rows="1" class="stc-psp-export" onclick="this.select();"><?php echo esc_textarea( (string) wp_json_encode( $tpl['settings'] ) ); ?></textarea></td>
							<td><a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete template?', 'stc-product-showcase-pro' ) ); ?>');"><?php esc_html_e( 'Delete', 'stc-product-showcase-pro' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Add / Import Template', 'stc-product-showcase-pro' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'stc_psp_save_template' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="template_name"><?php esc_html_e( 'Template Name', 'stc-product-showcase-pro' ); ?></label></th>
					<td><input type="text" name="template_name" id="template_name" class="regular-text" required /></td>
				</tr>
				<tr>
					<th><label for="template_settings"><?php esc_html_e( 'Settings JSON', 'stc-product-showcase-pro' ); ?></label></th>
					<td><textarea name="template_settings" id="template_settings" rows="6" class="large-text code">{}</textarea></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Global', 'stc-product-showcase-pro' ); ?></th>
					<td><label><input type="checkbox" name="template_global" value="1" /> <?php esc_html_e( 'Make available to all editors', 'stc-product-showcase-pro' ); ?></label></td>
				</tr>
			</table>
			<button type="submit" name="stc_psp_save_template" value="1" class="button button-primary"><?php esc_html_e( 'Save Template', 'stc-product-showcase-pro' ); ?></button>
		</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Tiny field helpers for the settings form
	 * ------------------------------------------------------------------ */

	/**
	 * Render a text input row.
	 *
	 * @param string              $key      Setting key.
	 * @param string              $label    Label.
	 * @param array<string,mixed> $settings Settings.
	 */
	private function text_row( string $key, string $label, array $settings ): void {
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" id="%1$s" name="%3$s[%1$s]" value="%4$s" class="regular-text" /></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( STC_PSP_Settings::OPTION ),
			esc_attr( (string) ( $settings[ $key ] ?? '' ) )
		);
	}

	/**
	 * Render a textarea row.
	 *
	 * @param string              $key      Setting key.
	 * @param string              $label    Label.
	 * @param array<string,mixed> $settings Settings.
	 */
	private function textarea_row( string $key, string $label, array $settings ): void {
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><textarea id="%1$s" name="%3$s[%1$s]" rows="3" class="large-text">%4$s</textarea></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( STC_PSP_Settings::OPTION ),
			esc_textarea( (string) ( $settings[ $key ] ?? '' ) )
		);
	}

	/**
	 * Render a yes/no switch row.
	 *
	 * @param string              $key      Setting key.
	 * @param string              $label    Label.
	 * @param array<string,mixed> $settings Settings.
	 */
	private function switch_row( string $key, string $label, array $settings ): void {
		$checked = ( ( $settings[ $key ] ?? 'no' ) === 'yes' );
		printf(
			'<tr><th scope="row">%2$s</th><td><label class="stc-psp-toggle"><input type="checkbox" name="%3$s[%1$s]" value="yes" %4$s /> %5$s</label></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( STC_PSP_Settings::OPTION ),
			checked( $checked, true, false ),
			esc_html__( 'Enable', 'stc-product-showcase-pro' )
		);
	}
}
