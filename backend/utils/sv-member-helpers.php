<?php

function sv_verified_member_instance()
{
    if (!class_exists("BP_Verified_Member")) return false;
    global $sv_verified_member_instance;
    if ($sv_verified_member_instance == null)
        $sv_verified_member_instance = new BP_Verified_Member();
    return $sv_verified_member_instance;
}

function sv_is_user_verified($user_id)
{
    return sv_verified_member_instance()->is_user_verified($user_id);
}


function sv_get_member_list($current_user_id, $args = [], $type = "")
{
    $friends_list = [];
    $is_friend_request = ($type == "friendship_request") ? true : false;

    if (bp_has_members($args)) {
        while (bp_members()) : bp_the_member();
            $user_id = bp_get_member_user_id();

            $user_avatar_url = bp_core_fetch_avatar(
                array(
                    'item_id' => $user_id,
                    'type'    => 'full',
                    'no_grav' => true,
                    'html'    => FALSE     // FALSE = return url, TRUE (default) = return img html
                )
            );

            $list = [
                "user_id"           => $user_id,
                "user_name"         => bp_core_get_user_displayname($user_id),
                "user_mention_name" => bp_get_member_user_login(),
                "user_image"        => $user_avatar_url,
                "is_user_verified"  => sv_is_user_verified($user_id)
            ];
            if ($is_friend_request) {
                $list["request_id"]  = friends_get_friendship_id($user_id, $current_user_id);
            }
            $friends_list[] = $list;

        endwhile;
    }
    return $friends_list;
}

function sv_request_verification($user_id)
{
    global $bp_verified_member_admin;

    if (sv_is_user_verified($user_id))
        return __('Already verified!', "socialv-api");

    if (sv_is_already_request_sent($user_id))
        return __('Already sent!', "socialv-api");

    update_user_meta($user_id, 'bp_verified_member_verification_request', 'pending');

    $unseen_requests = get_transient('bp_verified_member_new_requests');
    if (empty($unseen_requests)) {
        $unseen_requests = array();
    }
    $unseen_requests[] = $user_id;

    set_transient('bp_verified_member_new_requests', array_unique($unseen_requests));

    if ($bp_verified_member_admin->settings->get_option('enable_verification_requests')) {
        // Send notification email
        bp_send_email('bp_verified_member_received_verification_request', get_bloginfo('admin_email'), array(
            'tokens' => array(
                'site.name'      => get_bloginfo('name'),
                'requester.name' => bp_core_get_user_displayname($user_id),
            ),
        ));
    }

    return __('Request Sent!', 'socialv-api');
}

function sv_is_already_request_sent($user_id)
{
    $verification_request_status = get_user_meta($user_id, 'bp_verified_member_verification_request', true);
    return ($verification_request_status === 'pending');
}

add_filter("bp_rest_members_prepare_value", "sv_add_member_additional_data", 10, 3);
function sv_add_member_additional_data($response, $request, $user)
{
    $user_id = $user->ID;
    $response->data['is_user_verified'] = sv_is_user_verified($user_id);
    $response->data['last_active'] = bp_get_last_activity($user_id);
    return $response;
}

if (!function_exists("socialv_get_account_privacy_settings")) {
    function socialv_get_account_privacy_settings($name)
    {
        $static_settings = [
            "account_privacy" => [
                "group_name"    => __("Account Privacy", "socialv-api"),
                "field"         => [
                    "name"  => "Account type",
                    "key"   => "socialv_user_account_type",
                    "types" => [
                        "public"    => __("Public", "socialv-api"),
                        "private"   => __("Private", "socialv-api")
                    ]
                ]
            ]
        ];

        return $static_settings[$name];
    }
}

function socialv_get_user_profile_info($user_id, $current_user_id)
{
    $groups = [];
    $allowed_visibility_levels = ["public", "loggedin"];
    $is_friend = friends_check_friendship($user_id, $current_user_id);
    if ($is_friend)
        array_push($allowed_visibility_levels, "friends");

    if ($user_id == $current_user_id)
        $allowed_visibility_levels = array_merge($allowed_visibility_levels, ["friends", "adminsonly"]);

    if (bp_has_profile(['user_id' => $user_id, "hide_empty_fields" => false])) :

        while (bp_profile_groups()) : bp_the_profile_group();

            $fields_group = [
                "id"      => bp_get_the_profile_group_id(),
                "name"    => bp_get_the_profile_group_name()
            ];

            while (bp_profile_fields()) : bp_the_profile_field();
                $field_id = bp_get_the_profile_field_id();

                if (!in_array(bp_get_the_profile_field_visibility_level(), $allowed_visibility_levels)) continue;

                $fields_group["fields"][] = [
                    "id"            => $field_id,
                    "name"          => bp_get_the_profile_field_name(),
                    "value"         => bp_get_the_profile_field_edit_value(),
                ];

            endwhile;

            $groups[] = $fields_group;

        endwhile;

    endif;

    return $groups;
}

function sv_refused_friend_suggestions($current_user_id, $refused_id)
{
    $refused_suggestions = get_user_meta($current_user_id, 'socialv_refused_friend_suggestions', true);

    if ($refused_suggestions) {
        if (in_array($refused_id, $refused_id))
            return true;

        $refused_suggestions[] = $refused_id;
    } else {
        $refused_suggestions = [];
        $refused_suggestions[] = $refused_id;
    }

    return update_user_meta($current_user_id, "socialv_refused_friend_suggestions", $refused_suggestions);
}

function sv_verify_email($args)
{
    // Hash and store the password.
    $usermeta['password'] = wp_hash_password($args['user_pass']);

    $usermeta = apply_filters('bp_signup_usermeta', $usermeta);

    $wp_user_id = bp_core_signup_user($args['user_login'], $args['user_pass'], $args['user_email'], $usermeta);

    return $wp_user_id;
}

// buddypress rest members per_page validation filter
add_filter("rest_user_collection_params", "sv_rest_members_params");
function sv_rest_members_params($params)
{
    $params["per_page"]["minimum"] = 0;
    return $params;
}


//check activation key
function sv_get_activation_key_by_email($user_email) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'signups';

    $activation_key = $wpdb->get_var(
        $wpdb->prepare("SELECT activation_key FROM $table_name WHERE user_email = %s", $user_email)
    );

    return $activation_key;
}

function socialv_user_reward_list($current_user_id){
    // Get user achievements
    $rewards = gamipress_get_user_achievements($current_user_id);
    
   
    $filtered_rewards = array_map(function ($reward) {
        $image_url = get_post_thumbnail_id($reward->ID);
        return [
            "earned" => $reward->points,
            "type" => $reward->points_type,
            "title" => $reward->title,
            "image" => $image_url,

        ];
    }, $rewards);

    return $filtered_rewards;
}

