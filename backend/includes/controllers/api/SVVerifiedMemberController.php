<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use WP_REST_Server;


class SVVerifiedMemberController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/member-request-verification', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_verify_member_request'],
                'permission_callback' => '__return_true'
            ));
        });
    }

    public function socialv_verify_member_request($request)
    {

        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $verification_request = sv_request_verification($current_user_id);

        if (!$verification_request)
            return comman_custom_response([
                "status" => false,
                "message" => __('Something Wrong !, Try again later.', SOCIALV_API_TEXT_DOMAIN),
                "data" => []
            ]);

        return comman_custom_response([
            "status" => true,
            "message" => $verification_request
        ]);
    }
}
