<?php
/*
Whitecoin Payments for WooCommerce
http://www.whitecoinway.com/
*/

// Include everything
include(dirname(__FILE__) . '/wcwhitecoin-include-all.php');

//===========================================================================
// Global vars.

global $g_wcwhitecoin__plugin_directory_url;
$g_wcwhitecoin__plugin_directory_url = plugins_url ('', __FILE__);

global $g_wcwhitecoin__cron_script_url;
$g_wcwhitecoin__cron_script_url = $g_wcwhitecoin__plugin_directory_url . '/wcwhitecoin-cron.php';

//===========================================================================

//===========================================================================
// Global default settings
global $g_wcwhitecoin__config_defaults;
$g_wcwhitecoin__config_defaults = array (

   // ------- Hidden constants
// 'supported_currencies_arr'             =>  array ('USD', 'AUD', 'CAD', 'CHF', 'CNY', 'DKK', 'EUR', 'GBP', 'HKD', 'JPY', 'NZD', 'PLN', 'RUB', 'SEK', 'SGD', 'THB'), // Not used right now.
   'database_schema_version'              =>  1.2,
   'assigned_address_expires_in_mins'     =>  2*60,   // 4 hours to pay for order and receive necessary number of confirmations.
   'funds_received_value_expires_in_mins' =>  10,		// 'received_funds_checked_at' is fresh (considered to be a valid value) if it was last checked within 'funds_received_value_expires_in_mins' minutes.
   'starting_index_for_new_whitecoin_addresses' =>  '2',    // Generate new addresses for the wallet starting from this index.
   'max_blockchains_api_failures'         =>  '3',    // Return error after this number of sequential failed attempts to retrieve blockchain data.
   'max_unusable_generated_addresses'     =>  '20',   // Return error after this number of unusable (non-empty) whitecoin addresses were 
   //requests.
   'soft_cron_job_schedule_name'          =>  'minutes_1',   // WP cron job frequency
   'delete_expired_unpaid_orders'         =>  '1',   // Automatically delete expired, unpaid orders from WooCommerce->Orders database
   'max_unused_addresses_buffer'          =>  10,     // Do not pre-generate more than these number of unused addresses. Pregeneration is done only by hard cron job or manually at plugin settings.
   'cache_exchange_rates_for_minutes'			=>	5,			// Cache exchange rate for that number of minutes without re-calling exchange rate API's.
// 'soft_cron_max_loops_per_run'					=>	2,			// NOT USED. Check up to this number of assigned whitecoin addresses per soft cron run. Each loop involves number of DB queries as well as API query to blockchain - and this may slow down the site.
   'elists'																=>	array(),

   // ------- General Settings
   'license_key'                          =>  'UNLICENSED',
   'api_key'                              =>  substr(md5(microtime()), -16),
   'delete_db_tables_on_uninstall'        =>  '0',
   'enable_soft_cron_job'                 =>  '1',    // Enable "soft" Wordpress-driven cron jobs.

   // ------- Special settings
   'exchange_rates'                       =>  array('EUR' => array('method|type' => array('time-last-checked' => 0, 'exchange_rate' => 1), 'GBP' => array())),
   );
//===========================================================================

//===========================================================================
function WCWHITECOIN__GetPluginNameVersionEdition($please_donate = true)
{
  $return_data = '<h2 style="border-bottom:1px solid #DDD;padding-bottom:10px;margin-bottom:20px;">' .
            WCWHITECOIN_PLUGIN_NAME . ', version: <span style="color:#EE0000;">' .
            WCWHITECOIN_VERSION. '</span> [<span style="color:#EE0000;background-color:#FFFF77;">&nbsp;' .
            WCWHITECOIN_EDITION . '&nbsp;</span> edition]' .
          '</h2>';


  if ($please_donate)
  {
    $return_data .= '<p style="border:1px solid #890e4e;padding:5px 10px;color:#004400;background-color:#FFF;"><u>Please donate Whitecoin to</u>:&nbsp;&nbsp;<span style="color:#d21577;font-size:110%;font-weight:bold;">WdonAteNr8JFqiNJCgyiA6TRPYdETDc5fV</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p>';
  }

  return $return_data;
}
//===========================================================================

