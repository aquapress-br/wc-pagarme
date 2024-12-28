<?php 

namespace Aquapress\Pagarme\Gateways;

/**
 * Process payment with credit card.
 *
 * @since 1.0.0
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * \Aquapress\Pagarme\Gateways\CreditCard class.
 *
 * @extends \Aquapress\Pagarme\Abstracts\Gateway.
 */
class CreditCard extends \Aquapress\Pagarme\Abstracts\Gateway {

	const CARD_ID                  = 'pagarme_card';
	const CARD_NUMBER              = 'pagarme_card_number';
	const CARD_NAME                = 'pagarme_card_holder_name';
	const CARD_EXPIRY              = 'pagarme_card_expiry';
	const CARD_CVC                 = 'pagarme_card_cvc';
	const CARD_INSTALLMENTS        = 'pagarme_card_installments';
	const CARD_SAVE_OPTION         = 'pagarme_card_save_option';
	const CARD_TOKEN               = 'pagarmetoken';
	const FILTER_CARD_INSTALLMENTS = 'wc_pagarme_card_installments';
	const FILTER_MAX_INSTALLMENTS  = 'wc_pagarme_max_installments';
	const FILTER_CARD_DATA         = 'wc_pagarme_card_data';

	/**
	 * Start payment method.
	 *
	 * @return   void
	 */
	public function __construct() {
		$this->id                   = 'wc_pagarme_creditcard';
		$this->method_title         = __( 'Pagar.me', 'wc-pagarme' );
		$this->method_description   = __(
			'Receba com Cartão de Crédito usando a Pagar.me.',
			'wc-pagarme'
		);
		$this->supports             = array(
			'tokenization',
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);
		$this->testmode             = $this->get_option( 'testmode' );
		$this->public_key           = $this->get_option( 'public_key' );
		$this->secret_key           = $this->get_option( 'secret_key' );
		$this->public_key_sandbox   = $this->get_option( 'public_key_sandbox' );
		$this->secret_key_sandbox   = $this->get_option( 'secret_key_sandbox' );
		$this->methods              = $this->get_option( 'methods' );
		$this->smallest_installment = $this->get_option( 'smallest_installment' );
		$this->interest_rate        = $this->get_option( 'interest_rate' );
		$this->installments         = $this->get_option( 'installments' );
		$this->interest             = $this->get_option( 'interest' );
		$this->installment_type     = $this->get_option( 'installment_type' );
		$this->statement_descriptor = $this->get_option( 'statement_descriptor' );
		$this->operation_type       = $this->get_option( 'operation_type' );
		$this->tokenize_card        = $this->get_option( 'tokenize_card' );
		$this->debug                = $this->get_option( 'debug' ) === 'yes';
		$this->icon                 = null;

		// Enable custom form fields for this gateway.
		$this->has_fields = true;

		// Initialize the form fields and gateway settings.
		$this->init_form_fields();
		$this->init_settings();
		
		parent::init_gateway();
	}

