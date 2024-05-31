<?php

namespace Includes\baseClasses;

use Google\Web_Stories_Dependencies\AmpProject\Validator\Spec\Tag\Em;
use WP_Error;
use WP_Query;

class SVStories
{
    public function get_option($box_id, $option_key, $check = 'style_type', $from_opt = false, $default = null)
    {
        return wpstory_premium_helpers()->get_option($box_id, $option_key, $check, $from_opt, $default);
    }

    public function sv_get_user_public_story_items($story_id)
    {
        $story_item = array(
            'text'     => get_post_meta($story_id, 'text', true),
            'link'     => get_post_meta($story_id, 'link', true),
            'image'    => get_post_meta($story_id, 'image', true),
            'duration' => get_post_meta($story_id, 'duration', true),
            'new_tab'  => get_post_meta($story_id, 'new_tab', true),
        );

        $image_id = isset($story_item['image']['id']) ? $story_item['image']['id'] : '';

        if (empty($image_id)) {
            return;
        }

        $mime_type = wp_attachment_is('video', $image_id) ? 'video' : 'image';

        $duration = isset($story_item['duration']) ? $story_item['duration'] : 3;
        $src      = isset($story_item['image']['id']) ? wp_get_attachment_url($story_item['image']['id']) : '';

        if ('video' === $mime_type) {
            $video_meta  = get_post_meta($image_id, '_wp_attachment_metadata', true);
            $time_length = $video_meta['length_formatted'] ?? null;
            $duration    = WPSTORY()->time_to_sec($time_length);
        }

        return array(
            'id'            => (int) $story_id,
            'media_type'    => $mime_type,
            'duration'      => $duration,
            'story_media'   => $src,
            'story_link'    => $story_item['link'],
            'story_text'    => $story_item['text'],
            'time'          => get_post_timestamp($story_id),

        );
    }

    public function get_story_items($box_id, $author_id, $current_user_id)
    {
        $story_items_arr    = array();
        $is_seen            = true;
        $story_items        = get_post_meta($box_id, '', true);

        if (!empty($story_items) && is_array($story_items)) {

            $duration   = isset($story_items['duration'][0])  && !empty($story_items['duration'][0]) ? $story_items['duration'][0] : 3;
            $image      = isset($story_items['image'][0])  && !empty($story_items['image'][0]) ? maybe_unserialize($story_items['image'][0]) : '';
            $story_text = isset($story_items['text'][0])  && !empty($story_items['text'][0]) ? $story_items['text'][0] : '';
            $story_link = isset($story_items['link'][0])  && !empty($story_items['link'][0]) ? $story_items['link'][0] : '';
            $seen_key   = "sv_story_seen_by";
            $is_seen    = socialv_is_seen($box_id, $seen_key, $current_user_id);
            $exists     = socialv_get_story_seen_by($box_id, $seen_key);
            $view_count = $exists ? count($exists) : 0;
            $image_id   = !empty($image['id']) ? $image['id'] : '';

            $story_items_arr[] = array(
                'id'            => (int) $box_id,
                'seen_by_key'   => $seen_key,
                'duration'      => $duration,
                'media_type'    => wp_attachment_is('video', $image_id) ? 'video' : 'photo',
                'story_media'   => !empty($image['url']) ? $image['url'] : '',
                'story_link'    => $story_link,
                'story_text'    => $story_text,
                'time'          => get_post_timestamp($box_id),
                'seen'          => $is_seen,
                'view_count'    => $view_count,
            );
        }

        return ["items" => $story_items_arr, "seen" => $is_seen];
    }

    public function get_stories($box_id, $author = false, $current_user_id = false)
    {
        $stories = true;

        $current_user_id = $current_user_id ? $current_user_id : $author;
        $author_friends = function_exists("friends_get_friend_user_ids") ? friends_get_friend_user_ids($current_user_id) : "";

        if (!$author) {
            $stories = get_post_meta($box_id, 'wp-story-box-metabox', true);
            $stories = isset($stories['ids']) ? $stories['ids'] : null;
        }


        if (!$stories) {
            return false;
        }

        $author = !empty($author_friends) ? array_merge([$current_user_id], $author_friends) : [$current_user_id];

        $args = array(
            'order'             => 'DESC',
            'post_type'         => 'wpstory-user',
            'posts_per_page'    => -1,
            'post__in'          => [],
            'author__in'        =>  $author,
            'orderby'           => 'author__in',
            'post_status'       => 'publish',
        );

        $skip_timer = !wpstory_premium_helpers()->options('single_stories_timer');
        /**
         * Skip timer with adding custom parameter into $query_args argument.
         * 'skip_timer' => true will ignore timer feature.
         */


        if ($this->get_option($box_id, 'story_timer', 'timer_enable') && !$skip_timer) {
            $timer_day          = (int) $this->get_option($box_id, 'story_time_value', 'timer_enable');
            $args['date_query'] = array(
                'after' => gmdate('Y-m-d', strtotime('-' . $timer_day . ' days')),
            );
        }

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            wp_reset_postdata();
            return new WP_Error('stories-not-found', esc_html__('No stories found!', 'wp-story-premium'));
        }
        $user_stories = $cycle_arr = $add_to_last = $add_to_last_ids = [];

