<?php
//
// IngeniWooProductCreator() - Class to create or modify a single Woo product
//
class IngeniWooProductCreator extends WP_Background_Process {
	protected $action = 'ingeniwooimport';

	private $importOK = 0;

	protected function task( $item ) { 
		//$this->local_debug_log('task: '.print_r($item,true));

		$product = $item[0];

		$zipPath = $item[1];

		$this->importOK = $this->CreateWooProduct( $product, $zipPath );

		return false;
	}


	protected function complete() {
		parent::complete();

		$msg = '';

		if ( $this->is_queue_empty() ) {
			// Mae draft anything that was not modified in the last hour
			$this->make_unmodified_draft();

			// Email the report
			$msg = 'All done and complete.';
		} else {
			$msg = 'queue was not processed!!!!';
		}
		$this->local_debug_log($msg);

		// Email the report
		$ingeni_woo_report_email = get_option('ingeni_woo_report_email');
		wp_mail($ingeni_woo_report_email,'Ingeni Woo Import',$msg);

		// Clear the start time
		update_option('ingeni_woo_import_start', '' );

	}


	private function make_unmodified_draft( ) {
		global $wpdb;

		date_default_timezone_set("Australia/Sydney"); 
		$one_hour_ago = date("Y-m-d H:i:s", strtotime("-1 hour") );
		// Get the start time
		$start_time = get_option('ingeni_woo_import_start', $one_hour_ago );

		$table = $wpdb->prefix.'posts';
		$update_sql = 'UPDATE ' . $table .' SET post_status = "draft" WHERE (post_type = "product") AND(post_status = "publish") AND (post_modified < "'.$start_time.'")';
$this->local_debug_log($update_sql);
		$wpdb->query($update_sql);

}




	//
	// Clear out the WP_Async_Request data queue once it has been dispatched
	//
	public function clear_queue() {
		$this->data = array();
	}


	//
	// Misc utility functions
	//
	private function is_local() {
		$local_install = false;
		if ( ($_SERVER['SERVER_NAME']=='localhost') || ($_SERVER['SERVER_NAME']=='dev.local')) {
				$local_install = true;
		}
		return $local_install;
	}

	private function local_debug_log($msg) {
			$upload_dir = wp_upload_dir();
			$outFile = $upload_dir['basedir'];
			if ( $this->is_local() ) {
					$outFile .= '\\';
			} else {
					$outFile .= '/';
			}
			$outFile .= basename(__DIR__).'.txt';
			
			date_default_timezone_set(get_option('timezone_string'));

			// Now write out to the file
			$log_handle = fopen($outFile, "a");
			if ($log_handle !== false) {
					fwrite($log_handle, date("Y-m-d H:i:s").": ".$msg."\r\n");
					fclose($log_handle);
			}
	}	

	private function get_product_by_sku( $sku ) {
			global $wpdb;

			$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );

			if ( !$product_id )
					$product_id = 0;

