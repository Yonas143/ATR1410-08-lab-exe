<?php

namespace Includes\settings\Options;

use Includes\settings\SVSettings;
use Redux;


class SVFireBaseNotification extends SVSettings
{
	protected $firebase_key;

	public function __construct()
	{
		$onesignal_config = get_option("socialv_firebase_keys");
		if (!empty($onesignal_config)) {
			$this->firebase_key = $onesignal_config["app_id"];
		}
		$this->set_widget_options();
	}


	protected function set_widget_options()
	{
		Redux::set_section($this->opt_name, array(
			'title' 			=> __('Firebase', 'socialv-api'),
			'id' 				=> parent::$sv_option_prefix . 'firebase',
			'icon' 				=> 'custom-notifications',
			'desc'				=> __('Manage settings related firebase Push Notifications', 'socialv-api'),
			'customizer_width' 	=> '500px',
			'fields' => array(
				array(
					'id' 		=> parent::$sv_option_prefix . 'firebase_app_id',
					'type' 		=> 'text',
					'title' 	=> __('App Id', 'socialv-api'),
					'desc' 		=> __('Add FireBase app id here.', 'socialv-api'),
					'default' 	=> $this->firebase_key
				)
			)
		));
	}
}