// These are coming from plugin-specific table.
function WCWHITECOIN__get_persistent_settings ($key=false)
{
////// PERSISTENT SETTINGS CURRENTLY UNUSED
return array();
//////
  global $wpdb;

  $persistent_settings_table_name = $wpdb->prefix . 'wcwhitecoin_persistent_settings';
  $sql_query = "SELECT * FROM `$persistent_settings_table_name` WHERE `id` = '1';";

  $row = $wpdb->get_row($sql_query, ARRAY_A);
  if ($row)
  {
    $settings = @unserialize($row['settings']);
    if ($key)
      return $settings[$key];
    else
      return $settings;
  }
  else
    return array();
}
//===========================================================================

//===========================================================================
function WCWHITECOIN__update_persistent_settings ($wcwhitecoin_use_these_settings_array=false)
{
////// PERSISTENT SETTINGS CURRENTLY UNUSED
return;
//////
  global $wpdb;

  $persistent_settings_table_name = $wpdb->prefix . 'wcwhitecoin_persistent_settings';

  if (!$wcwhitecoin_use_these_settings)
    $wcwhitecoin_use_these_settings = array();

  $db_ready_settings = WCWHITECOIN__safe_string_escape (serialize($wcwhitecoin_use_these_settings_array));

  $wpdb->update($persistent_settings_table_name, array('settings' => $db_ready_settings), array('id' => '1'), array('%s'));
}
//===========================================================================

//===========================================================================
// Wipe existing table's contents and recreate first record with all defaults.
function WCWHITECOIN__reset_all_persistent_settings ()
{
////// PERSISTENT SETTINGS CURRENTLY UNUSED
return;
//////

  global $wpdb;
  global $g_wcwhitecoin__config_defaults;

  $persistent_settings_table_name = $wpdb->prefix . 'wcwhitecoin_persistent_settings';

  $initial_settings = WCWHITECOIN__safe_string_escape (serialize($g_wcwhitecoin__config_defaults));

  $query = "TRUNCATE TABLE `$persistent_settings_table_name`;";
  $wpdb->query ($query);

  $query = "INSERT INTO `$persistent_settings_table_name`
      (`id`, `settings`)
        VALUES
      ('1', '$initial_settings');";
  $wpdb->query ($query);
}
//===========================================================================

//===========================================================================
function WCWHITECOIN__get_settings ($key=false)
{
  global   $g_wcwhitecoin__plugin_directory_url;
  global   $g_wcwhitecoin__config_defaults;

  $wcwhitecoin_settings = get_option (WCWHITECOIN_SETTINGS_NAME);
  if (!is_array($wcwhitecoin_settings))
    $wcwhitecoin_settings = array();


  if ($key)
    return (@$wcwhitecoin_settings[$key]);
  else
    return ($wcwhitecoin_settings);
}
//===========================================================================

//===========================================================================
function WCWHITECOIN__update_settings ($wcwhitecoin_use_these_settings=false, $also_update_persistent_settings=false)
{
   if ($wcwhitecoin_use_these_settings)
      {
      if ($also_update_persistent_settings)
        WCWHITECOIN__update_persistent_settings ($wcwhitecoin_use_these_settings);

      update_option (WCWHITECOIN_SETTINGS_NAME, $wcwhitecoin_use_these_settings);
      return;
      }

   global   $g_wcwhitecoin__config_defaults;

   // Load current settings and overwrite them with whatever values are present on submitted form
   $wcwhitecoin_settings = WCWHITECOIN__get_settings();
   foreach ($g_wcwhitecoin__config_defaults as $k=>$v)
      {
      if (isset($_POST[$k]))
         {
         if (!isset($wcwhitecoin_settings[$k]))
            $wcwhitecoin_settings[$k] = ""; // Force set to something.
         WCWHITECOIN__update_individual_wcwhitecoin_setting ($wcwhitecoin_settings[$k], $_POST[$k]);
         }
      // If not in POST - existing will be used.
      }

   //---------------------------------------
   // Validation
   //if ($wcwhitecoin_settings['aff_payout_percents3'] > 90)
   //   $wcwhitecoin_settings['aff_payout_percents3'] = "90";
   //---------------------------------------

  if ($also_update_persistent_settings)
    WCWHITECOIN__update_persistent_settings ($wcwhitecoin_settings);

  update_option (WCWHITECOIN_SETTINGS_NAME, $wcwhitecoin_settings);
}
//===========================================================================

