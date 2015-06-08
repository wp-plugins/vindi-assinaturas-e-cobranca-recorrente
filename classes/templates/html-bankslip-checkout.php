<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $isTrial ):
	?>
	<div style="padding: 10px;border: 1px solid #f00; background-color: #fdd; color: #f00; margin: 10px 2px">
		<h3 style="color: #f00"><?php _e( 'MODO DE TESTES', 'woocommerce-vindi' ); ?></h3>

		<p>
			<?php _e( 'Sua conta na Vindi está em <strong>Modo Trial</strong>. Este modo é proposto para a realização de testes e, portanto, nenhum pedido será efetivamente cobrado.', 'woocommerce-vindi' ); ?>
		</p>
	</div>
	<?php
endif;
?>
<fieldset>
	<div class="vindi-invoice-description" style="padding: 20px 0; font-weight: bold;">
		<?php _e( 'Um Boleto Bancário será enviado mensalmente para o seu endereço de e-mail.', 'woocommerce-vindi' ); ?>
	</div>
</fieldset>
