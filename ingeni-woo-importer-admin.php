<?php

include_once( ABSPATH . 'wp-admin/includes/image.php' );

require_once ('ingeni-woo-importer-class.php');

$importer = new IngeniWooImporter();

function ingeni_woo_importer_admin_init() {


	//
	// Include the main importer class
	//
	ingeni_woo_importer_admin_scripts();
	ingeni_woo_importer_plugin_options();
}

function ingeni_woo_importer_admin_scripts() {	

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


//https://codex.wordpress.org/AJAX_in_Plugins
//add_action( 'admin_footer', 'ingeni_woo_progress_javascript' ); // Write our JS below here
function ingeni_woo_progress_javascript() { ?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {
		setInterval(fetchIngeniWooProgress,5000);
	});

	function fetchIngeniWooProgress() {
		var data = {
			'action': 'ingeni_woo_progress',
			'progress': 0
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function(response) {
			var info = response;
			if (info) {
				console.log(info);
			}
			jQuery('#ingeni_woo_importer_info').html(info);
		});
	}
	</script>
	<?php
}


//add_action( 'wp_ajax_ingeni_woo_progress', 'ingeni_woo_progress' );
function ingeni_woo_progress() {

	$my_progress = null;

	try {
		//$my_progress = IngeniWooImporter::getInstance()->get_import_progress();
		//$my_progress = $importer->get_import_progress();
//fb_log('progress is: '.$my_progress);
	} catch (Exception $e) {
    $my_progress = $e->getMessage();
	}

	wp_send_json($my_progress);

	wp_die(); // this is required to terminate immediately and return a proper response
}






