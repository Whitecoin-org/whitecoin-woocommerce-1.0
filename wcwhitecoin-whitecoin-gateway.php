<?php
/*
Whitecoin Payments Gateway for WooCommerce
http://www.whitecoin.info/
*/

//---------------------------------------------------------------------------
add_action('plugins_loaded', 'WCWHITECOIN__plugins__load_whitecoin_gtway', 0);
//---------------------------------------------------------------------------

//###########################################################################
// Hook payment gateway into WooCommerce

function WCWHITECOIN__plugins__load_whitecoin_gtway ()
{
    if (!class_exists('WC_Payment_Gateway'))
    	// Nothing happens here is WooCommerce is not loaded
    	return;

	//=======================================================================
	/**
	 * Whitecoin Payment Gateway
	 * Provides a Whitecoin Payment Gateway
	 * @class 		WCWHITECOIN_Whitecoin
	 * @extends		WC_Payment_Gateway
	 * @version 	1.0
	 * @author 		Whitecoin-OtZi
	 */
	class WCWHITECOIN_Whitecoin extends WC_Payment_Gateway
	{
		//-------------------------------------------------------------------
	     /* Constructor for the gateway.
		  * Public
		  * Return true 
	     */
		public function __construct()
		{
      $this->id				= 'whitecoin';
      $this->icon 			= plugins_url('/images/whitecoin_buyitnow_32x.png', __FILE__);	// 32 pixels high
      $this->has_fields 		= false;
      $this->method_title     = __( 'Whitecoin', 'woocommerce' );

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title 		= $this->settings['title'];	// The title which the user is shown on the checkout – retrieved from the settings which init_settings loads.
			$this->whitecoin_rpchostaddr = $this->settings['whitecoin_rpchostaddr'];
			$this->whitecoin_rpcport = $this->settings['whitecoin_rpcport'];			
			$this->whitecoin_rpccert = $this->settings['whitecoin_rpccert'];
			$this->whitecoin_rpcssl = $this->settings['whitecoin_rpcssl'];
			$this->whitecoin_rpcssl_load = $this->settings['whitecoin_rpcssl_load'];
			$this->whitecoin_bittrex = $this->settings['whitecoin_bittrex'];
			$this->whitecoin_apikey_bittrex = $this->settings['whitecoin_apikey_bittrex'];
			$this->whitecoin_amount_bittrex = $this->settings['whitecoin_amount_bittrex'];
			$this->whitecoin_apisecret_bittrex = $this->settings['whitecoin_apisecret_bittrex'];
			$this->whitecoin_rpcuser = $this->settings['whitecoin_rpcssl'];//$this->settings['whitecoin_rpcuser'];			
			$this->whitecoin_rpcpass = $this->settings['whitecoin_rpcpass'];
			$this->whitecoin_addprefix = $this->settings['whitecoin_addprefix'];
			$this->whitecoin_confirmations = $this->settings['whitecoin_confirmations'];
			$this->exchange_rate_type = $this->settings['exchange_rate_type'];
			$this->exchange_multiplier = $this->settings['exchange_multiplier'];
			$this->description 	= $this->settings['description'];	// Short description about the gateway which is shown on checkout.
			$this->instructions = $this->settings['instructions'];	// Detailed payment instructions for the buyer.
			$this->instructions_multi_payment_str  = __('You may send payments from multiple accounts to reach the total required.', 'woocommerce');
			$this->instructions_single_payment_str = __('You must pay in a single payment in full.', 'woocommerce');
            
            //set SSL active/not active
			if($this->whitecoin_rpcssl=='yes'){
				$this->settings['whitecoin_rpcssl_active'] = 'yes';
			}else{
				$this->settings['whitecoin_rpcssl_active'] = 'no';
				}
				
			if($this->whitecoin_bittrex=='yes'){
				$this->settings['whitecoin_bittrex_active'] = 'yes';
			}else{
				$this->settings['whitecoin_bittrex_active'] = 'no';
				}
			// Load the form fields.
			$this->init_form_fields();

			// Actions
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      else
				add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options')); // hook into this action to save options in the backend

	    add_action('woocommerce_thankyou_' . $this->id, array(&$this, 'WCWHITECOIN__thankyou_page')); // hooks into the thank you page after payment

	    	// Customer Emails
	    add_action('woocommerce_email_before_order_table', array(&$this, 'WCWHITECOIN_instructions__email'), 10, 2); // hooks into the email template to show additional details

			// Hook IPN callback logic
			if (version_compare (WOOCOMMERCE_VERSION, '2.0', '<'))
				add_action('init', array(&$this, 'WCWHITECOIN__maybe_whitecoin_ipn_callback'));
			else
				add_action('woocommerce_api_' . strtolower(get_class($this)), array($this,'WCWHITECOIN__maybe_whitecoin_ipn_callback'));

			// Validate currently set currency for the store. Must be among supported ones.
			if (!$this->WCWHITECOIN__is_gtway_valid__use()) $this->enabled = false;
	    }
		//-------------------------------------------------------------------
		///////////////////////////////////////////////////////////////////////////////////
	    /**
	     * Check if this gateway is enabled and available for the store's default currency
	     * Public
	     * Return bool
	     */
	    function WCWHITECOIN__is_gtway_valid__use(&$ret_reason_message=NULL)
	    {
	    	$valid = true;

	    	//----------------------------------
	    	// Validate settings
	    	if (!$this->whitecoin_rpchostaddr)
	    	{
	    		$reason_message = __("Your Whitecoin RPC host address is not specified", 'woocommerce');
		    		$valid = false;
	    	}
			else if (!$this->whitecoin_rpcport)
	    	{
	    		$reason_message = __("Your Whitecoin RPC Port is not specified", 'woocommerce');
		    		$valid = false;
	    	}
			else if (!$this->whitecoin_rpcuser)
	    	{
	    		$reason_message = __("Your Whitecoin RPC Username is not specified", 'woocommerce');
		    		$valid = false;
	    	}
			else if (!$this->whitecoin_rpcpass)
	    	{
	    		$reason_message = __("Your Whitecoin RPC Password is not specified", 'woocommerce');
		    		$valid = false;
	    	}
			
			if ($this->whitecoin_rpcssl=='yes')
	    	{  if ($this->whitecoin_rpcssl_load=='')
	    	    {
	    		$reason_message = __($this->whitecoin_rpcssl."Your Whitecoin RPC Cert Path is not specified".$this->whitecoin_rpcssl_load, 'woocommerce');
		    		$valid = false;
	    	    }
			}
			
			if ($this->whitecoin_bittrex=='yes')
	    	{  if ($this->whitecoin_apikey_bittrex=='')
	    	    {
	    		$reason_message = __($this->whitecoin_apikey_bittrex."Your API Key is not specified".$this->whitecoin_apikey_bittrex, 'woocommerce');
		    		$valid = false;
	    	    }
				
				if ($this->whitecoin_amount_bittrex=='')
	    	    {
	    		$reason_message = __($this->whitecoin_amount_bittrex."Amount to verify for order is not specified".$this->whitecoin_amount_bittrex, 'woocommerce');
		    		$valid = false;
	    	    }
				
				if ($this->whitecoin_apisecret_bittrex=='')
	    	    {
	    		$reason_message = __($this->whitecoin_apisecret_bittrex."Your API Secret is not specified".$this->whitecoin_apisecret_bittrex, 'woocommerce');
		    		$valid = false;
	    	    }
				
				
			}
	   
	    	if (!$valid)
	    	{
	    		if ($ret_reason_message !== NULL)
	    			$ret_reason_message = $reason_message;
	    		return false;
	    	}
	    	//----------------------------------

	    	//----------------------------------
	    	// Validate connection to exchange rate services

	   		$store_currency_code = get_woocommerce_currency();
	   		if ($store_currency_code != 'WHITECOIN')
	   		{
					$currency_rate = WCWHITECOIN__get_exchange_rate_whitecoin ($store_currency_code, 'getfirst', 'bestrate', false);
					if (!$currency_rate)
					{
						$valid = false;

						// error message.
						$error_msg = "ERROR: Cannot determine exchange rates (for '$store_currency_code')! {{{ERROR_MESSAGE}}} Make sure your PHP settings are configured properly and your server can (is allowed to) connect to external WEB services via PHP.";
						$extra_error_message = "";
						$fns = array ('file_get_contents', 'curl_init', 'curl_setopt', 'curl_setopt_array', 'curl_exec');
						$fns = array_filter ($fns, 'WCWHITECOIN__function_not_exists');
						$extra_error_message = "";
						if (count($fns))
							$extra_error_message = "The following PHP functions are disabled on your server: " . implode (", ", $fns) . ".";

						$reason_message = str_replace('{{{ERROR_MESSAGE}}}', $extra_error_message, $error_msg);

		    		if ($ret_reason_message !== NULL)
		    			$ret_reason_message = $reason_message;
		    		return false;
					}
				}

	     	return true;
	    	//----------------------------------
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Initial Gateway Settings Form Fields
	     * Public
	     * Return void
	     */
	    function init_form_fields()
	    {
		    // This defines the settings we want to show in the admin area.
	    	//-----------------------------------
	    	// Assemble currency ticker.
	   		$store_currency_code = get_woocommerce_currency();
	   		if ($store_currency_code == 'XWC')
	   			$currency_code = 'USD';
	   		else
	   			$currency_code = $store_currency_code;

				$currency_ticker = WCWHITECOIN__get_exchange_rate_whitecoin ($currency_code, 'getfirst', 'bestrate', true);
	    	//-----------------------------------

	    	//-----------------------------------
	    	// Payment instructions
	    	$payment_instructions = '
<div id="whitecoin_intr_pay" style="border:1px solid #e0e0e0">

<table class="wcwhitecoin-payment-instructions-table" id="wcwhitecoin-payment-instructions-table">
  <tr class="bpit-table-row">
    <td colspan="2">Please send your whitecoin payment as follows:</td>
  </tr>
  <tr class="bpit-table-row">
    <td style="border:1px solid #FCCA09;vertical-align:middle" class="bpit-td-name bpit-td-name-amount">
      Amount (<strong>WHITECOIN</strong>):
    </td>
    <td class="bpit-td-value bpit-td-value-amount">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;color:#CC0000;font-weight: bold;font-size: 120%">
      	{{{WHITECOINS_AMOUNT}}}
      </div>
    </td>
  </tr>
  <tr class="bpit-table-row">
    <td style="border:1px solid #FCCA09;vertical-align:middle" class="bpit-td-name bpit-td-name-amount_currency">
      Total Amount with tax in(<strong>{{{CURRENCY}}}</strong>):
    </td>
    <td class="bpit-td-value bpit-td-value-amount">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;color:#CC0000;font-weight: bold;font-size: 120%">
      	{{{CURRENCY_AMOUNT}}}
      </div>
    </td>
  </tr>
  <tr class="bpit-table-row">
    <td style="border:1px solid #FCCA09;vertical-align:middle" class="bpit-td-name bpit-td-name-whitecoinaddr">
      Address:
    </td>
    <td class="bpit-td-value bpit-td-value-whitecoinaddr">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;color:#555;font-weight: bold;font-size: 120%">
        {{{WHITECOINS_ADDRESS}}}
      </div>
    </td>
  </tr>
  
</table>

Please note:
<ol class="bpit-instructions">
    <li>You must make a payment within 2 hour, or your order will be cancelled</li>
    <li>As soon as your payment is received in full you will receive email confirmation with order delivery details.</li>
    <li>{{{EXTRA_INSTRUCTIONS}}}</li>
</ol>
</div>
';

/*<tr class="bpit-table-row">
    <td style="border:1px solid #FCCA09;vertical-align:middle" class="bpit-td-name bpit-td-name-qr">
	    QR Code:
    </td>
    <td class="bpit-td-value bpit-td-value-qr">
      <div style="border:1px solid #FCCA09;padding:5px;margin:2px;">
        <a href="//{{{WHITECOINS_ADDRESS}}}?amount={{{WHITECOINS_AMOUNT}}}"><img src="https://blockchain.info/qr?data=whitecoin://{{{WHITECOINS_ADDRESS}}}?amount={{{WHITECOINS_AMOUNT}}}&amp;size=180" style="vertical-align:middle;border:1px solid #888" /></a>
      </div>
    </td>
  </tr>*/



				$payment_instructions = trim ($payment_instructions);

	    	$payment_instructions_description = '
						  <p class="description" style="width:50%;float:left;width:49%;">
					    	' . __( 'Specific instructions given to the customer to complete Whitecoins payment.<br />You may change it, but make sure these tags will be present: <b>{{{WHITECOINS_AMOUNT}}}</b>, <b>{{{WHITECOINS_ADDRESS}}}</b> and <b>{{{EXTRA_INSTRUCTIONS}}}</b> as these tags will be replaced with customer - specific payment details.', 'woocommerce' ) . '
						  </p>
						  <p class="description" style="width:50%;float:left;width:49%;">
					    	Payment Instructions, original template (for reference):<br />
					    	<textarea rows="2" onclick="this.focus();this.select()" readonly="readonly" style="width:100%;background-color:#f1f1f1;height:4em">' . $payment_instructions . '</textarea>
						  </p>
					';
				$payment_instructions_description = trim ($payment_instructions_description);
	    	//-----------------------------------


	    	$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'woocommerce' ),
								'type' => 'checkbox',
								'label' => __( 'Enable Whitecoin Payments', 'woocommerce' ),
								'default' => 'yes'
							),
				'title' => array(
								'title' => __( 'Title', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
								'default' => __( 'Whitecoin Payment', 'woocommerce' )
							),
				'whitecoin_rpchostaddr' => array(
								'title' => __('Whitecoin RPC host address:', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your Whitecoin RPC username [Save changes].", 'woocommerce'),
							),
								
				'whitecoin_rpcport' => array(
								'title' => __('Whitecoin RPC Port', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your Whitecoin RPC Port [Save changes].", 'woocommerce'),
							),		

				'whitecoin_rpcssl' => array(
								'title' => __('Whitecoin RPC SSL connect', 'woocommerce' ),
								'type' => 'checkbox',
								'default' => 'yes',
								'description' => __("Please select if your connect to Whitecoin RPC is with SSL certificate [Save changes].", 'woocommerce'),
							),
							
				'whitecoin_rpcssl_active' => array(
								'title' => '',
								'type' => 'hidden',
								'default' => 'no',
								'description' => '',
							),
								
				'whitecoin_rpcssl_load' => array(
								'title' => __('Whitecoin RPC SSL connect', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Path certificate server. Example: /siteroot/server.cert", 'woocommerce'),
							),		
							
				'whitecoin_rpcuser' => array(
								'title' => __('Whitecoin RPC username', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your Whitecoin RPC username [Save changes].<br />", 'woocommerce'),
							),
				'whitecoin_rpcpass' => array(
								'title' => __('Whitecoin RPC password', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your Whitecoin RPC password [Save changes].<br />", 'woocommerce'),
							),
				'whitecoin_addprefix' => array(
								'title' => __('Whitecoin address Prefix', 'woocommerce' ),
								'type' => 'text',
								'default' => 'whitecoin',
								'description' => __('The prefix for the address labels.<span class="help">The account will be in the form</span>', 'woocommerce'),
							),
							
				'whitecoin_confirmations' => array(
								'title' => __('Number of confirmations required before accepting payment', 'woocommerce' ),
								'type' => 'text',
								'default' => '6',
								'description' => __('', 'woocommerce'),
							),
							
				'whitecoin_bittrex' => array(
								'title' => __('Autoselling on Bittrex', 'woocommerce' ),
								'type' => 'checkbox',
								'default' => 'yes',
								'description' => __("Please select if you would create automatic sell order on bittrex.", 'woocommerce'),
							),
							
				'whitecoin_apikey_bittrex' => array(
								'title' => __('API Key Bittrex', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your API Key for bittrex.", 'woocommerce'),
							),
				'whitecoin_apisecret_bittrex' => array(
								'title' => __('API Secret Bittrex', 'woocommerce' ),
								'type' => 'text',
								'default' => '',
								'description' => __("Please enter your API Secret for bittrex.", 'woocommerce'),
							),					
				'whitecoin_amount_bittrex' => array(
								'title' => __('Amount to verify for order (WHITECOIN) ', 'woocommerce' ),
								'type' => 'text',
								'default' => '10',
								'description' => __("Please enter amount to verify.", 'woocommerce'),
							),		
							
				'whitecoin_bittrex_active' => array(
								'title' => '',
								'type' => 'hidden',
								'default' => 'no',
								'description' => '',
							),

				'exchange_rate_type' => array(
								'title' => __('Exchange rate calculation type', 'woocommerce' ),
								'type' => 'select',
								'disabled' => $store_currency_code=='WHITECOIN'?true:false,
								'options' => array(
									'vwap' => __( 'Weighted Average', 'woocommerce' ),
									'realtime' => __( 'Real time', 'woocommerce' ),
									'bestrate' => __( 'Most profitable', 'woocommerce' ),
									),
								'default' => 'vwap',
								'description' => ($store_currency_code=='WHITECOIN'?__('<span style="color:red;"><b>Disabled</b>: Applies only for stores with non-whitecoin default currency.</span><br />', 'woocommerce'):'') .
									__('<b>Weighted Average</b> (recommended): <a href="http://en.wikipedia.org/wiki/Volume-weighted_average_price" target="_blank">weighted average</a> rates polled from a number of exchange services<br />
										<b>Real time</b>: the most recent transaction rates polled from a number of exchange services.<br />
										<b>Most profitable</b>: pick better exchange rate of all indicators (most favorable for merchant). Calculated as: MIN (Weighted Average, Real time)') . '<br />' . $currency_ticker,
							),
				'exchange_multiplier' => array(
								'title' => __('Exchange rate multiplier', 'woocommerce' ),
								'type' => 'text',
								'disabled' => $store_currency_code=='WHITECOIN'?true:false,
								'description' => ($store_currency_code=='WHITECOIN'?__('<span style="color:red;"><b>Disabled</b>: Applies only for stores with non-whitecoin default currency.</span><br />', 'woocommerce'):'') .
									__('Extra multiplier to apply to convert store default currency to whitecoin price. <br />Example: <b>1.05</b> - will add extra 5% to the total price in whitecoins. May be useful to compensate merchant\'s loss to fees when converting whitecoins to local currency, or to encourage customer to use whitecoins for purchases (by setting multiplier to < 1.00 values).', 'woocommerce' ),
								'default' => '1.00',
							),
				'description' => array(
								'title' => __( 'Customer Message', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'Initial instructions for the customer at checkout screen', 'woocommerce' ),
								'default' => __( 'Please proceed to the next screen to see necessary payment details.', 'woocommerce' )
							),
				'instructions' => array(
								'title' => __( 'Payment Instructions (HTML)', 'woocommerce' ),
								'type' => 'textarea',
								'description' => $payment_instructions_description,
								'default' => $payment_instructions,
							),
				);
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
		/**
		 * Admin Panel Options
		 * Public
		 * Return void
		 */
		public function admin_options()
		{
			$validation_msg = "";
			$store_valid    = $this->WCWHITECOIN__is_gtway_valid__use ($validation_msg);

			// After defining the options, we need to display them too; thats where this next function comes into play:
	    	?>
	    	<h3><?php _e('Whitecoin Payment', 'woocommerce'); ?></h3>
	    	<p>
	    		<?php _e('<p style="border:1px solid #890e4e;padding:5px 10px;color:#004400;background-color:#FFF;"><u>Please donate WHITECOIN to</u>:&nbsp;&nbsp;<span style="color:#d21577;font-size:110%;font-weight:bold;">WdonAteNr8JFqiNJCgyiA6TRPYdETDc5fV</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size:95%;">(All supporters will be in www.whitecoin.info)</span></p>
	    			',
	    				'woocommerce'); ?>
	    	</p>
	    	<?php
	    		echo $store_valid ? ('<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#004400;background-color:#CCFFCC;">' . __('Whitecoin payment gateway is operational','woocommerce') . '</p>') : ('<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#EE0000;background-color:#FFFFAA;">' . __('Whitecoin payment gateway is not operational: ','woocommerce') . $validation_msg . '</p>');
	    	?>
	    	<table class="form-table">
	    	<?php
	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();
	    	?>
			</table><!--/.form-table-->
	    	<?php
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	  // Hook into admin options saving.
    public function process_admin_options()
    {
    	// Call parent
    	parent::process_admin_options();

    	if (isset($_POST) && is_array($_POST))
    	{
	  		$wcwhitecoin_settings = WCWHITECOIN__get_settings ();
	  		if (!isset($wcwhitecoin_settings['gateway_settings']) || !is_array($wcwhitecoin_settings['gateway_settings']))
	  			$wcwhitecoin_settings['gateway_settings'] = array();

	    	$prefix        = 'woocommerce_whitecoin_';
	    	$prefix_length = strlen($prefix);

	    	foreach ($_POST as $varname => $varvalue)
	    	{

	    		if (strpos($varname, 'woocommerce_whitecoin_') === 0)
	    		{
	    			$trimmed_varname = substr($varname, $prefix_length);
	    			if ($trimmed_varname != 'description' && $trimmed_varname != 'instructions'){
					  if ($trimmed_varname == 'whitecoin_bittrex_active'){
						  
						  if ($_POST['woocommerce_whitecoin_whitecoin_bittrex'] === '1'){
							$wcwhitecoin_settings['gateway_settings'][$trimmed_varname] = 'yes';  
						  }else{
							$wcwhitecoin_settings['gateway_settings'][$trimmed_varname] = 'no';  
						 }
					   }else if ($trimmed_varname == 'whitecoin_rpcssl_active'){
						  if ($_POST['woocommerce_whitecoin_whitecoin_rpcssl'] === '1'){
							$wcwhitecoin_settings['gateway_settings'][$trimmed_varname] = 'yes';  
						  }else{
							$wcwhitecoin_settings['gateway_settings'][$trimmed_varname] = 'no';  
						 }	  
					  }else{
	    				$wcwhitecoin_settings['gateway_settings'][$trimmed_varname] = $varvalue;
	    		   }
				  }
				}
	    	}

	  		// Update gateway settings within GOWC own settings for easier access.
			
	      WCWHITECOIN__update_settings ($wcwhitecoin_settings);
	    }
    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Process the payment and return the result
	     *
	     * @access public
	     * @param int $order_id
	     * @return array
	     */
		function process_payment ($order_id)
		{
			$order = new WC_Order ($order_id);

			//-----------------------------------
			// Save whitecoin payment info together with the order.
			// Note: this code must be on top here, as other filters will be called from here and will use these values ...
			//
			// Calculate realtime whitecoin price (if exchange is necessary)

			$exchange_rate = WCWHITECOIN__get_exchange_rate_whitecoin (get_woocommerce_currency(), 'getfirst', $this->exchange_rate_type);
			/// $exchange_rate = WCWHITECOIN__get_exchange_rate_whitecoin (get_woocommerce_currency(), $this->exchange_rate_retrieval_method, $this->exchange_rate_type);
			if (!$exchange_rate)
			{
				$msg = 'ERROR: Cannot determine Whitecoin exchange rate. Possible issues: store server does not allow outgoing connections, exchange rate servers are blocking incoming connections or down. ' .
					   'You may avoid that by setting store currency directly to Whitecoin(WHITECOIN)';
      			WCWHITECOIN__log_event (__FILE__, __LINE__, $msg);
      			exit ('<h2 style="color:red;">' . $msg . '</h2>');
			}

			$order_total_in_whitecoin   = ($order->get_total() / $exchange_rate);
			if (get_woocommerce_currency() != 'WHITECOIN')
				// Apply exchange rate multiplier only for stores with non-whitecoin default currency.
				$order_total_in_whitecoin = $order_total_in_whitecoin * $this->exchange_multiplier;

			$order_total_in_whitecoin   = sprintf ("%.8f", $order_total_in_whitecoin);

  		$whitecoins_address = false;

  		$order_info =
  			array (
  				'order_id'				=> $order_id,
  				'order_total'			=> $order_total_in_whitecoin,
  				'order_datetime'  => date('Y-m-d H:i:s T'),
  				'requested_by_ip'	=> @$_SERVER['REMOTE_ADDR'],
  				);

  		$ret_info_array = array();
	   			// This function generate whitecoin address from RPC
				$ret_info_array = WCWHITECOIN__get_whitecoin_address_for_payment($order_info);
				$whitecoins_address = @$ret_info_array['generated_whitecoin_address'];

			if (!$whitecoins_address)
			{
				$msg = "ERROR: cannot generate whitecoin address for the order: '" . @$ret_info_array['message'] . "'";
      			WCWHITECOIN__log_event (__FILE__, __LINE__, $msg);
      			exit ('<h2 style="color:red;">' . $msg . '</h2>');
			}

   		WCWHITECOIN__log_event (__FILE__, __LINE__, "     Generated unique whitecoin address: '{$whitecoins_address}' for order_id " . $order_id);

     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'order_total_in_whitecoin', 	// meta key
     		$order_total_in_whitecoin 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'whitecoins_address',	// meta key
     		$whitecoins_address 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'whitecoins_paid_total',	// meta key
     		"0" 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'whitecoins_refunded',	// meta key
     		"0" 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 				// post id ($order_id)
     		'_incoming_payments',	// meta key. Starts with '_' - hidden from UI.
     		array()					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
     	update_post_meta (
     		$order_id, 				// post id ($order_id)
     		'_payment_completed',	// meta key. Starts with '_' - hidden from UI.
     		0					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
		update_post_meta (
     		$order_id, 				// post id ($order_id)
     		'exchange_rate',	// meta key. Starts with '_' - hidden from UI.
     		$exchange_rate					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
			//-----------------------------------


			// The whitecoin gateway does not take payment immediately, but it does need to change the orders status to on-hold
			// (so the store owner knows that whitecoin payment is pending).
			// We also need to tell WooCommerce that it needs to redirect to the thankyou page – this is done with the returned array
			// and the result being a success.
			//
			global $woocommerce;

			//	Updating the order status:

			// Mark as on-hold (we're awaiting for whitecoins payment to arrive)
			$order->update_status('on-hold', __('Awaiting whitecoin payment to arrive', 'woocommerce'));

/*
			///////////////////////////////////////
			// timbowhite's suggestion:
			// -----------------------
			// Mark as pending (we're awaiting for whitecoins payment to arrive), not 'on-hold' since
      // woocommerce does not automatically cancel expired on-hold orders. Woocommerce handles holding the stock
      // for pending orders until order payment is complete.
			$order->update_status('pending', __('Awaiting whitecoin payment to arrive', 'woocommerce'));

			// Me: 'pending' does not trigger "Thank you" page and neither email sending. Not sure why.
			//			Also - I think cancellation of unpaid orders needs to be initiated from cron job, as only we know when order needs to be cancelled,
			//			by scanning "on-hold" orders through '' timeout check.
			///////////////////////////////////////
*/
			// Remove cart
			$woocommerce->cart->empty_cart();

			// Empty awaiting payment session
			unset($_SESSION['order_awaiting_payment']);

			// Return thankyou redirect
			if (version_compare (WOOCOMMERCE_VERSION, '2.1', '<'))
			{
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))))
				);
			}
			else
			{
				return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, $this->get_return_url( $order )))
					);
			}
		}
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Output for the order received page.
	     * Public
	     * Return void
	     */
		function WCWHITECOIN__thankyou_page($order_id)
		{
			// WCWHITECOIN__thankyou_page is hooked into the "thank you" page and in the simplest case can just echo’s the description.

			// Get order object.
			$order = new WC_Order($order_id);

			// Assemble detailed instructions.
			$order_total_in_whitecoin   = get_post_meta($order->id, 'order_total_in_whitecoin',   true); // set single to true to receive properly unserialized array
			$whitecoins_address = get_post_meta($order->id, 'whitecoins_address', true); // set single to true to receive properly unserialized array
			$exchange_rate  = get_post_meta($order->id, 'exchange_rate',   true); // set exchange rate


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{WHITECOINS_AMOUNT}}}',  $order_total_in_whitecoin, $instructions);
			$instructions = str_replace ('{{{CURRENCY}}}',  get_woocommerce_currency(), $instructions);
			$instructions = str_replace ('{{{CURRENCY_AMOUNT}}}',  $order_total_in_whitecoin * $exchange_rate, $instructions);
			$instructions = str_replace ('{{{WHITECOINS_ADDRESS}}}', $whitecoins_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);
            $order->add_order_note( __("Order instructions: price=&#3647;{$order_total_in_whitecoin}, incoming account:{$whitecoins_address}", 'woocommerce'));

	        echo wpautop (wptexturize ($instructions));
		}
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Add content to the WC emails.
	     * Public
	     * Param WC_Order $order
	     * Pparam bool $sent_to_admin
	     * Return void
	     */
		function WCWHITECOIN_instructions__email ($order, $sent_to_admin)
		{
	    	if ($sent_to_admin) return;
	    	if (!in_array($order->status, array('pending', 'on-hold'), true)) return;
	    	if ($order->payment_method !== 'whitecoin') return;

	    	// Assemble payment instructions for email
			$order_total_in_whitecoin   = get_post_meta($order->id, 'order_total_in_whitecoin',   true); // set single to true to receive properly unserialized array
			$whitecoins_address = get_post_meta($order->id, 'whitecoins_address', true); // set single to true to receive properly unserialized array
			$exchange_rate  = get_post_meta($order->id, 'exchange_rate',   true); // set exchange rate


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{WHITECOINS_AMOUNT}}}',  $order_total_in_whitecoin, 	$instructions);
			$instructions = str_replace ('{{{CURRENCY}}}',  get_woocommerce_currency(), $instructions);
			$instructions = str_replace ('{{{CURRENCY_AMOUNT}}}',  $order_total_in_whitecoin * $exchange_rate, $instructions);
			$instructions = str_replace ('{{{WHITECOINS_ADDRESS}}}', $whitecoins_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);

			echo wpautop (wptexturize ($instructions));
		}
		//-------------------------------------------------------------------

	}
	//=======================================================================


	//-----------------------------------------------------------------------
	// Hook into WooCommerce - add necessary hooks and filters
	add_filter ('woocommerce_payment_gateways', 	'WCWHITECOIN__add_whitecoin_gateway' );

	// Disable unnecessary billing fields.
	/// Note: it affects whole store.
	/// add_filter ('woocommerce_checkout_fields' , 	'WCWHITECOIN__woocommerce_checkout_fields' );

	add_filter ('woocommerce_currencies', 			'WCWHITECOIN__add_whitecoin_currency');
	add_filter ('woocommerce_currency_symbol', 		'WCWHITECOIN__add_whitecoin_currency_symbol', 10, 2);

	// Change [Order] button text on checkout screen.
    /// Note: this will affect all payment methods.
    /// add_filter ('woocommerce_order_button_text', 	'WCWHITECOIN__order_button_text');
	//-----------------------------------------------------------------------

	//=======================================================================
	/**
	 * Add the gateway to WooCommerce
	 *
	 * @access public
	 * @param array $methods
	 * @package
	 * @return array
	 */
	function WCWHITECOIN__add_whitecoin_gateway( $methods )
	{
		$methods[] = 'WCWHITECOIN_Whitecoin';
		return $methods;
	}
	//=======================================================================

	//=======================================================================
	// Our hooked in function - $fields is passed via the filter!
	function WCWHITECOIN__woocommerce_checkout_fields ($fields)
	{
	     unset($fields['order']['order_comments']);
	     unset($fields['billing']['billing_first_name']);
	     unset($fields['billing']['billing_last_name']);
	     unset($fields['billing']['billing_company']);
	     unset($fields['billing']['billing_address_1']);
	     unset($fields['billing']['billing_address_2']);
	     unset($fields['billing']['billing_city']);
	     unset($fields['billing']['billing_postcode']);
	     unset($fields['billing']['billing_country']);
	     unset($fields['billing']['billing_state']);
	     unset($fields['billing']['billing_phone']);
	     return $fields;
	}
	//=======================================================================

	//=======================================================================
	function WCWHITECOIN__add_whitecoin_currency($currencies)
	{
	     $currencies['WHITECOIN'] = __( 'Whitecoin (₲)', 'woocommerce' );
	     return $currencies;
	}
	//=======================================================================

	//=======================================================================
	function WCWHITECOIN__add_whitecoin_currency_symbol($currency_symbol, $currency)
	{
		switch( $currency )
		{
			case 'WHITECOIN':
				$currency_symbol = '₲';
				break;
		}

		return $currency_symbol;
	}
	//=======================================================================

	//=======================================================================
 	function WCWHITECOIN__order_button_text () { return 'Continue'; }
	//=======================================================================
}
//###########################################################################