	/**
	 * Initializes hooks for the payment gateway.
	 *
	 * This method registers the necessary WordPress and WooCommerce hooks
	 * required for the payment gateway's functionality. These hooks may include
	 * actions and filters that allow the gateway to integrate with the checkout process,
	 * handle payment transactions, and respond to various events during the payment lifecycle.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'public_enqueue' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Defines and initializes the form fields for the payment gateway's admin settings.
	 *
	 * This method sets up the form fields that appear on the plugin's admin settings page.
	 * These fields allow administrators to configure various options for the payment gateway,
	 * such as API keys, titles, descriptions, and other settings specific to the gateway.
	 * The fields are used to capture and save configuration data in the WordPress admin area.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$fields = array(
			'enabled'              => array(
				'title'       => __( 'Ativar/Desativar', 'wc-pagarme' ),
				'label'       => __(
					'Marque para habilitar esta forma de pagamento.',
					'wc-pagarme'
				),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                => array(
				'title'       => __( 'Título do Checkout', 'wc-pagarme' ),
				'type'        => 'text',
				'description' => __(
					'Este campo controla o título que o usuário vê durante o checkout.',
					'wc-pagarme'
				),
				'default'     => 'Cartão de Crédito',
				'desc_tip'    => true,
			),
			'description'          => array(
				'title'       => __( 'Descrição do Checkout', 'wc-pagarme' ),
				'type'        => 'textarea',
				'description' => __(
					'Este campo controla a descrição que o usuário vê durante o checkout.',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'default'     => __(
					'Insira os detalhes do cartão de crédito',
					'wc-pagarme'
				),
			),
			'environment'          => array(
				'title'       => __( 'Configurações de Integração', 'wc-pagarme' ),
				'type'        => 'title',
				'description' => __(
					'Selecione o ambiente ativo para a API',
					'wc-pagarme'
				),
			),
			'testmode'             => array(
				'title'       => __( 'Ambiente de Sandbox', 'wc-pagarme' ),
				'type'        => 'checkbox',
				'label'       => __( 'Habilitar o Teste da Pagar.me', 'wc-pagarme' ),
				'description' => __(
					'O Sandbox da Pagar.me pode ser utilizado para testar os pagamentos',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'default'     => 'no',
			),
			//'public_key'           => array(
				//'title'       => __( 'Chave Pública', 'wc-pagarme' ),
				//'type'        => 'text',
				//'description' => __( 'Chave Pública da Pagar.me', 'wc-pagarme' ),
				//'desc_tip'    => true,
			//),
			'secret_key'           => array(
				'title'       => __( 'Chave Secreta', 'wc-pagarme' ),
				'type'        => 'text',
				'description' => __( 'Chave Secreta da Pagar.me', 'wc-pagarme' ),
				'desc_tip'    => true,
			),
			//'public_key_sandbox'   => array(
				//'title'       => __( 'Chave Pública do Sandbox', 'wc-pagarme' ),
				//'type'        => 'text',
				//'description' => __(
					//'Chave Pública da Pagar.me para Sandbox',
					//'wc-pagarme'
				//),
				//'desc_tip'    => true,
			//),
			'secret_key_sandbox'   => array(
				'title'       => __( 'Chave Secreta de Testes', 'wc-pagarme' ),
				'type'        => 'text',
				'description' => __(
					'Chave Secreta da Pagar.me para Testes',
					'wc-pagarme'
				),
				'desc_tip'    => true,
			),
			'payment_settings'     => array(
				'title'       => __( 'Configurações de Pagamento', 'wc-pagarme' ),
				'type'        => 'title',
				'description' => __(
					'Personalize as opções de pagamento',
					'wc-pagarme'
				),
			),
			'statement_descriptor' => array(
				'title'             => __(
					'Texto no Extrato do Cartão de Crédito',
					'wc-pagarme'
				),
				'type'              => 'text',
				'description'       => __(
					'Texto a ser exibido no extrato do cartão de crédito',
					'wc-pagarme'
				),
				'desc_tip'          => true,
				'default'           => __( 'Compra online', 'wc-pagarme' ),
				'custom_attributes' => array( 'maxlength' => '13' ),
			),
			'smallest_installment' => array(
				'title'       => __( 'Menor Parcela', 'wc-pagarme' ),
				'type'        => 'text',
				'description' => __(
					'Valor mínimo de cada parcela, não pode ser inferior a 1.',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'default'     => '1',
			),
			'installments'         => array(
				'title'       => __( 'Parcelamento', 'wc-pagarme' ),
				'type'        => 'select',
				'description' => __(
					'Número máximo de parcelas para pedidos na sua loja.',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => '1',
				'options'     => array(
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
				),
			),
			'methods'              => array(
				'title'       => __( 'Bandeiras Aceitas', 'wc-pagarme' ),
				'type'        => 'multiselect',
				'description' => __(
					'Selecione as bandeiras de cartão que serão aceitas como pagamento. Pressione a tecla Ctrl para selecionar mais de uma bandeira.',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => array(
					'visa',
					'mastercard',
					'diners',
					'discover',
					'elo',
					'amex',
					'jcb',
					'aura',
				),
				'options'     => array(
					'visa'       => __( 'Visa', 'wc-pagarme' ),
					'mastercard' => __( 'MasterCard', 'wc-pagarme' ),
					'diners'     => __( 'Diners', 'wc-pagarme' ),
					'discover'   => __( 'Discover', 'wc-pagarme' ),
					'elo'        => __( 'Elo', 'wc-pagarme' ),
					'amex'       => __( 'American Express', 'wc-pagarme' ),
					'jcb'        => __( 'JCB', 'wc-pagarme' ),
					'aura'       => __( 'Aura', 'wc-pagarme' ),
				),
			),
			'installment_type'     => array(
				'title'       => __( 'Tipo de Parcelamento', 'wc-pagarme' ),
				'type'        => 'select',
				'description' => __(
					'O cliente adiciona juros às parcelas no valor total do pedido.',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => 'store',
				'options'     => array(
					'client' => __( 'Cliente', 'wc-pagarme' ),
					'store'  => __( 'Loja', 'wc-pagarme' ),
				),
			),
			'interest_rate'        => array(
				'title'       => __( 'Taxa de Juros (%)', 'wc-pagarme' ),
				'type'        => 'text',
				'description' => __(
					'Percentual de juros que será cobrado do cliente na parcela em que houver aplicação de juros.',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'default'     => '2',
			),
			'interest'             => array(
				'title'       => __( 'Cobrar Juros A Partir De', 'wc-pagarme' ),
				'type'        => 'select',
				'description' => __(
					'Indique a partir de qual parcela será cobrado juros.',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => '6',
				'options'     => array(
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
				),
			),
			'operation_type'       => array(
				'title'       => __( 'Tipo de Captura', 'wc-pagarme' ),
				'type'        => 'select',
				'description' => __(
					'Indique o tipo de captura de pagamento.',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => 'auth_and_capture',
				'options'     => array(
					'auth_and_capture' => __(
						'Autorização e Captura',
						'wc-pagarme'
					),
					'pre_auth'         => __( 'Pré-autorização', 'wc-pagarme' ),
				),
			),
			'tokenize_card' => array(
				'title'       => __( 'Coletar Dados do Cartão', 'wc-pagarme' ),
				'type'        => 'select',
				'description' => __(
					'Controla como os dados do cartão devem ser manuseados.',
					'wc-pagarme'
				),
				'desc_tip'    => true,
				'class'       => 'wc-enhanced-select',
				'default'     => 'ask_before_saving',
				'options'     => array(
					'ask_before_saving'   => __(
						'Perguntar Antes de Salvar',
						'wc-pagarme'
					),
					'save_without_asking' => __(
						'Salvar Sem Perguntar',
						'wc-pagarme'
					),
					'never_save'          => __( 'Nunca Salvar', 'wc-pagarme' ),
				),
			),
		);

		$this->form_fields = $fields;
	}

	/**
	 * Processes the payment for the specified order.
	 *
	 * This method handles the payment process after a payment request has been sent
	 * during checkout. It uses the provided `$order_id` to retrieve order details and
	 * complete the payment transaction. The method typically involves interacting with
	 * the payment gateway to process the payment and may return an array containing the
	 * result of the payment operation, such as the payment status and any relevant messages.
	 *
	 * @param string $order_id The ID of the order being processed.
	 *
	 * @return array An array containing the result of the payment processing, which may include
	 *               payment status, redirect URLs, or error messages.
	 */
	public function process_payment( $order_id ) {
		try {
			// Process transaction request.
			$transaction = $this->api->do_transaction(
				apply_filters(
					'wc_pagarme_transaction_data',
					Aquapress\Pagarme\Helpers\Build_Transaction_Payload( $order_id ),
					$order_id,
					$this
				)
			);
			// Process order status and save response info.
			$this->save_order_meta_fields( $order_id, $transaction );
			$this->process_order_status( $order_id, $transaction );
			// Go to order received page.
			return array( 'result' => 'success' );
		} catch ( Exception $e ) {
			// Output checkout error message.
			wc_pagarme_add_checkout_notice(
				__( 'Não conseguimos processar o pagamento com o cartão fornecido. Verifique as informações fornecidas e tente novamente. Se o problema persistir, entre em contato com o banco emissor para obter mais informações.', 'wc-pagarme' ),
				'error'
			);
		}
		// Go to checkout.
		return array( 'result' => 'fail' );
	}

