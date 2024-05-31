<?php

namespace Includes\settings\Options;

use Includes\settings\SVSettings;
use Redux;


class SVMembership extends SVSettings
{

	public function __construct()
	{
		$this->set_widget_option();
	}
	public function membership_options()
	{
		$enable_membership = [
			[
				'id' 		=> parent::$sv_option_prefix . 'is_membership_enable',
				'type' 		=> 'radio',
				'title' 	=> __('Membership', 'socialv-api'),
				'options'	=> [
					0 	=> "Disable",
					1 	=> "Enable"
				],
				'desc' 		=> __('Disable / Enable Paid Membership Pro for App.', 'socialv-api'),
				'default' 	=> 1
			]
		];
		$options = array_merge($enable_membership, $this->set_payment_options("stripe"), $this->set_payment_options("razorpay"));
		return $options;
	}

	public function set_payment_options($gateway)
	{
		$gateway_title 		= ucfirst($gateway);
		$enable_payment_id 	= parent::$sv_option_prefix . "enable_{$gateway}";
		$payment_mode_id 	= parent::$sv_option_prefix . "{$gateway}_payment_mode";
		$modes 				= ["testing", "live"];
		$text_options = [
			"url" 			=> __("URL", SOCIALV_API_TEXT_DOMAIN),
			"key" 			=> __("Key", SOCIALV_API_TEXT_DOMAIN),
			"public_key" 	=> __("Public Key", SOCIALV_API_TEXT_DOMAIN)
		];
		$options = [
			[
				'id' 		=> $enable_payment_id,
				'type' 		=> 'switch',
				'title' 	=> __("$gateway_title payment", SOCIALV_API_TEXT_DOMAIN),
				'subtitle'	=> __("Enable $gateway payment for membership checkout.", SOCIALV_API_TEXT_DOMAIN),
				"required"	=> [
					[parent::$sv_option_prefix . 'is_membership_enable', "=", 1]
				],
				'default' 	=> true
			],
			[
				'id' 		=> $payment_mode_id,
				'type' 		=> 'radio',
				'title' 	=> __("$gateway_title payment", SOCIALV_API_TEXT_DOMAIN),
				'options'	=> [
					"testing" 	=> "Test",
					"live" 		=> "Live"
				],
				"required"	=> [$enable_payment_id, "=", true],
				'subtitle' 	=> __('Select payment mode.', SOCIALV_API_TEXT_DOMAIN),
				'default' 	=> "testing"
			],
			[
				'id' 		=> parent::$sv_option_prefix . "{$gateway}_name",
				'type' 		=> 'text',
				'title' 	=> __("Gateway Name", SOCIALV_API_TEXT_DOMAIN),
				"required"	=> [
					[$enable_payment_id, "=", true]
				],
				'class'		=> "socialv-api-sub-fields"
			]
		];
		foreach ($modes as $mode) {
			foreach ($text_options as $option => $label) {
				$id = parent::$sv_option_prefix . "{$gateway}_{$mode}_{$option}";
				$options[] = [
					'id' 		=> $id,
					'type' 		=> 'text',
					'title' 	=> esc_html($label),
					"required"	=> [
						[$enable_payment_id, "=", true],
						[$payment_mode_id, "=", $mode]
					],
					'class'		=> "socialv-api-sub-fields"
				];
			}
		}
		return $options;
	}

	protected function set_widget_option()
	{
		Redux::set_section($this->opt_name, array(
			'title' => esc_html__('Membership', SOCIALV_API_TEXT_DOMAIN),
			'id'    => parent::$sv_option_prefix . 'membership',
			'icon'  => 'custom-social-groups',
		));

		// activity settings
		Redux::set_section($this->opt_name, array(
			'title' 			=> __('Payment Gateways', SOCIALV_API_TEXT_DOMAIN),
			'id' 				=> parent::$sv_option_prefix . 'payment_gateways',
			'icon' 				=> "custom-activity",
			'desc'				=> __('Manage settings related Membership payment gateways.', SOCIALV_API_TEXT_DOMAIN),
			'customizer_width' 	=> '500px',
			'subsection' 		=> true,
			'fields' 			=> $this->membership_options()
		));
	}

	public static function get_payment_options($gateway)
	{
		$options = [];
		$enable_payment_id 	= "enable_{$gateway}";

		$is_enable = SVSettings::sv_get_option($enable_payment_id);
		if (empty($is_enable))
			return $options;

		return [
			[
				"id"		=> $gateway,
				"enable" 	=> (int) $is_enable,
				"mode"		=> SVSettings::sv_get_option("{$gateway}_payment_mode"),
				"name" 		=> SVSettings::sv_get_option("{$gateway}_name"),
				"testing"	=> [
					"url" 			=> SVSettings::sv_get_option("{$gateway}_testing_url"),
					"key" 			=> SVSettings::sv_get_option("{$gateway}_testing_key"),
					"public_key" 	=> SVSettings::sv_get_option("{$gateway}_testing_public_key")
				],
				"live"	=> [
					"url" 			=> SVSettings::sv_get_option("{$gateway}_live_url"),
					"key" 			=> SVSettings::sv_get_option("{$gateway}_live_key"),
					"public_key" 	=> SVSettings::sv_get_option("{$gateway}_live_public_key")
				]
			]
		];
	}
}
