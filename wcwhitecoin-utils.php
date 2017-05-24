<?php
/*
Whitecoin Payments for WooCommerce
*/


//===========================================================================
/*
   Input:
   ------
      $order_info =
         array (
            'order_id'        => $order_id,
            'order_total'     => $order_total_in_whitecoin,
            'order_datetime'  => date('Y-m-d H:i:s T'),
            'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
            );
*/
// Returns:
// --------
/*
    $ret_info_array = array (
       'result'                      => 'success', // OR 'error'
       'message'                     => '...',
       'host_reply_raw'              => '......',
       'generated_whitecoin_address'   => '1H9uAP3x439YvQDoKNGgSYCg3FmrYRzpD2', // or false
       );
*/
//


function WCWHITECOIN__get_whitecoin_address_for_payment($order_info)
{
   global $wpdb;

   // status = "unused", "assigned", "used"
   $whitecoin_addresses_table_name     = $wpdb->prefix . 'wcwhitecoin_whitecoin_addresses';
   
   $wcwhitecoin_settings = WCWHITECOIN__get_settings ();
   $clean_address = NULL;
   $current_time = time();

  //-------------------------------------------------------
  if (!$clean_address)
  {
    // Still could not find unused virgin address. Time to generate it from scratch.
    /*
    Returns:
       $ret_info_array = array (
          'result'                      => 'success', // 'error'
          'message'                     => '', // Failed to find/generate whitecoin address',
          'host_reply_raw'              => '', // Error. No host reply availabe.',
          'generated_whitecoin_address'   => '1FVai2j2FsFvCbgsy22ZbSMfUd3HLUHvKx', // false,
          );
    */
    $ret_addr_array = WCWHITECOIN__generate_new_whitecoin_address_wallet ($wcwhitecoin_settings, $order_info);
    if ($ret_addr_array['result'] == 'success')
      $clean_address = $ret_addr_array['generated_whitecoin_address'];
  }
  //-------------------------------------------------------

  //-------------------------------------------------------
   if ($clean_address)
   {
   /*
         $order_info =
         array (
            'order_id'     => $order_id,
            'order_total'  => $order_total_in_whitecoin,
            'order_datetime'  => date('Y-m-d H:i:s T'),
            'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
            );

*/

      /*
      $address_meta =
         array (
            'orders' =>
               array (
                  // All orders placed on this address in reverse chronological order
                  array (
                     'order_id'     => $order_id,
                     'order_total'  => $order_total_in_whitecoin,
                     'order_datetime'  => date('Y-m-d H:i:s T'),
                     'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
                  ),
                  array (
                     ...
                  ),
               ),
            'other_meta_info' => array (...)
         );
      */

      // Prepare `address_meta` field for this clean address.
      $address_meta = $wpdb->get_var ("SELECT `address_meta` FROM `$whitecoin_addresses_table_name` WHERE `whitecoin_address`='$clean_address'");
      $address_meta = WCWHITECOIN_unserialize_address_meta ($address_meta);

      if (!isset($address_meta['orders']) || !is_array($address_meta['orders']))
         $address_meta['orders'] = array();

      array_unshift ($address_meta['orders'], $order_info);    // Prepend new order to array of orders
      if (count($address_meta['orders']) > 10)
         array_pop ($address_meta['orders']);   // Do not keep history of more than 10 unfullfilled orders per address.
      $address_meta_serialized = WCWHITECOIN_serialize_address_meta ($address_meta);

      // Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
      //
      $current_time = time();
      $remote_addr  = $order_info['requested_by_ip'];
      $query =
      "UPDATE `$whitecoin_addresses_table_name`
         SET
            `total_received_funds` = '0',
            `received_funds_checked_at`='$current_time',
            `status`='assigned',
            `assigned_at`='$current_time',
            `last_assigned_to_ip`='$remote_addr',
            `address_meta`='$address_meta_serialized'
        WHERE `whitecoin_address`='$clean_address';";
      $ret_code = $wpdb->query ($query);

      $ret_info_array = array (
         'result'                      => 'success',
         'message'                     => "",
         'host_reply_raw'              => "",
         'generated_whitecoin_address'   => $clean_address,
         );

      return $ret_info_array;
  }
  //-------------------------------------------------------

   $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => 'Failed to find/generate whitecoin address. ' . $ret_addr_array['message'],
      'host_reply_raw'              => $ret_addr_array['host_reply_raw'],
      'generated_whitecoin_address'   => false,
      );
   return $ret_info_array;
}
//===========================================================================

