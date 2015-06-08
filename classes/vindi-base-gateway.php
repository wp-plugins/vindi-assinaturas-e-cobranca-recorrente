<?php

abstract class WC_Vindi_Base_Gateway extends WC_Payment_Gateway {

	/**
	 * @var bool
	 */
	protected $validated = true;

	/**
	 * @var WC_Vindi_API
	 */
	public $api;

	/**
	 * @var bool
	 */
	protected $debug = false;

	/**
	 * Should return payment type for payment processing.
	 * @return string
	 */
	public abstract function type();

	/**
	 * WC_Vindi_Base_Gateway constructor.
	 */
	public function __construct() {

		$this->debug        = 'yes' === $this->get_option( 'debug' );
		$this->title        = $this->get_option( 'title' );
		$this->enabled      = $this->get_option( 'enabled' );
		$this->apiKey       = $this->get_option( 'apiKey' );
		$this->returnStatus = $this->get_option( 'returnStatus' );

		if ( $this->debug ) {
			$this->logger = new WC_Logger();
		}

		// Instanciate API Connector
		$this->api = new WC_Vindi_API( $this );

		// Hooks
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
				&$this,
				'process_admin_options',
			] );
			add_action( 'add_meta_boxes_shop_order', [ &$this, 'vindi_order_metabox' ] );

			add_filter( 'product_type_selector', [ &$this, 'vindiSubscriptionProductType' ] );

			add_action( 'save_post', [ &$this, 'vindiSaveSubscriptionMeta' ] );
		}
	}

	/**
	 * Admin Panel Options
	 * @return void
	 */
	public function admin_options() {
		include_once( 'templates/html-admin-options.php' );
	}

	/**
	 * Check if SSL is enabled when merchant is not trial.
	 * @return boolean
	 */
	protected function checkSsl() {
		return $this->api->isMerchantStatusTrial()
		       || ( 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) && is_ssl() );
	}

	/**
	 * Add message to log if debug is active
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public function log( $message ) {
		if ( $this->debug ) {
			$this->logger->add( 'vindi-wc', $message );
		}
	}

	/**
	 * @param int $post_id
	 */
	public function vindiSaveSubscriptionMeta( $post_id ) {
		if ( ! isset( $_POST['product-type'] ) || ( 'vindi-subscription' !== $_POST['product-type'] ) ) {
			return;
		}

		$subscription_price = stripslashes( $_REQUEST['vindi_subscription_price'] );
		$subscription_plan  = (int) stripslashes( $_REQUEST['vindi_subscription_plan'] );

		update_post_meta( $post_id, 'vindi_subscription_price', $subscription_price );
		update_post_meta( $post_id, '_regular_price', $subscription_price );
		update_post_meta( $post_id, '_price', $subscription_price );

		update_post_meta( $post_id, 'vindi_subscription_plan', $subscription_plan );
	}

	/**
	 * Get the users country either from their order, or from their customer data
	 * @return string|null
	 */
	public function getCountryCode() {
		global $woocommerce;

		if ( isset( $_GET['order_id'] ) ) {

			$order = new WC_Order( $_GET['order_id'] );

			return $order->billing_country;
		} elseif ( $woocommerce->customer->get_country() ) {

			return $woocommerce->customer->get_country();
		}

		return null;
	}

	/**
	 * Return the URL that will receive the webhooks.
	 * @return string
	 */
	public function getEventsUrl() {
		return get_site_url() . "/wc-api/wc_vindi_creditcard_gateway/?action=api&token=" . $this->getToken();
	}

	/**
	 * An unique token used to check the validity of webhooks.
	 * @return string
	 */
	public function getToken() {
		return sanitize_file_name( wp_hash( 'vindi-wc' ) );
	}

	/**
	 * @param $types
	 *
	 * @return mixed
	 */
	public function vindiSubscriptionProductType( $types ) {
		$types['vindi-subscription'] = __( 'Assinatura Vindi', 'woocommerce-vindi' );

		return $types;
	}

	/**
	 * Create Vindi Order Meta Box
	 * @return void
	 */
	public function vindi_order_metabox() {
		add_meta_box( 'vindi-wc-subscription-meta-box',
			__( 'Assinatura Vindi', 'woocommerce-vindi' ),
			[ &$this, 'vindi_order_metabox_content' ],
			'shop_order',
			'normal',
			'default'
		);
	}

	/**
	 * Validate plugin settings
	 * @return bool
	 */
	public function validateSettings() {
		$currency = get_option( 'woocommerce_currency' );

		return in_array( $currency, [ 'BRL' ] ) && ! empty( $this->apiKey );
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$this->log( sprintf( 'Processando pedido %s.', $order_id ) );
		$order   = new WC_Order( $order_id );
		$payment = new WC_Vindi_Payment( $order, $this );

		// exit if validation by validate_fields() fails
		if ( ! $this->validated ) {
			return false;
		}

		// Validate plugin settings
		if ( ! $this->validateSettings() ) {
			return $payment->abort( __( 'O Pagamento foi cancelado devido a erro de configuração do meio de pagamento.', 'woocommerce-vindi' ) );
		}

		try {
			$response = $payment->process();
			$order->reduce_order_stock();
		} catch ( Exception $e ) {
			$response = [
				'result'   => 'fail',
				'redirect' => '',
			];
		}

		return $response;
	}

	/**
	 * WC Get Template helper.
	 *
	 * @param       $name
	 * @param array $args
	 */
	protected function getTemplate( $name, $args = [ ] ) {
		wc_get_template( $name, $args, '', plugin_dir_path( __FILE__ ) . 'templates/' );
	}

	/**
	 * Check if the order is a Single Payment Order (not a Subscription).
	 * @return bool
	 */
	protected function isSingleOrder() {
		/** @var WooCommerce $woocommerce */
		global $woocommerce;
		$items = $woocommerce->cart->cart_contents;

		foreach ( $items as $item ) {
			if ( 'vindi-subscription' === $item['data']->product_type ) {
				return false;
			}
		}

		return true;
	}
}