<?php

class WC_Vindi_Payment {
	/**
	 * Order type is invalid.
	 */
	const ORDER_TYPE_INVALID = 0;

	/**
	 * Order type is Subscription Payment.
	 */
	const ORDER_TYPE_SUBSCRIPTION = 1;

	/**
	 * Order type is Single Payment.
	 */
	const ORDER_TYPE_SINGLE = 2;

	/**
	 * Order that will be paid;
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * Vindi Gateway.
	 * @var WC_Vindi_Base_Gateway
	 */
	protected $gateway;

	/**
	 * @param WC_Order              $order
	 * @param WC_Vindi_Base_Gateway $gateway
	 */
	function __construct( $order, WC_Vindi_Base_Gateway $gateway ) {
		$this->order   = $order;
		$this->gateway = $gateway;
	}

	/**
	 * Validate order to chose payment type.
	 * @return int order type.
	 */
	public function validateOrder() {
		$items = $this->order->get_items();

		foreach ( $items as $item ) {
			$product = $this->order->get_product_from_item( $item );

			if ( $product->is_type( 'vindi-subscription' ) ) {
				if ( 1 === count( $items ) ) {
					return static::ORDER_TYPE_SUBSCRIPTION;
				}

				return static::ORDER_TYPE_INVALID;
			}
		}

		return static::ORDER_TYPE_SINGLE;
	}

	/**
	 * Retrieve Plan for Vindi Subscription.
	 * @return int|bool
	 */
	public function getPlan() {
		$items   = $this->order->get_items();
		$item    = array_shift( $items ); //get only the first item
		$product = $this->order->get_product_from_item( $item );

		$vindiPlan = get_post_meta( $product->id, 'vindi_subscription_plan', true );

		if ( ! $product->is_type( 'vindi-subscription' ) || empty( $vindiPlan ) ) {
			$this->abort( __( 'O produto selecionado não é uma assinatura.', 'woocommerce-vindi' ), true );
		}

		return $vindiPlan;
	}

	/**
	 * Find or Create a Customer at Vindi for the given credentials.
	 * @return array|bool
	 */
	public function getCustomer() {
		$currentUser = wp_get_current_user();

		$email = $this->order->billing_email;

		$address = [
			'street'             => $this->order->billing_address_1,
			'number'             => $this->order->billing_number,
			'additional_details' => $this->order->billing_address_2,
			'zipcode'            => $this->order->billing_postcode,
			'neighborhood'       => $this->order->billing_neighborhood,
			'city'               => $this->order->billing_city,
			'state'              => $this->order->billing_state,
			'country'            => $this->order->billing_country,
		];

		$userId = $currentUser->ID;

		if ( ! $userCode = get_user_meta( $userId, 'vindi_user_code', true ) ) {
			$userCode = 'wc-' . $userId . '-' . time();

			add_user_meta( $userId, 'vindi_user_code', $userCode, true );
		}

		$metadata = [ ];

		// Pessoa jurídica
		if ( '2' === $this->order->billing_persontype ) {
			$name      = $this->order->billing_company;
			$cpfOrCnpj = $this->order->billing_cnpj;
			$notes     = 'Nome: ' . $this->order->billing_first_name . ' ' . $this->order->billing_last_name;
			if ( $this->gateway->sendNfeInformation ) {
				$metadata['inscricao_estadual'] = $this->order->billing_ie;
			}
		} // Pessoa física
		else {
			$name      = $this->order->billing_first_name . ' ' . $this->order->billing_last_name;
			$cpfOrCnpj = $this->order->billing_cpf;
			$notes     = '';
			if ( $this->gateway->sendNfeInformation ) {
				$metadata['carteira_de_identidade'] = $this->order->billing_rg;
			}
		}

		$customer = [
			'name'          => $name,
			'email'         => $email,
			'registry_code' => $cpfOrCnpj,
			'code'          => $userCode,
			'address'       => $address,
			'notes'         => $notes,
			'metadata'      => $metadata,
		];

		$customerId = $this->gateway->api->findOrCreateCustomer( $customer );

		if ( false === $customerId ) {
			$this->abort( __( 'Falha ao registrar o usuário. Verifique os dados e tente novamente.', 'woocommerce-vindi' ), true );
		}

		$this->gateway->log( 'Cliente Vindi: ' . $customerId );

		if ( $this->isCc() ) {
			$this->createPaymentProfile( $customerId );
		}

		return $customerId;
	}

