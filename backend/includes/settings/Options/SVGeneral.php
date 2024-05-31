<?php

namespace Includes\settings\Options;

use Includes\settings\SVSettings;
use Redux;


class SVGeneral extends SVSettings
{
	protected $options;

	public function __construct()
	{
		$this->options = $this->dependent_options();
		$this->set_widget_option();
	}
	function dependent_options()
	{

		$return_data = [
			[
				'id' 		=> parent::$sv_option_prefix . 'is_ads_enable',
				'type' 		=> 'radio',
				'title' 	=> __('AdMob', 'socialv-api'),
				'options'	=> [
					0 	=> "Disable",
					1 	=> "Enable"
				],
				'desc' 		=> __('Disable / Enable Ads for App.', 'socialv-api'),
				'default' 	=> 1
			],
            [
				'id' 		=> parent::$sv_option_prefix . 'is_blog_enable',
				'type' 		=> 'radio',
				'title' 	=> __('Blog', 'socialv-api'),
				'options'	=> [
					0 	=> "Disable",
					1 	=> "Enable"
				],
				'desc' 		=> __('Disable / Enable Blogs for App only.', 'socialv-api'),
				'default' 	=> 1
			],
			[
				'id' 		=> parent::$sv_option_prefix . 'is_forums_enable',
				'type' 		=> 'radio',
				'title' 	=> __('Forums', 'socialv-api'),
				'options'	=> [
					0 	=> "Disable",
					1 	=> "Enable"
				],
				'desc' 		=> __('Disable / Enable Forums for App only.', 'socialv-api'),
				'default' 	=> 1
			],
            [
				'id' 		=> parent::$sv_option_prefix . 'is_social_login_enable',
				'type' 		=> 'radio',
				'title' 	=> __('Social Login', 'socialv-api'),
				'options'	=> [
					0 	=> "Disable",
					1 	=> "Enable"
				],
				'desc' 		=> __('Disable / Enable Social Login for App only.', 'socialv-api'),
				'default' 	=> 1
			],
            [
				'id' 		=> parent::$sv_option_prefix . 'is_gamipress_enable',
				'type' 		=> 'radio',
				'title' 	=> __('GamiPress', 'socialv-api'),
				'options'	=> [
					0 	=> "Disable",
					1 	=> "Enable"
				],
				'desc' 		=> __('Disable / Enable GamiPress for App only.', 'socialv-api'),
				'default' 	=> 1
			]
		];

		return $return_data;
	}

	protected function set_widget_option()
	{
		Redux::set_section($this->opt_name, array(
			'title' 			=> __('General', 'socialv-api'),
			'id' 				=> parent::$sv_option_prefix . 'general',
			'icon' 				=> "custom-Dashboard",
			'desc'				=> __('Manage General settings', 'socialv-api'),
			'customizer_width' 	=> '500px',
			'fields' 			=> $this->options
		));
	}
}
