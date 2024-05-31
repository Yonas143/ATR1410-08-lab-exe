<?php

use Includes\baseClasses\SVStories;

function socialv_upload_story_attachment($attachment)
{
    $blob            = $attachment; // phpcs:ignore
    $blob_mime_type  = $blob['type'];
    $blob_file_type  = explode('/', $blob_mime_type)[0];
    $blob_check_type = explode('/', $blob_mime_type)[1];
    $allowed_types   = wpstory_premium_helpers()->get_allowed_file_types('array');

    /**
     * Check allowed file types.
     * This method required for security.
     * First control is being on frontend. If someone hack, block it here.
     */
    if (!empty($allowed_types) && !in_array($blob_check_type, $allowed_types, true)) {
        return ['attachment_id' => false, 'message' => esc_html__('File type not allowed', 'socialv-api')];
    }

    // if ('video' === $blob_file_type) {
    //     $video_id = media_handle_upload('file', 0);

    //     if (is_wp_error($video_id)) {
    //         return ['attachment_id' => false, 'message' => esc_html__('Something wrong. Try again !', 'socialv-api')];
    //     }

    //     return ['attachment_id' => $video_id];
    //     // wp_send_json_success(array('message' => $video_id));
    // }

    $blob_name       = $blob['name'];
    $blob_type       = '.' . explode('/', $blob_mime_type)[1];
    $upload_dir      = wp_upload_dir();
    $upload_path     = str_replace('/', DIRECTORY_SEPARATOR, $upload_dir['path']) . DIRECTORY_SEPARATOR;
    $upload_url      = str_replace('/', DIRECTORY_SEPARATOR, $upload_dir['url']) . DIRECTORY_SEPARATOR;
    $hashed_filename = md5($blob_name . microtime()) . '_' . $blob_name . $blob_type;

    move_uploaded_file($blob['tmp_name'], $upload_path . $hashed_filename);

    $file             = array();
    $file['error']    = '';
    $file['tmp_name'] = $upload_path . $hashed_filename;
    $file['name']     = $hashed_filename;
    $file['type']     = $blob_mime_type;
    $file['size']     = filesize($upload_path . $hashed_filename);

    $file_return = wp_handle_sideload(
        $file,
        array(
            'test_form' => false,
            'test_type' => false,
        )
    );

    $filename   = $file_return['file'];
    $attachment = array(
        'post_mime_type' => $blob_mime_type,
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filename)),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'guid'           => $upload_url . basename($filename),
    );

    $attachment_id   = wp_insert_attachment($attachment, $filename);

    $attachment_meta = wp_generate_attachment_metadata($attachment_id, $filename);

    wp_update_attachment_metadata($attachment_id, $attachment_meta);

    return ['attachment_id' => $attachment_id];
    // wp_send_json_success(array('message' => $attachment_id));
}

function socialv_submit_story($args = [])
{
    do_action('wpstory_before_story_submit', 'single');
    if (empty($args))
        return [
            "message"       => esc_html__("one of the required field is empty", "socialv-api"),
            "status_code"   => 422
        ];

    $user             = get_user_by("ID", $args['current_user_id']);
    $user_story_count = wpstory_premium_helpers()->user_story_count($user->ID);
    $story_limit      = wpstory_premium_helpers()->options('user_story_limit');

    // Check story count.
    if (!empty($story_limit) && $user_story_count >= $story_limit) {
        return [
            'message' => sprintf( /* translators: %1$s: story limit %2$s: story count */
                _n(
                    'You can publish only %1$s story! Currently story count is %2$s.',
                    'You can publish only %1$s stories! Currently story count is %2$s.',
                    $story_limit,
                    'socialv-api'
                ),
                $story_limit,
                $user_story_count
            ),
            "status_code"   => 422
        ];
    }

    $attachment_id = isset($args['sv-wpstory-item-media-id']) && !empty($args['sv-wpstory-item-media-id']) ? wp_unslash((int) $args['sv-wpstory-item-media-id']) : null;

    // If there is an attachment ID hack, abort ajax.
    if (empty($attachment_id) || 'attachment' !== get_post_type($attachment_id)) {
        return array('message' => 'error 1001', "status_code"   => 422);
    }

    $link_text = isset($args['sv-wpstory-story-link-text']) && !empty($args['sv-wpstory-story-link-text']) ? sanitize_text_field(wp_unslash($args['sv-wpstory-story-link-text'])) : '';
    $link      = isset($args['sv-wpstory-story-link']) && !empty($args['sv-wpstory-story-link']) ? esc_url_raw(wp_unslash($args['sv-wpstory-story-link'])) : '';
    $duration  = isset($args['sv-wpstory-story-duration']) && !empty($args['sv-wpstory-story-duration']) ? wp_unslash((int) $args['sv-wpstory-story-duration']) : '';
    $status    = wpstory_premium_helpers()->options('user_publish_status', 'draft');

    if (!wpstory_premium_helpers()->options('allow_link', true)) {
        $link_text = '';
        $link      = '';
    }

    $post_object = array(
        'text'     => $link_text,
        'link'     => $link,
        'duration' => $duration > 0 ? $duration : wpstory_premium_helpers()->options('default_story_duration', 3),
        'image'    => array(
            'url'       => wp_get_attachment_url($attachment_id),
            'id'        => $attachment_id,
            'thumbnail' => wp_get_attachment_image_url($attachment_id, 'thumbnail', true),
        ),
    );
    $user_name = wpstory_premium_helpers()->get_user_name($user->ID);
    $story_id = wp_insert_post(
        array(
            'post_author' => $user->ID,
            'post_title'  => $user_name,
            'post_type'   => 'wpstory-user',
            'post_status' => $status,
            'meta_input'  => $post_object,
        )
    );

    if (!is_wp_error($story_id)) {

        // Attach uploaded images to story.
        wp_update_post(
            array(
                'ID'          => $attachment_id,
                'post_parent' => $story_id,
            )
        );

        $published_message = esc_html__('Story published!', 'socialv-api');

        if ('publish' !== $status) {
            $published_message = esc_html__('Your story sent for review!', 'socialv-api');
        }

        return ['message' => $published_message, "status_code" => 200];
        update_user_meta($user->ID, 'wpstory_last_updated', current_time('mysql'));
    }

    return ['message' => esc_html__('Error! Something went wrong.', 'socialv-api'), "status_code" => 422];
}