	/**
	 * Build payment type for credit card.
	 *
	 * @param int $customerId
	 *
	 * @return array
	 */
	public function getCcPaymentType( $customerId ) {
		return [
			'customer_id'     => $customerId,
			'holder_name'     => $_POST['vindi_cc_fullname'],
			'card_expiration' => $_POST['vindi_cc_monthexpiry'] . '/' . $_POST['vindi_cc_yearexpiry'],
			'card_number'     => $_POST['vindi_cc_number'],
			'card_cvv'        => $_POST['vindi_cc_cvc'],
		];
	}

	/**
	 * Check if payment is of type "Credit Card"
	 * @return bool
	 */
	public function isCc() {
		return 'cc' === $this->gateway->type();
	}

	/**
	 * Check if payment is of type "Invoice"
	 * @return bool
	 */
	public function isInvoice() {
		return 'invoice' === $this->gateway->type();
	}

	/**
	 * @return string
	 */
	public function paymentMethodCode() {
		// TODO fix it to proper method code
		return $this->isCc() ? 'credit_card' : 'bank_slip';
	}

	/**
	 * @param string $message
	 * @param bool   $throwException
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function abort( $message, $throwException = false ) {
		$this->gateway->log( $message );

		$this->order->add_order_note( $message );
		wc_add_notice( $message, 'error' );

		if ( $throwException ) {
			throw new Exception( $message );
		}

		return false;
	}

	/**
	 * @return array|void
	 * @throws Exception
	 */
	public function process() {
		switch ( $orderType = $this->validateOrder() ) {
			case static::ORDER_TYPE_SINGLE:
				return $this->processSinglePayment();
			case static::ORDER_TYPE_SUBSCRIPTION:
				return $this->processSubscription();
			case static::ORDER_TYPE_INVALID:
			default:
				return $this->abort( __( 'Falha ao processar carrinho de compras. Verifique os itens escolhidos e tente novamente.', 'woocommerce-vindi' ), true );
		}
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function processSubscription() {
		$customerId = $this->getCustomer();

		$subscription = $this->createSubscription( $customerId );

		add_post_meta( $this->order->id, 'vindi_wc_subscription_id', $subscription['id'] );
		add_post_meta( $this->order->id, 'vindi_wc_bill_id', $subscription['bill']['id'] );
		$this->addDownloadUrlMetaForSubscription( $subscription );

		return $this->finishPayment();
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function processSinglePayment() {
		$customerId = $this->getCustomer();

		$billId = $this->createBill( $customerId );

		add_post_meta( $this->order->id, 'vindi_wc_bill_id', $billId );
		$this->addDownloadUrlMetaForSinglePayment( $billId );

		return $this->finishPayment();
	}

	/**
	 * @param int $customerId
	 *
	 * @throws Exception
	 */
	protected function createPaymentProfile( $customerId ) {
		$ccInfo = $this->getCcPaymentType( $customerId );

		$paymentProfileId = $this->gateway->api->createCustomerPaymentProfile( $ccInfo );

		if ( false === $paymentProfileId ) {
			$this->abort( __( 'Falha ao registrar o método de pagamento. Verifique os dados e tente novamente.', 'woocommerce-vindi' ), true );
		}
	}

	/**
	 * @param int   $vindiPlan
	 * @param float $orderTotal
	 *
	 * @return array|bool
	 * @throws Exception
	 */
	protected function getProductItems( $vindiPlan, $orderTotal ) {
		$productItems = $this->gateway->api->buildPlanItemsForSubscription( $vindiPlan, $orderTotal );

		if ( empty( $productItems ) ) {
			return $this->abort( __( 'Falha ao recuperar informações sobre o produto na Vindi. Verifique os dados e tente novamente.', 'woocommerce-vindi' ), true );
		}

		return $productItems;
	}

	/**
	 * @param $customerId
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function createSubscription( $customerId ) {
		$vindiPlan    = $this->getPlan();
		$productItems = $this->getProductItems( $vindiPlan, $this->order->get_total() );

		$body = [
			'customer_id'         => $customerId,
			'payment_method_code' => $this->paymentMethodCode(),
			'plan_id'             => $vindiPlan,
			'product_items'       => $productItems,
		];

		$subscription = $this->gateway->api->createSubscription( $body );

		if ( ! isset( $subscription['id'] ) || empty( $subscription['id'] ) ) {
			$this->gateway->log( sprintf( 'Erro no pagamento do pedido %s.', $this->order->id ) );

			$message = sprintf( __( 'Pagamento Falhou. (%s)', 'woocommerce-vindi' ), $this->gateway->api->lastError );
			$this->order->update_status( 'failed', $message );

			throw new Exception( $message );
		}

		return $subscription;
	}

	/**
	 * @param int $customerId
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function createBill( $customerId ) {
		$uniquePaymentProduct = $this->gateway->api->findOrCreateUniquePaymentProduct();

		$this->gateway->log( 'Produto para pagamento único: ' . $uniquePaymentProduct );

		$body = [
			'customer_id'         => $customerId,
			'payment_method_code' => $this->paymentMethodCode(),
			'bill_items'          => [
				[
					'product_id' => $uniquePaymentProduct,
					'amount'     => $this->order->get_total(),
				],
			],
		];

		if ( 'credit_card' === $this->paymentMethodCode() && isset( $_POST['vindi_cc_installments'] ) ) {
			$body['installments'] = (int) $_POST['vindi_cc_installments'];
		}

		$billId = $this->gateway->api->createBill( $body );

		if ( ! $billId ) {
			$this->gateway->log( sprintf( 'Erro no pagamento do pedido %s.', $this->order->id ) );

			$message = sprintf( __( 'Pagamento Falhou. (%s)', 'woocommerce-vindi' ), $this->gateway->api->lastError );
			$this->order->update_status( 'failed', $message );

			throw new Exception( $message );
		}

		return $billId;
	}

	/**
	 * @param $subscription
	 */
	protected function addDownloadUrlMetaForSubscription( $subscription ) {
		if ( isset( $subscription['bill'] ) ) {
			$bill        = $subscription['bill'];
			$downloadUrl = false;

			if ( 'review' === $bill['status'] ) {
				$this->gateway->api->approveBill( $bill['id'] );
				$downloadUrl = $this->gateway->api->getBankSlipDownload( $bill['id'] );
			} else if ( isset( $bill['charges'] ) && count( $bill['charges'] ) ) {
				$downloadUrl = $bill['charges'][0]['print_url'];
			}

			if ( $downloadUrl ) {
				add_post_meta( $this->order->id, 'vindi_wc_invoice_download_url', $downloadUrl );
			}
		}
	}

	/**
	 * @param int $billId
	 */
	protected function addDownloadUrlMetaForSinglePayment( $billId ) {
		if ( $this->gateway->api->approveBill( $billId ) ) {
			$downloadUrl = $this->gateway->api->getBankSlipDownload( $billId );

			if ( $downloadUrl ) {
				add_post_meta( $this->order->id, 'vindi_wc_invoice_download_url', $downloadUrl );
			}
		}
	}

	/**
	 * @return array
	 */
	protected function finishPayment() {
		global $woocommerce;
		$woocommerce->cart->empty_cart();

		if ( $this->isCc() ) {
			$this->gateway->log( sprintf( 'Aguardando confirmação de recebimento do pedido %s pela Vindi.', $this->order->id ) );
			$this->order->update_status( 'pending', __( 'Aguardando confirmação de recebimento do pedido pela Vindi.', 'woocommerce-vindi' ) );
		} else {
			$this->gateway->log( sprintf( 'Aguardando pagamento do boleto do pedido %s.', $this->order->id ) );
			$this->order->update_status( 'pending', __( 'Aguardando pagamento do boleto do pedido', 'woocommerce-vindi' ) );
		}

		return [
			'result'   => 'success',
			'redirect' => $this->order->get_checkout_order_received_url(),
		];
	}
}