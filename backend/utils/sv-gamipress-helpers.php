<?php
function sv_rank_requirements($parent = null)
{
    if (!$parent) return [];

    $args = [
        "post_status"       => "publish",
        "post_type"         => "rank-requirement",
        "post_parent"       => $parent,
        "posts_per_page"    => -1
    ];

    $rank_requirements = new WP_Query($args);
    if (!$rank_requirements->have_posts()) return [];

    $requirements = [];
    while ($rank_requirements->have_posts()) {
        $rank_requirements->the_post();
        $id = get_the_ID();
        $requirements[] = [
            "id"            => $id,
            "title"         => get_the_title(),
            "has_earned"    => gamipress_has_user_earned_rank($id, get_current_user_id())
        ];
    }

    wp_reset_postdata();
    return $requirements;
}

function sv_rest_badge_response($response)
{
    $user_id    = get_current_user_id();
    $badge_id   = $response->data['id'];

    $response->data['author_name']      = bp_core_get_user_displayname($response->data['author']);
    $response->data['is_user_verified'] = sv_is_user_verified($user_id);
    $response->data['has_earned']       = gamipress_has_user_earned_achievement($badge_id, $user_id);
    $response->data['image']      = has_post_thumbnail($badge_id) ? get_the_post_thumbnail_url($badge_id, "full") : "";

    return $response;
}
add_filter("rest_prepare_badge", "sv_rest_badge_response");

function sv_rest_levels_response($response)
{
    $user_id    = get_current_user_id();
    $level_id = $response->data['id'];

    $response->data['author_name']      = bp_core_get_user_displayname($response->data['author']);
    $response->data['is_user_verified'] = sv_is_user_verified($user_id);
    $response->data['image']      = has_post_thumbnail($level_id) ? get_the_post_thumbnail_url($level_id, "full") : "";
    $response->data['requirements']     = sv_rank_requirements($level_id);

    return $response;
}
add_filter("rest_prepare_levels", "sv_rest_levels_response");

function sv_gamipress_user_points($user_id)
{

    $types = gamipress_get_points_types();

    // Let's check if all types provided are wrong
    $all_types_wrong = true;
    foreach ($types as $slug => $type) {
        if (in_array($slug, gamipress_get_points_types_slugs())) {
            $all_types_wrong = false;
        }
    }

    // just notify error if all types are wrong
    if ($all_types_wrong) return [];

    // On network wide active installs, we need to switch to main blog mostly for posts permalinks and thumbnails
    $blog_id = gamipress_switch_to_main_site_if_network_wide_active();
    if (!gamipress_is_network_wide_active()) {
        // If we're polling all sites, grab an array of site IDs
        $sites = gamipress_get_network_site_ids();
    } else {
        // Otherwise, use only the current site
        $sites = array($blog_id);
    }

    // Loop through each site (default is current site only)
    $points = [];
    foreach ($sites as $site_blog_id) {

        // If we're not polling the current site, switch to the site we're polling
        $current_site_blog_id = get_current_blog_id();

        if ($current_site_blog_id != $site_blog_id) {
            /**
             * Filter to override shortcode output
             *
             * @since 1.6.5
             *
             * @param string    $output     Final output
             * @param array     $atts       Shortcode attributes
             * @param string    $content    Shortcode content
             */
            switch_to_blog($site_blog_id);
        }

        foreach ($types as $slug => $points_type) {

            $post_id = $points_type['ID'] ?? 0;
            $points[] = [
                "id"            => $post_id,
                "type"          => $slug,
                "singular_name" => $points_type["singular_name"],
                "plural_name"   => $points_type["plural_name"],
                "earnings"      => gamipress_get_user_points($user_id, $slug),
                "image"         => ($post_id && has_post_thumbnail($post_id)) ? get_the_post_thumbnail_url($post_id, "full") : ""
            ];
        }

        if ($current_site_blog_id != $site_blog_id && is_multisite()) {
            // Come back to current blog
            restore_current_blog();
        }
    }

    // If switched to blog, return back to que current blog
    if ($blog_id !== get_current_blog_id() && is_multisite()) {
        restore_current_blog();
    }

    return $points;
}

