<?php

namespace Includes\baseClasses;

class SVGorupSuggestions
{
    /**
     * Get Suggestions Groups.
     */
    function get_group_suggestions($user_id)
    {

        // Get User ID.
        $user_id = ($user_id) ? $user_id : get_current_user_id();

        // Get List Of excluded Id's.
        $excluded_ids = (array) $this->get_excluded_groups_ids($user_id);

        // Get Friends Groups.
        $friends_groups = (array) $this->get_user_friends_groups($user_id);

        // Get Suggestion Groups.
        $group_suggestions = array_diff($friends_groups, $excluded_ids);

        // Randomize Order.
        shuffle($group_suggestions);

        // Return Group ID's.
        return $group_suggestions;
    }


    /**
     * Get Suggestions List.
     */
    function get_suggestions_list($args)
    {

        // Get User ID.
        $user_id = isset($args['current_user_id']) ? $args['current_user_id'] : get_current_user_id();

        // Get Suggestion Groups.
        $args["group_ids"] = $this->get_group_suggestions($user_id);

        // Limit Groups Number
        $group_suggestions = $this->get_obj($args);

        return $group_suggestions;
    }

    public function get_obj($args)
    {

        $parse_args = bp_ajax_querystring('groups') . "&include=" . implode(",", $args["group_ids"]) . "&per_page=" . $args["per_page"] . "&page=" . $args["page"];
        $groups = [];

        if (bp_has_groups($parse_args)) :

            while (bp_groups()) : bp_the_group();
                $group_id = bp_get_group_id();

                $groups[] = [
                    "id"                => $group_id,
                    "group_avtar_image" => bp_get_group_avatar('html=false&type=full'),
                    "name"              => bp_get_group_name(),
                ];

            endwhile;

        endif;
        return $groups;
    }
    /**
     * Get User Friends Groups
     */
    function get_user_friends_groups($user_id = null)
    {

        global $bp, $wpdb;

        // Get User ID.
        $user_id = ($user_id) ? $user_id : get_current_user_id();

        // Get All User Friends List.
        $user_friends = (array) friends_get_friend_user_ids($user_id);

        // Check If User have friends.
        if (empty($user_friends)) {
            return;
        }

        // Convert Friends List into string an separate user ids by commas.
        $friends_ids = '(' . join(',', $user_friends) . ')';

        // Prepare Friends SQL.
        $friends_groups_sql = "SELECT DISTINCT group_id FROM {$bp->groups->table_name} g, {$bp->groups->table_name_members} m WHERE g.id=m.group_id AND ( g.status='public' OR g.status='private' ) AND m.user_id in {$friends_ids} AND is_confirmed= 1";

        // Get Friend Groups ID's.
        $friends_groups_result = $wpdb->get_col($friends_groups_sql);

        return $friends_groups_result;
    }

    /**
     * Get User Excluded Groups
     */
    function get_excluded_groups_ids($user_id = null)
    {

        global $bp, $wpdb;

        // Get User ID.
        $user_id = ($user_id) ? $user_id : get_current_user_id();

        // Get Sql Result.
        $groups_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT group_id FROM {$bp->groups->table_name_members} WHERE user_id = %d ", $user_id));

        // List of Refused Suggestions
        $refused_groups = (array) get_user_meta($user_id, 'socialv_refused_group_suggestions', true);

        // Make an array of users group+groups hidden by user & Remove Repeated ID's
        $excluded_ids = array_unique(array_merge($groups_ids, $refused_groups));

        return $excluded_ids;
    }

    /**
     * Save New Refused Suggestions.
     */
    public static function sv_hide_group_suggestion($args)
    {
        $args = wp_parse_args(
            $args,
            [
                "suggestion_id"     => 0,
                "current_user_id"   => 0
            ]
        );
        // Get Suggested Group ID.
        $suggestion_id = $args['suggestion_id'];

        if (!$suggestion_id)
            return false;

        // Get Current User ID.
        if (!$args['current_user_id']) return false;

        $user_id = $args['current_user_id'];

        // Get Old Refused Suggestions.
        $refused_suggestions = (array) get_user_meta($user_id, 'socialv_refused_group_suggestions', true);

        // Add The new Refused Suggestion to the old refused suggetions list.
        if (!in_array($suggestion_id, $refused_suggestions)) {
            $refused_suggestions[] = $suggestion_id;
        }

        // Save New Refused Suggestion
        return update_user_meta($user_id, 'socialv_refused_group_suggestions', $refused_suggestions);
    }
}
