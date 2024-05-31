<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\Iqonic_Api_Authentication;
use Includes\baseClasses\SVBase;
use Includes\settings\SVSettings;
use WP_REST_Server;
use WP_User;

class SVSocialController extends SVBase
{

	public $module = 'socialv';

	public $nameSpace;

	function __construct()
	{

		$this->nameSpace = SOCIALV_API_NAMESPACE;

		add_action('rest_api_init', function () {

			register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/social-login', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [$this, 'socialv_social_login'],
				'permission_callback' => '__return_true'
			));

			register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/change-password', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [$this, 'socialv_change_password'],
				'permission_callback' => '__return_true'
			));

			register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/forgot-password', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [$this, 'socialv_forgot_password'],
				'permission_callback' => '__return_true'
			));

			register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/update-profile', array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [$this, 'socialv_update_profile'],
				'permission_callback' => '__return_true'
			));

			register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/delete-account', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [$this, 'socialv_delete_user_account'],
				'permission_callback' => '__return_true'
			));

			register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/settings', array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [$this, 'socialv_app_serttings'],
				'permission_callback' => '__return_true'
			));
		});
	}

	public function socialv_change_password($request)
	{

		$data = svValidationToken($request);

		if ($data['status']) {
			$user_id = $data['user_id'];
		} else {
			return comman_custom_response($data, $data['status_code']);
		}

		$parameters = $request->get_params();
		$parameters = svRecursiveSanitizeTextField($parameters);
		$userdata = get_user_by('ID', $data['user_id']);

		if ($userdata == null) {

			if ($userdata == null) {
				return comman_custom_response([
					"status" => false,
					"message" =>   __("User not found", SOCIALV_API_TEXT_DOMAIN)
				]);
			}
		}

		$status_code = true;

		if (wp_check_password($parameters['old_password'], $userdata->data->user_pass)) {
			wp_set_password($parameters['new_password'], $userdata->ID);
			$message = esc_html__("Password has been changed successfully", "socialv-api");
		} else {
			$status_code = false;
			$message = esc_html__("Old password is invalid", "socialv-api");
		}
		return comman_custom_response([
			"status" => $status_code,
			"message" =>   $message
		]);
	}

	public function socialv_forgot_password($request)
	{
		$parameters = $request->get_params();
		$email = $parameters['email'];

		$user = get_user_by('email', $email);
		$message = null;
		$status_code = null;

		if ($user) {

			$title = esc_html__('New Password', 'socialv-api');
			$password = svGenerateString();
			$message = '<label><b>' . esc_html__('Hello,', 'socialv-api') . '</b></label>';
			$message .= '<p>' . esc_html__('Your recently requested to reset your password. Here is the new password for your App', 'socialv-api') . '</p>';
			$message .= '<p><b>' . esc_html__('New Password') . ' </b> : ' . $password . '</p>';
			$message .= '<p>' . esc_html__('Thanks,', 'socialv-api') . '</p>';

			$headers = "MIME-Version: 1.0" . "\r\n";
			$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
			$is_sent_wp_mail = wp_mail($email, $title, $message, $headers);

			if ($is_sent_wp_mail) {
				wp_set_password($password, $user->ID);
				$message = esc_html__('Password has been sent successfully to your email address.', 'socialv-api');
				$status_code = true;
			} elseif (mail($email, $title, $message, $headers)) {
				wp_set_password($password, $user->ID);
				$message = esc_html__('Password has been sent successfully to your email address.', 'socialv-api');
				$status_code = true;
			} else {
				$message = esc_html__('Email not sent', 'socialv-api');
				$status_code = false;
			}
		} else {
			$message = esc_html__('User not found with this email address', 'socialv-api');
			$status_code = false;
		}
		return comman_custom_response([
			"status" => $status_code,
			"message" =>   $message
		]);
	}

	public function socialv_update_profile($request)
	{
		global $wpdb;
		$data = svValidationToken($request);

		if ($data['status']) {
			$user_id = $data['user_id'];
		} else {
			return comman_custom_response($data, $data['status_code']);
		}

		$userdata 	= get_user_by('ID', $user_id);
		if (!$userdata)
			return comman_custom_response([
				"status" => false,
				"message" => __('User not found', SOCIALV_API_TEXT_DOMAIN)
			]);

		$parameters = $request->get_params();
		$parameters = svRecursiveSanitizeTextField($parameters);

		$user_id		= $userdata->ID;
		$user_login 	= $parameters['user_login'];
		$email 			= $parameters['email'];
		$user_exists 	= username_exists($user_login);
		$email_exists 	= email_exists($email);

		if ($user_exists && $user_exists != $user_id)
			return comman_custom_response([
				"status" => false,
				"message" => __('Username already taken. Try another.', SOCIALV_API_TEXT_DOMAIN)
			]);

		if (!empty(trim($email)) && $email_exists && $email_exists != $user_id)
			return comman_custom_response([
				"status" => false,
				"message" => __('Email ID already exists.', SOCIALV_API_TEXT_DOMAIN)
			]);

		$first_name = $parameters['first_name'];
		$last_name 	= $parameters['last_name'];

		if ($user_login != $userdata->user_login) {
			$username = $wpdb->update(
				$wpdb->users,
				['user_login' => $user_login],
				['ID' => $user_id]
			);

			if (!$username)
				return comman_custom_response([
					"status" => false,
					"message" => __('Something wrong, Username does not changed. Try Again.', SOCIALV_API_TEXT_DOMAIN)
				]);
		}
		wp_update_user([
			'ID' 			=> $user_id,
			'first_name' 	=> $first_name,
			'last_name' 	=> $last_name,
			'user_email'	=> $email,
			'user_nicename'	=> $user_login,
			"nickname"		=> $first_name,
			"display_name"	=> $first_name . " " . $last_name

		]);
		update_user_meta($user_id, 'sv_is_rest_profile_updated', true);
		wp_set_password($parameters["password"], $user_id);

		return comman_custom_response([
			"status" => true,
			"message" => __('Profile has been updated successfully', SOCIALV_API_TEXT_DOMAIN)
		]);
	}

	public function socialv_delete_user_account($request)
	{
		require_once(ABSPATH . 'wp-admin/includes/user.php');

		$data = svValidationToken($request);

		if ($data['status']) {
			$user_id = $data['user_id'];
		} else {
			return comman_custom_response($data, $data['status_code']);
		}
		$user = wp_delete_user($user_id, true);
		if ($user) {
			return comman_custom_response([
				"status" => true,
				"message" => __('User Deleted Successfully', SOCIALV_API_TEXT_DOMAIN)
			]);
		} else {
			return comman_custom_response([
				"status" => false,
				"message" => __('User not Deleted', SOCIALV_API_TEXT_DOMAIN)
			]);
		}
	}

	function socialv_social_login($request)
	{
		$parameters = $request->get_params();
		$parameters = svRecursiveSanitizeTextField($parameters);

		$login_type 	= $parameters['login_type'];
		$is_mobile 		= ($login_type === "mobile");

		if ($is_mobile)
			return $this->sv_opt_login($parameters, $request);

		$res 			= '';
		$email 			= $parameters['email'];
		$password 		= $parameters['access_token'];

		$user 	= get_user_by("email", $email);
		if (!$user) {
			$first_name = $parameters['first_name'];
			$last_name 	= $parameters['last_name'];
			$avatar_url = $parameters['avatar_url'];

			$user 		= wp_create_user($email, $password, $email);
			wp_update_user([
				'ID' 			=> $user,
				'display_name' 	=> $first_name . ' ' . $last_name,
			]);

			update_user_meta($user, 'login_type', $login_type);
			update_user_meta($user, 'first_name', trim($first_name));
			update_user_meta($user, 'last_name', trim($last_name));

			$validate = new Iqonic_Api_Authentication();

			$res = $validate->iqonic_validate_social("username=" . $email . "&password=" . $password);
			if (!empty($avatar_url) && wp_check_filetype($parameters['avatar_url'])['ext'])
				sv_upload_avatar($avatar_url, $user);
			else
				update_user_meta($user, 'sv_social_login_avatar', $avatar_url);
		} else {
			wp_set_password($password, $user->ID);

			$validate 	= new Iqonic_Api_Authentication();
			$res 		= $validate->iqonic_validate_social("username=" . $email . "&password=" . $password);
		}

		return comman_custom_response([
			"status" => true,
			"message" => __('Social Login ', SOCIALV_API_TEXT_DOMAIN),
			"data" => json_decode($res)
		]);
	}

	public function sv_opt_login($parameters, $request = [])
	{


		$phone	= $parameters["phone"];

		$args 		= [
			'meta_key' 		=> "sv_phone",
			'meta_value' 	=> $phone,
			'number' 		=> 1
		];
		$user 		= get_users($args);

		if (empty($user)) {
			$user 				= wp_create_user($phone, $phone);
			$is_profile_updated = ["is_profile_updated" => false];

			wp_update_user([
				'ID' 			=> $user,
				'display_name' 	=> SVSettings::sv_get_option('default_user_display_name'),
			]);

			update_user_meta($user, 'login_type', $parameters['login_type']);
			update_user_meta($user, 'sv_phone', $phone);
			update_user_meta($user, 'sv_is_rest_profile_updated', false);

			$u = new WP_User($user);
			$u->set_role('subscriber');
			$validate = new Iqonic_Api_Authentication();

			$res = $validate->iqonic_validate_social("username=" . $phone . "&password=" . $phone);
			$res = array_merge(json_decode($res, true), $is_profile_updated);
		} else {
			$u 					= new WP_User(reset($user));
			$is_profile_updated = get_user_meta($u->ID, "sv_is_rest_profile_updated", true);
			$is_profile_updated = ["is_profile_updated" => $is_profile_updated ? true : false];
			$validate 			= new Iqonic_Api_Authentication();
			$res 				= $validate->iqonic_otp_login_token($u);
			$res 				= array_merge($res, $is_profile_updated);
		}

		return comman_custom_response([
			"status" => true,
			"message" => __('Login option', SOCIALV_API_TEXT_DOMAIN),
			"data" => $res
		]);
	}

	public function socialv_app_serttings($request)
	{	
		$is_pmp_enable = false;
		$plugins = get_option( 'active_plugins' );
		if ( in_array( 'paid-memberships-pro/paid-memberships-pro.php', $plugins ) ) { 
			$is_pmp_enable = true;
		}

		$settings = [
			"is_account_verification_require" 	=> (int) SVSettings::sv_get_option("account_verification"),
			"is_paid_membership_enable"			=> $is_pmp_enable,
			'show_ads' 							=> (int) SVSettings::sv_get_option("is_ads_enable"),
			'show_blogs' 						=> (int) SVSettings::sv_get_option("is_blog_enable"),
			'show_social_login' 				=> (int) SVSettings::sv_get_option("is_social_login_enable"),
			'show_shop' 						=> (int) SVSettings::sv_get_option("is_shop_enable"),
			'show_gamipress' 					=> (int) SVSettings::sv_get_option("is_gamipress_enable"),
			'show_learnpress' 					=> (int) SVSettings::sv_get_option("is_course_enable"),
			'show_membership' 					=> (int) SVSettings::sv_get_option("is_membership_enable"),
			'show_forums' 						=> (int) SVSettings::sv_get_option("is_forums_enable")
		];

		return comman_custom_response([
			"status" 	=> true,
			"message" 	=> __('Application settings', SOCIALV_API_TEXT_DOMAIN),
			"data" 		=> $settings
		]);
	}
}