//===========================================================================

//===========================================================================
/*
Returns:
   $ret_info_array = array (
      'result'                      => 'success', // 'error'
      'message'                     => '', // Failed to find/generate whitecoin address',
      'host_reply_raw'              => '', // Error. No host reply availabe.',
      'generated_whitecoin_address'   => '1FVai2j2FsFvCbgsy22ZbSMfUd3HLUHvKx', // false,
      );
*/
//
function WCWHITECOIN__generate_new_whitecoin_address_wallet ($wcwhitecoin_settings=false, $order_info)
{
  global $wpdb;

  $whitecoin_addresses_table_name = $wpdb->prefix . 'wcwhitecoin_whitecoin_addresses';

  if (!$wcwhitecoin_settings){
    $wcwhitecoin_settings = WCWHITECOIN__get_settings();
	}
	// Setting account for get new address
  $whitecoin_prefix = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_addprefix'];
  $whitecoin_account = $whitecoin_prefix."_".$order_info['order_id'];
  //Get numbers of confirmations requiered
  $confirmations = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_confirmations'];
 /* if($whitecoin_prefix){
  	  $whitecoin_account = $whitecoin_prefix."_".$order_info['order_id'];
  }else{
	  $whitecoin_account =(string)$order_info['order_id'];
  }*/
  
  $clean_address = false;

  // Find next index to generate
  $next_key_index = $wpdb->get_var ("SELECT MAX(`index_in_wallet`) AS `max_index_in_wallet` FROM `$whitecoin_addresses_table_name`;");
  if ($next_key_index === NULL)
    $next_key_index = $wcwhitecoin_settings['starting_index_for_new_whitecoin_addresses']; // Start generation of addresses from index #2 (skip two leading wallet's addresses)
  else
    $next_key_index = $next_key_index+1;  // Continue with next index

  $total_new_keys_generated = 0;
  $blockchains_api_failures = 0;
  do
  {
    $new_whitecoin_address_result = WCWHITECOIN__generate_whitecoin_address_from_rpc ($wcwhitecoin_settings, $whitecoin_account);
	
	if($new_whitecoin_address_result['result']=='error'){
	   $ret_info_array = array (
          'result'                      => 'error',
          'message'                     => $new_whitecoin_address_result['message']!=''?$new_whitecoin_address_result['message']:"Problem: Generated Whitecoin Address",
          'host_reply_raw'              => "",
          'generated_whitecoin_address'   => false,
          );
        return $ret_info_array;	
	}
	$new_whitecoin_address = $new_whitecoin_address_result['generated_whitecoin_address'];	
    $ret_info_array  = WCWHITECOIN__getreceivedbyaddress_info ($wcwhitecoin_settings, $whitecoin_account, $confirmations);
    $total_new_keys_generated ++;

    if ($ret_info_array['balance'] === false)
      $status = 'unknown';
    else if ($ret_info_array['balance'] == 0)
      $status = 'unused'; // Newly generated address with freshly checked zero balance is unused and will be assigned.
    else
      $status = 'used';   // Generated address that was already used to receive money.

    $funds_received                  = ($ret_info_array['balance'] === false)?-1:$ret_info_array['balance'];
    $received_funds_checked_at_time  = ($ret_info_array['balance'] === false)?0:time();

    // Insert newly generated address into DB
    $query =
      "INSERT INTO `$whitecoin_addresses_table_name`
      (`whitecoin_address`, `whitecoin_account`, `index_in_wallet`, `total_received_funds`, `received_funds_checked_at`, `status`) VALUES
      ('$new_whitecoin_address', '$whitecoin_account', '$next_key_index', '$funds_received', '$received_funds_checked_at_time', '$status');";
    $ret_code = $wpdb->query ($query);

    $next_key_index++;

    if ($ret_info_array['balance'] === false)
    {
      $blockchains_api_failures ++;
      if ($blockchains_api_failures >= $wcwhitecoin_settings['max_blockchains_api_failures'])
      {
        // Allow no more than 3 contigious blockchains API failures. After which return error reply.
        $ret_info_array = array (
          'result'                      => 'error',
          'message'                     => $ret_info_array['message'],
          'host_reply_raw'              => "",
          'generated_whitecoin_address'   => false,
          );
        return $ret_info_array;
      }
    }
    else
    {
      if ($ret_info_array['balance'] == 0)
      {
        // Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
        $clean_address    = $new_whitecoin_address;
      }
    }

    if ($clean_address)
      break;

    if ($total_new_keys_generated >= $wcwhitecoin_settings['max_unusable_generated_addresses'])
    {
      // Stop it after generating of 20 unproductive addresses.
      // Something is wrong. Possibly old merchant's wallet (with many used addresses) is used for new installation. - For this case 'starting_index_for_new_whitecoin_addresses'
      //  needs to be proper set to high value.
      $ret_info_array = array (
        'result'                      => 'error',
        'message'                     => "Problem: Generated '$total_new_keys_generated' addresses and none were found to be unused. Possibly old merchant's wallet (with many used addresses) is used for new installation. If that is the case - 'starting_index_for_new_whitecoin_addresses' needs to be proper set to high value",
        'host_reply_raw'              => '',
        'generated_whitecoin_address'   => false,
        );
      return $ret_info_array;
    }

  } while (true);

  // Here only in case of clean address.
  $ret_info_array = array (
    'result'                      => 'success',
    'message'                     => '',
    'host_reply_raw'              => '',
    'generated_whitecoin_address'   => $clean_address,
    );

  return $ret_info_array;
}