	/**
	 * Retrieves the checkout form fields for the payment gateway.
	 *
	 * This method generates and returns the necessary form fields that will be displayed
	 * during the checkout process. These fields are used to capture additional information
	 * required for processing payments. The `$order_total` parameter is used to customize
	 * the form fields based on the total amount of the current order.
	 *
	 * @param float $order_total The total amount of the order, used to customize the form fields.
	 *
	 * @return string HTML markup for the checkout form fields.
	 */
	public function get_checkout_form( $order_total ) {
		wc_get_template(
			'woocommerce/payment-form.php',
			array(
				'card_id'          => static::CARD_ID,
				'card_number'      => static::CARD_NUMBER,
				'card_name'        => static::CARD_NAME,
				'card_expiry'      => static::CARD_EXPIRY,
				'card_cvc'         => static::CARD_CVC,
				'card_save_option' => static::CARD_SAVE_OPTION,

				'tokenize_card'    => $this->tokenize_card,
				
				'installments'     => $this->get_installments_html( $order_total ),
				'saved_cards'      => $this->get_saved_payment_tokens(),
				
				'is_checkout'      => is_checkout(),
			),
			'woocommerce/pagarme/',
			WC_PAGARME_PATH . 'templates/'
		);
	}

	/**
	 * Get saved cards tokens.
	 *
	 * @return array
	 */
	public function get_saved_payment_tokens() {
		$saved_cards =  \WC_Payment_Tokens::get_customer_tokens( 
			get_current_user_id(), $this->id );
			
		return $saved_cards;
	}

