<?php
/**
 * Plugin Name: Vindi Woocommerce Assinaturas
 * Plugin URI: https://wordpress.org/plugins/vindi-assinaturas-e-cobranca-recorrente/
 * Description: Adiciona o gateway de pagamentos da Vindi para o WooCommerce.
 * Version: 2.3.0
 * Author: Vindi
 * Author URI: https://www.vindi.com.br
 * Requires at least: 4.0
 * Tested up to: 4.2
 *
 * Text Domain: woocommerce-vindi
 * Domain Path: /languages/
 *
 * Copyright: © 2014-2015 Vindi Tecnologia e Marketing LTDA
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Vindi' ) ) :

	/**
	 * WooCommerce Vindi main class.
	 */
	class WC_Vindi {

		/**
		 * @var string
		 */
		public $version = '2.3.0';

		/**
		 * Instance of this class.
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * @var WC_Vindi_CreditCard_Gateway
		 */
		public $creditCardGateway;

		/**
		 * @var WC_Vindi_BankSlip_Gateway
		 */
		public $bankSlipGateway;

		/**
		 * Initialize the plugin public actions.
		 */
		private function __construct() {
			// Checks if WooCommerce is installed.
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				add_action( 'admin_notices', [ &$this, 'woocommerceMissingNotice' ] );

				return;
			}

			// Checks if WooCommerce Extra Checkout Fields for Brazil is installed.
			if ( ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
				add_action( 'admin_notices', [ &$this, 'extraCheckoutMissingNotice' ] );

				return;
			}

			$this->includes();

			add_filter( 'woocommerce_payment_gateways', [ &$this, 'addGateway' ] );
			add_action( 'woocommerce_add_to_cart_validation', [ &$this, 'validateAddToCart' ], 1, 3 );
			add_action( 'woocommerce_update_cart_validation', [ &$this, 'validateUpdateCart' ], 1, 4 );
			add_action( 'woocommerce_vindi-subscription_add_to_cart', [ &$this, 'addSubscriptionToCart' ], 30 );

			if ( is_admin() ) {
				$this->creditCardGateway = new WC_Vindi_CreditCard_Gateway();
				$this->bankSlipGateway   = new WC_Vindi_BankSlip_Gateway();

				add_action( 'woocommerce_product_options_general_product_data', [
					&$this,
					'vindiSubscriptionPricingFields',
				] );
				add_action( 'admin_init', [ &$this, 'dismissTrialNotice' ] );

				$this->checkForTrial();
			}

			define( 'WC_VINDI_VERSION', $this->version );
		}

		/**
		 * Return an instance of this class.
		 * @return WC_Vindi A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Included files.
		 * @return void
		 */
		private function includes() {
			include_once( 'classes/vindi-api.php' );
			include_once( 'classes/vindi-base-gateway.php' );
			include_once( 'classes/vindi-creditcard-gateway.php' );
			include_once( 'classes/vindi-bankslip-gateway.php' );
			include_once( 'classes/vindi-payment.php' );
			include_once( 'classes/vindi-webhooks.php' );
			include_once( 'classes/wc-product-vindi-subscription.php' );
		}

		/**
		 * Add the gateway to WooCommerce.
		 *
		 * @param   array $methods WooCommerce payment methods.
		 *
		 * @return  array Payment methods with Vindi.
		 */
		public function addGateway( $methods ) {
			$methods[] = 'WC_Vindi_CreditCard_Gateway';
			$methods[] = 'WC_Vindi_BankSlip_Gateway';

			return $methods;
		}

		/**
		 * WooCommerce fallback notice.
		 * @return  string
		 */
		public function woocommerceMissingNotice() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Vindi Gateway depende da última versão do %s para funcionar!', 'woocommerce-vindi' ), '<a href="https://wordpress.org/extend/plugins/woocommerce/">' . __( 'WooCommerce', 'woocommerce-vindi' ) . '</a>' ) . '</p></div>';
		}

		/**
		 * WooCommerce Extra Checkout Fields for Brazil fallback notice.
		 * @return  string
		 */
		public function extraCheckoutMissingNotice() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Vindi Gateway depende da última versão do %s para funcionar!', 'woocommerce-vindi' ), '<a href="https://wordpress.org/extend/plugins/woocommerce-extra-checkout-fields-for-brazil/">' . __( 'WooCommerce Extra Checkout Fields for Brazil', 'woocommerce-vindi' ) . '</a>' ) . '</p></div>';
		}

		/**
		 * @param bool $valid
		 * @param int  $productId
		 * @param int  $quantity
		 *
		 * @return bool
		 */
		public function validateAddToCart( $valid, $productId, $quantity ) {
			global $woocommerce;

			$cart_items = $woocommerce->cart->get_cart();

			$product = wc_get_product( $productId );

			if ( empty( $cart_items ) ) {
				if ( $product->is_type( 'vindi-subscription' ) ) {
					return 1 === $quantity;
				}

				return $valid;
			}

			foreach ( $cart_items as $item ) {
				if ( 'vindi-subscription' === $item['data']->product_type ) {
					if ( $product->is_type( 'vindi-subscription' ) ) {
						$woocommerce->cart->empty_cart();
						wc_add_notice( __( 'Uma outra assinatura foi removida do carrinho. Você pode fazer apenas uma assinatura a cada vez.', 'woocommerce-vindi' ), 'notice' );

						return $valid;
					}

					wc_add_notice( __( 'Você não pode ter produtos e assinaturas juntos na mesma compra. Conclua sua compra atual ou limpe o carrinho para adicionar este item.', 'woocommerce-vindi' ), 'error' );

					return false;
				} else if ( $product->is_type( 'vindi-subscription' ) ) {
					wc_add_notice( __( 'Você não pode ter produtos e assinaturas juntos na mesma compra. Conclua sua compra atual ou limpe o carrinho para adicionar este item.', 'woocommerce-vindi' ), 'error' );

					return false;
				}
			}

			return $valid;
		}

		/**
		 * @param bool $valid
		 * @param      $cartItemKey
		 * @param      $values
		 * @param int  $quantity
		 *
		 * @return bool
		 */
		public function validateUpdateCart( $valid, $cartItemKey, $values, $quantity ) {
			/** @var WooCommerce $woocommerce */
			global $woocommerce;

			$item    = $woocommerce->cart->get_cart_item( $cartItemKey );
			$product = $item['data'];

			if ( $product->is_type( 'vindi-subscription' ) && 1 !== $quantity && 0 !== $quantity ) {
				wc_add_notice( __( 'Você pode fazer apenas uma assinatura a cada vez.', 'woocommerce-vindi' ), 'error' );

				return false;
			}

			return $valid;
		}

		/**
		 * Add custom template to Vindi Subscription Product.
		 */
		public function addSubscriptionToCart() {
			wc_get_template( 'add-to-cart.php', [ ], '', plugin_dir_path( __FILE__ ) . 'classes/templates/' );
		}

		/**
		 * Show pricing fields at admin's product page.
		 */
		public function vindiSubscriptionPricingFields() {
			global $post;

			echo '<div class="options_group vindi-subscription_pricing show_if_vindi-subscription">';

			woocommerce_wp_text_input( [
					'id'                => 'vindi_subscription_price',
					'label'             => sprintf( __( 'Preço da Assinatura (%s)', 'woocommerce-vindi' ), get_woocommerce_currency_symbol() ),
					'placeholder'       => __( '0,00', 'woocommerce-subscriptions' ),
					'type'              => 'text',
					'custom_attributes' => [
						'step' => 'any',
						'min'  => '0',
					],
					'description'       => __( 'Você deve manter o preço do produto igual ao do plano, este processo <strong>não</strong> é automático.', 'woocommerce-vindi' ),
					'desc_tip'          => true,
				]
			);

			$plans         = [ __( '-- Selecione --', 'woocommerce-vindi' ) ] + $this->creditCardGateway->api->getPlans();
			$selected_plan = get_post_meta( $post->ID, 'vindi_subscription_plan', true );

			woocommerce_wp_select( [
					'id'          => 'vindi_subscription_plan',
					'label'       => __( 'Plano da Vindi', 'woocommerce-vindi' ),
					'options'     => $plans,
					'description' => __( 'Selecione o plano da Vindi que deseja relacionar a esse produto', 'woocommerce-vindi' ),
					'desc_tip'    => true,
					'value'       => $selected_plan,
				]
			);

			echo '</div>';
			echo '<div class="show_if_vindi-subscription clear"></div>';
		}

		/**
		 * Check to see if a Vindi Merchant status is trial.
		 * Only shows a warn if user hadn't dismissed the message.
		 */
		private function checkForTrial() {
			if ( get_user_meta( get_current_user_id(), 'vindi_dismissed_trial_notice', true ) || isset( $_GET['vindi-dismiss-trial-notice'] ) ) {
				return;
			}

			if ( $this->creditCardGateway->api->isMerchantStatusTrial() ) {
				add_action( 'admin_notices', [ &$this, 'warnTrialClients' ] );
			}
		}

		/**
		 * Warn Clients about Trial mode, where no charges are fulfilled.
		 */
		public function warnTrialClients() {
			echo '<div class="error notice is-dismissible">
				<h3>' . __( 'MODO DE TESTES', 'woocommerce-vindi' ) . '</h3>
				<p>' . __( 'Sua conta na Vindi está em <strong>Modo Trial</strong>. Este modo é proposto para a realização de testes e, portanto, nenhum pedido será efetivamente cobrado.', 'woocommerce-vindi' ) . '</p>
				<p>' . __( 'Quando desejar dar início às vendas, entre em contato com a Vindi.', 'woocommerce-vindi' ) . '</p>
				<p><a href="?vindi-dismiss-trial-notice"><strong>' . __( 'Dispensar esse aviso', 'woocommerce-vindi' ) . '</strong></a></p>
			</div>';
		}

		/**
		 * Check for a attempt to dismiss the trial notice.
		 */
		public function dismissTrialNotice() {
			if ( isset( $_GET['vindi-dismiss-trial-notice'] ) ) {
				update_user_meta( get_current_user_id(), 'vindi_dismissed_trial_notice', 1 );
			}
		}
	}

endif;

add_action( 'wp_loaded', [ 'WC_Vindi', 'get_instance' ], 0 );