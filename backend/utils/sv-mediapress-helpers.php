<?php

/**
 * Add remote media.
 *
 * @since 1.2.0
 *
 * @param int       $args['current_user_id']    Logged-in user ID.
 * @param string    $args['url']                Remote media URL.
 * @param string    $args['component']          Media component member/groups.
 * @param int       $args['component_id']       Member / Gorup ID.
 * @param int       $args['group_id']           If group component.
 * @param string    $args['context']            "activity".
 * 
 * @return array|string    array containing media and gallery ID or error message.
 */
function sv_add_remote_media($args)
{
    // Is remote enabled?
    if (!mpp_is_remote_enabled()) {

        return __('Invalid action.', 'socialv-api');
    }

    // Remote url.
    $url = isset($args['url']) ? trim($args['url']) : null;

    if (!$url) {
        return __('Please provide a valid url.', 'socialv-api');
    }

    // find the components we are trying to add for.
    $component    = isset($args['component']) ? trim($args['component']) : null;
    $component_id = isset($args['group_id']) ? absint($args['group_id']) : absint($args['current_user_id']);
    $context      = isset($args['context']) ? $args['context'] : 'activity';

    $context = mpp_get_upload_context(false, $context);

    // To allow posting on other member's wall, we will need to
    // change the component id to current user id if the context is activity.
    if ('activity' === $context && 'members' === $component) {
        $component_id = $args['current_user_id'];
    }
    if ('activity' === $context && 'groups' === $component) {
        $component_id = $args['group_id'];
    }
    // Check if MediaPress is enabled for this component/component id.
    if (!mpp_is_enabled($component, $component_id)) {
        return __('Sorry, the functionality is disabled temporarily.', 'socialv-api');
    }

    $remote_args = array();
    $size_info = mpp_get_media_size('large');
    $remote_args['width'] = isset($size_info['width']) ? $size_info['width'] : 600;

    $parser = new MPP_Remote_Media_Parser($url, $remote_args);
    // It is neither raw url, nor oembed, we can not handle it.
    if (!$parser->is_raw && !$parser->is_oembed) {
        return __('Sorry, can not add the url.', 'socialv-api');
    }

    $type = $parser->type;
    // Invalid media type?
    if (!$type || !mpp_component_supports_type($component, $type)) {
        return __('This type is not supported.', 'socialv-api');
    }

    // if we are here, the server can handle upload.
    $gallery_id = 0;

    if (isset($args['gallery_id'])) {
        $gallery_id = absint($args['gallery_id']);
    }

    // did the client send us gallery id? If yes, let us try to fetch the gallery object.
    if ($gallery_id) {
        $gallery = mpp_get_gallery($gallery_id);
    } else {
        // not set.
        $gallery = null;
    }

    // if there is no gallery type defined.
    // It wil happen in case of new gallery creation from admin page
    // we will set the gallery type as the type of the first media.
    if ($gallery && empty($gallery->type)) {
        // update gallery type
        // set it to media type.
        mpp_update_gallery_type($gallery, $type);
    }

    // fallback to fetch context based gallery, if gallery is not specified.
    // if there is no gallery id given, we may want to auto create the gallery
    // try fetching the available default gallery for the context.
    if (!$gallery) {
        // try fetching context gallery?
        $gallery = mpp_get_context_gallery(array(
            'component'    => $component,
            'component_id' => $component_id,
            'user_id'      => $args["current_user_id"],
            'type'         => $type,
            'context'      => $context,
        ));
    }

    if (!$gallery) {
        return  __('The gallery is not selected.', 'socialv-api');
    }

    // if we are here, It means we have found a gallery to upload
    // check if gallery has a valid status?
    $is_valid_status = mpp_is_active_status($gallery->status);
    if (!$is_valid_status) {
        $default_status = mpp_get_default_status();
        // Check and update status if applicable.
        if (mpp_is_active_status($default_status) && mpp_component_supports_status($component, $default_status)) {
            // the current gallery status is invalid,
            // update status to current default privacy.
            mpp_update_gallery_status($gallery, $default_status);
        } else {
            // should we inform user that we can't handle this request due to status issue?
            return __('There was a problem with the privacy of your gallery.', 'socialv-api');
        }
    }

    // detect media type of uploaded file here and then upload it accordingly.
    // also check if the media type uploaded and the gallery type matches or not.
    // let us build our response for javascript
    // if we are uploading to a gallery, check for type.
    // since we will be allowing upload without gallery too,
    // It is required to make sure $gallery is present or not.
    if (!mpp_is_mixed_gallery($gallery) && $type !== $gallery->type) {
        // if we are uploading to a gallery and It is not a mixed gallery, the media type must match the gallery type.
        return sprintf(__('This type is not allowed in current gallery. Only %s is allowed!', 'socialv-api'), mpp_get_type_singular_name($type));
    }

    // If gallery is given, reset component and component_id to that of gallery's.
    if ($gallery) {
        $gallery_id = $gallery->id;
        // reset component and component_id
        // if they are set on gallery.
        if (!empty($gallery->component) && mpp_is_active_component($gallery->component)) {
            $component = $gallery->component;
        }

        if (!empty($gallery->component_id)) {
            $component_id = $gallery->component_id;
        }
    }


    // if we are here, all is well :).
    if (!mpp_user_can_add_remote_media($component, $component_id, $gallery)) {

        $error_message = apply_filters('mpp_remote_media_add_permission_denied_message', __("You don't have sufficient permissions.", 'socialv-api'), $component, $component_id, $gallery);

        return $error_message;
    }

    $status = isset($args['media_status']) ? $args['media_status'] : '';

    if (empty($status) && $gallery) {
        // inherit from parent,gallery must have an status.
        $status = $gallery->status;
    }

    // we may need some more enhancements here.
    if (!$status) {
        $status = mpp_get_default_status();
    }

    if (!mpp_is_active_status($status) || !mpp_component_supports_status($component, $status)) {
        // The status must be valid and supported by current component.
        // else we won't process upload.
        return __('There was a problem with the privacy.', 'socialv-api');
    }

    // Do the actual handling of media.
    if ($parser->is_oembed) {
        $info = sv_handle_oembed($parser, $gallery_id);
    } else {
        $info = sv_handle_raw($parser, $gallery_id);
    }

    if (is_wp_error($info)) {
        return $info->get_error_message();
    }


    if (empty($info['content']) && !empty($args['media_description'])) {
        $info['content'] = $args['media_description'];
    }

    $is_orphan = 0;
    // Any media uploaded via activity is marked as orphan
    // Orphan means not associated with the mediapress unless the activity to which it was attached is actually created,
    // check core/activity/actions.php to see how the orphaned media is adopted by the activity :).


    $is_remote = isset($info['is_remote']) ? $info['is_remote'] : 0;
    $is_oembed = isset($info['is_oembed']) ? $info['is_oembed'] : 0;

    $media_data = array(
        'title'          => $info['title'],
        'description'    => $info['content'],
        'gallery_id'     => $gallery_id,
        'user_id'        => get_current_user_id(),
        'type'           => $type,
        'mime_type'      => $info['mime_type'],
        'src'            => $info['file'],
        'url'            => $info['url'],
        'embed_html'     => $info['html'],
        'embed_url'      => $parser->url,
        'status'         => $status,
        'comment_status' => 'open',
        'storage_method' => isset($info['storage_method']) ? $info['storage_method'] : '',
        'component_id'   => $component_id,
        'component'      => $component,
        'context'        => $context,
        'is_remote'      => $is_remote,
        'is_oembed'      => $is_oembed,
        'is_orphan'      => $is_orphan,
        'is_raw'         => isset($info['is_raw']) ? $info['is_raw'] : 0,
        'source'         => $url,
    );

    $id = mpp_add_media($media_data);
    if (!$id) {
        return __('There was a problem. Please try again.', 'socialv-api');
    }

    // if the media is not uploaded from activity and auto publishing is not enabled,
    // record as unpublished.
    if ('activity' !== $context && !mpp_is_auto_publish_to_activity_enabled('add_media')) {
        mpp_gallery_add_unpublished_media($gallery_id, $id);
    }

    mpp_gallery_increment_media_count($gallery_id);
    // For Remote Media, we will send a lighter response.
    return array(
        'media_id'      => $id,
        "gallery_id"    => $gallery_id
    );
}