//===========================================================================

//===========================================================================
// Function makes sure that returned value is valid array
function WCWHITECOIN_unserialize_address_meta ($flat_address_meta)
{
   $unserialized = @unserialize($flat_address_meta);
   if (is_array($unserialized))
      return $unserialized;
   return array();
}
//===========================================================================

//===========================================================================
// Function makes sure that value is ready to be stored in DB
function WCWHITECOIN_serialize_address_meta ($address_meta_arr)
{
   return WCWHITECOIN__safe_string_escape(serialize($address_meta_arr));
}
//===========================================================================

//===========================================================================
function WCWHITECOIN__generate_whitecoin_address_from_rpc ($wcwhitecoin_settings=false, $whitecoin_account)
{
	if (!$wcwhitecoin_settings){
    $wcwhitecoin_settings = WCWHITECOIN__get_settings();
	}
	$ssl_true = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcssl_active'];
	$rpcuser = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcuser'];
	$rpcpass = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcpass'];
	$rpchost = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpchostaddr'];
	$rpcport = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcport'];
	
	
	//Create rpc connection
	$whitecoin = new Whitecoin($rpcuser,$rpcpass,$rpchost,$rpcport);
	if($ssl_true=='yes'){
     $prvkey_whitecoin = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcssl_load'];
     $whitecoin->setSSL($prvkey_whitecoin);
	}
    $response = $whitecoin->getnewaddress($whitecoin_account);  
	if($response['status']== 'error'){
		 $ret_info_array = array (
          'result'                      => 'error',
          'message'                     => $response['result'],
          'host_reply_raw'              => "",
          'generated_whitecoin_address'   => false,
          );
        return $ret_info_array;	
		}
	if($response['status']== 'success'){
		$ret_info_array = array (
          'result'                      => 'success',
          'message'                     => "",
          'host_reply_raw'              => "",
          'generated_whitecoin_address'   => $response['result'],
          );
        return $ret_info_array;	
		}
}
//===========================================================================



//===========================================================================
/*
$ret_info_array = array (
  'result'                      => 'success',
  'message'                     => "",
  'host_reply_raw'              => "",
  'balance'                     => false == error, else - balance
  );
*/

function WCWHITECOIN__getreceivedbyaddress_info ($wcwhitecoin_settings=false, $whitecoin_account, $confirmations=1, $api_timeout=10)
{
	if (!$wcwhitecoin_settings){
    $wcwhitecoin_settings = WCWHITECOIN__get_settings();
	}
	$ssl_true = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcssl_active'];
	$rpcuser = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcuser'];
	$rpcpass = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcpass'];
	$rpchost = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpchostaddr'];
	$rpcport = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcport'];
	
	if ($whitecoin_account == ''){
		$ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Invalid account",
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
	
	return $ret_info_array;	
	}	
	
	$whitecoin = new Whitecoin($rpcuser,$rpcpass,$rpchost,$rpcport);
    if($ssl_true=='yes'){
     $prvkey_whitecoin = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcssl_load'];
     $whitecoin->setSSL($prvkey_whitecoin);
	}
    $response = $whitecoin->getreceivedbyaccount($whitecoin_account,(int)$confirmations);  
	if($response['status']== 'error'){
		 $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Whitecoin API failure. Erratic replies/Error: ".$response['result'],
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
		}
	if($response['status']== 'success'){
		 if (is_numeric($response['result']))
  {
    $ret_info_array = array (
      'result'                      => 'success',
      'message'                     => "",
      'host_reply_raw'              => "",
      'balance'                     => $response['result'],
      );
  }
  else
  {
    $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Whitecoin API failure. Erratic replies",
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
  }
		}


  return $ret_info_array;
}

