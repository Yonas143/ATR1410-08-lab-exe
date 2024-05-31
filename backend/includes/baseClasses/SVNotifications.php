<?php

namespace Includes\baseClasses;

use BP_Notifications_Notification;


class SVNotifications extends BP_Notifications_Notification
{
	public function socialv_notification_settings()
	{
		$settings =  [
			"new_at_mention" => [
				"key" => "notification_activity_new_mention",
				"name" => esc_html__("Mentions Notifications", "socialv-api")
			],
			"membership_request_accepted"   => [
				"key"  => "notification_membership_request_completed",
				"name" => esc_html__("Join Group Notifications", "socialv-api")
			],
			"membership_request_rejected"   => [
				"key" => "notification_groups_group_updated",
				"name" => esc_html__("Group information Notifications", "socialv-api")
			],
			"new_membership_request"        => [
				"key" => "notification_groups_membership_request",
				"name" => esc_html__("Group Membership Request Notifications", "socialv-api")
			],
			"group_invite"                  => [
				"key" => "notification_groups_invite",
				"name" => esc_html__("Group Invitations Notifications", "socialv-api")
			],
			"member_promoted_to_admin"      => [
				"key" => "notification_groups_admin_promotion",
				"name" => esc_html__("Group Admin Promotion Notifications", "socialv-api")
			],
			"action_activity_liked"         => [
				"key" => "notification_activity_new_like",
				"name" => is_reaction_active() ? esc_html__("Reaction Notifications", "socialv-api") : esc_html__("Like Notifications", "socialv-api")
			],
			"socialv_share_post"         => [
				"key" => "notification_share_activity_post",
				"name" => esc_html__("Share on activity Notifications", "socialv-api")
			],
			"update_reply"			 => [
				"key" => "notification_activity_new_comment",
				"name" => esc_html__("Comment on activity Notifications", "socialv-api")
			],
			"comments"                      => [
				"key" => "notification_activity_new_reply",
				"name" => esc_html__("Replies Notifications", "socialv-api")
			],
			"friendship_request"            => [
				"key" => "notification_friends_friendship_request",
				"name" => esc_html__("Friendship Requested Notifications", "socialv-api")
			],
			"friendship_accepted"           => [
				"key" => "notification_friends_friendship_accepted",
				"name" => esc_html__("Friendship Accepted Notifications", "socialv-api")
			],
			"new_at_mention" => [
				"key" => "notification_activity_new_mention",
				"name" => esc_html__("Mentions Notifications", "socialv-api")
			],
			"forums" => [
				"key" => "notification_forums",
				"name" => esc_html__("Forum Notifications", "socialv-api")
			]
		];
		return $settings;
	}
	public function socialv_get_notifications($args = [])
	{

		$r = bp_parse_args(
			$args,
			array(
				'id'                => false,
				'user_id'           => 0,
				'item_id'           => false,
				'secondary_item_id' => false,
				'component_name'    => bp_notifications_get_registered_components(),
				'component_action'  => false,
				'search_terms'      => '',
				'order_by'          => 'date_notified',
				'sort_order'        => 'DESC',
				'page_arg'          => 'npage',
				'page'              => 1,
				'per_page'          => 25,
				'max'               => null,
				'meta_query'        => false,
				'date_query'        => false,
			)
		);

		// Sort order direction.
		if (!empty($_GET['sort_order'])) {
			$r['sort_order'] = $_GET['sort_order'];
		}

		// Setup variables.
		$pag_arg      = sanitize_key($r['page_arg']);
		$pag_page     = bp_sanitize_pagination_arg($pag_arg, $r['page']);
		$pag_num      = bp_sanitize_pagination_arg('num', $r['per_page']);
		$sort_order   = bp_esc_sql_order($r['sort_order']);
		$user_id      = $r['user_id'];
		// $is_new       = $r['is_new'];
		$search_terms = $r['search_terms'];
		$order_by     = $r['order_by'];
		$query_vars   = array(
			'id'                => $r['id'],
			'user_id'           => $user_id,
			'item_id'           => $r['item_id'],
			'secondary_item_id' => $r['secondary_item_id'],
			'component_name'    => $r['component_name'],
			'component_action'  => $r['component_action'],
			'meta_query'        => $r['meta_query'],
			'date_query'        => $r['date_query'],
			'search_terms'      => $search_terms,
			'order_by'          => $order_by,
			'sort_order'        => $sort_order,
			'page'              => $pag_page,
			'per_page'          => $pag_num,
		);

		// Setup the notifications to loop through.
		$notifications            =   self::socialv_result_notifications($query_vars);

		if ($notifications)
			return $notifications;

		return $notifications = [];
	}
	public function socialv_result_notifications($args = array())
	{
		global $wpdb;

		// Parse the arguments.
		$r = bp_parse_args(
			$args,
			array(
				'id'                => false,
				'user_id'           => false,
				'item_id'           => false,
				'secondary_item_id' => false,
				'component_name'    => bp_notifications_get_registered_components(),
				'component_action'  => false,
				'search_terms'      => '',
				'order_by'          => false,
				'sort_order'        => false,
				'page'              => false,
				'per_page'          => false,
				'meta_query'        => false,
				'date_query'        => false,
				'update_meta_cache' => true,
			)
		);

		// Get BuddyPress.
		$bp = buddypress();

		// METADATA.
		$meta_query_sql = self::socialv_get_notifications_meta_query_sql($r['meta_query']);

		// SELECT.
		$select_sql = "SELECT *";

		// FROM.
		$from_sql   = "FROM {$bp->notifications->table_name} n ";

		// JOIN.
		$join_sql   = $meta_query_sql['join'];

		// WHERE.
		$where_sql  = self::socialv_get_notifications_where_sql(array(
			'id'                => $r['id'],
			'user_id'           => $r['user_id'],
			'item_id'           => $r['item_id'],
			'secondary_item_id' => $r['secondary_item_id'],
			'component_name'    => $r['component_name'],
			'component_action'  => $r['component_action'],
			'search_terms'      => $r['search_terms'],
			'date_query'        => $r['date_query'],
			'table_name'        => $bp->notifications->table_name
		), $select_sql, $from_sql, $join_sql, $meta_query_sql);

		// ORDER BY.
		$order_sql  = self::socialv_get_notifications_order_by_sql(array(
			'order_by'   => $r['order_by'],
			'sort_order' => $r['sort_order']
		));

		// LIMIT %d, %d.
		$pag_sql    = self::socialv_get_notifications_get_paged_sql(array(
			'page'     => $r['page'],
			'per_page' => $r['per_page']
		));

		// Concatenate query parts.
		$sql = "{$select_sql} {$from_sql} {$join_sql} {$where_sql} {$order_sql} {$pag_sql}";

		$results = $wpdb->get_results($sql);

		// Update meta cache.
		if (true === $r['update_meta_cache']) {
			bp_notifications_update_meta_cache(wp_list_pluck($results, 'id'));
		}

		return $results;
	}
	public static function socialv_get_notifications_meta_query_sql($meta_query = array())
	{
		return parent::get_meta_query_sql($meta_query);
	}
	public static function socialv_get_notifications_where_sql($args = array(), $select_sql = '', $from_sql = '', $join_sql = '', $meta_query_sql = '')
	{
		return parent::get_where_sql($args, $select_sql, $from_sql, $join_sql, $meta_query_sql);
	}
	public static function socialv_get_notifications_order_by_sql($args = array())
	{
		return parent::get_order_by_sql($args);
	}
	public static function socialv_get_notifications_get_paged_sql($args = array())
	{
		return parent::get_paged_sql($args);
	}
	public function socialv_skip_notification($notification, $current_user_id)
	{
		$skipable_actions = [
			"new_membership_request",
			"group_invite",
			"friendship_request"
		];

		if (!$notification || empty($current_user_id) || !in_array($notification->component_action, $skipable_actions)) return false;

		$item_id = $notification->item_id;
		$action = $notification->component_action;
		$secondary_item_id = $notification->secondary_item_id;

		if ("friendship_request" == $action) {
			$return = friends_check_friendship($current_user_id, $item_id);
		} else if ("group_invite" == $action) {
			$return = groups_is_user_member($current_user_id, $item_id);
		} else if ("new_membership_request" == $action) {
			$return = groups_is_user_member($secondary_item_id, $item_id);
			if (!$return)
				$return = groups_check_for_membership_request($secondary_item_id, $item_id) ? false : true;
		}

		if ($return) {
			self::socialv_delete_notification($current_user_id, $notification->id);
			return true;
		}

		return false;
	}
	public function socialv_delete_notification($user_id, $notification_id)
	{
		if (!bp_notifications_check_notification_access($user_id, $notification_id)) {
			return false;
		}

		return BP_Notifications_Notification::delete(array('id' => $notification_id));
	}

