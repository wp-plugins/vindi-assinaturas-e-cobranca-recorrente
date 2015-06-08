<?php

class WC_Vindi_BankSlip_Gateway extends WC_Vindi_Base_Gateway {

	/**
	 * Class Constructor.
	 */
	public function __construct() {

		$this->id           = 'vindi-wc-bankslip';
		$this->method_title = __( 'Vindi - Boleto Bancário', 'woocommerce-vindi' );
		$this->has_fields   = true;

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		add_action( 'woocommerce_thankyou_' . $this->id, [ &$this, 'thank_you_page' ] );

		parent::__construct();
	}

	/**
	 * Check if this gateway is enabled and available in the user's country
	 * @return bool
	 */
	public function is_available() {
		return 'yes' === $this->enabled
		       && 'BR' === $this->getCountryCode()
		       && $this->api->acceptBankSlip()
		       && $this->checkSsl();
	}

	/**
	 * Payment fields for Vindi Direct Checkout
	 * @return void
	 */
	public function payment_fields() {

		$user_country = $this->getCountryCode();

		if ( empty( $user_country ) ) {
			_e( 'Selecione o País para visualizar as formas de pagamento.', 'woocommerce-vindi' );

			return;
		}

		if ( $user_country != 'BR' ) {
			_e( 'Vindi não está disponível no seu País.', 'woocommerce-vindi' );

			return;
		}

		if ( ! $this->api->acceptBankSlip() ) {
			_e( 'Este método de pagamento não é aceito.', 'woocommerce-vindi' );

			return;
		}
		$isTrial = $this->api->isMerchantStatusTrial();

		$this->getTemplate( 'html-bankslip-checkout.php', compact( 'isTrial' ) );
	}

	/**
	 * Display download button for invoice.
	 *
	 * @param int $order_id
	 */
	public function thank_you_page( $order_id ) {
		if ( $downloadUrl = get_post_meta( $order_id, 'vindi_wc_invoice_download_url', true ) ) {
			$this->getTemplate( 'html-bankslip-download.php', compact( 'downloadUrl' ) );
		}
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 * @return void
	 */
	public function init_form_fields() {
		$url      = admin_url( 'admin.php?page=wc-status&tab=logs&log_file=vindi-wc-' . $this->getToken() . '-log' );
		$logs_url = '<a href="' . $url . '" target="_blank">' . __( 'Ver Logs', 'woocommerce-vindi' ) . '</a>';

		$prospects_url = '<a href="https://app.vindi.com.br/prospects/new" target="_blank">' . __( 'Não possui uma conta?', 'woocommerce-vindi' ) . '</a>';

		$this->form_fields = [
			'enabled'      => [
				'title'   => __( 'Habilitar/Desabilitar', 'woocommerce-vindi' ),
				'label'   => __( 'Habilitar pagamento por Boleto Bancário com Vindi', 'woocommerce-vindi' ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'title'        => [
				'title'       => __( 'Título', 'woocommerce-vindi' ),
				'type'        => 'text',
				'description' => __( 'Título que o cliente verá durante o processo de pagamento.', 'woocommerce-vindi' ),
				'default'     => __( 'Boleto Bancário', 'woocommerce-vindi' ),
			],
			'apiKey'       => [
				'title'       => __( 'Chave da API Vindi', 'woocommerce-vindi' ),
				'type'        => 'text',
				'description' => __( 'A Chave da API de sua conta na Vindi. ' . $prospects_url, 'woocommerce-vindi' ),
				'default'     => '',
			],
			'returnStatus' => [
				'title'       => __( 'Status de conclusão do pedido', 'woocommerce-vindi' ),
				'type'        => 'select',
				'description' => __( 'Status que o pedido deverá ter após receber a confirmação de pagamento da Vindi.', 'woocommerce-vindi' ),
				'default'     => 'processing',
				'options'     => [
					'processing' => 'Processando',
					'on-hold'    => 'Aguardando',
					'completed'  => 'Concluído',
				],
			],
			'testing'      => [
				'title' => __( 'Testes', 'vindi-woocommerce' ),
				'type'  => 'title',
			],
			'debug'        => [
				'title'       => __( 'Log de Depuração', 'woocommerce-vindi' ),
				'label'       => __( 'Ativar Logs', 'woocommerce-vindi' ),
				'type'        => 'checkbox',
				'description' => sprintf( __( 'Ative esta opção para habilitar logs de depuração do servidor. %s', 'woocommerce-vindi' ), $logs_url ),
				'default'     => 'no',
			],
		];
	}

	/**
	 * Should return payment type for payment processing.
	 * @return string
	 */
	public function type() {
		return 'invoice';
	}
}