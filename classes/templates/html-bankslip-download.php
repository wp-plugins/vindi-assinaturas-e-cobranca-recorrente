<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $downloadUrl ) ) {
	return;
}

?>

<div class="woocommerce-message">
	<span>
		<a class="button" href="<?php echo esc_url( $downloadUrl ); ?>" target="_blank">
			<?php _e( 'Baixar boleto', 'woocommerce-vindi' ); ?>
		</a>
		<?php _e( 'Por favor, clique no botão ao lado para realizar o download do Boleto Bancário.', 'woocommerce-vindi' ); ?>
		<br/>
		<?php _e( 'Você pode imprimi-lo e pagá-lo via internet banking ou em agências bancárias e lotéricas.', 'woocommerce-vindi' ); ?>
		<br/>
		<?php _e( 'Após recebermos a confirmação do pagamento, seu pedido será processado.', 'woocommerce-vindi' ); ?>
	</span>
</div>