<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use Includes\baseClasses\SVNotifications;
use WP_REST_Server;

class SVNotificationController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/get-notification-settings',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_get_notification_settings'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/save-notification-settings',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_save_notification_settings'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/get-notifications-list',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_get_notifications_list'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/clear-notification',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_clear_notification'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/manage-user-player-ids',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_manage_user_player_ids'],
                    'permission_callback' => '__return_true'
                )
            );
            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/notification-count',
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'socialv_notification_count'],
                    'permission_callback' => '__return_true'
                )
            );
        });
    }
    public function socialv_get_notification_settings($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $notification_settings = [];
        $notification_obj = new SVNotifications();
        $settings = $notification_obj->socialv_notification_settings();
        foreach ($settings as $notification => $values) {
            $get_notify = get_user_meta($current_user_id, $values["key"], true);
            $notification_settings[] =  [
                "key"   => $values["key"],
                "name"  => $values["name"],
                "value" => "no" == $get_notify ? false : true
            ];
        }
        return comman_custom_response([
            "status" => true,
            "message" =>  __("Notification Settings", SOCIALV_API_TEXT_DOMAIN),
            "data" => $notification_settings
        ]);
    }

    public function socialv_save_notification_settings($request)
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
        foreach ($parameters as $settings) {
            $get_values =  $settings["value"] ? "yes" : "no";
            if (update_user_meta($current_user_id, $settings["key"], $get_values)) {
                $message = esc_html__("Settings saved.", SOCIALV_API_TEXT_DOMAIN);
                $status_code = true;
            }
        }
        return comman_custom_response([
            "status" => $status_code,
            "message" =>  $message,
        ]);
    }

    public function socialv_get_notifications_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $notifications = [];

        $per_page = $parameters["per_page"] ? $parameters["per_page"] : 10;
        $page = $parameters["page"] ? $parameters["page"] : 1;

        $allowed_registered_components = array_filter(bp_notifications_get_registered_components(), static function ($element) {
            return $element !== "messages";
        });

        $args = [
            "user_id"           => $current_user_id,
            "component_name"    => $allowed_registered_components,
            "per_page"          => $per_page,
            "page"              => $page
        ];

        $gorup_notification_actions = [
            "membership_request_accepted",
            "membership_request_rejected",
            "new_membership_request",
            "group_invite",
            "member_promoted_to_admin"
        ];


        $notification_obj = new SVNotifications();
        $get_notifications = $notification_obj->socialv_get_notifications($args);

        if ($get_notifications) :
            foreach ($get_notifications as $notification) :

                if ($notification_obj->socialv_skip_notification($notification, $current_user_id)) continue;

                $notification_id = $notification->id;
                $item_id = $notification->item_id;
                $secondary_item_id = $notification->secondary_item_id;
                $action = $notification->component_action;
                $component = $notification->component_name;

                $notification = [

                    "id"        => (int) $notification_id,
                    "is_new"    => (int) $notification->is_new,
                    "component" => $component,
                    "action"    => $action,
                    "date"      => $notification->date_notified,

                ];

                $notification["item_id"] = $item_id ? (int) $item_id : 0;
                $notification["secondary_item_id"] = $secondary_item_id ? (int) $secondary_item_id : 0;

                $is_action_in_1 = in_array($action, ["new_membership_request", "action_activity_liked", "socialv_share_post", "action_activity_reacted", "action_comment_activity_reacted", "comment_reply", "update_reply", "new_at_mention"]);
                $is_action_in_2 = in_array($action, ["friendship_request", "friendship_accepted"]);
                $is_forum = ($component == "forums");

                if (in_array($action, $gorup_notification_actions)) {
                    $group = groups_get_group($item_id);
                    if ($group) {
                        $notification["group_id"] = (int) $item_id;
                        $notification["item_name"] = $group->name ? $group->name : "";
                        $notification["item_image"] = bp_get_group_avatar_url($group);
                    }
                }


                if ($is_action_in_1 || $is_forum) {
                    $user_avatar = bp_core_fetch_avatar('html=false&type=full&item_id=' . $secondary_item_id . '&no_grav=' . false);
                    if ($action == "new_membership_request") {
                        $notification['request_id'] = groups_check_for_membership_request($secondary_item_id, $item_id);
                    }
                    $name = bp_core_get_user_displayname($secondary_item_id);
                    $notification["secondary_item_name"] = $name ? $name : "";
                    $notification["is_user_verified"] = sv_is_user_verified($secondary_item_id);
                    $notification["secondary_item_image"] = $user_avatar ? $user_avatar : "";
                    if ($is_forum) {
                        $new_action  = explode("_", $action);
                        $topic_id = (int) end($new_action);
                        $action = str_replace("_" . $topic_id, "", $action);
                        if ($action == "sv_new_topic_reply")
                            $topic_id = bbp_get_reply_topic_id($item_id);
                        $notification["topic_id"] = $topic_id;
                        $notification["action"] = $action;
                        $notification["item_name"] = bbp_get_topic_title($topic_id);
                    }
                }

                if ("group_invite" == $action) {
                    $invite = groups_check_user_has_invite($current_user_id, $item_id);
                    $notification["secondary_item_id"] = $invite ? $invite : 0;
                }

                if ($is_action_in_2) {
                    $notification["item_name"] = bp_core_get_user_displayname($item_id);
                    $notification["is_user_verified"] = sv_is_user_verified($item_id);
                    $notification["item_image"] = bp_core_fetch_avatar('html=false&type=full&item_id=' . $item_id . '&no_grav=' . false);
                }

                if (in_array($action, ["action_activity_liked", "socialv_share_post", "action_activity_reacted", "action_comment_activity_reacted", "comment_reply", "update_reply", "new_at_mention"])) {

                    $content = $image = "";
                    $id = $item_id;

                    if ("new_at_mention" == $action) {
                        $mention_activity = bp_activity_get(["in" => $id, "display_comments" => 1]);
                        if (!empty($mention_activity["activities"])) {
                            $mention_activity = $mention_activity['activities'][0];
                            $id = $mention_activity && "activity_comment" == $mention_activity->type ? $mention_activity->item_id : $id;
                        }
                    }

                    if (in_array($action, ["comment_reply", "update_reply", "action_comment_activity_reacted"])) {
                        $comment = bp_activity_get(["in" => $item_id, "display_comments" => 1]);
                        if (!empty($comment["activities"])) {
                            $comment = $comment['activities'][0];
                            $id = $comment ? $comment->item_id : $id;
                        }
                    }

                    $activity = bp_activity_get(["in" => $id]);

                    if (!empty($activity["activities"])) {

                        $activity = $activity['activities'][0];
                        $content = trim($activity->content);
                        $notification["item_id"] = $activity->id;

                        $media = bp_activity_get_meta($activity->id);
                        if ($action == "action_activity_reacted") {
                            $reaction_obj = get_reaction_db_obj();
                            if ($reaction_obj) {
                                $userReaction = get_reaction_db_obj()->getUserReaction($item_id, $secondary_item_id);
                                $image = isset($userReaction[0]) ? $userReaction[0]->image_url : "";
                            }
                        } elseif ($action == "action_comment_activity_reacted") {
                            $reaction_obj = get_reaction_db_obj();
                            if ($reaction_obj) {
                                $userReaction = get_reaction_db_obj()->getCommentReaction($activity->id, $secondary_item_id, $item_id);
                                $image = isset($userReaction[0]) ? $userReaction[0]->image_url : "";
                            }
                        } elseif ($media && isset($media["_mpp_attached_media_id"])) {

                            $media_attachment_id = $media["_mpp_attached_media_id"][0];

                            $media_type =  mpp_get_media_type($media_attachment_id);
                            if ("photo" == $media_type) {
                                $url = wp_get_attachment_image_src($media_attachment_id, "full");
                                $image = $url ? $url[0] : '';
                            }
                        }
                    }

                    $notification["item_name"] = "&nbsp;" == $content ? "" : $content;
                    $notification["item_image"] = $image;
                }

                $notification_obj->socialv_mark_notification($current_user_id, $notification_id, false);

                $notifications[] = $notification;

            endforeach;
        endif;
        wp_cache_set($current_user_id, false, 'socialv_notifications_unread_count');

        return comman_custom_response([
            "status" => true,
            "message" =>   __("List of Notification", SOCIALV_API_TEXT_DOMAIN),
            "data" => $notifications
        ]);
    }

    public function socialv_clear_notification($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        bp_notifications_delete_notifications_on_delete_user($current_user_id);
        return comman_custom_response([
            "status" => true,
            "message" =>   __("Notifications cleared.", SOCIALV_API_TEXT_DOMAIN)
        ]);
    }

    public function socialv_manage_user_player_ids($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        // $player_id = $parameters['player_id'];
        $add = $parameters['add'];
        $firebase_token =  $request->get_param('firebase_token');

        // FireBase Notification Token
        $firebase_tokens = [];
        if ($user_firebase_tokens = get_user_meta($current_user_id, SOCIALV_API_PREFIX . 'firebase_tokens', true)) {
            if (!$add) {
                if (in_array($firebase_token, $user_firebase_tokens)) {
                    $index = array_search($firebase_token, $user_firebase_tokens);
                    unset($user_firebase_tokens[$index]);
                }
                $user_firebase_tokens = array_values($user_firebase_tokens);
            } else {
                if (is_array($user_firebase_tokens) && in_array($firebase_token, $user_firebase_tokens)) return sv_comman_custom_response($user_firebase_tokens, 200);
                $user_firebase_tokens = empty($user_firebase_tokens) ? [$firebase_token] : array_merge([$firebase_token], $user_firebase_tokens);
            }
            $firebase_tokens = $user_firebase_tokens;
        }
        if (!empty($firebase_token)) {
            array_push($firebase_tokens, $firebase_token);
        }
        update_user_meta($current_user_id, SOCIALV_API_PREFIX . 'firebase_tokens', array_unique($firebase_tokens));


        return comman_custom_response([
            "status" => true,
            "message" =>   __("User Player Id", SOCIALV_API_TEXT_DOMAIN),
            "data" => $user_firebase_tokens
        ]);
    }

    public function socialv_notification_count($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $notification_count = (int) SVNotifications::socialv_notifications_get_unread_notification_count($current_user_id);


        $unread_messages_count = 0;
        if (function_exists("Better_Messages_WebSocket"))
            $unread_messages_count = Better_Messages()->functions->get_total_threads_for_user($current_user_id, 'unread');

        return comman_custom_response([
            "status" => true,
            "message" =>   __("Notification Count", SOCIALV_API_TEXT_DOMAIN),
            "data" => ["notification_count" => $notification_count, "unread_messages_count" => $unread_messages_count]
        ]);
    }
}
