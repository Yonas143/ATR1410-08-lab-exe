<?php

use Includes\baseClasses\SVCustomNotifications;

// notify forum subscribers
add_action("bbp_new_topic", "sv_new_topic_notification", 10, 4);
function sv_new_topic_notification($topic_id, $forum_id, $anonymous_data, $topic_author)
{
    if (bp_is_active('notifications')) {
        $args['status']             = true;
        $args["component_name"]     = "forums";
        $args["component_action"]   = "sv_new_topic";
        $subscribers                = bbp_get_subscribers($forum_id);
        if (count($subscribers) > 0) {
            foreach ($subscribers as $subscriber_id) {
                $args["user_to_notify"]     = $subscriber_id;
                SVCustomNotifications::sv_add_user_notification($topic_id, $args, $topic_author);
            }
        }
    }
}

// notify topic subscribers
add_action("bbp_new_reply", "sv_new_reply_notification", 10, 5);
function sv_new_reply_notification($reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author)
{
    if (bp_is_active('notifications')) {
        $args['status']             = true;
        $args["component_name"]     = "forums";
        $args["component_action"]   = "sv_new_topic_reply";
        $subscribers                = bbp_get_subscribers($topic_id);
        if (count($subscribers) > 0) {
            foreach ($subscribers as $subscriber_id) {
                $args["user_to_notify"]     = $subscriber_id;
                SVCustomNotifications::sv_add_user_notification($reply_id, $args, $reply_author);
            }
        }
    }
}

function sv_get_forums($current_user_id, $args = [])
{
    $forums = [];
    $ids = [];
    if (bbp_has_forums($args)) :
        while (bbp_forums()) : bbp_the_forum();
            $forum_id = bbp_get_forum_id();
            $is_private = bbp_get_forum_visibility() === "private" ? 1 : 0;
            $forums[] = [
                "id"            => $forum_id,
                "title"         => bbp_get_forum_title(),
                "description"   => bbp_get_forum_content(),
                "type"          => bbp_get_forum_type(),
                "topic_count"   => bbp_get_forum_topic_count(),
                "post_count"    => bbp_get_forum_post_count(),
                "is_private"    => $is_private,
                "freshness"     => sv_get_forum_freshness(),
                "group_details" => sv_get_forum_group_details(bbp_get_forum_group_ids(), $current_user_id)
            ];
            $ids[] = $forum_id;
        endwhile;
    endif;
    return ["forums" => $forums, "forum_ids" => $ids];
}

function sv_get_forum_freshness($forum_id = false)
{
    $forum_id = $forum_id ? $forum_id : bbp_get_forum_id();
    if (!$forum_id) return [];

    $freshness = [];
    $args = [
        'show_stickies'     => false,
        'order'             => 'DESC',
        'post_parent'       => $forum_id,
        'posts_per_page'    => 3
    ];
    $topic_result = sv_has_topics($args);
    if ($topic_result) {
        $topics = $topic_result->posts;
        $repeated_authors = [];
        foreach ($topics as $topic) :

            $freshness_author = array_reverse(bbp_get_topic_engagements($topic->ID));

            $count = count($freshness_author);

            $len = $count < 3 ? $count : 3;
            for ($i = 0; $i < $len; $i++) {
                if (in_array($freshness_author[$i], $repeated_authors)) continue;

                $repeated_authors[] = $freshness_author[$i];

                $freshness[] = [
                    "user_id"               => $freshness_author[$i],
                    "user_profile_image"    => get_avatar_url($freshness_author[$i], ["size" => 150])
                ];
            }

        endforeach;
    }

    return $freshness;
}

function sv_get_forum_group_details($id, $current_user_id)
{
    if (empty($id)) return [];

    if (is_array($id)) {
        $groups = [];
        foreach ($id as $group_id) {
            $group = groups_get_group($group_id);
            if (!$group) continue;
            $is_member = groups_is_user_member($current_user_id, $group_id);
            $creator_name = bp_core_get_user_displayname($group->creator_id);
            $groups[] = [
                "group_id"          => $group->id,
                "created_by_id"     => $group->creator_id,
                "created_by_name"   => $creator_name ? $creator_name : "",
                "is_group_member"   => $is_member ? true : $is_member,
                "created_at_date "  => $group->date_created,
                "cover_image"       => bp_get_group_cover_url($group)
            ];
        }
        return $groups;
    } else {
        $group = groups_get_group($id);
        if (!$group) return [];
        $is_member = groups_is_user_member($current_user_id, $id);
        $creator_name = bp_core_get_user_displayname($group->creator_id);
        return [
            "group_id"          => $group->id,
            "created_by_id"     => $group->creator_id,
            "created_by_name"   => $creator_name ? $creator_name : "",
            "is_group_member"   => $is_member ? true : $is_member,
            "created_at_date "  => $group->date_created,
            "cover_image"       => bp_get_group_cover_url($group)
        ];
    }
}
function sv_get_forum_last_updated($forum_id, $type = "forum")
{
    $last_update_by_id = bbp_get_forum_last_reply_author_id($forum_id);
    if (!$last_update_by_id)
        $last_update_by_id = bbp_get_forum_last_topic_author_id();

    $author_name = get_user_meta($last_update_by_id, "nickname", true);

    if (empty($author_name)) {
        $author_name = get_the_author_meta('user_login', $last_update_by_id);
    }

    $topic_count = bbp_get_forum_topic_count($forum_id, true, true);
    // $topic_count += bbp_get_forum_topic_count_hidden($forum_id, false, true);
    $last_update_time = bbp_get_forum_last_active_time($forum_id);

    $last_update = _n(
        sprintf("This %s has %d topic, and was last updated %s by %s", $type, $topic_count, $last_update_time, $author_name),
        sprintf("This %s has %d topics, and was last updated %s by %s", $type, $topic_count, $last_update_time, $author_name),
        $topic_count,
        "socialv-api"
    );

    return $last_update;
}