function sv_handle_oembed(MPP_Remote_Media_Parser $parser, $gallery_id)
{

    if (!$parser || !$parser->data) {
        return new WP_Error('invalid_embed', __('Invalid media.', 'mediapress'));
    }

    if (!mpp_is_oembed_enabled()) {
        return new WP_Error('not_supported', __('Not supported.', 'mediapress'));
    }

    $info = array(
        'title'     => isset($parser->title) ? $parser->title : '',
        'content'   => '',
        'file'      => '',
        'url'       => '',
        'mime_type' => '',
        'is_remote' => 1,
        'is_raw'    => 0,
        'is_oembed' => 1,
    );

    switch ($parser->type) {

        case 'photo':
            $info['mime_type'] = 'photo/x-embed';
            $info['is_oembed'] = 0; // we are linking to raw url.
            $html              = $parser->get_html();
            if (!$html) {
                return new WP_Error('no_data', __('There was an issue, please try again.', 'mediapress'));
            }

            $info['html']       = $html;
            $info['gallery_id'] = $gallery_id;

            return sv_process_media($parser->data->url, $info);

            break;

        case 'video':
            $info['mime_type'] = 'video/x-embed';
            $html              = $parser->get_html();
            if (!$html) {
                return new WP_Error('no_data', __('There was an issue, please try again.', 'mediapress'));
            }
            $info['html'] = $html;

            break;
    }

    if (empty($info['title'])) {
        $info['title'] = !empty($parser->data->author_name) ? $parser->data->author_name : __('Video', 'mediapress');
    }
    return $info;
}

