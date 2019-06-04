<?php
function ingeni_woo_upload_to_server( $selectedFile, $allowed_types = array("csv"), &$err_message ) {
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
			if ( $selectedFile['size'] > 500000 ) {
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
	$this->local_debug_log('err msg:' .$uploadOk.' = '.$err_message.' = ' . $upload['url']);
	
	} catch (Exception $e) {
		$this->local_debug_log('ingeni_woo_upload_to_server: '.$e->message);
	}
	// Remove the tmp file
	unset( $selectedFile['tmp_name'] );
	
	return $uploadOk;
}
?>