function sv_create_forums_topic($parameters, $current_user_id)
{
    if (!current_user_can('publish_topics')) return false;

    if (empty($parameters)) return false;

    if (empty($parameters['forum_id'])) return false;

    $forum_id = $parameters['forum_id'];
    if (bbp_is_forum_category($forum_id)) return false;
    if (bbp_is_forum_closed($forum_id) && !current_user_can('edit_forum', $forum_id)) return false;
    if (bbp_is_forum_private($forum_id) && !current_user_can('read_forum', $forum_id)) return false;
    if (bbp_is_forum_hidden($forum_id) && !current_user_can('read_forum', $forum_id)) return false;

    if (empty($parameters['topic_title'])) return false;

    $title = $parameters['topic_title'];
    if (bbp_is_title_too_long($title)) return false;

    $content = $parameters['topic_content'];

    if (isset($parameters['image']))
        $content .= "<img src='{$parameters['image']}' />";

    $args = array(
        'post_parent'    => $forum_id, // forum ID
        'post_status'    => bbp_get_public_status_id(),
        'post_type'      => bbp_get_topic_post_type(),
        'post_author'    => $current_user_id,
        'post_content'   => $content,
        'post_title'     => $title,
        'comment_status' => 'closed',
        'menu_order'     => 0
    );
    if (!bbp_check_for_duplicate($args)) return false;

    $topic_id = bbp_insert_topic($args);
    do_action('bbp_new_topic', $topic_id, $forum_id, [], $current_user_id);
    if (!empty($topic_id) && !is_wp_error($topic_id)) {
        if (!empty($parameters['tags'])) {
            $tags = wp_set_object_terms($topic_id, $parameters['tags'], "topic-tag");
        }
        if ($parameters["notify_me"] && !bbp_is_user_subscribed($current_user_id, $topic_id)) {
            $is_subscribed = bbp_add_user_subscription($current_user_id, $topic_id);
        }
        // bbp_insert_topic_update_counts($topic_id, $forum_id);
        return $topic_id;
    }
    return false;
}

function sv_get_topics($args = [], $current_user_id = false, $is_topic_lists = false)
{
    $topic_list = [];

    if (bbp_has_topics($args)) :
        while (bbp_topics()) : bbp_the_topic();

            $topic_id = bbp_get_topic_id();
            $author_id = bbp_get_topic_author_id();
            $author_name = get_user_meta($author_id, "nickname", true);

            if (empty($author_name)) {
                $author_name = get_the_author_meta('user_login', $author_id);
            }

            $content = get_post_field('post_content', $topic_id);

            $topics = [
                "id"                => $topic_id,
                "title"             => bbp_get_topic_title(),
                "description"       => $content,
                "created_by_id"     => $author_id,
                "created_by_name"   => $author_name,
                "voices_count"      => bbp_get_topic_voice_count($topic_id, true),
                "post_count"        => bbp_get_topic_post_count($topic_id, true),
                "created_at_date"   => bbp_get_topic_post_date()
            ];

            if ($is_topic_lists) {
                $topics["forum_id"] =  bbp_get_topic_forum_id();
                $topics["forum_name"] =  bbp_get_topic_forum_title();
                $topics["is_favorites"] =  bbp_is_user_favorite($current_user_id, $topic_id);
                $topics["is_engagement"] =  bbp_is_user_engaged($current_user_id, $topic_id);
                $topics["is_user_topic"] =  $current_user_id == bbp_get_topic_author_id();
            }

            $topics["freshness"] =  sv_get_topic_freshness();

            $topic_list[] = $topics;

        endwhile;
    endif;
    return $topic_list;
}

function sv_get_topic_freshness()
{
    $freshness_author = array_reverse(bbp_get_topic_engagements());
    $freshness = [];
    $count = count($freshness_author);
    $len = $count < 3 ? $count : 3;
    for ($i = 0; $i < $len; $i++) {
        $freshness[] = [
            "user_id"               => $freshness_author[$i],
            "user_profile_image"    => get_avatar_url($freshness_author[$i], ["size" => 150])
        ];
    }
    return $freshness;
}

