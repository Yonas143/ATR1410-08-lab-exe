<?php

namespace Includes\settings\Options;

use Includes\settings\SVSettings;
use Redux;


class SVWooCommerce extends SVSettings
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
				'id' 		=> parent::$sv_option_prefix . 'is_shop_enable',
				'type' 		=> 'radio',
				'title' 	=> __('Woocommerce Shop', 'socialv-api'),
				'options'	=> [
					false 	=> "Disable",
					true 	=> "Enable"
				],
				'desc' 		=> __('Disble / Enable shop for app users.', 'socialv-api'),
				'default' 	=> true
			],
		];

		return $return_data;
	}

	protected function set_widget_option()
	{
		Redux::set_section($this->opt_name, array(
			'title' 			=> __('Woocommerce', 'socialv-api'),
			'id' 				=> parent::$sv_option_prefix . 'woocommerce',
			'icon' 				=> "custom-Woo-commerce",
			'desc'				=> __('Woocommerece settings', 'socialv-api'),
			'customizer_width' 	=> '500px',
			'fields' 			=> $this->options
		));
	}
}
