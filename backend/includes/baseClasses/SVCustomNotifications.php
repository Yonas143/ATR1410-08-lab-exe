<?php

namespace Includes\baseClasses;

use BP_Activity_Activity;
use BP_Notifications_Notification;
use Includes\Settings\SVSettings;

class SVCustomNotifications
{
	public $notification_data;
	public $notification_type;

	protected static $firebase_config = false;

	public function __construct()
	{
		if (!is_dependent_theme_active()) {
			add_filter('bp_notifications_get_registered_components', [$this, 'add_custom_notification_component'], 99);
			add_filter('bp_notifications_get_notifications_for_user', [$this, 'custom_format_buddypress_notifications'], 99, 5);
			add_action('socialv_activity_shared', [$this, 'add_share_activity_user_notification'], 10, 3);
		}
		add_action("bp_rest_friends_delete_item", [$this, "socialv_remove_notification"], 10, 2);
		add_action("bp_notification_after_save", [$this, "socialv_send_notification"]);
		// better messsages push notificaton: New message, save reaction better_messages_message_meta_updated
		add_action("better_messages_message_sent", [$this, "sv_bm_new_message"]);
		add_action("better_messages_message_meta_updated", [$this, "sv_bm_reaction"], 10, 4);

		// add_action("wp_head",function(){
		// 	$message = Better_Messages()->functions->get_participants(298);
		// 	print_r($message);die;
		// });
	}

	function add_custom_notification_component($component_names = array())
	{
		// Force $component_names to be an array.
		if (!is_array($component_names))
			$component_names = array();

		// Add 'custom' component to registered components array.
		array_push($component_names, 'socialv_activity_like_notification', 'socialv_share_post_notification');

		// Return component's with 'custom' appended.
		return $component_names;
	}

	function custom_format_buddypress_notifications($action, $item_id, $secondary_item_id, $total_items, $format = 'string')
	{

		if (!in_array($action, ["action_activity_liked", "socialv_share_post", "sv_new_topic", "sv_new_topic_reply"]))
			return $action;
		if (!bp_is_active('activity') && $action != "sv_new_topic")
			return $action;

		$user_name  	= bp_core_get_user_displayname($secondary_item_id);
		if ($total_items > 1) {
			$user_name .= sprintf(esc_html__(' And %d more users', SOCIALV_API_TEXT_DOMAIN), $total_items);
		}

		$args = [
			"action_activity_liked" => [
				"text" 		=> $user_name . esc_html__(' liked your post', SOCIALV_API_TEXT_DOMAIN),
				"filter" 	=> 'socialv_like_notification_string',
				"link" 		=> esc_url(bp_get_activity_directory_permalink() . "p/" . $item_id)
			],
			"socialv_share_post" => [
				"text" 		=> $user_name . esc_html__(' shared your post', SOCIALV_API_TEXT_DOMAIN),
				"filter" 	=> 'socialv_share_post_string',
				"link" 		=> esc_url(bp_get_activity_directory_permalink() . "p/" . $item_id)
			],
			"sv_new_topic" => [
				"text" 		=> sprintf(__('%s created new topic to %s', SOCIALV_API_TEXT_DOMAIN), $user_name, bbp_get_forum_title(bbp_get_topic_forum_id($item_id))),
				"filter" 	=> 'socialv_new_topic_string',
				"link"		=> get_permalink($item_id)
			],
			"sv_new_topic_reply" => [
				"text"      => sprintf(__('%s replied to %s', SOCIALV_API_TEXT_DOMAIN), $user_name, bbp_get_topic_title(bbp_get_reply_topic_id($item_id))),
				"filter" 	=> 'socialv_new_topic_string',
				"link"		=> get_permalink(bbp_get_reply_topic_id($item_id)) . "#post-" . $item_id
			]
		];

		$link	= $args[$action]["link"];
		$text	= $args[$action]["text"];
		$text 	= "<a href='$link'>" . $text . "</a>";
		// WordPress Toolbar.

		if ('string' === $format) {
			return apply_filters($args[$action]["filter"], '' . $text . '', $text, $link);
		} else {
			return apply_filters($args[$action]["filter"], array(
				'text' => $text,
				'link' => $link
			), $link, (int) $total_items, $text);
		}

		return $action;
	}