/**
 * Process raw remote media.
 *
 * @param MPP_Remote_Media_Parser $parser importer.
 * @param int                     $gallery_id gallery id.
 *
 * @return array|WP_Error
 */
function sv_handle_raw(MPP_Remote_Media_Parser $parser, $gallery_id)
{

    if (!mpp_is_remote_file_enabled()) {
        return new WP_Error('not_supported', __('Not supported.', 'mediapress'));
    }

    return sv_process_media($parser->url, array(
        'title'      => $parser->title,
        'gallery_id' => $gallery_id,
    ));
}

/**
 * Process a raw remote media.
 *
 * @param string $url raw file url.
 * @param array  $args args.
 *
 * @return array|WP_Error
 */
function sv_process_media($url, $args)
{

    $gallery_id = $args['gallery_id'];
    unset($args['gallery_id']);

    $info = wp_parse_args($args, array(
        'title'     => '',
        'content'   => '',
        'file'      => '',
        'url'       => '',
        'mime_type' => '',
        'is_remote' => 1,
        'is_raw'    => 1,
        'is_oembed' => 0,
        'html'      => '',
    ));

    // Do wee download remote media?
    $download_remote_media = mpp_is_remote_file_download_enabled();
    // get the uploader.
    // should we pass the component?
    // should we check for the existence of the default storage method?
    $uploader = mpp_get_storage_manager();
    $gallery  = mpp_get_gallery($gallery_id);
    // check if the server can handle the upload?
    if ($download_remote_media && !$uploader->can_handle()) {
        return __('Server can not handle this much amount of data. Please upload a smaller file or ask your server administrator to change the settings.', 'socialv-api');
    }

    // check if the user has available storage for his profile
    // or the component gallery(component could be groups, sitewide).
    if ($download_remote_media && !mpp_has_available_space($gallery->component, $gallery->component_id)) {
        return __('Unable to upload. You have used the allowed storage quota!', 'socialv-api');
    }

    if (!$download_remote_media) {
        $info['is_remote'] = 1;
        $info['is_raw']    = 1;
        $info['url']       = $url;

        // mime type and all?
        return $info;
    }

    $uploaded = $uploader->import_url($url, $gallery_id);

    if (isset($uploaded['error'])) {
        return new WP_Error('upload_error', $uploaded['error']);
    }

    $info['mime_type'] = $uploaded['type'];
    $info['file']      = $uploaded['file'];
    $info['url']       = $uploaded['url'];
    $info['is_remote'] = 0;
    $info['is_raw']    = 0;

    return $info;
}

