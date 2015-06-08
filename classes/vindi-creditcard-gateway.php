<?php

class WC_Vindi_CreditCard_Gateway extends WC_Vindi_Base_Gateway {

	/**
	 * @var int
	 */
	private $maxInstallments = 12;

	/**
	 * Class Constructor.
	 */
	public function __construct() {

		$this->id           = 'vindi-wc-creditcard';
		$this->method_title = __( 'Vindi - Cartão de Crédito', 'woocommerce-vindi' );
		$this->has_fields   = true;

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values
		$this->smallestInstallment = $this->get_option( 'smallestInstallment' );
		$this->installments        = $this->get_option( 'installments' );

		parent::__construct();

		add_action( 'wp_enqueue_scripts', [ &$this, 'checkoutScript' ] );

		$this->checkApiCall();
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
			'enabled'             => [
				'title'   => __( 'Habilitar/Desabilitar', 'woocommerce-vindi' ),
				'label'   => __( 'Habilitar pagamento via Cartão de Crédito com a Vindi', 'woocommerce-vindi' ),
				'type'    => 'checkbox',
				'default' => 'no',
			],
			'title'               => [
				'title'       => __( 'Título', 'woocommerce-vindi' ),
				'type'        => 'text',
				'description' => __( 'Título que o cliente verá durante o processo de pagamento.', 'woocommerce-vindi' ),
				'default'     => __( 'Cartão de Crédito', 'woocommerce-vindi' ),
			],
			'apiKey'              => [
				'title'       => __( 'Chave da API Vindi', 'woocommerce-vindi' ),
				'type'        => 'text',
				'description' => __( 'A Chave da API de sua conta na Vindi. ' . $prospects_url, 'woocommerce-vindi' ),
				'default'     => '',
			],
			'returnStatus'        => [
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
			'singleCharge'        => [
				'title' => __( 'Vendas Avulsas', 'vindi-woocommerce' ),
				'type'  => 'title',
			],
			'smallestInstallment' => [
				'title'       => __( 'Valor mínimo da parcela', 'woocommerce-vindi' ),
				'type'        => 'text',
				'description' => __( 'Valor mínimo da parcela, não deve ser inferior a R$ 5,00.', 'woocommerce-vindi' ),
				'default'     => '5',
			],
			'installments'        => [
				'title'       => __( 'Número máximo de parcelas', 'woocommerce-vindi' ),
				'type'        => 'select',
				'description' => __( 'Número máximo de parcelas para vendas avulsas. Deixe em 1x para desativar o parcelamento.', 'woocommerce-vindi' ),
				'default'     => '1',
				'options'     => [
					'1'  => '1x',
					'2'  => '2x',
					'3'  => '3x',
					'4'  => '4x',
					'5'  => '5x',
					'6'  => '6x',
					'7'  => '7x',
					'8'  => '8x',
					'9'  => '9x',
					'10' => '10x',
					'11' => '11x',
					'12' => '12x',
				],
			],
			'testing'             => [
				'title' => __( 'Testes', 'cielo-woocommerce' ),
				'type'  => 'title',
			],
			'debug'               => [
				'title'       => __( 'Log de Depuração', 'woocommerce-vindi' ),
				'label'       => __( 'Ativar Logs', 'woocommerce-vindi' ),
				'type'        => 'checkbox',
				'description' => sprintf( __( 'Ative esta opção para habilitar logs de depuração do servidor. %s', 'woocommerce-vindi' ), $logs_url ),
				'default'     => 'no',
			],
		];
	}

	/**
	 * Check if this gateway is enabled and available in the user's country
	 * @return bool
	 */
	public function is_available() {
		$methods   = $this->api->getPaymentMethods();
		$ccMethods = $methods['credit_card'];

		return 'yes' === $this->enabled
		       && 'BR' === $this->getCountryCode()
		       && count( $ccMethods )
		       && $this->checkSsl();
	}

