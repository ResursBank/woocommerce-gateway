<?php

/**
 * Static Payment Flow: OmniCheckout
 *
 * Class WC_Resurs_Bank_Omni
 */
class WC_Gateway_ResursBank_Omni extends WC_Resurs_Bank {

	/**
	 * WC_Resurs_Bank_Omni constructor (simplified)
	 * Enabling is not controlled from this class.
	 */
	public function __construct() {
		$this->resetOmniCustomerFields = array();
		$this->id                      = "resurs_bank_omnicheckout";
		$this->method_title            = "Resurs Bank Omnicheckout";
		$this->description             = "Resurs Bank Omnicheckout";
		$this->has_fields              = true;
		$this->iFrameLocation          = $this->get_option( 'iFrameLocation' );
		if ( empty( $this->iFrameLocation ) ) {
			$this->iFrameLocation = "afterCheckoutForm";
		}
		$this->flow           = initializeResursFlow();
		$this->omniSuccessUrl = "";

		$icon_name    = "resurs-standard";
		$path_to_icon = $this->icon = apply_filters( 'woocommerce_resurs_bank_checkout_icon', $this->plugin_url() . '/img/' . $icon_name . '.png' );
		$temp_icon    = plugin_dir_path( __FILE__ ) . 'img/' . $icon_name . '.png';
		$has_icon     = (string) file_exists( $temp_icon );

		$this->has_icon();
		$this->init_form_fields();
		$this->init_settings();
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
		} else {
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		}

		/* OmniCheckout */
		add_filter( 'woocommerce_checkout_fields', array( $this, 'resurs_omnicheckout_fields' ) );

