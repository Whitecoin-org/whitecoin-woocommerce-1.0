<?php
/*


Plugin Name: Whitecoin Payments for WooCommerce
Plugin URI: http://www.whitecoin.info/
Description: Whitecoin Payments for WooCommerce plugin allows you to accept payments in whitecoin for physical and digital products at your WooCommerce-powered online store.
Version: 1.0
Author: Oizopower
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html

*/


// Include everything
include(dirname(__FILE__) . '/wcwhitecoin-include-all.php');

//---------------------------------------------------------------------------
// Add hooks and filters

// create custom plugin settings menu
add_action( 'admin_menu',                   'WCWHITECOIN_create_menu' );

register_activation_hook(__FILE__,          'WCWHITECOIN_activate');
register_deactivation_hook(__FILE__,        'WCWHITECOIN_deactivate');
register_uninstall_hook(__FILE__,           'WCWHITECOIN_uninstall');

add_filter ('cron_schedules',               'WCWHITECOIN__add_custom_scheduled_intervals');
add_action ('WCWHITECOIN_cron_action',             'WCWHITECOIN_cron_job_worker');     // Multiple functions can be attached to 'WCWHITECOIN_cron_action' action

WCWHITECOIN_set_lang_file();
//---------------------------------------------------------------------------

//===========================================================================
// activating the default values
function WCWHITECOIN_activate()
{
    global  $g_wcwhitecoin__config_defaults;

    $wcwhitecoin_default_options = $g_wcwhitecoin__config_defaults;

    // This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
    $wcwhitecoin_settings = WCWHITECOIN__get_settings ();

    foreach ($wcwhitecoin_settings as $key=>$value)
    	$wcwhitecoin_default_options[$key] = $value;

    update_option (WCWHITECOIN_SETTINGS_NAME, $wcwhitecoin_default_options);

    // Re-get new settings.
    $wcwhitecoin_settings = WCWHITECOIN__get_settings ();

    // Create necessary database tables if not already exists...
    WCWHITECOIN__create_database_tables ($wcwhitecoin_settings);

    //----------------------------------
    // Setup cron jobs

    if ($wcwhitecoin_settings['enable_soft_cron_job'] && !wp_next_scheduled('WCWHITECOIN_cron_action'))
    {
    	$cron_job_schedule_name = strpos($_SERVER['HTTP_HOST'], 'ttt.com')===FALSE ? $wcwhitecoin_settings['soft_cron_job_schedule_name'] : 'seconds_30';
    	wp_schedule_event(time(), $cron_job_schedule_name, 'WCWHITECOIN_cron_action');
    }
    //----------------------------------

}
//---------------------------------------------------------------------------
// Cron Subfunctions
function WCWHITECOIN__add_custom_scheduled_intervals ($schedules)
{
	$schedules['seconds_30']     = array('interval'=>30,     'display'=>__('Once every 30 seconds'));     // For testing only.
	$schedules['minutes_1']      = array('interval'=>1*60,   'display'=>__('Once every 1 minute'));
	$schedules['minutes_2.5']    = array('interval'=>2.5*60, 'display'=>__('Once every 2.5 minutes'));
	$schedules['minutes_5']      = array('interval'=>5*60,   'display'=>__('Once every 5 minutes'));

	return $schedules;
}
//---------------------------------------------------------------------------
//===========================================================================

//===========================================================================
// deactivating
function WCWHITECOIN_deactivate ()
{
    // Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...

   //----------------------------------
   // Clear cron jobs
   wp_clear_scheduled_hook ('WCWHITECOIN_cron_action');
   //----------------------------------
}
//===========================================================================

//===========================================================================
// uninstalling
function WCWHITECOIN_uninstall ()
{
    $wcwhitecoin_settings = WCWHITECOIN__get_settings();

    if ($wcwhitecoin_settings['delete_db_tables_on_uninstall'])
    {
        // delete all settings.
        delete_option(WCWHITECOIN_SETTINGS_NAME);

        // delete all DB tables and data.
        WCWHITECOIN__delete_database_tables ();
    }
}
//===========================================================================

//===========================================================================
function WCWHITECOIN_create_menu()
{

    // create new top-level menu
    // http://www.fileformat.info/info/unicode/char/e3f/index.htm
    add_menu_page (
        __('Woo Whitecoin', WCWHITECOIN_I18N_DOMAIN),                    // Page title
        __('Whitecoin', WCWHITECOIN_I18N_DOMAIN),                        // Menu Title - lower corner of admin menu
        'administrator',                                        // Capability
        'wcwhitecoin-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'WCWHITECOIN__render_general_settings_page',                   // Function
        plugins_url('/images/whitecoin_16x.png', __FILE__)                // Icon URL
        );

    add_submenu_page (
        'wcwhitecoin-settings',                                        // Parent
        __("WooCommerce Whitecoin Payments Gateway", WCWHITECOIN_I18N_DOMAIN),                   // Page title
        __("General Settings", WCWHITECOIN_I18N_DOMAIN),               // Menu Title
        'administrator',                                        // Capability
        'wcwhitecoin-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'WCWHITECOIN__render_general_settings_page'                    // Function
        );
}
//===========================================================================

//===========================================================================
// load language files
function WCWHITECOIN_set_lang_file()
{
    # set the language file
    $currentLocale = get_locale();
    if(!empty($currentLocale))
    {
        $moFile = dirname(__FILE__) . "/lang/" . $currentLocale . ".mo";
        if (@file_exists($moFile) && is_readable($moFile))
        {
            load_textdomain(WCWHITECOIN_I18N_DOMAIN, $moFile);
        }

    }
}
//===========================================================================

