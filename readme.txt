=== Ingeni Woocommerce Importer ===

Contributors: Bruce McKinnon
Tags: woocommerce
Requires at least: 4.8
Tested up to: 5.1.1
Stable tag: 2020.12

Allows Woocommerce products to be created or updated from a CSV. Uses a CSV schema file to map feilds supplied in the import CSV to WooCommerce product fields. 



== Description ==

* - Used in conjunction with Woocommerce.

* - Uses a side-car schema CSV to map columns in the import file to Woocommerce product fields.

* - Allows both creation of new products and updating of existing products.




== Installation ==

1. Upload the 'ingeni-woo-importer’ folder to the '/wp-content/plugins/' directory.

2. Activate the plugin through the 'Plugins' menu in WordPress.



== Frequently Asked Questions ==


++ How do I start the import? ++

The plugin provides a Wordpress Dashboard widget.




== Changelog ==

v2018.01 - Initial version.

v2019.01 - Now allows multiple columns in the import file to use the ‘tags’ filed name. Multiple tags will be created with the product.

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
- UTF-8 chars will cause problems with DB columns that only support UTF-7, but have improved error handling to ensure the import process continues.
- Background batch sizes restored to 20 products per batch

v2020.11 - Can now preserve the title, desc and short desc, and feature status of existing products.
- IngeniWooProductCreator() - Fixed an error whereby original prices with currency signs were not correctly being set to float values.

v2020.12 - Rev'ed to updated readme.txt

