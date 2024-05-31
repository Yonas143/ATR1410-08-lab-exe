<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use Includes\baseClasses\SVLMSCheckout;
use LP_Gateways;
use LP_Order;
use WP_Query;
use WP_REST_Server;

class SVLMSController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/lms-payment-methods',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_get_lms_available_payments'],
                    'permission_callback' => '__return_true'
                )
            );
            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/lms-place-order',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_lms_place_order'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/lms-orders',
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'socialv_rest_user_orders'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/lms-order-details',
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'socialv_rest_order_details'],
                    'permission_callback' => '__return_true'
                )
            );
        });
    }

    public function socialv_get_lms_available_payments($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $availabel_payments = LP_Gateways::instance()->get_available_payment_gateways();

        return comman_custom_response([
            "status" => true,
            "message" => __("LMS available payment list", SOCIALV_API_TEXT_DOMAIN),
            "data" => array_values($availabel_payments)
        ]);
    }

    public function socialv_lms_place_order($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $args = [
            'current_user_id'   => $current_user_id,
            'cart_items'        => $parameters['course_ids'],
            'cart_subtotal'     => $parameters['subtotal'],
            'cart_total'        => $parameters['total'],
            'payment_method'    => $parameters['payment_method'],
            'customer_note'     => $parameters['customer_note']
        ];

        $checkout = new SVLMSCheckout($args);
        $order_summary = $checkout->process_checkout();

        return comman_custom_response([
            "status" => true,
            "message" => __("LMS order place", SOCIALV_API_TEXT_DOMAIN),
            "data" => $order_summary
        ]);
    }

    public function socialv_rest_user_orders($request)
    {
       
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $order_statuses = LP_Order::get_order_statuses();

        $args = [
            'post_type'         => 'lp_order',
            'author'            => $current_user_id,
            'post_status'       => array_keys($order_statuses),
            'paged'             => isset($parameters["page"]) && !empty($parameters["page"]) ? $parameters["page"] : 1,
            'posts_per_page'    => isset($parameters["per_page"]) && !empty($parameters["per_page"]) ? $parameters["per_page"] : 20
        ];

        $orders = [];
        $wp_query = new WP_Query($args);
        if ($wp_query->have_posts()) :
            while ($wp_query->have_posts()) :
                $wp_query->the_post();
                $order = new LP_Order(get_the_ID());

                $orders[] = [
                    "id"            => get_the_ID(),
                    "order_number"  => $order->get_order_number(),
                    "order_items"   => $this->sv_order_items($order->get_items()),
                    "order_status"  => $order->get_order_status()
                ];

            endwhile;
        endif;

        return comman_custom_response([
            "status" => true,
            "message" => __("Rest User order", SOCIALV_API_TEXT_DOMAIN),
            "data" => $orders
        ]);
    }

    public function socialv_rest_order_details($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (!isset($parameters["id"]) && empty($parameters["id"])) return comman_custom_response([
            "status" => true,
            "message" => __("Empty request id", SOCIALV_API_TEXT_DOMAIN)
        ]);

        $order          = new LP_Order($parameters["id"]);
        if (!$order) return comman_custom_response([
            "status" => true,
            "message" => __("Empty Order List", SOCIALV_API_TEXT_DOMAIN)
        ]);

        if ($current_user_id != $order->get_user_id()) return comman_custom_response([
            "status" => true,
            "message" => __("Author of order not match with current user", SOCIALV_API_TEXT_DOMAIN)
        ]);

        $id = $order->get_id();

        $order_details = [
            "id"                => $id,
            "order_number"      => $order->get_order_number(),
            "order_date"        => get_the_date("Y-m-d", $id),
            "order_status"      => $order->get_order_status(),
            "order_items"       => $this->sv_order_items($order->get_items()),
            "order_key"         => $order->get_order_key(),
            "order_subtotal"    => $order->get_formatted_order_subtotal(),
            "order_total"       => $order->get_formatted_order_total(),
            "order_method"      => $order->get_payment_method_title()
        ];

        return comman_custom_response([
            "status" => true,
            "message" => __("Details of order", SOCIALV_API_TEXT_DOMAIN),
            "data" => $order_details
        ]);
    }
    public function sv_order_items($items)
    {
        $order_items = [];
        foreach ($items as $item) {

            $order_items[] = [
                "id"            => $item["course_id"],
                "name"          => $item["name"],
                "regular_price" => get_post_meta($item["course_id"], "_lp_regular_price", true),
                "sale_price"    => get_post_meta($item["course_id"], "_lp_sale_price", true),
                "quantity"      => $item["quantity"]
            ];
        }
        
        return $order_items;
    }
}
