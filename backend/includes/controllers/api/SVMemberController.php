<?php

namespace Includes\Controllers\Api;

use BP_Core_User;
use Includes\baseClasses\SVBase;
use Includes\baseClasses\SVFriendSuggestions;
use Includes\baseClasses\SVGorupSuggestions;
use Includes\baseClasses\SVNotifications;
use Includes\settings\SVSettings;
use WP_REST_Server;


class SVMemberController extends SVBase
{

    public $module = 'socialv';
    protected $sv_option_prefix = "svo_";

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/register-user', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_register_user'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/activate-account', array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'socialv_activate_account'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-member-details', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_member_details'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-member-friends-list', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_member_friends_list'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-friendship-request-list', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_friendship_request_list'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-friend-request-sent-list', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_friend_request_sent_list'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-dashboard', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_dashboard'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-profile-fields', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_profile_fields'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/update-profile-settings', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_update_profile_settings'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-profile-visibility-settings', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_profile_visibility_settings'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/save-profile-visibility-settings', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_save_profile_visibility_settings'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-user-list', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_user_list'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/refuse-user-suggestion', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_refuse_user_suggestion'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/refuse-group-suggestion', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_refuse_group_suggestion'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/update-active-status', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_update_active_status'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/resend-activation-key', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_resend_actiavtion_key'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/gamipress/', 'user/data', array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'socialv_user_earned_points'],
                'permission_callback' => '__return_true'
            ));
            register_rest_route($this->nameSpace . '/api/v1/gamipress/', 'user/earnings', array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'socialv_user_earnings'],
                'permission_callback' => '__return_true'
            ));
            register_rest_route($this->nameSpace . '/api/v1/gamipress/', 'user/achievements', array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'socialv_user_earned_achievements'],
                'permission_callback' => '__return_true'
            ));
        });
    }

    public function socialv_register_user($request)
    {
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $user_login = $parameters["user_login"];
        $user_email = $parameters["user_email"];
        $user_name  = $parameters["user_name"];
        $password   = $parameters["password"];


        $aValid = array('-', '_');
        $errors = array();

        if (username_exists($user_login)) {
            $errors[] = esc_html__('Username already exists.', SOCIALV_API_TEXT_DOMAIN);
        } elseif (!ctype_alnum(str_replace($aValid, '', $user_login))) {
            $errors[] = esc_html__("You can only use '_', '-' in username", SOCIALV_API_TEXT_DOMAIN);
        }

        if (email_exists($user_email)) {
            $errors[] = esc_html__('Email already exists.', SOCIALV_API_TEXT_DOMAIN);
        }

        $status_code = false;
        $message = $errors;
        // only create the user in if there are no errors
        if (empty($errors)) {
            $args = array(
                'user_login'        => $user_login,
                'user_pass'         => $password,
                'user_email'        => $user_email,
                'first_name'        => $user_name,
                'user_registered'   => date('Y-m-d H:i:s'),
                'role'              => 'subscriber'
            );

            // send verify email with activation key if account verification require else register user
            if (SVSettings::sv_get_option("account_verification"))
                $new_user_id = sv_verify_email($args);
            else
                $new_user_id = wp_insert_user($args);

            $message = esc_html__("Something Wrong. Try again.", SOCIALV_API_TEXT_DOMAIN);
            if (!is_wp_error($new_user_id)) {
                do_action("socialv_rest_after_user_register", $new_user_id, $request);

                // send an email to the admin alerting them of the registration
                wp_new_user_notification($new_user_id);

                $status_code = true;
                $message = esc_html__("Register successfully.", SOCIALV_API_TEXT_DOMAIN);
            }
            $message = [$message];
        }

        return comman_custom_response([
            "status" => $status_code,
            "message" => $message
        ]);
    }

    public function socialv_activate_account($request)
    {

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        if (!isset($parameters['verification_key']) || empty(trim($parameters['verification_key'])))
            return sv_comman_message_response(__("Verification key is required.", SOCIALV_API_TEXT_DOMAIN), 422);

        $user_id = bp_core_activate_signup($parameters['verification_key']);

        if ($user_id && !is_wp_error($user_id))
            return comman_custom_response([
                "status" => true,
                "message" => __("Account is activated", SOCIALV_API_TEXT_DOMAIN),
                "data" => ["is_activated" => 1]
            ]);

        return comman_custom_response([
            "status" => false,
            "message" => __("Invalid activation key / Something worng. Try again.", SOCIALV_API_TEXT_DOMAIN),
            "data" => ["is_activated" => 1]
        ]);
    }

    public function socialv_get_member_details($request)
    {
        global $wpdb;
        global $bp;

        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);


        $user_id = $parameters['user_id'];
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            return comman_custom_response([
                "status" => false,
                "message" => __("User does not exist", SOCIALV_API_TEXT_DOMAIN),
                "data" => []
            ]);
        }

        $friendship_status = friends_check_friendship_status((int)$current_user_id, (int)$user_id);

        $user_avatar_url = bp_core_fetch_avatar(
            array(
                'item_id' => $user_id,
                'type'    => 'full',
                'html'    => FALSE     // FALSE = return url, TRUE (default) = return img html
            )
        );

        $cover_image_url = bp_attachments_get_attachment(
            'url',
            array(
                'object_dir' => 'members',
                'item_id'    => $user_id,
            )
        );

        $total_posts = $wpdb->get_results(
            "SELECT COUNT(*) as count FROM {$bp->activity->table_name} 
             WHERE component IN ('activity','groups') 
             AND  type IN ('activity_update','mpp_media_upload')
             AND hide_sitewide = 0
             AND   user_id = $user_id",
        );

        $profile_info = socialv_get_user_profile_info($user_id, $current_user_id);
        if (class_exists('gamipress'))  $reward_list = socialv_user_reward_list(array('user_id' => $user_id));

        $account_privacy = get_user_meta($user_id, "socialv_user_account_type", true);

        $member_details = [
            "id"                    => $user_id,
            "member_avtar_image"    => $user_avatar_url,
            "member_cover_image"    => !empty($cover_image_url) ? $cover_image_url : "",
            "name"                  => bp_core_get_user_displayname($user_id),
            "mention_name"          => bp_core_get_username($user_id),
            "email"                 => bp_core_get_user_email($user_id),
            "is_user_verified"      => sv_is_user_verified($user_id),
            "friends_count"         => (int) friends_get_friend_count_for_user($user_id),
            "post_count"            => !empty($total_posts) ? (int) $total_posts[0]->count : 0,
            "account_type"          => $account_privacy ? $account_privacy : "public",
            "groups_count"          => (int) bp_get_total_group_count_for_user($user_id),
            "friendship_status"     => ($user_id == $current_user_id) ? "current_user" : $friendship_status, //"requested/friends/null" relation between between login user and other member
            "profile_info"          => $profile_info,
            "blocked_by_me"         => imt_is_blocked_by_me($user_id, $current_user_id),
            "blocked_by"            => imt_is_blocked_by_me($current_user_id, $user_id),
            "highlight_story"       => sv_story_instance()->sv_get_user_public_stories($user_id),
            "reward_list"           => $reward_list
        ];

        return comman_custom_response([
            "status" => true,
            "message" => __("Member details", SOCIALV_API_TEXT_DOMAIN),
            "data" => $member_details
        ]);
    }

    public function socialv_get_member_friends_list($request)
    {
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $friends_list = [];

        $user_id = $parameters["user_id"];
        $per_page = $parameters['per_page'];
        $page = $parameters['page'];
        $search_terms = !empty($parameters["search_terms"]) ? $parameters["search_terms"] : "active";
        $search_terms = !empty($parameters["search_terms"]) ? $parameters["search_terms"] : "";
        $args = [
            'user_id'       => $user_id,
            'type'          => $search_terms,
            'per_page'      => $per_page,
            'page'          => $page,
            'search_terms'  => $search_terms
        ];
        $friends_list = sv_get_member_list($user_id, $args);

        return comman_custom_response([
            "status" => true,
            "message" => __("Member Friend List", SOCIALV_API_TEXT_DOMAIN),
            "count"=> (int) friends_get_total_friend_count($user_id),
            "data" => $friends_list
        ]);
    }

    public function socialv_get_friendship_request_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $requests_list = [];

        $per_page = $parameters['per_page'];
        $page = $parameters['page'];

        $args = [
            'include'       => bp_get_friendship_requests($current_user_id),
            'per_page'      => $per_page,
            'page'          => $page,
        ];

        $requests_list = sv_get_member_list($current_user_id, $args, "friendship_request");

        return comman_custom_response([
            "status" => true,
            "message" => __("List of Friend Request", SOCIALV_API_TEXT_DOMAIN),
            "data" => $requests_list
        ]);
    }

    public function socialv_get_friend_request_sent_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $requests_list = [];

        $per_page = $parameters['per_page'];
        $page = $parameters['page'];
        $include_ids = get_sent_request_user_ids($current_user_id);

        if (empty($include_ids))
            return comman_custom_response([
                "status" => true,
                "message" => __("List of Sent Friendsip", SOCIALV_API_TEXT_DOMAIN),
                "data" => $requests_list
            ]);

        $args = [
            'include'       => $include_ids,
            'per_page'      => $per_page,
            'page'          => $page,
        ];
        $requests_list = sv_get_member_list($current_user_id, $args, "friendship_request");

        return comman_custom_response([
            "status" => true,
            "message" => __("List of Sent Friendsip", SOCIALV_API_TEXT_DOMAIN),
            "data" => $requests_list
        ]);
    }

    public function socialv_get_dashboard($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        global $sv_app_settings;
        $prefix = $this->sv_option_prefix;

        $visibility_levels      = buddypress()->profile->visibility_levels;
        $dashboard              = [];
        $visibilities           = [];
        $story_allowed_types    = [];
        $is_highlight_story     = 0;
        $story_actions          = [];

        foreach ($visibility_levels as $level) {
            $visibilities[] = $level;
        }

        //gamipress dependancy
        if (class_exists("Gamipress")) {
            $is_game_enable  = 1;
        } else {
            $is_game_enable  = 0;
        }

        // woocommerce dependancy
        if (class_exists("WooCommerce")) {
            $is_woo_enable  = 1;
            $woo_currency   = get_woocommerce_currency_symbol();
            $is_shop_enable = (int) SVSettings::sv_get_option("is_shop_enable");
        } else {
            $is_woo_enable  = 0;
            $is_shop_enable = 0;
            $woo_currency   = "";
        }

        //LMS dependancy 
        if (class_exists("LearnPress")) {
            $is_lms_enable  = 1;
            $is_course_enable = (int) SVSettings::sv_get_option("is_course_enable");
            $lms_currency   = learn_press_get_currency_symbol(learn_press_get_currency());
        } else {
            $is_lms_enable      = 0;
            $is_course_enable   = 0;
            $lms_currency       = "";
        }

        // wp story dependancy
        if (class_exists("Wpstory_Premium_Helpers")) {
            $story_allowed_types    = array_values(wpstory_premium_helpers()->get_allowed_file_types('array'));

            if (WPSTORY()->options('buddypress_public_stories'))
                $is_highlight_story = 1;

            $story_actions = [
                [
                    "action"    => "draft",
                    "name"      => "Draft"
                ],
                [
                    "action"    => "trash",
                    "name"      => "Trash"
                ],
                [
                    "action"    => "delete",
                    "name"      => "Delete"
                ]
            ];
        }

        // GIPHY dependency
        if (class_exists("BuddyPress_GIPHY")) {
            $prefix         = $this->sv_option_prefix;
            $giphy_key      = isset($sv_app_settings[$prefix . "giphy_api_key"]) ? $sv_app_settings[$prefix . "giphy_api_key"] : "";
            $ios_giphy_key  = isset($sv_app_settings[$prefix . "giphy_ios_api_key"]) ? $sv_app_settings[$prefix . "giphy_ios_api_key"] : "";
        } else {
            $giphy_key      = "";
            $ios_giphy_key  = "";
        }

        // better message websocket
        if (function_exists("Better_Messages_WebSocket")) {
            $user_name = bp_core_get_user_displayname($current_user_id);
            $encrypted["user_name"] = sv_encrypt_message_for_user($user_name, $current_user_id);
            $user_avatar_url = bp_core_fetch_avatar(
                array(
                    'item_id' => $current_user_id,
                    'type'    => 'full',
                    'no_grav' => true,
                    'html'    => FALSE     // FALSE = return url, TRUE (default) = return img html
                )
            );
            $is_websocket_enable = 1;
            $encrypted["user_avatar"] = sv_encrypt_message_for_user($user_avatar_url, $current_user_id);
            $unread_messages_count = Better_Messages()->functions->get_total_threads_for_user($current_user_id, 'unread');
        } else {
            $encrypted = [];
            $unread_messages_count = 0;
            $is_websocket_enable = 0;
        }

        // Iqonic Reaction dependency
        $is_reaction_enable = (int) is_reaction_active();

        $report_types       = socialv_get_report_types();

        if (sv_is_user_verified($current_user_id)) {
            $varification_status = "accepted";
        } else if (sv_is_already_request_sent($current_user_id)) {
            $varification_status = "pending";
        } else {
            $varification_status = '';
        }

        $account_privacy            = socialv_get_account_privacy_settings("account_privacy");
        $account_privacy_visibility = [];
        foreach ($account_privacy["field"]["types"] as $key => $label) {
            $account_privacy_visibility[] = ["id" => $key, "label" => $label];
        }
        $suggested_user     = new SVFriendSuggestions($current_user_id);
        $group_suggestions  = new SVGorupSuggestions();
        $args = [
            'per_page'          => 5,
            'page'              => 1,
            'current_user_id'   => $current_user_id
        ];

        // profile
        $display_post_count         = SVSettings::sv_get_theme_dependent_options("posts_count");
        $display_comments_count     = SVSettings::sv_get_theme_dependent_options("comments_count");
        $display_profile_views      = SVSettings::sv_get_theme_dependent_options("profile_views");
        $display_friend_request_btn = SVSettings::sv_get_theme_dependent_options("friend_request_btn");

        $dashboard = [
            "notification_count"            => (int) SVNotifications::socialv_notifications_get_unread_notification_count($current_user_id),
            "unread_messages_count"         => $unread_messages_count,
            "verification_status"           => $varification_status,
            "is_gamipress_enable"           => $is_game_enable,
            "is_woocommerce_enable"         => $is_woo_enable,
            "is_shop_enable"                => $is_shop_enable,
            "woo_currency"                  => $woo_currency,
            "is_lms_enable"                 => $is_lms_enable,
            "is_course_enable"              => $is_course_enable,
            "lms_currency"                  => $lms_currency,
            "is_reaction_enable"            => $is_reaction_enable,
            "story_allowed_types"           => $story_allowed_types,
            "is_highlight_story_enable"     => $is_highlight_story,
            "display_post_count"            => (int) ($display_post_count == 1 || $display_post_count == "yes"),
            "display_comments_count"        => (int) ($display_comments_count == 1 || $display_comments_count == "yes"),
            "display_profile_views"         => (int) ($display_profile_views == 1 || $display_profile_views == "yes"),
            "display_friend_request_btn"    => (int) ($display_friend_request_btn == 1 || $display_friend_request_btn == "yes"),
            "story_actions"                 => $story_actions,
            "giphy_key"                     => $giphy_key,
            "ios_giphy_key"                 => $ios_giphy_key,
            "account_privacy_visibility"    => $account_privacy_visibility,
            "visibilities"                  => $visibilities,
            "report_types"                  => $report_types,
            "suggested_user"                => $suggested_user->sv_get_friend_suggestions($args),
            "suggested_groups"              => $group_suggestions->get_suggestions_list($args),
            "encrypted_data"                => $encrypted,
            "is_websocket_enable"           => $is_websocket_enable
        ];


        return comman_custom_response([
            "status" => true,
            "message" => __("Dashbord", SOCIALV_API_TEXT_DOMAIN),
            "data" => $dashboard
        ]);
    }

    public function socialv_get_profile_fields($request)
    {

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $groups = $fields_group = $field_options = [];
        $type_with_options = [
            'checkbox',
            'datebox',
            'multiselectbox',
            'selectbox',
            'radio'
        ];

        $args = ["hide_empty_fields" => false];

        $user_id = $parameters['user_id'] ?? false;
        if ($user_id)
            $args['user_id'] = $user_id;

        $signup_fields_only  = $parameters['sign_up_only'] ?? false;
        if ($signup_fields_only)
            $args["signup_fields_only"] = true;

        if (bp_has_profile($args)) :

            while (bp_profile_groups()) : bp_the_profile_group();

                $fields_group = [
                    "group_id"      => bp_get_the_profile_group_id(),
                    "group_name"    => bp_get_the_profile_group_name()
                ];

                while (bp_profile_fields()) : bp_the_profile_field();
                    $field_id = bp_get_the_profile_field_id();
                    if (in_array(bp_get_the_profile_field_type(), $type_with_options)) {
                        $field_options = socialv_get_profile_field_options($field_id);
                    }
                    $fields_group["fields"][] = [
                        "id"            => $field_id,
                        "type"          => bp_get_the_profile_field_type(),
                        "label"         => bp_get_the_profile_field_name(),
                        "value"         => bp_get_the_profile_field_edit_value(),
                        "options"       => $field_options,
                        "is_required"   => bp_get_the_profile_field_is_required()
                    ];


                endwhile;

                $groups[] = $fields_group;

            endwhile;

        endif;


        return comman_custom_response([
            "status" => true,
            "message" => __("Member Profile Fields", SOCIALV_API_TEXT_DOMAIN),
            "data" => $groups
        ]);
    }

    public function socialv_update_profile_settings($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $status_code = false;
        $message = esc_html__("Something Wrong. Try again !", SOCIALV_API_TEXT_DOMAIN);
        foreach ($parameters["fields"] as $field) {
            $field_id = $field['id'];
            $old_value = xprofile_get_field_data($field_id, $current_user_id);
            $new_value = $field["value"];
            if (!empty($new_value) && $old_value != $new_value) {
                $field_updated = xprofile_set_field_data($field_id, $current_user_id, $new_value, $field["is_required"]);
                if ($field_updated) {
                    $status_code = true;
                    $message = esc_html__("Profile updated.", SOCIALV_API_TEXT_DOMAIN);
                }
            }
        }

        return comman_custom_response([
            "status" => $status_code,
            "message" => $message
        ]);
    }

    public function socialv_get_profile_visibility_settings($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $field_group = $groups = [];
        $account_privacy_fields = socialv_get_account_privacy_settings("account_privacy");

        $account_privacy = get_user_meta($current_user_id, $account_privacy_fields["field"]["key"], true);
        $account_privacy = $account_privacy ? $account_privacy : "public";
        $groups[] = [
            "group_name"    => $account_privacy_fields["group_name"],
            "group_type"    => "static_settings",
            "fields"        => [
                [
                    "id"            => 1,
                    "name"          => $account_privacy_fields["field"]["name"],
                    "visibility"    => $account_privacy_fields["field"]["types"][$account_privacy],
                    "level"         => $account_privacy,
                    "key"           => $account_privacy_fields["field"]["key"],
                    "can_change"    => true
                ]
            ]
        ];

        if (bp_xprofile_get_settings_fields(["user_id" => $current_user_id])) :

            while (bp_profile_groups()) : bp_the_profile_group();

                if (bp_profile_fields()) :

                    $field_group = [
                        "group_name"    => bp_get_the_profile_group_name(),
                        "group_type"    => "dynamic_settings"
                    ];

                    while (bp_profile_fields()) : bp_the_profile_field();

                        $field_group["fields"][] = [
                            "id"            => bp_get_the_profile_field_id(),
                            "name"          => bp_get_the_profile_field_name(),
                            "visibility"    => bp_get_the_profile_field_visibility_level_label(),
                            "level"         => bp_get_the_profile_field_visibility_level(),
                            "can_change"    => bp_user_can($current_user_id, 'bp_xprofile_change_field_visibility')
                        ];

                    endwhile;

                    $groups[] = $field_group;

                endif;

            endwhile;

        endif;

        return comman_custom_response([
            "status" => true,
            "message" =>  __("Profile Visiblity settings", SOCIALV_API_TEXT_DOMAIN),
            "data" => $groups
        ]);
    }

    public function socialv_save_profile_visibility_settings($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $message = esc_html__("Something wrong. Try again.", SOCIALV_API_TEXT_DOMAIN);
        $status_code = false;
        foreach ($parameters["fields"] as $field) {
            $can_change = $field["can_change"];
            if ($parameters["group_type"] == "static_settings" && $can_change) {
                $key = $field["key"];
                $value = $field["level"];
                if (update_user_meta($current_user_id, $key, $value)) {
                    $message = esc_html__("Settings saved.", SOCIALV_API_TEXT_DOMAIN);
                    $status_code = true;
                }
            } else if ($can_change) {
                $field_id = $field["id"];
                $level = $field["level"];
                $old_level = xprofile_get_field_visibility_level($field_id, $current_user_id);
                if ($level != $old_level) {
                    xprofile_set_field_visibility_level($field_id, $current_user_id, $level);
                }


                $message = esc_html__("Settings saved.", SOCIALV_API_TEXT_DOMAIN);
                $status_code = true;
            }
        }

        // return $message;
        return comman_custom_response([
            "status" => $status_code,
            "message" =>  $message
        ]);
    }

    public function socialv_get_user_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $type = isset($parameters["type"]) ? $parameters["type"] : "suggested";

        $suggested_user = new SVFriendSuggestions($current_user_id);
        $args = [
            'per_page'      => $parameters["per_page"],
            'page'          => $parameters["page"],
        ];

        if ("suggested" == $type)
            $suggested_users = $suggested_user->sv_get_friend_suggestions($args);

        return comman_custom_response([
            "status" => true,
            "message" =>  __("User friend suggestion list", SOCIALV_API_TEXT_DOMAIN),
            "data" => $suggested_users
        ]);
    }
    public function socialv_refuse_user_suggestion($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (sv_refused_friend_suggestions($current_user_id, $parameters["refuse_id"]))
            return comman_custom_response([
                "status" => true,
                "message" =>  __("Suggestion removed", SOCIALV_API_TEXT_DOMAIN)
            ]);
        else
            return comman_custom_response([
                "status" => false,
                "message" =>  __("Something Wrong. Try Again !", SOCIALV_API_TEXT_DOMAIN)
            ]);
    }

    public function socialv_refuse_group_suggestion($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $args = [
            "current_user_id" => $current_user_id,
            "suggestion_id" => $parameters["refuse_id"]
        ];
        if (SVGorupSuggestions::sv_hide_group_suggestion($args))
            return comman_custom_response([
                "status" => true,
                "message" =>  __("Suggestion removed", SOCIALV_API_TEXT_DOMAIN)
            ]);
        else
            return comman_custom_response([
                "status" => false,
                "message" =>  __("Something Wrong. Try Again !", SOCIALV_API_TEXT_DOMAIN)
            ]);
    }
    public function socialv_update_active_status($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        if (class_exists("BP_Core_User")) {
            BP_Core_User::update_last_activity($current_user_id, bp_core_current_time());
        }

        return comman_custom_response([
            "status" => true,
            "message" =>  __("Status updated.", SOCIALV_API_TEXT_DOMAIN)
        ]);
    }

    public function socialv_resend_actiavtion_key($request)
    {
        global $wpdb;

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (empty($parameters['user_email'])) return comman_custom_response([
            "status" => false,
            "message" =>  __("email field is empty", SOCIALV_API_TEXT_DOMAIN),
            "data" => []
        ]);

        $email = $parameters['user_email'];
        if (!email_exists($email)) return comman_custom_response([
            "status" => false,
            "message" =>  __("email is not exist", SOCIALV_API_TEXT_DOMAIN),
            "data" => []
        ]);


        $user = get_user_by('email', $email);
        $user_id = $user->ID;
        $user_status = get_user_meta($user_id, 'user_status', true);

        if (!$user_status) {
            return comman_custom_response([
                "status" => false,
                "message" =>  __("Account is already activated.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }

        // Get user's login as the default salutation
        $salutation = $user->user_login;
        // Check if xProfile is active and a specific field is set
        if (bp_is_active('xprofile') && function_exists('bp_xprofile_fullname_field_id')) {
            $fullname_field_id = bp_xprofile_fullname_field_id();

            if (isset($user->ID) && bp_has_profile(array('user_id' => $user->ID, 'field' => $fullname_field_id))) {
                // Get the value of the xProfile full name field
                $profile_data = bp_get_profile_field_data(['field' => $fullname_field_id]);
                if (!empty($profile_data)) {
                    $salutation = $profile_data;
                }
            }
        }
        $activation_key = sv_get_activation_key_by_email($email);
        if (!$activation_key) {
            $activation_key = wp_generate_password(32, false);
            if (bp_update_user_meta($user_id, 'activation_key', $activation_key)) {
                $table_name = $wpdb->prefix . 'signups';
                $data = array(
                    'user_id' => $user_id,
                    'activation_key' => $activation_key,
                );
                $format = array('%d',  '%s');
                $wpdb->insert($table_name, $data, $format);
            }
        }
        if ($activation_key) {
            bp_core_signup_send_validation_email($user_id, $email, $activation_key, $salutation);
            return comman_custom_response([
                "status" => true,
                "message" =>  __("verification key resend successfully.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }
        return comman_custom_response([
            "status" => false,
            "message" =>  __("something wrong.", SOCIALV_API_TEXT_DOMAIN)
        ]);
    }
    public function socialv_user_earnings($request)
    {

        $user_id = $request->get_param("user_id");
        $user = get_userdata($user_id);
        if (!$user)
            return comman_custom_response([
                "status"    => true,
                "message"   =>  __("User not found.", SOCIALV_API_TEXT_DOMAIN),
                "data"      => []
            ]);
        $achievement = sv_gamipress_user_achievements($user_id);
        return comman_custom_response([
            "status"    => true,
            "message"   =>  __("User earnings.", SOCIALV_API_TEXT_DOMAIN),
            "data"      => [
                "points"            => sv_gamipress_user_points($user_id),
                "rank"              => sv_gamipress_user_ranks($user_id),
                "achievement"       => $achievement["achievements"],
                "achievement_count" => $achievement["count"]
            ]
        ]);
    }
    public function socialv_user_earned_achievements($request)
    {

        $parameters = svRecursiveSanitizeTextField($request->get_params());

        $user_id    = $parameters["user_id"];
        $per_page   = $parameters["per_page"];
        $page       = $parameters["page"];
        $user       = get_userdata($user_id);
        if (!$user)
            return comman_custom_response([
                "status"    => true,
                "message"   =>  __("User not found.", SOCIALV_API_TEXT_DOMAIN),
                "data"      => []
            ]);

        $achievements = sv_gamipress_user_achievements($user_id, ["page" => $page, "posts_per_page" => $per_page]);
        $achievements = $achievements["achievements"] ?? "";
        
        if (empty($achievements))
            return comman_custom_response([
                "status"    => true,
                "message"   =>  __("No data found.", SOCIALV_API_TEXT_DOMAIN),
                "data"      => []
            ]);

        return comman_custom_response([
            "status"    => true,
            "message"   =>  __("User achievements.", SOCIALV_API_TEXT_DOMAIN),
            "data"      =>  $achievements,
        ]);
    }
}