//===========================================================================
// Takes care of recursive updating
function WCWHITECOIN__update_individual_wcwhitecoin_setting (&$wcwhitecoin_current_setting, $wcwhitecoin_new_setting)
{
   if (is_string($wcwhitecoin_new_setting))
      $wcwhitecoin_current_setting = WCWHITECOIN__stripslashes ($wcwhitecoin_new_setting);
   else if (is_array($wcwhitecoin_new_setting))  // Note: new setting may not exist yet in current setting: curr[t5] - not set yet, while new[t5] set.
      {
      // Need to do recursive
      foreach ($wcwhitecoin_new_setting as $k=>$v)
         {
         if (!isset($wcwhitecoin_current_setting[$k]))
            $wcwhitecoin_current_setting[$k] = "";   // If not set yet - force set it to something.
         WCWHITECOIN__update_individual_wcwhitecoin_setting ($wcwhitecoin_current_setting[$k], $v);
         }
      }
   else
      $wcwhitecoin_current_setting = $wcwhitecoin_new_setting;
}
//===========================================================================

//===========================================================================
//
// Reset settings only for one screen
function WCWHITECOIN__reset_partial_settings ($also_reset_persistent_settings=false)
{
   global   $g_wcwhitecoin__config_defaults;

   // Load current settings and overwrite ones that are present on submitted form with defaults
   $wcwhitecoin_settings = WCWHITECOIN__get_settings();

   foreach ($_POST as $k=>$v)
      {
      if (isset($g_wcwhitecoin__config_defaults[$k]))
         {
         if (!isset($wcwhitecoin_settings[$k]))
            $wcwhitecoin_settings[$k] = ""; // Force set to something.
         WCWHITECOIN__update_individual_wcwhitecoin_setting ($wcwhitecoin_settings[$k], $g_wcwhitecoin__config_defaults[$k]);
         }
      }

  update_option (WCWHITECOIN_SETTINGS_NAME, $wcwhitecoin_settings);

  if ($also_reset_persistent_settings)
    WCWHITECOIN__update_persistent_settings ($wcwhitecoin_settings);
}
//===========================================================================

//===========================================================================
function WCWHITECOIN__reset_all_settings ($also_reset_persistent_settings=false)
{
  global   $g_wcwhitecoin__config_defaults;

  update_option (WCWHITECOIN_SETTINGS_NAME, $g_wcwhitecoin__config_defaults);

  if ($also_reset_persistent_settings)
    WCWHITECOIN__reset_all_persistent_settings ();
}
//===========================================================================

//===========================================================================
// Recursively strip slashes from all elements of multi-nested array
function WCWHITECOIN__stripslashes (&$val)
{
   if (is_string($val))
      return (stripslashes($val));
   if (!is_array($val))
      return $val;

   foreach ($val as $k=>$v)
      {
      $val[$k] = WCWHITECOIN__stripslashes ($v);
      }

   return $val;
}
//===========================================================================

