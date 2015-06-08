<?php

class WC_Vindi_Webhooks {
	/**
	 * @var WC_Vindi_Base_Gateway
	 */
	private $gateway;

	/**
	 * Class Constructor.
	 *
	 * @param WC_Vindi_Base_Gateway $gateway
	 */
	public function __construct( WC_Vindi_Base_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Handle incoming webhook.
	 */
	public function handle() {
		try {
			$body = file_get_contents( 'php://input' );
			$this->gateway->log( sprintf( 'Novo Webhook chamado: %s', $body ) );
			$jsonBody = json_decode( $body, true );

			if ( ! $jsonBody || ! isset( $jsonBody['event'] ) ) {
				throw new Exception( 'Evento do Webhook não encontrado!' );
			}

			$type = $jsonBody['event']['type'];
			$data = $jsonBody['event']['data'];
		} catch ( Exception $e ) {
			$this->gateway->log( sprintf( 'Falha ao interpretar JSON do webhook: %s', $e->getMessage() ) );
			http_response_code( 400 );
			exit( '0' );
		}

		switch ( $type ) {
			// the webhook is being called before Order is actually placed.
			// I'm sorry for this, not going to use queues for now, so the solution is to use sleep().

			case 'test':
				$this->gateway->log( 'Evento de teste do webhook.' );
				exit();
			case 'subscription_created':
				sleep( 8 );

				return $this->subscriptionCreated( $data );
			case 'bill_paid':
				sleep( 10 );

				return $this->billPaid( $data );
			case 'charge_rejected':
				sleep( 10 );

				return $this->chargeRejected( $data );
			default:
				$this->gateway->log( sprintf( 'Evento do webhook ignorado pelo plugin: %s', $type ) );
				exit( '0' );
		}
	}

	/**
	 * Handle 'subscription_created' event.
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function subscriptionCreated( $data ) {
		$subscriptionId = (int) sanitize_text_field( $data['subscription']['id'] );

		$this->gateway->log( sprintf( 'Nova assinatura criada: %d', $subscriptionId ) );

		$query = $this->querySubscriptions( $subscriptionId );

		if ( ! $query->have_posts() ) {
			$this->gateway->log( sprintf( 'Nenhum pedido encontrado para a assinatura: %d', $subscriptionId ) );

			return false;
		}

		while ( $query->have_posts() ) {
			$query->the_post();
			$order = new WC_Order( $query->post->ID );

			$order->add_order_note( __( 'O pedido foi recebido com sucesso pela Vindi e está sendo processado.', 'woocommerce-vindi' ) );
			$this->gateway->log( sprintf( 'O pedido %d foi recebido com sucesso pela Vindi.', $order->id ) );
		}

		return true;
	}

	/**
	 * Handle 'bill_paid' event.
	 * The bill can be related to a subscription or a single payment.
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function billPaid( $data ) {
		if ( $data['bill']['subscription'] && $subscriptionId = (int) sanitize_text_field( $data['bill']['subscription']['id'] ) ) {
			$this->gateway->log( sprintf( 'Nova confirmação de pagamento da assinatura: %d', $subscriptionId ) );

			$query = $this->querySubscriptions( $subscriptionId );

			if ( ! $query->have_posts() ) {
				$this->gateway->log( sprintf( 'Nenhum pedido encontrado para a assinatura: %d', $subscriptionId ) );

				return false;
			}
		} else {
			$billId = (int) sanitize_text_field( $data['bill']['id'] );

			$this->gateway->log( sprintf( 'Nova confirmação de pagamento da compra simples: %d', $billId ) );

			$query = $this->queryBills( $billId );

			if ( ! $query->have_posts() ) {
				$this->gateway->log( sprintf( 'Nenhum pedido encontrado para o pagamento simples: %d', $billId ) );

				return false;
			}
		}
		while ( $query->have_posts() ) {
			$query->the_post();
			$order = new WC_Order( $query->post->ID );

			$status = $this->gateway->returnStatus;
			$order->update_status( $status, __( 'O Pagamento foi realizado com sucesso pela Vindi.', 'woocommerce-vindi' ) );
			$this->gateway->log( sprintf( 'O Pagamento do pedido %s foi realizado com sucesso pela Vindi.', $order->id ) );
		}

		return true;
	}

	/**
	 * Handle 'charge_rejected' event.
	 * The bill can be related to a subscription or a single payment.
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function chargeRejected( $data ) {
		$charge         = $data['charge'];
		$gatewayMessage = sanitize_text_field( $charge['last_transaction']['gateway_message'] );
		$billId         = (int) sanitize_text_field( $charge['bill']['id'] );
		$isLastAttempt  = is_null( $charge['next_attempt'] );

		$query = $this->queryBills( $billId );

		if ( ! $query->have_posts() ) {
			$this->gateway->log( sprintf( 'Nenhum pedido encontrado para a fatura: %d', $billId ) );

			return false;
		}

		while ( $query->have_posts() ) {
			$query->the_post();
			$order = new WC_Order( $query->post->ID );

			if ( $isLastAttempt ) {
				$order->update_status( 'failed', sprintf( __( 'Todas as tentativas de pagamento foram rejeitadas pela Vindi. Motivo: "%s".', 'woocommerce-vindi' ), $gatewayMessage ) );
				$this->gateway->log( sprintf( 'Todas as tentativas de pagamento do pedido %s foram rejeitadas pela Vindi. Motivo: "%s".', $order->id, $gatewayMessage ) );
			} else {
				$order->add_order_note( sprintf( __( 'Tentativa de Pagamento rejeitada pela Vindi. Motivo: "%s". Uma nova tentativa será feita.', 'woocommerce-vindi' ), $gatewayMessage ) );
				$this->gateway->log( sprintf( 'Tentativa de pagamento do pedido %s foi rejeitada pela Vindi. Motivo: "%s". Uma nova tentativa será feita.', $order->id, $gatewayMessage ) );
			}
		}

		return true;
	}

	/**
	 * Query orders containing subscriptionId meta
	 *
	 * @param int $subscriptionId
	 *
	 * @return WP_Query
	 */
	protected function querySubscriptions( $subscriptionId ) {
		$args = [
			'post_type'   => 'shop_order',
			'meta_key'    => 'vindi_wc_subscription_id',
			'meta_value'  => $subscriptionId,
			'post_status' => 'any',
		];

		return new WP_Query( $args );
	}

	/**
	 * Query orders containing billId meta
	 *
	 * @param int $billId
	 *
	 * @return WP_Query
	 */
	protected function queryBills( $billId ) {
		$args = [
			'post_type'   => 'shop_order',
			'meta_key'    => 'vindi_wc_bill_id',
			'meta_value'  => $billId,
			'post_status' => 'any',
		];

		return new WP_Query( $args );
	}
}