function sv_get_topic_details($parameters, $current_user_id)
{
    $topic_details = [];
    $topic = bbp_get_topic($parameters['topic_id']);
    if ($topic) :

        $topic_id = $topic->ID;
        $author_id = $topic->post_author;
        $author_name = get_user_meta($author_id, "nickname", true);

        if (empty($author_name)) {
            $author_name = get_the_author_meta('user_login', $author_id);
        }

        $content = get_post_field('post_content', $topic_id);
        if (bbp_thread_replies()) {
            $post_list = sv_get_topic_posts($parameters);
        } else {
            $post_list = sv_get_replies($parameters, $current_user_id);
        }

        $tags = bbp_get_topic_tags($topic_id);

        $topic_details = [
            "id"                => $topic_id,
            "title"             => $topic->post_title,
            "description"       => $content,
            "created_by_id"     => $author_id,
            "is_user_verified"  => sv_is_user_verified($author_id),
            "created_by_name"   => empty($author_name)?sv_default_display_name():$author_name,
            "voices_count"      => bbp_get_topic_voice_count($topic_id),
            "post_count"        => bbp_get_topic_post_count($topic_id),
            "forum_id"          => $topic->post_parent,
            "forum_name"        => bbp_get_topic_forum_title($topic_id),
            "is_favorites"      => bbp_is_user_favorite($current_user_id, $topic_id),
            "is_subscribed"     => bbp_is_user_subscribed($current_user_id, $topic_id),
            "created_at_date"   => $topic->post_date,
            "last_update"       => bbp_get_topic_last_active_time($topic_id),
            "tags"              => $tags,
            "post_list"         => $post_list
        ];


    endif;
    return $topic_details;
}

function sv_get_topic_posts($parameters, $current_user_id = false)
{
    $reply_list = [];

    $args = ["post_parent" => $parameters['topic_id'], "posts_per_page" => -1];

    $per_page = !empty($parameters['posts_per_page']) ? $parameters['posts_per_page'] : -1;
    $paged = !empty($parameters['page']) ? $parameters['page'] : 1;

    if (bbp_has_replies($args)) :

        // Get bbPress
        $bbp = bbpress();

        // Reset the reply depth
        $bbp->reply_query->reply_depth = 0;

        // In reply loop
        $bbp->reply_query->in_the_loop = true;
        $replies = $bbp->reply_query->posts;

        $parents = [];
        $child = [];

        foreach ($replies as $reply) {
            $reply = (array) $reply;

            $reply_id = $reply['ID'];
            $author_id = (int) $reply['post_author'];
            $author_name = get_user_meta($author_id, "nickname", true);

            if (empty($author_name)) {
                $author_name = get_the_author_meta('user_login', $author_id);
            }

            $reply = [
                "id"                => $reply_id,
                "title"             => $reply['post_title'],
                "content"           => $reply['post_content'],
                "created_by_id"     => $author_id,
                "created_by_name"   => empty($author_name)?sv_default_display_name():$author_name,
                "profile_image"     => get_avatar_url($author_id, ["size" => 150]),
                "is_user_verified"  => sv_is_user_verified($author_id),
                "topic_id"          => bbp_get_reply_topic_id($reply_id),
                "topic_name"        => bbp_get_reply_topic_title($reply_id),
                "created_at_date"   => bbp_get_reply_post_date($reply_id),
                "key"               => bbp_get_user_display_role($author_id),
                "reply_to"          => $reply['reply_to']
            ];

            if (!isset($reply['reply_to']) || $reply['reply_to'] == 0)
                $parents[] = $reply;
            else
                $child[$reply['reply_to']][] = (array) $reply;
        }

        $parents = array_slice($parents, $per_page * ($paged - 1), $per_page);

        foreach ($parents as $parent) {
            $reply_hierarchy[] = replyCreateTree($child, array($parent));
        }

        if ($reply_hierarchy)
            $reply_list = call_user_func_array('array_merge', $reply_hierarchy);

    endif;
    return $reply_list;
}

function replyCreateTree(&$child, $parent)
{
    $comments_hierarchy = array();
    foreach ($parent as $key => $value) {
        if (isset($child[$value['id']])) {
            $value['children'] = replyCreateTree($child, $child[$value['id']]);
        }
        $comments_hierarchy[] = $value;
    }
    return $comments_hierarchy;
}

