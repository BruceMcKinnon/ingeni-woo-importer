=== Ingeni Woocommerce Importer ===

Contributors: Bruce McKinnon
Tags: woocommerce
Requires at least: 4.8
Tested up to: 5.1.1
Stable tag: 2020.01

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
