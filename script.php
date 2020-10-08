<?php

header('Content-Type: text/html; charset=utf-8');
// Include wp-load.php
require_once('../wp-load.php');

// Include image.php
require_once(ABSPATH . 'wp-admin/includes/image.php');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
// CSV url
$spreadsheet_url="https://kircsv.302.de/Web-Products-Upload.csv";

if(!ini_set('default_socket_timeout', 10000)) echo "<!-- unable to change socket timeout -->";

$spreadsheet_data = array();
if (($handle = fopen($spreadsheet_url, "r")) !== FALSE) { // reading sheet
    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
        $spreadsheet_data[] = $data;
    }
    /*echo "<pre>";
    print_r($spreadsheet_data);
    exit;*/
    fclose($handle);
}


//get products from woocommerce
	$woo_products = array();
	$args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
    );
    $loop = new WP_Query($args);
    if($loop->have_posts()){
	    while($loop->have_posts()) : $loop->the_post();
	        global $product;
	        $woo_product_id  = $product->get_id();
	        $woo_product_sku = $product->get_sku();
	        $woo_products[$woo_product_sku] = $woo_product_id;
	    endwhile;
	}
    wp_reset_query();
$sheet_products = array();
$headers = array();
foreach ($spreadsheet_data as $key => $value) {
	if ($key == 0) {
		$headers =  $value;
	}else{

	  	if(!empty($value[1])){
	  		$product_mcat	= $value[0]; // Main category
			$product_name 	= $value[1]; // Product Name
			$product_pcat 	= $value[2]; // Parent category
			$product_scat	= $value[3]; // sub category
			$product_sku 	= $value[4]; // Item number can be null but can not be duplicate
			$manufacturer	= $value[5]; // Attribute (manufacturer)
			if(isset($value[6]) && !empty($value[6])){
				$product_model	= $value[6]; // Operating hours
			}else{
				$product_model	= ""; // Operating hours
			}
			if(isset($value[7]) && !empty($value[7])){
				$construction_year	= $value[7]; // Operating hours
			}else{
				$construction_year	= ""; // Operating hours
			}
			if(isset($value[8]) && !empty($value[8])) {
				$product_hour	= $value[8]; // Operating hours
			}else{
				$product_hour	= ""; // Operating hours
			}
			$product_desc	= $value[9]; // Description
			$product_price	= $value[10]; //Price
			$product_price2	= $value[11]; // Price2

			if(isset($value[18]) && !empty($value[18])){
				$newsletter	= $value[18]; 
			}else{
				$newsletter	= 0;
			}

			if (isset($value[17]) && !empty($value[17])) {
				$sold	= $value[17]; 
			}else{
				$sold	= 0;
			}
			$term 		= '';
			$m_term_id 	= 0;
			$p_term_id 	= 0;
			$s_term_id	= 0;
			$term = '';
			if(!empty($product_mcat)){
				$term = get_term_by('name', $product_mcat, 'maschinentyp_website');
				if(empty($term)){
					$m_term_id = wp_insert_term($product_mcat, 'maschinentyp_website');// insetrting new category
					$term = get_term_by('term_id', $m_term_id, 'maschinentyp_website');
				}
			}
			$term1 = '';
			if(!empty($product_pcat)){
				$term1 = get_term_by('name', $product_pcat, 'product_cat');
				if(empty($term1)){
					$p_term_id = wp_insert_term($product_pcat,'product_cat');
					$term1 = get_term_by('term_id', $p_term_id, 'product_cat');			
				}
			}
			$term2 = '';
			if(!empty($product_scat)){
				$term2 = get_term_by('name', $product_scat, 'product_cat');
				if(empty($term2)){
					$s_term_id = wp_insert_term(
						$product_scat,
						'product_cat',
						array(
							'slug' => str_replace(" ", "-",strtolower($product_scat)), 
							'parent'=> term_exists( $product_pcat, 'product_cat' )['term_id']
						)
					);
					$term2 = get_term_by('term_id', $s_term_id, 'product_cat');
				}
			}
			
			$product_id = wc_get_product_id_by_sku( $product_sku );
			$product 	= wc_get_product( $product_id );
			


			// if product exists in sheet and database then update it
			if($product){
				// if exist
			}else{
				$product = new WC_Product();
				$product->set_sku($product_sku); //can be blank in case you don't have sku, but You can't add duplicat
				$product_id = $product->save(); // get new created Product ID
			}
			$product->set_name($product_name); //set product name/title
			$product->set_status("publish");  // can be publish,draft or any wordpress post status
			$product->set_catalog_visibility('visible'); // add the product visibility status
			$product->set_manage_stock(false); // true or false
			$product->set_reviews_allowed(true); // true or false
			$product->set_sold_individually(true); // true or false
			$product->set_stock_status("instock");
			$product->set_description($product_desc); // set product descriptoin
			$product->set_price($product_price); // set product price
			$product->set_regular_price($product_price);
			$product->save(); 

			$term_ids = [$term1->term_id, $term2->term_id ];
			wp_set_object_terms($product_id, $term_ids, 'product_cat'); // Set product category
			wp_set_object_terms($product_id, $term->term_id, 'maschinentyp_website'); // Set product main category
			update_post_meta( $product_id,'product_hours', $product_hour); // set Operating hours
			update_post_meta( $product_id,'model', $product_model); // set model
			update_post_meta( $product_id,'construction_year', $construction_year); // set construction year
			update_post_meta( $product_id,'newsletter', $newsletter); // is in newsletter or not
			update_post_meta( $product_id,'sold', $sold); // is sold or not
			update_post_meta( $product_id,'manufacturer', $manufacturer); // set manufacturer
			$product->save(); 

			$gallery_images = array();
			$product_image = '';
			$feature_image_index = 0;

			if(isset($value[16]) && !empty($value[16]))  {
				$product_image	= $value[16];
				$feature_image_index = 16;
				$gallery_images[$feature_image_index] = $product_image;
			}
			if(isset($value[15]) && !empty($value[15]))  {
				$product_image	= $value[15];
				$feature_image_index = 15;
				$gallery_images[$feature_image_index] = $product_image;
			}
			if(isset($value[14]) && !empty($value[14])) {
				$product_image	= $value[14];
				$feature_image_index = 14;
				$gallery_images[$feature_image_index] = $product_image;
			}
			if(isset($value[13]) && !empty($value[13])) {
				$product_image	= $value[13];
				$feature_image_index = 13;
				$gallery_images[$feature_image_index] = $product_image;
			}
			if(isset($value[12]) && !empty($value[12])) {
				$product_image	= $value[12];
				$feature_image_index = 12;
				$gallery_images[$feature_image_index] = $product_image;
			}
			unset($gallery_images[$feature_image_index]);

			// Example for image data array to insert/update featured image
			$product_image_array = array( 'img_url'=>$product_image, 'width'=>693, 'height'=>960 ); 
			
			create_image($product_image_array,$product_id);
			if(!empty($gallery_images)){
				foreach ($gallery_images as $index_image => $gallery_image) {
					// Example for image data array to insert/update gallery
					if(!empty($gallery_image)){
						$gallery_image_array = array( 'url'=>$gallery_image, 'width'=>693, 'height'=>960 );
						create_image($gallery_image_array,$product_id);
					}
				}
			}

			$sheet_products[$product_sku] = $product_id;
			
		}
		
	}
}
echo "Trash Start<pre>";
print_r($sheet_products);
print_r($woo_products);
echo "</pre>";
if(!empty($woo_products)){
	foreach($woo_products as $woo_product_sku => $woo_product_id){
		if(isset($sheet_products[$woo_product_sku])){
			
		}else{
			wp_trash_post($woo_product_id);
		}
	}
}

