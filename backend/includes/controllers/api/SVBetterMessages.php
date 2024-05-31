<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\Iqonic_Api_Authentication;
use Includes\baseClasses\SVBase;
use WP_REST_Server;
use WP_User;
use WPForms\Admin\Settings\Captcha\Turnstile;

class SVBetterMessages extends SVBase
{

	public $module = 'messages';

	public $nameSpace;

	function __construct()
	{

		$this->nameSpace = SOCIALV_API_NAMESPACE;

		add_action('rest_api_init', function () {

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/settings',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'socialv_bm_settings'],
					'permission_callback' => '__return_true'
				)
			);

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/message',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'socialv_message'],
					'permission_callback' => '__return_true'
				)
			);

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/emoji-reactions',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'socialv_emoji_reactions'],
					'permission_callback' => '__return_true'
				)
			);

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/chat-background',
				[
					[
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => [$this, 'socialv_chat_background'],
						'permission_callback' => '__return_true'
					],
					[
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => [$this, 'socialv_remove_chat_background'],
						'permission_callback' => '__return_true'
					],
				]
			);
		});
	}

	public function socialv_bm_settings($request)
	{
		return comman_custom_response([
			"status" => true,
			"message" =>  __("Better Messages settings", SOCIALV_API_TEXT_DOMAIN),
			"data" => sv_get_bm_settings()
		]);
	}

	public function socialv_message($request)
	{

		$data = svValidationToken($request);

		if ($data['status']) {
			$current_user_id = (int) $data['user_id'];
		} else {
			return comman_custom_response($data, $data['status_code']);
		}

		$parameters = $request->get_params();
		$parameters = svRecursiveSanitizeTextField($parameters);
		$msg_id = $parameters["id"];

		$message = Better_Messages()->functions->get_message($msg_id);

		if (!$message) return comman_custom_response([
			"status" => true,
			"message" => __("Message Not Found", SOCIALV_API_TEXT_DOMAIN)
		]);

		if (!Better_Messages()->functions->check_access($message->thread_id, $current_user_id)){ return comman_custom_response([
			"status" => true,
			"message" => __("Access denied", SOCIALV_API_TEXT_DOMAIN)
		]);}

		$response = [
			"message_id" 	=> (int) $message->id,
			"thread_id" 	=> (int) $message->thread_id,
			"sender_id" 	=> (int) $message->sender_id,
			"message" 		=> $message->message,
			"date_sent" 	=> $message->date_sent,
			"meta" 			=> apply_filters('better_messages_rest_message_meta', [], (int) $message->id, (int) $message->thread_id, $message->message),
			"favorited" 	=> (Better_Messages()->functions->is_message_starred($message->id, $current_user_id)) ? 1 : 0,
			"lastUpdate"    => (float) Better_Messages()->functions->get_message_meta($message->id, 'bm_last_update', true),
			"createdAt"   	=> (float) Better_Messages()->functions->get_message_meta($message->id, 'bm_created_time', true),
			"tmpId" 		=> Better_Messages()->functions->get_message_meta($message->id, 'bm_tmp_id', true)
		];

		return comman_custom_response([
			"status" => true,
			"message" => __("Message details", SOCIALV_API_TEXT_DOMAIN),
			"data" => $response
		]);
	}

	public function socialv_emoji_reactions($request)
	{
		$reactions = Better_Messages()->settings['reactionsEmojies'];
		$reactions = array_keys($reactions);
		$all_emojis = Better_Messages_Emojis()->getDataset()['emojis'];

		$result = array_filter($all_emojis, function ($item) use ($reactions) {
			return isset($item['skins'][0]['unified']) && in_array($item['skins'][0]['unified'], $reactions);
		});

		if (empty($result)) return comman_custom_response([
			"status" => true,
			"message" => __("No emoji found", SOCIALV_API_TEXT_DOMAIN)
		]);

		return comman_custom_response([
			"status" => true,
			"message" => __("Emoji Details", SOCIALV_API_TEXT_DOMAIN),
			"data" => array_values($result)
		]);
	}

	public function socialv_chat_background($request)
	{

		$data = svValidationToken($request);

		if ($data['status']) {
			$current_user_id = (int) $data['user_id'];
		} else {
			return comman_custom_response($data, $data['status_code']);
		}

		$parameters = $request->get_params();
		$parameters = svRecursiveSanitizeTextField($parameters);
		$type = $parameters["type"];
		$value		= "";
		if ($type == "thread") {
			$thread_id  = $parameters["id"];;
			$key   		= "socialv_chat_background";

			$is_thread_participant 		= Better_Messages()->functions->is_thread_participant($current_user_id, $thread_id);
			$is_thread_moderator 		= Better_Messages()->functions->is_thread_moderator($current_user_id, $thread_id);
			$is_thread_super_moderator 	= Better_Messages()->functions->is_thread_super_moderator($current_user_id, $thread_id);
			
			if (!$is_thread_participant && !$is_thread_moderator && !$is_thread_super_moderator)
				return comman_custom_response([
					"status" => true,
					"message" => __('Sorry, you are not allowed to do that', SOCIALV_API_TEXT_DOMAIN)
				]);

			$thread_meta = Better_Messages()->functions->get_thread_meta($thread_id, $key);
			if ($thread_meta && isset($thread_meta["attachment_id"])) {
				wp_delete_attachment($thread_meta["attachment_id"], true);
			}
			Better_Messages()->functions->update_thread_meta($thread_id, $key, $value);

			$attachment_id = sv_upload_media($_FILES["file"]);
			if ($attachment_id) {
				$value = [
					"attachment_id" => $attachment_id,
					"url" 			=> wp_get_attachment_url($attachment_id)
				];
				Better_Messages()->functions->update_thread_meta($thread_id, $key, $value);

				return comman_custom_response([
					"status" => true,
					"message" =>  __('Update User Thread Meta', SOCIALV_API_TEXT_DOMAIN)
				]);
			}
		} else if ($type == "global") {
			$key   		= "socialv_chat_background";
			$user_meta = get_user_meta($current_user_id, $key, true);
			if ($user_meta && isset($user_meta["attachment_id"])) {
				wp_delete_attachment($user_meta["attachment_id"], true);
			}
			update_user_meta($current_user_id, $key, $value);

			$attachment_id = sv_upload_media($_FILES["file"]);
			if ($attachment_id) {
				$value = [
					"id" 			=> "chat_background",
					"attachment_id" => $attachment_id,
					"url" 			=> wp_get_attachment_url($attachment_id)
				];
				update_user_meta($current_user_id, $key, $value);

				return comman_custom_response([
					"status" => true,
					"message" => __('Update User Meta', SOCIALV_API_TEXT_DOMAIN)
				]);
			}
		}

		return comman_custom_response([
			"status" => false,
			"message" => __('Fail To Update User Meta', SOCIALV_API_TEXT_DOMAIN)
		]);
	}

	public function socialv_remove_chat_background($request)
	{
		
		$data = svValidationToken($request);

		if ($data['status']) {
			$current_user_id = (int) $data['user_id'];
		} else {
			return comman_custom_response($data, $data['status_code']);
		}

		$parameters = $request->get_params();
		$parameters = svRecursiveSanitizeTextField($parameters);
		$value		= "";
		$type = $parameters["type"];

		if ($type == "thread") {
			$thread_id  = $parameters["id"];;
			$key   		= "socialv_chat_background";

			$is_thread_participant 		= Better_Messages()->functions->is_thread_participant($current_user_id, $thread_id);
			$is_thread_moderator 		= Better_Messages()->functions->is_thread_moderator($current_user_id, $thread_id);
			$is_thread_super_moderator 	= Better_Messages()->functions->is_thread_super_moderator($current_user_id, $thread_id);

			if (!$is_thread_participant && !$is_thread_moderator && !$is_thread_super_moderator)
				return comman_custom_response([
					"status" => true,
					"message" => __('Sorry, you are not allowed to do that', SOCIALV_API_TEXT_DOMAIN)
				]);

			$thread_meta = Better_Messages()->functions->get_thread_meta($thread_id, $key);
			if ($thread_meta && isset($thread_meta["attachment_id"])) {
				wp_delete_attachment($thread_meta["attachment_id"], true);
			}
			Better_Messages()->functions->update_thread_meta($thread_id, $key, $value);

			return comman_custom_response([
				"status" => true,
				"message" => __('Chat Background Thread Meta Field Updated', SOCIALV_API_TEXT_DOMAIN)
			]);

		} else if ($type == "global") {
			$key   		= "socialv_chat_background";
			$user_meta = get_user_meta($current_user_id, $key, true);
			if ($user_meta && isset($user_meta["attachment_id"])) {
				wp_delete_attachment($user_meta["attachment_id"], true);
			}
			update_user_meta($current_user_id, $key, $value);

			return comman_custom_response([
				"status" => true,
				"message" => __('Chat Background Meta field Updated', SOCIALV_API_TEXT_DOMAIN)
			]);
			
		}

		return comman_custom_response([
			"status" => false,
			"message" =>  __('Chat Background Fail To Remove', SOCIALV_API_TEXT_DOMAIN)
		]);
	}
}
