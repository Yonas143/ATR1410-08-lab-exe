<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use WP_REST_Server;


class SVForumController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/create-forums-topic', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_create_forums_topic'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-all-forums', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_forums'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-forum-details', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_forum_details'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-topic-list', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_topic_list'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-topic-details', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_topic_details'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/reply-forums-topic', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_reply_forums_topic'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/edit-topic-reply', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_edit_topic_reply'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/favorite-topic', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_favorite_topic'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/subscribe', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_subscribe'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/subscription-list', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_subscription_list'],
                'permission_callback' => '__return_true'
            ));

            register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/get-topic-reply-list', array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => [$this, 'socialv_get_topic_reply_list'],
                'permission_callback' => '__return_true'
            ));
        });
    }

    public function socialv_create_forums_topic($request)
    {

        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $topic_created = sv_create_forums_topic($parameters, $current_user_id);

        if ($topic_created)
            return comman_custom_response([
                "status" => true,
                "message" => __("Success", SOCIALV_API_TEXT_DOMAIN)
            ]);
        else
            return comman_custom_response([
                "status" => false,
                "message" => __("Something worng. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
    }

    public function socialv_get_forums($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $keyword = isset($parameters['keyword']) ? trim($parameters['keyword']) : "";

        $page = $parameters['page'];
        $posts_per_page = $parameters['posts_per_page'];

        $args = [
            "paged"             => $page,
            "posts_per_page"    => $posts_per_page
        ];

        if (!empty($keyword)) {
            $args["s"] = $parameters['keyword'];
            $args["posts_per_page"] = -1;
        }

        $forums = sv_get_forums($current_user_id, $args);

        if (!empty($keyword))
            $forums_by_topic = get_forums_by_topic($parameters, $forums['ids'], $current_user_id);
        else
            $forums_by_topic = [];

        $forums = array_merge($forums['forums'], $forums_by_topic);

        return comman_custom_response([
            "status" => true,
            "message" => __("Forum Details", SOCIALV_API_TEXT_DOMAIN),
            "data" => $forums
        ]);
    }

    public function socialv_get_forum_details($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $forum_id = !empty($parameters["forum_id"]) ? $parameters["forum_id"] : 0;
        if (!$forum_id) return comman_custom_response([
            "status" => false,
            "message" => __("Not Found Forum Id.", SOCIALV_API_TEXT_DOMAIN)
        ]);

        $forum = bbp_get_forum($forum_id);
        if (!$forum) return comman_custom_response([
            "status" => false,
            "message" => __("Forum Not Found.", SOCIALV_API_TEXT_DOMAIN)
        ]);

        $forum_id = $forum->ID;
        $type = bbp_get_forum_type($forum_id);
        $is_private = bbp_get_forum_visibility($forum_id) === "private" ? 1 : 0;
        $last_update = sv_get_forum_last_updated($forum_id, $type);

        $forum_details = [
            "id"            => $forum_id,
            "title"         => $forum->post_title,
            "description"   => $forum->post_content,
            "image"         => "",
            "is_private"    => $is_private,
            "is_subscribed" => bbp_is_user_subscribed($current_user_id, $forum_id),
            "last_update"   => $last_update
        ];

        $group_ids = bbp_get_forum_group_ids($forum_id);

        if (!empty($group_ids) && isset($group_ids[0])) {
            $group = groups_get_group($group_ids[0]);
            if ($group) {
                $forum_details["image"] = bp_get_group_avatar('html=false&type=full', $group);
                $forum_details["group_details"] = sv_get_forum_group_details($group_ids[0], $current_user_id);
            }
        }

        $args = ["post_parent" => $forum_id];

        if ("category" == $type) {
            $args["paged"] = !empty($parameters['forums_page']) ? $parameters['forums_page'] : 1;
            $args["posts_per_page"] = !empty($parameters['forums_per_page']) ? $parameters['forums_per_page'] : 20;

            $children_list = sv_get_forums($current_user_id, $args)['forums'];

            if (!empty($children_list))
                $forum_details["forum_list"] = $children_list;
        } else {
            $subforums = bbp_get_forum_subforum_count($forum_id, true);
            if ($subforums && $subforums > 0) {
                $args["paged"] = !empty($parameters['forums_page']) ? $parameters['forums_page'] : 1;
                $args["posts_per_page"] = !empty($parameters['forums_per_page']) ? $parameters['forums_per_page'] : 20;
                $children_list = sv_get_forums($current_user_id, $args)['forums'];

                if (!empty($children_list))
                    $forum_details["forum_list"] = $children_list;
            }

            $args["paged"] = !empty($parameters['topics_page']) ? $parameters['topics_page'] : 1;
            $args["posts_per_page"] = !empty($parameters['topics_per_page']) ? $parameters['topics_per_page'] : 20;

            $topics = sv_get_topics($args);
            if (!empty($topics))
                $forum_details["topic_list"] = $topics;
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("Forum Details.", SOCIALV_API_TEXT_DOMAIN),
            "data" => $forum_details
        ]);
    }

    public function socialv_get_topic_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $forum_id = !empty($parameters["forum_id"]) ? $parameters["forum_id"] : 0;

        $page = !empty($parameters['page']) ? $parameters['page'] : 1;
        $posts_per_page = !empty($parameters['posts_per_page']) ? $parameters['posts_per_page'] : 20;

        $args = [
            "paged"             => $page,
            "posts_per_page"    => $posts_per_page
        ];

        if ($parameters['is_user_topic']) {
            $args["author"] = $current_user_id;
        } elseif ($parameters['is_favorites']) {
            $args["meta_query"] =   [
                [
                    "key"       => "_bbp_favorite",
                    "value"     => $current_user_id,
                    "compare"   => "=="

                ]
            ];
        } elseif ($parameters['is_engagements']) {
            $args["meta_query"] =   [
                [
                    "key"       => "_bbp_engagement",
                    "value"     => $current_user_id,
                    "compare"   => "=="

                ]
            ];
        } else {
            $args["post_parent"] = $forum_id;
        }

        $topics = sv_get_topics($args, $current_user_id, true);

        return comman_custom_response([
            "status" => true,
            "message" => __("Topic List.", SOCIALV_API_TEXT_DOMAIN),
            "data" => $topics
        ]);
    }

    public function socialv_get_topic_details($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (empty($parameters["topic_id"]))  return comman_custom_response([
            "status" => false,
            "message" => __("Topic Id is empty.", SOCIALV_API_TEXT_DOMAIN),
            "data" => []
        ]);

        $topic = sv_get_topic_details($parameters, $current_user_id);

        return comman_custom_response([
            "status" => true,
            "message" => __("Topic Details.", SOCIALV_API_TEXT_DOMAIN),
            "data" => $topic
        ]);
    }
    public function socialv_reply_forums_topic($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (empty($parameters["topic_id"])) return comman_custom_response([
            "status" => true,
            "message" => __("Topic Id Not Found.", SOCIALV_API_TEXT_DOMAIN)
        ]);

        $reply_id = sv_reply_forums_topic($parameters, $current_user_id);

        if ($reply_id)
            return comman_custom_response([
                "status" => true,
                "message" => __("Success.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        else
            return comman_custom_response([
                "status" => false,
                "message" => __("Something worng. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
    }

    public function socialv_edit_topic_reply($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (empty($parameters["id"])) return [];

        $id = sv_update_forums_topic_reply($parameters, $current_user_id);

        if ($id)
            return comman_custom_response([
                "status" => true,
                "message" => __("Success", SOCIALV_API_TEXT_DOMAIN)
            ]);
        else
            return comman_custom_response([
                "status" => false,
                "message" => __("Something worng. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
    }

    public function socialv_favorite_topic($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (empty($parameters["topic_id"])) return [];
        $topic_id = $parameters["topic_id"];

        if (!bbp_is_user_favorite($current_user_id, $topic_id) && bbp_add_user_favorite($current_user_id, $topic_id))
            return comman_custom_response([
                "status" => true,
                "message" => __("Success", SOCIALV_API_TEXT_DOMAIN)
            ]);
        elseif (bbp_remove_user_favorite($current_user_id, $topic_id))
            return comman_custom_response([
                "status" => true,
                "message" => __("Success", SOCIALV_API_TEXT_DOMAIN)
            ]);
        else
            return comman_custom_response([
                "status" => false,
                "message" => __("Something worng. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
    }

    public function socialv_subscribe($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (empty($parameters["id"])) return [];
        $id = $parameters["id"];

        if (!bbp_is_user_subscribed($current_user_id, $id) && bbp_add_user_subscription($current_user_id, $id))
            return comman_custom_response([
                "status" => true,
                "message" => __("Success", SOCIALV_API_TEXT_DOMAIN)
            ]);
        elseif (bbp_remove_user_subscription($current_user_id, $id))
            return comman_custom_response([
                "status" => true,
                "message" => __("Success", SOCIALV_API_TEXT_DOMAIN)
            ]);
        else
            return comman_custom_response([
                "status" => false,
                "message" => __("Something worng. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
    }

    public function socialv_get_subscription_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $page = $parameters['page'];
        $posts_per_page = $parameters['posts_per_page'];

        $args = [
            "post_parent"       => "any",
            "paged"             => $page,
            "posts_per_page"    => $posts_per_page,
            "meta_query"        => [
                [
                    "key"       => "_bbp_subscription",
                    "value"     => $current_user_id,
                    "compare"   => "=="
                ]
            ]
        ];


        $response = [
            "forums" => sv_get_forums($current_user_id, $args)["forums"],
            "topics" => sv_get_topics($args)
        ];

        return comman_custom_response([
            "status" => true,
            "message" => __("List of topic reply", SOCIALV_API_TEXT_DOMAIN),
            "data" => $response
        ]);
    }

    public function socialv_get_topic_reply_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if ($parameters['is_user_replies'] || !bbp_thread_replies())
            $response = sv_get_replies($parameters, $current_user_id);
        else
            $response = sv_get_topic_posts($parameters);

        return comman_custom_response([
            "status" => true,
            "message" => __("List of topic reply", SOCIALV_API_TEXT_DOMAIN),
            "data" => $response
        ]);
    }
}