function sv_gamipress_user_ranks($user_id)
{
    $types = gamipress_get_rank_types();
    // On network wide active installs, we need to switch to main blog mostly for posts permalinks and thumbnails
    $blog_id = gamipress_switch_to_main_site_if_network_wide_active();

    // If we're polling all sites, grab an array of site IDs
    if (!gamipress_is_network_wide_active())
        $sites = gamipress_get_network_site_ids();
    // Otherwise, use only the current site
    else
        $sites = array(get_current_blog_id());

    // On network wide active installs, force to just loop main site
    if (gamipress_is_network_wide_active()) {
        $sites = array(get_main_site_id());
    }

    // Loop through each site (default is current site only)
    foreach ($sites as $site_blog_id) {

        // If we're not polling the current site, switch to the site we're polling
        $current_site_blog_id = get_current_blog_id();

        if ($current_site_blog_id != $site_blog_id) {
            switch_to_blog($site_blog_id);
        }
        foreach ($types as $slug => $rank_type) {
            $rank_id = gamipress_get_user_rank_id($user_id, $slug);
            $rank = [
                "id"    => $rank_id,
                "name"  => get_the_title($rank_id),
                "image" => ($rank_id && has_post_thumbnail($rank_id)) ? get_the_post_thumbnail_url($rank_id, "full") : ""
            ];
        }



        if ($current_site_blog_id != $site_blog_id && is_multisite()) {
            // Come back to current blog
            restore_current_blog();
        }
    }



    // If switched to blog, return back to que current blog
    if ($blog_id !== get_current_blog_id() && is_multisite()) {
        restore_current_blog();
    }

    /**
     * Filter to override shortcode output
     *
     * @since 1.6.5
     *
     * @param string    $output     Final output
     * @param array     $atts       Shortcode attributes
     * @param string    $content    Shortcode content
     */
    return $rank;
}
function sv_gamipress_user_achievements($user_id, $args = [])
{


    $query_args = wp_parse_args($args, array(
        // Achievements atts
        'type'              => 'all',
        'search'            => '',
        'page'              => 1,
        'posts_per_page'    => 10,
        'user_id'           => $user_id,
        'orderby'           => 'menu_order',
        'order'             => 'ASC',
        'include'           => '',
        'exclude'           => '',
    ));

    $query = sv_gamipress_achievements_query($query_args);

    /**
     * Filter to override shortcode output
     *
     * @since 1.6.5
     *
     * @param string    $output     Final output
     * @param array     $atts       Shortcode attributes
     * @param string    $content    Shortcode content
     */
    return $query;
}