        $is_seen = true;
        while ($query->have_posts()) {
            $query->the_post();
            $author_id = get_the_author_meta('ID');
            $story_id    = get_the_ID();

            $story_items_arr = $this->get_story_items($story_id, $author_id, $current_user_id);
            if (empty($story_items_arr)) {
                continue;
            }

            if (!empty($story_items_arr["items"])) {
                $user_stories =  $story_items_arr["items"];
            }

            if (!empty($cycle_arr[$author_id]) || !empty($add_to_last[$author_id])) {
                if (in_array($author_id, $add_to_last_ids))
                    $add_to_last[$author_id]['items'] = array_merge($user_stories, $add_to_last[$author_id]['items']);
                else
                    $cycle_arr[$author_id]['items'] = array_merge($user_stories, $cycle_arr[$author_id]['items']);
            } else {
                $is_seen = self::is_all_story_viewed($author_id, $current_user_id);
                $display_name = wpstory_premium_helpers()->get_user_name($author_id);
                if ($is_seen) {
                    $add_to_last[$author_id] = array(
                        'user_id'           => (int) $author_id,
                        'avarat_url'        => wpstory_premium_helpers()->get_user_avatar($author_id, 180),
                        'name'              => $display_name,
                        'lastUpdated'       => get_post_timestamp($story_id),
                        "is_user_verified"  => sv_is_user_verified($author_id),
                        'seen'              => $is_seen,
                        'items'             => $user_stories
                    );
                    $add_to_last_ids[] = $author_id;
                } else {
                    $cycle_arr[$author_id]    = array(
                        'user_id'           => (int) $author_id,
                        'avarat_url'        => wpstory_premium_helpers()->get_user_avatar($author_id, 180),
                        'name'              => $display_name,
                        'lastUpdated'       => get_post_timestamp($story_id),
                        "is_user_verified"  => sv_is_user_verified($author_id),
                        'seen'              => $is_seen,
                        'items'             => $user_stories
                    );
                }
            }
        }
        wp_reset_postdata();