	function add_share_activity_user_notification($activity_id, $shared_activity_id, $user_id)
	{
		// notify-user
		if (bp_is_active('notifications')) {
			$args['status'] 					= true;
			$args["component_name"] 			= "socialv_share_post_notification";
			$args["component_action"] 			= "socialv_share_post";
			$args['enable_notification_key'] 	= "notification_share_activity_post";
			$shared_activity = bp_activity_get(['in' => $shared_activity_id]);
			if (isset($shared_activity['activities'][0])) {
				$args["activity_user_id"] 		= $shared_activity['activities'][0]->user_id;
				self::sv_add_user_notification($activity_id, $args, $user_id);
			}
		}
	}

	public static function sv_add_user_notification($item_id, $args = [], $user_id = "")
	{
		$activity 	= new BP_Activity_Activity($item_id);
		$user_id 	= !empty($user_id) ? $user_id : get_current_user_id();

		if ($activity) {
			$user_to_notify = isset($args['activity_user_id']) ? $args['activity_user_id'] : $activity->user_id;
			$notify_user 	= get_user_meta($user_to_notify, $args['enable_notification_key'], true);
			if ($notify_user == "no")
				return;
		}

		if (isset($args["user_to_notify"]))
			$user_to_notify = $args["user_to_notify"];

		if ($user_to_notify == $user_id)
			return;


		$notification_args = [
			'user_id'           => $user_to_notify,
			'item_id'           => $item_id,
			'secondary_item_id' => $user_id,
			'component_name'    => $args["component_name"],
			'component_action'  => $args["component_action"],
			'is_new'            => 1
		];

		$existing = BP_Notifications_Notification::get($notification_args);

		if (!empty($existing) && !$args['status']) {
			return BP_Notifications_Notification::delete(array('id' => $existing[0]->id));
		} else {
			return bp_notifications_add_notification(array_merge($notification_args, ['date_notified' => bp_core_current_time()]));
		}
	}

	function socialv_send_notification($obj, $skip = false)
	{
		
		if ($skip)
			$fields_data = $obj;
		else
			$fields_data = self::get_notification_data($obj);

		if (!$fields_data) return;

		$player_ids = $fields_data["player_ids"];
		$content 	= $fields_data["content"];
		
		$heading 	= $fields_data["heading"];
		$data 		= $fields_data["data"];
		$fields = [
				'data' 					=> $data,
			'content' 				=> $content,
			"include_player_ids"	=> $player_ids,
			'headings' 				=> $heading
		];

		if (!empty($fields_data['button']))
			$fields['button'] = $fields_data["button"];

		if (isset($fields_data["large_icon"]))
			$fields['large_icon'] = $fields_data["large_icon"];

		if (isset($url))
			$fields['url'] = $url;


		$sendContent = json_encode($fields, JSON_UNESCAPED_UNICODE);
		if (!function_exists('curl_init')) {
			die('cURL library is not enabled in PHP');
		}

		//fire base notification
		$this->send_notification_on_firebase($fields);
		return;

	}