function WCWHITECOIN__getbalancewallet_info ($wcwhitecoin_settings=false, $api_timeout=10)
{
	if (!$wcwhitecoin_settings){
    $wcwhitecoin_settings = WCWHITECOIN__get_settings();
	}
	$ssl_true = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcssl_active'];
	$rpcuser = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcuser'];
	$rpcpass = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcpass'];
	$rpchost = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpchostaddr'];
	$rpcport = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcport'];
		
	
	$whitecoin = new Whitecoin($rpcuser,$rpcpass,$rpchost,$rpcport);
    if($ssl_true=='yes'){
     $prvkey_whitecoin = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcssl_load'];
     $whitecoin->setSSL($prvkey_whitecoin);
	}
    $response = $whitecoin->getbalance();  
	if($response['status']== 'error'){
		 $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Whitecoin API failure. Erratic replies/Error: ".$response['result'],
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
		}
	if($response['status']== 'success'){
		 if (is_numeric($response['result']))
  {
    $ret_info_array = array (
      'result'                      => 'success',
      'message'                     => "",
      'host_reply_raw'              => "",
      'balance'                     => $response['result'],
      );
  }
  else
  {
    $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Whitecoin API failure. Erratic replies",
      'host_reply_raw'              => "",
      'balance'                     => false,
      );
  }
		}


  return $ret_info_array;
}

function WCWHITECOIN__senttoaddres ($wcwhitecoin_settings=false, $deposit_address, $quantity, $api_timeout=10)
{
	if (!$wcwhitecoin_settings){
    $wcwhitecoin_settings = WCWHITECOIN__get_settings();
	}
	$ssl_true = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcssl_active'];
	$rpcuser = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcuser'];
	$rpcpass = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcpass'];
	$rpchost = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpchostaddr'];
	$rpcport = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcport'];
		
	
	$whitecoin = new Whitecoin($rpcuser,$rpcpass,$rpchost,$rpcport);
    if($ssl_true=='yes'){
     $prvkey_whitecoin = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_rpcssl_load'];
     $whitecoin->setSSL($prvkey_whitecoin);
	}
    $response = $whitecoin->sendtoaddress($deposit_address,$quantity);  
	if($response['status']== 'error'){
		 $ret_info_array = array (
      'result'                      => 'error',
      'message'                     => "Error sent Whitecoins to address: ".$response['result'],
      'host_reply_raw'              => "",
      'txid'                     => false,
      );
		}
	if($response['status']== 'success'){
    $ret_info_array = array (
      'result'                      => 'success',
      'message'                     => "",
      'host_reply_raw'              => "",
      'txid'                     => $response['result'],
      );
  }



  return $ret_info_array;
}
//===========================================================================

//===========================================================================
// Returns:
//    success: number of currency units (dollars, etc...) would take to convert to 1 whitecoin, ex: "15.32476".
//    failure: false
//
// $currency_code, one of: USD, AUD, CAD, CHF, CNY, DKK, EUR, GBP, HKD, JPY, NZD, PLN, RUB, SEK, SGD, THB
// $rate_retrieval_method
//		'getfirst' -- pick first successfully retireved rate
//		'getall'   -- retrieve from all possible exchange rate services and then pick the best rate.
//
// $rate_type:
//    'vwap'    	-- weighted average as per: http://en.wikipedia.org/wiki/VWAP
//    'realtime' 	-- Realtime exchange rate
//    'bestrate'  -- maximize number of whitecoins to get for item priced in currency: == min (avg, vwap, sell)
//                 This is useful to ensure maximum whitecoin gain for stores priced in other currencies.
//                 Note: This is the least favorable exchange rate for the store customer.
// $get_ticker_string - true - ticker string of all exchange types for the given currency.

