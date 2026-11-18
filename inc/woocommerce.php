<?php
/**
 * WooCommerce compatibility (optional – only loads when WooCommerce is active).
 *
 * @package KhoaHoc
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'woocommerce' );
	}
);

if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
	return;
}

// Theme wrappers around shop pages.
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
add_action(
	'woocommerce_before_main_content',
	function () {
		echo '<div class="kh-container kh-page kh-woo">';
	},
	10
);
add_action(
	'woocommerce_after_main_content',
	function () {
		echo '</div>';
	},
	10
);

// Digital courses: skip shipping fields that confuse buyers.
add_filter(
	'woocommerce_checkout_fields',
	function ( $fields ) {
		foreach ( array( 'billing_company', 'billing_address_2', 'billing_postcode', 'billing_state' ) as $k ) {
			unset( $fields['billing'][ $k ] );
		}
		return $fields;
	}
);

// Cart count in header updates via AJAX fragments.
add_filter(
	'woocommerce_add_to_cart_fragments',
	function ( $fragments ) {
		$fragments['span.kh-cart-count'] = '<span class="kh-cart-count">' . (int) WC()->cart->get_cart_contents_count() . '</span>';
		return $fragments;
	}
);
