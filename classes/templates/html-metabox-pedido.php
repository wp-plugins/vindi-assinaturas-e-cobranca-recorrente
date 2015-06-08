<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '<p><strong>' . __( 'Status', 'woocommerce-vindi' ) . ': </strong>' . $status . ' ';
if ( $test ) {
	echo '<strong>' . __( '(Assinatura em Modo Teste)', 'woocommerce-vindi' ) . '</strong>';
}
echo '</p>';
echo '<p><strong>' . __( 'Início da Assinatura', 'woocommerce-vindi' ) . ': </strong> ' . $start . '</p>';
echo '<p><strong>' . __( 'Estágio da Assinatura', 'woocommerce-vindi' ) . ': </strong> ' . $stage . '</p>';
echo '<p><strong>' . __( 'Última Cobrança', 'woocommerce-vindi' ) . ': </strong> ' . $last_charge . '</p>';
echo '<p><strong>' . __( 'Próxima Cobrança', 'woocommerce-vindi' ) . ': </strong> ' . $next_charge . '</p>';
if ( empty( $invoiceId ) ) {
	echo '<p><strong>' . __( 'Cartão de Crédito', 'woocommerce-vindi' ) . ': </strong> ' . $creditcard . '</p>';
} else {
	echo '<p><strong>' . __( 'Link do Boleto', 'woocommerce-vindi' ) . ': </strong> ';
	echo '<a href="' . $this->api->getInvoiceDownloadURL( $invoiceId ) . '">' . __( 'Download do Boleto', 'woocommerce-vindi' ) . '</a></p>';
}