	public function socialv_mark_notification($user_id, $notification_id, $is_new = false)
	{
		if (!bp_notifications_check_notification_access($user_id, $notification_id)) {
			return false;
		}

		return BP_Notifications_Notification::update(
			array('is_new' => $is_new),
			array('id'     => $notification_id)
		);
	}

	public static function socialv_notifications_get_unread_notification_count($user_id = 0)
	{
		if (empty($user_id)) {
			$user_id = (bp_displayed_user_id()) ? bp_displayed_user_id() : bp_loggedin_user_id();
		}
		$allowed_registered_components = array_filter(bp_notifications_get_registered_components(), static function ($element) {
			return $element !== "messages" && (is_reaction_active() && $element !== "socialv_activity_like_notification");
		});
		$count = wp_cache_get($user_id, 'socialv_notifications_unread_count');
		if (false === $count) {
			$count = BP_Notifications_Notification::get_total_count(array(
				'user_id' 			=> $user_id,
				'is_new'  			=> true,
				'component_name'	=> $allowed_registered_components
			));
			wp_cache_set($user_id, $count, 'socialv_notifications_unread_count');
		}

		/**
		 * Filters the count of unread notification items for a user.
		 *
		 * @since 1.9.0
		 * @since 2.7.0 Added user ID parameter.
		 *
		 * @param int $count   Count of unread notification items for a user.
		 * @param int $user_id User ID for notifications count.
		 */
		return apply_filters('bp_notifications_get_total_notification_count', (int) $count, $user_id);
	}
}
