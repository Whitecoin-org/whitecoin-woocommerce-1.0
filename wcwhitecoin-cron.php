<?php
/*
Whitecoin Payments for WooCommerce
http://www.whitecoin.info/
*/


// Include everything
define('WCWHITECOIN_MUST_LOAD_WP',  '1');
include(dirname(__FILE__) . '/wcwhitecoin-include-all.php');

// Cpanel-scheduled cron job call
if (@$_REQUEST['hardcron']=='1')
  WCWHITECOIN_cron_job_worker (true);

//===========================================================================
// '$hardcron' == true if job is ran by Cpanel's cron job.

function WCWHITECOIN_cron_job_worker ($hardcron=false)
{
  global $wpdb;

  $wcwhitecoin_settings ='';
  $wcwhitecoin_settings = WCWHITECOIN__get_settings ();

  // status = "unused", "assigned", "used"
  $whitecoin_addresses_table_name     = $wpdb->prefix . 'wcwhitecoin_whitecoin_addresses';
  $whitecoin_uuid_table_name          = $wpdb->prefix . 'wcwhitecoin_whitecoin_uuid';

  $funds_received_value_expires_in_secs = $wcwhitecoin_settings['funds_received_value_expires_in_mins'] * 60;
  $assigned_address_expires_in_secs     = $wcwhitecoin_settings['assigned_address_expires_in_mins'] * 60;

  //Get numbers of confirmations requiered
  $confirmations = @$wcwhitecoin_settings ['gateway_settings']['whitecoin_confirmations'];

  $clean_address = NULL;
  $current_time = time();

  // Search for completed orders (addresses that received full payments for their orders) ...

  // NULL == not found
  // Retrieve:
  //     'assigned'   - unexpired, with old balances (due for revalidation. Fresh balances and still 'assigned' means no [full] payment received yet)
  //     'revalidate' - all
  //        order results by most recently assigned
  $query =
    "SELECT * FROM `$whitecoin_addresses_table_name`
      WHERE
      (
        (`status`='assigned' AND (('$current_time' - `assigned_at`) < '$assigned_address_expires_in_secs'))
        OR
        (`status`='revalidate')
      )
      AND (('$current_time' - `received_funds_checked_at`) > '$funds_received_value_expires_in_secs')
      ORDER BY `received_funds_checked_at` ASC;"; // Check the ones that haven't been checked for longest time
  $rows_for_balance_check = $wpdb->get_results ($query, ARRAY_A);


  if (is_array($rows_for_balance_check))
  	$count_rows_for_balance_check = count($rows_for_balance_check);
  else
  	$count_rows_for_balance_check = 0;

  if (is_array($rows_for_balance_check))
  {
  	$ran_cycles = 0;
  	foreach ($rows_for_balance_check as $row_for_balance_check)
  	{   
  		$ran_cycles++;	// To limit number of cycles per soft cron job.

		  // Prepare 'address_meta' for use.
		  $address_meta    = WCWHITECOIN_unserialize_address_meta (@$row_for_balance_check['address_meta']);
		  $last_order_info = @$address_meta['orders'][0];

		  $row_id       = $row_for_balance_check['id'];


		  // Retrieve current balance at address.
		  $balance_info_array = WCWHITECOIN__getreceivedbyaddress_info (false, $row_for_balance_check['whitecoin_account'], $confirmations, $wcwhitecoin_settings['blockchain_api_timeout_secs']);
		   
		  if ($balance_info_array['result'] == 'success')
		  {
        $current_time = time();
        $query =
          "UPDATE `$whitecoin_addresses_table_name`
             SET
                `total_received_funds` = '{$balance_info_array['balance']}',
                `received_funds_checked_at`='$current_time'
            WHERE `id`='$row_id';";
        $ret_code = $wpdb->query ($query);

        if ($balance_info_array['balance'] > 0)
        {

          if ($row_for_balance_check['status'] == 'revalidate')
          {
            // Address with suddenly appeared balance. Check if that is matching to previously-placed [likely expired] order
            if (!$last_order_info || !@$last_order_info['order_id'] || !@$balance_info_array['balance'] || !@$last_order_info['order_total'])
            {
              // No proper metadata present. Mark this address as 'xused' (used by unknown entity outside of this application) and be done with it forever.
              $query =
                "UPDATE `$whitecoin_addresses_table_name`
                   SET
                      `status` = 'xused'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
              continue;
            }
            else
            {
              // Metadata for this address is present. Mark this address as 'assigned' and treat it like that further down...
              $query =
                "UPDATE `$whitecoin_addresses_table_name`
                   SET
                      `status` = 'assigned'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
            }
          }

          WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: NOTE: Detected non-zero balance at address: '{$row_for_balance_check['whitecoin_address']}, order ID = '{$last_order_info['order_id']}'. Detected balance ='{$balance_info_array['balance']}'.");
          if ($balance_info_array['balance'] < $last_order_info['order_total'])
          {
            WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: NOTE: balance at address: '{$row_for_balance_check['whitecoin_address']}' (WHITECOIN '{$balance_info_array['balance']}') is not yet sufficient to complete it's order (order ID = '{$last_order_info['order_id']}'). Total required: '{$last_order_info['order_total']}'. Will wait for more funds to arrive...".$wcwhitecoin_settings['funds_received_value_expires_in_mins']."a".$wcwhitecoin_settings['assigned_address_expires_in_mins']);
          }
        }
        else
        {
			WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: NOTE: Detected balance zero at address: '{$row_for_balance_check['whitecoin_address']}, order ID = '{$last_order_info['order_id']}'.");

        }

        // Note: to be perfectly safe against late-paid orders, we need to:
        //	Scan '$address_meta['orders']' for first UNPAID order that is exactly matching amount at address.

		    if ($balance_info_array['balance'] >= $last_order_info['order_total'])
		    {

	        // Last order was fully paid! Complete it...
	        WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: NOTE: Full payment for order ID '{$last_order_info['order_id']}' detected at address: '{$row_for_balance_check['whitecoin_address']}' (WHITECOIN '{$balance_info_array['balance']}'). Total was required for this order: '{$last_order_info['order_total']}'. Processing order ...");

	        // Update order' meta info
	        $address_meta['orders'][0]['paid'] = true;

	        // Process and complete the order within WooCommerce (send confirmation emails, etc...)
	        WCWHITECOIN__process_payment_completed_for_order ($last_order_info['order_id'], $balance_info_array['balance']);

	        // Update address' record
	        $address_meta_serialized = WCWHITECOIN_serialize_address_meta ($address_meta);

	        // Update DB - mark address as 'used'.
	        //
	        $current_time = time();

          // Note: `total_received_funds` and `received_funds_checked_at` are already updated above.
          //
	        $query =
	          "UPDATE `$whitecoin_addresses_table_name`
	             SET
	                `status`='used',
	                `address_meta`='$address_meta_serialized'
	            WHERE `id`='$row_id';";
	        $ret_code = $wpdb->query ($query);
	        WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: SUCCESS: Order ID '{$last_order_info['order_id']}' successfully completed.");

		    }
		  }
		  else
		  {
		    WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance for address: '{$row_for_balance_check['whitecoin_address']}: " . $balance_info_array['message']);
		  }
		  //..//
		}
	}
	
//============================================================================
//**************** Cancelled expired orders***************************************
 $query =
    "SELECT * FROM `$whitecoin_addresses_table_name`
      WHERE
      (
        (`status`='assigned' AND (('$current_time' - `assigned_at`) > '$assigned_address_expires_in_secs'))
        OR
        (`status`='revalidate')
      )
      AND (('$current_time' - `received_funds_checked_at`) > '$funds_received_value_expires_in_secs')
      ORDER BY `received_funds_checked_at` ASC;"; // Check the ones that haven't been checked for longest time
  $rows_for_clean = $wpdb->get_results ($query, ARRAY_A);

  if (is_array($rows_for_clean))
  	$count_rows_for_clean = count($rows_for_clean);
  else
  	$count_rows_for_clean = 0;


  if (is_array($rows_for_clean))
  {
  	$ran_cycles = 0;
  	foreach ($rows_for_clean as $row_for_clean)
  	{
  		$ran_cycles++;	// To limit number of cycles per soft cron job.

		  // Prepare 'address_meta' for use.
		  $address_meta    = WCWHITECOIN_unserialize_address_meta (@$row_for_clean['address_meta']);
		  $last_order_info = @$address_meta['orders'][0];

		  $row_id       = $row_for_clean['id'];


		  // Retrieve current balance at address.
		  $balance_info_array = WCWHITECOIN__getreceivedbyaddress_info (false, $row_for_clean['whitecoin_account'], $confirmations, $wcwhitecoin_settings['blockchain_api_timeout_secs']);
		  if ($balance_info_array['result'] == 'success')
		  {
        // Refresh 'received_funds_checked_at' field
        $current_time = time();
        $query =
          "UPDATE `$whitecoin_addresses_table_name`
             SET
                `total_received_funds` = '{$balance_info_array['balance']}',
                `received_funds_checked_at`='$current_time'
            WHERE `id`='$row_id';";
        $ret_code = $wpdb->query ($query);

        if ($balance_info_array['balance'] > 0)
        {
          if ($row_for_clean['status'] == 'revalidate')
          {
            // Address with suddenly appeared balance. Check if that is matching to previously-placed [likely expired] order
            if (!$last_order_info || !@$last_order_info['order_id'] || !@$balance_info_array['balance'] || !@$last_order_info['order_total'])
            {
              // No proper metadata present. Mark this address as 'xused' (used by unknown entity outside of this application) and be done with it forever.
              $query =
                "UPDATE `$whitecoin_addresses_table_name`
                   SET
                      `status` = 'xused'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
              continue;
            }
            else
            {
              // Metadata for this address is present. Mark this address as 'assigned' and treat it like that further down...
              $query =
                "UPDATE `$whitecoin_addresses_table_name`
                   SET
                      `status` = 'assigned'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
            }
          }

          WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: NOTE: Detected non-zero balance at address: '{$row_for_clean['whitecoin_address']}, order ID = '{$last_order_info['order_id']}'. Detected balance ='{$balance_info_array['balance']}'.");

          if ($balance_info_array['balance'] < $last_order_info['order_total'])
          {
            WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job Cancell: NOTE: balance at address: '{$row_for_clean['whitecoin_address']}' (WHITECOIN '{$balance_info_array['balance']}') is not yet sufficient to complete it's order (order ID = '{$last_order_info['order_id']}'). Total required: '{$last_order_info['order_total']}'.");
          }
        }
        else
        {
			// Process and cancelled the order within WooCommerce
	        WCWHITECOIN__process_cancelled_for_order ($last_order_info['order_id']);

	        // Update address' record
	        $address_meta_serialized = WCWHITECOIN_serialize_address_meta ($address_meta);
		  	
		  // Mark this addres as "cancelled" for cancell order
              $query =
                "UPDATE `$whitecoin_addresses_table_name`
                   SET
                      `status` = 'cancelled'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);	
			

        }

        // Note: to be perfectly safe against late-paid orders, we need to:
        //	Scan '$address_meta['orders']' for first UNPAID order that is exactly matching amount at address.

		    if ($balance_info_array['balance'] >= $last_order_info['order_total'])
		    {

	        // Last order was fully paid! Complete it...
	        WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: NOTE: Full payment for order ID '{$last_order_info['order_id']}' detected at address: '{$row_for_clean['whitecoin_address']}' (WHITECOIN '{$balance_info_array['balance']}'). Total was required for this order: '{$last_order_info['order_total']}'. Processing order ...");

	        // Update order' meta info
	        $address_meta['orders'][0]['paid'] = true;

	        // Process and complete the order within WooCommerce (send confirmation emails, etc...)
	        WCWHITECOIN__process_payment_completed_for_order ($last_order_info['order_id'], $balance_info_array['balance']);

	        // Update address' record
	        $address_meta_serialized = WCWHITECOIN_serialize_address_meta ($address_meta);

	        // Update DB - mark address as 'used'.
	        //
	        $current_time = time();

          // Note: `total_received_funds` and `received_funds_checked_at` are already updated above.
          //
	        $query =
	          "UPDATE `$whitecoin_addresses_table_name`
	             SET
	                `status`='used',
	                `address_meta`='$address_meta_serialized'
	            WHERE `id`='$row_id';";
	        $ret_code = $wpdb->query ($query);
	        WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: SUCCESS: Order ID '{$last_order_info['order_id']}' successfully completed.");

		    }
			else{ //cancell orders if balance not completed
			// Process and cancelled the order within WooCommerce
	        WCWHITECOIN__process_cancelled_for_order ($last_order_info['order_id']);

	        // Update address' record
	        $address_meta_serialized = WCWHITECOIN_serialize_address_meta ($address_meta);
		  	
		  // Mark this addres as "cancelled" for cancell order
              $query =
                "UPDATE `$whitecoin_addresses_table_name`
                   SET
                      `status` = 'cancelled'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);	
				
			}	
		  }
		  else
		  {
		    WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance for address: '{$row_for_clean['whitecoin_address']}: " . $balance_info_array['message']);
		  }
		  //..//
		}
	}