function WCWHITECOIN__get_exchange_rate_whitecoin ($currency_code, $rate_retrieval_method = 'getfirst', $rate_type = 'vwap', $get_ticker_string=false)
{
   if ($currency_code == 'XWC')
      return "1.00";   // 1:1

	$wcwhitecoin_settings = WCWHITECOIN__get_settings ();

	$current_time  = time();
	$cache_hit     = false;
	$requested_cache_method_type = $rate_retrieval_method . '|' . $rate_type;
	$ticker_string = "<span style='color:darkgreen;'>Current Rates for 1 Whitecoin (in {$currency_code})={{{EXCHANGE_RATE}}}</span>";
	$ticker_string_error = "<span style='color:red;background-color:#FFA'>WARNING: Cannot determine exchange rates (for '$currency_code')! {{{ERROR_MESSAGE}}} Make sure your PHP settings are configured properly and your server can (is allowed to) connect to external WEB services via PHP.</span>";


	$this_currency_info = @$wcwhitecoin_settings['exchange_rates'][$currency_code][$requested_cache_method_type];
	if ($this_currency_info && isset($this_currency_info['time-last-checked']))
	{
	  $delta = $current_time - $this_currency_info['time-last-checked'];
	  if ($delta < (@$wcwhitecoin_settings['cache_exchange_rates_for_minutes'] * 60))
	  {

	     // Exchange rates cache hit
	     // Use cached value as it is still fresh.
			if ($get_ticker_string)
	  		return str_replace('{{{EXCHANGE_RATE}}}', $this_currency_info['exchange_rate'], $ticker_string);
	  	else
	  		return $this_currency_info['exchange_rate'];
	  }
	}
   
   //Whitecoin rate
     $whitecoinrate = WCWHITECOIN__get_whitecoin_rate();
	 if(!$whitecoinrate){
		return false;
      }
	  
	$rates = array();


	// bitcoinaverage covers both - vwap and realtime
	  $rates[] = WCWHITECOIN__get_exchange_rate_from_bitcoinaverage($currency_code, $rate_type, $wcwhitecoin_settings, $whitecoinrate);  // Requested vwap, realtime or bestrate
	if ($rates[0])
	{

		// First call succeeded

		if ($rate_type == 'bestrate')
			$rates[] = WCWHITECOIN__get_exchange_rate_from_bitpay ($currency_code, $rate_type, $wcwhitecoin_settings, $whitecoinrate);		   // Requested bestrate

		$rates = array_filter ($rates);

		if (count($rates) && $rates[0])
		{
			$exchange_rate = min($rates);
  		// Save new currency exchange rate info in cache
 			WCWHITECOIN__update_exchange_rate_cache ($currency_code, $requested_cache_method_type, $exchange_rate);
 		}
 		else
 			$exchange_rate = false;
 	}
 	else
 	{

 		// First call failed
		if ($rate_type == 'vwap')
 			$rates[] = WCWHITECOIN__get_exchange_rate_from_bitcoincharts ($currency_code, $rate_type, $wcwhitecoin_settings, $whitecoinrate);
 		else

			$rates[] = WCWHITECOIN__get_exchange_rate_from_bitpay ($currency_code, $rate_type, $wcwhitecoin_settings, $whitecoinrate);
			
		//$rates = array_filter ($rates);
		if (count($rates) && $rates[0])
		{
			$exchange_rate = min($rates);
  		// Save new currency exchange rate info in cache
 			WCWHITECOIN__update_exchange_rate_cache ($currency_code, $requested_cache_method_type, $exchange_rate);
 		}
 		else
 			$exchange_rate = false;

 	}


	if ($get_ticker_string)
	{
		if ($exchange_rate)
			return str_replace('{{{EXCHANGE_RATE}}}', $exchange_rate , $ticker_string);
		else
		{
			$extra_error_message = "";
			$fns = array ('file_get_contents', 'curl_init', 'curl_setopt', 'curl_setopt_array', 'curl_exec');
			$fns = array_filter ($fns, 'WCWHITECOIN__function_not_exists');

			if (count($fns))
				$extra_error_message = "The following PHP functions are disabled on your server: " . implode (", ", $fns) . ".";

			return str_replace('{{{ERROR_MESSAGE}}}', $extra_error_message, $ticker_string_error);
		}
	}
	else
		return $exchange_rate;

}
//===========================================================================

//===========================================================================
function WCWHITECOIN__function_not_exists ($fname) { return !function_exists($fname); }
//===========================================================================

