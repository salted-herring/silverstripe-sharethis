<?php
/**
 *@author: nicolaas[at]sunnysideup.co.nz
 *@description: manages social networking data objects
 **/

class SocialNetworkingModelAdmin extends ModelAdmin {

	public static $managed_models = array(
		"SocialNetworkingLinksDataObject",
		"ShareThisDataObject",
		"FacebookFeed_Page",
		"FacebookFeed_Item",
		"MyTwitterData"
	);

	public static $url_segment = 'social';

	public static $menu_title = 'Social Media';

}
