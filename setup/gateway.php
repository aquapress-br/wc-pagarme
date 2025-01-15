<?php defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_pagarme_gateways_register' ) ) {

	/**
	 * Registers Pagar.me payment methods for WooCommerce.
	 *
	 * This function adds custom Pagar.me payment gateways (like Credit Card) to the list of available
	 * WooCommerce payment methods. It uses the `apply_filters` function to extend the
	 * 'woocommerce_payment_gateways' filter with additional payment methods.
	 *
	 * @since    1.0.0
	 *
	 * @return   void
	 */
	function wc_pagarme_gateways_register() {
		// Adds Pagar.me's Credit Card gateway to the WooCommerce payment methods list.
		add_filter(
			'woocommerce_payment_gateways',
			function ( $payment_methods ) {
				$payment_methods[] = '\Aquapress\Pagarme\Gateways\CreditCard';
				$payment_methods[] = '\Aquapress\Pagarme\Gateways\PIX';
				$payment_methods[] = '\Aquapress\Pagarme\Gateways\Boleto';
				return $payment_methods;
			}
		);
	}

}


if ( ! function_exists( 'wc_pagarme_marketplaces_register' ) ) {
	/**
	 * Registers and initializes marketplace connectors for WooCommerce Pagar.me integration.
	 *
	 * This function checks if the wc_pagarme_marketplaces_register function is not already defined.
	 * If it's not, it defines the function to register marketplace connectors.
	 *
	 * The function performs the following steps:
	 *
	 * @return void
	 */
	function wc_pagarme_marketplaces_register() {
		$embedded = array(
			'\Aquapress\Pagarme\Marketplaces\Dokan',
		);

		$load_connectors = array_unique(
			apply_filters(
				'wc_pagarme_marketplaces',
				$embedded
			)
		);

		foreach ( $load_connectors as $class_name ) {
			if ( ! apply_filters( 'wc_pagarme_marketplace_load', true, $class_name ) ) {
				continue;
			}

			if ( is_string( $class_name ) && class_exists( $class_name ) ) {
				$obj = new $class_name();

				if ( $obj->is_available() ) {
					$obj->init_connector();
				}
			}
		}
	}
}

if ( ! function_exists( 'wc_pagarme_webhooks_register' ) ) {
	/**
	 * Registers and initializes webhooks for WooCommerce Pagar.me integration.
	 *
	 * @return void
	 */
	function wc_pagarme_webhooks_register() {
		$embedded = array(
			'\Aquapress\Pagarme\Webhooks\Update_Orders',
		);

		$load_connectors = array_unique(
			apply_filters(
				'wc_pagarme_webhooks',
				$embedded
			)
		);

		foreach ( $load_connectors as $class_name ) {
			if ( ! apply_filters( 'wc_pagarme_webhook_load', true, $class_name ) ) {
				continue;
			}

			if ( is_string( $class_name ) && class_exists( $class_name ) ) {
				$obj = new $class_name();
				
				$obj->init_webhook();
			}
		}
	}
}

if ( ! function_exists( 'wc_pagarme_resources_register' ) ) {
	/**
	 * Registers and initializes resources for WooCommerce Pagar.me integration.
	 *
	 * @return void
	 */
	function wc_pagarme_resources_register() {
		$embedded = array(
			'\Aquapress\Pagarme\Resources\International_Payments',
		);

		$load_connectors = array_unique(
			apply_filters(
				'wc_pagarme_resources',
				$embedded
			)
		);

		foreach ( $load_connectors as $class_name ) {
			if ( ! apply_filters( 'wc_pagarme_resource_load', true, $class_name ) ) {
				continue;
			}

			if ( is_string( $class_name ) && class_exists( $class_name ) ) {
				$obj = new $class_name();

				if ( $obj->is_available() ) {
					$obj->init_hooks();
				}
			}
		}
	}
}