	public function socialv_remove_notification($friendship, $response)
	{
		$response = $response->get_data();
		if ($response['deleted']) {
			BP_Notifications_Notification::delete(array('secondary_item_id' => $friendship->id));
		}
	}
	public function get_notification_data($obj)
	{
		$data = $button = [];

		$user_id 				= $obj->user_id;
		$action 				= $obj->component_action;
		$component 				= $obj->component_name;
		$notification_settings 	= new SVNotifications();
		$item_id 				= $obj->item_id;
		$notification_id 		= $obj->id;
		$secondary_item_id 		= $obj->secondary_item_id;
		$is_forum 				= ($component == "forums");
		$topic_id 				= 0;
		if ($is_forum) {
			$is_notification_enable = get_user_meta($user_id, "notification_forums", true);
			if ($action == "sv_new_topic") {
				$topic_id				= $item_id;
			} else if ($action == "sv_new_topic_reply") {
				$data["reply_id"]		= $item_id;
				$topic_id				= (int) bbp_get_reply_topic_id($item_id);
			} else {
				$new_action  			= explode("_", $action);
				$topic_id 				= (int) end($new_action);
				$data["reply_id"]		= $item_id;
			}
			$data["topic_id"]		= $topic_id;
			$data["forum_id"]		= (int) bbp_get_topic_forum_id($topic_id);
			$data["user_id"]		= $secondary_item_id;
		} else {
			$get_notification_settings 	= $notification_settings->socialv_notification_settings();
			$setting_values 			= $get_notification_settings[$action];
			$is_notification_enable 	= get_user_meta($user_id, $setting_values['key'], true);
		}

		if ("no" == $is_notification_enable) return false;

		$secondary 	= [
			"new_membership_request",
			"action_activity_liked",
			"socialv_share_post",
			"action_activity_reacted",
			"action_comment_activity_reacted",
			"comment_reply",
			"update_reply",
			"new_at_mention",
			"bbp_new_reply_" . $topic_id,
			"sv_new_topic",
			"sv_new_topic_reply"
		];
		$groups 	= [
			"membership_request_accepted",
			"membership_request_rejected",
			"group_invite",
			"member_promoted_to_admin"
		];
		$request 	= ["friendship_request", "friendship_accepted"];

		$user_name 	= "";
		$reaction 	= "";
		if (in_array($action, $secondary) || $is_forum) {
			if ($action == "action_activity_reacted") {
				$reaction_obj 		= get_reaction_db_obj();
				if ($reaction_obj) {
					$userReaction 	= get_reaction_db_obj()->getUserReaction($item_id, $secondary_item_id);
					$reaction 		= isset($userReaction[0]) ? $userReaction[0]->image_url : "";
				}
			} else if ("new_membership_request" == $action) {
				$data["group_id"] = $item_id;
				$button = [
					["id" => "reject_member_request", "text" => "Reject"],
					["id" => "accept_member_request", "text" => "Accept"]
				];
			} else if (in_array($action, ["action_comment_activity_reacted", "comment_reply", "update_reply"])) {
				$data["is_comment"] = 1;
				$comment 			= bp_activity_get(["in" => $item_id, "display_comments" => 1]);

				if ($comment) {
					$comment 			= $comment["activities"];
					$data["post_id"] 	= $comment[0]->item_id;

					$reaction_obj 		= get_reaction_db_obj();
					if ($reaction_obj) {
						$userReaction 	= get_reaction_db_obj()->getCommentReaction($data["post_id"], $secondary_item_id, $item_id);
						$reaction 		= isset($userReaction[0]) ? $userReaction[0]->image_url : "";
					}
				} else {
					$data["post_id"] 	= 0;
				}
			} else {
				$data["post_id"] 		= 0;
			}

			$user_name = bp_core_get_user_displayname($secondary_item_id);
		} elseif (in_array($action, $request)) {
			if ("friendship_request" == $action) {
				$button = [
					["id" => "reject_friendship", "text" => "Reject"],
					["id" => "accept_friendship", "text" => "Accept"]
				];
			}
			$data["user_id"] 	= $item_id;
			$user_name 			= bp_core_get_user_displayname($item_id);
		} elseif (in_array($action, $groups)) {
			$group = groups_get_group($item_id);
			if ($group)
				$user_name = $group->name;

			if ("group_invite" == $action) {
				$button = [
					["id" => "reject_group_request", "text" => "Reject"],
					["id" => "accept_gorup_request", "text" => "Accept"]
				];
			}

			$data["group_id"] 	= $item_id;
		} else {
			$data["user_id"] 	= isset($data["user_id"]) ? $data["user_id"] : $item_id;
			$user_name 			= bp_core_get_user_displayname($item_id);
		}

		// get notification message
		$args = [
			"action"			=> $action,
			"user_name"			=> $user_name,
			"is_forum"			=> $is_forum,
			"topic_id"			=> $topic_id
		];
		$gorup_notification_actions = self::push_notification_messages($args, $obj);

		if (isset($gorup_notification_actions["heading"])) {
			$data["id"] = $notification_id;
			$get_player_ids = [];
			if (is_array($user_id)) {
				foreach ($user_id as $id) {
					$get_player_ids =  array_merge($get_player_ids, get_user_meta($id, SOCIALV_API_PREFIX . 'firebase_tokens') ?? []);
				}
			} else {
				$get_player_ids = get_user_meta($user_id, SOCIALV_API_PREFIX . 'firebase_tokens', true) ?? [];
			}
			$data = [
				"player_ids" 		=> $get_player_ids,
				"data" 				=> $data,
				"button"			=> $button,
				"heading" 			=> [
					"en" 	=> $gorup_notification_actions["heading"]
				],
				"content" 			=> [
					"en" 	=> $gorup_notification_actions["content"]
				]
			];
			if (!empty($reaction))
				$data['large_icon'] = $reaction;
		}

		return $data;
	}

