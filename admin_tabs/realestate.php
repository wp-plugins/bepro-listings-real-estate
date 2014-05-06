<?php
/**
 * Realestate tab
 *
 * @author 		BePro Listings
 * @package 	bepro_listings_realestate/admin_tabs
 */


 $data = get_option("bepro_listings_realestate");
 ?>

 <div class="panel entry-content" id="tab-realestate">
	<h2>Real Estate Options</h2>
	<div id="bepro_listings_realestate_form_div">
		<form class='admin_addon_form' id="bepro_listings_realestate_form">
			<input type="hidden" name="action" value="bepro_ajax_save_realestate_settings">
			<span class="form_label">Realestate Heading?</span><input type="text" id="heading" name="heading" value="<?php echo $data["heading"]; ?>"/><br />
			<input type="submit" value="Save Realestate Settings">
		</form>
	</div> 
 </div>