<?php

namespace Includes\Controllers\Api;

use BP_Activity_Activity;
use BP_Invitation;
use Includes\baseClasses\SVActivityComments;
use Includes\baseClasses\SVBase;
use Includes\baseClasses\SVCustomNotifications;
use WP_REST_Server;

class SVInvitations extends SVBase
{

    public $module = 'socialv';

    public $nameSpace;

    function __construct()
    {

        $this->nameSpace = SOCIALV_API_NAMESPACE;

        add_action('rest_api_init', function () {

            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/invite-list',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'socialv_rest_invite_list'],
                        'permission_callback' => '__return_true'
                    ],
                    [
                        'methods'             => WP_REST_Server::DELETABLE,
                        'callback'            => [$this, 'socialv_rest_remove_invite'],
                        'permission_callback' => '__return_true'
                    ]
                ]
            );
            register_rest_route(
                $this->nameSpace . '/api/v1/' . $this->module,
                '/send-invite',
                [
                    [
                        'methods'             => WP_REST_Server::EDITABLE,
                        'callback'            => [$this, 'socialv_rest_send_invite'],
                        'permission_callback' => '__return_true'
                    ]
                ]
            );
        });
    }


    public function socialv_rest_invite_list($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $invite_list = [];


        $args = [
            "inviter_id"    => $current_user_id,
            "page"          => $parameters["page"],
            "per_page"      => $parameters["per_page"]
        ];
        $query_args = wp_parse_args(
            $args,
            [
                "inviter_id"    => $current_user_id,
                "page"          => 1,
                "per_page"      => 20
            ]
        );

        if (bp_has_members_invitations($query_args)) :
            while (bp_the_members_invitations()) : bp_the_members_invitation();
                $invite_list[] = [
                    "id"            => bp_get_the_members_invitation_property('id'),
                    "email"         => bp_get_the_members_invitation_property('invitee_email'),
                    "message"       => bp_get_the_members_invitation_property('content'),
                    "invite_sent"   => bp_get_the_members_invitation_property('invite_sent'),
                    "accepted"      => bp_get_the_members_invitation_property('accepted'),
                    "date_modified" => bp_get_the_members_invitation_property('date_modified')
                ];
            endwhile;
        endif;
        return comman_custom_response([
            "status" => true,
            "message" => __("Invite List", SOCIALV_API_TEXT_DOMAIN),
            "data" => $invite_list
        ]);
    }

    public function socialv_rest_remove_invite($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        $id = $parameters["id"];

        $remove = bp_members_invitations_delete_invites(["id" => $id, "inviter_id" => $current_user_id]);

        if ($remove)
            return comman_custom_response([
                "status" => true,
                "message" => __("Invite cancelled.", SOCIALV_API_TEXT_DOMAIN)
            ]);
        else
            return comman_custom_response([
                "status" => true,
                "message" => __("Something Wrong. Try again.", SOCIALV_API_TEXT_DOMAIN)
            ]);
    }

    public function socialv_rest_send_invite($request)
    {
        $data = svValidationToken($request);

        if ($data['status'] && isset($data['user_id']))
            $current_user_id = $data['user_id'];
        else
            return comman_custom_response($data, $data['status_code']);

        $parameters = $request->get_params();
        $parameters = svRecursiveSanitizeTextField($parameters);

        if (email_exists($parameters["email"]))
            return comman_custom_response([
                "status" => false,
                "message" => __("User already exists.", SOCIALV_API_TEXT_DOMAIN)
            ]);

        if ($parameters['type'] == "resend") {
            $is_invite_sent = 0;
            $invite_ids = $parameters["invite_id"];
            foreach ($invite_ids as $invite_id) {
                if (bp_members_invitation_resend_by_id($invite_id)) {
                    $is_invite_sent++;
                }
            }
            $message = sprintf(
                esc_html(
                    /* translators: %d: the number of invitations that were resent. */
                    _n('%d invitation was resent.', '%d invitations were resent.', $is_invite_sent, SOCIALV_API_TEXT_DOMAIN)
                ),
                $is_invite_sent
            );

            $status_code = true;
        } else {
           
            $invition_check = "not send";
            $args = [
                'invitee_email' => $parameters["email"],
                'inviter_id'    => $current_user_id,
                'content'       => $parameters["message"],
                'send_invite'   => 1,
            ];
            $already_invited = bp_members_invitations_get_invites($args);
            foreach ($already_invited as $invite_id) {
                if ($invite_id->invitee_email == $parameters["email"]) {
                    return comman_custom_response([
                        "status" => false,
                        "message" => __("Already invited.", SOCIALV_API_TEXT_DOMAIN)
                    ]);
                }
            }
            if ($invition_check == "not send") {
                $is_invite_sent = bp_members_invitations_invite_user($args);
                $message = __("Invite has been sent.", SOCIALV_API_TEXT_DOMAIN);
                $status_code = true;
            }
        }
        if (!$is_invite_sent) {
            $message = __("Something Wrong. Try again.", SOCIALV_API_TEXT_DOMAIN);
            $status_code = false;
        }
        return comman_custom_response([
            "status" => $status_code,
            "message" => $message,
        ]);
    }
}