	/**
	 * Get installments HTML.
	 *
	 * @param  float  $order_total Order total.
	 * @return string
	 */
	public function get_installments_html( $order_total = 0 ) {
		$html         = '';
		$installments = apply_filters(
			static::FILTER_MAX_INSTALLMENTS,
			$this->installments,
			$order_total
		);

		if ( '1' == $installments ) {
			return $html;
		}

		$html .=
			'<select id="pagarme-installments" name="' .
			static::CARD_INSTALLMENTS .
			'" style="font-size: 1.5em; padding: 4px; width: 100%;">';

		$interest_rate = static::normalize_interest_rate_value( $this->interest_rate ) / 100;

		for ( $i = 1; $i <= $installments; $i++ ) {
			$credit_total    = $order_total / $i;
			$credit_interest = sprintf(
				__( 'sem juros. Total: %s', 'wc-pagarme' ),
				sanitize_text_field( wc_price( $order_total ) )
			);
			$smallest_value  = $this->smallest_installment;

			if (
				'client' == $this->installment_type &&
				$i >= $this->interest &&
				0 < $interest_rate
			) {
				$interest_total       =
					$order_total *
					( $interest_rate / ( 1 - 1 / pow( 1 + $interest_rate, $i ) ) );
				$interest_order_total = $interest_total * $i;

				if ( $credit_total < $interest_total ) {
					$credit_total    = $interest_total;
					$credit_interest = sprintf(
						__(
							'com juros de %1$s%% a.m. Total: %2$s',
							'wc-pagarme'
						),
						static::normalize_interest_rate_value( $this->interest_rate ),
						sanitize_text_field( wc_price( $interest_order_total ) )
					);
				}
			}

			if ( 1 != $i && $credit_total < $smallest_value ) {
				continue;
			}

			$at_sight = 1 == $i ? 'pagarme-at-sight' : '';

			$html .=
				'<option value="' .
				$i .
				'" class="' .
				$at_sight .
				'">' .
				sprintf(
					__( '%1$sx de %2$s %3$s', 'wc-pagarme' ),
					$i,
					sanitize_text_field( wc_price( $credit_total ) ),
					$credit_interest
				) .
				'</option>';
		}

		$html .= '</select>';

		return $html;
	}

