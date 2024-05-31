<?php

use Includes\baseClasses\SVActivityComments;
use Includes\Settings\SVSettings;

function sv_default_display_name($name = "")
{
    if (!empty(trim($name))) return $name;

    return SVSettings::sv_get_option('default_user_display_name');
}
add_filter("bp_core_get_user_displayname", "sv_default_display_name");
add_filter("wpstory_author_name", "sv_default_display_name");
function svValidationToken($request)
{
    $data = [
        'message' => 'Valid token',
        'status' => true,
        'status_code' => 200
    ];
    $response = collect((new Jwt_Auth_Public('jwt-auth', '1.1.0'))->validate_token($request, false));

    if ($response->has('errors')) {
        $data['status'] = false;
        $data['message'] = isset(array_values($response['errors'])[0][0]) ? array_values($response['errors'])[0][0] : __("Authorization failed");
    } else {
        // $header = $request->get_headers();
        $data['user_id'] = get_current_user_id(); //$response['data']->user->id;
    }
    return $data;
}

function svValidateRequest($rules, $request, $message = [])
{
    $error_messages = [];
    $required_message = esc_html__(' field is required', 'socialv-api');
    $email_message =  esc_html__(' has invalid email address', 'socialv-api');

    if (count($rules)) {
        foreach ($rules as $key => $rule) {
            if (strpos($rule, '|') !== false) {
                $ruleArray = explode('|', $rule);
                foreach ($ruleArray as $r) {
                    if ($r === 'required') {
                        if (!isset($request[$key]) || $request[$key] === "" || $request[$key] === null) {
                            $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $required_message;
                        }
                    } elseif ($r === 'email') {
                        if (isset($request[$key])) {
                            if (!filter_var($request[$key], FILTER_VALIDATE_EMAIL) || !is_email($request[$key])) {
                                $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $email_message;
                            }
                        }
                    }
                }
            } else {
                if ($rule === 'required') {
                    if (!isset($request[$key]) || $request[$key] === "" || $request[$key] === null) {
                        $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $required_message;
                    }
                } elseif ($rule === 'email') {
                    if (isset($request[$key])) {
                        if (!filter_var($request[$key], FILTER_VALIDATE_EMAIL) || !is_email($request[$key])) {
                            $error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $email_message;
                        }
                    }
                }
            }
        }
    }

    return $error_messages;
}

function svRecursiveSanitizeTextField($array)
{
    $filterParameters = [];
    foreach ($array as $key => $value) {

        if ($value === '') {
            $filterParameters[$key] = null;
        } else {
            if (is_array($value)) {
                $filterParameters[$key] = svRecursiveSanitizeTextField($value);
            } else {
                if (preg_match("/<[^<]+>/", $value, $m) !== 0) {
                    $filterParameters[$key] = $value;
                } else {
                    $filterParameters[$key] = sanitize_text_field($value);
                }
            }
        }
    }

    return $filterParameters;
}

function svGetErrorMessage($response)
{
    return isset(array_values($response->errors)[0][0]) ? array_values($response->errors)[0][0] : __("Internal server error");
}

function sv_upload_avatar($img_url, $user_id)
{
    if (!$img_url || empty($img_url) || !$user_id || empty($user_id)) return false;
    $uploaded_img_type = wp_check_filetype($img_url);

    $is_image_type_allowed = array_intersect($uploaded_img_type, bp_core_get_allowed_avatar_mimes());
    if (empty($is_image_type_allowed)) return false;
    $user_image = bp_core_fetch_avatar(
        array(
            'item_id'   => $user_id,
            'no_grav'   => true,
            'type'      => 'full',
            'html'      => FALSE     // FALSE = return url, TRUE (default) = return img html
        )
    );

    if ($user_image == bp_core_avatar_default()) {
        $image = wp_get_image_editor($img_url);
        if (!is_wp_error($image)) {
            $avatar_uplod_dir = bp_members_avatar_upload_dir('avatars', $user_id)['path'];

            if (!file_exists($avatar_uplod_dir))
                $path_created = wp_mkdir_p($avatar_uplod_dir);

            $timestamp = bp_core_current_time(true, 'timestamp');

            $image->resize(BP_AVATAR_FULL_WIDTH, BP_AVATAR_FULL_HEIGHT);
            $image->save($avatar_uplod_dir . "/" . $timestamp . "-bpfull." . $uploaded_img_type['ext']);

            $image->resize(BP_AVATAR_THUMB_WIDTH, BP_AVATAR_THUMB_HEIGHT, false);
            $image->save($avatar_uplod_dir . "/" . $timestamp . "-bpthumb." . $uploaded_img_type['ext']);
            return true;
        }
    }
    return false;
}

function svGenerateString($length_of_string = 10)
{
    // String of all alphanumeric character
    $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    return substr(str_shuffle($str_result), 0, $length_of_string);
}

function sv_comman_message_response($message, $status_code = 200)
{
    $response = new WP_REST_Response(
        array(
            "message" => $message
        )
    );
    $response->set_status($status_code);
    return $response;
}