function socialv_add_media($media, $user_id, $args = [])
{
    $file = $media;

    if (mpp_get_file_extension($file["_mpp_file"]["name"]) == "mov") {
        $file["_mpp_file"]["type"] = "video/mp4";
        $file["_mpp_file"]["name"] = str_replace(".mov", ".mp4", $media["_mpp_file"]["name"]);
    }

    // input file name, set via the mpp.Uploader
    // key name in the files array.
    $file_id = '_mpp_file';

    // find the components we are trying to add for.
    $component    = isset($args['component']) ? trim($args['component']) : "members";
    $component_id = isset($args['post_in']) && $args['post_in'] ? absint($args['post_in']) : $user_id;
    $context      = isset($args['context']) ? $args['context'] : 'activity';

    $context      = mpp_get_upload_context(false, $context);

    // Check if MediaPress is enabled for this component/component id.
    if (!mpp_is_enabled($component, $component_id)) {
        wp_send_json_error(array(
            'message' => esc_html__('Sorry, the upload functionality is disabled temporarily.', 'socialv-api'),
        ));
    }

    // get the uploader.
    $uploader = mpp_get_storage_manager();

    // check if the server can handle the upload?
    if ($file[$file_id]['size'] > wp_max_upload_size()) {
        wp_send_json_error(array(
            'message' => esc_html__('Server can not handle this much amount of data. Please upload a smaller file or ask your server administrator to change the settings.', 'socialv-api'),
        ));
    }

    // check if the user has available storage for his profile
    // or the component gallery(component could be groups, sitewide).
    if (!socialv_has_available_space($component, $component_id)) {
        wp_send_json_error(array(
            'message' => esc_html__('Unable to upload. You have used the allowed storage quota!', 'socialv-api'),
        ));
    }
    // if we are here, the server can handle upload.
    $gallery_id = 0;

    if (isset($args['gallery_id'])) {
        $gallery_id = absint($args['gallery_id']);
    }

    // did the client send us gallery id? If yes, let us try to fetch the gallery object.
    if ($gallery_id) {
        $gallery = mpp_get_gallery($gallery_id);
    } else {
        // not set.
        $gallery = null;
    }

    // get media type from file extension.
    $media_type = mpp_get_media_type_from_extension(mpp_get_file_extension($file[$file_id]['name']));
    // Invalid media type?
    if (!$media_type || !mpp_component_supports_type($component, $media_type)) {
        wp_send_json_error(array('message' => esc_html__('This file type is not supported.', 'socialv-api')));
    }
    if ($gallery && empty($gallery->type)) {
        // update gallery type
        // set it to media type.
        mpp_update_gallery_type($gallery, $media_type);
    }


    if (!$gallery) {
        // try fetching context gallery?
        $gallery = mpp_get_context_gallery(array(
            'component'    => $component,
            'component_id' => $component_id,
            'user_id'      => $user_id,
            'type'         => $media_type,
            'context'      => $context,
        ));
    }

    if (!$gallery) {
        wp_send_json_error(array('message' => esc_html__('The gallery is not selected.', 'socialv-api')));
    }

    // if we are here, It means we have found a gallery to upload
    // check if gallery has a valid status?
    $is_valid_status = mpp_is_active_status($gallery->status);
    if (!$is_valid_status) {
        $default_status = mpp_get_default_status();
        // Check and update status if applicable.
        if (mpp_is_active_status($default_status) && mpp_component_supports_status($component, $default_status)) {
            // the current gallery status is invalid,
            // update status to current default privacy.
            mpp_update_gallery_status($gallery, $default_status);
            $gallery->status = $default_status;
        } else {
            // should we inform user that we can't handle this request due to status issue?
            wp_send_json_error(array('message' => esc_html__('There was a problem with the privacy of your gallery.', 'socialv-api')));
        }
    }


    // we may want to check the upload type and set the gallery to activity gallery etc if it is not set already.
    $error = false;


    if (!mpp_is_mixed_gallery($gallery) && $media_type !== $gallery->type) {
        // if we are uploading to a gallery and It is not a mixed gallery, the media type musesc_html__("Error in upload") match the gallery type.
        wp_send_json_error(array(
            'message' => sprintf(esc_html__('This file type is not allowed in current gallery. Only <strong>%s</strong> files are allowed!', 'socialv-api'), mpp_get_allowed_file_extensions_as_string($gallery->type)),
        ));
    }

    // If gallery is given, reset component and component_id to that of gallery's.
    if ($gallery) {
        $gallery_id = $gallery->id;
        // reset component and component_id
        // if they are set on gallery.
        if (!empty($gallery->component) && mpp_is_active_component($gallery->component)) {
            $component = $gallery->component;
        }

        if (!empty($gallery->component_id)) {
            $component_id = $gallery->component_id;
        }
    }

    // if we are here, all is well :).
    if (!socialv_user_can_upload($user_id, $component, $component_id, $gallery)) {

        $error_message = apply_filters('mpp_upload_permission_denied_message', esc_html__("You don't have sufficient permissions to upload.", 'socialv-api'), $component, $component_id, $gallery);

        wp_send_json_error(array('message' => $error_message));
    }


    $status = isset($args['media_status']) ? $args['media_status'] : '';

    if (empty($status) && $gallery) {
        // inherit from parent,gallery must have an status.
        $status = $gallery->status;
    }

    // we may need some more enhancements here.
    if (!$status) {
        $status = mpp_get_default_status();
    }

    if (!mpp_is_active_status($status) || !mpp_component_supports_status($component, $status)) {
        // The status must be valid and supported by current component.
        // else we won't process upload.
        wp_send_json_error(array('message' => esc_html__('There was a problem with the privacy.', 'socialv-api')));
    }
    // if we are here, we have checked for all the basic errors, so let us just upload now.
    $uploaded = $uploader->upload($file, array(
        'file_id'      => $file_id,
        'gallery_id'   => $gallery_id,
        'component'    => $component,
        'component_id' => $component_id,
    ));

    // upload was successful?
    if (!isset($uploaded['error'])) {

        // file was uploaded successfully.
        if (apply_filters('mpp_use_processed_file_name_as_media_title', false)) {
            $title = wp_basename($uploaded['file']);
        } else {
            $title = wp_basename($file[$file_id]['name']);
        }

        $title_parts = pathinfo($title);
        $title       = trim(substr($title, 0, - (1 + strlen($title_parts['extension']))));

        $url  = $uploaded['url'];
        $type = $uploaded['type'];
        $file = $uploaded['file'];


        $content = isset($args['media_description']) ? $args['media_description'] : '';

        $is_orphan = 0;

        $media_data = array(
            'title'          => $title,
            'description'    => $content,
            'gallery_id'     => $gallery_id,
            'user_id'        => $user_id,
            'is_remote'      => false,
            'type'           => $media_type,
            'mime_type'      => $type,
            'src'            => $file,
            'url'            => $url,
            'status'         => $status,
            'comment_status' => 'open',
            'storage_method' => mpp_get_storage_method(),
            'component_id'   => $component_id,
            'component'      => $component,
            'context'        => $context,
            'is_orphan'      => $is_orphan,
        );

        $id = mpp_add_media($media_data);

        if (!$id) return esc_html__("Error in upload", "socialv-api");
        // if the media is not uploaded from activity and auto publishing is not enabled,
        // record as unpublished.
        if ('activity' !== $context && !mpp_is_auto_publish_to_activity_enabled('add_media')) {
            mpp_gallery_add_unpublished_media($gallery_id, $id);
        }

        mpp_gallery_increment_media_count($gallery_id);

        return ["media_id" => $id, "gallery_id" => $gallery_id];
    } else {

        return esc_html__("Error in upload", "socialv-api");
    }
}

