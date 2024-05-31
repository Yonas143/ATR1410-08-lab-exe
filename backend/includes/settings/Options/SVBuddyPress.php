<?php

namespace Includes\settings\Options;

use Includes\settings\SVSettings;
use Redux;


class SVBuddyPress extends SVSettings
{
	protected $is_theme_active;

	public function __construct()
	{
		$this->is_theme_active = is_dependent_theme_active();
		$this->set_widget_option();
	}
	public function activity_feed_options()
	{
		$disable_class = $this->is_theme_active ? "disabled socialv-api-sub-fields fold" : "";
		$options = [
			[
				'id' 		=> parent::$sv_option_prefix . 'account_verification',
				'type' 		=> 'button_set',
				'title' 	=> __('Acount Verification', 'socialv-api'),
				'options'	=> [
					true 	=> "Enable",
					false 	=> "Disable"
				],
				'desc' 		=> __('If account verification is enabled, every new user will be required to verify their email account in order to use the app.', 'socialv-api'),
				'default' 	=> true
			],
			[
				'id' 		=> parent::$sv_option_prefix . 'friends_only_activity',
				'type' 		=> 'button_set',
				'title' 	=> __('Friends only activity', 'socialv-api'),
				'options'	=> [
					true 	=> "Enable",
					false 	=> "Disable"
				],
				'class'		=> $disable_class,
				'desc' 		=> __('By enabling it logged-in user can see their friends activity only.', 'socialv-api'),
				'default' 	=> true
			],
			[
				'id'        => parent::$sv_option_prefix . 'display_blog_post',
				'type'      => 'checkbox',
				'class'		=> $disable_class,
				'readonly'	=> true,
				'desc'     	=> esc_html__('Select this option to dispaly blog posts in feed.', 'socialv-api'),
				'title' 	=> esc_html__('Activity Blog Posts', 'socialv-api'),
				'default'   => '0'
			]
		];
		if ($this->is_theme_active) {
			$option_info = [
				'id'    => 'friends_only_activity_notes',
				'type'  => 'info',
				'title' => esc_html__('Disabled Options', 'socialv-api'),
				'style' => 'warning',
				'icon' => 'el el-info-circle',
				'desc'  => esc_html__('Disabled options can be manageable from theme options now.', 'socialv-api')
			];
			array_unshift($options, $option_info);
		}


		if (class_exists('BuddyPress_GIPHY')) {
			$giphy_option_1 = [
				'id' 		=> parent::$sv_option_prefix . 'giphy_api_key',
				'type' 		=> 'text',
				'title' 	=> __('GIPHY Key', 'socialv-api'),
				'desc' 		=> __('Add GIPHY Key here.', 'socialv-api'),
			];
			$giphy_option_2 = [
				'id' 		=> parent::$sv_option_prefix . 'giphy_ios_api_key',
				'type' 		=> 'text',
				'desc' 		=> __('Add GIPHY Key here for iOS.', 'socialv-api'),
			];
			array_push($options, $giphy_option_1, $giphy_option_2);
		}
		return $options;
	}

	protected function set_widget_option()
	{
		Redux::set_section($this->opt_name, array(
			'title' => esc_html__('BuddyPress', 'socialv-api'),
			'id'    => parent::$sv_option_prefix . 'buddypress',
			'icon'  => 'custom-social-groups',
		));

		// activity settings
		Redux::set_section($this->opt_name, array(
			'title' 			=> __('Activity Feeds', 'socialv-api'),
			'id' 				=> parent::$sv_option_prefix . 'activity_feeds',
			'icon' 				=> "custom-activity",
			'desc'				=> __('Manage settings related BuddyPress activity feeds', 'socialv-api'),
			'customizer_width' 	=> '500px',
			'subsection' 		=> true,
			'fields' 			=> $this->activity_feed_options()
		));
	}
}
