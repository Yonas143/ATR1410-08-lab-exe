<?php

namespace Includes\Controllers\Api;

use BP_Activity_Activity;

use Includes\baseClasses\SVActivityComments;
use Includes\baseClasses\SVBase;
use Includes\baseClasses\SVCustomNotifications;
use WP_REST_Server;

class SVActivityController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        $custom_notification = new SVCustomNotifications();
        add_action('rest_api_init', function () {

            remove_filter('bp_activity_content_before_save', 'force_balance_tags');
            remove_filter('bp_get_activity_content_body', 'force_balance_tags');
            remove_filter('bp_get_activity_content', 'force_balance_tags');

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/create-post',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_create_post'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-post', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_post'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-post-details', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_post'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/delete-post', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_delete_activity'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/save-post-comment', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_save_activity_comment'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-posts-all-comment', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_posts_all_comment'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/delete-post-comment', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_delete_comment'],
                'permission_callback' => '__return_true'
            ));

            // register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-post-in-list', array(
            //     'methods'             => WP_REST_Server::ALLMETHODS,
            //     'callback'            => [$this, 'socialv_get_post_in_lists'],
            //     'permission_callback' => '__return_true'
            // ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-all-user-who-liked-post', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_all_user_who_liked_post'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/like-activity', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_like_activity'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/favorite-activity', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_favorite_activity'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/pin-activity', array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'socialv_pin_activity'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/hide-post', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_hide_post'],
                'permission_callback' => '__return_true'
            ));
        });
    }

    public function socialv_create_post($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $content        = !empty($parameters['content']) ? $parameters['content'] : "";
        $activity_type  = !empty($parameters['activity_type']) ? $parameters['activity_type'] : "activity_update";
        $post_in        = !empty($parameters['post_in']) ? $parameters['post_in'] : 0;
        $component      = !empty($parameters['component']) ? $parameters['component'] : "activity";
        $child_id       = isset($parameters["child_id"]) ? $parameters["child_id"] : "";
        $media_type     = $parameters['media_type'];
        $media_count    = !empty($parameters['media_count']) ? $parameters['media_count'] : 0;
        $user_link      = mpp_get_user_link($current_user_id);

        $args = [
            'user_id'   => $current_user_id,
            'content'   => $content,
            'type'      => $activity_type,
        ];
        if (isset($parameters['id'])) {
            $args['id'] = $parameters['id'];
        }
        if ("activity_share" == $activity_type) {
            $parent_activity = bp_activity_get(["in" => $child_id, "display_comments" => 0]);
            $activity_user_id = !empty($parent_activity["activities"]) ? $parent_activity["activities"][0]->user_id : 0;
            if ($parent_activity["activities"][0]->user_id == $current_user_id) {
                $args["action"] = '<a href="' . bp_members_get_user_url($activity_user_id) . '">' . get_the_author_meta('display_name', $activity_user_id) . '</a>' . __(' shared his post', SOCIALV_API_TEXT_DOMAIN);
            } else {
                $args["action"] = '<a href="' . bp_members_get_user_url($current_user_id) . '">' . get_the_author_meta('display_name', $current_user_id) . '</a> ' . sprintf(__('shared %s post', SOCIALV_API_TEXT_DOMAIN), '<a href="' . bp_members_get_user_url($activity_user_id) . '">' . get_the_author_meta('display_name', $activity_user_id) . '</a>');
            }
        } else if (empty($post_in) && !empty($media_type)) {
            $args["action"] = sprintf(esc_html__('%s posted an update', SOCIALV_API_TEXT_DOMAIN), $user_link, strtolower(mpp_get_type_singular_name($media_type)));
        }

        $primary_link     = bp_core_get_userlink($current_user_id, false, true);

        if ($post_in != 0) {
            $group     = bp_groups_get_activity_group($post_in);

            $group_link = '<a href="' . esc_url(bp_get_group_url($group)) . '">' . esc_html($group->name) . '</a>';

            // Set the Activity update posted in a Group action.
            $args["action"] = sprintf(esc_html__('%1$s posted an update in the group %2$s', SOCIALV_API_TEXT_DOMAIN), $user_link, $group_link);

            if (!bp_current_user_can('bp_moderate') && !groups_is_user_member($current_user_id, $post_in)) {
                return comman_custom_response([
                    "status" => false,
                    "message" => __("Something wrong.", SOCIALV_API_TEXT_DOMAIN)
                ]);
            }

            $args['item_id'] =  $post_in;
            $activity_id = groups_record_activity($args);

            $message = esc_html__("Posted successfully.", SOCIALV_API_TEXT_DOMAIN);
            groups_update_groupmeta($post_in, 'last_activity', bp_core_current_time());
        } else {
            $args['component'] =  $component;
            $args['primary_link'] = $primary_link;

            $activity_id = bp_activity_add($args);
            $message = esc_html__("Posted successfully.", SOCIALV_API_TEXT_DOMAIN);
        }

        // Bail on failure.
        if (false === $activity_id || is_wp_error($activity_id)) {
            $status_code = false;
            $message = esc_html__("Something wrong.", SOCIALV_API_TEXT_DOMAIN);
        } else {
            $status_code = true;
            // Add this update to the "latest update" usermeta so it can be fetched anywhere.
            bp_update_user_meta(bp_loggedin_user_id(), 'bp_latest_update', array(
                'id'      => $activity_id,
                'content' => $content
            ));

            $gallery_id = null;

            if (isset($parameters["current_type"]) && $parameters["current_type"] == "activity_share") {
                $child_id = bp_activity_get_meta($child_id, 'shared_activity_id', true);
                bp_activity_update_meta($activity_id, 'shared_activity_id', $child_id);
                $message = esc_html__("Activity shared.", SOCIALV_API_TEXT_DOMAIN);
                do_action("socialv_activity_shared", $activity_id, $child_id, $current_user_id);
            } else if ("activity_share" == $activity_type && !empty($child_id)) {
                bp_activity_update_meta($activity_id, 'shared_activity_id', $child_id);
                $message = esc_html__("Activity shared.", SOCIALV_API_TEXT_DOMAIN);
                do_action("socialv_activity_shared", $activity_id, $child_id, $current_user_id);
            } else if (($activity_type == "mpp_media_upload" || $media_type == "gif") && $media_count > 0) {
                if ($media_type == "gif") {
                    $gif_meta = [
                        "bp_activity_gif_id"    => $parameters["media_id"],
                        "bp_activity_gif"       => $parameters["media_0"]
                    ];
                    bp_activity_update_meta($activity_id, "_bp_activity_gif_data", $gif_meta);
                } else {
                    for ($i = 0; $i < $media_count; $i++) {
                        if (isset($_FILES['media_' . $i])) {
                            $media = ['_mpp_file' => $_FILES['media_' . $i]];

                            $media_data = socialv_add_media($media, $current_user_id, $parameters);

                            $gallery_id = $media_data['gallery_id'];
                        } else {
                            $media_args = [
                                "current_user_id"   => $current_user_id,
                                "url"               => $parameters['media_' . $i],
                                "component"         => ($component == "activity") ? "members" : $component
                            ];
                            if ($post_in != 0)
                                $media_args["group_id"] = $post_in;

                            $media_data = sv_add_remote_media($media_args);
                        }
                        bp_activity_add_meta($activity_id, '_mpp_attached_media_id', $media_data['media_id']);
                    }

                    mpp_activity_update_activity_type($activity_id, "media_upload");

                    if ($gallery_id) {
                        mpp_activity_update_gallery_id($activity_id, $gallery_id);
                    }

                    mpp_activity_update_context($activity_id, 'gallery');

                    // save activity privacy.
                    bp_activity_update_meta($activity_id, 'activity-privacy', "pubilc");
                }
                $message = esc_html__("Posted successfully.", SOCIALV_API_TEXT_DOMAIN);
            }
        }

        return comman_custom_response([
            "status" =>  $status_code,
            "message" => $message
        ]);
    }

    public function socialv_get_post($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return sv_comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $type = $parameters["type"];

        $user_id = $parameters["user_id"];
        if ($user_id != $current_user_id && in_array($type, ["single-activity", "timeline"])) {
            $account_type = get_user_meta($user_id, "socialv_user_account_type", true);
            if ($account_type == "private") {
                $friendship_status = friends_check_friendship_status((int)$current_user_id, (int)$user_id);
                if ("is_friend" != $friendship_status)
                    return comman_custom_response([
                        "status" => true,
                        "message" => __("Private account.", SOCIALV_API_TEXT_DOMAIN)
                    ]);
            }
        }
        $args = [
            "current_user_id"   => $current_user_id,
            "user_id"           => $user_id,
            "per_page"          => $parameters["per_page"],
            "page"              => $parameters["page"],
            "type"              => $type,
            "group_id"          => $parameters["group_id"],
            "activity_id"       => !empty($parameters["activity_id"]) ? $parameters["activity_id"] : ''
        ];

        $activities = socialv_get_activity_post($args);
        if ("single-activity" == $parameters["type"])
            $activities = $activities[0] ? $activities[0] : $activities;
        if ("private-group" == $activities)
            return comman_custom_response([
                "status" => true,
                "message" => __("This is a private group and you must request group membership in order to join.", SOCIALV_API_TEXT_DOMAIN)
            ]);

        return comman_custom_response([
            "status" => true,
            "message" => __("Get activity post.", SOCIALV_API_TEXT_DOMAIN),
            "data" => $activities
        ]);
    }


    public function socialv_delete_activity($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $activity = new BP_Activity_Activity((int) $parameters['activity_id']);

        $delete_activity = socialv_delete_user_activity($activity, $current_user_id);

        return comman_custom_response([
            "status" => $delete_activity["status_code"],
            "message" => $delete_activity['message']
        ]);
    }

    function socialv_save_activity_comment($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();

        $parameters = svRecursiveSanitizeTextField($parameters);
        $activity_id = $parameters['activity_id'];
        $content = $parameters['content'];
        $parent_id = $parameters['parent_comment_id'] ? $parameters['parent_comment_id'] : false;

        $has_media = (isset($parameters["media_type"]) && $parameters["media_type"] == "gif");

        if ($has_media)
            $content .= '<img src="' . $parameters['media'] . '" />';
        $args = [
            'content'       => $content,
            'activity_id'   => $activity_id,
            'user_id'       => $current_user_id,
            'parent_id'     => $parent_id
        ];

        if (isset($parameters['id'])) {
            $args['id'] = $parameters['id'];
        }

        $comment_id = bp_activity_new_comment($args);

        if ($comment_id) {
            $status_code = true;
            if ($has_media) {
                $gif_meta = [
                    "bp_activity_gif_id"    => $parameters["media_id"],
                    "bp_activity_gif"       => $parameters["media"]
                ];
                bp_activity_update_meta($comment_id, "_bp_activity_gif_data", $gif_meta);
            }
            $message = esc_html__("Comment posted successfully.", SOCIALV_API_TEXT_DOMAIN);
        } else {
            $status_code = false;
            $message = esc_html__("Something wrong.", SOCIALV_API_TEXT_DOMAIN);
        }
        $response = [
            'message' => $message,
            'comment_id' => $comment_id
        ];

        return comman_custom_response([
            "status" => $status_code,
            "message" => $message,
            "data" => $response
        ]);
    }

    public function socialv_get_posts_all_comment($request)
    {
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $activity_id = $parameters['activity_id'];
        $per_page = $parameters['per_page'];
        $page = $parameters['page'];
        $comments = [];

        $get_activity = bp_activity_get(['in' => $activity_id]);
        if ($get_activity) {
            $activity = (object)["activity" => $get_activity["activities"][0]];
            $comments = new SVActivityComments();
            $comments = $comments->socialv_activity_get_comments($activity, "all", ["page" => $page, "per_page" => $per_page]);
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("List Of Post Comments", SOCIALV_API_TEXT_DOMAIN),
            "data" => $comments
        ]);
    }

    public function socialv_delete_comment($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);


        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $activity = new BP_Activity_Activity((int) $parameters['comment_id']);

        if (!socialv_can_delete_activity($activity, $current_user_id))
            return comman_custom_response([
                "status" => false,
                "message" => esc_html__("Can't delete.", SOCIALV_API_TEXT_DOMAIN)
            ]);

        $activity_id = $parameters['activity_id'];
        $comment_id = $parameters['comment_id'];

        $delete_activity = bp_activity_delete_comment($activity_id, $comment_id);

        if ($delete_activity) {
            $status_code = true;
            $message = esc_html__("deleted.", SOCIALV_API_TEXT_DOMAIN);
        } else {
            $status_code = false;
            $message = esc_html__("Something Wrong.", SOCIALV_API_TEXT_DOMAIN);
        }

        return comman_custom_response([
            "status" => $status_code,
            "message" => $message
        ]);
    }

    // public function socialv_get_post_in_lists($request)
    // {
    //     $data = svValidationToken($request);

    //     if ($data['status'] && isset($data['user_id']))
    //         $current_user_id = $data['user_id'];
    //     else
    //         return comman_custom_response($data, $data['status_code']);


    //     $post_in[] = ["id" => 0, "title" => "My Profile"];
    //     $args = 'user_id=' . $current_user_id . '&type=alphabetical&max=100&per_page=100&populate_extras=0&update_meta_cache=0';

    //     if (bp_has_groups($args)) :
    //         while (bp_groups()) : bp_the_group();
    //             $post_in[] = ["id" => bp_get_group_id(), "title" => bp_get_group_name()];
    //         endwhile;
    //     endif;

    //     return comman_custom_response([
    //         "status" => true,
    //         "message" => __("Post List", SOCIALV_API_TEXT_DOMAIN),
    //         "data" => $post_in
    //     ]);
    // }

    public function socialv_get_all_user_who_liked_post($request)
    {
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $user_who_liked = [];
        if (!empty($parameters['activity_id'])) {
            $args = [
                "per_page"  => $parameters["per_page"],
                "page"      => $parameters["page"]
            ];
            $user_who_liked = socialv_activity_liked_users($parameters['activity_id'], $args);
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("List Of Person Who like post", SOCIALV_API_TEXT_DOMAIN),
            "data" => $user_who_liked['list']
        ]);
    }

    public function socialv_like_activity($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $meta_key = "_socialv_activity_liked_users";

        $currentvalue = bp_activity_get_meta($parameters['activity_id'], $meta_key, true);

        $post_array = explode(', ', $currentvalue);

        if (!in_array($current_user_id, $post_array)) {
            if (!empty($currentvalue)) {
                $newvalue = $currentvalue . ', ' . $current_user_id;
            } else {
                $newvalue = $current_user_id;
            }

            if ($meta_key == "_socialv_activity_liked_users" && bp_activity_update_meta($parameters['activity_id'], $meta_key, $newvalue, $currentvalue)) {

                $args = array("has_activity" => socialv_is_user_liked($parameters['activity_id'], $current_user_id), "status" => true);
            }
        } else {
            $key = array_search($current_user_id, $post_array);

            unset($post_array[$key]);

            if ($meta_key == "_socialv_activity_liked_users" && bp_activity_update_meta($parameters['activity_id'], $meta_key, implode(", ", $post_array), $currentvalue)) {
                $args = array("has_activity" => socialv_is_user_liked($parameters['activity_id'], $current_user_id), "status" => false);
            }
        }
        // notify-user
        if ($meta_key == "_socialv_activity_liked_users" && bp_is_active('notifications')) {
            $args["component_name"] = "socialv_activity_like_notification";
            $args["component_action"] = "action_activity_liked";
            $args['enable_notification_key'] = "notification_activity_new_like";
            SVCustomNotifications::sv_add_user_notification($parameters['activity_id'], $args, $current_user_id);
        }

        $is_liked = ["is_liked" => false];

        if ($args['status'])
            $is_liked = ["is_liked" => true];

        return comman_custom_response([
            "status" => true,
            "message" => __("List Activity", SOCIALV_API_TEXT_DOMAIN),
            "data" => $is_liked
        ]);
    }

    public function socialv_favorite_activity($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $activity_id = $parameters['post_id'];

        if ($parameters['is_favorite'] && bp_activity_remove_user_favorite($activity_id, $current_user_id)) {
            $message = __("Post removed from favorite.", SOCIALV_API_TEXT_DOMAIN);
            $code = 200;
        } elseif (bp_activity_add_user_favorite($activity_id, $current_user_id)) {
            $message = __("Post added to favorite.", SOCIALV_API_TEXT_DOMAIN);
            $code = 200;
        } else {
            $message = __("Something wrong try again.", SOCIALV_API_TEXT_DOMAIN);
            $code = 422;
        }

        return comman_custom_response([
            "status" => $code,
            "message" => $message
        ]);
    }

    public function socialv_pin_activity($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $activity_id = $parameters['post_id'];

        $meta_key = "_socialv_user_pinned_activity";
        $current_value = get_user_meta($current_user_id, $meta_key, true);

        $post_array = explode(', ', $current_value);

        if (!in_array($activity_id, $post_array) && $parameters['pin_activity']) {
            if (!empty($current_value)) {
                $newvalue = $current_value . ', ' . $activity_id;
            } else {
                $newvalue = $activity_id;
            }

            if (update_user_meta($current_user_id, $meta_key, $newvalue, $current_value)) {
                $message    = __("Post pinned.", SOCIALV_API_TEXT_DOMAIN);
                $code       = 200;
            }
        } else if (in_array($activity_id, $post_array) && !$parameters['pin_activity']) {
            $key = array_search($activity_id, $post_array);
            unset($post_array[$key]);

            if (update_user_meta($current_user_id, $meta_key, implode(", ", $post_array), $current_value)) {
                $message    = __("Post un-pinned.", SOCIALV_API_TEXT_DOMAIN);
                $code       = 200;
            }
        } else {
            $message    = __("Something wrong. Try again.", SOCIALV_API_TEXT_DOMAIN);
            $code       = 422;
        }


        return comman_custom_response([
            "status" => $code,
            "message" => $message
        ]);
    }

    public function socialv_hide_post($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);


        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $meta_key = "_socialv_activity_hiden_by_user";

        if (!isset($parameters["activity_id"])) {
            return comman_custom_response([
                "status" => true,
                "message" => __("Id not present", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }

        $activity_id = $parameters["activity_id"];

        $hidden_activities = get_user_meta($current_user_id, $meta_key, true);

        if ($hidden_activities) {
            if (in_array($activity_id, $hidden_activities)) {
                $unset_id = array_search($activity_id, $hidden_activities);
                unset($hidden_activities[$unset_id]);
                if (update_user_meta($current_user_id, $meta_key, array_values($hidden_activities)))
                    return comman_custom_response([
                        "status" => true,
                        "message" => __("Post is now visible", SOCIALV_API_TEXT_DOMAIN)
                    ]);
            }

            $hidden_activities[] = $activity_id;
            if (update_user_meta($current_user_id, $meta_key, $hidden_activities))
                return comman_custom_response([
                    "status" => true,
                    "message" => __("Post is now hidden", SOCIALV_API_TEXT_DOMAIN)
                ]);
        } else {
            $hidden_activities = [];
            $hidden_activities[] = $activity_id;
            if (update_user_meta($current_user_id, $meta_key, $hidden_activities))
                return comman_custom_response([
                    "status" => true,
                    "message" => __("Post is now hidden", SOCIALV_API_TEXT_DOMAIN)
                ]);
        }
    }
}