function sv_comman_custom_response($res, $status_code = 200)
{
    $response = new WP_REST_Response($res);
    $response->set_status($status_code);
    return $response;
}

function sv_comman_list_response($data, $message = false, $status = true)
{
    $response = new WP_REST_Response(array(
        "status"    => $status,
        "message"   => $message ? $message : __("Nothing Found", SOCIALV_API_TEXT_DOMAIN),
        "data"      => $data
    ));

    $response->set_status(200);
    return $response;
}

function comman_custom_response($res, $status_code = 200)
{
    $response = new WP_REST_Response($res);
    $response->set_status($status_code);
    return $response;
}

function is_dependent_theme_active()
{
    $active_theme = function_exists("wp_get_theme") ? wp_get_theme() : "";
    $active_theme = $active_theme ? strtolower($active_theme->name) : "";
    return strpos(" " . $active_theme, "socialv");
}

function is_dependent_plugin_active($plugin_dir_name, $plugin_file_name)
{
    return is_plugin_active($plugin_dir_name . "/" . $plugin_file_name);
}

add_filter("bp_core_fetch_avatar_no_grav", function ($no_grav) {
    return true;
});

function change_bp_default_avatar_for_user($url, $params)
{

    $social_avatar = isset($params['item_id']) ? get_user_meta($params['item_id'], 'sv_social_login_avatar', true) : false;
    if ($social_avatar) {
        $url = $social_avatar;
    } else {
        $default_avatar = SVSettings::sv_get_theme_dependent_options("defalt_avatar_img");
        if (isset($default_avatar["url"]) && !empty($default_avatar["url"]))
            $url = $default_avatar["url"];
    }

    return $url;
}
add_filter('bp_core_avatar_default_thumb', 'change_bp_default_avatar_for_user', 10, 2);
add_filter('bp_core_default_avatar', 'change_bp_default_avatar_for_user', 10, 2);
function get_dom_document_instance()
{
    global $sv_dom_document_instance;
    if ($sv_dom_document_instance == null)
        $sv_dom_document_instance = new DOMDocument();

    return $sv_dom_document_instance;
}
function sv_split_text_links($content)
{
    if (empty(trim($content))) return "";

    $doc = get_dom_document_instance();
    //     $doc->loadHTML($content);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $content);
    // check if having anchor tag
    $aTags = $doc->getElementsByTagName('a');
    if (!$aTags->length)
        return "";

    $resultArray = [];

    $pElements = $doc->getElementsByTagName('p');
    $divElements = $doc->getElementsByTagName('div');

    foreach ($pElements as $pElement) {
        if ($pElement->childNodes->length) {
            $resultArray = array_merge($resultArray, processElements($pElement, $doc));
        }
    }

    foreach ($divElements as $divElement) {
        if ($divElement->childNodes->length) {
            $resultArray = array_merge($resultArray, processElements($divElement, $doc));
        }
    }

    return $resultArray;
}


function processElements($elements, $doc)
{
    $resultArray = [];

    foreach ($elements->childNodes as $node) {
        if ($node instanceof DOMElement && $node->nodeName === 'a') {
            $resultArray[] = [
                'is_link' => strpos(" " . $doc->saveHTML($node), "is_mention=1") ? false : true,
                'content' => $doc->saveHTML($node),
            ];
        } elseif ($node instanceof DOMText) {
            $textContent = trim($node->textContent);
            if (!empty($textContent)) {
                $resultArray[] = [
                    'is_link' => false,
                    'content' => $textContent,
                ];
            }
        } elseif ($node instanceof DOMElement && $node->nodeName === 'div') {
            // Handle nested <div> elements
            $nestedDivResult = processElements($node, $doc);
            if (!empty($nestedDivResult)) {
                $resultArray = array_merge($resultArray, $nestedDivResult);
            }
        }
    }

    return $resultArray;
}

