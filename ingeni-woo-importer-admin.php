<?php


function ingeni_woo_importer_admin_init() {
	// Register the jQuery date/time picker control
	//wp_register_script( 'jquery.simple-dtpicker', plugins_url( 'jquery.simple-dtpicker.js', __FILE__ ) );
	//wp_register_style('jquery-simple-dtpicker-css',  plugins_url( 'jquery.simple-dtpicker.css', __FILE__ ) );
	
	ingeni_woo_importer_admin_scripts();
	ingeni_woo_importer_plugin_options();
}

function ingeni_woo_importer_admin_scripts() {	
	// Set up the jQuery date/time picker control
	//wp_enqueue_script( 'jquery.simple-dtpicker' );
	//wp_enqueue_style( 'jquery-simple-dtpicker-css' );
}



/*** Set up the custom dashboard widgets ***/
add_action( 'wp_dashboard_setup', 'ingeni_woo_importer_dashboard_widgets' );
function ingeni_woo_importer_dashboard_widgets() {
	wp_add_dashboard_widget(
                 'ingeni_woo_importer_admin_init',
                 'Ingeni Woo Product Importer',
                 'ingeni_woo_importer_admin_init'
        );	
}








// Display and save the exporter options panel
function ingeni_woo_importer_plugin_options() {

	$selected_file = "";


	// Current user must be a Contributor at least.
	if ( !current_user_can( 'edit_posts' ) )  {
		wp_die( __( 'You don\'t have sufficient permissions to access this page.' ) );
	}

	if (class_exists('IngeniWooImporter')) {
    $importer = new IngeniWooImporter();
	}


	if ( (isset($_POST['ingeni_woo_importer_edit_hidden'])) && ($_POST['ingeni_woo_importer_edit_hidden'] == 'Y') ){
		$errMsg = "";
		
		switch ($_REQUEST['btn_ingeni_woo_importer_submit']) {
			case "Import Now":
				$importer->local_debug_log('files: '.print_r($_FILES,true));
				// Selected file
				if ( $_FILES['btn_ingeniwoo_select']['name'] != '' ) {
					$selected_file = $_FILES['btn_ingeniwoo_select']['file'];
				}

				
				$import_count = $importer->IngeniRunWooImport();
				
				if ( $import_count >= 0 ) {
					
					echo('<div class="updated"><p><strong>'.$import_count.' rows imported...</strong></p></div>');
				} else {
					echo('<div class="updated"><p><strong>'.$errMsg.'</strong></p></div>');		
				}
			break;
				
			case "Save Settings":
				//update_option('pfc_packing_slips_max_rows', $_POST['options_max_rows']);
				
				echo('<div class="updated"><p>Settings saved...</p></div>');
			break;
			
			case "Clear Multis";
				$clear_count = clear_multi();
				echo('<div class="updated"><p>Cleared '.$clear_count.'</p></div>');
			break;
		}

	}





	echo('<div class="wrap">');
		echo('<form action="" method="post" enctype="multipart/form-data">'); 
		echo('<input type="hidden" name="ingeni_woo_importer_edit_hidden" value="Y">');

		echo('<table class="form-table"><tbody>');

		echo('<tr valign="top">'); 
		echo('<td><input type="file" name="btn_ingeniwoo_select" value="Select"></td>');
		echo('</tr>');

		echo('<tr valign="top">'); 
		echo('<td>Select file:'.$selected_file.'</td>');
		echo('</tr>'); 

		echo('</tbody></table><br/>');

		echo('<input type="submit" name="btn_ingeni_woo_importer_submit" value="Save Settings">');
		echo('<input type="submit" name="btn_ingeni_woo_importer_submit" value="Import Now">');
		
		echo('</form>');

		
		if (is_local()) {
			echo('<p style="color:red;" >Local Install!!!</p>');
		} else {
			echo('<p style="color:green;" >Public Install</p>');			
		}
	echo('</div>');

}


?>