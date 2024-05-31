<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use WP_REST_Server;

class SVWooController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-store-api-nonce', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_store_api_nonce'],
                'permission_callback' => '__return_true'
            ));
            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-product-details', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_product_details'],
                'permission_callback' => '__return_true'
            ));
        });
    }

    public function socialv_get_store_api_nonce($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $store_api_nonce = wp_create_nonce('wc_store_api');

        return comman_custom_response([
            "status" => true,
            "message" => __("Store API nonce", SOCIALV_API_TEXT_DOMAIN),
            "data" => ['store_api_nonce' => $store_api_nonce]
        ]);
    }
    public function socialv_get_product_details($request)
    {

        global $product;

        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $json_response = [];

        $product_details = sv_get_product_details_helper($parameters['product_id'], $current_user_id);

        if ($product_details != []) {
            $json_response[] = $product_details;
            if (isset($product_details['variations']) && count($product_details['variations'])) {
                foreach ($product_details['variations'] as $variation) {
                    $product = sv_get_product_details_helper($variation, $current_user_id);

                    if ($product != []) {
                        $json_response[] = $product;
                    }
                }
            }
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("Product detials", SOCIALV_API_TEXT_DOMAIN),
            "data" => $json_response
        ]);
    }
}