function socialv_get_activity_args($args)
{

    $friends_id = [];
    $current_user_id = $args["current_user_id"];
    $user_id = $args["user_id"];
    if (SVSettings::is_friends_only_activity()) {
        if (function_exists('friends_get_friend_user_ids')) {
            $friends_id = friends_get_friend_user_ids($current_user_id);
        }
        // include user's own too?
        array_push($friends_id, $current_user_id);
    }

    $parse_args = [
        "per_page"  => $args["per_page"],
        "page"      => $args["page"]
    ];

    if ("groups" == $args["type"]) {
        $parse_args["primary_id"] = $args["group_id"];
        $parse_args["object"] = "groups";
        $parse_args["scope"] = "home";
        $parse_args["show_hidden"] = true;
    } else if ("timeline" == $args["type"]) {
        $parse_args["user_id"] = [$user_id];
        $parse_args['object'] = 'activity';
        $parse_args['primary_id'] = 'groups';
    } else if ("favorites" == $args["type"]) {
        $parse_args["user_id"] = $user_id;
        $parse_args["scope"] = "favorites";
        $parse_args["object"] = "activity";
    } else if ("single-activity" == $args["type"]) {
        $parse_args['include'] = $args["activity_id"];
        $parse_args["show_hidden"] = true;
    } else {
        $parse_args["user_id"] = $friends_id;
    }

    return $parse_args;
}
function sv_get_pinned_activity($user_id = 0)
{
    if (!$user_id) {
        $user =   wp_get_current_user();
        $user_id = $user->ID;
    }

    $pinned_activity = get_user_meta($user_id, "_socialv_user_pinned_activity", true);

    return $pinned_activity;
}
function sv_add_pinned_activity($args)
{
    $activities = [];
    if (isset($args['page']) && $args['page'] > 1) {
        return;
    }

    if (!isset($args['in']) || empty($args['in'])) {
        return;
    }
    // Get Sticky Posts ID's.
    $posts_ids__in = $args['in'];

    $type = $args["type"];
    $current_user_id = $args["current_user_id"];
    $group_id = $args["group_id"] ?? 0;

    $args = [
        "is_reaction_enable"    => $args["is_reaction_enable"],
        "current_user_id"       => $current_user_id,
        "default_username"      => $args["default_username"],
        "is_pinned"             => 1
    ];
    $query_args = array(
        'in'                => $posts_ids__in,
        'per_page'          => count(explode(',', $posts_ids__in)),
        'show_hidden'       => 1,
        'display_comments'  => 'threaded'
    );
    if ($type == "timeline") {
        $query_args["user_id"] = $current_user_id;
        $query_args['object'] = 'activity';
        $query_args['primary_id'] = 'groups';
    } elseif ($type ==  "favorites") {
        $query_args["user_id"] = $current_user_id;
        $query_args["scope"] = "favorites";
        $query_args["object"] = "activity";
    } elseif ($type == "groups") {
        $query_args["primary_id"] = $group_id;
        $query_args['object'] = 'groups';
    }

    $activities = sv_get_activity_obj($query_args, $args);

    return $activities;
}

function socialv_get_activity_post($args = [])
{
    $current_user_id = $args["current_user_id"];
    $post_in = "newsfeed";
    if ("groups" == $args['type']) {

        $group = bp_get_group($args["group_id"]);

        if ("private" == $group->status && !groups_is_user_member($current_user_id, $args["group_id"]))
            return "private-group";

        $post_in = $group->name;
    }

    $is_reaction_enable     = is_reaction_active();
    $query_args = socialv_get_activity_args($args);
    $pinned_activities = false;
    $posts_ids = sv_get_pinned_activity($current_user_id);
    if ($current_user_id != $args["user_id"] && $args['type'] != "newsfeed")
        $posts_ids = "";

    if (!empty($posts_ids) && $args['type'] != "single-activity") {
        $query_args["exclude"] = $posts_ids;
        $args["is_reaction_enable"] = $is_reaction_enable;
        $pinned_activities = sv_add_pinned_activity(array_merge($args, ["in" => $posts_ids]));
    }

    if ($post_in == "newsfeed") {
        $exclude = get_user_meta($current_user_id, "_socialv_activity_hiden_by_user", true);

        if ($exclude)
            $exclude = implode(",", $exclude);

        if (isset($query_args["exclude"]))
            $exclude = $query_args["exclude"] . "," . $exclude;

        $query_args["exclude"] = $exclude;
    }

    $include_actions = "activity_update,mpp_media_upload,activity_share";

    if (SVSettings::is_blog_post_enable())
        $include_actions .= ",new_blog_post";

    $query_args['action']   = $include_actions;
    $default_args = [
        "is_reaction_enable"    => $is_reaction_enable,
        "current_user_id"       => $current_user_id,
        "default_username"      => SVSettings::sv_get_option('default_user_display_name')
    ];

    $activities = sv_get_activity_obj($query_args, $default_args);

    if (is_array($pinned_activities))
        $activities = array_merge($pinned_activities, $activities);

    return $activities;
}

// set BP_Embed instance to the constant
add_action("bp_core_setup_oembed", function ($bp_embed_obj_array) {
    define("SV_BP_EMBED", json_encode($bp_embed_obj_array));
});