	/**
	 * Validate installments.
	 *
	 * @param  array $posted
	 * @param  float $order_total
	 * @return bool
	 */
	protected function validate_installments( $posted, $order_total ) {
		// Stop if don't have installments.
		if ( ! isset( $posted[ static::CARD_INSTALLMENTS ] ) ) {
			return true;
		}

		try {
			// Validate the installments field.
			if (
				! isset( $posted[ static::CARD_INSTALLMENTS ] ) ||
				'' === $posted[ static::CARD_INSTALLMENTS ]
			) {
				throw new Exception(
					__( 'Selecione um número de parcelas.', 'wc-pagarme' )
				);
			}

			$installments      = absint( $posted[ static::CARD_INSTALLMENTS ] );
			$installment_total = $order_total / $installments;
			$_installments     = apply_filters(
				static::FILTER_MAX_INSTALLMENTS,
				$this->installments,
				$order_total
			);
			$interest_rate     = static::normalize_interest_rate_value( $this->interest_rate ) / 100;

			if (
				'client' == $this->installment_type &&
				$installments >= $this->interest &&
				0 < $interest_rate
			) {
				$interest_total    =
					$order_total *
					( $interest_rate /
						( 1 - 1 / pow( 1 + $interest_rate, $installments ) ) );
				$installment_total =
					$installment_total < $interest_total
						? $interest_total
						: $installment_total;
			}
			$smallest_value = $this->smallest_installment;

			if (
				$installments > $_installments ||
				( 1 != $installments && $installment_total < $smallest_value )
			) {
				throw new Exception(
					__( 'Número inválido de parcelas!', 'wc-pagarme' )
				);
			}
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );

			return false;
		}

