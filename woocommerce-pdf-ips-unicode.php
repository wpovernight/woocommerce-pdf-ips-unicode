<?php
/**
 * Plugin Name: PDF Invoices & Packing Slips for WooCommerce - Unicode Language Pack
 * Plugin URI:  http://www.wpovernight.com
 * Description: Adds the Arial Unicode MS font to PDF Invoices & Packing Slips for WooCommerce for extended character support
 * Version:     1.0.1
 * Author:      WP Overnight
 * Author URI:  http://www.wpovernight.com
 * License:     GPLv2 or later
 * License URI: http://www.opensource.org/licenses/gpl-license.php
 */

function wpo_wcpdf_arial_unicode( $document_type, $document ) {
	?>
		/* Load font */
		@font-face {
			font-family: 'Arial Unicode MS';
			font-style: normal;
			font-weight: normal;
			src: local('Arial Unicode MS'), local('Arial Unicode MS'), url('<?php echo dirname(__FILE__); ?>/fonts/Arial Unicode.ttf') format('truetype');
		}
		@font-face {
			font-family: 'Arial Unicode MS';
			font-style: normal;
			font-weight: bold;
			src: local('Arial Unicode MS Bold'), local('Arial Unicode MS-Bold'), url('<?php echo dirname(__FILE__); ?>/fonts/Arial Unicode.ttf') format('truetype');
		}
		@font-face {
			font-family: 'Arial Unicode MS';
			font-style: italic;
			font-weight: normal;
			src: local('Arial Unicode MS Italic'), local('Arial Unicode MS-Italic'), url('<?php echo dirname(__FILE__); ?>/fonts/Arial Unicode.ttf') format('truetype');
		}
		@font-face {
			font-family: 'Arial Unicode MS';
			font-style: italic;
			font-weight: bold;
			src: local('Arial Unicode MS Bold Italic'), local('Arial Unicode MS-BoldItalic'), url('<?php echo dirname(__FILE__); ?>/fonts/Arial Unicode.ttf') format('truetype');
		}

		/* Override font */
		body {
			font-family: 'Arial Unicode MS' !important;
		}
	<?php
}

add_action( 'wpo_wcpdf_custom_styles', 'wpo_wcpdf_arial_unicode', 10, 2 );

add_filter( 'wpo_wcpdf_dompdf_options', function( $options ) {
	$options['isFontSubsettingEnabled'] = true;
	return $options;
}, 20, 1 );
