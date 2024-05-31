<?php

namespace Includes\baseClasses;

use Includes\settings\SVSettings;

class SVActivate extends SVBase
{

	public static function activate()
	{
		// array of dependent plugins [ plugin-name as key => (bool)is_external as value ]
		$plugins = [
			'jwt-authentication-for-wp-rest-api' 	=> false,
			'buddypress' 							=> false,
			'bbpress' 								=> false,
			'bp-verified-member' 					=> false,
			'mediapress' 							=> false,
			'woocommerce' 							=> false,
			'yith-woocommerce-wishlist' 			=> false,
			'learnpress' 							=> false,
			'learnpress-course-review' 				=> false,
			'bp-better-messages' 					=> false,
			'paid-memberships-pro' 					=> false,
			'pmpro-buddypress' 						=> false,
			'wp-story-premium' 						=> true,
			'iqonic-moderation-tool' 				=> true,
			'iqonic-reactions' 						=> true
		];
		
		if (!(is_dependent_plugin_active("iqonic-moderation-tool", "iqonic-moderation-tool.php") || is_dependent_plugin_active("iqonic-extensions", "iqonic-extension.php")))
			$plugins['redux-framework'] = false;

		(new SVGetDependency($plugins))->getPlugins();
	}

	public function init()
	{
		if (is_dependent_plugin_active("iqonic-moderation-tool", "iqonic-moderation-tool.php") || is_dependent_plugin_active("iqonic-extensions", "iqonic-extension.php"))
			deactivate_plugins(["redux-framework/redux-framework.php"]);

		// API handle
		(new SVApiHandler())->init();
		// API Settings
		if (class_exists("Redux")) (new SVSettings())->init();

		global $socialv_options;
		if (empty($socialv_options)) {
			$socialv_options = get_options('socialv-options');
		}
	}
}