//-----------------------------------------------------------------------------////
//--------------Auto create order selling on Bittrex--------------------------/////
if($wcwhitecoin_settings ['gateway_settings']['whitecoin_bittrex_active']=='yes'){
 // Retrieve current balance at wallet.
		  $balance_info = WCWHITECOIN__getbalancewallet_info($wcwhitecoin_settings, $wcwhitecoin_settings['blockchain_api_timeout_secs']);
		   
		  if ($balance_info['result'] == 'success')
		  { if ($balance_info['balance'] >= $wcwhitecoin_settings ['gateway_settings']['whitecoin_amount_bittrex'] )
		    { 
		      $deposit_address = WCWHITECOIN__bittrex_getdeposit_address(@$wcwhitecoin_settings ['gateway_settings']['whitecoin_apikey_bittrex']);
		       	if ($deposit_address['result'] == 'success')
		  		{ 
				  $txid = WCWHITECOIN__senttoaddres ($wcwhitecoin_settings, $deposit_address['deposit_address'], $balance_info['balance'] - 0.00001);
				  if ($txid['result'] == 'success')
		  			{ 
				 	 WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: Succes withdraw: Withdraw amount '{$balance_info['balance']}' to address: '{$deposit_address['deposit_address']}'. TxID: '{$txid['txid']}'");		
					}else{//senttoaddres  
					WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: Error withdraw: Error execute withdraw to address: '{$deposit_address['deposit_address']}': '{$txid['message']}'.{$balance_info['balance']}");	
				 	 }	//senttoaddres 
				}else{ //deposit_address 
				     WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: Error: Imposible get deposit address to bittrex:'{$deposit_address['message']}'.");	
				}//deposit_address 		
			}//balance is low  
			
	      }else{ //get balance
			WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance for wallet: '{$balance_info['message']}: ");  
			  
		}
		
//Verify for selling order

$balance_avail_bittrex = WCWHITECOIN__bittrex_getbalance($wcwhitecoin_settings ['gateway_settings']['whitecoin_apikey_bittrex']);	
	if ($balance_avail_bittrex['result'] == 'success')
		  { 
		  if ($balance_avail_bittrex['balance_av'] >= $wcwhitecoin_settings ['gateway_settings']['whitecoin_amount_bittrex'] )
		    { 
			 $sell_order = WCWHITECOIN__bittrex_sellmarket ($wcwhitecoin_settings ['gateway_settings']['whitecoin_apikey_bittrex'], $balance_avail_bittrex['balance_av']);
			 
			 if($sell_order['result'] == 'success'){
			  $whitecoin_Uuid_table_name     = $wpdb->prefix . 'wcwhitecoin_whitecoin_Uuid';
			  
			  $uuid = $sell_order['resultUuid'];
			  $amount = $balance_avail_bittrex['balance_av'];
			  
			  $query =
      "INSERT INTO `$whitecoin_uuid_table_name`
      (`id`, `whitecoin_uuid`, `whitecoin_amount`, `whitecoin_sell_at`) VALUES
      ('','$uuid', '$amount', NOW());";
				  
              $ret_code = $wpdb->query ($query);
				 
				WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: Succes SellMarket: resultUuid: '{$sell_order['resultUuid']}' , Amount: '{$balance_avail_bittrex['balance_av']}'. ".var_dump($wcwhitecoin_settings)); 
			 }else{	 //sellorder
			   WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: Warning SellMarket: Cannot place sell order: '{$sell_order['message']}'.");  
			 }//sellorder
		}//balance low
	}	else{ //balance available
		
		WCWHITECOIN__log_event (__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance available: '{$balance_avail_bittrex['message']}: ");
	    }
			  	
}// if bittrex auto order is active		
	

}
//===========================================================================
