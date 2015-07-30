<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

global $product;

if ( ! $product->is_purchasable() && ( ! is_user_logged_in() ) ) {
	return;
}

if ( ! $product->is_in_stock() ) : ?>
	<link itemprop="availability" href="http://schema.org/OutOfStock">
<?php else : ?>

	<link itemprop="availability" href="http://schema.org/InStock">

	<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

	<form class="cart" method="post" enctype='multipart/form-data'>

		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->id ); ?>"/>

		<button type="submit" class="single_add_to_cart_button button alt">
			<?php echo $product->single_add_to_cart_text(); ?>
		</button>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

	</form>

	<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

<?php endif; ?>
