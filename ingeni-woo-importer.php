<?php
/*
Plugin Name: Ingeni Woo Products Importer
Version: 2019.01
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

*/



class IngeniWooImporter {

    function __construct() {
        //require_once ('ingeni-woo-uploader.php');
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


    public function local_debug_log($msg) {
        $upload_dir = wp_upload_dir();
        $outFile = $upload_dir['basedir'];
        if ( is_local() ) {
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




    public function IngeniRunWooImport(  ) {
        try {
            $importCount = 0;
            $errMsg = "";
            $allowedTypes = array("csv","zip");

            $zip_path = "";

            $this->local_debug_log('IngeniRunWooImport starting: '.$_FILES['btn_ingeniwoo_select']);

            if ( $this->ingeni_woo_upload_to_server( $_FILES['btn_ingeniwoo_select'], $allowedTypes, $errMsg, $zip_path )  == 0 ) {
                $this->local_debug_log( 'upload err: '.$errMsg );
            } else {
                $uploadedFile = $errMsg;
                $this->local_debug_log('the file was uploaded: '.$uploadedFile);
            }

            // Read the file and convert it to an associative array of products,
            // using the first row as the file titles
            $csv = array_map('str_getcsv', file($uploadedFile));
            array_walk($csv, function(&$a) use ($csv) {
            $a = array_combine( $csv[0], $a );
            });

            $headers_assoc = array_shift( $csv ); // remove column header
            $headers = array_values($headers_assoc);
            /*
            for ($idx = 0; $idx < count($headers); ++$idx) {
                $headers[$idx] = 'col-'.sprintf('%1$04d',$idx);
            }
            */

            $products = array();
            foreach($csv as $prod) {
                array_push ( $products, array_change_key_case( $prod ) ); // make all of the keys lowercase
            }
            unset ($csv);

            //$this->local_debug_log('out: '.print_r($products,true));


            //$this->local_debug_log('headers:'.print_r($headers,true));


            //
            // Now remap the array using the schema
            //
            $schema_line = file( __DIR__ . '/import-schema.csv');
            $schema = explode(',',$schema_line[0]);
//$this->local_debug_log('exploded: '.print_r($schema,true));
            $schema_count = count($schema);
            $this->local_debug_log( 'schema: ' . print_r($schema,true));
            $this->local_debug_log( 'product count: ' . count($products));
            $this->local_debug_log( 'schema count: ' . $schema_count );
            $this->local_debug_log( 'headers count: ' . count($headers));

            $idx = 0;
            for ($idx = 0; $idx < $schema_count ; ++$idx) {
//$this->local_debug_log('schema row: '.$schema[0][$idx]);
                if ( strlen($schema[$idx] ) > 0) {
                    //foreach ($products as $individual) {
                    for ($prod_idx = 0; $prod_idx < count($products); ++$prod_idx) {
                        //$this->RenameAssocKeys( $individual, strtolower($headers[$idx]), strtolower($schema[$idx]) );
                        //$this->local_debug_log('is now: '. print_r($individual,true));
                        $this->RenameAssocKeys( $products[$prod_idx], strtolower($headers[$idx]), strtolower($schema[$idx]) );
//$this->local_debug_log( 'is now: ' . print_r($products[$prod_idx],true));
                    }
                }
            }
            $this->local_debug_log('***finished loop: '. $idx . ' out of '. $schema_count);
            //$this->local_debug_log('final: '. print_r($products,true));

            //
            // Now create or modify the individual products
            //
            $importCount = 0;
            foreach ($products as $single_product) {
//$this->local_debug_log('importing: '.$single_product['sku']);
//$this->local_debug_log(print_r($single_product,true));

                // Check if there are multiple tags



                if ( strlen($single_product['sku']) > 0 ) {
                    $result = $this->CreateWooProduct( $single_product, $zip_path );
                    if ($result > 0) {
                        $importCount += 1;
                    }
                }
            }
            $this->local_debug_log('Finished. Imported: '. $importCount);


        } catch (Exception $e) {
            $this->local_debug_log('IngeniRunWooImport: '.$e->message);
        }

        // Delete the files that we just uploaded. We don't need them anymore.
        if ( file_exists($uploadedFile) ) {
            unlink($uploadedFile);
        }


        return $importCount;
    }

    private function rrmdir($src) {
        $dir = opendir($src);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                $full = $src . '/' . $file;
                if ( is_dir($full) ) {
                    rrmdir($full);
                }
                else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        rmdir($src);
    }


    private function RenameAssocKeys(&$array, $oldkey, $newkey) {
        //$this->local_debug_log('looking for '.$oldkey.' to change to '.$newkey);
        if ( ($oldkey == $newkey) || ($newkey == '') ) {
            // Nothing to change - get out of here!
            return true;
        }
        if ( array_key_exists( $oldkey, $array ) ) {
            if ( array_key_exists( $newkey, $array ) ) {
                $array[$newkey] .= ', ' . $array[$oldkey];
                //$this->local_debug_log('exists key '.$newkey.' and updated to '.$array[$newkey]);

            } else {
                $array[$newkey] = $array[$oldkey];
                //$this->local_debug_log('found key '.$oldkey.' and changed to '.$newkey.' = '.$array[$newkey]);
            }
            unset( $array[$oldkey] );
            return true;
        }
        return false;
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
                    $attachmentData = wp_generate_attachment_metadata( $attachmentId, $imageFile);
                    //$this->local_debug_log('attachdata:'.print_r($attachmentData,true));

                    // update and return attachment metadata
                    wp_update_attachment_metadata( $attachmentId, $attachmentData );
                
                    // Save the attachment ID
                    array_push( $imageIds, $attachmentId );
                } else {
                    $this->local_debug_log('add_gallery_images: File not found: '.$imageFile);
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


    private function CreateWooProduct( $product, $zip_path ) {
        $post_id = 0;

        set_time_limit(30); // Force the max execution timer to restart

//$this->local_debug_log('working on: '.print_r($product,true));
        // Check if the product category exists
        $prod_cat = 0;
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

        if ( $prod_cat > 0 ) {

            $post_id = $this->get_product_by_sku($product['sku']);
//$this->local_debug_log('post id: '.$post_id);
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
                // Update the existing product
                $existing_prod = array(
                    'ID'           => $post_id,
                    'post_title'   => $product['title'],
                    'post_content' => $product['description'],
                    'post_excerpt' => $this->get_first_sentence($product['description']),
                );

                // Update the post into the database
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
                    wp_set_object_terms($post_id, $product['product_type'], 'product_type');
                    
                    // Set the product tags
                    if (strlen($product['tags']) > 0) {
                        wp_set_object_terms($post_id, explode(',',$product['tags']), 'product_tag');
                    }


                    // Add images
                    if ( strlen($product['image']) > 0) {
                        $this->add_gallery_images( $post_id, $product['image'], $zip_path );
                    }

                    $stock_status = 'outofstock';
                    if ( $product['stock'] > 0 ) {
                        $stock_status = 'instock';
                    }
//$this->local_debug_log('manage stock: '.strtolower($product['manage_stock']));
                    if ( (strtolower($product['manage_stock']) == 'y') || (strtolower($product['manage_stock']) == 'yes') ) {
                        $product['manage_stock'] = 'yes';
                    } else {
                        $product['manage_stock'] = 'no';
                    }

                    update_post_meta( $post_id, '_visibility', 'visible' );
                    update_post_meta( $post_id, '_stock_status', $stock_status);
                    update_post_meta( $post_id, 'total_sales', '0');
                    update_post_meta( $post_id, '_downloadable', 'no');
                    update_post_meta( $post_id, '_virtual', 'no');
                    update_post_meta( $post_id, '_regular_price', $product['price'] );
                    update_post_meta( $post_id, '_sale_price', $product['price'] );
                    update_post_meta( $post_id, '_purchase_note', "" );
                    update_post_meta( $post_id, '_featured', "no" );
                    update_post_meta( $post_id, '_weight', $product['weight'] );
                    update_post_meta( $post_id, '_length', $product['length'] );
                    update_post_meta( $post_id, '_width', $product['width'] );
                    update_post_meta( $post_id, '_height', $product['height'] );
                    update_post_meta( $post_id, '_sku', $product['sku'] );
                    update_post_meta( $post_id, '_product_attributes', array());
                    update_post_meta( $post_id, '_sale_price_dates_from', "" );
                    update_post_meta( $post_id, '_sale_price_dates_to', "" );
                    update_post_meta( $post_id, '_price', $product['price'] );
                    update_post_meta( $post_id, '_sold_individually', "" );
                    update_post_meta( $post_id, '_manage_stock', $product['manage_stock'] );
                    update_post_meta( $post_id, '_backorders', "no" );
                    update_post_meta( $post_id, '_stock', $product['stock'] );


                } catch (Exception $e) {
                    $this->local_debug_log('CreateWooProduct Z: '.$e->message);
                }
            }
        } else {
            $this->local_debug_log('CreateWooProduct: Could not obtain Category ID!');
        }

        return $post_id;
    }


    function ingeni_woo_upload_to_server( $selectedFile, $allowed_types = array("csv", "zip"), &$err_message, &$zip_path ) {
        try {
            $upl_folder = wp_upload_dir();
    
            //$target_file = $target_dir . $selectedFile['name'];
            $target_file = $selectedFile['name'];
            $uploadOk = 1;
            $uploadFileType = strtolower( pathinfo($target_file,PATHINFO_EXTENSION) );
    
            // Check if file already exists
            if ( file_exists( $target_file ) ) {
                $err_message =  "Sorry, file already exists.";
                $uploadOk = 0;
            }
    
            // Check file size
            if ($uploadOk > 0) {
                if ( $selectedFile['size'] > 50000000 ) {
                    $err_message = "Sorry, your file is too large.";
                    $uploadOk = 0;
                }
            }
            
            // Allow certain file formats
            if ($uploadOk > 0) {
                if ( !in_array( $uploadFileType, $allowed_types ) ) {
                    $err_message = "Sorry, that file type is not allowed: ".$uploadFileType;
                    $uploadOk = 0;
                }
            }
    
            // if everything is ok, try to upload file
            if ( $uploadOk > 0 ) {
                $upload = wp_upload_bits($target_file, null, file_get_contents($selectedFile['tmp_name']));
    
                if ($upload['error'] == false) {
                    $err_message =  $upload['file'];
                    $uploadOK = 1;
                } else {
                    $err_message = $upload['error'];
                    $uploadOk = 0;
                }
            }
            $this->local_debug_log('A err msg:' .$uploadOk.' = '.$err_message.' = ' . $upload['url']);

            if ( $uploadOk > 0) {
                $path_parts = pathinfo($upload['file']);
                $this->local_debug_log('path parts='.print_r($path_parts,true));
                if ( strtolower($path_parts['extension']) == "zip" ) {
                    $temp_path = $path_parts['dirname'] . '/' . date('is');
                    $this->local_debug_log('temp dir='.$temp_path);


                    mkdir($temp_path);
                    if ( $this->unzip_upload($upload['file'], $temp_path) > 0 ) {
                        // We have unzipped, so find the csv file;
                        // Just in case, check to see if there has been a folder created
                        // with the same name as the zip file.
                        if ( is_dir( $temp_path . '/' . $path_parts['filename'] ) ) {
                            $temp_path .= '/' . $path_parts['filename'];
                        }

                        $tmp_files = scandir($temp_path);
                        $this->local_debug_log('files='.print_r($tmp_files,true));
                        foreach ($tmp_files as $tmp_file) {
                            if ( strpos(strtolower($tmp_file),'.csv') !== false ) {
                                $err_message = $upload['url'] = $temp_path . '/' . $tmp_file;
                                break;
                            }
                        }
                        $path_parts = pathinfo($err_message);
                        $zip_path = $path_parts['dirname'];
                    }
                }
            }
            $this->local_debug_log('B err msg:' .$uploadOk.' = '.$err_message.' = ' . $upload['url']);

        
        } catch (Exception $e) {
            $this->local_debug_log('ingeni_woo_upload_to_server: '.$e->message);
        }
        // Remove the tmp file
        unset( $selectedFile['tmp_name'] );
        
        return $uploadOk;
    }

    private function unzip_upload ( $uploaded_zip, $path ) {
        $retValue = 0;
        try {
            $zip = new ZipArchive;
            if ($zip->open($uploaded_zip) === TRUE) {
                $zip->extractTo($path);
                $zip->close();

                // Get of the zip file - we're done with it!
                unlink($uploaded_zip);

                $retValue = 1;
            }
        } catch (Exception $e) {
            $this->local_debug_log('unzip_upload: '.$e->message);
        }
        return $retValue;
    }


}



function ingeni_woo_importer_extender() {
	// Init auto-update from GitHub repo
	require 'plugin-update-checker/plugin-update-checker.php';
	$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'https://github.com/BruceMcKinnon/ingeni-woo-importer',
		__FILE__,
		'ingeni-woo-importer'
	);
}
add_action( 'wp_enqueue_scripts', 'ingeni_woo_importer_extender' );



//
// Include the admin functions
//
require_once ('ingeni-woo-importer-admin.php');


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