function sv_get_activity_obj($query_args, $args = [])
{
    $activities = [];

    $args = wp_parse_args($args, [
        "is_reaction_enable"    => false,
        "current_user_id"       => get_current_user_id(),
        "default_username"      => __("SocialV User", "socialv-api"),
        "is_pinned"             => 0
    ]);

    if (bp_has_activities($query_args)) :
        remove_filter('bp_get_activity_content_body', array(json_decode(SV_BP_EMBED), 'autoembed'), 8);
        add_filter('wp_kses_allowed_html', 'theme_slug_kses_allowed_html', 10, 2);
        remove_filter('bp_get_activity_content_body', 'bp_activity_truncate_entry', 5);
        remove_filter('bp_get_activity_content', 'bp_activity_truncate_entry', 5);

        while (bp_activities()) : bp_the_activity();

            global $activities_template;

            $activity_id        = bp_get_activity_id();
            $content            = bp_get_activity_content_body();
            $usernames          = bp_activity_find_mentions($content);

            if (!empty($usernames)) {
                // Replace @mention text with userlinks.
                foreach ((array) $usernames as $user_id => $username) {
                    $link = add_query_arg("user_id", $user_id, bp_core_get_user_domain($user_id));
                    $link = add_query_arg("is_mention", "1", $link);

                    $content = str_replace(bp_core_get_user_domain($user_id), $link, $content);
                }
                $has_mentions = 1;
            } else {
                $has_mentions = 0;
            }

            $activity_action        = bp_get_activity_type();
            $blog_id                = bp_get_activity_secondary_item_id();
            $media_list             = [];
            $media_list_with_ids    = [];
            $meta                   = bp_activity_get_meta($activity_id);
            $type                   = "activity_update";

            if (in_array($activity_action, ["new_blog_post", "activity_share"]))
                $type = $activity_action;


            if ($meta) {
                // if has gif
                $has_gif = isset($meta["_bp_activity_gif_data"]) ? true : false;
                $gif = "";
                if ($has_gif) {
                    $gif_data = maybe_unserialize($meta["_bp_activity_gif_data"][0]);
                    $gif = $gif_data["bp_activity_gif"];
                    if (!empty($gif)) {
                        $media_type     = "gif";
                        $media_list[]   = $gif;
                        $media_list_with_ids[] = [
                            "id"    => (int) $activity_id,
                            "url"   => $gif,
                            "type"  => $media_type
                        ];
                    }
                    $content = wp_kses($content, 'no-images');
                } else {
                    $media_attachments  = isset($meta["_mpp_attached_media_id"]) ? $meta["_mpp_attached_media_id"] : [];
                    $media_type         = isset($meta["_mpp_gallery_id"][0]) ? mpp_get_gallery_type($meta["_mpp_gallery_id"][0]) : "";

                    foreach ($media_attachments as $media_attachment_id) {
                        $data = [];
                        $oembed_source      = get_post_meta($media_attachment_id, "_mpp_oembed_content", true);
                        $attachment_source  = get_post_meta($media_attachment_id, "_mpp_source", true);
                        $data["id"]         = (int) $media_attachment_id;
                        if (!empty($attachment_source) && (strpos($attachment_source, "youtube") || strpos($attachment_source, "youtu.be"))) {
                            $url            = $attachment_source;
                            $media_type     = mpp_get_media_type((int) $media_attachment_id);
                            $data["source"] = "youtube";
                        } else if ($oembed_source) {
                            $url        = $oembed_source;
                            $media_type = "oembed";
                        } else {
                            $url        = wp_get_attachment_url($media_attachment_id);
                            $media_type = mpp_get_media_type((int) $media_attachment_id);
                        }

                        $url                    = $url ? $url : '';
                        $media_list[]           = $url;
                        $data["url"]            = $url;
                        $data["type"]           = $media_type;

                        $data["gallery_id"] = !empty($media_type) ? $meta["_mpp_gallery_id"][0] : 0;

                        $media_list_with_ids[]  = $data;
                    }
                }
            }
            $user_id    = bp_get_activity_user_id();
            $user_name  = bp_core_get_user_displayname($user_id);
            $user_image = bp_core_fetch_avatar(
                array(
                    'item_id'   => $user_id,
                    'no_grav'   => true,
                    'type'      => 'full',
                    'html'      => FALSE     // FALSE = return url, TRUE (default) = return img html
                )
            );

            $comments = new SVActivityComments();
            $comments = $comments->socialv_activity_get_comments($activities_template, true, ["page" => 1, "per_page" => 3, "current_user_id" => $args["current_user_id"]]);

            $users_who_liked    = socialv_activity_liked_users($activity_id);
            $friendship_status  = friends_check_friendship_status((int)$args["current_user_id"], (int)$user_id);

            $component  = $activities_template->activity->component;
            $group_id   = 0;
            $group_name = "";
            $post_in = $component;
            if ($component == "groups") {
                $group_id   = bp_get_activity_item_id();
                $group      = bp_get_group($group_id);
                $group_name = $group->name;
                // $post_in    = $component;
            }
            if ($args["is_reaction_enable"]) {
                $is_liked           = false;
                $like_count         = 0;
                $users_who_liked    = [];
                $cur_user_reaction  = rest_user_reaction($activity_id, $args["current_user_id"], "activity");
                $reactions          = sv_rest_reaction_list($activity_id, "activity");
                $reaction_count     = rest_get_reaction_count("iq_reaction_activity", "WHERE activity_id={$activity_id}");
            } else {
                $is_liked           = socialv_is_user_liked($activity_id, $args["current_user_id"]);
                $like_count         = $users_who_liked['count'];
                $users_who_liked    = $users_who_liked['list'];
                $cur_user_reaction  = null;
                $reactions          = [];
                $reaction_count     = 0;
            }
            $content_object = sv_split_text_links($content);
            $activities[] = [
                "activity_id"           => $activity_id,
                "blog_id"               => $blog_id,
                "content"               => $content,
                "content_object"        => $content_object,
                "has_mentions"          => $has_mentions,
                "post_in"               => $post_in,
                "group_id"              => $group_id,
                "group_name"            => $group_name,
                "user_id"               => $user_id,
                "User_name"             => !empty($user_name) ? $user_name : $args["default_username"],
                "user_email"            => bp_core_get_user_email($user_id),
                "user_image"            => $user_image ? $user_image : "",
                "is_user_verified"      => (int) sv_is_user_verified($user_id),
                "type"                  => $type,
                "media_type"            => $media_type,
                "media_list"            => $media_list,
                "medias"                => $media_list_with_ids,
                "is_liked"              => (int) $is_liked,
                "like_count"            => $like_count,
                "users_who_liked"       => $users_who_liked,
                "reaction_count"        => $reaction_count,
                "cur_user_reaction"     => $cur_user_reaction,
                "reactions"             => $reactions,
                "is_favorites"          => (int) in_array($activity_id, (array) bp_activity_get_user_favorites($args["current_user_id"])),
                "is_friend"             => ("is_friend" == $friendship_status) ? 1 : 0,
                "is_pinned"             => $args["is_pinned"],
                "comment_count"         => bp_activity_get_comment_count(),
                "comments"              => $comments,
                "date_recorded"         => bp_get_activity_date_recorded(),
                "child_post"            => sv_get_child_post($activity_id)
            ];

        endwhile;

    endif;
    return $activities;
}