//===========================================================================
function WCWHITECOIN__process_payment_completed_for_order ($order_id, $whitecoins_paid=false)
{

	if ($whitecoins_paid)
		update_post_meta ($order_id, 'whitecoins_paid_total', $whitecoins_paid);

	// Payment completed
	// Make sure this logic is done only once, in case customer keep sending payments :)
	if (!get_post_meta($order_id, '_payment_completed', true))
	{
		update_post_meta ($order_id, '_payment_completed', '1');

		WCWHITECOIN__log_event (__FILE__, __LINE__, "Success: order '{$order_id}' paid in full. Processing and notifying customer ...");

		// Instantiate order object.
		$order = new WC_Order($order_id);
				
		$order->add_order_note( __('Order paid in full', 'woocommerce') );

	  $order->payment_complete();
	}
}
//===========================================================================

function WCWHITECOIN__process_cancelled_for_order ($order_id)
{

	if (!get_post_meta($order_id, '_order_cancelled', true))
	{
		update_post_meta ($order_id, '_order_cancelled', '1');

		WCWHITECOIN__log_event (__FILE__, __LINE__, "Order Cancell: order '{$order_id}' is cancelled. ");

		// Instantiate order object.
		$order = new WC_Order($order_id);
		$order->add_order_note( __('Order cancelled', 'woocommerce') );

	  $order->cancel_order();
	}
}
//===========================================================================

function WCWHITECOIN_order_cancelled__email ($order)
		{
	    	if (!in_array($order->status, array('pending', 'on-hold'), true)) return;
	    	if ($order->payment_method !== 'whitecoin') return;

	    	// Assemble payment instructions for email
			$order_total_in_whitecoin   = get_post_meta($order->id, 'order_total_in_whitecoin',   true); // set single to true to receive properly unserialized array
			$whitecoins_address = get_post_meta($order->id, 'whitecoins_address', true); // set single to true to receive properly unserialized array


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{WHITECOINS_AMOUNT}}}',  $order_total_in_whitecoin, 	$instructions);
			$instructions = str_replace ('{{{WHITECOINS_ADDRESS}}}', $whitecoins_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);

			echo wpautop (wptexturize ($instructions));
		}
		//-------------------------------------------------------------------