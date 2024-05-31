<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use WC_Data_Store;
use WP_REST_Server;
use YITH_WCWL_Wishlist_Factory;

class SVWishlistController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/add-to-wishlist', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_add_to_wishlist'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-wishlist-product', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_wishlist_product'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/remove-from-wishlist', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_remove_from_wishlist'],
                'permission_callback' => '__return_true'
            ));
        });
    }

    public function socialv_add_to_wishlist($request)
    {
        if (!function_exists("YITH_WCWL"))
            return comman_custom_response([
                "status" => false,
                "message" => __('Internal Error, Try after sometime.', SOCIALV_API_TEXT_DOMAIN)
            ]);

        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        // $wishlist_id = $parameters['wishlist_id'] ? (int) $parameters['wishlist_id'] : 0;
        // $quantity = $parameters['quantity'] ? (int) $parameters['quantity'] : 1;
        $product_id = $parameters['product_id'] ? (int) $parameters['product_id'] : 0;

        if (!$product_id)
            return comman_custom_response([
                "status" => false,
                "message" => __('Try Again.', SOCIALV_API_TEXT_DOMAIN)
            ]);


        $args = [
            'user_id'           => $current_user_id,
            'add_to_wishlist'   => $product_id
            // 'quantity'          => $quantity,
            // 'wishlist_id'       => $wishlist_id,
        ];

        try {
            YITH_WCWL()->add($args);
        } catch (\YITH_WCWL_Exception $e) {
            return comman_custom_response([
                "status" => false,
                "message" => __("Already added / Can't Process.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        } catch (\Exception $e) {
            return comman_custom_response([
                "status" => false,
                "message" => __("Internal Error, Try after sometime.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("Added to your wishlist", SOCIALV_API_TEXT_DOMAIN)
        ]);
    }
    public function socialv_get_wishlist_product($request)
    {
        if (!function_exists("YITH_WCWL"))
            return comman_custom_response([
                "status" => false,
                "message" => __("Internal Error, Try after sometime.", SOCIALV_API_TEXT_DOMAIN)
            ]);

        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $wishlist = YITH_WCWL_Wishlist_Factory::get_wishlists(["user_id" => $current_user_id]);
        $wishlist_id = $wishlist && isset($wishlist[0]) ? $wishlist[0]->get_data()['id'] : 0;

        $args = [
            "user_id"       => $current_user_id,
            "wishlist_id"   => $wishlist_id
        ];

        if (!empty($parameters['per_page'])) {
            $per_page = $parameters['per_page'];
            $args["limit"] = $per_page;

            if (!empty($parameters['page'])) {
                $paged = $parameters['page'] == 1 ? 0 : $parameters['page'];

                if ($paged > 1) {
                    $paged = ($per_page * $paged - $per_page);
                }

                $args["offset"] = $paged;
            }
        }


        try {
            $results = YITH_WCWL_Wishlist_Factory::get_wishlist_items($args);
        } catch (\Exception $e) {
            return comman_custom_response([
                "status" => true,
                "message" => __("Somthing went wrong.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }

        if (empty($results))
            return comman_custom_response([
                "status" => true,
                "message" => __("Wishlist is empty", SOCIALV_API_TEXT_DOMAIN),
                "data" => []
            ]);

        $wishlist_items = sv_get_wishlist_items($results);
        
        return comman_custom_response([
            "status" => true,
            "message" => __("Wishlist items", SOCIALV_API_TEXT_DOMAIN),
            "data" => $wishlist_items
        ]);
    }
    public function socialv_remove_from_wishlist($request)
    {
        if (!function_exists("YITH_WCWL"))
            return comman_custom_response([
                "status" => false,
                "message" => __("Internal Error, Try after sometime.", SOCIALV_API_TEXT_DOMAIN)
            ]);



        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        // $wishlist_id = $parameters['wishlist_id'] ? (int) $parameters['wishlist_id'] : 0;
        $product_id = $parameters['product_id'] ? (int) $parameters['product_id'] : 0;

        if (!$product_id /* || ! $wishlist_id */)
            return comman_custom_response([
                "status" => false,
                "message" => __("Try Again.", SOCIALV_API_TEXT_DOMAIN)
            ]);


        $args = [
            'remove_from_wishlist'  => $product_id,
            'user_id'               => $current_user_id
            // 'wishlist_id'           => $wishlist_id,
        ];

        try {
            YITH_WCWL()->remove($args);
        } catch (\YITH_WCWL_Exception $e) {
            return comman_custom_response([
                "status" => false,
                "message" => __("Unable to remove, Try Again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        } catch (\Exception $e) {
            return comman_custom_response([
                "status" => false,
                "message" => __("Internal Error, Try after sometime.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("Removed from your wishlist", SOCIALV_API_TEXT_DOMAIN)
        ]);
    }
}
