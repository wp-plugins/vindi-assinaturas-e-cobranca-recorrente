<?php

class WC_Product_Vindi_Subscription extends WC_Product_Simple {
	/**
	 * __construct function.
	 * @access public
	 *
	 * @param mixed $product
	 */
	public function __construct( $product ) {
		parent::__construct( $product );
		$this->product_type = 'vindi-subscription';
	}

	/**
	 * Get the add to cart button text
	 * @access public
	 * @return string
	 */
	public function add_to_cart_text() {
		$text = $this->is_purchasable() && $this->is_in_stock() ? __( 'Assinar', 'woocommerce-vindi' ) : parent::add_to_cart_text();

		return apply_filters( 'woocommerce_product_add_to_cart_text', $text, $this );
	}

	/**
	 * Get the add to cart button text for the single page
	 * @access public
	 * @return string
	 */
	public function single_add_to_cart_text() {
		return apply_filters( 'woocommerce_product_single_add_to_cart_text', self::add_to_cart_text(), $this );
	}
}