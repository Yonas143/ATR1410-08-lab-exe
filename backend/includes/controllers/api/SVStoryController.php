<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use Includes\baseClasses\SVStories;
use WP_Query;
use WP_REST_Server;


class SVStoryController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;
        if (!is_admin() && !function_exists('post_exists')) {
            require_once(ABSPATH . 'wp-admin/includes/post.php');
        }
        add_action('rest_api_init', function () {

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/add-story', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_add_story'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-stories', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_stories'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-highlight-category', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_user_highlight_categories'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-highlight-stories', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_highlight_stories'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-story-views', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_story_viewed_by'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/view-story', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_view_story'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/delete-story', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_story_item_delete'],
                'permission_callback' => '__return_true'
            ));
        });
    }


    public function socialv_add_story($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if ($_FILES["media"]["size"] > 0) $attachment = socialv_upload_story_attachment($_FILES["media"]);
        else return comman_custom_response([
            "status" => false,
            "message" => __('Media not selected.', SOCIALV_API_TEXT_DOMAIN)
        ]);

        if ($attachment) {

            $args = [
                "current_user_id"               => $current_user_id,
                'sv-wpstory-item-media-id'      => $attachment['attachment_id'],
                'sv-wpstory-story-link-text'    => isset($parameters["story_text"]) ? $parameters["story_text"] : '',
                'sv-wpstory-story-link'         => isset($parameters["story_link"]) ? $parameters["story_link"] : '',
                'sv-wpstory-story-duration'     => isset($parameters["duration"]) ? $parameters["duration"] : 0
            ];

            if ("highlight" == $parameters["type"]) {
                if (!WPSTORY()->options('buddypress_public_stories')) return comman_custom_response([
                    "status" => false,
                    "message" => __('Disabled', SOCIALV_API_TEXT_DOMAIN)
                ]);
                $args["sv-wpstory-story-parent"] = isset($parameters["parent_id"]) ? $parameters["parent_id"] : '';
                $args["sv-wpstory-highlight-title"] = isset($parameters["parent_title"]) ? $parameters["parent_title"] : '';


                if ($_FILES["parent_thumb"]["size"] > 0) $parent_attachment = socialv_upload_story_attachment($_FILES["parent_thumb"]);
                else $parent_attachment["attachment_id"] = "";
                $args["sv-wpstory-thumb-media-id"] = $parent_attachment["attachment_id"];

                $args["status"] = isset($parameters["status"]) ? $parameters["status"] : "publish";

                $story = socialv_submit_public_story($args);

                return comman_custom_response([
                    "status" => $story["status_code"],
                    "message" => $story["message"]
                ]);
            }

            $story = socialv_submit_story($args);

            return comman_custom_response([
                "status" => $story["status_code"],
                "message" => $story["message"]
            ]);
        }
        return comman_custom_response([
            "status" => $attachment["status_code"],
            "message" => $attachment["message"]
        ]);
    }

    function socialv_get_stories($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (!empty($parameters["user_id"])) {
            $user_stories = sv_story_instance()->get_user_stories(0, $parameters["user_id"], $current_user_id);
            if (is_wp_error($user_stories))
                return comman_custom_response([
                    "status" => false,
                    "message" =>  __('User stories Not found', SOCIALV_API_TEXT_DOMAIN),
                    "data" => []
                ]);

            return comman_custom_response([
                "status" => true,
                "message" =>  __('User stories', SOCIALV_API_TEXT_DOMAIN),
                "data" => $user_stories
            ]);
        }

        $user_stories = sv_story_instance()->get_stories(0, $current_user_id);
        if (is_wp_error($user_stories)) return comman_custom_response([
            "status" => true,
            "message" =>  __('User stories Not found', SOCIALV_API_TEXT_DOMAIN),
            "data" => []
        ]);

        return comman_custom_response([
            "status" => true,
            "message" =>  __('User stories', SOCIALV_API_TEXT_DOMAIN),
            "data" => $user_stories
        ]);
    }

    public function socialv_user_highlight_categories($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $cat_args = array(
            'posts_per_page' => -1,
            'post_type'      => 'wpstory-public',
            'post_status'    => 'publish',
            'post_parent'    => 0,
            'author'         => $current_user_id,
        );
        $highlight_cat = [];
        $cat_query = new WP_Query($cat_args);
        if ($cat_query->have_posts()) :
            while ($cat_query->have_posts()) {
                $cat_query->the_post();
                $highlight_cat[] = [
                    "id"    => get_the_ID(),
                    "name"  => get_the_title()
                ];
            }
        endif;
        wp_reset_postdata();

        return comman_custom_response([
            "status" => true,
            "message" =>  __('User`s highlight categories', SOCIALV_API_TEXT_DOMAIN),
            "data" => $highlight_cat
        ]);
    }

    function socialv_get_highlight_stories($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $status = isset($parameters['status']) ? $parameters['status'] : "publish";

        $user_stories = sv_story_instance()->sv_get_user_public_stories($current_user_id, [], $status);

        if (is_wp_error($user_stories))
            return comman_custom_response([
                "status" => true,
                "message" =>  __('User`s highlight stories not found', SOCIALV_API_TEXT_DOMAIN)
            ]);

        return comman_custom_response([
            "status" => true,
            "message" =>  __('User`s highlight stories', SOCIALV_API_TEXT_DOMAIN),
            "data" => $user_stories
        ]);
    }

    public function socialv_view_story($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $story_id = $parameters["story_id"];
        $seen_by_key = "sv_story_seen_by";

        if (socialv_is_seen($story_id, $seen_by_key, $current_user_id)) return comman_custom_response([
            "status" => true,
            "message" =>  __('Seen', SOCIALV_API_TEXT_DOMAIN)
        ]);

        if (spcialv_set_story_as_seen($story_id, $seen_by_key, $current_user_id))
            return comman_custom_response([
                "status" => true,
                "message" =>  __('Story viewed', SOCIALV_API_TEXT_DOMAIN)
            ]);

        return comman_custom_response([
            "status" => false,
            "message" =>  __('Something Wrong', SOCIALV_API_TEXT_DOMAIN)
        ]);
    }

    public function socialv_story_viewed_by($request)
    {

        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $story_id = $parameters["story_id"];
        $uniq_key = "sv_story_seen_by";

        $story = get_post($story_id);

        if ('wpstory-user' !== get_post_type($story_id)) {
            return comman_custom_response([
                "status" => false,
                "message" =>  __('View story not found', SOCIALV_API_TEXT_DOMAIN)
            ]);
        }


        if ((int) $current_user_id !== (int) $story->post_author) {
            return comman_custom_response([
                "status" => false,
                "message" =>  __('Story author not match with current user', SOCIALV_API_TEXT_DOMAIN)
            ]);
        }

        $seen_by_ids = socialv_get_story_seen_by($story_id, $uniq_key);
        $member_list = [];

        foreach ($seen_by_ids as $id => $time) {

            $user_avatar = bp_core_fetch_avatar(
                array(
                    'item_id'   => $id,
                    'no_grav'   => true,
                    'type'      => 'full',
                    'html'      => FALSE     // FALSE = return url, TRUE (default) = return img html
                )
            );

            $member_list[] = [
                "user_id"           => $id,
                "user_name"         => bp_core_get_user_displayname($id),
                "mention_name"      => bp_core_get_username($id),
                "user_avatar"       => $user_avatar ? $user_avatar : "",
                "is_user_verified"  => sv_is_user_verified($id),
                "seen_time"         => $time
            ];
        }
        return comman_custom_response([
            "status" => true,
            "message" =>  __('View Member List of story', SOCIALV_API_TEXT_DOMAIN),
            "data" => $member_list
        ]);
    }

    public function socialv_story_item_delete($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $story_id = isset($parameters["story_id"]) ? $parameters["story_id"] : "";
        $type = isset($parameters["type"]) ? $parameters["type"] : "story";
        $status = isset($parameters["status"]) ? $parameters["status"] : "draft";

        if (empty($story_id)) {
            return comman_custom_response([
                "status" => false,
                "message" =>  __('Something Wrong.', SOCIALV_API_TEXT_DOMAIN)
            ]);
        }

        if (!in_array(get_post_type($story_id), array('wpstory-user', 'wpstory-public'), true)) {
            return comman_custom_response([
                "status" => false,
                "message" =>  __('Something Wrong.', SOCIALV_API_TEXT_DOMAIN)
            ]);
        }

        $user_can_manage = wpstory_premium_helpers()->user_can_manage_story($current_user_id, $story_id);

        if (!$user_can_manage) {
            return comman_custom_response([
                "status" => false,
                "message" =>  __('You do not have premission to delete this story.', SOCIALV_API_TEXT_DOMAIN)
            ]);
        }


        if ($type == "category") {
            $is_error = false;
            $parent_id = $story_id;
            $args = array(
                'posts_per_page' => -1,
                'order'          => 'DESC',
                'fields'         => 'ids',
                'post_parent'    => $story_id,
                'post_status'    => ['draft', 'trash', 'publish'],
                'post_type'      => 'wpstory-public'
            );
            $story_ids = get_children($args);
            if (in_array($status, ['draft', 'trash', 'publish'])) {

                foreach ($story_ids as $story_id) {
                    $is_true = sv_story_delete_status($story_id, $status);

                    if (!$is_true) $is_error = true;
                }
                if (!$is_error) {
                    if ("publish" == $status)
                        $status_message = __("Highlight has been restored.", SOCIALV_API_TEXT_DOMAIN);
                    else
                        $status_message = _x(sprintf("Highlight has been added to %1s.", $status), "trash or draft highlights", SOCIALV_API_TEXT_DOMAIN);
                }
                return comman_custom_response([
                    "status" => true,
                    "message" =>  $status_message
                ]);
            } else if ('delete' === $status) {
                foreach ($story_ids as $story_id) {
                    $is_true = sv_story_permanent_delete($story_id);
                    if (!$is_true) $is_error = true;
                }
                if (!$is_error) {
                    sv_story_permanent_delete($parent_id);
                    return comman_custom_response([
                        "status" => true,
                        "message" => __('Story has been deleted.', SOCIALV_API_TEXT_DOMAIN)
                    ]);
                }
            }
        } else {
            // $status = wpstory_premium_helpers()->options('user_deleting_status', 'draft');
            if (in_array($status, ['draft', 'trash', 'publish']) && sv_story_delete_status($story_id, $status)) {
                if ("publish" == $status)
                    $status_message = __("Story has been restored.", SOCIALV_API_TEXT_DOMAIN);
                else
                    $status_message = _x(sprintf("Story has been added to %1s.", $status), "trash or draft story", SOCIALV_API_TEXT_DOMAIN);

                return comman_custom_response([
                    "status" => true,
                    "message" => $status_message
                ]);
            } else if ('delete' === $status && sv_story_permanent_delete($story_id)) {
                return comman_custom_response([
                    "status" => true,
                    "message" => __('Story has been deleted.', SOCIALV_API_TEXT_DOMAIN)
                ]);
            }
        }

        return comman_custom_response([
            "status" => false,
            "message" => __('Something Wrong.', SOCIALV_API_TEXT_DOMAIN)
        ]);
    }
}