function sv_gamipress_achievements_query($args = array())
{
    // Setup our AJAX query vars
    $type               = isset($args['type']) ? $args['type'] : false;
    $posts_per_page     = isset($args['posts_per_page']) ? $args['posts_per_page'] : false;
    $page               = isset($args['page']) ? $args['page'] : false;
    $search             = isset($args['search']) ? $args['search'] : false;
    $user_id            = isset($args['user_id']) ? $args['user_id'] : false;
    $orderby            = isset($args['orderby']) ? $args['orderby'] : false;
    $order              = isset($args['order']) ? $args['order'] : false;
    $include            = isset($args['include']) ? $args['include'] : array();
    $exclude            = isset($args['exclude']) ? $args['exclude'] : array();
    $showed_ids         = isset($args['showed_ids']) ? $args['showed_ids'] : array();
    $achievements       = [];

    // On network wide active installs, we need to switch to main blog mostly for posts permalinks and thumbnails
    $blog_id = gamipress_switch_to_main_site_if_network_wide_active();



    // Ensure user ID as int
    $user_id = absint($user_id);

    // Convert $type to properly support multiple achievement types
    $type = gamipress_get_achievement_types_slugs();


    // Prevent empty strings to be turned an array by explode()
    if (!is_array($include) && empty($include)) {
        $include = array();
    }

    // Build $exclude array
    if (!is_array($exclude) && empty($exclude)) {
        $exclude = array();
    }

    // Build $include array
    if (!is_array($include)) {
        $include = explode(',', $include);
    }

    // Build $exclude array
    if (!is_array($exclude)) {
        $exclude = explode(',', $exclude);
    }

    // Grab our hidden achievements (used to filter the query)
    $hidden = gamipress_get_hidden_achievement_ids($type);


    // Otherwise, use only the current site
    $sites = array(get_current_blog_id());


    // On network wide active installs, force to just loop main site
    if (gamipress_is_network_wide_active())
        $sites = array(get_main_site_id());

    // Loop through each site (default is current site only)
    foreach ($sites as $site_blog_id) {

        // If we're not polling the current site, switch to the site we're polling
        $current_site_blog_id = get_current_blog_id();

        if ($current_site_blog_id != $site_blog_id)
            switch_to_blog($site_blog_id);

        // Grab user earned achievements (used to filter the query)
        $earned_ids = gamipress_get_user_earned_achievement_ids($user_id, $type);
        if (empty($earned_ids))
            return [
                'achievements'  => [],
                'count'         => 0,
            ];
        // If filter is set to load earned achievements and user hasn't earned anything, don't need to continue

        // Query Achievements
        $query_args = array(
            'post_type'         => $type,
            'orderby'           => $orderby,
            'order'             => $order,
            'posts_per_page'    => $posts_per_page,
            'paged'             => absint($page),
            'post_status'       => 'publish',
            'post__in'          => $earned_ids,
            'post__not_in'      => array_diff($hidden, $earned_ids)
        );

        // Include certain achievements
        if (!empty($include)) {
            $query_args['post__not_in'] = array_diff($query_args['post__not_in'], $include);
            $query_args['post__in'] = array_merge($query_args['post__in'], $include);
        }

        // Exclude certain achievements
        if (!empty($exclude)) {
            $query_args['post__not_in'] = array_merge($query_args['post__not_in'], $exclude);
        }

        // Search
        if ($search) {
            $query_args['s'] = $search;
        }

        // Order By
        if (in_array($orderby, array('points_awarded', 'points_to_unlock'))) {
            $query_args['meta_key'] = ($orderby === 'points_awarded' ? '_gamipress_points' : '_gamipress_points_to_unlock');
            $query_args['orderby'] = 'meta_value_num';
        }

        // Process already displayed achievements
        if (!empty($showed_ids)) {
            // Exclude already displayed achievements
            $query_args['post__in'] = array_diff($query_args['post__in'], $showed_ids);
            $query_args['post__not_in'] = array_merge($query_args['post__not_in'], $showed_ids);
            // Offset not needed since displayed post are getting already excluded
            unset($query_args['offset']);
        }

        // Prevent to display posts excluded
        if (!empty($query_args['post__in']) && !empty($query_args['post__not_in'])) {
            $query_args['post__in'] = array_diff($query_args['post__in'], $query_args['post__not_in']);
        }

        // Query achievements
        $achievement_posts = new WP_Query($query_args);
        if (!$achievement_posts->have_posts()) return [];

        // Loop achievements found
        while ($achievement_posts->have_posts()) : $achievement_posts->the_post();
            // Render the achievement passing the template args
            $id = get_the_ID();
            $achievements[] = [
                "id" => $id,
                "name" => get_the_title(),
                "image"         => (has_post_thumbnail($id)) ? get_the_post_thumbnail_url($id, "full") : ""
            ];
        endwhile;
        wp_reset_postdata();

        // Display a message for no results
        if (empty($achievements))
            return [
                'achievements'  => [],
                'count'         => 0,
            ];


        // Come back to current blog
        if ($current_site_blog_id != $site_blog_id && is_multisite())
            restore_current_blog();
    }

    // If switched to blog, return back to que current blog
    if ($blog_id !== get_current_blog_id() && is_multisite())
        restore_current_blog();

    $query = array(
        'achievements'  => $achievements,
        'count'         => $achievement_posts->post_count,
    );

    /**
     * Filter the achievements query
     *
     * @since 1.6.5
     *
     * @param array $response
     * @param array $args
     *
     * @return array
     */
    return $query;
}