function sv_reply_forums_topic($parameters, $current_user_id)
{
    if (!current_user_can('publish_replies')) return false;

    $topic_id = $parameters['topic_id'];
    $content = $parameters['content'];

    if (isset($parameters['image']))
        $content .= "<img src='{$parameters['image']}' />";
    $args = array(
        'post_parent'    => $topic_id, // topic ID
        'post_type'      => bbp_get_reply_post_type(),
        'post_author'    => $current_user_id,
        'post_title'     => __("Reply To: ", "socialv_api") . bbp_get_topic_title($topic_id),
        'post_content'   => $content,
        'menu_order'     => bbp_get_topic_reply_count($topic_id, true) + 1,
        'comment_status' => 'closed'
    );
    $forum_id = bbp_get_topic_forum_id($topic_id);
    $reply_to = 0;
    $topic_tags = $parameters['tags'];

    $meta = array(
        'forum_id'  => $forum_id,
        'topic_id'  => $topic_id
    );
    if (!$parameters["is_reply_topic"] && !empty($parameters['reply_id'])) {
        $reply_to = $parameters['reply_id'];
        $meta['reply_to'] = $reply_to;
    }

    $reply_id = bbp_insert_reply($args, $meta);
    do_action('bbp_new_reply', $reply_id, $topic_id, $forum_id, [], $current_user_id, false, $reply_to);

    if (!empty($reply_id) && !is_wp_error($reply_id)) {
        if (!empty($topic_tags)) {
            $tags = wp_set_object_terms($topic_id, $topic_tags, "topic-tag");
        }
        if ($parameters["notify_me"] && !bbp_is_user_subscribed($current_user_id, $topic_id)) {
            $is_subscribed = bbp_add_user_subscription($current_user_id, $topic_id);
        }
        // bbp_insert_reply_update_counts($reply_id, $topic_id, $forum_id);
        return $reply_id;
    }

    return false;
}
function sv_update_forums_topic_reply($parameters, $current_user_id)
{

    $id = $parameters['id'];
    $topic_tags = $parameters['tags'];

    if (!$parameters['is_topic']) {
        if (!current_user_can('edit_reply', $id)) return false;
        $topic_id = bbp_get_reply_topic_id($id);
        $args = [
            'reply_id'      => $id,
            'post_title'    => __("Reply To: ", "socialv_api") . bbp_get_topic_title($topic_id),
            'post_content'  => $parameters['content'],
            'topic_tags'    => $topic_tags,
            'image'         => $parameters["image"]
        ];
        $update_id = sv_update_reply($args);
    } else {
        if (!current_user_can('edit_topic', $id)) return false;
        $args = [
            'topic_id'      => $id,
            'forum_id'      => bbp_get_topic_forum_id($id),
            'post_title'    => $parameters['topic_title'],
            'post_content'  => $parameters['content'],
            'topic_tags'    => $topic_tags,
            'image'         => $parameters["image"]
        ];
        $update_id = sv_update_topic($args);
    }



    if (!empty($update_id) && !is_wp_error($update_id)) {
        if ($parameters["notify_me"] && !bbp_is_user_subscribed($current_user_id, $topic_id)) {
            $is_subscribed = bbp_add_user_subscription($current_user_id, $topic_id);
        } else {
            $is_subscribed = bbp_add_user_subscription($current_user_id, $topic_id);
        }
        return $update_id;
    }

    return false;
}