function sv_get_child_post($activity_id)
{
    $activity = null;
    $child_id = bp_activity_get_meta($activity_id, 'shared_activity_id', true);
    if ($child_id) {
        $child_activity = bp_activity_get(["in" => $child_id, "display_comments" => 0]);
        if (!empty($child_activity["activities"])) {
            $data = $child_activity["activities"][0];

            $activity_id = $data->id;
            $user_id = $data->user_id;
            $content = $data->content;
            $activity_action = $data->action;

            $media_list = [];
            $media_list_with_ids = [];
            $meta = bp_activity_get_meta($activity_id);
            $type = "activity_update";
            if (in_array($activity_action, ["new_blog_post", "activity_share"]))
                $type = $activity_action;


            if ($meta) {
                // if has gif
                $has_gif = isset($meta["_bp_activity_gif_data"]) ? true : false;
                $gif = "";
                if ($has_gif) {
                    $gif_data = maybe_unserialize($meta["_bp_activity_gif_data"][0]);
                    $gif = $gif_data["bp_activity_gif"];
                    if (!empty($gif)) {
                        $media_type = "gif";
                        $media_list[] = $gif;
                        $media_list_with_ids[] = [
                            "id"    => (int) $activity_id,
                            "url"   => $gif,
                            "type"  => $media_type
                        ];
                    }
                    $content = wp_kses($content, 'no-images');
                } else {
                    $media_attachments = isset($meta["_mpp_attached_media_id"]) ? $meta["_mpp_attached_media_id"] : [];

                    $media_type = isset($meta["_mpp_gallery_id"]) && isset($meta["_mpp_gallery_id"][0]) ? mpp_get_gallery_type($meta["_mpp_gallery_id"][0]) : "";

                    foreach ($media_attachments as $media_attachment_id) {
                        $data = [];
                        $oembed_source      = get_post_meta($media_attachment_id, "_mpp_oembed_content", true);
                        $attachment_source  = get_post_meta($media_attachment_id, "_mpp_source", true);
                        $data["id"]         = (int) $media_attachment_id;
                        if (!empty($attachment_source) && (strpos($attachment_source, "youtube") || strpos($attachment_source, "youtu.be"))) {
                            $url            = $attachment_source;
                            $media_type     = mpp_get_media_type((int) $media_attachment_id);
                            $data["source"] = "youtube";
                        } else if ($oembed_source) {
                            $url        = $oembed_source;
                            $media_type = "oembed";
                        } else {
                            $url        = wp_get_attachment_url($media_attachment_id);
                            $media_type = mpp_get_media_type((int) $media_attachment_id);
                        }

                        $url                    = $url ? $url : '';
                        $media_list[]           = $url;
                        $data["url"]            = $url;
                        $data["type"]           = $media_type;
                        $data["gallery_id"] = !empty($media_type) ? $meta["_mpp_gallery_id"][0] : 0;
                        $media_list_with_ids[]  = $data;
                    }
                }
            }

            $user_image = bp_core_fetch_avatar(
                array(
                    'item_id'   => $user_id,
                    'no_grav'   => true,
                    'type'      => 'full',
                    'html'      => FALSE     // FALSE = return url, TRUE (default) = return img html
                )
            );

            $content_object = sv_split_text_links($content);
            $activity = [
                "activity_id"       => $activity_id,
                "content"           => $content,
                "content_object"    => $content_object,
                "user_id"           => $user_id,
                "User_name"         => bp_core_get_user_displayname($user_id),
                "user_email"        => bp_core_get_user_email($user_id),
                "user_image"        => $user_image ? $user_image : "",
                "is_user_verified"  => (int) sv_is_user_verified($user_id),
                "type"              => $type,
                "media_type"        => $media_type,
                "media_list"        => $media_list,
                "medias"            => $media_list_with_ids,

            ];
        }
    }
    return $activity;
}

