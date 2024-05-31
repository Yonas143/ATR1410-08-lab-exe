<?php

namespace Includes\baseClasses;


class SVActivityComments
{

	function socialv_activity_get_comments($activities_template, $is_all = false, $args = [])
	{

		global $wpdb;

		$comments = [];

		$activity_id = $activities_template->activity->id;
		$args = wp_parse_args($args, [
			"per_page" 	=> 3,
			"page" 		=> 1,
			"order_by" 	=> "DESC"
		]);

		$spam = 'ham_only';
		$limit = "";
		$top_level_parent_id = 'activity_comment' == $activities_template->activity->type ? $activities_template->activity->item_id : 0;
		$left = $activities_template->activity->mptt_left;
		$right = $activities_template->activity->mptt_right;

		$per_page = $args["per_page"];
		$page = $args["page"];
		$order_by = $args["order_by"];
		$current_user_id = isset($args["current_user_id"]) ? $args["current_user_id"] : get_current_user_id();
		if (!$is_all)
			$limit = "LIMIT " . $args["per_page"] * ($page - 1) . "," . $per_page;



		if (empty($top_level_parent_id))
			$top_level_parent_id = $activity_id;


		$bp = buddypress();


		// Don't retrieve activity comments marked as spam.
		if ('ham_only' == $spam) {
			$spam_sql = 'AND a.is_spam = 0';
		} elseif ('spam_only' == $spam) {
			$spam_sql = 'AND a.is_spam = 1';
		} else {
			$spam_sql = '';
		}


		$sql = $wpdb->prepare("SELECT id,user_id,content,item_id,secondary_item_id,date_recorded FROM {$bp->activity->table_name} a WHERE a.type = 'activity_comment' {$spam_sql} AND a.item_id = %d and a.mptt_left > %d AND a.mptt_left < %d ORDER BY a.date_recorded {$order_by} {$limit}", $top_level_parent_id, $left, $right);
		$get_comments = $wpdb->get_results($sql, ARRAY_A);

		$new_comments = [];
		$list_with_secondary_id = [];
		foreach ($get_comments as $comment) {
			$comment_id = $comment["id"];
			$meta = bp_activity_get_meta($comment_id, "_bp_activity_gif_data", true);

			// if mention
			$usernames          = bp_activity_find_mentions($comment["content"]);
			if (!empty($usernames)) {
				// Replace @mention text with userlinks.
				foreach ((array) $usernames as $user_id => $username) {
					$link = add_query_arg("user_id", $user_id, bp_members_get_user_url($user_id));
					$comment["content"] = str_replace(bp_members_get_user_url($user_id), $link, $comment["content"]);
				}
				$comment["has_mentions"] = 1;
			} else {
				$comment["has_mentions"] = 0;
			}

			if ($meta) {
				$media_list_with_ids = [];
				$gif = $meta["bp_activity_gif"];
				$media_list_with_ids[] = [
					"id"    => (int) $comment_id,
					"url"   => $gif,
					"type"  => "gif"
				];
				$comment['medias'] = $media_list_with_ids;
				$content = wp_kses($comment["content"], 'no-images');

				$comment["content"] = $content;
			} else {
				$comment['media_list'] = [];
			}
			$comment["content"] = apply_filters('bp_get_activity_content', $comment["content"]);
			$comment["user_name"] = bp_core_get_user_displayname($comment['user_id']);
			$comment["user_image"] = bp_core_fetch_avatar(
				array(
					'item_id' => $comment['user_id'],
					'no_grav' => true,
					'type'    => 'full',
					'html'    => FALSE     // FALSE = return url, TRUE (default) = return img html
				)
			);

			$comment["user_email"] = bp_core_get_user_email($comment['user_id']);
			if (is_reaction_active()) {
				$comment['reaction_count'] = rest_get_reaction_count("iq_comment_reaction", "WHERE comment_id={$comment_id}");
				$comment["cur_user_reaction"] = rest_user_reaction($comment_id, $current_user_id, "comment");
				$comment["reactions"] = sv_rest_reaction_list($comment_id, "comment");
			}
			$comment["is_user_verified"] = sv_is_user_verified($comment['user_id']);


			if ($comment['item_id'] == $comment['secondary_item_id'])
				$new_comments[] = $comment;

			$list_with_secondary_id[$comment['secondary_item_id']][] = $comment;
		}

		if ($is_all && !empty($new_comments))
			$new_comments = array_slice($new_comments, $per_page * ($page - 1), $per_page);

		foreach ($new_comments as $comment) {
			$comments_hierarchy[] = self::createTree($list_with_secondary_id, array($comment));
		}

		if (!empty($comments_hierarchy) && $comments_hierarchy )
			$comments = call_user_func_array('array_merge', $comments_hierarchy);

		return $comments;
	}

	function createTree(&$list_with_secondary_id, $parent)
	{
		$comments_hierarchy = array();
		foreach ($parent as $key => $value) {
			if (isset($list_with_secondary_id[$value['id']])) {
				$value['children'] = self::createTree($list_with_secondary_id, $list_with_secondary_id[$value['id']]);
			}
			$comments_hierarchy[] = $value;
		}
		return $comments_hierarchy;
	}
}
