<?php

namespace Includes\settings\Options;

use Includes\settings\SVSettings;
use Redux;


class SVMemberProfile extends SVSettings
{
	protected $is_theme_active;

	public function __construct()
	{
		$this->is_theme_active = is_dependent_theme_active();
		$this->set_widget_option();
		add_action("wp", [$this, "sv_default_avatar"], 999);
	}

	public function member_profile_options()
	{
		$disable_class = $this->is_theme_active ? "disabled socialv-api-sub-fields fold" : "";
		$options = [
			[
				'id' 		=> parent::$sv_option_prefix . 'default_user_display_name',
				'type' 		=> 'text',
				'default'	=> __("SocialV User", "socialv-api"),
				'title' 	=> __('Default user display name', 'socialv-api'),
				'desc' 		=> __("If the user's display name is empty, the entered name will be displayed. If you don't need a default user display name, you can simply leave the field empty..", "socialv-api"),
			],
			[
				'id'       	=> parent::$sv_option_prefix . 'defalt_avatar_img',
				'type'     	=> 'media',
				'url'      	=> false,
				'read-only' => false,
				'title'    	=> esc_html__('Avatar', 'wp-rig'),
				'subtitle' 	=> esc_html__('Set default avatar image from here.', 'wp-rig'),
				'class'		=> $disable_class
			],
			[
				'id'        => parent::$sv_option_prefix . 'posts_count',
				'type'      => 'button_set',
				'title'     => esc_html__('Display posts count', 'socialv-api'),
				'subtitle'  => esc_html__('Enable to display posts count in profile.', 'socialv-api'),
				'class'		=> $disable_class,
				'options'   => array(
					0	=> esc_html__('Disable', 'socialv-api'),
					1   => esc_html__('Enable', 'socialv-api')
				),
				'default'   => 1
			],
			[
				'id'        => parent::$sv_option_prefix . 'comments_count',
				'type'      => 'button_set',
				'title'     => esc_html__('Display comment count', 'socialv-api'),
				'subtitle' 	=> esc_html__('Enable to display comment count in profile.', 'socialv-api'),
				'class'		=> $disable_class,
				'options'   => array(
					0	=> esc_html__('Disable', 'socialv-api'),
					1   => esc_html__('Enable', 'socialv-api')
				),
				'default'   => 1
			],
			[
				'id'        => parent::$sv_option_prefix . 'profile_views',
				'type'      => 'button_set',
				'title'     => esc_html__('Display profile views', 'socialv-api'),
				'subtitle' 	=> esc_html__('Enable to display profile views.', 'socialv-api'),
				'class'		=> $disable_class,
				'options'   => array(
					0	=> esc_html__('Disable', 'socialv-api'),
					1   => esc_html__('Enable', 'socialv-api')
				),
				'default'   => 1
			],
			[
				'id'        => parent::$sv_option_prefix . 'friend_request_btn',
				'type'      => 'button_set',
				'title'     => esc_html__('Request Button', 'socialv-api'),
				'class'		=> $disable_class,
				'subtitle' 	=> esc_html__('Enable to friend request button.', 'socialv-api'),
				'options'   => array(
					0	=> esc_html__('Disable', 'socialv-api'),
					1   => esc_html__('Enable', 'socialv-api')
				),
				'default'   => 1
			]
		];
		if ($this->is_theme_active) {
			$option_info = [
				'id'    => 'member_profile_settings_notes',
				'type'  => 'info',
				'title' => esc_html__('Disabled Options', 'socialv-api'),
				'style' => 'warning',
				'icon' 	=> 'el el-info-circle',
				'desc'  => esc_html__('Disabled options can be manageable from theme options now.', 'socialv-api')
			];
			array_unshift($options, $option_info);
		}
		return $options;
	}
	protected function set_widget_option()
	{
		// -------- User Profile Settings ----------//
		Redux::set_section($this->opt_name, array(
			'title' => esc_html__('Member Profile', 'socialv-api'),
			'id'    => 'user_section',
			'icon'  => 'custom-member-profile',
			'subsection' => true,
			'fields' => $this->member_profile_options()
		));
	}

	public function sv_default_avatar()
	{
		$default_avatar = SVSettings::sv_get_theme_dependent_options("defalt_avatar_img");
		if (isset($default_avatar["url"]) && !empty($default_avatar["url"]))
			define('BP_AVATAR_DEFAULT',  $default_avatar["url"]);
		else if (!is_dependent_theme_active() && !defined('BP_AVATAR_DEFAULT'))
			define('BP_AVATAR_DEFAULT',  SOCIALV_API_DIR_URI . '/assets/images/default-avatar.jpg');
	}
}
