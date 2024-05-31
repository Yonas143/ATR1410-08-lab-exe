<?php

use IR\Admin\Classes\IR_Database;


function is_reaction_active()
{
    global $is_reaction_active;

    if (isset($is_reaction_active))
        return $is_reaction_active;

    $is_reaction_active = is_plugin_active('iqonic-reactions/iqonic-reactions.php');

    return $is_reaction_active;
}

function get_reaction_db_obj()
{
    return is_reaction_active() ? new IR_Database() : false;
}

function add_activity_user_additional_data($data)
{
    $data['is_verified'] = sv_is_user_verified($data['id']);
    return $data;
}
add_filter("ir_rest_activity_reaction_user_data", "add_activity_user_additional_data");

function add_comment_user_additional_data($data)
{
    $data['is_verified'] = sv_is_user_verified($data['id']);
    return $data;
}
add_filter("ir_rest_comment_reaction_user_data", "add_comment_user_additional_data");

function sv_rest_reaction_list($id, $component)
{
    $db_class = get_reaction_db_obj();
    if ($db_class) {
        $args = [
            'page'         => 0,
            'per_page'     => 3
        ];
        if ("comment" == $component) {
            $comment = bp_activity_get(["in" => $id, "display_comments" => 1]);

            if (!$comment) return [];

            $comment = $comment["activities"];
            $activity_id = $comment[0]->item_id;
            $fetch_reactions = $db_class->getCommentsReactionList($activity_id, $id, $args);
        } else {
            $fetch_reactions = $db_class->getReactions($id, $args);
        }

        if ($fetch_reactions)
            return rest_reaction_list($fetch_reactions, $component);
    }
    return [];
}

function rest_user_reaction($id, $user_id, $component)
{
    $db_class = get_reaction_db_obj();
    $reaction = null;
    if ($db_class) {
        if ($component == "comment") {
            $comment = bp_activity_get(["in" => $id, "display_comments" => 1]);

            if (!$comment) return null;

            $comment = $comment["activities"];
            $activity_id = $comment[0]->item_id;
            $reaction = $db_class->getCommentReaction($activity_id, $user_id, $id);
            if (!isset($reaction[0])) return null;
            $reaction = $reaction[0];

            $reaction->reaction = $reaction->name;
            $reaction->icon = $reaction->image_url;
            unset($reaction->table_id);
            unset($reaction->reaction_count);
            unset($reaction->image_url);
            unset($reaction->name);
        } else {
            $reaction = $db_class->getUserReaction($id, $user_id);
            if (!isset($reaction[0])) return null;
            $reaction = $reaction[0];

            $reaction->reaction = $reaction->name;
            $reaction->icon = $reaction->image_url;
            unset($reaction->table_id);
            unset($reaction->reaction_count);
            unset($reaction->image_url);
            unset($reaction->name);
        }
    }

    return $reaction;
}

function rest_get_reaction_count($table, $where = "")
{
    $db_class = get_reaction_db_obj();
    $columns = ["COUNT(*) AS `count`"];

    $table = $db_class->$table;

    $columns = implode(",", $columns);
    return (int) ($db_class->execute_query("SELECT {$columns} FROM {$table} {$where}"))[0]->count;
}