/**
 * Create gallery album
 * @since 1.2.0
 *
 * @param int       $args['user_id']        Current (logged-in) user ID.
 * @param string    $args['component']      Component of gallery - members/groups.
 * @param int       $args['group_id']       If component is groups.
 * @param string    $args['title']          Title of gallery.
 * @param string    $args['description']    Description of gallery.
 * @param string    $args['type']           Type of gallery - photo/video/audio/doc...
 * @param string    $args['status']         Status of gallery - public/private/loggedin.
 * 
 * @return array gallery ID, error|success message.
 */
function sv_action_create_gallery($args)
{

    do_action("sv_rest_before_gallery_created", $args);
    // update it to allow passing component/id from the form.
    if (!isset($args['component']) || empty($args['component']))
        return  [0, __("Component undefined", 'socialv-api'), 422];

    $component          = $args['component']; // members/groups
    $user_id            = $args['user_id'];
    $component_id       = $component == "groups" ? $args["group_id"] : $user_id;
    // check for permission
    // we may want to allow passing of component from the form in future!
    if (!mpp_user_can_create_gallery($component, $component_id)) {
        return  [0, __("You don't have permission to create gallery!", 'socialv-api'), 422];
    }

    $title          = $args['title'];
    $description    = $args['description'];
    $type           = $args['type']; // photo/video/audio/doc...
    $status         = $args['status'];

    // if we are here, validate the data and let us see if we can create.
    if (!mpp_is_active_status($status))
        return [0, __('Invalid gallery status!', 'socialv-api'), 422];

    if (!mpp_is_active_type($type))
        return [0, __('Invalid gallery type!', 'socialv-api'), 422];

    // check for current component.
    if (!mpp_is_enabled($component, $component_id))
        return [0, __('Invalid action!', 'socialv-api'), 422];

    if (empty($title))
        return [0, __('Title can not be empty', 'socialv-api'), 422];

    // let us create gallery.
    $description = empty($description) ? " " : $description;
    $g_args = array(
        'title'        => $title,
        'description'  => $description,
        'type'         => $type,
        'status'       => $status,
        'creator_id'   => $user_id,
        'component'    => $component,
        'component_id' => $component_id,
    );

    $gallery_id = mpp_create_gallery($g_args);

    if (!$gallery_id)
        return [0, __('Unable to create gallery!', 'socialv-api'), 422];

    do_action("sv_rest_after_gallery_created", $gallery_id, $g_args);
    // if we are here, the gallery was created successfully,
    return [$gallery_id, __('Gallery created successfully!', 'socialv-api'), 200];
}