function socialv_submit_public_story($args)
{
    do_action('wpstory_before_story_submit', 'public');

    if (empty($args))
        return [
            "message"       => esc_html__("one of the required field is empty", "socialv-api"),
            "status_code"   => 422
        ];

    $user             = get_user_by("ID", $args['current_user_id']);

    $user_story_count = wpstory_premium_helpers()->user_story_count($user->ID, 'wpstory-public');
    $story_limit      = wpstory_premium_helpers()->options('user_public_story_limit', 10);

    // Check story count.

    if (!empty($story_limit) && $user_story_count >= $story_limit) {
        return [
            'message' => sprintf( /* translators: %1$s: story limit %2$s: story count */
                _n(
                    'You can publish only %1$s story! Currently story count is %2$s.',
                    'You can publish only %1$s stories! Currently story count is %2$s.',
                    $story_limit,
                    'socialv-api'
                ),
                $story_limit,
                $user_story_count
            ),
            "status_code"   => 422
        ];
    }

    if (!empty($args['sv-wpstory-story-parent'])) {
        $parent_id        = (int) wp_unslash($args['sv-wpstory-story-parent']);
        $user_item_count  = (array) wpstory_premium_helpers()->user_story_item_count($user->ID, $parent_id);
        $story_item_limit = wpstory_premium_helpers()->options('user_public_story_item_limit', 10);

        // Check story item count.
        if (!empty($story_item_limit) && count($user_item_count) > $story_item_limit) {
            return array(
                'message' => sprintf( /* translators: %s: story item limit */
                    _n('You can create only %s item per story!', 'You can create only %s items per story!', $story_item_limit, 'socialv-api'),
                    $story_item_limit
                ),
                "status_code"   => 422
            );
        }

        // Check user has privileges to append story to this story parent.
        if (!wpstory_premium_helpers()->user_can_manage_story($user->ID, $parent_id)) {
            return array('message' => 'error 1000', "status_code"   => 422);
        }

        $parent_title = get_the_title($parent_id);
        $parent_thumb = get_post_thumbnail_id($parent_id);
    } else {
        $parent_id = post_exists($args['sv-wpstory-highlight-title'], '', '', 'wpstory-public');

        if (!empty($args['sv-wpstory-highlight-title']) && $parent_id) {
            // $parent_id        = (int) wp_unslash($args['sv-wpstory-story-parent']);
            $user_item_count  = (array) wpstory_premium_helpers()->user_story_item_count($user->ID, $parent_id);
            $story_item_limit = wpstory_premium_helpers()->options('user_public_story_item_limit', 10);

            // Check story item count.
            if (!empty($story_item_limit) && count($user_item_count) > $story_item_limit) {
                return array(
                    'message' => sprintf( /* translators: %s: story item limit */
                        _n('You can create only %s item per story!', 'You can create only %s items per story!', $story_item_limit, 'socialv-api'),
                        $story_item_limit
                    ),
                    "status_code"   => 422
                );
            }

            // Check user has privileges to append story to this story parent.
            if (!wpstory_premium_helpers()->user_can_manage_story($user->ID, $parent_id)) {
                return array('message' => 'error 1000', "status_code"   => 422);
            }

            $parent_title = get_the_title($parent_id);
            $parent_thumb = get_post_thumbnail_id($parent_id);
        } else {
            $parent_id    = null;
            $parent_title = isset($args['sv-wpstory-highlight-title']) ? sanitize_text_field(wp_unslash($args['sv-wpstory-highlight-title'])) : '';
            $parent_thumb = isset($args['sv-wpstory-thumb-media-id']) && !empty($args['sv-wpstory-thumb-media-id']) ? wp_unslash((int) $args['sv-wpstory-thumb-media-id']) : null;
        }
    }

    $attachment_id = isset($args['sv-wpstory-item-media-id']) && !empty($args['sv-wpstory-item-media-id']) ? wp_unslash((int) $args['sv-wpstory-item-media-id']) : null;

    // If there is an attachment ID hack, abort ajax.
    if (empty($attachment_id) || 'attachment' !== get_post_type($attachment_id)) {
        return array('message' => 'error 1001', "status_code"   => 200);
    }

    // If there is an attachment ID hack, abort ajax.
    if (!empty($parent_thumb) && 'attachment' !== get_post_type($parent_thumb)) {
        return array('message' => 'error 1002', "status_code"   => 200);
    }

    if (!$parent_id) {
        $parent_id = wp_insert_post(
            array(
                'post_author' => $user->ID,
                'post_title'  => $parent_title,
                'post_type'   => 'wpstory-public',
                'post_status' => 'publish',
            )
        );

        if (is_wp_error($parent_id)) {
            wp_send_json_error(array('message' => esc_html__('Story can not be published. Try again later.', 'socialv-api')));
        }

        if (!empty($parent_thumb)) {
            set_post_thumbnail($parent_id, $parent_thumb);
        }
    }

    $link_text = isset($args['sv-wpstory-story-link-text']) && !empty($args['sv-wpstory-story-link-text']) ? sanitize_text_field(wp_unslash($args['sv-wpstory-story-link-text'])) : '';
    $link      = isset($args['sv-wpstory-story-link']) && !empty($args['sv-wpstory-story-link']) ? esc_url_raw(wp_unslash($args['sv-wpstory-story-link'])) : '';
    $duration  = isset($args['sv-wpstory-story-duration']) && !empty($args['sv-wpstory-story-duration']) ? wp_unslash((int) $args['sv-wpstory-story-duration']) : '';
    $status    = isset($args['status']) && !empty($args['status']) ?  $args['status'] : wpstory_premium_helpers()->options('user_publish_status', 'draft');

    if (!wpstory_premium_helpers()->options('allow_link', true)) {
        $link_text = '';
        $link      = '';
    }

    $post_object = array(
        'text'     => $link_text,
        'link'     => $link,
        'duration' => $duration > 0 ? $duration : wpstory_premium_helpers()->options('default_story_duration', 3),
        'image'    => array(
            'url'       => wp_get_attachment_url($attachment_id),
            'id'        => $attachment_id,
            'thumbnail' => wp_get_attachment_image_url($attachment_id, 'thumbnail', true),
        ),
    );
    $user_name = wpstory_premium_helpers()->get_user_name($user->ID);
    $story_id = wp_insert_post(
        array(
            'post_author' => $user->ID,
            'post_title'  => $user_name,
            'post_type'   => 'wpstory-public',
            'post_status' => $status,
            'meta_input'  => $post_object,
            'post_parent' => $parent_id,
        )
    );

    if (is_wp_error($story_id)) {
        return array('message' => esc_html__('Story can not be published. Try again later.', 'socialv-api'), "status_code" => 422);
    }

    // Attach uploaded images to story.
    wp_update_post(
        array(
            'ID'          => $attachment_id,
            'post_parent' => $story_id,
        )
    );

    $published_message = esc_html__('Story published!', 'socialv-api');

    if ('publish' !== $status) {
        $published_message = esc_html__('Your story sent for review!', 'socialv-api');
    }

    return array('message' => $published_message, "status_code" => 200);
}
function spcialv_set_story_as_seen($story_id, $key, $user_id)
{
    $exists = wp_cache_get($key . '-' . $story_id);
    if (!$exists)
        $exists =  get_post_meta($story_id, $key, true);

    if ($exists) {
        $exists[$user_id] = current_time('mysql');
    } else {
        $exists = [];
        $exists = [$user_id => current_time('mysql')];
    }

    if (update_post_meta($story_id, $key, $exists))
        return true;

    return false;
}

function socialv_is_seen($story_id, $key, $user_id)
{

    $exists = wp_cache_get($key . '-' . $story_id);
    if (!$exists)
        $exists =  get_post_meta($story_id, $key, true);

    if (!$exists) return false;

    wp_cache_set($key . '-' . $story_id, $exists);

    $exists = array_keys($exists);

    return in_array($user_id, $exists);
}

function socialv_get_story_seen_by($story_id, $key)
{
    $exists = wp_cache_get($key . '-' . $story_id);
    if (!$exists)
        $exists =  get_post_meta($story_id, $key, true);

    wp_cache_set($key . '-' . $story_id, $exists);

    return $exists;
}

function sv_story_instance()
{
    global $sv_story_instance;
    if ($sv_story_instance == null)
        $sv_story_instance = new SVStories();

    return $sv_story_instance;
}

function sv_story_permanent_delete($story_id)
{
    $is_deleted = wp_delete_post($story_id, true);
    if ($is_deleted) return true;
    else return false;
}
function sv_story_delete_status($story_id, $status)
{
    $is_changed = wp_update_post(
        array(
            'ID'          => $story_id,
            'post_status' => $status,
        )
    );

    return !is_wp_error($is_changed);
}