//===========================================================================
function WCWHITECOIN__update_exchange_rate_cache ($currency_code, $requested_cache_method_type, $exchange_rate)
{
  // Save new currency exchange rate info in cache
  $wcwhitecoin_settings = WCWHITECOIN__get_settings ();   // Re-get settings in case other piece updated something while we were pulling exchange rate API's...
  $wcwhitecoin_settings['exchange_rates'][$currency_code][$requested_cache_method_type]['time-last-checked'] = time();
  $wcwhitecoin_settings['exchange_rates'][$currency_code][$requested_cache_method_type]['exchange_rate'] = $exchange_rate;
  WCWHITECOIN__update_settings ($wcwhitecoin_settings);

}
//===========================================================================

//===========================================================================
function WCWHITECOIN__get_whitecoin_rate($source_url='https://bittrex.com/api/v1.1/public/getticker?market=BTC-XWC',$rate='L')
{
 $src = file_get_contents($source_url);
 $obj = json_decode($src);
 if($obj->success===true){
	 if($rate=='L'){
    return $btcwhitecoin = $obj->result->Last;
	}
	else if($rate=='A'){
     return $btcwhitecoin = $obj->result->Ask;
	}
 }else{
	 return false;
	} 
}
//===========================================================================


//===========================================================================
// $rate_type: 'vwap' | 'realtime' | 'bestrate'
function WCWHITECOIN__get_exchange_rate_from_bitcoinaverage ($currency_code, $rate_type, $wcwhitecoin_settings, $whitecoin_rate)
{
	$source_url	=	"https://api.bitcoinaverage.com/ticker/global/{$currency_code}/";
	$result = @WCWHITECOIN__get_contents  ($source_url, false, $wcwhitecoin_settings['exchange_rate_api_timeout_secs']);

	$rate_obj = @json_decode(trim($result), true);
	if (!is_array($rate_obj))
		return false;


	if (@$rate_obj['24h_avg'])
		$rate_24h_avg = @$rate_obj['24h_avg'];
	else if (@$rate_obj['last'] && @$rate_obj['ask'] && @$rate_obj['bid'])
		$rate_24h_avg = ($rate_obj['last'] + $rate_obj['ask'] + $rate_obj['bid']) / 3;
	else
		$rate_24h_avg = @$rate_obj['last'];

	switch ($rate_type)
	{
		case 'vwap'	:				return $rate_24h_avg * $whitecoin_rate;
		case 'realtime'	:		return @$rate_obj['last'] * $whitecoin_rate;
		case 'bestrate'	:
		default:						return min ($rate_24h_avg * $whitecoin_rate, @$rate_obj['last'] * $whitecoin_rate);
	}
}
//===========================================================================

//===========================================================================
// $rate_type: 'vwap' | 'realtime' | 'bestrate'
function WCWHITECOIN__get_exchange_rate_from_bitcoincharts ($currency_code, $rate_type, $wcwhitecoin_settings, $whitecoin_rate)
{
	$source_url	=	"http://api.bitcoincharts.com/v1/weighted_prices.json";
	$result = @WCWHITECOIN__get_contents  ($source_url, false, $wcwhitecoin_settings['exchange_rate_api_timeout_secs']);

	$rate_obj = @json_decode(trim($result), true);


	// Only vwap rate is available
	return @$rate_obj[$currency_code]['24h'] * $whitecoin_rate;
}
//===========================================================================

//===========================================================================
// $rate_type: 'vwap' | 'realtime' | 'bestrate'
function WCWHITECOIN__get_exchange_rate_from_bitpay ($currency_code, $rate_type, $wcwhitecoin_settings, $whitecoin_rate)
{
	$source_url	=	"https://bitpay.com/api/rates";
	$result = @WCWHITECOIN__get_contents  ($source_url, false, $wcwhitecoin_settings['exchange_rate_api_timeout_secs']);

	$rate_objs = @json_decode(trim($result), true);
	if (!is_array($rate_objs))
		return false;

	foreach ($rate_objs as $rate_obj)
	{
		if (@$rate_obj['code'] == $currency_code)
		{


			return @$rate_obj['rate'] * $whitecoin_rate;	// Only realtime rate is available
		}
	}


	return false;
}
//===========================================================================
//===========================================================================
// Credits: http://www.php.net/manual/en/function.mysql-real-escape-string.php#100854
function WCWHITECOIN__safe_string_escape ($str="")
{
   $len=strlen($str);
   $escapeCount=0;
   $targetString='';
   for ($offset=0; $offset<$len; $offset++)
   {
     switch($c=$str{$offset})
     {
         case "'":
         // Escapes this quote only if its not preceded by an unescaped backslash
                 if($escapeCount % 2 == 0) $targetString.="\\";
                 $escapeCount=0;
                 $targetString.=$c;
                 break;
         case '"':
         // Escapes this quote only if its not preceded by an unescaped backslash
                 if($escapeCount % 2 == 0) $targetString.="\\";
                 $escapeCount=0;
                 $targetString.=$c;
                 break;
         case '\\':
                 $escapeCount++;
                 $targetString.=$c;
                 break;
         default:
                 $escapeCount=0;
                 $targetString.=$c;
     }
   }
   return $targetString;
}
//===========================================================================