	/**
	 * Payment fields for Vindi Direct Checkout
	 * @return void
	 */
	public function payment_fields() {
		/** @var WooCommerce $woocommerce */
		global $woocommerce;

		if ( $this->isSingleOrder() && $this->installments > 1 ) {
			$total = $woocommerce->cart->total;

			$installments = '';
			for ( $i = 1; $i <= $this->installments; $i ++ ) {
				$value = ceil( $total / $i * 100 ) / 100;

				if ( $value >= $this->smallestInstallment ) {
					$price = wc_price( $value );
					$installments .= '<option value="' . $i . '">' . sprintf( __( '%dx de %s', 'woocommerce-vindi' ), $i, $price ) . '</option>';
				} else {
					$this->maxInstallments = $i - 1;
					break;
				}
			}
		}

		$user_country = $this->getCountryCode();

		if ( empty( $user_country ) ) {
			_e( 'Selecione o País para visualizar as formas de pagamento.', 'woocommerce-vindi' );

			return;
		}

		if ( $user_country != 'BR' ) {
			_e( 'Vindi não está disponível no seu País.', 'woocommerce-vindi' );

			return;
		}

		$paymentMethods = $this->api->getPaymentMethods();

		if ( $paymentMethods === false || empty( $paymentMethods ) || ! count( $paymentMethods['credit_card'] ) ) {
			_e( 'Estamos enfrentando problemas técnicos no momento. Tente novamente mais tarde ou entre em contato.', 'woocommerce-vindi' );

			return;
		}

		$months = '<option value="">' . __( 'Mês', 'woocommerce-vindi' ) . '</option>';
		for ( $i = 1; $i <= 12; $i ++ ) {
			$timestamp = mktime( 0, 0, 0, $i, 1 );
			$num       = date( 'm', $timestamp );
			$name      = date( 'F', $timestamp );
			$months .= sprintf( '<option value="%s">%02d - %s</option>', $num, $num, __( $name ) );
		}

		$years = '<option value="">' . __( 'Ano', 'woocommerce-vindi' ) . '</option>';
		for ( $i = date( 'Y' ); $i <= date( 'Y' ) + 15; $i ++ ) {
			$years .= sprintf( '<option value="%u">%u</option>', $i, $i );
		}

		$isTrial = $this->api->isMerchantStatusTrial();

		$this->getTemplate( 'html-creditcard-checkout.php', compact( 'months', 'years', 'installments', 'isTrial' ) );
	}

	/**
	 * Validate payment fields
	 * @return void
	 */
	public function validate_fields() {
		$ccFields = [
			'vindi_cc_fullname'    => __( 'Nome do Portador do Cartão de Crédito requerido.', 'woocommerce-vindi' ),
			'vindi_cc_number'      => __( 'Número do Cartão de Crédito requerido.', 'woocommerce-vindi' ),
			'vindi_cc_cvc'         => __( 'Código de Segurança do Cartão requerido.', 'woocommerce-vindi' ),
			'vindi_cc_monthexpiry' => __( 'Mês de Validade do Cartão requerido.', 'woocommerce-vindi' ),
			'vindi_cc_yearexpiry'  => __( 'Ano de Validade do Cartão requerido.', 'woocommerce-vindi' ),
		];

		foreach ( $ccFields as $field => $message ) {
			if ( ! isset( $_POST[ $field ] ) || empty( $_POST[ $field ] ) ) {
				wc_add_notice( $message, 'error' );
			}
		}

		/* Validate expiry date */
		$now      = time();
		$ccExpiry = mktime( 0, 0, 0, (int) $_POST['vindi_cc_monthexpiry'], 1, (int) $_POST['vindi_cc_yearexpiry'] );
		if ( $now > $ccExpiry ) {
			wc_add_notice( __( 'Este cartão de crédito já expirou. Tente novamente com outro cartão de crédito dentro do prazo de validade.', 'woocommerce-vindi' ), 'error' );
		}

		if ( $this->isSingleOrder() && $this->installments > 1 ) {
			if ( ! isset( $_POST['vindi_cc_installments'] ) || empty( $_POST['vindi_cc_installments'] ) ) {
				wc_add_notice( __( 'Quantidade de Parcelas requerido.', 'woocommerce-vindi' ), 'error' );
			}

			if ( 1 > $_POST['vindi_cc_installments'] || $this->maxInstallments < $_POST['vindi_cc_installments'] ) {
				wc_add_notice( __( 'A Quantidade de Parcelas escolhidas é inválida.', 'woocommerce-vindi' ), 'error' );
			}
		}

		$this->validated = ! wc_notice_count();
	}

	/**
	 * Check if object is being acessed via a webhook.
	 */
	protected function checkApiCall() {
		if (
			isset( $_GET['action'] ) && ( 'api' === $_GET['action'] ) &&
			isset( $_GET['token'] ) && ( $this->getToken() === $_GET['token'] )
		) {
			$webhooks = new WC_Vindi_Webhooks( $this );
			$webhooks->handle();
		}
	}

	/**
	 * Checkout scripts
	 * @return void
	 */
	public function checkoutScript() {
		if ( is_checkout() ) {
			if ( ! ( get_query_var( 'order-received' ) ) ) {
				wp_enqueue_script( 'vindi-checkout', plugin_dir_url( __FILE__ ) . '../assets/js/checkout.js', [
					'jquery',
					'jquery-payment',
				] );
			}
		}
	}

	/**
	 * Should return payment type for payment processing.
	 * @return string
	 */
	public function type() {
		return 'cc';
	}
}