function sv_update_topic($args)
{
    // Define local variable(s)
    $revisions_removed = false;
    $topic = $topic_id = $topic_author = $forum_id = 0;
    $topic_title = $topic_content = $topic_edit_reason = '';
    $anonymous_data = array();

    /** Topic *****************************************************************/

    // Topic id was not passed
    if (empty($args['topic_id'])) {
        return false;

        // Topic id was passed
    } elseif (is_numeric($args['topic_id'])) {
        $topic_id = (int) $args['topic_id'];
        $topic    = bbp_get_topic($topic_id);
    }

    // Topic does not exist
    if (empty($topic)) {
        return false;

        // Topic exists
    } else {

        // Check users ability to create new topic
        if (!bbp_is_topic_anonymous($topic_id)) {

            // User cannot edit this topic
            if (!current_user_can('edit_topic', $topic_id)) {
                return false;
            }

            // Set topic author
            $topic_author = bbp_get_topic_author_id($topic_id);

            // It is an anonymous post
        } else {

            // Filter anonymous data
            $anonymous_data = bbp_filter_anonymous_post_data();
        }
    }

    /** Topic Forum ***********************************************************/

    // Forum id was not passed
    if (empty($args['forum_id'])) {
        return false;

        // Forum id was passed
    } elseif (is_numeric($args['forum_id'])) {
        $forum_id = (int) $args['forum_id'];
    }

    // Current forum this topic is in
    $current_forum_id = bbp_get_topic_forum_id($topic_id);

    // Forum exists
    if (!empty($forum_id) && ($forum_id !== $current_forum_id)) {

        // Forum is a category
        if (bbp_is_forum_category($forum_id)) {
            return false;

            // Forum is not a category
        } else {

            // Forum is closed and user cannot access
            if (bbp_is_forum_closed($forum_id) && !current_user_can('edit_forum', $forum_id)) {
                return false;
            }

            // Forum is private and user cannot access
            if (bbp_is_forum_private($forum_id) && !current_user_can('read_forum', $forum_id)) {
                return false;

                // Forum is hidden and user cannot access
            } elseif (bbp_is_forum_hidden($forum_id) && !current_user_can('read_forum', $forum_id)) {
                return false;
            }
        }
    }

    /** Topic Title ***********************************************************/

    if (!empty($args['post_title'])) {
        $topic_title = sanitize_text_field($args['post_title']);
    }

    // Filter and sanitize
    $topic_title = apply_filters('bbp_edit_topic_pre_title', $topic_title, $topic_id);

    // No topic title
    if (empty($topic_title)) {
        return false;
    }

    // Title too long
    if (bbp_is_title_too_long($topic_title)) {
        return false;
    }

    /** Topic Content *********************************************************/

    if (!empty($args['post_content'])) {
        $topic_content = $args['post_content'];
    }

    // Filter and sanitize
    $topic_content = apply_filters('bbp_edit_topic_pre_content', $topic_content, $topic_id);

    // No topic content
    if (empty($topic_content)) {
        return false;
    }

    /** Topic Bad Words *******************************************************/

    if (!bbp_check_for_moderation($anonymous_data, $topic_author, $topic_title, $topic_content, true)) {
        return false;
    }

    /** Topic Status **********************************************************/

    // Get available topic statuses
    $topic_statuses = bbp_get_topic_statuses($topic_id);

    // Use existing post_status
    $topic_status = $topic->post_status;

    // Maybe force into pending
    if (bbp_is_topic_public($topic->ID) && !bbp_check_for_moderation($anonymous_data, $topic_author, $topic_title, $topic_content)) {
        $topic_status = bbp_get_pending_status_id();

        // Check for possible posted topic status
    } elseif (!empty($_POST['bbp_topic_status']) && in_array($_POST['bbp_topic_status'], array_keys($topic_statuses), true)) {

        // Allow capable users to explicitly override the status
        if (current_user_can('moderate', $forum_id)) {
            $topic_status = sanitize_key($_POST['bbp_topic_status']);

            // Not capable
        } else {
            return false;
        }
    }

    /** Topic Tags ************************************************************/

    // Either replace terms
    if (bbp_allow_topic_tags() && current_user_can('assign_topic_tags', $topic_id) && !empty($args['topic_tags'])) {
        // Escape tag input
        $terms = $args['topic_tags'];


        // Add topic tag ID as main key
        $terms = array(bbp_get_topic_tag_tax_id() => $terms);

        // ...or remove them.
    } elseif (isset($args['topic_tags'])) {
        $terms = array(bbp_get_topic_tag_tax_id() => array());

        // Existing terms
    } else {
        $terms = array(bbp_get_topic_tag_tax_id() => explode(',', bbp_get_topic_tag_names($topic_id, ',')));
    }

    /** Additional Actions (Before Save) **************************************/

    do_action('bbp_edit_topic_pre_extras', $topic_id);

    // Bail if errors
    if (bbp_has_errors()) {
        return false;
    }

    /** No Errors *************************************************************/
    if (!empty($args["image"])) {
        $topic_content .= $args["image"];
    }
    // Add the content of the form to $topic_data as an array
    // Just in time manipulation of topic data before being edited
    $topic_data = apply_filters('bbp_edit_topic_pre_insert', array(
        'ID'           => $topic_id,
        'post_title'   => $topic_title,
        'post_content' => $topic_content,
        'post_status'  => $topic_status,
        'post_parent'  => $forum_id,
        'post_author'  => $topic_author,
        'post_type'    => bbp_get_topic_post_type(),
        'tax_input'    => $terms,
    ));

    // Toggle revisions to avoid duplicates
    if (post_type_supports(bbp_get_topic_post_type(), 'revisions')) {
        $revisions_removed = true;
        remove_post_type_support(bbp_get_topic_post_type(), 'revisions');
    }

    // Insert topic
    $topic_id = wp_update_post($topic_data);

    // Toggle revisions back on
    if (true === $revisions_removed) {
        $revisions_removed = false;
        add_post_type_support(bbp_get_topic_post_type(), 'revisions');
    }

    /** No Errors *************************************************************/

    if (!empty($topic_id) && !is_wp_error($topic_id)) {

        // Update counts, etc...
        do_action('bbp_edit_topic', $topic_id, $forum_id, $anonymous_data, $topic_author, true /* Is edit */);

        /** Revisions *********************************************************/

        // Update locks
        update_post_meta($topic_id, '_edit_last', bbp_get_current_user_id());
        delete_post_meta($topic_id, '_edit_lock');

        // Revision Reason
        // if (!empty($_POST['bbp_topic_edit_reason'])) {
        //     $topic_edit_reason = sanitize_text_field($_POST['bbp_topic_edit_reason']);
        // }

        // // Update revision log
        // if (!empty($_POST['bbp_log_topic_edit']) && ("1" === $_POST['bbp_log_topic_edit'])) {
        //     $revision_id = wp_save_post_revision($topic_id);
        //     if (!empty($revision_id)) {
        //         bbp_update_topic_revision_log(array(
        //             'topic_id'    => $topic_id,
        //             'revision_id' => $revision_id,
        //             'author_id'   => bbp_get_current_user_id(),
        //             'reason'      => $topic_edit_reason
        //         ));
        //     }
        // }

        /** Move Topic ********************************************************/

        // If the new forum id is not equal to the old forum id, run the
        // bbp_move_topic action and pass the topic's forum id as the
        // first arg and topic id as the second to update counts.
        if ($forum_id !== $topic->post_parent) {
            bbp_move_topic_handler($topic_id, $topic->post_parent, $forum_id);
        }

        /** Additional Actions (After Save) ***********************************/

        do_action('bbp_edit_topic_post_extras', $topic_id);

        /** Redirect **********************************************************/

        // Redirect to
        $redirect_to = bbp_get_redirect_to();

        // View all?
        $view_all = bbp_get_view_all('edit_others_replies');

        // Get the topic URL
        $topic_url = bbp_get_topic_permalink($topic_id, $redirect_to);

        // Add view all?
        if (!empty($view_all)) {
            $topic_url = bbp_add_view_all($topic_url);
        }

        // Allow to be filtered
        return $topic_id;

        /** Errors ****************************************************************/
    } else {
        return false;
    }
}
function sv_update_reply($args)
{

    // Define local variable(s)
    $revisions_removed = false;
    $reply = $reply_id = $reply_to = $reply_author = $topic_id = $forum_id = 0;
    $reply_title = $reply_content = $reply_edit_reason = $terms = '';
    $anonymous_data = array();

    /** Reply *****************************************************************/

    // Reply id was not passed
    if (empty($args['reply_id'])) {
        return false;

        // Reply id was passed
    } elseif (is_numeric($args['reply_id'])) {
        $reply_id = (int) $args['reply_id'];
        $reply    = bbp_get_reply($reply_id);
    }

    // Reply does not exist
    if (empty($reply)) {
        return false;

        // Reply exists
    } else {

        // Check users ability to create new reply
        if (!bbp_is_reply_anonymous($reply_id)) {

            // User cannot edit this reply
            if (!current_user_can('edit_reply', $reply_id)) {
                return false;
            }

            // Set reply author
            $reply_author = bbp_get_reply_author_id($reply_id);

            // It is an anonymous post
        } else {

            // Filter anonymous data
            $anonymous_data = bbp_filter_anonymous_post_data();
        }
    }


    /** Reply Topic ***********************************************************/

    $topic_id = bbp_get_reply_topic_id($reply_id);

    /** Topic Forum ***********************************************************/

    $forum_id = bbp_get_topic_forum_id($topic_id);

    // Forum exists
    if (!empty($forum_id) && ($forum_id !== bbp_get_reply_forum_id($reply_id))) {

        // Forum is a category
        if (bbp_is_forum_category($forum_id)) {
            return false;
            // Forum is not a category
        } else {

            // Forum is closed and user cannot access
            if (bbp_is_forum_closed($forum_id) && !current_user_can('edit_forum', $forum_id)) {
                return false;
            }

            // Forum is private and user cannot access
            if (bbp_is_forum_private($forum_id) && !current_user_can('read_forum', $forum_id)) {
                return false;

                // Forum is hidden and user cannot access
            } elseif (bbp_is_forum_hidden($forum_id) && !current_user_can('read_forum', $forum_id)) {
                return false;
            }
        }
    }

    /** Reply Title ***********************************************************/

    if (!empty($args['post_title'])) {
        $reply_title = sanitize_text_field($args['post_title']);
    }


    // Title too long
    if (bbp_is_title_too_long($reply_title)) {
        return false;
    }

    /** Reply Content *********************************************************/

    if (!empty($args['post_content'])) {
        $reply_content = $args['post_content'];
    }


    // No reply content
    if (empty($reply_content)) {
        return false;
    }

    /** Reply Bad Words *******************************************************/

    if (!bbp_check_for_moderation($anonymous_data, $reply_author, $reply_title, $reply_content, true)) {
        return false;
    }

    /** Reply Status **********************************************************/

    // Use existing post_status
    $reply_status = $reply->post_status;

    // Maybe force into pending
    if (bbp_is_reply_public($reply_id) && !bbp_check_for_moderation($anonymous_data, $reply_author, $reply_title, $reply_content)) {
        $reply_status = bbp_get_pending_status_id();
    }

    /** Reply To **************************************************************/

    // Handle Reply To of the reply; $_REQUEST for non-JS submissions
    if (isset($args['reply_to']) && current_user_can('moderate', $reply_id)) {
        $reply_to = bbp_validate_reply_to($args['reply_to'], $reply_id);
    } elseif (bbp_thread_replies()) {
        $reply_to = bbp_get_reply_to($reply_id);
    }

    /** Topic Tags ************************************************************/

    // Either replace terms
    if (bbp_allow_topic_tags() && current_user_can('assign_topic_tags', $topic_id) && !empty($args['topic_tags'])) {
        $terms = $args['topic_tags'];

        // ...or remove them.
    } elseif (isset($args['topic_tags'])) {
        $terms = '';

        // Existing terms
    } else {
        $terms = bbp_get_topic_tag_names($topic_id);
    }

    /** Additional Actions (Before Save) **************************************/

    do_action('bbp_edit_reply_pre_extras', $reply_id);

    // Bail if errors
    if (bbp_has_errors()) {
        return;
    }

    /** No Errors *************************************************************/
    if (!empty($args["image"])) {
        $reply_content .= $args["image"];
    }
    // Add the content of the form to $reply_data as an array
    // Just in time manipulation of reply data before being edited
    $reply_data = apply_filters('bbp_edit_reply_pre_insert', array(
        'ID'           => $reply_id,
        'post_title'   => $reply_title,
        'post_content' => $reply_content,
        'post_status'  => $reply_status,
        'post_parent'  => $topic_id,
        'post_author'  => $reply_author,
        'post_type'    => bbp_get_reply_post_type()
    ));

    // Toggle revisions to avoid duplicates
    if (post_type_supports(bbp_get_reply_post_type(), 'revisions')) {
        $revisions_removed = true;
        remove_post_type_support(bbp_get_reply_post_type(), 'revisions');
    }

    // Insert reply
    $reply_id = wp_update_post($reply_data);

    // Toggle revisions back on
    if (true === $revisions_removed) {
        $revisions_removed = false;
        add_post_type_support(bbp_get_reply_post_type(), 'revisions');
    }

    /** Topic Tags ************************************************************/

    // Just in time manipulation of reply terms before being edited
    $terms = apply_filters('bbp_edit_reply_pre_set_terms', $terms, $topic_id, $reply_id);

    // Insert terms
    $terms = wp_set_post_terms($topic_id, $terms, bbp_get_topic_tag_tax_id(), false);

    // Term error
    if (is_wp_error($terms)) {
        return false;
    }

    /** No Errors *************************************************************/

    if (!empty($reply_id) && !is_wp_error($reply_id)) {

        // Update counts, etc...
        do_action('bbp_edit_reply', $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author, true, $reply_to);

        /** Revisions *********************************************************/

        // Update locks
        update_post_meta($reply_id, '_edit_last', bbp_get_current_user_id());
        delete_post_meta($reply_id, '_edit_lock');

        // Revision Reason
        // if (!empty($_POST['bbp_reply_edit_reason'])) {
        //     $reply_edit_reason = sanitize_text_field($_POST['bbp_reply_edit_reason']);
        // }

        // Update revision log
        // if (!empty($_POST['bbp_log_reply_edit']) && ("1" === $_POST['bbp_log_reply_edit'])) {
        //     $revision_id = wp_save_post_revision($reply_id);
        //     if (!empty($revision_id)) {
        //         bbp_update_reply_revision_log(array(
        //             'reply_id'    => $reply_id,
        //             'revision_id' => $revision_id,
        //             'author_id'   => bbp_get_current_user_id(),
        //             'reason'      => $reply_edit_reason
        //         ));
        //     }
        // }

        /** Additional Actions (After Save) ***********************************/

        do_action('bbp_edit_reply_post_extras', $reply_id);

        /** Redirect **********************************************************/

        // Redirect to
        return $reply_id;

        /** Errors ****************************************************************/
    } else {
        return false;
    }
}
function sv_get_replies($parameters, $current_user_id)
{
    if (empty($parameters)) return [];

    $page = !empty($parameters['page']) ? $parameters['page'] : 1;
    $posts_per_page = !empty($parameters['posts_per_page']) ? $parameters['posts_per_page'] : 20;

    $args = [
        "paged"             => $page,
        "posts_per_page"    => $posts_per_page
    ];

    if ($parameters["is_user_replies"])
        $args['author'] = $current_user_id;
    else
        $args['post_parent'] = $parameters["topic_id"];


    $replies = [];
    if (bbp_has_replies($args)) :
        while (bbp_replies()) : bbp_the_reply();

            $reply_id = bbp_get_reply_id();

            $author_id = bbp_get_reply_author_id();
            $author_name = get_user_meta($author_id, "nickname", true);

            if (empty($author_name)) {
                $author_name = get_the_author_meta('user_login', $author_id);
            }

            $content = get_post_field('post_content', $reply_id);
            $replies[] = [
                "id"                => $reply_id,
                "title"             => bbp_get_reply_title(),
                "content"           => $content,
                "created_by_id"     => $author_id,
                "created_by_name"   => empty($author_name)?sv_default_display_name():$author_name,
                "profile_image"     => get_avatar_url($author_id, ["size" => 150]),
                "is_user_verified"  => sv_is_user_verified($author_id),
                "topic_id"          => bbp_get_reply_topic_id(),
                "topic_name"        => bbp_get_reply_topic_title(),
                "created_at_date"   => bbp_get_reply_post_date(),
                "key"               => bbp_get_user_display_role($author_id)
            ];
        endwhile;
    endif;

    return $replies;
}

