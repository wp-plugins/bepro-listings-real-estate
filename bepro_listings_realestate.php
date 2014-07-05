<?php
/*
Plugin Name: BePro Listings Real Estate
Plugin Script: bepro_listings_realestate.php
Plugin URI: http://www.beprosoftware.com/shop
Description: Everything needed to create a Realestate site. Requires BePro Listings
Version: 1.0.2
License: Commercial
Author: BePro Software Team
Author URI: http://www.beprosoftware.com


Copyright 2012 [Beyond Programs LTD.](http://www.beyondprograms.com/)

*/

if ( !defined( 'ABSPATH' ) ) exit;

class Bepro_listings_realestate{

	/**
	 * Welcome to BePro Listings Realestate, part of the BePro Software collection.
	*/
	 
	//Start
	function __construct() {
		$this->load_constants();
		add_action('wp_head', array($this, 'front_end_header'), 0);
		add_action('admin_init', array($this, 'bepro_listings_realestate_admin_init'));
		add_action("bepro_listings_add_listing", array($this, "update_realestate_listing"));
		add_action("bepro_listings_update_listing", array($this, "update_realestate_listing"));
		add_action('bepro_listings_item_before_details', array($this, 'item_page_realestate') );
		add_action('post_updated', array($this, 'bepro_realestate_admin_save_details') );
		add_action("bepro_listings_admin_tabs", array($this, "admin_tab"), 86);
		add_action("bepro_listings_admin_tab_panels", array($this, "admin_tab_panel"), 86);
		add_action( 'wp_ajax_bepro_ajax_save_realestate_settings', array($this, 'save_realestate_settings') );
		
		
		add_filter("bepro_listings_num_admin_menus", array($this, "realestate_admin_menu_count"));
	}
	
	function load_constants(){
		global $wpdb;
		
		if ( !defined( 'BEPRO_LISTINGS_REALESTATE_TABLE_BASE' ) )
			define( 'BEPRO_LISTINGS_REALESTATE_TABLE_BASE', "bepro_listings_realestate" );
		if ( !defined( 'BEPRO_LISTINGS_REALESTATE_TABLE_TYPES_BASE' ) )
			define( 'BEPRO_LISTINGS_REALESTATE_TABLE_TYPES_BASE', "bepro_listings_realestate_types" );
		if ( !defined( 'BEPRO_LISTINGS_REALESTATE_TABLE' ) )
			define( 'BEPRO_LISTINGS_REALESTATE_TABLE', $wpdb->prefix."bepro_listings_realestate" );
		if ( !defined( 'BEPRO_LISTINGS_REALESTATE_TABLE_TYPES' ) )
			define( 'BEPRO_LISTINGS_REALESTATE_TABLE_TYPES', $wpdb->prefix."bepro_listings_realestate_types" );
	}
	function realestate_admin_menu_count(){
		return $num = $num + 1;
	}
	
	function front_end_header(){
		echo '<link type="text/css" rel="stylesheet" href="'.plugins_url('css/bepro_listings_realestate.css', __FILE__ ).'" >';
	}
	
	//activate
	function bepro_listings_realestate_activate() {
		global $wpdb;  
		
		if (function_exists('is_multisite') && is_multisite()){ 
			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
			foreach($blogids as $blogid_x){
				Bepro_listings_realestate::bepro_listings_realestate_install_table($blogid_x);
			}
		}else{
			Bepro_listings_realestate::bepro_listings_realestate_install_table();
		}
	}
	