if ( ! function_exists( 'wc_pagarme_migrations_register' ) ) {

	/**
	 * Register migrations
	 *
	 * @since    1.0.0
	 * @return   string
	 */
	function wc_pagarme_migrations_register() 
	{
		$migration_history = get_option( 'pagarme_migrations', [] );
		
		$embedded = array(
			'\Aquapress\Pagarme\Migrations\Legacy_Compatibility',
		);

		$load_migrations = array_unique ( 
			apply_filters( 
				'wc_pagarme_migrations', 
				$embedded 
			)
		);
		
		foreach ( $load_migrations as $class_name ) {
			$is_load = apply_filters( 'wc_pagarme_load_migration', true, $class_name );

			if ( ! $is_load ) {
				continue;
			}
			
			if ( is_string( $class_name ) && class_exists( $class_name ) ) {
				$migration = new $class_name();						
			} else {
				throw new \Exception( 
					sprintf( __( 'The %s is not a valid class name or the class was not found.', 'wc-pagarme' ), $class_name )
				);
			}
			
			if ( ! is_a( $migration, '\Aquapress\Pagarme\Abstracts\Migration' ) ) {
				throw new \Exception( 
					__( 'A classe \Aquapress\Pagarme\Abstracts\Migration não foi estendida para um ou mais elementos.', 'wc-pagarme' )
				);
			}
			
			// Initializes the migration process.
			if ( ! isset( $migration_history[ $migration->version ] ) ) {
				if ( $has_processed = $migration->process( DASHLIFTER_VERSION ) ) {
					$migration_history[ $migration->version ] = date( 'Y-m-d\TH:i:s\Z', time() );
					// Register the migration version and time.
					update_option( 'pagarme_migrations', $migration_history );
				} else {
					// Trigger a warning if the migration fails.
					trigger_error(
						sprintf(
							__( 'Dashlifter: Falha na migração para a versão %s. Prossiga com a instalação do plugin novamente. Se o problema persistir, entre em contato com o suporte.', 'wc-pagarme' ), 
							$migration->version
						), 
						E_USER_WARNING
					);
					
					break; // Stop next migrations
				}
			}
		}
	}

}

if ( ! function_exists( 'wc_pagarme_tasks_register' ) ) {

	/**
	 * Task register
	 *
	 * @since    1.0.0
	 * @return   string
	 */
	function wc_pagarme_tasks_register() {
		$embedded = array(
			'\Aquapress\Pagarme\Tasks\Update_Recipients',
		);

		$load_tasks = array_unique(
			apply_filters(
				'wc_pagarme_tasks',
				$embedded
			)
		);

		foreach ( $load_tasks as $class_name ) {
			$is_load = apply_filters( 'wc_pagarme_load_task', true, $class_name );

			if ( ! $is_load ) {
				continue;
			}
		
			if ( is_string( $class_name ) && class_exists( $class_name ) ) {
				$obj = new $class_name();
				if ( $obj->is_available() ) {
					if ( ! wp_next_scheduled( "pagarme_{$obj->id}" ) ) {
						wp_schedule_event( time() + $obj->interval, $obj->recurrence, "pagarme_{$obj->id}" );
					}
				}
			}

		}
	}

}

if ( ! function_exists( 'wc_pagarme_tasks_unregister' ) ) {

	/**
	 * Task unregister
	 *
	 * @since    1.0.0
	 * @return   string
	 */
	function wc_pagarme_tasks_unregister() {
		$embedded = array(
			'\Aquapress\Pagarme\Tasks\Update_Recipients',
		);

		$load_tasks = array_unique(
			apply_filters(
				'wc_pagarme_tasks',
				$embedded
			)
		);

		foreach ( $load_tasks as $class_name ) {
			$is_load = apply_filters( 'wc_pagarme_load_task', true, $class_name );

			if ( ! $is_load ) {
				continue;
			}

			if ( is_string( $class_name ) && class_exists( $class_name ) ) {
				$obj = new $class_name();
				wp_clear_scheduled_hook( "pagarme_{$obj->id}" );
			}
			
		}
	}

}