add_action("bp_rest_groups_create_item", "sv_create_group_forum");
function sv_create_group_forum($group)
{

    if ($group->enable_forum) {
        $forum_id = bbp_insert_forum(array(
            'post_parent'  => bbp_get_group_forums_root_id(),
            'post_title'   => $group->name,
            'post_content' => $group->description,
            'post_status'  => $group->status
        ));


        // // Validate forum_id
        if ($forum_id) {
            $group_id = $group->id;

            bbp_add_forum_id_to_group($group_id, $forum_id);
            bbp_add_group_id_to_forum($forum_id, $group_id);

            groups_update_groupmeta($group_id, '_bbp_forum_enabled_' . $forum_id, true);
        }
    }
}

function get_forums_by_topic($parameters, $forum_ids, $current_user_id)
{
    $page = !empty($parameters['page']) ? $parameters['page'] : 1;
    // $posts_per_page = !empty($parameters['posts_per_page']) ? $parameters['posts_per_page'] : 20;

    $args = [
        "paged"             => $page,
        "posts_per_page"    => -1,
        "s"                 => trim($parameters['keyword']),
    ];
    if (!empty($forum_ids)) {
        $args["meta_query"] =   [
            [
                "key"       => "_bbp_forum_id",
                "value"     => $forum_ids,
                "compare"   => "NOT IN"
            ]
        ];
    }

    $forums = [];

    if (bbp_has_topics($args)) :
        while (bbp_topics()) : bbp_the_topic();

            $forum_id = bbp_get_topic_forum_id();
            $forum = bbp_get_forum($forum_id);

            if (!$forum) continue;

            $forum_id = $forum->ID;
            $is_private = bbp_get_forum_visibility($forum_id) === "private" ? 1 : 0;
            $forums[] = [
                "id"            => $forum_id,
                "title"         => $forum->post_title,
                "description"   => $forum->post_content,
                "type"          => $forum->post_type,
                "topic_count"   => bbp_get_forum_topic_count($forum_id),
                "post_count"    => bbp_get_forum_post_count($forum_id),
                "is_private"    => $is_private,
                "freshness"     => sv_get_forum_freshness($forum_id),
                "group_details" => sv_get_forum_group_details(bbp_get_forum_group_ids(), $current_user_id)
            ];
        endwhile;
    endif;
    return $forums;
}