			return $product_id;
	}


	private function add_gallery_images( $prod_id, $image_file_list, $zip_path ) {
			$retVal = false;

			try {
					// check and return file type
					$images = explode(',',$image_file_list);
//$this->local_debug_log('images:'.print_r($images,true));

					$imageIds = array();

					foreach ( $images as $image ) {
							$image = trim($image);
							$imageFile = $zip_path . '/' . $image;

							if ( file_exists( $imageFile ) ) {
									set_time_limit(30); // Force the max execution timer to restart
									
									$wpFileType = wp_check_filetype($imageFile, null);
	
									// Attachment attributes for file
									$attachment = array(
											'post_mime_type' => $wpFileType['type'],  // file type
											'post_title' => sanitize_file_name($image),  // sanitize and use image name as file name
											'post_content' => '',  // could use the image description here as the content
											'post_status' => 'inherit'
									);
									//$this->local_debug_log('attach:'.print_r($attachment,true));

									// insert and return attachment id
									$attachmentId = wp_insert_attachment( $attachment, $imageFile, $prod_id );
									//$this->local_debug_log('attachid:'.$attachmentId);
	
									// insert and return attachment metadata
									if ( function_exists("wp_generate_attachment_metadata") ) {
										$attachmentData = wp_generate_attachment_metadata( $attachmentId, $imageFile);
										//$this->local_debug_log('attachdata:'.print_r($attachmentData,true));

										// update and return attachment metadata
										wp_update_attachment_metadata( $attachmentId, $attachmentData );
									}
							
									// Save the attachment ID
									array_push( $imageIds, $attachmentId );
							} else {
									//$this->local_debug_log('add_gallery_images: File not found: '.$imageFile);
							}
					}

					if ( count( $imageIds ) > 0 ) {
							// Set the first image as the feature image
							set_post_thumbnail( $prod_id, $imageIds[0] );

							if ( count( $imageIds ) > 1 ) {
									array_shift( $imageIds ); //removes first item of the array (because it's been set as the featured image already)
									update_post_meta( $prod_id, '_product_image_gallery', implode(',',$imageIds ) ); //set the images id's left over after the array shift as the gallery images            
							}
					}
					$retVal = true;

			} catch (Exception $e) {
					$this->local_debug_log('add_gallery_images: '.$e->message);
			}
			return $retVal;
	}


	private function get_first_sentence( $content ) {
		$retVal = $content;

		// Remove H4s
		$clean = preg_replace('#<h4>(.*?)</h4>#', '', $content);
		$clean = wp_strip_all_tags($clean);

		$period = strpos($clean, ".");
		if ($period === false)
				$period = strlen($clean)-1;
		$exclaim = strpos($clean, "!");
		if ($exclaim === false)
				$exclaim = strlen($clean)-1;
		$question = strpos($clean, "?");
		if ($question === false)
				$question = strlen($clean)-1;

		$loc = min( array($period,$exclaim,$question));

		$retVal = substr($clean,0, ($loc+1) );

		return $retVal;
	}

	private function CreateWooProduct( $product, $zip_path ) {
			$post_id = 0;

			set_time_limit(30); // Force the max execution timer to restart

$this->local_debug_log(' CreateWooProductworking on: '.print_r($product['sku'],true));

		try {
			// Check if the product category exists
			$prod_cat = 0;
//$this->local_debug_log('cat: '.$product['category']);
			$prod_cat_obj = get_term_by( 'name', $product['category'], 'product_cat', ARRAY_A) ;

//$this->local_debug_log('cat obj: '.print_r($prod_cat_obj,true));
			// If not, create it now
			if (!$prod_cat_obj) {
//$this->local_debug_log('got to creat prod cat:'.$product['category']);
					$new_cat = wp_insert_term(
							$product['category'],
							'product_cat',
							array(
							'description'=> $product['category']
							)
					);
//$this->local_debug_log('new cat:'.print_r($new_cat,true));
					if ( $new_cat ) {
							$prod_cat = (int)$new_cat['term_id'];
					}
			} else {
					$prod_cat = (int)$prod_cat_obj['term_id'];
			}

			if ( strlen(trim($product['excerpt'])) == 0 ) {
				$product['excerpt'] = $this->get_first_sentence($product['description']);
			}

			if ( $prod_cat > 0 ) {

					$post_id = $this->get_product_by_sku($product['sku']);
//$this->local_debug_log($product['sku'].' post id: '.$post_id);
					if ($post_id < 1) {
							// Now create the product
							try {
									$post = array(
											'post_author' => $user_id,
											'post_content' => $product['description'],
											'post_status' => "publish",
											'post_title' => $product['title'],
											'post_parent' => '',
											'post_type' => "product",
											'post_excerpt' => $this->get_first_sentence($product['description']),
									);

									//Create post
									$post_id = wp_insert_post( $post, $wp_error );
									if( $post_id ) {
											$attach_id = get_post_meta($post->parent_id, "_thumbnail_id", true);
											add_post_meta($post_id, '_thumbnail_id', $attach_id);
									}

									wp_set_object_terms( $post_id, $prod_cat, 'product_cat' );

							} catch (Exception $e) {
									$this->local_debug_log('CreateWooProduct A: '.$e->message);
							}
					} else {
							// Update the existing product - make sure it is published again
							$existing_prod = array(
									'ID'           => $post_id,
									'post_title'   => $product['title'],
									'post_content' => $product['description'],
									'post_excerpt' => $this->get_first_sentence($product['description']),
									'post_status' => "publish",
							);

							// Update the post into the database
							$update_err = "";
							$post_id = wp_update_post( $existing_prod, $update_err );
							if ( is_wp_error($update_err) && ($post_id < 1) ) {
									$err_msg = "";
									$errors = $post_id->get_error_messages();
									foreach ($errors as $error) {
											$err_msg .= $error->get_error_message() . " ";
									}
									$this->local_debug_log('CreateWooProduct M: '.$err_msg);
							}
					}

					//$this->local_debug_log('zip_path: '.$zip_path);

					if ($post_id > 0) {
							try {
									// Set the product type - usually Simple
									if (array_key_exists('product_type',$product)) {
										wp_set_object_terms($post_id, $product['product_type'], 'product_type');
									}

									// Set the product tags
									if (array_key_exists('tags',$product)) {
										if (strlen($product['tags']) > 0) {
	//$this->local_debug_log('   tags: '.$product['tags']);
												if ( substr($product['tags'],0,1) == ',' ) {
													$product['tags'] = substr($product['tags'],1,(strlen($product['tags'])-1));
												}
												if ( substr($product['tags'],(strlen($product['tags'])-1),1) == ',' ) {
													$product['tags'] = substr($product['tags'],0,strlen($product['tags'])-1);
												}
												wp_set_object_terms($post_id, explode(',',$product['tags']), 'product_tag');
										}
									}

									// Add images
									if (array_key_exists('image',$product)) {
										if ( strlen($product['image']) > 0) {
												$this->add_gallery_images( $post_id, $product['image'], $zip_path );
										}
									}

									$stock_status = 'outofstock';
									if (array_key_exists('stock',$product)) {
//$this->local_debug_log(' stock: '.strtolower($product['stock']));										
										if ( $product['stock'] > 0 ) {
												$stock_status = 'instock';
										}
									}
//$this->local_debug_log('stock_status: '.$stock_status);
//$this->local_debug_log('manage stock: '.strtolower($product['manage_stock']));
									$manage_stock = "yes";
									if (array_key_exists('manage_stock',$product)) {
										if ( (strtolower($product['manage_stock']) == 'n') || (strtolower($product['manage_stock']) == 'no') ) {
											$manage_stock  = 'no';
										}
									}


									$this_product = new WC_Product( $post_id );
									
									// The price field is the price actually shown to the customer
									if ( array_key_exists('sale_price',$product) ) {
										$this_product->set_sale_price( $product['sale_price'] );
									
$this->local_debug_log('  set sale price: ' . $product['sale_price']);
									} else {
										$this_product->set_sale_price( '' );
$this->local_debug_log('  clear sale price');
									}
									$this_product->set_price( $product['price'] );
									$this_product->set_regular_price( $product['price'] );
$this->local_debug_log('  set price: ' . $product['price']);

									//$this->update_woo_meta( $post_id, '_regular_price', 'price', $product );
									//$this->update_woo_meta( $post_id, '_sale_price', 'sale_price', $product );


									$this_product->set_catalog_visibility( 'visible' );
									$this_product->set_stock_status( $stock_status );
									$current_sales = $this_product->get_total_sales();
									$this_product->set_total_sales( $current_sales );
									$this_product->set_downloadable( 'no' );
									$this_product->set_virtual( 'no' );
									$this_product->set_purchase_note( '' );
									$this_product->set_featured( 'no' );
									$this_product->set_manage_stock( $manage_stock );
									/*
									update_post_meta( $post_id, '_visibility', 'visible' );
									update_post_meta( $post_id, '_stock_status', $stock_status);
									update_post_meta( $post_id, 'total_sales', '0');
									update_post_meta( $post_id, '_downloadable', 'no');
									update_post_meta( $post_id, '_virtual', 'no');
									update_post_meta( $post_id, '_purchase_note', "" );
									update_post_meta( $post_id, '_featured', "no" );
									update_post_meta( $post_id, '_manage_stock', $manage_stock );
									// Force sale price to be cleared
									update_post_meta( $post_id, '_sale_price', "" );
									*/


									$this_product->set_sku( $product['sku'] );
									$this_product->set_attributes( array() );
									$this_product->set_date_on_sale_from( '' );
									$this_product->set_date_on_sale_to( '' );
									$this_product->set_sold_individually( '' );
									$this_product->set_backorders( 'no' );
/*
									update_post_meta( $post_id, '_sku', $product['sku'] );
									update_post_meta( $post_id, '_product_attributes', array());
									update_post_meta( $post_id, '_sale_price_dates_from', "" );
									update_post_meta( $post_id, '_sale_price_dates_to', "" );
									update_post_meta( $post_id, '_sold_individually', "" );
									update_post_meta( $post_id, '_backorders', "no" );
*/


									if (array_key_exists('weight', $product)) {
										$this_product->set_weight( $product['weight'] );
									}
									if (array_key_exists('length', $product)) {
										$this_product->set_length( $product['length'] );
									}
									if (array_key_exists('width', $product)) {
										$this_product->set_width( $product['width'] );
									}
									if (array_key_exists('height', $product)) {
										$this_product->set_height( $product['height'] );
									}
									if (array_key_exists('stock', $product)) {
										$this_product->set_stock_quantity( floatval($product['stock']) );
									}

/*
									$this->update_woo_meta( $post_id, '_weight', 'weight', $product );
									$this->update_woo_meta( $post_id, '_length', 'length', $product );
									$this->update_woo_meta( $post_id, '_width', 'width', $product );
									$this->update_woo_meta( $post_id, '_height', 'height', $product );
									$this->update_woo_meta( $post_id, '_stock', 'stock', $product );
*/

									
									$this_product->set_date_modified( date('Y-m-d H:i:s') );

									$this_product->save();
$this_product = null;
$my_product = new WC_Product( $post_id );
							
$this->local_debug_log('  prices: ' . $my_product->get_price() .' | Sale:'. $my_product->get_sale_price().' | Regular:'. $my_product->get_regular_price() );
$my_product = null;

if (stripos($product['title'],"6th gear") !== false) {
	$this->local_debug_log(print_r($product,true));
	$this->local_debug_log(print_r($this_product,true));
}



							} catch (Exception $e) {
									$this->local_debug_log('CreateWooProduct Z: '.$e->message);
							}
					}
			} else {
					$this->local_debug_log('CreateWooProduct: Could not obtain Category ID!');
			}
		} catch (Exception $e) {
			$this->local_debug_log('CreateWooProduct ERR: '.$e->message);
		}
			return $post_id;
	}


	private function update_woo_meta( $post_id, $name, $item, &$product_array ) {
		if (array_key_exists($item,$product_array)) {
/*			
if (stripos($name,'_price') !== false) {
	$this->local_debug_log($post_id.'|'.$name.'|'.$product_array[$item]);
}
*/
			update_post_meta( $post_id, $name, $product_array[$item] );
		}
	}

	public function get_queue_count() {
		global $wpdb;

		$count = 0;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';
//$this->local_debug_log('key:'.$key);
		$count = $wpdb->get_var( $wpdb->prepare( "
		SELECT COUNT(*)
		FROM {$table}
		WHERE {$column} LIKE %s
	", $key ) );

		return ( $count );
	}
}
?>