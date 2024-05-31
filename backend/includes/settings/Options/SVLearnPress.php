<?php

namespace Includes\settings\Options;

use Includes\settings\SVSettings;
use Redux;


class SVLearnPress extends SVSettings
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
				'id' 		=> parent::$sv_option_prefix . 'is_course_enable',
				'type' 		=> 'radio',
				'title' 	=> __('LearnPress', 'socialv-api'),
				'options'	=> [
					0 	=> "Disable",
					1 	=> "Enable"
				],
				'desc' 		=> __('Disable / Enable LearnPress for App only.', 'socialv-api'),
				'default' 	=> 1
			],
		];

		return $return_data;
	}

	protected function set_widget_option()
	{
		Redux::set_section($this->opt_name, array(
			'title' 			=> __('LearnPress', 'socialv-api'),
			'id' 				=> parent::$sv_option_prefix . 'learnpress',
			'icon' 				=> "custom-Education",
			'desc'				=> __('Manage LearnPress settings', 'socialv-api'),
			'customizer_width' 	=> '500px',
			'fields' 			=> $this->options
		));
	}
}
