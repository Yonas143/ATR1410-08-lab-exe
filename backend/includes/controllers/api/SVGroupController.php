<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use Includes\baseClasses\SVGorupSuggestions;
use WP_REST_Server;

class SVGroupController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/get-group-list',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_get_group_list'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/get-group-details',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_get_group_details'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/get-group-members-list',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_get_group_members'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/get-membership-request',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_get_membership_request'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/get-invite-user-list',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_invite_user_list'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/group-manage-invitation',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_group_manage_invitation'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/group-manage-settings',
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'socialv_group_manage_settings'],
                    'permission_callback' => '__return_true'
                )
            );
        });
    }

    public function socialv_get_group_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int)$data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $groups = [];

        $user_id                = isset($parameters["user_id"]) ? (int) $parameters["user_id"] : 0;
        $type                   = !empty($parameters['type']) ? $parameters['type'] : "my_group";
        $per_page               = !empty($parameters['per_page']) ? $parameters['per_page'] : 10;
        $page                   = !empty($parameters['page']) ? $parameters['page'] : 1;
        $current_member_groups  = "my_group" == $type && $user_id ? "&user_id=" . $user_id : "";

        if ("suggestions" == $type) {
            $group_suggestions = new SVGorupSuggestions();
            $args = [
                'per_page'          => $per_page,
                'page'              => $page,
                'current_user_id'   => $current_user_id
            ];
            $groups = $group_suggestions->get_suggestions_list($args);
            return comman_custom_response([
                "status" => true,
                "message" => __("List Of group suggestion", SOCIALV_API_TEXT_DOMAIN),
                "data" => $groups
            ]);
        }

        $parse_args = bp_ajax_querystring('groups') . "&per_page=" . ($per_page) . "&page=" . $page . $current_member_groups;

        if (bp_has_groups($parse_args)) :

            while (bp_groups()) : bp_the_group();

                $group_id   = bp_get_group_id();
                $creator_id = bp_get_group_creator_id();
                $member_args = [
                    "group_id"          => $group_id,
                    "per_page"          => 5,
                    "page"              => 1,
                    "return"            => "data",
                    "current_user_id"   => $current_user_id
                ];

                $member_list        = socialv_get_group_members_list($member_args);
                $is_member          = groups_is_user_member($current_user_id, $group_id);
                $is_admin           = groups_is_user_admin($current_user_id, $group_id);
                $has_invite         = groups_check_user_has_invite($current_user_id, $group_id);
                $is_request_sent    = groups_check_for_membership_request($current_user_id, $group_id);

                $groups[] = [
                    "id"                        => $group_id,
                    "group_cover_image"         => bp_get_group_cover_url(),
                    "group_avtar_image"         => bp_get_group_avatar('html=false&type=full'),
                    "name"                      => bp_get_group_name(),
                    "post_count"                => socialv_total_group_post_count($group_id),
                    "member_count"              => bp_get_group_total_members(),
                    "group_created_by_id"       => $creator_id,
                    "group_created_by"          => bp_core_get_user_displayname($creator_id),
                    "is_group_member"           => $is_member ? true : $is_member,
                    "is_group_admin"            => $is_admin ? true : $is_admin,
                    "group_type"                => bp_get_group_type(),
                    "is_request_sent"           => $is_request_sent ? $is_request_sent : 0,
                    "has_invite"                => $has_invite ? $has_invite : 0,
                    "member_list"               => $member_list
                ];

            endwhile;

        endif;

        return comman_custom_response([
            "status" => true,
            "message" => __("group List", SOCIALV_API_TEXT_DOMAIN),
            "data" => $groups
        ]);
    }

    public function socialv_get_group_details($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int)$data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $group_id   = (int)$parameters["group_id"];
        $group      = groups_get_group($group_id);

        $member_args = [
            "group_id"  => $group_id,
            "per_page"  => 5,
            "page"      => 1,
            "return"    => "data",
            "current_user_id"   => $current_user_id
        ];

        $member_list = socialv_get_group_members_list($member_args);

        $is_member          = groups_is_user_member($current_user_id, $group_id);
        $is_admin           = groups_is_user_admin($current_user_id, $group_id);
        $is_request_sent    = groups_check_for_membership_request($current_user_id, $group_id);
        $has_invite         = groups_check_user_has_invite($current_user_id, $group_id);
        $is_banned          = groups_is_user_banned($current_user_id, $group_id);
        $is_mod             = groups_is_user_mod($current_user_id, $group_id);
        $invite_status      = bp_group_get_invite_status($group_id);
        $is_gallery_enabled = mpp_group_is_gallery_enabled($group_id);

        $group_details = [
            "id"                        => $group_id,
            "group_cover_image"         => bp_get_group_cover_url($group),
            "group_avtar_image"         => bp_get_group_avatar('html=false&type=full', $group),
            "name"                      => $group->name,
            "can_invite"                => bp_groups_user_can_send_invites($group_id, $current_user_id),
            "description"               => $group->description,
            "post_count"                => socialv_total_group_post_count($group_id),
            "member_count"              => bp_get_group_total_members($group),
            "group_created_by_id"       => $group->creator_id,
            "group_created_by"          => bp_core_get_user_displayname($group->creator_id),
            "date_created"              => $group->date_created,
            "is_group_member"           => $is_member ? true : $is_member,
            "is_group_admin"            => $is_admin ? true : $is_admin,
            "is_mod"                    => $is_mod ? 1 : 0,
            "is_banned"                 => $is_banned ? 1 : 0,
            "is_gallery_enabled"        => $is_gallery_enabled ? 1 : 0,
            "group_type"                => $group->status,
            "invite_status"             => $invite_status,
            "is_request_sent"           => $is_request_sent ? $is_request_sent : 0,
            "has_invite"                => $has_invite ? $has_invite : 0,
            "member_list"               => $member_list
        ];

        return comman_custom_response([
            "status" => true,
            "message" => __("group details", SOCIALV_API_TEXT_DOMAIN),
            "data" => $group_details
        ]);
    }

    public function socialv_get_group_members($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $group_id = $parameters["group_id"];
        $per_page = !empty($parameters["per_page"]) ? $parameters["per_page"] : 10;
        $page = !empty($parameters["page"]) ? $parameters["page"] : 1;
        $search_terms = !empty($parameters["search_terms"]) ? $parameters["search_terms"] : "";

        $member_args = [
            "group_id"          => $group_id,
            "per_page"          => $per_page,
            "page"              => $page,
            "search_terms"      => $search_terms,
            "return"            => "data",
            "current_user_id"   => $current_user_id
        ];

        $member_list = socialv_get_group_members_list($member_args);


        return comman_custom_response([
            "status" => true,
            "message" => __("group details", SOCIALV_API_TEXT_DOMAIN),
            "data" => $member_list
        ]);
    }

    public function socialv_get_membership_request($request)
    {
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $group_id = $parameters["group_id"];
        $per_page = !empty($parameters["per_page"]) ? $parameters["per_page"] : 10;
        $page = !empty($parameters["page"]) ? $parameters["page"] : 1;
        $membership_requests = [];
        $parse_args = bp_ajax_querystring('membership_requests') . "&group_id=" . $group_id . "&per_page=" . $per_page . "&page=" . $page;
        if (bp_group_has_membership_requests($parse_args)) {
            while (bp_group_membership_requests()) : bp_group_the_membership_request();
                global $requests_template;

                $user_id = $requests_template->request->user_id;
                $user_avatar = bp_core_fetch_avatar(
                    array(
                        'item_id'   => $user_id,
                        'type'      => 'full',
                        'no_grav'   => true,
                        'html'      => FALSE     // FALSE = return url, TRUE (default) = return img html
                    )
                );

                $membership_requests[] = [
                    "request_Id"        => $requests_template->request->invitation_id,
                    "user_id"           => $user_id,
                    "user_name"         => $requests_template->request->display_name ? $requests_template->request->display_name : sv_default_display_name(),
                    "user_mention_name" => $requests_template->request->user_login,
                    "user_image"        => $user_avatar,
                    "is_user_verified"  => sv_is_user_verified($user_id)
                ];
            endwhile;
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("group membership request", SOCIALV_API_TEXT_DOMAIN),
            "data" => $membership_requests
        ]);
    }

    public function socialv_invite_user_list($request)
    {
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $group_id = $parameters["group_id"];
        $per_page = !empty($parameters["per_page"]) ? $parameters["per_page"] : 10;
        $page = !empty($parameters["page"]) ? $parameters["page"] : 1;

        $invite_list = [];

        $member_args = [
            "group_id"  => $group_id,
            "per_page"  => 0,
            "page"      => 1,
            "return"    => "ids"
        ];

        $exclude_member_list = socialv_get_group_members_list($member_args);

        $args = [
            'exclude'       => $exclude_member_list,
            'per_page'      => $per_page,
            'page'          => $page,
            'search_terms'  => $parameters['search_terms']
        ];


        if (bp_has_members($args)) {

            while (bp_members()) : bp_the_member();
                $user_id = bp_get_member_user_id();

                $is_invited = groups_is_user_invited($user_id, $group_id);
                $user_avatar = bp_core_fetch_avatar(
                    array(
                        'item_id'   => $user_id,
                        'no_grav'   => true,
                        'type'      => 'full',
                        'html'      => FALSE     // FALSE = return url, TRUE (default) = return img html
                    )
                );
                
                $invite_list[] = [
                    "user_Id"           => $user_id,
                    "is_invited"        => $is_invited ? true : false,
                    "user_name"         => bp_core_get_user_displayname($user_id),
                    "user_image"        => $user_avatar,
                    "is_user_verified"  => sv_is_user_verified($user_id)
                ];

            endwhile;
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("invite user list", SOCIALV_API_TEXT_DOMAIN),
            "data" => $invite_list
        ]);
    }

    public function socialv_group_manage_invitation($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $group_id = $parameters["group_id"];
        $user_id = $parameters["user_id"];

        if (groups_is_user_member($user_id, $group_id))
            return comman_custom_response([
                "status" => true,
                "message" => __("Already a member of group.", SOCIALV_API_TEXT_DOMAIN)
            ]);

        $is_inviting = $parameters["is_inviting"];
        $message = esc_html__("Something Wrong.", SOCIALV_API_TEXT_DOMAIN);
        $status_code = false;
        if ($is_inviting) {
            $args = [
                'user_id'       => $user_id,
                'group_id'      => $group_id,
                'inviter_id'    => $current_user_id,
                'send_invite'   => 1
            ];

            $invited = groups_invite_user($args);

            if ($invited) {
                $message = esc_html__("Request sent.", SOCIALV_API_TEXT_DOMAIN);
                $status_code = true;
            }
        } else {
            if (groups_delete_invite($user_id, $group_id)) {
                $message = esc_html__("Request removed.", SOCIALV_API_TEXT_DOMAIN);
                $status_code = true;
            }
        }
        return comman_custom_response([
            "status" => $status_code,
            "message" => $message
        ]);
    }

    /**
     * Chnage group settings.
     *
     * @since 1.2.0
     *
     * @param int       'group_id'                  to change settings.
     * @param string    "invite_status"             'members', 'mods', or 'admins'.
     * @param string    "enable_gallery"            Is gallery enabled - yes/no.
     * 
     * @return string    success / error message.
     */
    public function socialv_group_manage_settings($request)
    {

        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);


        if (!isset($parameters["group_id"]) || empty($parameters["group_id"]))
            return comman_custom_response([
                "status" => false,
                "message" => __("Can't identify the group. Try after sometimes.", SOCIALV_API_TEXT_DOMAIN)
            ]);

        $group_id = $parameters["group_id"];

        if (!groups_is_user_admin($current_user_id, $group_id))
            return comman_custom_response([
                "status" => false,
                "message" => __("You are not allowed to change settings.", SOCIALV_API_TEXT_DOMAIN)
            ]);

        $group = groups_get_group($group_id);

        $enable_forum = $group->enable_forum;
        $status = $group->status;

        $gallery_enble = groups_get_groupmeta($group_id, "_mpp_is_enabled", true);
        $invite_status = groups_get_groupmeta($group_id, "invite_status", true);

        $is_gallery_enable = isset($parameters["enable_gallery"]) || !empty($parameters["enable_gallery"]) ? $parameters["enable_gallery"] : $gallery_enble;
        $invite_status = isset($parameters["invite_status"]) || !empty($parameters["invite_status"]) ? $parameters["invite_status"] : $invite_status;

        $gallery_setting = groups_update_groupmeta($group_id, "_mpp_is_enabled", $is_gallery_enable);

        if (!groups_edit_group_settings($group_id, $enable_forum, $status, $invite_status)) {
            $message = __('There was an error updating group settings. Please try again.', SOCIALV_API_TEXT_DOMAIN);
            $status_code = false;
        } else {
            $message = __('Group settings were successfully updated.', SOCIALV_API_TEXT_DOMAIN);
            $status_code = true;
        }

        return comman_custom_response([
            "status" => $status_code,
            "message" => $message
        ]);
    }
}