	public static function push_notification_messages($args = [], $obj = NULL)
	{
		$args = wp_parse_args(
			$args,
			[
				"user_name" => "{{user_name}}",
				"action"	=> "",
				"topic_id"	=> "",
				"is_forum" 	=> 1
			]
		);
		$user_name = $args['user_name'];

		$gorup_notification_actions = [
			// user notification
			"new_at_mention"				=> [
				"heading" 	=> $user_name . esc_html__(" mentioned you", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" mentioned you", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("User mention", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} where you want to place user name.", SOCIALV_API_TEXT_DOMAIN)
			],
			"socialv_share_post" 		=> [
				"heading" 	=> $user_name . esc_html__(" shared your post", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" shared your post", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("Share Posts", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} where you want to place user name.", SOCIALV_API_TEXT_DOMAIN)
			],
			"comment_reply" 				=> [
				"heading" 	=> $user_name . esc_html__(" commented", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" replied on your comment", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("Comment", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} where you want to place user name.", SOCIALV_API_TEXT_DOMAIN)
			],
			"update_reply" 					=> [
				"heading" 	=> $user_name . esc_html__(" commented", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" commented on your post", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("Update comment", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} where you want to place user name.", SOCIALV_API_TEXT_DOMAIN)
			],
			"friendship_request" 			=> [
				"heading" 	=> esc_html__("Request from ", SOCIALV_API_TEXT_DOMAIN) . $user_name,
				"content" 	=> $user_name . esc_html__(" sent you a friend request", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("Friend request", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} where you want to place user name.", SOCIALV_API_TEXT_DOMAIN)
			],
			"friendship_accepted" 			=> [
				"heading" 	=> $user_name . esc_html__(" is your friend now", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" accepted your friend request", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("Friendship accepted", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} where you want to place user name.", SOCIALV_API_TEXT_DOMAIN)
			],
			// group notification
			"membership_request_accepted" 	=> [
				"heading"	=> "Now you are in group",
				"content"	=> sprintf(esc_html__("Your request to join %s group has been accepted", SOCIALV_API_TEXT_DOMAIN), $user_name),
				"title"		=> __("Membership request accepted", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{group_title}} where you want to place Group title.", SOCIALV_API_TEXT_DOMAIN)
			],
			"membership_request_rejected" 	=> [
				"heading" 	=> $user_name . esc_html__(" rejected your request", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> sprintf(esc_html__("Your request to join %s group has been rejected", SOCIALV_API_TEXT_DOMAIN), $user_name),
				"title"		=> __("Membership request rejected", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{group_title}} where you want to place Group title.", SOCIALV_API_TEXT_DOMAIN)
			],
			"new_membership_request" 		=> [
				"heading" 	=> $user_name . esc_html__(" wants to join", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" sends request to join the group", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("New membership request", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{group_title}} where you want to place Group title.", SOCIALV_API_TEXT_DOMAIN)
			],
			"group_invite" 					=> [
				"heading" 	=> esc_html__("Invitation from Group", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" group invited you to join the group", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("Group invitation", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{group_title}} where you want to place Group title.", SOCIALV_API_TEXT_DOMAIN)
			],
			"member_promoted_to_admin" 		=> [
				"heading" 	=> esc_html__("You are admin now", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> sprintf(esc_html__("You are promoted to admin in %s group", SOCIALV_API_TEXT_DOMAIN), $user_name),
				"title"		=> __("Member promoted to admin", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{group_title}} where you want to place Group title.", SOCIALV_API_TEXT_DOMAIN)
			]
		];

		// like | reactoin notifiactions
		if (is_reaction_active()) {

			$activity_value = [
				"heading" 	=> $user_name . esc_html__(" reacted on your post", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" reacted on your post", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("Activity Reaction", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} where you want to place user name.", SOCIALV_API_TEXT_DOMAIN)
			];
			$comment_value = [
				"heading" 	=> $user_name . esc_html__(" reacted on your comment", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" reacted on your comment", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("Comment Reaction", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} where you want to place user name.", SOCIALV_API_TEXT_DOMAIN)
			];
			$activity_key 	= "action_activity_reacted";
			$comment_key 	= "action_comment_activity_reacted";

			$firstPart = array_slice($gorup_notification_actions, 0, 1, true);
			$secondPart = array_slice($gorup_notification_actions,  1, null, true);
			$gorup_notification_actions = $firstPart + array($activity_key => $activity_value) + array($comment_key => $comment_value) + $secondPart;
		} else {
			$like_value = [
				"heading" 	=> $user_name . esc_html__(" liked your post", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" liked your post", SOCIALV_API_TEXT_DOMAIN),
				"title"		=> __("Like activity", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} where you want to place user name.", SOCIALV_API_TEXT_DOMAIN)
			];
			$like_key 	= "action_activity_liked";

			$firstPart 	= array_slice($gorup_notification_actions, 0, 1, true);
			$secondPart = array_slice($gorup_notification_actions,  1, null, true);
			$gorup_notification_actions = $firstPart + array($like_key => $like_value) + $secondPart;
		}

		// forum notification
		$forum_title = $topic_title = "";
		if ($args['is_forum']) {
			$forum_title = isset($obj->item_id) ? bbp_get_topic_title(bbp_get_reply_topic_id($obj->item_id)) : "{{forum_title}}";
			$gorup_notification_actions["sv_new_topic"] 	= [
				"heading" 	=> esc_html__("New topic", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> sprintf(__('%s created new topic to %s', SOCIALV_API_TEXT_DOMAIN), $user_name, $forum_title),
				"title"		=> __("Forum subscriber", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} & {{forum_title}} where you want to place user name & topic title.", SOCIALV_API_TEXT_DOMAIN)
			];

			if ($args["action"] == "sv_new_topic_reply")
				$topic_title = isset($obj->item_id) ? bbp_get_topic_title(bbp_get_reply_topic_id($obj->item_id)) : "{{topic_title}}";
			else if ($args["action"] == "sv_new_topic_reply")
				$topic_title = !empty($args['topic_id']) ? bbp_get_topic_title($args['topic_id']) : "{{topic_title}}";
			else
				$topic_title = "{{topic_title}}";


			$gorup_notification_actions["sv_new_topic_reply"] 	= [
				"heading" 	=> esc_html__("New reply", SOCIALV_API_TEXT_DOMAIN),
				"content"  	=> sprintf(__('%s replied to %s', SOCIALV_API_TEXT_DOMAIN), $user_name, $topic_title),
				"title"		=> __("Topic subscriber", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} & {{topic_title}} where you want to place user name & topic title.", SOCIALV_API_TEXT_DOMAIN)
			];

			$gorup_notification_actions["bbp_new_reply_" . $args['topic_id']] 	= [
				"heading" 	=> esc_html__("New topic reply", SOCIALV_API_TEXT_DOMAIN),
				"content" 	=> $user_name . esc_html__(" replied in ", SOCIALV_API_TEXT_DOMAIN) . $topic_title,
				"title"		=> __("Topic replies", SOCIALV_API_TEXT_DOMAIN),
				"desc"		=> __("{{user_name}} & {{topic_title}} where you want to place user name & topic title.", SOCIALV_API_TEXT_DOMAIN)
			];
		}

		// return messages array list
		if ($user_name == "{{user_name}}")
			return $gorup_notification_actions;

		// return specific  notification from list
		$heading = SVSettings::sv_get_option($args["action"] . "_heading");
		if (empty($heading)) {
			$heading = $gorup_notification_actions[$args["action"]]["heading"];
		} else {
			$heading = str_replace("{{user_name}}", $user_name, $heading);
			$heading = str_replace("{{group_title}}", $user_name, $heading);
			$heading = str_replace("{{topic_title}}", $topic_title, $heading);
			$heading = str_replace("{{forum_title}}", $forum_title, $heading);
		}

		$content = SVSettings::sv_get_option($args["action"] . "_content");
		if (!empty($content)) {
			$content = str_replace("{{user_name}}", $user_name, $content);
			$content = str_replace("{{group_title}}", $user_name, $content);
			$content = str_replace("{{topic_title}}", $topic_title, $content);
			$content = str_replace("{{forum_title}}", $forum_title, $content);
		} else {
			$content = "";
		}

		return ["heading" => $heading, "content" => $content];
	}
	public function sv_bm_new_message($message)
	{
		$player_ids = [];
		$sender_id = $message->sender_id;

		$recipients = array_keys($message->recipients);
		foreach ($recipients as $recipient) {
			$muted_threads = Better_Messages()->functions->get_user_muted_threads($recipient);
			if (isset($muted_threads[$message->thread_id])) {
				continue;
			}
			$recipient_player_ids = get_user_meta($recipient, SOCIALV_API_PREFIX . 'firebase_tokens', true);
			if (is_array($recipient_player_ids))
				$player_ids = array_merge($player_ids, $recipient_player_ids);
		}
		if (count($player_ids) < 1) return;
		$additional_data["thread_id"] 	= $message->thread_id;
		$additional_data["message_id"] 	= $message->id;
		$message_content = $message->message == "<!-- BM-ONLY-FILES -->" ? __("Attachment", SOCIALV_API_TEXT_DOMAIN) : html_entity_decode($message->message);
		$message_content = wp_strip_all_tags($message_content);
		$data = [
			"player_ids" 		=> $player_ids,
			"data" 				=> $additional_data,
			"heading" 			=> [
				"en" 	=> bp_core_get_user_displayname($sender_id)
			],
			"content" 			=> [
				"en" 	=> $message_content
			]
		];

		$this->socialv_send_notification($data, true);
	}
	public function sv_bm_reaction($thread_id, $message_id, $meta_key, $reactions)
	{
		if ($meta_key !== 'bm_reactions') return;

		$player_ids = [];
		$sender_id = array_keys($reactions)[0];
		$reaction = array_values($reactions)[0];
		$emoji = mb_convert_encoding('&#x' . $reaction . ';', 'UTF-8', 'HTML-ENTITIES');

		$recipients = Better_Messages()->functions->get_participants($thread_id)['recipients'];
		foreach ($recipients as $recipient) {
			$recipient_player_ids = get_user_meta($recipient, SOCIALV_API_PREFIX . 'firebase_tokens', true);
			if (is_array($recipient_player_ids))
				$player_ids = array_merge($player_ids, $recipient_player_ids);
		}

		$additional_data["thread_id"] 	= $thread_id;
		$additional_data["message_id"] 	= $message_id;
		$data = [
			"player_ids" 		=> $player_ids,
			"data" 				=> $additional_data,
			"heading" 			=> [
				"en" 	=> bp_core_get_user_displayname($sender_id)
			],
			"content" 			=> [
				"en" 	=> sprintf(__("reacted: %s to your message.", "socialv-api"), $emoji)
			]
		];

		$this->socialv_send_notification($data, true);
	}

	private function send_notification_on_firebase($sendContent)
	{
		
		$firebase_config = get_option('sv_app_settings');


		if (empty($sendContent['include_player_ids']))
			return false;


		// FCM endpoint
		$fcmEndpoint = 'https://fcm.googleapis.com/fcm/send';

		// Notification payload
		$message = [
			'title' => $sendContent['headings']["en"],
			'body' => $sendContent['content']["en"],
		];
		$addition = ["additional_data" => $sendContent['data']];
		$data = [
			'registration_ids' => $sendContent['include_player_ids'],
			'notification' => $message,
			'data' => $addition
		];
		

		// Set headers
		$headers = [
			'Authorization: key=' . $firebase_config['svo_firebase_app_id'],
			'Content-Type: application/json',
		];
		

		// Initialize cURL session
		$ch = curl_init($fcmEndpoint);

		// Set cURL options
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

		// Execute cURL session
		$response = curl_exec($ch);
		// Close cURL session
		curl_close($ch);
	}
}
