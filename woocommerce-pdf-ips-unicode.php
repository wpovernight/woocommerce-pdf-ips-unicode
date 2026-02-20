<?php
/**
 * Plugin Name:      PDF Invoices & Packing Slips for WooCommerce - Unicode Font Pack
 * Requires Plugins: woocommerce-pdf-invoices-packing-slips
 * Plugin URI:       https://github.com/wpovernight/woocommerce-pdf-ips-unicode
 * Description:      Adds Unicode-capable fonts to PDF Invoices & Packing Slips for WooCommerce to improve character support across multiple languages.
 * Version:          2.0.0
 * Author:           WP Overnight
 * Author URI:       https://wpovernight.com/
 * License:          GPLv3
 * License URI:      https://opensource.org/licenses/gpl-license.php
 * Text Domain:      woocommerce-pdf-ips-unicode
 * Domain Path:      /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPO_IPS_Unicode_Font_Pack' ) ) :
	
$plugin_path           = plugin_dir_path( __FILE__ );
$plugin_directory_name = basename( $plugin_path );
$plugin_file           = $plugin_directory_name . '/woocommerce-pdf-ips-unicode.php';
$github_updater_file   = $plugin_path . 'github-updater/GitHubUpdater.php';

if ( ! class_exists( '\\WPO\\GitHubUpdater\\GitHubUpdater' ) && file_exists( $github_updater_file ) ) {
	require_once $github_updater_file;
}

if ( class_exists( '\\WPO\\GitHubUpdater\\GitHubUpdater' ) ) {
	$gitHubUpdater = new \WPO\GitHubUpdater\GitHubUpdater( $plugin_file );
	$gitHubUpdater->setChangelog( 'CHANGELOG.md' );
	$gitHubUpdater->add();
}

final class WPO_IPS_Unicode_Font_Pack {

	public const OPTION_KEY = 'unicode_font';

	private static ?self $instance = null;

	/**
	 * Get the singleton instance of the plugin class.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_filter( 'wpo_wcpdf_settings_fields_general', array( $this, 'add_setting_field' ), 10, 5 );
		add_filter( 'wpo_wcpdf_general_settings_categories', array( $this, 'add_setting_to_category' ), 10, 2 );

		add_action( 'wpo_wcpdf_custom_styles', array( $this, 'output_custom_styles' ), 10, 2 );

		add_filter( 'wpo_wcpdf_dompdf_options', array( $this, 'enable_dompdf_font_subsetting' ), 20, 1 );
	}

	/**
	 * Load the plugin's text domain for translations.
	 * 
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'woocommerce-pdf-ips-unicode',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Add the Unicode font setting field to the general settings.
	 * 
	 * @param array  $settings_fields
	 * @param string $page
	 * @param string $option_group
	 * @param string $option_name
	 * @param object $general_settings
	 * @return array
	 */
	public function add_setting_field( array $settings_fields, string $page, string $option_group, string $option_name, $general_settings ): array {
		if ( ! function_exists( 'WPO_WCPDF' ) || ! class_exists( '\WPO\IPS\Settings' ) ) {
			return $settings_fields;
		}

		$insert_settings = array(
			array(
				'type'     => 'setting',
				'id'       => self::OPTION_KEY,
				'title'    => __( 'Unicode font', 'woocommerce-pdf-ips-unicode' ),
				'callback' => 'select',
				'section'  => 'general_settings',
				'args'     => array(
					'option_name' => $option_name,
					'id'          => self::OPTION_KEY,
					'default'     => 'noto',
					'options'     => apply_filters(
						'wpo_wcpdf_unicode_font_options',
						array(
							'noto'       => __( 'Noto Sans', 'woocommerce-pdf-ips-unicode' ),
							'dejavu'     => __( 'DejaVu Sans', 'woocommerce-pdf-ips-unicode' ),
							'liberation' => __( 'Liberation Sans', 'woocommerce-pdf-ips-unicode' ),
						)
					),
					'description' => __( 'Select an alternative font to improve character support for certain languages/scripts.', 'woocommerce-pdf-ips-unicode' ),
				),
			),
		);

		return \WPO\IPS\Settings::instance()->move_setting_after_id(
			$settings_fields,
			$insert_settings,
			'font_subsetting'
		);
	}

	/**
	 * Add the Unicode font setting to the "Advanced Formatting" category.
	 * 
	 * @param array  $settings_categories
	 * @param object $settings_general
	 * @return array
	 */
	public function add_setting_to_category( array $settings_categories, $settings_general ): array {
		if ( empty( $settings_categories['advanced_formatting']['members'] ) || ! is_array( $settings_categories['advanced_formatting']['members'] ) ) {
			return $settings_categories;
		}

		$members = $settings_categories['advanced_formatting']['members'];

		if ( in_array( self::OPTION_KEY, $members, true ) ) {
			return $settings_categories;
		}

		$pos = array_search( 'font_subsetting', $members, true );

		if ( false !== $pos ) {
			array_splice( $members, $pos + 1, 0, array( self::OPTION_KEY ) );
		} else {
			$members[] = self::OPTION_KEY;
		}

		$settings_categories['advanced_formatting']['members'] = $members;

		return $settings_categories;
	}

	/**
	 * Output custom CSS styles to include the selected Unicode font in the PDF.
	 * 
	 * @param string $document_type
	 * @param object $document
	 * @return void
	 */
	public function output_custom_styles( string $document_type, $document ): void {
		$base_url = plugin_dir_url( __FILE__ ) . 'fonts/';

		$general_settings = get_option( 'wpo_wcpdf_settings_general', array() );
		$selected         = isset( $general_settings[ self::OPTION_KEY ] ) && is_string( $general_settings[ self::OPTION_KEY ] )
			? $general_settings[ self::OPTION_KEY ]
			: 'noto';

		$fonts = $this->get_font_registry( $document_type, $document );

		if ( empty( $fonts[ $selected ] ) ) {
			$selected = 'noto';
		}
		if ( empty( $fonts[ $selected ]['files']['regular'] ) ) {
			return;
		}

		$family = $fonts[ $selected ]['family'];
		$files  = $fonts[ $selected ]['files'];

		?>
		/* Unicode font: <?php echo esc_html( $selected ); ?> */

		@font-face {
			font-family: '<?php echo esc_attr( $family ); ?>';
			font-style: normal;
			font-weight: 400;
			src: url('<?php echo esc_url( $base_url . $files['regular'] ); ?>') format('truetype');
		}

		<?php if ( ! empty( $files['bold'] ) ) : ?>
		@font-face {
			font-family: '<?php echo esc_attr( $family ); ?>';
			font-style: normal;
			font-weight: 700;
			src: url('<?php echo esc_url( $base_url . $files['bold'] ); ?>') format('truetype');
		}
		<?php endif; ?>

		<?php if ( ! empty( $files['italic'] ) ) : ?>
		@font-face {
			font-family: '<?php echo esc_attr( $family ); ?>';
			font-style: italic;
			font-weight: 400;
			src: url('<?php echo esc_url( $base_url . $files['italic'] ); ?>') format('truetype');
		}
		<?php endif; ?>

		<?php if ( ! empty( $files['bold_italic'] ) ) : ?>
		@font-face {
			font-family: '<?php echo esc_attr( $family ); ?>';
			font-style: italic;
			font-weight: 700;
			src: url('<?php echo esc_url( $base_url . $files['bold_italic'] ); ?>') format('truetype');
		}
		<?php endif; ?>

		body {
			font-family: '<?php echo esc_attr( $family ); ?>', sans-serif !important;
		}
		<?php
	}

	/**
	 * Enable font subsetting in Dompdf to reduce PDF file size when using Unicode fonts.
	 * 
	 * @param array $options
	 * @return array
	 */
	public function enable_dompdf_font_subsetting( array $options ): array {
		$options['isFontSubsettingEnabled'] = true;
		return $options;
	}

	/**
	 * Get the registry of available Unicode fonts.
	 * 
	 * @param string $document_type
	 * @param object $document
	 * @return array
	 */
	private function get_font_registry( string $document_type, $document ): array {
		$fonts = array(
			'noto' => array(
				'family' => 'WPO Unicode Noto',
				'files'  => array(
					'regular'     => 'NotoSans-Regular.ttf',
					'bold'        => 'NotoSans-Bold.ttf',
					'italic'      => 'NotoSans-Italic.ttf',
					'bold_italic' => 'NotoSans-BoldItalic.ttf',
				),
			),
			'dejavu' => array(
				'family' => 'WPO Unicode DejaVu',
				'files'  => array(
					'regular'     => 'DejaVuSans.ttf',
					'bold'        => 'DejaVuSans-Bold.ttf',
					'italic'      => 'DejaVuSans-Oblique.ttf',
					'bold_italic' => 'DejaVuSans-BoldOblique.ttf',
				),
			),
			'liberation' => array(
				'family' => 'WPO Unicode Liberation',
				'files'  => array(
					'regular'     => 'LiberationSans-Regular.ttf',
					'bold'        => 'LiberationSans-Bold.ttf',
					'italic'      => 'LiberationSans-Italic.ttf',
					'bold_italic' => 'LiberationSans-BoldItalic.ttf',
				),
			),
		);

		return apply_filters( 'wpo_wcpdf_unicode_font_registry', $fonts, $document_type, $document );
	}
}

WPO_IPS_Unicode_Font_Pack::instance();

endif;
