<?php

namespace Includes\settings\Options;

use Includes\baseClasses\SVCustomNotifications;
use Includes\settings\SVSettings;
use Redux;


class SVNotificationsSettings extends SVSettings
{

	public function __construct()
	{
		$this->set_widget_option();
	}
	function notification_dependent_options()
	{

		$options = [];
		$notifications = SVCustomNotifications::push_notification_messages();
		foreach ($notifications as $action => $data) {
			$options[] = [
				'id' 		=> parent::$sv_option_prefix . $action . "_heading",
				'type' 		=> 'text',
				'title' 	=> $data["title"],
				'desc' 		=> __("Chnage notification title using ", SOCIALV_API_TEXT_DOMAIN) . $data["desc"],
				'default'	=> $data["heading"]
			];
			$options[] = [
				'id' 		=> parent::$sv_option_prefix . $action . "_content",
				'type' 		=> 'text',
				'desc' 		=> __("Chnage notification short description using ", SOCIALV_API_TEXT_DOMAIN) . $data["desc"],
				'default'	=> $data["content"]
			];
		}

		return $options;
	}

	protected function set_widget_option()
	{
		Redux::set_section($this->opt_name, array(
			'title' 			=> __('Notifications', 'socialv-api'),
			'id' 				=> parent::$sv_option_prefix . 'notifications',
			'icon' 				=> "custom-notifications",
			'desc'				=> __('Manage notifications settings', 'socialv-api'),
			'customizer_width' 	=> '500px',
			'subsection' 		=> true,
			'fields' 			=> $this->notification_dependent_options()
		));
	}
}