function create_image($product_image,$product_id){
	if(!empty($product_image)){
		if(array_key_exists('img_url', $product_image)){ // Featured image
			update_post_meta( $product_id, '_knawatfibu_url', $product_image );
		}else{ 
			$update = true;// Gallery image
			$attach_id_array = get_post_meta($product_id, '_knawatfibu_wcgallary', true);
			if($attach_id_array == false || empty($attach_id_array)){
				$attach_id_array = array();
			}
				if(!in_array($product_image, $attach_id_array)){
					foreach($attach_id_array as $attach_id_key => $attach_id_value){
						if(!empty($attach_id_value)){
							if(isset($attach_id_value['url']) && !empty($attach_id_value['url'])){
								if($product_image == $attach_id_value['url']){
									$update = false;
								}
							}
						}
					}
					if($update){
						$attach_id_array[] = $product_image;
						update_post_meta( $product_id, '_knawatfibu_wcgallary', $attach_id_array );
					}
				}
		} 
	}

				/*
				$image_url        = $product_image; // Define the image URL here
			    $image_name       = 'wp_image.png';
			    $upload_dir       = wp_upload_dir(); // Set upload folder
			    $image_data       = file_get_contents($image_url); // Get image data
			    $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
			    $filename         = basename( $unique_file_name ); // Create image file name
			    // Check folder permission and define file location
			    if( wp_mkdir_p( $upload_dir['path'] ) ) {
			        $file = $upload_dir['path'] . '/' . $filename;
			    } else {
			        $file = $upload_dir['basedir'] . '/' . $filename;
			    }

			    // Create the image  file on the server
			    file_put_contents( $file, $image_data );
			    // Check image file type
			    $wp_filetype = wp_check_filetype( $filename, null );
			    // Set attachment data
			    $attachment = array(
			        'post_mime_type' => $wp_filetype['type'],
			        'post_title'     => sanitize_file_name( $filename ),
			        'post_content'   => '',
			        'post_status'    => 'inherit'
			    );
			    // Create the attachment
			    $attach_id = wp_insert_attachment( $attachment, $file, $product_id);
			    
			    // Define attachment metadata
			    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
			    // Assign metadata to attachment
			    wp_update_attachment_metadata( $attach_id, $attach_data);
			    set_post_thumbnail($product_id, $attach_id ); // set product image
			    return $attach_id;
			    */
//}	
//return false;
}