<?php
/*
Plugin Name: Ingeni Woo Products Importer
Version: 2020.14
Plugin URI: https://ingeni.net
Author: Bruce McKinnon - ingeni.net
Author URI: https://ingeni.net
Description: Import a CSV containing details of WooCommerce products to created or updated
*/

/*
Copyright (c) 2018 ingeni.net
Released under the GPL license
http://www.gnu.org/licenses/gpl.txt

Disclaimer: 
	Use at your own risk. No warranty expressed or implied is provided.
	This program is free software; you can redistribute it and/or modify 
	it under the terms of the GNU General Public License as published by 
	the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 	See the GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA


Requires : Wordpress 3.x or newer ,PHP 5 +


v2018.01 - Initial release
v2019.01 - Updated to allow multiple 'tags' columns.
v2019.02 - Implements background batch processing for really large imports
v2019.03 - Added UI controls
v2020.01 - Added make_unmodified_draft() to IngeniWooProductCreator
v2020.02 - Updater code was called by the wrong hook. Must be by init().
				 - Updated uperdater clode to v4.9
v2020.03 - IngeniWooImporter() - Now converts both the schema.csv and the import file into UTF8 encoding.
				 - IngeniWooProductCreator() - If updating an expisting product, make sure it is set to Publish (in case it was previous a Draft).
v2020.04 - IngeniWooProductCreator() - When updating or creating a product, force the _sale_price meta to be cleared before it is optionally re-written.
v2020.05 - IngeniWooProductCreator() - Use Woo Product class to set prices for regular and sale prices
v2020.06 - IngeniWooProductCreator() - Extra error trapping
v2020.07 - IngeniWooProductCreator() - Re-factored to utilise WC_Product in place of direct post_meta updates.
v2020.08 - Now support the custom Woo fields for Brand and MPN - supported for SEO markup by Ingeni Woo Product Meta plugin				 
v2020.09 - wp-background-process() - Extra error debugging and error trapping.
				 - CreateWooProduct() - Auto 'out-of-stock' products that have sale price > than the regular price
				 - IngeniRunWooImport() - Decrease batch sizes to 10 products each. Increase timeouts.
v2020.10 - Now ensures all text is imported as UTF-8.
				 - UTF-8 chars will cause problems with DB columns that only support UTF-7, but have improved error handling
				 to ensure the import process continues.
				 - Background batch sizes restored to 20 products per batch
v2020.11 - Can now preserve the title, desc and short desc, and feature status of existing products.
				 - IngeniWooProductCreator() - Fixed an error whereby original prices with currency signs were not correctly being set to float values.
v2020.12 - Rev'ed to updated readme.txt
v2020.13 - IngeniWooProductCreator() - Caught another instance of the currency sign stuffing things up. Added extra trap to make sure $0.00 products
						are set to Out Of Stock.
v2020.14 - IngeniWooProductCreator() - Added the getMoney() function to handle string to float currency conversions. Looks after additional currency formatting characters.
*/




function ingeni_woo_importer_extender() {
	// Init auto-update from GitHub repo
	require 'plugin-update-checker/plugin-update-checker.php';
	$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'https://github.com/BruceMcKinnon/ingeni-woo-importer',
		__FILE__,
		'ingeni-woo-importer'
	);

	
}
add_action( 'init', 'ingeni_woo_importer_extender' );


// Register CSS for the plugin
function ingeni_woo_admin_register_head() {
	$siteurl = get_option('siteurl');
	$url = $siteurl . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/ingeni-woo-importer.css';

	
	echo "<link rel='stylesheet' type='text/css' href='$url' />\n";
}
add_action('admin_head', 'ingeni_woo_admin_register_head' );



//
// Include the admin functions
//
require ('ingeni-woo-importer-admin.php');


//
// Plugin activation/deactivation hooks
//
function ingeni_settings_link($links) { 
  $settings_link = '<a href="tools.php?page=ingeni_woo_importer">Ingeni Woo Importer Settings</a>'; 
  array_push($links, $settings_link); 
  return $links; 
}
$plugin = plugin_basename(__FILE__); 
//add_filter("plugin_action_links_$plugin", 'ingeni_settings_link' );


//
// Plugin registration functions
//
register_activation_hook(__FILE__, 'ingeni_woo_import_activation');
function ingeni_woo_import_activation() {
	try {

	} catch (Exception $e) {
		local_debug_log("ingeni_woo_import_activation(): ".$e->getMessage());
	}

	flush_rewrite_rules( false );
}

register_deactivation_hook( __FILE__, 'ingeni_woo_import_deactivation' );
function ingeni_woo_import_deactivation() {

	flush_rewrite_rules( false );
}

?>