function socialv_is_user_liked($activity_id, $current_user_id)
{

    $posts = bp_activity_get_meta($activity_id, "_socialv_activity_liked_users", true);
    $post_array = explode(', ', $posts);

    if (in_array($current_user_id, $post_array)) {
        return true;
    }

    return false;
}

function socialv_activity_liked_users($activity_id, $args = [])
{
    $user_ids = bp_activity_get_meta($activity_id, "_socialv_activity_liked_users", true);
    $users_who_liked = ["count" => 0, "list" => []];
    if (!empty($user_ids)) {
        $user_ids = array_reverse(explode(', ', $user_ids));
        $count_likes = count($user_ids);
        $users_who_liked['count'] = $count_likes;
        if ($count_likes > 0) {
            $per_page = 3;
            $page = 1;
            if (!empty($args)) {
                $per_page = $args["per_page"];
                $page = $args["page"];
            }
            $user_ids = array_slice($user_ids, $per_page * ($page - 1), $per_page);
            foreach ($user_ids as $user_id) :
                $user_avatar = bp_core_fetch_avatar(
                    array(
                        'item_id' => $user_id,
                        'no_grav' => true,
                        'type'    => 'full',
                        'html'   => FALSE     // FALSE = return url, TRUE (default) = return img html
                    )
                );
                $user_name = bp_core_get_user_displayname($user_id);
                $users_who_liked['list'][] = [
                    "user_id"           => $user_id,
                    "user_name"         => $user_name ? $user_name : sv_default_display_name(),
                    "user_mention_name" => bp_core_get_username($user_id),
                    "user_avatar"       => $user_avatar ? $user_avatar : "",
                    "is_user_verified"  => sv_is_user_verified($user_id)
                ];
            endforeach;
        }
    }
    return $users_who_liked;
}

function socialv_user_can_upload($user_id, $component, $component_id, $gallery = null)
{
    if (empty($user_id)) return;

    $can_do = false;

    if (is_super_admin($user_id)) {
        $can_do = true;
    } elseif (mediapress()->is_bp_active() && 'members' == $component && $component_id == $user_id) {
        $can_do = true;
    } elseif (mpp_is_active_component('groups') && 'groups' == $component && function_exists('groups_is_user_member') && groups_is_user_member($user_id, $component_id)) {
        $can_do = true;
    } elseif (mpp_is_active_component('sitewide') && 'sitewide' == $component && $component_id == $user_id) {
        $can_do = true;
    }

    $can_do = apply_filters('mpp_user_can_upload', $can_do, $component, $component_id, $gallery);

    return apply_filters("mpp_can_user_upload_to_{$component}", $can_do, $component_id, $gallery);
}

function socialv_has_available_space($component, $component_id)
{

    // how much.
    $allowed_space = mpp_get_allowed_space($component, $component_id);

    $used_space = socialv_get_used_space($component, $component_id) / 1024 / 1024;

    if (($allowed_space - $used_space) <= 0) {
        return false;
    }

    return true;
}

function socialv_get_used_space($component, $component_id)
{

    // get default storage manager.

    // base gallery directory for owner.
    $dir_name = trailingslashit(mpp_local_storage()->get_component_base_dir($component, $component_id));

    if (!is_dir($dir_name) || !is_readable($dir_name)) {
        return 0; // we don't know the usage or no usage.
    }

    $dir  = dir($dir_name);
    $size = 0;

    while ($file = $dir->read()) {

        if ($file !== '.' && $file !== '..') {

            if (is_dir($dir_name . $file)) {
                //     "user_email"    => bp_core_get_user_email($users[$i]),
                $size += socialv_recurse_dirsize($dir_name . $file);
            } else {
                $size += filesize($dir_name . $file);
            }
        }
    }

    $dir->close();

    return $size;
}

function socialv_recurse_dirsize($directory)
{
    $size = 0;

    $directory = untrailingslashit($directory);

    if (!file_exists($directory) || !is_dir($directory) || !is_readable($directory)) {
        return false;
    }

    if ($handle = opendir($directory)) {

        while (($file = readdir($handle)) !== false) {
            $path = $directory . '/' . $file;
            if ($file !== '.' && $file !== '..') {

                if (is_file($path)) {
                    $size += filesize($path);
                } elseif (is_dir($path)) {

                    $handlesize = socialv_recurse_dirsize($path);

                    if ($handlesize > 0) {
                        $size += $handlesize;
                    }
                }
            }
        }
        closedir($handle);
    }

    return $size;
}

function socialv_delete_user_activity($activity, $current_user_id)
{
    if (!socialv_can_delete_activity($activity, $current_user_id))
        return ["status_code" => 422, "message" => esc_html__("Can't delete.", SOCIALV_API_TEXT_DOMAIN)];

    if (bp_activity_delete(array('id' => $activity->id, 'user_id' => $activity->user_id))) {
        $status_code = 200;
        $message = esc_html__("deleted.", SOCIALV_API_TEXT_DOMAIN);
    } else {
        $status_code = 422;
        $message = esc_html__("Something Wrong.", SOCIALV_API_TEXT_DOMAIN);
    }
    return ["status_code" => $status_code, "message" => $message];
}