// Display and save the exporter options panel
function ingeni_woo_importer_plugin_options() {
	global $importer;


	$selected_file = "";

	// Current user must be a Contributor at least.
	if ( !current_user_can( 'edit_posts' ) )  {
		wp_die( __( 'You don\'t have sufficient permissions to access this page.' ) );
	}



	if ( (isset($_POST['ingeni_woo_importer_edit_hidden'])) && ($_POST['ingeni_woo_importer_edit_hidden'] == 'Y') ){
		$errMsg = "";
		
		switch ($_REQUEST['btn_ingeni_woo_importer_submit']) {
			case "Import Now":
				global $importer;

				$import_count = -1;
				$date_start = new DateTime();

				// Set the start time
				date_default_timezone_set("Australia/Sydney"); 
				update_option('ingeni_woo_import_start', date('Y-m-d H:i:s') );


				// Selected file
				if ( $_FILES['btn_ingeniwoo_select']['name'] != '' ) {
					$selected_file = $_FILES['btn_ingeniwoo_select']['name'];
					$tmp_file = $_FILES['btn_ingeniwoo_select']['tmp_name'];
					$size = $_FILES['btn_ingeniwoo_select']['size'];

					$import_count = $importer->IngeniRunWooImport( $selected_file, $tmp_file, $size );
				} else {
					$errMsg = "No filename provided!!";
					$import_count = -1;
				}


				$date_end = new DateTime();
				$diffInSeconds = $date_end->getTimestamp() - $date_start->getTimestamp();


				if ( $import_count >= 0 ) {
					echo('<div class="updated"><p><strong>'.$import_count.' rows queued for background import in '.$diffInSeconds.' secs...</strong></p></div>');
					fb_log('all done: '.$import_count);
				} else {
					echo('<div class="updated"><p><strong>'.$errMsg.'</strong></p></div>');		
				}
				
			break;
				
			case "Save Settings":
				//update_option('pfc_packing_slips_max_rows', $_POST['options_max_rows']);
				update_option('ingeni_woo_skip_first_line', isset($_POST['ingeni_woo_skip_first_line'] ));
				update_option('ingeni_woo_report_email', $_POST['ingeni_woo_report_email'] );
				update_option('ingeni_woo_default_brand', $_POST['ingeni_woo_default_brand'] );
				update_option('ingeni_woo_preserve_desc', isset($_POST['ingeni_woo_preserve_desc'] ));
				update_option('ingeni_woo_draft_older_products', isset($_POST['ingeni_woo_draft_older_products'] ));
				update_option('ingeni_woo_pending_price_ceiling', $_POST['ingeni_woo_pending_price_ceiling'] );

				echo('<div class="updated"><p>Settings saved...</p></div>');

			break;
			
			case "Clear Multis";
				$clear_count = clear_multi();
				echo('<div class="updated"><p>Cleared '.$clear_count.'</p></div>');
			break;
		}
	}

	$ingeni_woo_skip_first_line = get_option('ingeni_woo_skip_first_line');
	$ingeni_woo_preserve_desc = get_option('ingeni_woo_preserve_desc');
	$ingeni_woo_report_email = get_option('ingeni_woo_report_email');
	$ingeni_woo_default_brand = get_option('ingeni_woo_default_brand');
	$ingeni_woo_draft_older_products = get_option('ingeni_woo_draft_older_products');
	$ingeni_woo_pending_price_ceiling = get_option('ingeni_woo_pending_price_ceiling');

	echo('<div class="wrap">');
		echo('<form action="" method="post" enctype="multipart/form-data">'); 
		echo('<input type="hidden" name="ingeni_woo_importer_edit_hidden" value="Y">');

		echo('<table class="form-table woo-importer"><tbody>');

		echo('<tr valign="top">'); 
		echo('<td><input type="file" name="btn_ingeniwoo_select" value="Select"></td>');
		echo('</tr>');

		echo('<tr valign="top">'); 
		echo('<td>Select file:'.$selected_file.'</td>');
		echo('</tr>'); 

		$checked_value = '';
		if ($ingeni_woo_skip_first_line) {
			$checked_value = ' checked'; 
		}
		echo('<tr valign="top"><td><input type="checkbox" id="ingeni_woo_skip_first_line" name="ingeni_woo_skip_first_line" '.$checked_value.' />Skip first line</td></tr>');  

		$checked_value = '';
		if ($ingeni_woo_preserve_desc) {
			$checked_value = ' checked'; 
		}
		echo('<tr valign="top"><td><input type="checkbox" id="ingeni_woo_preserve_desc" name="ingeni_woo_preserve_desc" '.$checked_value.' />Preserve Titles and Descriptions</td></tr>');  
		
		$checked_value = '';
		if ($ingeni_woo_draft_older_products) {
			$checked_value = ' checked'; 
		}
		echo('<tr valign="top"><td><input type="checkbox" id="ingeni_woo_draft_older_products" name="ingeni_woo_draft_older_products" '.$checked_value.' />Set older Products to Draft</td></tr>');  

		if ( !is_numeric($ingeni_woo_pending_price_ceiling) ) {
			$ingeni_woo_pending_price_ceiling = '0.00';
		}
		echo('<tr valign="top"><td>Set to Pending if price less or equal to:</td><td><input id="ingeni_woo_pending_price_ceiling" maxlength="50" size="10" name="ingeni_woo_pending_price_ceiling" value="'.$ingeni_woo_pending_price_ceiling.'" type="text" /></td></tr>');  

		echo('<tr valign="top"><td>Email reports to:</td><td><input id="ingeni_woo_report_email" maxlength="250" size="30" name="ingeni_woo_report_email" value="'.$ingeni_woo_report_email.'" type="text" /></td></tr>');  

		echo('<tr valign="top"><td>Default Product Brand:</td><td><input id="ingeni_woo_default_brand" maxlength="250" size="30" name="ingeni_woo_default_brand" value="'.$ingeni_woo_default_brand.'" type="text" /></td></tr>');  


		// Progress bar holder
		echo('<tr valign="top">'); 
		echo('<td><div id="ingeni_woo_importer_progress" style="width:300px;border:5px solid #EBEDEF;"></div></td>');
		echo('</tr>'); 
		// Progress information
		echo('<tr valign="top">'); 
		echo('<td><div id="ingeni_woo_importer_info" style="width"></div></td>');
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