//===========================================================================
/*
    ----------------------------------
    : Table 'whitecoin_addresses' :
    ----------------------------------
      status                "unused"      - never been used address with last known zero balance
                            "assigned"    - order was placed and this address was assigned for payment
                            "revalidate"  - assigned/expired, unused or unknown address suddenly got non-zero balance in it. Revalidate it for possible late order payment against meta_data.
                            "used"        - order was placed and this address and payment in full was received. Address will not be used again.
                            "xused"       - address was used (touched with funds) by unknown entity outside of this application. No metadata is present for this address, will not be able to correlated it with any order.
                            "unknown"     - new address was generated but cannot retrieve balance due to blockchain API failure.
*/
function WCWHITECOIN__create_database_tables ($wcwhitecoin_settings)
{
  global $wpdb;

  $wcwhitecoin_settings = WCWHITECOIN__get_settings();
  $must_update_settings = false;

  $whitecoin_addresses_table_name             = $wpdb->prefix . 'wcwhitecoin_whitecoin_addresses';
  $whitecoin_uuid_table_name             = $wpdb->prefix . 'wcwhitecoin_whitecoin_uuid';

  if($wpdb->get_var("SHOW TABLES LIKE '$whitecoin_addresses_table_name'") != $whitecoin_addresses_table_name)
      $b_first_time = true;
  else
      $b_first_time = false;

 //----------------------------------------------------------

  $query = "CREATE TABLE IF NOT EXISTS `$whitecoin_addresses_table_name` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `whitecoin_address` char(36) NOT NULL,
    `whitecoin_account` char(64) NOT NULL DEFAULT '',
    `index_in_wallet` bigint(20) NOT NULL DEFAULT '0',
    `status` char(16)  NOT NULL DEFAULT 'unknown',
    `last_assigned_to_ip` char(16) NOT NULL DEFAULT '0.0.0.0',
    `assigned_at` bigint(20) NOT NULL DEFAULT '0',
    `total_received_funds` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0.00000000',
    `received_funds_checked_at` bigint(20) NOT NULL DEFAULT '0',
    `address_meta` text NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `whitecoin_address` (`whitecoin_address`),
    KEY `index_in_wallet` (`index_in_wallet`),
    KEY `whitecoin_account` (`whitecoin_account`),
    KEY `status` (`status`)
    );";
  $wpdb->query ($query);
  
  $query = "CREATE TABLE IF NOT EXISTS `$whitecoin_uuid_table_name` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `whitecoin_uuid` char(60) NOT NULL,
    `whitecoin_amount` char(60) NOT NULL DEFAULT '',
    `whitecoin_sell_at` date,
    PRIMARY KEY (`id`)
    );";
  $wpdb->query ($query);
  //----------------------------------------------------------

	// upgrade wcwhitecoin_whitecoin_addresses table, add additional indexes
  if (!$b_first_time)
  {
    $version = floatval($wcwhitecoin_settings['database_schema_version']);

    if ($version < 1.1)
    {

      $query = "ALTER TABLE `$whitecoin_addresses_table_name` ADD INDEX `whitecoin_account` (`whitecoin_account` ASC) , ADD INDEX `status` (`status` ASC)";
      $wpdb->query ($query);
      $wcwhitecoin_settings['database_schema_version'] = 1.1;
      $must_update_settings = true;
    }

		if ($version < 1.2)
		{

      $query = "ALTER TABLE `$whitecoin_addresses_table_name` DROP INDEX `index_in_wallet`, ADD INDEX `index_in_wallet` (`index_in_wallet` ASC)";
      $wpdb->query ($query);
      $wcwhitecoin_settings['database_schema_version'] = 1.2;
      $must_update_settings = true;
    }
  }

  if ($must_update_settings)
  {
	  WCWHITECOIN__update_settings ($wcwhitecoin_settings);
	}

  //----------------------------------------------------------
  // Seed DB tables with initial set of data
  /* PERSISTENT SETTINGS CURRENTLY UNUNSED
  if ($b_first_time || !is_array(WCWHITECOIN__get_persistent_settings()))
  {
    // Wipes table and then creates first record and populate it with defaults
    WCWHITECOIN__reset_all_persistent_settings();
  }
  */
   //----------------------------------------------------------
}
//===========================================================================

//===========================================================================
// NOTE: Irreversibly deletes all plugin tables and data
function WCWHITECOIN__delete_database_tables ()
{
  global $wpdb;

  ///$persistent_settings_table_name       = $wpdb->prefix . 'wcwhitecoin_persistent_settings';
  ///$electrum_wallets_table_name          = $wpdb->prefix . 'wcwhitecoin_electrum_wallets';
  $whitecoin_addresses_table_name    = $wpdb->prefix . 'wcwhitecoin_whitecoin_addresses';

  ///$wpdb->query("DROP TABLE IF EXISTS `$persistent_settings_table_name`");
  ///$wpdb->query("DROP TABLE IF EXISTS `$electrum_wallets_table_name`");
  $wpdb->query("DROP TABLE IF EXISTS `$whitecoin_addresses_table_name`");
}
//===========================================================================