function socialv_can_delete_activity($activity, $current_user_id)
{
    // Check access.
    if (bp_user_can($current_user_id, "bp_moderate"))
        return true;

    $activity_user = isset($activity->user_id);
    $activity_user_id = $activity_user ? $activity->user_id : '';

    if (!$activity_user || ($activity_user_id != $current_user_id))
        return false;

    return true;
}

function socialv_get_group_members_list($args = [])
{
    $member_list = [];

    $group_id = $args['group_id'];
    $per_page = $args['per_page'];
    $page = $args['page'];
    $return = $args['return'];
    $parse_args = [
        "group_id"              => $group_id,
        "per_page"              => $per_page,
        "page"                  => $page,
        "search_terms"          => $args['search_terms'],
        "exclude_admins_mods"   => false,
    ];

    if (isset($args["current_user_id"]) && groups_is_user_admin($args["current_user_id"], $group_id))
        $parse_args["exclude_banned"] = false;


    $group_members = groups_get_group_members($parse_args);

    foreach ($group_members["members"] as $member) {
        if ($member) {
            if ("ids" == $return) {
                $member_list[] = $member->id;
            } else {
                $user_avatar = bp_core_fetch_avatar(
                    array(
                        'item_id' => $member->id,
                        'no_grav' => true,
                        'type'    => 'full',
                        'html'   => FALSE     // FALSE = return url, TRUE (default) = return img html
                    )
                );
                $user_name = $member->display_name;
                $member_list[] = [
                    "user_id"           => $member->id,
                    "user_name"         => $user_name ? $user_name : sv_default_display_name(),
                    "mention_name"      => $member->user_login,
                    "user_avatar"       => $user_avatar,
                    "is_admin"          => ($member->is_admin) ? true : false,
                    "is_mod"            => ($member->is_mod) ? 1 : 0,
                    "is_banned"         => ($member->is_banned) ? 1 : 0,
                    "is_user_verified"  => sv_is_user_verified($member->id)
                ];
            }
        }
    }

    return $member_list;
}

function socialv_total_group_post_count($group_id)
{
    global $wpdb;
    $sql = "SELECT COUNT(*) FROM wp_bp_activity 
            WHERE component = 'groups' 
            AND  type IN ('activity_update','mpp_media_upload')
            AND   item_id = %d";
    $total_posts = $wpdb->get_var($wpdb->prepare($sql, [$group_id]));
    return $total_posts;
}

function get_sent_request_user_ids($current_user_id)
{
    $user_ids = [];
    if (class_exists("BP_Friends_Friendship")) {
        $friendships = BP_Friends_Friendship::get_friendships($current_user_id, ["is_confirmed" => 0, "initiator_user_id" => $current_user_id]);
        foreach ($friendships as $friendship) {
            $user_ids[] = $friendship->friend_user_id;
        }
    }
    return $user_ids;
}

function socialv_get_profile_field_options($field_id)
{
    $options = [];
    if (empty($field_id))
        return $options;

    $field_obj = xprofile_get_field($field_id, null, false);
    $field_child = $field_obj->get_children();

    if ($field_child) {
        foreach ($field_child as $option) {
            $options[] = [
                "id"    => $option->id,
                "name"  => $option->name
            ];
        }
    }
    return $options;
}

function socialv_get_report_types()
{
    if (!function_exists("imt_get_report_types")) return [];

    $types = imt_get_report_types();
    $report_types = [];
    if ($types) {
        foreach ($types as $key => $value) {
            $report_types[] = [
                "key"   => $key,
                "label" => $value
            ];
        }
    }
    return $report_types;
}

// exclude members from search result
add_filter("bp_rest_members_get_items_query_args", function ($args, $request) {
    $blocked = get_user_meta($request['current_user'], "imt_blocked_members", true);
    $blocked_by = get_user_meta($request['current_user'], "imt_member_blocked_by", true);
    $blocked = $blocked ? $blocked : [];
    $blocked_by = $blocked_by ? $blocked_by : [];
    $exclude = array_unique(array_merge($blocked, $blocked_by));
    $args['exclude'] = $exclude;
    return $args;
}, 10, 2);

function sv_exclude_private_user_activities($where_conditions)
{
    if (!SVSettings::is_friends_only_activity()) {
        $current_user_id = get_current_user_id();
        $exclude = friends_get_friend_user_ids($current_user_id);
        $exclude[] = $current_user_id;

        $args = [
            'fields'        => 'ids',
            'meta_key'      => 'socialv_user_account_type',
            'meta_value'    => 'private',
            'exclude'       => $exclude
        ];

        $users = get_users($args);

        if (!empty($users)) {
            $user_ids = implode(",", $users);
            $where_conditions["private_user_not_in"] = "a.user_id NOT IN ($user_ids)";
        }
    }
    return $where_conditions;
}
add_filter('bp_activity_get_where_conditions', 'sv_exclude_private_user_activities');