	//Setup database for multisite
	function bepro_listings_realestate_install_table($blog_id = false) {
		global $wpdb;
		Bepro_listings_realestate::load_constants();
		//Manage Multi Site
		if($blog_id && ($blog_id != 1)){
			$table_name = $wpdb->prefix.$blog_id."_".BEPRO_LISTINGS_REALESTATE_TABLE_BASE;
			$r_types_table = $wpdb->prefix.$blog_id."_".BEPRO_LISTINGS_REALESTATE_TABLE_TYPES_BASE;
		}else{
			$table_name = $wpdb->prefix.BEPRO_LISTINGS_REALESTATE_TABLE_BASE;
			$r_types_table = $wpdb->prefix.BEPRO_LISTINGS_REALESTATE_TABLE_TYPES_BASE;
		}		
		
 		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'")!=$table_name) {

			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				post_id int(9) NOT NULL,
				num_rooms int(9) DEFAULT NULL,
				num_baths int(9) DEFAULT NULL,
				num_floors int(9) DEFAULT NULL,
				num_parking int(9) DEFAULT NULL,
				status int(9) DEFAULT NULL,
				sq_ft float DEFAULT NULL,
				owner varchar(55) DEFAULT NULL,
				created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY `post_id` (`post_id`)
			);";

			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
			dbDelta($sql);
			
		}
		
		
		if ($wpdb->get_var("SHOW TABLES LIKE '$r_types_table'")!=$r_types_table){
			$sql2 = "CREATE TABLE " . $r_types_table . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				r_status varchar(55) DEFAULT NULL,
				created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id)
			);
			INSERT INTO ".$r_types_table." (r_status) VALUES('For Sale');
			INSERT INTO ".$r_types_table." (r_status) VALUES('For Rent');
			INSERT INTO ".$r_types_table." (r_status) VALUES('Sublet');
			INSERT INTO ".$r_types_table." (r_status) VALUES('Sold');
			";

			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
			dbDelta($sql2);
		}
		
		
		/*load default options if they dont already exist	*/	
		$data = get_option("bepro_listings_realestate");
		if(empty($data["heading"])){
			$data["heading"] = "Amenities";
			//save
			update_option("bepro_listings_realestate", $data);
		}
		
		
	}
	
	function bepro_listings_realestate_admin_init(){
		add_meta_box("bpr_realestate_details_meta", "Realestate Details", array($this,"bpr_realestate_details_meta"), "bepro_listings", "normal", "low");
	}
	
	function bpr_realestate_details_meta($post){
		$this->add_form_realestate_option($post);
	}
	
	function bepro_realestate_admin_save_details($post_id){
		global $wpdb;
		if (!isset($_POST['save_bepro_listing'])) return; 
		if ($parent_id = wp_is_post_revision($post_id)) 
			$post_id = $parent_id;
		
		$post_type = get_post_type( $post_id);
			if($post_type != "bepro_listings")return;	

		$post["post_id"] = $post_id;
		$this->update_realestate_listing($post);
	}
	
	
	//submission form fields
	function add_form_realestate_option($post){
		$info = $this->get_realestate_info($post->ID);
		$statuses = $this->get_realestate_statuses();
		
		$add_form = "";
		$add_form .= "<div style='clear:both'></div>";
		$add_form .= "<div class='bepro_upload_form_realestate'>";
		
		if(!is_admin())
			$add_form .= "<span class='form_heading'>".((!empty($options["heading"]))? $options["heading"]:"Amenities")."</span>";
		
		$add_form .= '<span class="form_label">'.__("# Rooms", "bepro-listings").'</span><input type="text" name="bepro_listings_r_num_rooms" value="'.$info->num_rooms.'"><br />';
		$add_form .= '<span class="form_label">'.__("# Baths", "bepro-listings").'</span><input type="text" name="bepro_listings_r_num_baths" value="'.$info->num_baths.'"><br />';
		$add_form .= '<span class="form_label">'.__("# Floors", "bepro-listings").'</span><input type="text" name="bepro_listings_r_num_floors" value="'.$info->num_floors.'"><br />';
		$add_form .= '<span class="form_label">'.__("# Parking", "bepro-listings").'</span><input type="text" name="bepro_listings_r_num_parking" value="'.$info->num_parking.'"><br />';
		$add_form .= '<span class="form_label">'.__("# sq_ft", "bepro-listings").'</span><input type="text" name="bepro_listings_r_sq_ft" value="'.$info->sq_ft.'"><br />';
		$add_form .= '<span class="form_label">'.__("Listed By", "bepro-listings").'</span><input type="text" name="bepro_listings_r_owner" value="'.$info->owner.'"><br />';
		$add_form .= '<span class="form_label">'.__("Status", "bepro-listings").'</span><select name="bepro_listings_r_status">';
		foreach($statuses as $key => $val){
			$selected = ($info->status == $val)? "selected='selected'":"";
			$add_form .= "<option value='".$val."' $selected>".$key."</option>";
		}
		$add_form .= "</select>";
		$add_form .= "</div>";
		
		echo $add_form;
	}
	
	
	//hook into the bepro listings update feature
	function update_realestate_listing($post){

		global $wpdb;
		$new_info = array();
		$new_info["num_rooms"] = (!empty($_POST["bepro_listings_r_num_rooms"]) && is_numeric($_POST["bepro_listings_r_num_rooms"]))? $_POST["bepro_listings_r_num_rooms"]:"";
		$new_info["num_baths"] = (!empty($_POST["bepro_listings_r_num_baths"]) && is_numeric($_POST["bepro_listings_r_num_baths"]))? $_POST["bepro_listings_r_num_baths"]:"";
		$new_info["num_floors"] = (!empty($_POST["bepro_listings_r_num_floors"]) && is_numeric($_POST["bepro_listings_r_num_floors"]))? $_POST["bepro_listings_r_num_floors"]:"";
		$new_info["num_parking"] = (!empty($_POST["bepro_listings_r_num_parking"]) && is_numeric($_POST["bepro_listings_r_num_parking"]))? $_POST["bepro_listings_r_num_parking"]:"";
		$new_info["status"] = (!empty($_POST["bepro_listings_r_status"]) && is_numeric($_POST["bepro_listings_r_status"]))? $_POST["bepro_listings_r_status"]:"";
		$new_info["sq_ft"] = (!empty($_POST["bepro_listings_r_sq_ft"]) && is_numeric($_POST["bepro_listings_r_sq_ft"]))? $_POST["bepro_listings_r_sq_ft"]:"";
		$new_info["owner"] = (!empty($_POST["bepro_listings_r_owner"]))? addslashes(strip_tags($_POST["bepro_listings_r_owner"])):"";
		
		$check_exists = $wpdb->get_row("SELECT * FROM ".BEPRO_LISTINGS_REALESTATE_TABLE." WHERE post_id=".$post["post_id"]);
		if($check_exists){
			$query_args = "";
			$query_args .= !empty($new_info["num_rooms"])? ",num_rooms='".$new_info["num_rooms"]."'":"";
			$query_args .= !empty($new_info["num_baths"])? ",num_baths='".$new_info["num_baths"]."'":"";
			$query_args .= !empty($new_info["num_floors"])? ",num_floors='".$new_info["num_floors"]."'":"";
			$query_args .= !empty($new_info["num_parking"])? ",num_parking='".$new_info["num_parking"]."'":"";
			$query_args .= !empty($new_info["status"])? ",status='".$new_info["status"]."'":"";
			$query_args .= !empty($new_info["sq_ft"])? ",sq_ft='".$new_info["sq_ft"]."'":"";
			$query_args .= !empty($new_info["owner"])? ",owner='".addslashes(strip_tags($new_info["owner"]))."'":"";
			
			//remove first character because its a coma (,)
			$query_args = substr($query_args, 1);
			
			$wpdb->query("UPDATE ".BEPRO_LISTINGS_REALESTATE_TABLE." SET $query_args WHERE post_id = ".$post["post_id"]);
		}else{
			$wpdb->query("INSERT INTO ".BEPRO_LISTINGS_REALESTATE_TABLE." (post_id, num_rooms, num_baths, num_floors, num_parking, status, sq_ft, owner) VALUES(".$post["post_id"].", '".$new_info["num_rooms"]."','".$new_info["num_bath"]."', '".$new_info["num_floors"]."', '".$new_info["num_parking"]."', '".$new_info["status"]."', '".$new_info["sq_ft"]."', '".$new_info["owner"]."')");
		}	
	}
	
	//show information on item page
	function item_page_realestate(){
		$page_id = get_the_ID();
		$info = $this->get_realestate_info($page_id);
		$options = get_option("bepro_listings_realestate");
		$realestate_section = "<h3>".((!empty($options["heading"]))? $options["heading"]:"Amenities")." : </h3><div class='bepro_listing_realestate_section'>
			<div><span class='item_label'># Bed Rooms</span> - ".(empty($info->num_rooms)?"NA":$info->num_rooms)."</div>
			<div><span class='item_label'># Bath Rooms</span> - ".(empty($info->num_baths)?"NA":$info->num_baths)."</div>
			<div><span class='item_label'># Parking spots</span> - ".(empty($info->num_parking)?"NA":$info->num_parking)."</div>
			<div><span class='item_label'># Floors</span> - ".(empty($info->num_floors)?"NA":$info->num_floors)."</div>
			<div><span class='item_label'>sq feet</span> - ".(empty($info->sq_ft)?"NA":$info->sq_ft)."</div>
			<div><span class='item_label'>Listed By</span> - ".(empty($info->owner)?"NA":$info->owner)."</div>
		</div>";
		echo $realestate_section;
	}
	
	function get_realestate_info($post_id){
		global $wpdb;
		return $wpdb->get_row("SELECT * FROM ".BEPRO_LISTINGS_REALESTATE_TABLE." WHERE post_id=".$post_id);
	
	}
	
	function get_realestate_statuses(){
		global $wpdb;
		$raw_statuses =  $wpdb->get_results("SELECT * FROM ".BEPRO_LISTINGS_REALESTATE_TABLE_TYPES);
		
		$statuses = array();
		foreach($raw_statuses as $status){
			$statuses[$status->r_status] = $status->id;
		}
		return $statuses;
	}
	
	function admin_tab(){
		include(plugin_dir_path( __FILE__ )."admin_tabs/tab-realestate.php");
	}
	function admin_tab_panel(){
		include(plugin_dir_path( __FILE__ )."admin_tabs/realestate.php");
	}
	
	function save_realestate_settings(){
		if(!is_admin()){
			echo 0;
			exit;
		}
		
		$data["heading"] = addslashes(strip_tags($_POST["heading"]));
		if(update_option("bepro_listings_realestate", $data))
			echo 1;
		else
			echo 0;
		
		exit;
	}
}
register_activation_hook( __FILE__, array( 'Bepro_listings_realestate', 'bepro_listings_realestate_activate' ) );
$skyscraper = new Bepro_listings_realestate();	