        if (empty($user_stories)) {
            return new WP_Error('stories-not-found', esc_html__('No stories found!', 'wp-story-premium'));
        }
        $cycle_arr = array_merge($cycle_arr, $add_to_last);
        return array_values($cycle_arr);
    }

    public function get_user_stories($box_id, $author, $current_user_id)
    {
        $args = array(
            'order'             => 'DESC',
            'post_type'         => 'wpstory-user',
            'posts_per_page'    => -1,
            'post__in'          => array(),
            'author__in'        => array($author),
            'post_status'       => 'publish'
        );

        $skip_timer = !wpstory_premium_helpers()->options('single_stories_timer');
        /**
         * Skip timer with adding custom parameter into $query_args argument.
         * 'skip_timer' => true will ignore timer feature.
         */


        if ($this->get_option($box_id, 'story_timer', 'timer_enable') && !$skip_timer) {
            $timer_day          = (int) $this->get_option($box_id, 'story_time_value', 'timer_enable');
            $args['date_query'] = array(
                'after' => gmdate('Y-m-d', strtotime('-' . $timer_day . ' days')),
            );
        }

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            wp_reset_postdata();
            return new WP_Error('stories-not-found', esc_html__('No stories found!', 'wp-story-premium'));
        }

        $user_stories   = [];
        $is_seen        = true;
        while ($query->have_posts()) {
            $query->the_post();

            $author_id = $author;
            $story_id  = get_the_ID();


            $story_items_arr = $this->get_story_items($story_id, $author_id, $current_user_id);
            if (empty($story_items_arr)) {
                continue;
            }

            if (!empty($story_items_arr["items"])) {
                if ($is_seen && !$story_items_arr["seen"])
                    $is_seen = false;
                $user_stories = array_merge($user_stories, $story_items_arr["items"]);
            }
        }
        wp_reset_postdata();

        if (empty($user_stories)) {
            return new WP_Error('stories-not-found', esc_html__('No stories found!', 'wp-story-premium'));
        }
        
        $cycle_arr    = array(
            'user_id'           => (int) $author_id,
            'avarat_url'        => wpstory_premium_helpers()->get_user_avatar($author_id, 180),
            'name'              => wpstory_premium_helpers()->get_user_name($author_id),
            'lastUpdated'       => get_post_timestamp($story_id),
            "is_user_verified"  => sv_is_user_verified($author_id),
            'seen'              => $is_seen,
            "items"             => $user_stories
        );

        return $cycle_arr;
    }
    // get highlight stories
    public function sv_get_user_public_stories($author_id, $query_args = array(), $status = "publish")
    {
        if (!function_exists("WPSTORY")) return [];
        if (!WPSTORY()->options('buddypress_public_stories')) return [];

        $args = array(
            'order'          => 'DESC',
            'post_type'      => 'wpstory-public',
            'author'         => $author_id,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'post_status'    => 'publish',
            'post_parent'    => 0,
        );

        /**
         * Skip timer with adding custom parameter into $query_args argument.
         * 'skip_timer' => true will ignore timer feature.
         */
        $skip_timer = isset($query_args['skip_timer']) && $query_args['skip_timer'];

        if (WPSTORY()->options('story_timer') && !$skip_timer) {
            $timer_day          = (int) WPSTORY()->options('story_time_value');
            $args['date_query'] = array(
                'after' => gmdate('Y-m-d', strtotime('-' . $timer_day . ' days')),
            );
        }

        if (!empty($query_args) && is_array($query_args)) {
            foreach ($query_args as $key => $value) {
                $args[$key] = $value;
            }
        }

        $parent_stories = new WP_Query($args);
        if (!$parent_stories->have_posts()) {
            wp_reset_postdata();

            return [];
        }

        $story_circles = array();
        while ($parent_stories->have_posts()) {
            $parent_stories->the_post();

            $story_parent_id = get_the_ID();

            $child_stories = new WP_Query(
                array(
                    'order'          => 'DESC',
                    'post_type'      => 'wpstory-public',
                    'author'         => $author_id,
                    'posts_per_page' => -1,
                    'orderby'        => 'date',
                    'post_status'    => $status,
                    'post_parent'    => $story_parent_id,
                )
            );

            if (!$child_stories->have_posts()) {
                $child_stories->reset_postdata();
                continue;
            }

            $story_items_arr = array();
            while ($child_stories->have_posts()) {
                $child_stories->the_post();
                $child_story_id    = get_the_ID();
                $story_items_arr[] = $this->sv_get_user_public_story_items($child_story_id);
            }

            wp_reset_postdata();

            if (has_post_thumbnail($story_parent_id)) {
                $circle_image_id   = get_post_thumbnail_id($story_parent_id);
                $circle_image      = wp_get_attachment_image_url($circle_image_id, 'medium');
            } else {
                $circle_image      = $story_items_arr[0]['preview'];
            }

            $circle_title = get_the_title($story_parent_id);

            $circles_append = array(
                "catgeroy_id"       => $story_parent_id,
                'category_image'    => $circle_image,
                'catgeroy_name'     => $circle_title,
                'items'             => $story_items_arr,
            );

            $story_circles[] = $circles_append;
        }

        wp_reset_postdata();

        return  $story_circles;
    }

    public function is_all_story_viewed($author_id, $current_user_id)
    {
        $args = array(
            'order'             => 'DESC',
            'post_type'         => 'wpstory-user',
            'posts_per_page'    => -1,
            'post__in'          => array(),
            'author__in'        => array($author_id),
            'orderby'           => 'post__in',
            'post_status'       => 'publish',
        );

        $box_id = 0;
        $skip_timer = !wpstory_premium_helpers()->options('single_stories_timer');
        /**
         * Skip timer with adding custom parameter into $query_args argument.
         * 'skip_timer' => true will ignore timer feature.
         */


        if ($this->get_option($box_id, 'story_timer', 'timer_enable') && !$skip_timer) {
            $timer_day          = (int) $this->get_option($box_id, 'story_time_value', 'timer_enable');
            $args['date_query'] = array(
                'after' => gmdate('Y-m-d', strtotime('-' . $timer_day . ' days')),
            );
        }

        $query = new WP_Query($args);
        if (!$query->have_posts()) {
            wp_reset_postdata();
            return false;
        }

        $is_seen_all = true;
        while ($query->have_posts()) {
            $query->the_post();

            $story_id = get_the_ID();

            $seen_key = 'sv_story_seen_by';
            $is_seen  = socialv_is_seen($story_id, $seen_key, $current_user_id);

            if (!$is_seen && $is_seen_all) $is_seen_all = false;
        }

        wp_reset_postdata();



        return  $is_seen_all;
    }
}