		if ( $this->isResursOmni() ) {
			if ( $this->iFrameLocation == "afterCheckoutForm" ) {
				add_action( 'woocommerce_after_checkout_form', array( $this, 'resurs_omnicheckout_form_location' ) );
			}
			if ( $this->iFrameLocation == "beforeReview" ) {
				add_action( 'woocommerce_checkout_before_order_review', array(
					$this,
					'resurs_omnicheckout_form_location'
				) );
			}
		}
	}

	public function resurs_omnicheckout_form_location() {
		global $resursIframeCount;
		if ( ! isset( $resursIframeCount ) ) {
			$resursIframeCount = 1;
		}
		$frameDisplay = "";
		// Prevent this iframe to load twice (cheat mode for some templates)
		if ( $resursIframeCount > 1 ) {
			return;
		}
		// Actions and info for Resurs Checkout that invokes on last-resorts (legacy)
		echo '<div id="omniActions" style="display: none;"></div>';
		echo '<div id="omniInfo"></div>';

		// Prepare the frame
		try {
			$frameDisplay .= '<div class="col2-set" id="resurs-checkout-container">' . $this->resurs_omnicheckout_create_frame() . "</div>";
		} catch ( Exception $e ) {
			$frameContent = __( 'We are unable to load Resurs Checkout for the moment. Please try again later.', 'WC_Payment_Gateway' );
			$frameDisplay .= '<div class="col2-set label-warning" style="border:1px solid red; text-align: center;" id="resurs-checkout-container">' . $frameContent . "<!-- \n" . $e->getMessage() . " --></div>";
		}
		$resursIframeCount ++;
		echo $frameDisplay;
	}

	/**
	 * There is a slight different design for this method since it's enabled through the choice of flow from Resurs primary configuration.
	 * Very few settings are configured from here.
	 */
	function admin_options() {
		// The WOO-48 should expire this section.
		$_REQUEST['tab']     = "tab_resursbank";
		$_REQUEST['section'] = "resurs_bank_omnicheckout";
		$url                 = admin_url( 'admin.php' );
		$url                 = add_query_arg( 'page', $_REQUEST['page'], $url );
		$url                 = add_query_arg( 'tab', $_REQUEST['tab'], $url );
		$url                 = add_query_arg( 'section', $_REQUEST['section'], $url );
		wp_safe_redirect( $url );
		die( "Deprecated space" );

		?>
        <table class="form-table">
            <h2>Custom shopFlow - <?php echo $this->method_title; ?></h2>
            <h3>Status: <?php echo hasResursOmni() ? __( "Enabled" ) : __( "Disabled" ); ?></h3>
			<?php
			$this->generate_settings_html();
			?>
        </table>
		<?php
	}

	function init_form_fields() {
		$this->form_fields = getResursWooFormFields( null, 'resurs_bank_omnicheckout' );
	}

	public function calculate_totals( $totals ) {
		global $woocommerce;
	}

	public function add_payment_gateway_extra_charges_row() {
		// Not in use at this position, since everything is handled externally
	}

	public function payment_fields() {
		if ( $this->iFrameLocation == "inMethods" ) {
			echo '<div id="resurs-checkout-container">' . $this->resurs_omnicheckout_create_frame() . "</div>";
		} else {
			echo '<div id="resurs-checkout-loader">' . $this->description . '</div>';
		}
	}

	public function get_current_gateway() {
		global $woocommerce;
		$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
		$current_gateway    = null;
		$default_gateway    = get_option( 'woocommerce_default_gateway' );
		if ( ! empty( $available_gateways ) ) {
			// Chosen Method
			if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
				$current_gateway = $available_gateways[ $woocommerce->session->chosen_payment_method ];
			} elseif ( isset( $available_gateways[ $default_gateway ] ) ) {
				$current_gateway = $available_gateways[ $default_gateway ];
			} else {
				$current_gateway = current( $available_gateways );
			}
		}
		if ( ! is_null( $current_gateway ) ) {
			return $current_gateway;
		} else {
			return false;
		}
	}

	public function has_icon() {
	}

	public static function interfere_checkout_process( $posted ) {
	}

	protected function resurs_omnicheckout_create_frame() {
		$this->flow->setPreferredPaymentService( \Resursbank\RBEcomPHP\ResursMethodTypes::METHOD_CHECKOUT );
		$this->flow->Include = array();
		$bookDataOmni        = self::createResursOmniOrder();
		//$shopUrl = home_url('');
		$omniRef = WC()->session->get( 'omniRef' );
		try {
			$flowBook  = $this->flow->bookPayment( $omniRef, $bookDataOmni );
			$flowFrame = is_string( $flowBook ) ? $flowBook : "";
			$flowFrame .= '<noscript><b>' . __( 'OmniCheckout will not work properly without Javascript functions enabled', 'WC_Payment_Gateway' ) . '</b></noscript>';
			if ( isset( $_SESSION['customTestUrl'] ) && ! empty( $_SESSION['customTestUrl'] ) ) {
				$flowFrame .= '<div class="resurs-read-more-box">' . __( 'Custom test environment URL', 'WC_Payment_Gateway' ) . ': <b>' . htmlentities( $_SESSION['customTestUrl'] ) . '</b></div>';
			}
		} catch ( Exception $e ) {
			$errorUnable = __( 'We are unable to load Resurs Checkout for the moment. Please try again later.', 'WC_Payment_Gateway' );
			$flowFrame   = '<div class="col2-set label-warning" style="border:1px solid red; text-align: center;" id="resurs-checkout-container">' . $errorUnable . "<!-- \n" . $e->getMessage() . " --></div>";
		}

		return $flowFrame;
	}

	/**
	 * @return array
	 */
	private function createResursOmniOrder() {
		global $woocommerce;
		$specLines    = self::get_payment_spec( $woocommerce->cart, true );
		$getUrls      = $this->createReturnUrls();
		$bookDataOmni = array(
			'orderLines' => $specLines,
			'successUrl' => $getUrls['successUrl'],
			'backUrl'    => $getUrls['backUrl']
		);
		$storeId      = apply_filters( "resursbank_set_storeid", null );
		if ( ! empty( $storeId ) ) {
			$bookDataOmni['storeId'] = $storeId;
		}

		return $bookDataOmni;
	}

	/**
	 * Shared OmniUrlMaker
	 * @return array
	 */
	private function createReturnUrls() {
		global $woocommerce;
		$returnArray = array();
		try {
			//$omniBack = $woocommerce->cart->get_cart_url();
			$omniBack    = $this->createResursOmniSuccessUrl( true );
			$omniSuccess = $this->createResursOmniSuccessUrl();
			$returnArray = array(
				'successUrl' => $omniSuccess,
				'backUrl'    => $omniBack
			);
		} catch ( Exception $e ) {

		}

		return $returnArray;
	}

	private function createResursOmniSuccessUrl( $isFailing = false ) {
		$this->omniSuccessUrl = home_url( '/' );
		if ( isResursSimulation() ) {
			$this->omniSuccessUrl = getResursOption( "devSimulateSuccessUrl" );
		}
		$omniRef              = WC()->session->get( 'omniRef' );
		$this->omniSuccessUrl = add_query_arg( 'wc-api', 'WC_Resurs_Bank', $this->omniSuccessUrl );
		$this->omniSuccessUrl = add_query_arg( 'utm_nooverride', '1', $this->omniSuccessUrl );
		$this->omniSuccessUrl = add_query_arg( 'event-type', 'check_signing_response', $this->omniSuccessUrl );
		$this->omniSuccessUrl = add_query_arg( 'payment_id', $omniRef, $this->omniSuccessUrl );
		$this->omniSuccessUrl = add_query_arg( 'set-no-session', '1', $this->omniSuccessUrl );
		$this->omniSuccessUrl = add_query_arg( 'flow-type', 'check_omni_response', $this->omniSuccessUrl );
		$this->omniSuccessUrl = add_query_arg( 'failInProgress', $isFailing ? 1 : 0, $this->omniSuccessUrl );
		$omniSuccessNonce     = wp_nonce_url( $this->omniSuccessUrl, "omnicheckout_callback_mode", "omnicheckout_callback_nonce" );
		$omniSuccessUrl       = $omniSuccessNonce;
		$this->omniSuccessUrl = $omniSuccessUrl;

		return $this->omniSuccessUrl;
	}

	/**
	 * Field removal at omni level
	 *
	 * If omnicheckout is enabled all fields should be handled by the iFrame instead.
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public function resurs_omnicheckout_fields( $fields ) {
		$keepFieldsHidden = getResursOption( "useStandardFieldsForShipping", "woocommerce_resurs_bank_omnicheckout_settings" );
		//$this->resetOmniCustomerFields = $fields;
		if ( isResursOmni() && hasResursOmni() ) {
			if ( ! defined( 'OMNICHECKOUT_PROCESSPAYMENT' ) ) {
				if ( ! $keepFieldsHidden ) {
					if ( isset( $fields['billing'] ) ) {
						$fields['billing'] = array();
					}
					if ( isset( $fields['shipping'] ) ) {
						$fields['shipping'] = array();
					}
				}
				/*
				 * For omni, to handle shipping, we need to remove all fields, including the "create account"-part
				 * to make the shipping area disappear. This is a problem, since this leads to the fact that the customer
				 * won't be able to create an account on fly. This however, is now being handled by the configuration interface
				 * since the behaviour from the themes may act different.
				 */
				$cleanOmniCustomerFields = ( $this->get_option( 'cleanOmniCustomerFields' ) == "true" ? 1 : 0 );
				if ( $cleanOmniCustomerFields && ! $keepFieldsHidden ) {
					$fields = array();
				}
			}

			return $fields;
		}

		return $fields;
	}

	/**
	 * Update the frame dynamically with cart-corrections from the checkout.
	 */
	public static function resurs_omni_update_order_review( $data ) {
		global $woocommerce;
		$currentOmniRef = null;
		if ( isset( WC()->session ) ) {
			$currentOmniRef = WC()->session->get( 'omniRef' );
		}
		if ( $_REQUEST['payment_method'] === 'resurs_bank_omnicheckout' && ! empty( $currentOmniRef ) ) {
			$paymentSpec = self::get_payment_spec( $woocommerce->cart );
			if ( isset( $paymentSpec['totalAmount'] ) ) {
				$flow               = initializeResursFlow();
				$omniUpdateResponse = $flow->setCheckoutFrameOrderLines( $currentOmniRef, $paymentSpec['specLines'] );
				if ( isset( $omniUpdateResponse['code'] ) ) {
					if ( $omniUpdateResponse['code'] == 200 ) {
					}
				}

				return $omniUpdateResponse;
			}
		}
	}

	public static function interfere_update_order_review( $array ) {
		$currentOmniRef = null;
		$doUpdateIframe = false;
		if ( isset( WC()->session ) ) {
			$paymentSpec    = self::get_payment_spec( WC()->cart );
			$currentOmniRef = WC()->session->get( 'omniRef' );
			$lastAmount     = WC()->session->get( 'lastOmniAmount' );
			if ( ! empty( $lastAmount ) && $lastAmount !== $paymentSpec['totalAmount'] ) {
				$doUpdateIframe = true;
			}
			$array['lastOmniAmount']    = $lastAmount;
			$array['currentOmniAmount'] = $paymentSpec['totalAmount'];
			$array['doUpdateIframe']    = $doUpdateIframe;

			WC()->session->set( 'lastOmniAmount', $paymentSpec['totalAmount'] );

			if ( isset( $paymentSpec['totalAmount'] ) && $doUpdateIframe ) {
				$paymentSpecAmount = $paymentSpec['totalAmount'];
				$flow              = initializeResursFlow();
				$omniUpdateResponse = $flow->setCheckoutFrameOrderLines( $currentOmniRef, $paymentSpec['specLines'] );
				if ( omniOption( "omniFrameNotReloading" ) ) {
					$array['#omniActions'] = '<script>document.location.reload(true);</script>';
				} else {
					$omniUpdateResponse = $flow->setCheckoutFrameOrderLines( $currentOmniRef, $paymentSpec['specLines'] );
				}

				if ( isset( $omniUpdateResponse['code'] ) ) {
					$array['omniUpdateResponse'] = $omniUpdateResponse['code'];
					if ( $omniUpdateResponse['code'] == 200 ) {
						return $array;
					}
				}
				$array['#omniInfo'] = $omniUpdateResponse;
			}
		}

		return $array;
	}

	/**
	 * Get specLines for startPaymentSession
	 *
	 * @param  array $cart WooCommerce cart containing order items
	 *
	 * @return array       The specLines for startPaymentSession
	 */
	protected static function get_spec_lines( $cart ) {
		$spec_lines = array();
		foreach ( $cart as $item ) {
			$data     = $item['data'];
			$_tax     = new WC_Tax();//looking for appropriate vat for specific product
			$rates    = array();
			$taxClass = $data->get_tax_class();
			$rates    = @array_shift( $_tax->get_rates( $taxClass ) );
			if ( isset( $rates['rate'] ) ) {
				$vatPct = (double) $rates['rate'];
			} else {
				$vatPct = 0;
			}

			$priceExTax     = ( ! isWooCommerce3() ? $data->get_price_excluding_tax() : wc_get_price_excluding_tax( $data ) );
			$totalVatAmount = ( $priceExTax * ( $vatPct / 100 ) );
			$setSku         = $data->get_sku();
			$bookArtId      = ! isWooCommerce3() ? $data->id : $data->get_id();
			$postTitle      = ! isWooCommerce3() ? $data->post->post_title : $data->get_title();
			$optionUseSku   = getResursOption( "useSku" );
			if ( $optionUseSku && ! empty( $setSku ) ) {
				$bookArtId = $setSku;
			}
			$spec_lines[] = array(
				'id'                   => $bookArtId,
				'artNo'                => $bookArtId,
				'description'          => ( empty( $postTitle ) ? __( 'Article description missing', 'WC_Payment_Gateway' ) : $postTitle ),
				'quantity'             => $item['quantity'],
				'unitMeasure'          => '',
				'unitAmountWithoutVat' => $priceExTax,
				'vatPct'               => $vatPct,
				'totalVatAmount'       => ( $priceExTax * ( $vatPct / 100 ) ),
				'totalAmount'          => ( ( $priceExTax * $item['quantity'] ) + ( $totalVatAmount * $item['quantity'] ) ),
				'type'                 => 'ORDER_LINE',
			);
		}

		return $spec_lines;
	}

	/**
	 * Get and convert payment spec from cart, convert it to Resurs Specrows
	 *
	 * @param $cart WooCommerce Cart containing order items
	 * @param bool $specLinesOnly Return only the array of speclines
	 *
	 * @return array The paymentSpec for startPaymentSession
	 */
	protected static function get_payment_spec( $cart, $specLinesOnly = false ) {
		global $woocommerce;

		//$payment_fee_tax_pct = 0;   // TODO: Figure out this legacy variable, that was never initialized.
		$spec_lines     = self::get_spec_lines( $cart->get_cart() );
		$shipping       = (float) $cart->shipping_total;
		$shipping_tax   = (float) $cart->shipping_tax_total;
		$shipping_total = (float) ( $shipping + $shipping_tax );
		/*
		 * Compatibility.
		 */
		$shipping_tax_pct = ( ! is_nan( @round( $shipping_tax / $shipping, 2 ) * 100 ) ? @round( $shipping_tax / $shipping, 2 ) * 100 : 0 );

		if ( false === empty( $shipping ) ) {
		}    //
		$spec_lines[]    = array(
			'id'                   => 'frakt',
			'artNo'                => '00_frakt',
			'description'          => __( 'Shipping', 'WC_Payment_Gateway' ),
			'quantity'             => '1',
			'unitMeasure'          => '',
			'unitAmountWithoutVat' => $shipping,
			'vatPct'               => $shipping_tax_pct,
			'totalVatAmount'       => $shipping_tax,
			'totalAmount'          => $shipping_total,
			'type'                 => 'SHIPPING_FEE',
		);
		$payment_method  = $woocommerce->session->chosen_payment_method;
		$payment_options = get_option( 'woocommerce_' . $payment_method . '_settings' );
		//$payment_fee = get_option('woocommerce_' . $payment_method . '_settings')['price'];
		$payment_fee           = getResursOption( 'price', 'woocommerce_' . $payment_method . '_settings' );
		$payment_fee           = (float) ( isset( $payment_fee ) ? $payment_fee : '0' );
		$payment_fee_tax_class = get_option( 'woocommerce_resurs-bank_settings' )['priceTaxClass'];
		if ( ! hasWooCommerce( "2.3", ">=" ) ) {
			$payment_fee_tax_class_rates = $cart->tax->get_rates( $payment_fee_tax_class );
			$payment_fee_tax             = $cart->tax->calc_tax( $payment_fee, $payment_fee_tax_class_rates, false, true );
		} else {
			// ->tax has been deprecated since WC 2.3
			$payment_fee_tax_class_rates = WC_Tax::get_rates( $payment_fee_tax_class );
			$payment_fee_tax             = WC_Tax::calc_tax( $payment_fee, $payment_fee_tax_class_rates, false, true );
		}

		$payment_fee_total_tax = 0;
		foreach ( $payment_fee_tax as $value ) {
			$payment_fee_total_tax = $payment_fee_total_tax + $value;
		}
		$tax_rates_pct_total = 0;
		foreach ( $payment_fee_tax_class_rates as $key => $rate ) {
			$tax_rates_pct_total = $tax_rates_pct_total + (float) $rate['rate'];
		}

		$ResursFeeName = "";
		$fees          = $cart->get_fees();
		if ( is_array( $fees ) ) {
			//$resursPriceDescription = sanitize_title($payment_options['priceDescription']);
			foreach ( $fees as $fee ) {
				/*
				 * Ignore this fee if it matches the Resurs description.
				 */
				//if ($fee == $resursPriceDescription) { continue; }
				if ( $fee->tax > 0 ) {
					$rate = ( $fee->tax / $fee->amount ) * 100;
				} else {
					$rate = 0;
				}
				if ( ! empty( $fee->id ) ) {
					$spec_lines[] = array(
						'id'                   => $fee->id,
						'artNo'                => $fee->id,
						'description'          => $fee->name,
						'quantity'             => 1,
						'unitMeasure'          => '',
						'unitAmountWithoutVat' => $fee->amount,
						'vatPct'               => ! is_nan( $rate ) ? $rate : 0,
						'totalVatAmount'       => $fee->tax,
						'totalAmount'          => $fee->amount + $fee->tax,
						'type'                 => 'ORDER_LINE',
					);
				}
			}
		}
		if ( $cart->coupons_enabled() ) {
			$coupons = $cart->get_coupons();
			if ( count( $coupons ) > 0 ) {
				$coupon_values     = $cart->coupon_discount_amounts;
				$coupon_tax_values = $cart->coupon_discount_tax_amounts;

				foreach ( $coupons as $code => $coupon ) {
					$post = get_post( ( ! isWooCommerce3() ? $coupon->id : $coupon->get_id() ) );
					//$taxify = ($coupon_tax_values[$code] / $coupon_values[$code]*100);

					$couponId     = ( ! isWooCommerce3() ? $coupon->id : $coupon->get_id() );
					$couponCode   = ( ! isWooCommerce3() ? $coupon->id : $coupon->get_code() );
					$spec_lines[] = array(
						'id'                   => $couponId,
						'artNo'                => $couponCode . '_' . 'kupong',
						'description'          => $post->post_excerpt,
						'quantity'             => 1,
						'unitMeasure'          => '',
						'unitAmountWithoutVat' => ( 0 - (float) $coupon_values[ $code ] ) + ( 0 - (float) $coupon_tax_values[ $code ] ),
						'vatPct'               => 0,
						'totalVatAmount'       => 0,
						'totalAmount'          => ( 0 - (float) $coupon_values[ $code ] ) + ( 0 - (float) $coupon_tax_values[ $code ] ),
						'type'                 => 'DISCOUNT',
					);
				}
			}
		}
		$ourPaymentSpecCalc = self::calculateSpecLineAmount( $spec_lines );
		if ( ! $specLinesOnly ) {
			$payment_spec = array(
				'specLines'      => $spec_lines,
				'totalAmount'    => $ourPaymentSpecCalc['totalAmount'],
				'totalVatAmount' => $ourPaymentSpecCalc['totalVatAmount'],
			);
		} else {
			return $spec_lines;
		}

		return $payment_spec;
	}

	/**
	 * @param array $specLine
	 *
	 * @return array
	 */
	protected static function calculateSpecLineAmount( $specLine = array() ) {
		/*
		 * Defaults
		 */
		$setPaymentSpec = array(
			'totalAmount'    => 0,
			'totalVatAmount' => 0
		);
		if ( is_array( $specLine ) && count( $specLine ) ) {
			foreach ( $specLine as $row ) {
				$setPaymentSpec['totalAmount']    += $row['totalAmount'];
				$setPaymentSpec['totalVatAmount'] += $row['totalVatAmount'];
			}
		}

		return $setPaymentSpec;
	}
}

