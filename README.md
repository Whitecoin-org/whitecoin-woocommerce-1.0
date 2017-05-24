#=== Whitecoin Payments for WooCommerce ===  
Contributors: Oizopower, Whitecoin  
Donate link: http://whitecoin.info  
Tags: whitecoin , whitecoin wordpress, plugin , bitcoin plugin, whitecoin payments, accept whitecoin, bitcoins , accept whitecoin , whitecoins  
Requires at least: 3.0.1  
Tested up to: 3.9  
Stable tag: 1.0  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  
  


Whitecoin Payments for WooCommerce is a Wordpress plugin that allows to accept whitecoin at WooCommerce-powered online stores.

== Description ==  
Based in Bitcoin Payments for WooCommerce  
Your online store must use WooCommerce platform (free wordpress plugin).  
Once you installed and activated WooCommerce, you may install and activate Whitecoin Payments for WooCommerce.  


= Benefits =

* Fully automatic operation
* 100% hack secure - by design it is impossible for hacker to steal your XWC even if your whole server and database will be hacked.
* 100% safe against losses - no private keys are required or kept anywhere at your online store server.
* Accept payments in Whitecoin directly into your personal wallet.
* Wallet can stay in another server.
* Accept payment in Whitecoin for physical and digital downloadable products.
* Add Whitecoin payments option to your existing online store with alternative main currency.
* Flexible exchange rate calculations fully managed via administrative settings.
* Zero fees and no commissions for Whitecoin payments processing from any third party.
* Support for many currencies.
* Set main currency of your store in any currency or Whitecoin.
* Automatic conversion to Whitecoin via realtime exchange rate feed and calculations.
* Ability to set exchange rate calculation multiplier to compensate for any possible losses due to bank conversions and funds transfer fees.


== Installation ==  
Before you start, you must configure a RPC Whitecoin Wallet in Windows/Linux or Mac with this settings in whitecoin.conf:  
Example:

rpcuser=username  
rpcpassword=Secretsuperpass  
daemon=1  
server=1  
rpcport=15815  
port=15814  
rpcallowip=127.0.0.1  
rpcssl=1  
addnode=seed1.oizopower.nl  
addnode=seed2.oizopower.nl  
addnode=seed3.oizopower.nl  

If you wanna use a ssl Certificate you must create with this intructions if not skip this step:  

https://en.bitcoin.it/wiki/Enabling_SSL_on_original_client_daemon


1.  Install WooCommerce plugin and configure your store (if you haven't done so already - http://wordpress.org/plugins/woocommerce/).
2.  Install "Whitecoin Payments for WooCommerce" wordpress plugin just like any other Wordpress plugin.
3.  Activate.
4.  Run and setup your wallet.
5.  Click on "Console" tab and run this command (to extend the size of wallet's gap limit): wallet.storage.put('gap_limit',100)
6.  Within your site's Wordpress admin, navigate to:
	    WooCommerce -> Settings -> Checkout -> Whitecoin
7.  Fill:
    Whitecoin RPC host address: RPC/Wallet server address
    Whitecoin RPC Port: RPC por used : Example = 12615
    Whitecoin RPC SSL connect: Check this options if you have ssl enabled in RPC
    Whitecoin RPC SSL connect: Select the path to certificate server
    Whitecoin RPC username: username used in your RPC server
    Whitecoin RPC password: password used in your RPC server
    Whitecoin address Prefix:The prefix for the address labels.The account will be in the form
8.  Press [Save changes]
9. If you do not see any errors - your store is ready for operation and to access payments in Whitecoins!

All supporters will be in whitecoin.info

== Remove plugin ==

1. Deactivate plugin through the 'Plugins' menu in WordPress
2. Delete plugin through the 'Plugins' menu in WordPress


== Supporters ==

*Whitecoin: 

== Changelog ==

= 1.00 =