		return true;
	}

	/**
	 * Payment fields.
	 *
	 * @return string
	 */
	public function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}

		// Get order total.
		if ( method_exists( $this, 'get_order_total' ) ) {
			$order_total = $this->get_order_total();
		} else {
			$order_total = $this->get_order_total();
		}

		$this->get_checkout_form( $order_total );
	}

	/**
	 * CreditCard data.
	 *
	 * @return array
	 */
	public function get_creditcard_data( $posted, $order = false ) {
		$card_id    = isset( $posted[ static::CARD_ID ] )
			? sanitize_text_field( $posted[ static::CARD_ID ] )
			: '';
		$card_token = isset( $posted[ static::CARD_TOKEN ] )
			? sanitize_text_field( $posted[ static::CARD_TOKEN ] )
			: '';

		$card_holder = isset( $posted[ static::CARD_NAME ] )
			? sanitize_text_field( $posted[ static::CARD_NAME ] )
			: '';
		$card_cvv    = isset( $posted[ static::CARD_CVC ] )
			? sanitize_text_field( $posted[ static::CARD_CVC ] )
			: '';

		$card_number = isset( $posted[ static::CARD_NUMBER ] )
			? WC_Pagarme_Helper::only_numbers( $posted[ static::CARD_NUMBER ] )
			: '';
		$card_brand  = $this->get_card_brand( $card_number );

		$card_save_option = isset( $posted[ static::CARD_SAVE_OPTION ] );
		$card_expiration = preg_replace(
			'/\s/',
			'',
			$posted[ static::CARD_EXPIRY ]
		);

		$installments = apply_filters(
			static::FILTER_CARD_INSTALLMENTS,
			absint( $posted[ static::CARD_INSTALLMENTS ] ?? 1 )
		);

		list(
			$interest_order_total,
			$interest_value,
			$interest_rate,
		) = $this->get_credit_interest_data( $installments, $order->order_total );

		$data = array(
			'type'                => 'credit-card',

			'card_save_option'    => $card_save_option,
			'card_id'             => $card_id,
			'card_token'          => $card_token,

			'card_number'         => $card_number,
			'card_brand'          => $card_brand,
			'card_holder'         => $card_holder,
			'card_expiration'     => $card_expiration,
			'card_cvv'            => $card_cvv,

			'card_installments'   => $installments,
			'card_interest_rate'  => $interest_rate,
			'card_interest_value' => round( $interest_value, 2 ),
			'card_order_total'    => round( $interest_order_total, 2 ),
		);

		if ( $order ) {
			if ( ! $this->validate_installments( $posted, $order->order_total ) ) {
				throw new Exception(
					__(
						'Número ou valor das parcelas inválido',
						'wc-pagarme'
					)
				);
			}

			update_post_meta(
				$order->id,
				'_wc_pagarme_payment_data',
				array_merge(
					$data,
					array(
						'card_number' => preg_replace(
							'/\d(?=\d{4})/',
							'*',
							$data['card_number']
						),
						'card_cvv'    => '***',
					)
				)
			);
		}

		return apply_filters( static::FILTER_CARD_DATA, $data );
	}

	/**
	 * Return interest data.
	 *
	 * @return array
	 */
	public function get_credit_interest_data( $installments, $order_total ) {
		$order_total          = (float) $order_total;
		$interest_order_total = $order_total;
		$real_interest_total  = 0;
		$interest_rate        = 0;

		if (
			isset( $this->installment_type ) &&
			'client' == $this->installment_type &&
			$installments >= $this->interest
		) {
			$interest_rate        = static::normalize_interest_rate_value( $this->interest_rate ) / 100;
			$interest_total       = number_format(
				$order_total *
					( $interest_rate /
						( 1 - 1 / pow( 1 + $interest_rate, $installments ) ) ),
				2
			);
			$interest_order_total = $interest_total * $installments;
			$interest_order_calc  = $interest_order_total - $order_total; // fix interest total for 1 installments
			$real_interest_total  =
				$interest_order_calc < 0 ? 0 : $interest_order_calc;
		}

		return array(
			$interest_order_total,
			$real_interest_total,
			$interest_rate * 100,
		);
	}

	/**
	 * Get cardband by number
	 *
	 * @param  string $number The card number.
	 */
	public function get_card_brand( $number ) {
		$number = preg_replace( '([^0-9])', '', $number );
		$brand  = '';

		$supported_brands = array(
			'visa'       => '/^4\d{12}(\d{3})?$/',
			'mastercard' => '/^(5[1-5]\d{4}|677189)\d{10}$/',
			'diners'     => '/^3(0[0-5]|[68]\d)\d{11}$/',
			'discover'   => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
			'elo'        =>
				'/^((((636368)|(438935)|(504175)|(451416)|(636297))\d{0,10})|((5067)|(4576)|(4011))\d{0,12})$/',
			'amex'       => '/^3[47]\d{13}$/',
			'jcb'        => '/^(?:2131|1800|35\d{3})\d{11}$/',
			'aura'       => '/^(5078\d{2})(\d{2})(\d{11})$/',
			'hipercard'  => '/^(606282\d{10}(\d{3})?)|(3841\d{15})$/',
		);

		foreach ( $supported_brands as $key => $value ) {
			if ( preg_match( $value, $number ) ) {
				$brand = $key;
				break;
			}
		}

		return $brand;
	}

	/**
	 * Validate the card form
	 *
	 * @return void
	 */
	public function validate_card_fields() {
		if ( ! isset( $_POST[ static::CARD_ID ] ) || empty( $_POST[ static::CARD_ID ] ) ) {
			if (
				empty( $_POST[ static::CARD_NUMBER ] ) ||
				! isset( $_POST[ static::CARD_NUMBER ] )
			) {
				throw new Exception(
					__(
						'Erro de validação de dados. Insira o número do cartão.',
						'wc-pagarme'
					)
				);
			}

			if (
				empty( $_POST[ static::CARD_NAME ] ) ||
				! isset( $_POST[ static::CARD_NAME ] )
			) {
				throw new Exception(
					__(
						'Erro de validação de dados. Insira o nome do cartão.',
						'wc-pagarme'
					)
				);
			}

			if (
				empty( $_POST[ static::CARD_EXPIRY ] ) ||
				! isset( $_POST[ static::CARD_EXPIRY ] )
			) {
				throw new Exception(
					__(
						'Erro de validação de dados. Insira a data de validade do cartão.',
						'wc-pagarme'
					)
				);
			}

			if ( strlen( $_POST[ static::CARD_EXPIRY ] ) < 5 ) {
				throw new Exception(
					__(
						'Erro de validação de dados. A validade do cartão deve corresponder ao formato MM-AAAA. Ex: 12-2034.',
						'wc-pagarme'
					)
				);
			}

			if (
				empty( $_POST[ static::CARD_CVC ] ) ||
				! isset( $_POST[ static::CARD_EXPIRY ] )
			) {
				throw new Exception(
					__(
						'Erro de validação de dados. Insira o CVC do cartão.',
						'wc-pagarme'
					)
				);
			}
		}
	}
	
	/**
	 * Normalize interest rate value.
	 * Ensures the value is properly formatted.
	 *
	 * @param  string|int|float $value
	 * @return int|float
	 */
	public static function normalize_interest_rate_value( $value ) 
	{
		$value = str_replace( '%', '', $value );
		$value = str_replace( ',', '.', $value );

		return $value;
	}
	
	/**
	 * Get card brand name.
	 *
	 * @param string $brand Card brand.
	 * @return string
	 */
	public static function get_card_brand_name( $brand ) {
		$names = array(
			'visa'       => __( 'Visa', 'wc-pagarme' ),
			'mastercard' => __( 'MasterCard', 'wc-pagarme' ),
			'amex'       => __( 'American Express', 'wc-pagarme' ),
			'aura'       => __( 'Aura', 'wc-pagarme' ),
			'jcb'        => __( 'JCB', 'wc-pagarme' ),
			'diners'     => __( 'Diners', 'wc-pagarme' ),
			'elo'        => __( 'Elo', 'wc-pagarme' ),
			'hipercard'  => __( 'Hipercard', 'wc-pagarme' ),
			'discover'   => __( 'Discover', 'wc-pagarme' ),
		);

		return isset( $names[ $brand ] ) ? $names[ $brand ] : $brand;
	}
	
	/**
	 * Register admin_enqueue styles and scripts for payment method
	 *
	 * @since    1.0.0
	 * @return   array    void
	 */
	public function admin_enqueue() {
		wp_enqueue_script(
			'wc-pagarme-card-settings',
			WC_PAGARME_URI . 'assets/js/admin/card-settings.js',
			array( 'jquery' ),
			WC_PAGARME_VERSION,
			true
		);
	}

	/**
	 * Register public styles and scripts for payment method
	 *
	 * @since    1.0.0
	 * @return   array    void
	 */
	public function public_enqueue() {
		wp_enqueue_script( 'wc-credit-card-form' );
		wp_enqueue_script(
			'wc-pagarme-card-fields',
			WC_PAGARME_URI . 'assets/js/checkout/card-fields.js',
			array( 'jquery', 'jquery-blockui' ),
			WC_PAGARME_VERSION,
			true
		);
		wp_enqueue_script(
			'wc-pagarme-card-form',
			WC_PAGARME_URI . 'assets/js/checkout/card-form.js',
			array( 'jquery', 'jquery-blockui' ),
			WC_PAGARME_VERSION,
			true
		);
		wp_enqueue_script(
			'jquery-mask',
			WC_PAGARME_URI . 'assets/js/jquery.mask.js',
			array( 'jquery' ),
			'1.14.10',
			true
		);
		wp_enqueue_style(
			'wc-pagarme-card-form',
			WC_PAGARME_URI . 'assets/public/css/card-form.css'
		);
	}

	/**
	 * Validates the checkout form fields.
	 *
	 * @throws Exception If the taxpayer identification number is empty for non-Brazilian customers.
	 *
	 * @return bool  True if the validation passes, otherwise throws an exception.
	 */
	public function validate_fields() {
		try {
			$this->validate_card_fields();
			} catch ( Exception $e ) {
			wc_pagarme_add_checkout_notice( 
				$e->getMessage(), 
				'error' 
			);
		}
	}
}