function sv_register_activity_actions()
{

    $contexts =   ["sitewide", "member", "group", "activity"];
    $components =  ["sitewide", "members", "groups", "activity"];

    // Register the activity stream actions for all enabled gallery component.
    foreach ($components as $component) {
        bp_activity_set_action(
            $component,
            'activity_share',
            __('User shared activity', 'socialv-api'),
            false,
            __('Activity Shared', 'socialv-api'),
            $contexts
        );
    }
}
add_action("bp_register_activity_actions", "sv_register_activity_actions");

function theme_slug_kses_allowed_html($tags, $context)
{
    switch ($context) {
        case 'no-images':
            $tags = wp_kses_allowed_html('post');
            unset($tags['img']);
            return $tags;
        default:
            return $tags;
    }
}

add_filter("rest_prepare_user", function ($response) {
    $response->data['is_user_verified'] = sv_is_user_verified($response->data['id']);
    return $response;
});

add_filter("rest_prepare_post", function ($response) {
    $is_close_comments_for_old_posts = get_option("close_comments_for_old_posts", false);
    if ($is_close_comments_for_old_posts) {
        $close_comments_days_old = get_option("close_comments_days_old", false);
        $now        = time();
        $your_date  = strtotime($response->data['date']);
        $datediff   = abs($now - $your_date);
        $total_days = floor($datediff / (60 * 60 * 24));
        $response->data['sv_is_comment_open'] = (int) $close_comments_days_old > (int) $total_days;
    } else {
        $response->data['sv_is_comment_open'] = true;
    }

    // pmp Restrictions
    if (function_exists('pmpro_has_membership_access')) {
        $subscription_plan = pmpro_has_membership_access($response->data['ID'], null, true);
        $response->data["subscriptions"] = collect($subscription_plan[1])->map(function ($plan_id) {
            $level = pmpro_getLevel($plan_id);
            $plan_data = [
                'id'    => (int) $plan_id,
                'label' => $level->name,
            ];

            return $plan_data;
        });
        $is_restricted = pmpro_has_membership_access($response->data['ID'], get_current_user_id(), false);
        $response->data['is_restricted'] = $is_restricted ? false : true;
    }


    return $response;
});

function add_additional_course_info($response, $object, $request)
{
    $data = svValidationToken($request);

    if ($data['status'])
        $current_user_id = $data['user_id'];
    elseif (!$data['status'] && $data['status_code'] == 401)
        return sv_comman_custom_response($data, $data['status_code']);

    if (!$current_user_id) return $response;

    $lp_order    = false;
    $lp_order_db = LP_Order_DB::getInstance();
    $lp_order_id = $lp_order_db->get_last_lp_order_id_of_user_course($current_user_id, $response->data['id']);

    if ($lp_order_id) {
        $lp_order = new LP_Order($lp_order_id);
    }

    if ($lp_order) {
        $response->data['in_cart'] = in_array($lp_order->get_status(), ["processing"]);
        $response->data['order_status'] =  $lp_order->get_status();
    }
    return $response;
}
add_filter("lp_jwt_rest_prepare_lp_course_object", "add_additional_course_info", 10, 3);

function sv_upload_media($file)
{
    // it allows us to use wp_handle_upload() function
    require_once(ABSPATH . 'wp-admin/includes/file.php');

    // you can add some kind of validation here
    if (empty($file))
        return __('No files selected.', SOCIALV_API_TEXT_DOMAIN);

    /*---------------------------- update code ----------------------------------- 
    -----------------------------------------------------------------------------
    -----------------------------------------------------------------------------
    ==========================use media_handle_upload()==========================
    -----------------------------------------------------------------------------
    ----------------------------------------------------------------------------*/
    $upload = wp_handle_upload($file, ['test_form' => false]);

    if (!empty($upload['error']))
        return 0;

    // it is time to add our uploaded image into WordPress media library
    $attachment_id = wp_insert_attachment(
        array(
            'guid'           => $upload['url'],
            'post_mime_type' => $upload['type'],
            'post_title'     => basename($upload['file']),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ),
        $upload['file']
    );

    if (is_wp_error($attachment_id) || !$attachment_id)
        return 0;

    wp_update_attachment_metadata(
        $attachment_id,
        wp_generate_attachment_metadata($attachment_id, $upload['file'])
    );

    return $attachment_id;
}

add_filter("bp_rest_activity_prepare_value", function ($response) {
    if ($response) {
        $data           = $response->get_data();
        $data['name']   = bp_core_get_user_displayname($data['user_id']);

        $response->set_data($data);
    }
    return $response;
});

// allow <br> tag (enter) 
function sv_allowed_tags($activity_allowedtags)
{
    $activity_allowedtags["br"] = [];

    return $activity_allowedtags;
}
add_filter('bp_activity_allowed_tags', 'sv_allowed_tags');
add_filter('bp_messages_allowed_tags', 'sv_allowed_tags');
