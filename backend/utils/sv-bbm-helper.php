<?php
use Includes\Settings\SVSettings;

function sv_update_setting_response($settings)
{
    $user_id  = Better_Messages()->functions->get_current_user_id();
    $key      = "socialv_chat_background";
    // print_r($settings);
    $user_meta = get_user_meta($user_id, $key, true);
    if ($user_meta) {
        $settings["chat_background"] = $user_meta;
    }

    foreach ($settings as $setting) {
        if ($setting["id"] == "bm_blocked_users") {
            if (!empty($setting["users"])) {
                foreach ($setting["users"] as $user_id => $timestamp) {
                    $user_avatar_url = bp_core_fetch_avatar(
                        array(
                            'item_id' => $user_id,
                            'type'    => 'full',
                            'html'    => FALSE     // FALSE = return url, TRUE (default) = return img html
                        )
                    );

                    $user[] = [
                        "id"                    => $user_id,
                        "member_avtar_image"    => $user_avatar_url,
                        "member_cover_image"    => !empty($cover_image_url) ? $cover_image_url : "",
                        "name"                  => bp_core_get_user_displayname($user_id),
                        "mention_name"          => bp_core_get_username($user_id),
                        "email"                 => bp_core_get_user_email($user_id),
                        "is_user_verified"      => sv_is_user_verified($user_id),
                    ];
                }
                $setting["user"] = $user;
            }
        }
        $data[] = $setting;
    }
    $response = $data;

    return $response;
}
add_filter("better_messages_user_config", "sv_update_setting_response", 12);

// add online status in threads user object
function sv_online_user($item, $user_id, $include_personal)
{

    if (Better_Messages()->websocket) {
        $online = Better_Messages()->websocket->get_online_users();
        if ($user_id == get_current_user_id())
            return $item;

        if (in_array($user_id, $online)) {
            $item["online_status"] = "online";
        } else {
            $currentTime = current_time('timestamp');
            $activityString = sprintf(__("Active %s ago", "socialv-api"), human_time_diff(strtotime($item["lastActive"]), $currentTime));

            $item["online_status"]  = $activityString;
        }
    }

    return $item;
}
add_filter("better_messages_rest_user_item", "sv_online_user", 10, 3);

function sv_add_better_message_secret_key($data, $user)
{
    if (!function_exists("Better_Messages_WebSocket")) return $data;

    $site_id                = Better_Messages_WebSocket()->site_id;
    $secret_key             = sha1($site_id . Better_Messages_WebSocket()->secret_key . $user->ID);
    $data['bm_secret_key']  = $secret_key;

    return $data;
}
add_filter('jwt_auth_token_before_dispatch', "sv_add_better_message_secret_key", 10, 2);

add_filter("better_messages_rest_message_meta", function ($meta, $message_id, $thread_id, $message) {

    if (!function_exists("Better_Messages_WebSocket")) return $meta;

    $thread_key                 = Better_Messages()->functions->get_thread_meta($thread_id, 'secret_key');
    $meta["encrypted_message"]  = BPBM_AES256::encrypt($message, $thread_key);

    return $meta;
}, 10, 4);

function sv_encrypt_message_for_user($message, $user_id)
{
    if (Better_Messages()->settings['encryptionEnabled'] !== '1') {
        return $message;
    }

    if (!function_exists("Better_Messages_WebSocket")) return $message;

    $secret_key = Better_Messages_WebSocket()->get_user_secret_key($user_id);
    return BPBM_AES256::encrypt($message, $secret_key);
}

function add_secret_key($thread_item, $thread_id)
{
    $key           = "socialv_chat_background";
    $thread_background = Better_Messages()->functions->get_thread_meta($thread_id, $key);
    if ($thread_background)
        $thread_item["chat_background"] = $thread_background;

    if (!function_exists("Better_Messages_WebSocket")) return $thread_item;

    $thread_key = Better_Messages_WebSocket()->get_thread_secret_key($thread_id, 'secret_key');

    $thread_item["secret_key"] = $thread_key;

    return $thread_item;
}
add_filter("better_messages_rest_thread_item", "add_secret_key", 10, 2);

function better_messages_rest_user_item_update($item, $user_id)
{
   
    if(empty(trim($item['name']))){
        $item['name'] = SVSettings::sv_get_option('default_user_display_name');
    }
    return $item;
}
add_filter("better_messages_rest_user_item", "better_messages_rest_user_item_update", 10, 2);

add_filter("bp_better_messages_overwrite_settings", function ($settings) {
    $settings['encryptionEnabled'] = 0;
    return $settings;
});

function sv_get_bm_settings()
{
    if (function_exists("Better_Messages_Options")) {
        $options = Better_Messages_Options();
        $settings = apply_filters('bp_better_messages_overwrite_settings', $options->settings);
        return $settings;
    }
    return "";
}
