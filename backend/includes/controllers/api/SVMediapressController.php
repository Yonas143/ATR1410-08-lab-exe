<?php

namespace Includes\Controllers\Api;

use BP_Activity_Activity;

use Includes\baseClasses\SVActivityComments;
use Includes\baseClasses\SVBase;
use Includes\baseClasses\SVCustomNotifications;
use WP_REST_Server;

class SVMediapressController extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/get-supported-media-list',
                array(
                    'methods'             => WP_REST_Server::ALLMETHODS,
                    'callback'            => [$this, 'socialv_get_supported_media_type'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/media-active-statuses',
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'socialv_rest_media_statuses'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/create-album',
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'socialv_rest_create_album'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/albums',
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'socialv_rest_albums'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/upload-media',
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'socialv_rest_upload_media'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/album-media-list',
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'socialv_rest_album_media_list'],
                    'permission_callback' => '__return_true'
                )
            );

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/delete-album-media',
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'socialv_delete_album_media'],
                    'permission_callback' => '__return_true'
                )
            );
        });
    }
    public function socialv_get_supported_media_type($request)
    {
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $component = !empty($parameters['component']) ? $parameters['component'] : "members"; //groups,members/sitewide

        $types = [
            ["title" => esc_html__("Photos", SOCIALV_API_TEXT_DOMAIN), "type" => "photo", "is_active" => false],
            ["title" => esc_html__("Videos", SOCIALV_API_TEXT_DOMAIN), "type" => "video", "is_active" => false],
            ["title" => esc_html__("Audios", SOCIALV_API_TEXT_DOMAIN), "type" => "audio", "is_active" => false],
            ["title" => esc_html__("Documents", SOCIALV_API_TEXT_DOMAIN), "type" => "doc", "is_active" => false]
        ];

        if (class_exists('BuddyPress_GIPHY'))
            $types[] = ["title" => esc_html__("GIF", SOCIALV_API_TEXT_DOMAIN), "type" => "gif", "is_active" => true, "allowed_type" => ["gif"]];

        foreach ($types as $key => $type) {

            if (mpp_is_active_type($type['type']) && mpp_component_supports_type($component, $type['type'])) {
                $types[$key]['is_active']       = true;
                $types[$key]['allowed_type']    = mpp_get_allowed_file_extensions($type['type']);
            }
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("List of supported media", SOCIALV_API_TEXT_DOMAIN),
            "data" => $types
        ]);
    }

    public function socialv_rest_media_statuses($request)
    {
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $component = !empty($parameters['component']) ? $parameters['component'] : "sitewide"; //groups,members/sitewide        
        $statuses = mpp_get_active_statuses();

        if ("groups" == $component) {
            unset($statuses['friendsonly']);
            unset($statuses['private']);
        } else {
            unset($statuses['groupsonly']);
        }

        return comman_custom_response([
            "status" => true,
            "message" => __("Statuse of rest media", SOCIALV_API_TEXT_DOMAIN),
            "data" => array_values($statuses)
        ]);
    }

    public function socialv_rest_create_album($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $args = [
            "user_id"       => $current_user_id,
            "component"     => $parameters["component"],
            "title"         => $parameters["title"],
            "description"   => $parameters["description"],
            "type"          => $parameters["type"],
            "status"        => $parameters["status"]
        ];
        if ("groups" == $parameters["component"])
            $args["group_id"] = $parameters["group_id"];

        $response = sv_action_create_gallery($args);
        return comman_custom_response([
            "status" => true,
            "message" => $response[1],
            "data" => ["album_id" => $response[0]]
        ]);
    }

    public function socialv_rest_albums($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $parameters['current_user_id'] = $current_user_id;

        $response = sv_get_albums($parameters);

        return comman_custom_response([
            "status" => true,
            "message" => __("Album List", SOCIALV_API_TEXT_DOMAIN),
            "data" => $response
        ]);
    }

    public function socialv_rest_upload_media($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);
        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $count = $parameters["count"];


        $args = [
            "component"     => "members",
            "gallery_id"    => $parameters["gallery_id"]
        ];

        if (!empty($parameters["group_id"])) {
            $args["component"] = "groups";
            $args["post_in"] = $parameters["group_id"];
        }
        $message = esc_html__("Media uploaded successfully.", SOCIALV_API_TEXT_DOMAIN);
        $status_code = true;
        for ($i = 0; $i < $count; $i++) {
            if (isset($_FILES['media_' . $i])) {
                $media = ['_mpp_file' => $_FILES['media_' . $i]];

                $media_response = socialv_add_media($media, $current_user_id, $args);
                if (!isset($media_response["media_id"])) {
                    $message = esc_html__("Something wrong. Try Again.", SOCIALV_API_TEXT_DOMAIN);
                    $status_code = false;
                }
            }
        }

        return comman_custom_response([
            "status" => $status_code,
            "message" => $message
        ]);
    }

    public function socialv_rest_album_media_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = (int) $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);
        $parameters['current_user_id'] = $current_user_id;

        $response = sv_get_album_media_list($parameters);

        return comman_custom_response([
            "status" => true,
            "message" => __("Album`s Media List", SOCIALV_API_TEXT_DOMAIN),
            "data" => $response
        ]);
    }

    public function socialv_delete_album_media($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $id     = isset($parameters['id']) ? $parameters['id'] : "";
        $type   = isset($parameters['type']) ? $parameters['type'] : "";
        if (empty($id) || empty($type))
            return comman_custom_response([
                "status" => false,
                "message" => __("Something went wrong. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);


        $status_code = true;
        if ("gallery" == $type) {
            $user_can   = "mpp_user_can_delete_gallery";
            $delete     = "mpp_delete_gallery";
            $message    = __("Gallery has been deleted.", SOCIALV_API_TEXT_DOMAIN);
        } else if ("media" == $type) {
            $user_can   = "mpp_user_can_delete_media";
            $delete     = "mpp_delete_media";
            $message    = __("Media has been deleted.", SOCIALV_API_TEXT_DOMAIN);
        } else if ("gif" == $type) {
            if (bp_activity_update_meta($id, '_bp_activity_gif_data', ""))
                return comman_custom_response([
                    "status" => true,
                    "message" => __("Media has been deleted.", SOCIALV_API_TEXT_DOMAIN)
                ]);

            return comman_custom_response([
                "status" => false,
                "message" => __("Something went wrong. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        }

        $ids = explode(",", $id);

        foreach ($ids as $media_id) {
            if (!$user_can($media_id, $current_user_id) || !$delete($media_id)) {
                $status_code    = false;
                $message        = __("Something went wrong. Try again.", SOCIALV_API_TEXT_DOMAIN);
            }
        }

        return comman_custom_response([
            "status" => $status_code,
            "message" => $message
        ]);
    }
}
