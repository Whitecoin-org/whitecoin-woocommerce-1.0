<?php
/*
Whitecoin Payments for WooCommerce
http://www.whitecoin.info/
*/

//---------------------------------------------------------------------------
// Global definitions
if (!defined('WCWHITECOIN_PLUGIN_NAME'))
  {
  define('WCWHITECOIN_VERSION',           '1.0');

  //-----------------------------------------------
  define('WCWHITECOIN_EDITION',           'Standard');    


  //-----------------------------------------------
  define('WCWHITECOIN_SETTINGS_NAME',     'WCWHITECOIN-Settings');
  define('WCWHITECOIN_PLUGIN_NAME',       'Whitecoin Payments for WooCommerce');   


  // i18n plugin domain for language files
  define('WCWHITECOIN_I18N_DOMAIN',       'wcwhitecoin');

  }
//---------------------------------------------------------------------------

//------------------------------------------
// Load wordpress for POSTback, WebHook and API pages that are called by external services directly.
if (defined('WCWHITECOIN_MUST_LOAD_WP') && !defined('WP_USE_THEMES') && !defined('ABSPATH'))
   {
   $g_blog_dir = preg_replace ('|(/+[^/]+){4}$|', '', str_replace ('\\', '/', __FILE__)); // For love of the art of regex-ing
   define('WP_USE_THEMES', false);
   require_once ($g_blog_dir . '/wp-blog-header.php');

   // Force-elimination of header 404 for non-wordpress pages.
   header ("HTTP/1.1 200 OK");
   header ("Status: 200 OK");

   require_once ($g_blog_dir . '/wp-admin/includes/admin.php');
   }
//------------------------------------------

require_once(dirname(__FILE__) . '/includes/wcwhitecoin_rpclib.php');
require_once (dirname(__FILE__) . '/wcwhitecoin-cron.php');
require_once(dirname(__FILE__) . '/wcwhitecoin-utils.php');
require_once(dirname(__FILE__) . '/wcwhitecoin-admin.php');
require_once(dirname(__FILE__) . '/wcwhitecoin-render-settings.php');
require_once(dirname(__FILE__) . '/wcwhitecoin-whitecoin-gateway.php');

?>