function omni_terms_page( $page_id ) {
	if ( isResursOmni() ) {
		return 0;
	}

	return $page_id;
}

if ( hasResursOmni() ) {
	function woocommerce_add_resurs_bank_omnicheckout( $methods ) {
		$optionEnabled = getResursOption( 'enabled' );
		if ( ! $optionEnabled ) {
			return $methods;
		}
		global $woocommerce;
		$methods[] = "WC_Gateway_ResursBank_Omni";

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_resurs_bank_omnicheckout', 0 );
	//add_action('woocommerce_checkout_update_order_review', 'WC_Gateway_ResursBank_Omni::resurs_omni_update_order_review');
	add_filter( 'woocommerce_get_terms_page_id', 'omni_terms_page', 1 );

	/*
	 * Keeping this until next version, since it does not matter in which function the cart updater is sent to,
	 * as long as we can fetch the cart from WooCommerce.
	 */
	add_filter( 'woocommerce_update_order_review_fragments', 'WC_Gateway_ResursBank_Omni::interfere_update_order_review', 0, 1 );
}

function omniOption( $key = "" ) {
	$response = get_option( 'woocommerce_resurs_bank_omnicheckout_settings' )[ $key ];
	if ( empty( $response ) ) {
		$response = get_option( $key );
	}
	if ( $response === "true" ) {
		return true;
	}
	if ( $response === "false" ) {
		return false;
	}
	if ( $response === "yes" ) {
		return true;
	}
	if ( $response === "no" ) {
		return false;
	}

	return $response;
}