function sv_has_topics($args = array())
{

    /** Defaults **************************************************************/

    // Other defaults
    $default_topic_search  = bbp_sanitize_search_request('ts');
    $default_show_stickies = (bool) (bbp_is_single_forum() || bbp_is_topic_archive()) && (false === $default_topic_search);
    $default_post_parent   = bbp_is_single_forum() ? bbp_get_forum_id() : 'any';

    // Default argument array
    $default = array(
        'post_type'      => bbp_get_topic_post_type(), // Narrow query down to bbPress topics
        'post_parent'    => $default_post_parent,      // Forum ID
        'meta_key'       => '_bbp_last_active_time',   // Make sure topic has some last activity time
        'meta_type'      => 'DATETIME',
        'orderby'        => 'meta_value',              // 'meta_value', 'author', 'date', 'title', 'modified', 'parent', rand',
        'order'          => 'DESC',                    // 'ASC', 'DESC'
        'posts_per_page' => bbp_get_topics_per_page(), // Topics per page
        'paged'          => bbp_get_paged(),           // Page Number
        'show_stickies'  => $default_show_stickies,    // Ignore sticky topics?
        'max_num_pages'  => false,                     // Maximum number of pages to show

        // Conditionally prime the cache for related posts
        'update_post_family_cache' => true
    );

    // Only add 's' arg if searching for topics
    // See https://bbpress.trac.wordpress.org/ticket/2607
    if (!empty($default_topic_search)) {
        $default['s'] = $default_topic_search;
    }

    // What are the default allowed statuses (based on user caps)
    if (bbp_get_view_all('edit_others_topics')) {

        // Default view=all statuses
        $post_statuses = array_keys(bbp_get_topic_statuses());

        // Add support for private status
        if (current_user_can('read_private_topics')) {
            $post_statuses[] = bbp_get_private_status_id();
        }

        // Join post statuses together
        $default['post_status'] = $post_statuses;

        // Lean on the 'perm' query var value of 'readable' to provide statuses
    } else {
        $default['perm'] = 'readable';
    }

    // Maybe query for topic tags
    if (bbp_is_topic_tag()) {
        $default['term']     = bbp_get_topic_tag_slug();
        $default['taxonomy'] = bbp_get_topic_tag_tax_id();
    }

    /** Setup *****************************************************************/

    // Parse arguments against default values
    $r = bbp_parse_args($args, $default, 'has_topics');

    // Get bbPress
    // $bbp = bbpress();

    // Call the query
    $topic_query = new WP_Query($r);

    // Maybe prime last active posts
    // if ( ! empty( $r['update_post_family_cache'] ) ) {
    // 	bbp_update_post_family_caches( $topic_query->posts );
    // }

    // Set post_parent back to 0 if originally set to 'any'
    if ('any' === $r['post_parent']) {
        $r['post_parent'] = 0;
    }

    // Limited the number of pages shown
    // if ( ! empty( $r['max_num_pages'] ) ) {
    // 	$topic_query->max_num_pages = (int) $r['max_num_pages'];
    // }

    /** Stickies **************************************************************/

    // Put sticky posts at the top of the posts array
    if (!empty($r['show_stickies']) && ($r['paged'] <= 1)) {
        bbp_add_sticky_topics($topic_query, $r);
    }

    // If no limit to posts per page, set it to the current post_count
    if (-1 === $r['posts_per_page']) {
        $r['posts_per_page'] = $topic_query->post_count;
    }

    // Add pagination values to query object
    $posts_per_page = (int) $r['posts_per_page'];
    $paged          = (int) $r['paged'];

    // Only add pagination if query returned results
    if ((!empty($topic_query->post_count) || !empty($topic_query->found_posts)) && !empty($posts_per_page)) {

        // Limit the number of topics shown based on maximum allowed pages
        if ((!empty($r['max_num_pages'])) && ($topic_query->found_posts > ($topic_query->max_num_pages * $topic_query->post_count))) {
            $topic_query->found_posts = $topic_query->max_num_pages * $topic_query->post_count;
        }

        // Total topics for pagination boundaries
        $total_pages = ($posts_per_page === $topic_query->found_posts)
            ? 1
            : ceil($topic_query->found_posts / $posts_per_page);

        // Maybe add view-all args
        $add_args = bbp_get_view_all()
            ? array('view' => 'all')
            : false;

        // Pagination settings with filter
        $bbp_topic_pagination = apply_filters('bbp_topic_pagination', array(
            'base'      => bbp_get_topics_pagination_base($r['post_parent']),
            'format'    => '',
            'total'     => $total_pages,
            'current'   => $paged,
            'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
            'next_text' => is_rtl() ? '&larr;' : '&rarr;',
            'mid_size'  => 1,
            'add_args'  => $add_args,
        ));

        // Add pagination to query object
        $topic_query->pagination_links = bbp_paginate_links($bbp_topic_pagination);
    }

    // Filter & return
    return apply_filters('bbp_has_topics', $topic_query, $topic_query);
}


add_filter('bp_notifications_get_where_conditions', 'sv_notifications_get_where_condition_for_blocked', 10, 2);
function sv_notifications_get_where_condition_for_blocked($where, $args)
{
    $bp = buddypress();
    $table_name = isset($args['table_name']) ? $args['table_name'] : $bp->notifications->table_name;

    $where['sv_same_user'] = "id NOT IN (select id from $table_name WHERE user_id=secondary_item_id AND component_name IN('forums'))";

    return $where;
}