function sv_get_albums_query_args($parameters)
{

    $args = [
        "post_type"         => "mpp-gallery",
        "status"            => "publish",
        "paged"             => $parameters["page"],
        "posts_per_page"    => $parameters["per_page"]
    ];

    $current_user_id    = $parameters["current_user_id"];
    $user_id            = (int) $parameters["user_id"];
    $type               = $parameters["type"];
    $is_current_user    = ($current_user_id == $user_id);

    if (!empty($parameters["group_id"])) {
        $component      = "groups";
        $component_id   = $parameters["group_id"];
        $status         = ["public", "loggedin"];
        if (groups_is_user_member($current_user_id, $component_id))
            $status[]   = "groupsonly";
        if ("my-gallery" == $type)
            $args["author"]   = $current_user_id;
    } else {
        $component      = "members";
        $component_id   = $user_id;
        $status         = ["public", "loggedin"];
        if ($is_current_user) {
            $status[]   = "private";
            $status[]   = "friendsonly";
        }
        if (friends_check_friendship($current_user_id, $user_id))
            $status[]   = "friendsonly";
    }

    $status = mpp_string_to_array($status);
    $status = mpp_get_tt_ids($status, mpp_get_status_taxname());

    $tax_query[] = [
        'taxonomy' => mpp_get_status_taxname(),
        'field'    => 'term_taxonomy_id',
        'terms'    => $status,
        'operator' => 'IN',
    ];

    if (!empty($type) && mpp_are_registered_types($type)) {

        $type = mpp_string_to_array($type);
        $type = mpp_get_tt_ids($type, mpp_get_type_taxname());

        $tax_query[] = array(
            'taxonomy' => mpp_get_type_taxname(),
            'field'    => 'term_taxonomy_id',
            'terms'    => $type,
            'operator' => 'IN',
        );
    }
    if (!empty($component) && mpp_are_registered_components($component)) {

        $component = mpp_string_to_array($component);
        $component = mpp_get_tt_ids($component, mpp_get_component_taxname());

        $tax_query[] = array(
            'taxonomy' => mpp_get_component_taxname(),
            'field'    => 'term_taxonomy_id',
            'terms'    => $component,
            'operator' => 'IN',
        );
    }
    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }
    $args['tax_query'] = $tax_query;

    if (!empty($component_id)) {
        $meta_compare = '=';

        if (is_array($component_id)) {
            $meta_compare = 'IN';
        }

        $gmeta_query[] = array(
            'key'     => '_mpp_component_id',
            'value'   => $component_id,
            'compare' => $meta_compare,
            'type'    => 'UNSIGNED',
        );
    }

    // reset meta query.
    if (!empty($gmeta_query)) {
        $args['meta_query'] = $gmeta_query;
    }

    return $args;
}