//===========================================================================
// Syntax:
//    WCWHITECOIN__log_event (__FILE__, __LINE__, "Hi!");
//    WCWHITECOIN__log_event (__FILE__, __LINE__, "Hi!", "/..");
//    WCWHITECOIN__log_event (__FILE__, __LINE__, "Hi!", "", "another_log.php");
function WCWHITECOIN__log_event ($filename, $linenum, $message, $prepend_path="", $log_file_name='__log.php')
{
   $log_filename   = dirname(__FILE__) . $prepend_path . '/' . $log_file_name;
   $logfile_header = "<?php exit(':-)'); ?>\n" . '/* =============== WhitecoinGTW LOG file =============== */' . "\r\n";
   $logfile_tail   = "\r\nEND";

   // Delete too long logfiles.
   //if (@file_exists ($log_filename) && filesize($log_filename)>1000000)
   //   unlink ($log_filename);

   $filename = basename ($filename);

   if (@file_exists ($log_filename))
      {
      // 'r+' non destructive R/W mode.
      $fhandle = @fopen ($log_filename, 'r+');
      if ($fhandle)
         @fseek ($fhandle, -strlen($logfile_tail), SEEK_END);
      }
   else
      {
      $fhandle = @fopen ($log_filename, 'w');
      if ($fhandle)
         @fwrite ($fhandle, $logfile_header);
      }

   if ($fhandle)
      {
      @fwrite ($fhandle, "\r\n// " . $_SERVER['REMOTE_ADDR'] . '(' . $_SERVER['REMOTE_PORT'] . ')' . ' -> ' . date("Y-m-d, G:i:s T") . "|" . WCWHITECOIN_VERSION . "/" . WCWHITECOIN_EDITION . "|$filename($linenum)|: " . $message . $logfile_tail);
      @fclose ($fhandle);
      }
}
//===========================================================================

//===========================================================================

//===========================================================================

//===========================================================================
function WCWHITECOIN__send_email ($email_to, $email_from, $subject, $plain_body)
{
   $message = "
   <html>
   <head>
   <title>$subject</title>
   </head>
   <body>" . $plain_body . "
   </body>
   </html>
   ";

   // To send HTML mail, the Content-type header must be set
   $headers  = 'MIME-Version: 1.0' . "\r\n";
   $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

   // Additional headers
   $headers .= "From: " . $email_from . "\r\n";    //"From: Birthday Reminder <birthday@example.com>" . "\r\n";

   // Mail it
   $ret_code = @mail ($email_to, $subject, $message, $headers);

   return $ret_code;
}
//===========================================================================
//===========================================================================
/*
  Get web page contents with the help of PHP cURL library
   Success => content
   Error   => if ($return_content_on_error == true) $content; else FALSE;
*/
function WCWHITECOIN__get_contents ($url, $return_content_on_error=false, $timeout=60, $user_agent=FALSE)
{
   if (!function_exists('curl_init'))
      {
      return @file_get_contents ($url);
      }

   $ssl_verf=1;
   if ($_SERVER['HTTP_HOST']=='localhost')
    {
      $ssl_verf = 0;
      }

   $options = array(
      CURLOPT_URL            => $url,
      CURLOPT_RETURNTRANSFER => true,     // return web page
      CURLOPT_HEADER         => false,    // don't return headers
      CURLOPT_ENCODING       => "",       // handle compressed
	  CURLOPT_SSL_VERIFYPEER => $ssl_verf,
      CURLOPT_USERAGENT      => $user_agent?$user_agent:urlencode("Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.576.0 Safari/534.12"), // who am i

      CURLOPT_AUTOREFERER    => true,     // set referer on redirect
      CURLOPT_CONNECTTIMEOUT => $timeout,       // timeout on connect
      CURLOPT_TIMEOUT        => $timeout,       // timeout on response in seconds.
      CURLOPT_FOLLOWLOCATION => true,     // follow redirects
      CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
      );

   $ch      = curl_init   ();

   if (function_exists('curl_setopt_array'))
      {
      curl_setopt_array      ($ch, $options);
      }
   else
      {
      // To accomodate older PHP 5.0.x systems
      curl_setopt ($ch, CURLOPT_URL            , $url);
      curl_setopt ($ch, CURLOPT_RETURNTRANSFER , true);     // return web page
      curl_setopt ($ch, CURLOPT_HEADER         , false);    // don't return headers
      curl_setopt ($ch, CURLOPT_ENCODING       , "");       // handle compressed
	  curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER , $ssl_verf);
      curl_setopt ($ch, CURLOPT_USERAGENT      , $user_agent?$user_agent:urlencode("Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.576.0 Safari/534.12")); // who am i
      curl_setopt ($ch, CURLOPT_AUTOREFERER    , true);     // set referer on redirect
      curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT , $timeout);       // timeout on connect
      curl_setopt ($ch, CURLOPT_TIMEOUT        , $timeout);       // timeout on response in seconds.
      curl_setopt ($ch, CURLOPT_FOLLOWLOCATION , true);     // follow redirects
      curl_setopt ($ch, CURLOPT_MAXREDIRS      , 10);       // stop after 10 redirects
      }

   $content = curl_exec   ($ch);
   $err     = curl_errno  ($ch);
   $header  = curl_getinfo($ch);
   // $errmsg  = curl_error  ($ch);

   curl_close             ($ch);

   if (!$err && $header['http_code']==200)
      return trim($content);
   else
   {
      if ($return_content_on_error)
         return trim($content);
      else
         return FALSE;
   }
      
}

