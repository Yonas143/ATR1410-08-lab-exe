<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WPCF7_ContactForm;
use WPCF7_FormTag;
use WPCF7_Pipes;

class SVContactController extends SVBase
{

	public $module = 'socialv';

	public $nameSpace;

	function __construct()
	{

		$this->nameSpace = SOCIALV_API_NAMESPACE;

		add_action('rest_api_init', function () {

			register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/contact-forms/(?P<id>\d+)', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [$this, 'get_contact_form'],
				'permission_callback' => '__return_true'
			));
		});
	}
	public function get_contact_form(WP_REST_Request $request)
	{
		$id = (int) $request->get_param('id');
		$item = wpcf7_contact_form($id);

		if (!$item) {
			return comman_custom_response([
				"status" => true,
				"message" => __("The requested contact form was not found.", SOCIALV_API_TEXT_DOMAIN)
			]);
		}

		$response = array(
			'id' => $item->id(),
			'slug' => $item->name(),
			'title' => $item->title(),
			'locale' => $item->locale(),
			'properties' => $this->get_properties($item),
		);

		return comman_custom_response([
			"status" => true,
			"message" => __("Contact form details", SOCIALV_API_TEXT_DOMAIN),
			"data" => $response
		]);
	}
	private function get_properties(WPCF7_ContactForm $contact_form)
	{
		$properties = $contact_form->get_properties();

		$properties['form'] = array(
			'content' => (string) $properties['form'],
			'fields' => array_map(
				function (WPCF7_FormTag $form_tag) {
					return comman_custom_response([
						"status" => true,
						"message" => __("Form Tag List", SOCIALV_API_TEXT_DOMAIN),
						"data" => [
							'type' => $form_tag->type,
							'basetype' => $form_tag->basetype,
							'name' => $form_tag->name,
							'options' => $form_tag->options,
							'raw_values' => $form_tag->raw_values,
							'labels' => $form_tag->labels,
							'values' => $form_tag->values,
							'pipes' => $form_tag->pipes instanceof WPCF7_Pipes
								? $form_tag->pipes->to_array()
								: $form_tag->pipes,
							'content' => $form_tag->content,
						]
					]);
				},
				$contact_form->scan_form_tags()
			),
		);

		$properties['additional_settings'] = array(
			'content' => (string) $properties['additional_settings'],
			'settings' => array_filter(array_map(
				function ($setting) {
					$pattern = '/^([a-zA-Z0-9_]+)[\t ]*:(.*)$/';

					if (preg_match($pattern, $setting, $matches)) {
						$name = trim($matches[1]);
						$value = trim($matches[2]);

						if (in_array($value, array('on', 'true'), true)) {
							$value = true;
						} elseif (in_array($value, array('off', 'false'), true)) {
							$value = false;
						}

						return comman_custom_response([
							"status" => true,
							"message" => __("Contact form properties", SOCIALV_API_TEXT_DOMAIN),
							"data" => [$name, $value]
						]);
					}

					return comman_custom_response([
						"status" => true,
						"message" => __("Contact Form Porperties Not Found", SOCIALV_API_TEXT_DOMAIN)
					]);
				},
				explode("\n", $properties['additional_settings'])
			)),
		);

		return comman_custom_response([
			"status" => true,
			"message" => __("Contact Form Porperties details", SOCIALV_API_TEXT_DOMAIN),
			"data" => $properties
		]);
	}
}