/**
 * Fetch gallery album
 * @since 1.2.0
 *
 * @param int       $parameters['current_user_id']    Current (logged-in) user ID.
 * @param int       $parameters['user_id']            User ID of whos albums will get fetch.
 * @param int       $parameters['group_id']           If gorup albums.
 * @param string    $parameters['type']               Type of gallery - photo/video/audio/doc...
 * @param int       $parameters['per_page']           Total number of albums per page.
 * @param int       $parameters['page']               Current page number.
 * 
 * @return array List of albums or empty array.
 */
function sv_get_albums($parameters)
{
    $parameters = wp_parse_args(
        $parameters,
        [
            $parameters["user_id"]  => $parameters["current_user_id"],
            $parameters["type"]     => '',
            $parameters["page"]     => 1,
            $parameters["per_page"] => 20
        ]
    );


    $args = sv_get_albums_query_args($parameters);
    $galleries = new WP_Query($args);
    $albums = [];
    $statuses = mpp_get_active_statuses();
    if ($galleries->have_posts()) {
        while ($galleries->have_posts()) {
            $galleries->the_post();

            $post_id    = get_the_ID();
            $type       = wp_get_post_terms($post_id, mpp_get_type_taxname());
            $status     = wp_get_post_terms($post_id, mpp_get_status_taxname());
            $thumbnail  = mpp_get_gallery_cover_src('thumbnail', $post_id);
            $albums[] = [
                "id"            => $post_id,
                "name"          => get_the_title(),
                "description"   => get_the_content(),
                "thumbnail"     => $thumbnail ? $thumbnail : '',
                "type"          => isset($type[0]->name) ? trim($type[0]->slug, "_") : "",
                "status"        => isset($status[0]->slug) ? $statuses[trim($status[0]->slug, "_")] : "",
                "can_delete"    => mpp_user_can_delete_gallery($post_id, $parameters["current_user_id"])
            ];
        }
        wp_reset_postdata();
    }
    return $albums;
}

/**
 * Fetch album media list
 * @since 1.2.0
 *
 * @param int       $parameters['current_user_id']    Current (logged-in) user ID.
 * @param int       $parameters['gallery_id']         Gallery ID to fetch media list.
 * @param int       $parameters['per_page']           Total number of albums per page.
 * @param int       $parameters['page']               Current page number.
 * 
 * @return array List of media for perticular gallery  or empty array.
 */
function sv_get_album_media_list($parameters)
{
    $parameters = wp_parse_args(
        $parameters,
        [
            $parameters["user_id"]      => $parameters["current_user_id"],
            $parameters["gallery_id"]   => 0,
            $parameters["page"]         => 1,
            $parameters["per_page"]     => 20,
        ]
    );

    $gallery_id = $parameters["gallery_id"];
    if (!$gallery_id) return [];

    $args = [
        'gallery_id'    => $gallery_id,
        'per_page'      => $parameters["per_page"],
        'page'          => $parameters["page"]
    ];

    $mppq = new MPP_Media_Query($args);

    if (!$mppq->have_media()) return [];

    $media_list = [];

    while ($mppq->have_media()) : $mppq->the_media();

        $media_id           = (int) mpp_get_media_id();
        $oembed_source      = get_post_meta($media_id, "_mpp_oembed_content", true);
        $attachment_source  = get_post_meta($media_id, "_mpp_source", true);
        $data["id"]         = $media_id;

        if (!empty($attachment_source) && (strpos($attachment_source, "youtube") || strpos($attachment_source, "youtu.be"))) {
            $url            = $attachment_source;
            $media_type     = mpp_get_media_type($media_id);
            $data["source"] = "youtube";
        } else if ($oembed_source) {
            $url            = $oembed_source;
            $media_type     = "oembed";
        } else {
            $url            = wp_get_attachment_url($media_id);
            $media_type     = mpp_get_media_type($media_id);
        }

        $data["url"]            = $url;
        $data["type"]           = $media_type;
        $data["gallery_id"]     = $gallery_id;
        $data["can_delete"]     = mpp_user_can_delete_media($media_id, $parameters["current_user_id"]);

        $media_list[] = $data;

    endwhile;

    return $media_list;
}