//===========================================================================
// Bittrex API
//    
/////// Get deposit address by currency   
 function WCWHITECOIN__bittrex_getdeposit_address ($apikey, $currency="WHITECOIN")
{  
  $url_api = "https://bittrex.com/api/v1.1/account/getdepositaddress?apikey=".$apikey."&currency=".$currency;
    $src = file_get_contents($url_api);
    $obj = json_decode($src);
     if($obj->success===true){
		 
		 $ret_info_array = array (
         'result'                      => 'success',
         'message'                     => "",
         'deposit_address'   => $obj->result->Address,
         );
		 
      return $ret_info_array;
    }else{
		$ret_info_array = array (
         'result'                      => 'error',
         'message'                     => $obj->message,
         'deposit_address'   => "",
         );
	    return $ret_info_array;
	} 
   
  } 
//////Sell Market///////////////////////
 function WCWHITECOIN__bittrex_sellmarket ($apikey, $quantity, $market="BTC-WHITECOIN")
{  
$whitecoinrate = WCWHITECOIN__get_whitecoin_rate('https://bittrex.com/api/v1.1/public/getticker?market=BTC-XWC','A');
	 if($whitecoinrate){
      
  $url_api = "https://bittrex.com/api/v1.1/market/selllimit?apikey=".$apikey."&market=".$market."&quantity=".$quantity."&rate=".$whitecoinrate;
    $src = file_get_contents($url_api);
    $obj = json_decode($src);
     if($obj->success===true){
		 
		 $ret_info_array = array (
         'result'                      => 'success',
         'message'                     => "",
         'resultUuid'   => $obj->result->resultUuid,
         );
		 
      return $ret_info_array;
    }else{
		$ret_info_array = array (
         'result'                      => 'error',
         'message'                     => $obj->message,
         'resultUuid'   => "",
         );
	    return $ret_info_array;
	} 
  }else{
		$ret_info_array = array (
         'result'                      => 'error',
         'message'                     => "Error Getting rates for Whitecoin".$whitecoinrate,
         'resultUuid'   => "",
         );
	    return $ret_info_array;
	} 
  } 
// Get balance Available  
 function WCWHITECOIN__bittrex_getbalance($apikey, $currency="WHITECOIN")
{  
  $url_api = "https://bittrex.com/api/v1.1/account/getbalance?apikey=".$apikey."&currency=".$currency;
    $src = file_get_contents($url_api);
    $obj = json_decode($src);
     if($obj->success===true){
		 
		 $ret_info_array = array (
         'result'                      => 'success',
         'message'                     => "",
         'balance_av'   => $obj->result->Available,
         );
		 
      return $ret_info_array;
    }else{
		$ret_info_array = array (
         'result'                      => 'error',
         'message'                     => $obj->message,
         'balance_av'   => "",
         );
	    return $ret_info_array;
	} 
   
  }
//===========================================================================
