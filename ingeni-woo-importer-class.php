<?php
require_once plugin_dir_path( __FILE__ ) . 'classes/wp-async-request.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/wp-background-process.php';
require_once ('ingeni-woo-product-creator-class.php');

class IngeniWooImporter {
    //private static $instance;

    public $background_import;
    public $progress;

    public function __construct() {
        $this->background_import = New IngeniWooProductCreator();
    }

    public function get_import_progress() {
        $currProgress = $this->progress;
        return $currProgress;
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


    public function IngeniRunWooImport( $importFile, $tmpFile, $fileSize ) {
        try {

            $importCount = 0;
            $errMsg = "";
            $allowedTypes = array("csv","zip");
            $zip_path = "";

            $default_brand = trim(get_option('ingeni_woo_default_brand'));

            
            //$this->background_import = New IngeniWooProductCreator;


            $this->local_debug_log("import file: ".$importFile);

            
            if ( $this->ingeni_woo_upload_to_server( $importFile, $tmpFile, $fileSize, $allowedTypes, $errMsg, $zip_path )  == 0 ) {
                $this->local_debug_log( 'upload err: '.$errMsg );
            } else {
                $uploadedFile = $errMsg;
                $this->local_debug_log('the file was uploaded: '.$uploadedFile);
            }

            if ( !file_exists($uploadedFile) ) {
                throw new Exception("Import file does not exist!");
            }


            $fileHandle = fopen($uploadedFile, "r");
            if ($fileHandle === FALSE) {
                throw new Exception("Error opening ".$uploadedFile);
            }


            //
            // Now grab the schema
            //
            $schema_line = file( __DIR__ . '/import-schema.csv');
            // Convert to UTF-8
            $schema_line = mb_convert_encoding($schema_line, "UTF-8", "auto");
//$this->local_debug_log('schema line: '.print_r($schema_line,true));
            $schema = explode(',',$schema_line[0]);
//$this->local_debug_log('exploded: '.print_r($schema,true));
            $schema_count = count($schema);

            $importCount = 0;
            $this->progress = 0;
            
            $ingeni_woo_skip_first_line = get_option('ingeni_woo_skip_first_line');
            $ingeni_woo_report_email = get_option('ingeni_woo_report_email');
        
            $maxImport = 0;

            //Jump over the header
            $offset = $ingeni_woo_skip_first_line ;
            if ($offset> 0) {
                $currRow = fgetcsv($fileHandle);
                unset($currRow);
            }
            $row_idx = 0;

            while( !feof($fileHandle) ) {
                set_time_limit(60);

                if ( ($currRow = fgetcsv($fileHandle)) !== FALSE ) {
                    $currRow = mb_convert_encoding($currRow, "UTF-8");
                    if ( ($currRow[0] == "")||($currRow[0] == NULL) ) {
                        fseek($fileHandle,0,SEEK_END);
                        $this->local_debug_log('out of here!');
                    }
//$this->local_debug_log(print_r($currRow,true));

                    $row_idx++;

                    //Do something with the current row
                    $product = array();

                    for ($schema_idx = 0; $schema_idx < $schema_count; $schema_idx++) {
                        if ($schema[$schema_idx] != '') {
                            if ( array_key_exists( $schema[$schema_idx], $product) ) {
                                // An add-on to an existing element
                                $product[$schema[$schema_idx]] = $product[$schema[$schema_idx]] . ',' . mb_convert_encoding($currRow[$schema_idx], "UTF-8");
                            } else {
                                // A new element
                                $product[$schema[$schema_idx]] =  mb_convert_encoding($currRow[$schema_idx], "UTF-8");
                            }
                        }
                    }

                    if ( !array_key_exists('brand',$product) ) {
                        if ($default_brand != '') {
                            $product['brand'] = $default_brand;
                        }
                    }
                    
//$this->local_debug_log('parsed prod: '.strlen($product['sku']).'|'.strlen($product['title']));
//var_dump($product);
                    // Now import it

                    //$product = mb_convert_encoding($product, "UTF-7", "UTF-8");
                    if ( strlen($product['sku']) > 0 ) {
                        $this->background_import->push_to_queue( array($product, $zip_path) );
                        $importCount += 1;
//$this->local_debug_log('push_to_queue: ['.$importCount.'] '.$product['sku'].' | '.$zip_path);
                    }
                    unset($product);
                }


                if ( (($importCount % 20) == 0) && ($importCount > 0) ) {
                    // Now all of the producsts are queued for import, start the process
                    // Do this is batches of 50 products max.
                    //$this->background_import->save()->dispatch();
                    $this->background_import->save();

                    usleep(200000); // Sleep 0.2 sec
                    $this->background_import->clear_queue();
                    set_time_limit(60);
                }


                // Update the progress bar
                $this->progress = $row_idx;
                //$this->local_debug_log('row='.$row_idx.', progress='.$this->get_import_progress());

                if ( ( $row_idx > $maxImport ) && ( $maxImport > 0 ) ) {
                    break;
                }
            }

            // Now all of the producsts are queued for import, start the process
            $this->background_import->save()->dispatch();

            $this->local_debug_log('Finished. Products queued for background import: '. $importCount);
            
            $this->local_debug_log(' in queue: '.$this->background_import->get_queue_count());
           
            set_time_limit(60);



            //Close the file
            fclose($fileHandle);

 
        } catch (Exception $e) {
            $this->local_debug_log('IngeniRunWooImport: '.$e->getMessage());
        }

        // Delete the files that we just uploaded. We don't need them anymore.
        if ( file_exists($uploadedFile) ) {
            unlink($uploadedFile);
        }

//$this->local_debug_log('Return.: '. $importCount);
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




    function ingeni_woo_upload_to_server( $selectedFile, $tmpFile, $fileSize, $allowed_types = array("csv", "zip"), &$err_message, &$zip_path ) {
        try {
            $upl_folder = wp_upload_dir();
    
            //$target_file = $target_dir . $selectedFile['name'];
            //$target_file = $selectedFile['name'];
            $target_file = $selectedFile;
            $uploadOk = 1;
            $uploadFileType = strtolower( pathinfo($target_file,PATHINFO_EXTENSION) );
    
            // Check if file already exists
            if ( file_exists( $target_file ) ) {
                $err_message =  "Sorry, file already exists.";
                $uploadOk = 0;
            }
    
            // Check file size
            if ($uploadOk > 0) {
                if ( $fileSize > 50000000 ) {
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
                $upload = wp_upload_bits($target_file, null, file_get_contents($tmpFile));
    
                if ($upload['error'] == false) {
                    $err_message =  $upload['file'];
                    $uploadOK = 1;
                } else {
                    $err_message = $upload['error'];
                    $uploadOk = 0;
                }
            }
            //$this->local_debug_log('A err msg:' .$uploadOk.' = '.$err_message.' = ' . $upload['url']);

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
            //$this->local_debug_log('B err msg:' .$uploadOk.' = '.$err_message.' = ' . $upload['url']);

        
        } catch (Exception $e) {
            $this->local_debug_log('ingeni_woo_upload_to_server: '.$e->message);
        }
        // Remove the tmp file
        unset( $tmpFile );
        
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
?>