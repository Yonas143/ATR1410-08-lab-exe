<?php

namespace Includes\baseClasses;

class SVFriendSuggestions
{
    private $user_id;
    private $args;
    private $exclude_user;
    private $user_friends;

    function __construct($user_id)
    {
        $this->user_id = !empty($user_id) ? $user_id : get_current_user_id();
    }
    /**
     * Get Friend Suggestions.
     */
    function sv_get_friend_suggestions($args)
    {
        // Get List Of excluded Id's.
        $exclude_user_ids = (array) $this->sv_get_excluded_friends_ids();

        $this->exclude_user = $exclude_user_ids;
        $this->args = $args;

        $friend_suggestions = $this->sv_get_friend_of_friends();

        return $friend_suggestions;
    }

    /**
     * Get user list to exclude from suggestions
     */
    function sv_get_excluded_friends_ids()
    {

        // Get User Friends
        $this->user_friends = (array) friends_get_friend_user_ids($this->user_id);

        // Get User Friendship requests List.
        $friendship_requests = $this->sv_get_user_friendship_requests();

        // List of Refused Suggestions
        $refused_friends = (array) $this->sv_get_refused_friend_suggestions();

        // blocked users
        $blocked = $this->sv_get_blocked_user();

        // pending users
        $pending_users = $this->sv_get_pending_users();

        // make an array of users group+groups hidden by user
        $exclude_user_ids = array_merge($this->user_friends, $friendship_requests, $refused_friends, $pending_users, $blocked);

        // Remove Repeated ID's.
        $exclude_user_ids = array_unique($exclude_user_ids);
        $exclude_user_ids[] = $this->user_id;

        return $exclude_user_ids;
    }

    /**
     * Get all user _ids if no friends / new user
     */
    function get_all_user_ids($exclude = [])
    {
        $offset = ($this->args['page'] - 1) * $this->args['per_page'];
        $per_page = $this->args['per_page'];
        $args = ["fields" => "ID", "number" => $per_page, "offset" => $offset, "paged" => $this->args['page']];
        if (!empty($exclude))
            $args["exclude"] = implode(",", $exclude);
        $user_ids = get_users($args);
        return !empty($user_ids) ? $user_ids : [];
    }

    public function sv_get_friend_of_friends()
    {
        global $wpdb;

        // Init Vars.
        $bp = buddypress();
        $suggestion_list = [];
        $user_in = implode(",", $this->user_friends);

        $exclude = implode(",", array_filter($this->exclude_user));
        $offset = ($this->args['page'] - 1) * $this->args['per_page'];
        $per_page = $this->args['per_page'];

        $friend_of_friends = "SELECT DISTINCT friend_user_id FROM 
        (SELECT DISTINCT friend_user_id FROM {$bp->friends->table_name} WHERE friend_user_id NOT IN ({$exclude}) AND  initiator_user_id IN($user_in) AND is_confirmed=1 
        UNION 
        SELECT DISTINCT initiator_user_id FROM {$bp->friends->table_name} WHERE initiator_user_id NOT IN ({$exclude}) AND friend_user_id IN($user_in) AND is_confirmed=1) 
        AS friends_of_friend LIMIT {$offset},{$per_page}";


        $friendship_result = $wpdb->get_col($wpdb->prepare($friend_of_friends));

        if (empty($friendship_result)) {
            $friendship_result = $this->get_all_user_ids($this->exclude_user);
        }
        if ($friendship_result) {
            foreach ($friendship_result as $user_id) {

                $user_avatar_url = bp_core_fetch_avatar(
                    array(
                        'item_id' => $user_id,
                        'type'    => 'full',
                        'no_grav' => true,
                        'html'    => FALSE     // FALSE = return url, TRUE (default) = return img html
                    )
                );

                $suggestion_list[] = [
                    "user_id"           => (int) $user_id,
                    "user_name"         => bp_core_get_user_displayname($user_id),
                    "user_mention_name" => bp_members_get_user_slug($user_id),
                    "user_image"        => $user_avatar_url,
                    "is_user_verified"  => sv_is_user_verified($user_id)
                ];
            }
        }

        return $suggestion_list;
    }
    /**
     * User Friendship requests
     */
    public function sv_get_user_friendship_requests()
    {
        global $wpdb;

        // Init Vars.
        $bp = buddypress();

        // SQL get users requests sent by current user
        $sql = "SELECT friend_user_id FROM {$bp->friends->table_name} WHERE initiator_user_id = %d AND is_confirmed = 0";

        // SQL get users requests sent by other to current user
        $sql_initiator = "SELECT initiator_user_id FROM {$bp->friends->table_name} WHERE friend_user_id = %d AND is_confirmed = 0";

        // Get List of Membership Requests.
        $friendship_requests = $wpdb->get_col($wpdb->prepare($sql, $this->user_id));
        $initiator_friendship_requests = $wpdb->get_col($wpdb->prepare($sql_initiator, $this->user_id));

        if ($initiator_friendship_requests)
            $friendship_requests = array_merge($friendship_requests, $initiator_friendship_requests);

        return $friendship_requests;
    }

    public function sv_get_refused_friend_suggestions()
    {
        // Get Refused Groups.
        return get_user_meta($this->user_id, 'socialv_refused_friend_suggestions', true);
    }
    public function sv_get_blocked_user()
    {
        $exclude_members = [];

        $bloked_id = function_exists("imt_get_blocked_members_ids") ? imt_get_blocked_members_ids($this->user_id) : [];
        $blocked_by = function_exists("imt_get_members_blocked_by_ids") ? imt_get_members_blocked_by_ids($this->user_id) : [];
        if ($bloked_id)
            $exclude_members = $bloked_id;

        if ($blocked_by)
            $exclude_members = array_merge($exclude_members, $blocked_by);


        return $exclude_members;
    }
    public function sv_get_pending_users()
    {
        $args = [
            'fields'        => 'ids',
            "meta_query"    => [
                [
                    "key" => "activation_key",
                    "value" => "",
                    "compare" => "!="
                ]
            ]

        ];
        return get_users($args);
    }
}
