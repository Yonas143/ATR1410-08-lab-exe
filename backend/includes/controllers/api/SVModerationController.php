<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use WP_REST_Server;

class SVModerationController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/block-member-account', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_block_member_account'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-blocked-members', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_blocked_members'],
                'permission_callback' => '__return_true'
            ));
            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/report-user-account', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_report_user_account'],
                'permission_callback' => '__return_true'
            ));
            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/report-post', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_report_post'],
                'permission_callback' => '__return_true'
            ));
            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/report-group', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_report_group'],
                'permission_callback' => '__return_true'
            ));
        });
    }

    public function socialv_block_member_account($request)
    {
        $data = svValidationToken($request);

       if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $bloked_member_id = $parameters['user_id'];
        $key = $parameters['key'];

        if ("block" == $key) {
            $is_success = imt_block_member($bloked_member_id, $current_user_id);
            $message = esc_html__("You blocked ", SOCIALV_API_TEXT_DOMAIN);
        }

        if ("unblock" == $key) {
            $is_success = imt_unblock_member($bloked_member_id, $current_user_id);
            $message = esc_html__("You unblocked ", SOCIALV_API_TEXT_DOMAIN);
        }


        if ($is_success) {
            $message .= bp_core_get_user_displayname($bloked_member_id) . ".";
            return comman_custom_response([
                "status" => true,
                "message" =>  $message
            ]);
        } else {
            return comman_custom_response([
                "status" => false,
                "message" =>  __("Something went wrong. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }
    }

    public function socialv_get_blocked_members($request)
    {
        $data = svValidationToken($request);

       if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $members = [];
        $members_ids = imt_get_blocked_members_ids($current_user_id);

        if ($members_ids) {
            foreach ($members_ids as $id) {
                $user_avatar_url = bp_core_fetch_avatar(
                    array(
                        'item_id' => $id,
                        'no_grav' => true,
                        'type'    => 'full',
                        'html'    => FALSE     // FALSE = return url, TRUE (default) = return img html
                    )
                );
                
                $members[] = [
                    "user_id"           => $id,
                    "user_image"        => $user_avatar_url,
                    "user_name"         => bp_core_get_user_displayname($id),
                    "user_mention_name" => bp_core_get_username($id),
                    "is_user_verified"  => sv_is_user_verified($id)
                ];
            }
        }
        return comman_custom_response([
            "status" => true,
            "message" => __("Block members list", SOCIALV_API_TEXT_DOMAIN),
            "data" => $members
        ]);
    }
    public function socialv_report_user_account($request)
    {
        $data = svValidationToken($request);

       if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $reported = $parameters['user_id'];

        $report_count = imt_item_reports_count($reported, $current_user_id);
        if ($report_count == 5) return comman_custom_response([
            "status" => true,
            "message" =>  __("Limit exceed.", SOCIALV_API_TEXT_DOMAIN)
        ]);

        $args = [
            "reporter"      => $current_user_id,
            "reported"      => $reported,
            "report_type"   => $parameters['report_type'] ? $parameters['report_type'] : "other",
            "details"       => $parameters['details'],
            "activity_type" => "member"
        ];

        $report = imt_report($args);

        if ($report) {
            return comman_custom_response([
                "status" => true,
                "message" =>  __("Reported successfully.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        } else {
            return comman_custom_response([
                "status" => false,
                "message" =>  __("Something went wrong. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }
    }
    public function socialv_report_post($request)
    {
        $data = svValidationToken($request);

       if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $item_id = $parameters['item_id'];

        $report_count = imt_item_reports_count($item_id, $current_user_id);
        if ($report_count == 5)  return comman_custom_response([
            "status" => true,
            "message" =>  __("Limit exceed.", SOCIALV_API_TEXT_DOMAIN)
        ]);

        $args = [
            "reporter"      => $current_user_id,
            "reported"      => $parameters['user_id'],
            "item_id"       => $item_id,
            "report_type"   => $parameters['report_type'] ? $parameters['report_type'] : "other",
            "details"       => $parameters['details'],
            "activity_type" => "activity"
        ];

        $report = imt_report($args);

        if ($report) {
            return comman_custom_response([
                "status" => true,
                "message" =>  __("Reported successfully.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        } else {
            return comman_custom_response([
                "status" => false,
                "message" =>  __("Something went wrong. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }
    }
    public function socialv_report_group($request)
    {
        $data = svValidationToken($request);

       if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $group_id = $parameters['group_id'];

        $report_count = imt_item_reports_count($group_id, $current_user_id);
        if ($report_count == 5) return comman_custom_response([
            "status" => true,
            "message" =>  __("Limit exceed.", SOCIALV_API_TEXT_DOMAIN)
        ]);

        $args = [
            "reporter"      => $current_user_id,
            "reported"      => $group_id,
            "item_id"       => $group_id,
            "report_type"   => $parameters['report_type'] ? $parameters['report_type'] : "other",
            "details"       => $parameters['details'],
            "activity_type" => "group"
        ];

        $report = imt_report($args);

        if ($report) {
            return comman_custom_response([
                "status" => true,
                "message" =>  __("Reported successfully.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        } else {
            return comman_custom_response([
                "status" => false,
                "message" =>  __("Something went wrong. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }
    }
}
