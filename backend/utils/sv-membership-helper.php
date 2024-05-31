<?php
add_filter("pmpro_rest_api_permissions", function ($permission) {
    return true;
});
function sv_add_pmp_order_meta($order_id, $meta_key, $meta_value)
{
    global $wpdb;


    $sqlQuery = "INSERT INTO $wpdb->pmpro_membership_ordermeta
								(`pmpro_membership_order_id`, `meta_key`, `meta_value`)
								VALUES('" . esc_sql($order_id) . "',
									   '" . esc_sql($meta_key) . "',
									   '" . esc_sql($meta_value) . "' )";

    if ($wpdb->query($sqlQuery) !== false)
        return $wpdb->insert_id;

    return 0;
}

function st_get_pmp_order_meta($order_id, $meta_key = "", $single = false)
{
    global $wpdb;

    $sqlQuery = "SELECT meta_key, meta_value FROM $wpdb->pmpro_membership_ordermeta";
    $where = " WHERE pmpro_membership_order_id = $order_id";

    if (!empty(trim($meta_key)))
        $where .= " AND meta_key = '$meta_key'";

    $sqlQuery .= $where;

    $dbobj = $wpdb->get_results(
        $wpdb->prepare($sqlQuery)
    );

    if (!$dbobj) return "";

    $updatedArray = array_reduce($dbobj, function ($result, $item) {
        $result[$item->meta_key] = $item->meta_value;
        return $result;
    }, []);

    if ($single && count($updatedArray) == 1)
        return $updatedArray[$meta_key];

    return $updatedArray;
}

/**
 * Get all PMPro membership levels.
 *
 * @param bool $include_hidden Include levels marked as hidden/inactive.
 * @param bool $use_cache      If false, use $pmpro_levels global. If true use other caches.
 * @param bool $force          Resets the static var caches as well.
 */
function sv_pmpro_getAllLevels($user_id = false, $include_hidden = false, $use_cache = false, $force = true)
{
    global $pmpro_levels, $wpdb;

    static $pmpro_all_levels;            // every single level
    static $pmpro_visible_levels;        // every single level that's not hidden

    if ($force) {
        $pmpro_levels = NULL;
        $pmpro_all_levels = NULL;
        $pmpro_visible_levels = NULL;
    }

    // just use the $pmpro_levels global
    if (!empty($pmpro_levels) && !$use_cache) {
        return $pmpro_levels;
    }

    // If use_cache is true check if we have something in a static var.
    if ($include_hidden && isset($pmpro_all_levels)) {
        return $pmpro_all_levels;
    }
    if (!$include_hidden && isset($pmpro_visible_levels)) {
        return $pmpro_visible_levels;
    }

    // build query
    $sqlQuery = "SELECT * FROM $wpdb->pmpro_membership_levels ";
    if (!$include_hidden) {
        $sqlQuery .= ' WHERE allow_signups = 1 ORDER BY id';
    }

    // get levels from the DB
    $raw_levels = $wpdb->get_results($sqlQuery);
    if ($user_id)
        $user_levels = array_column(pmpro_getMembershipLevelsForUser($user_id, true), "ID");
    else
        $user_levels = false;
    // lets put them into an array where the key is the id of the level
    $pmpro_levels = array();
    foreach ($raw_levels as $raw_level) {
        $raw_level->initial_payment     = pmpro_round_price($raw_level->initial_payment);
        $raw_level->is_initial          = !$user_levels || !in_array($raw_level->id, $user_levels);
        $raw_level->billing_amount      = pmpro_round_price($raw_level->billing_amount);
        $raw_level->trial_amount        = pmpro_round_price($raw_level->trial_amount);
        $raw_level->bp_level_options    = sv_pmpro_bp_get_level_options($raw_level->id);
        $pmpro_levels[$raw_level->id]   = $raw_level;
    }

    // Store an extra cache specific to the include_hidden param.
    if ($include_hidden) {
        $pmpro_all_levels = $pmpro_levels;
    } else {
        $pmpro_visible_levels = $pmpro_levels;
    }
    return $pmpro_levels;
}

function sv_pmpro_bp_get_level_options($level_id)
{

    $default_options = array(
        'pmpro_bp_restrictions'             => -1, //Default to Lock All BuddyPress for non-members
        'pmpro_bp_group_creation'           => 0,
        'pmpro_bp_group_single_viewing'     => 0,
        'pmpro_bp_groups_page_viewing'      => 0,
        'pmpro_bp_groups_join'              => 0,
        'pmpro_bp_private_messaging'        => 0,
        'pmpro_bp_public_messaging'         => 0,
        'pmpro_bp_send_friend_request'      => 0,
        'pmpro_bp_member_directory'         => 0,
        'pmpro_bp_group_automatic_add'      => array(),
        'pmpro_bp_group_can_request_invite' => array(),
        'pmpro_bp_member_types'             => array()
    );

    if ($level_id == -1) {
        // defaults
        return $default_options;
    } elseif ($level_id == 0) {
        // non-member users
        $options = get_option('pmpro_bp_options_users', $default_options);
    } else {
        // level options
        $options = get_option('pmpro_bp_options_' . $level_id, $default_options);

        // might be set to mirror non-member users
        if ($options['pmpro_bp_restrictions'] == PMPROBP_USE_NON_MEMBER_SETTINGS) {
            $non_member_user_options                    = sv_pmpro_bp_get_level_options(0);
            $options['pmpro_bp_restrictions']           = $non_member_user_options['pmpro_bp_restrictions'];
            $options['pmpro_bp_group_creation']         = $non_member_user_options['pmpro_bp_group_creation'];
            $options['pmpro_bp_group_single_viewing']   = $non_member_user_options['pmpro_bp_group_single_viewing'];
            $options['pmpro_bp_groups_page_viewing']    = $non_member_user_options['pmpro_bp_groups_page_viewing'];
            $options['pmpro_bp_groups_join']            = $non_member_user_options['pmpro_bp_groups_join'];
            $options['pmpro_bp_private_messaging']      = $non_member_user_options['pmpro_bp_private_messaging'];
            $options['pmpro_bp_public_messaging']       = $non_member_user_options['pmpro_bp_public_messaging'];
            $options['pmpro_bp_send_friend_request']    = $non_member_user_options['pmpro_bp_send_friend_request'];
            $options['pmpro_bp_member_directory']       = $non_member_user_options['pmpro_bp_member_directory'];
        }
    }

    if ($options['pmpro_bp_restrictions'] == PMPROBP_GIVE_ALL_ACCESS) {
        $options['pmpro_bp_group_creation']         = 1;
        $options['pmpro_bp_group_single_viewing']   = 1;
        $options['pmpro_bp_groups_page_viewing']    = 1;
        $options['pmpro_bp_groups_join']            = 1;
        $options['pmpro_bp_private_messaging']      = 1;
        $options['pmpro_bp_public_messaging']       = 1;
        $options['pmpro_bp_send_friend_request']    = 1;
        $options['pmpro_bp_member_directory']       = 1;
    }

    if ($options['pmpro_bp_restrictions'] == PMPROBP_LOCK_ALL_ACCESS) {
        $options['pmpro_bp_group_creation']         = 0;
        $options['pmpro_bp_group_single_viewing']   = 0;
        $options['pmpro_bp_groups_page_viewing']    = 0;
        $options['pmpro_bp_groups_join']            = 0;
        $options['pmpro_bp_private_messaging']      = 0;
        $options['pmpro_bp_public_messaging']       = 0;
        $options['pmpro_bp_send_friend_request']    = 0;
        $options['pmpro_bp_member_directory']       = 0;
    }

    // Fill in defaults
    $options = array_merge($default_options, $options);

    return $options;
}

function user_level_response($levels, $user_id)
{
    if (!$levels) return $levels;

    if (!empty($levels->enddate)) {
        $levels->is_expired = strtotime(current_time( 'mysql' )) > $levels->enddate;
        return $levels;
    }

    $startingDate = $levels->startdate;
    if (!empty($levels->expiration_number)) {
        $expiration_number = $levels->expiration_number;
        $expiration_period = $levels->expiration_period;
        $enddate = date('Y-m-d', strtotime("+$expiration_number $expiration_period", $startingDate));
    } elseif (!empty($levels->cycle_number)) {
        $cycle_number = $levels->cycle_number;
        $cycle_period = $levels->cycle_period;
        $enddate = date('Y-m-d', strtotime("+$cycle_number $cycle_period", $startingDate));
    }

    $levels->enddate    = isset($enddate) ? strtotime($enddate) : null;
    $levels->is_expired = strtotime(current_time( 'mysql' )) > $levels->enddate;
    return $levels;
}
add_filter("pmpro_get_membership_level_for_user", "user_level_response", 10, 2);

function sv_get_pmp_orders($results)
{
    $orders = [];

    foreach ($results  as $order) {
        $billing = [
            "name"      => $order->billing_name,
            "street"    => $order->billing_street,
            "city"      => $order->billing_city,
            "state"     => $order->billing_state,
            "zip"       => $order->billing_zip,
            "country"   => $order->billing_country,
            "phone"     => $order->billing_phone
        ];

        $membership_level = pmpro_getLevel($order->membership_id);

        $orders[] = [
            "id"                            => $order->id,
            "code"                          => $order->code,
            "user_id"                       => $order->user_id,
            "membership_id"                 => $order->membership_id,
            "membership_name"               => $membership_level ? $membership_level->name : "",
            "discount_code"                 => st_get_pmp_order_meta($order->id,"discount_code",true),
            "billing"                       => $billing,
            "subtotal"                      => $order->subtotal,
            "tax"                           => $order->tax,
            "total"                         => $order->total,
            "payment_type"                  => $order->payment_type,
            "cardtype"                      => $order->cardtype,
            "accountnumber"                 => $order->accountnumber,
            "expirationmonth"               => $order->expirationmonth,
            "expirationyear"                => $order->expirationyear,
            "status"                        => $order->status,
            "gateway"                       => $order->gateway,
            "gateway_environment"           => $order->gateway_environment,
            "payment_transaction_id"        => $order->payment_transaction_id,
            "subscription_transaction_id"   => $order->subscription_transaction_id,
            "timestamp"                     => $order->timestamp,
            "affiliate_id"                  => $order->affiliate_id,
            "affiliate_subid"               => $order->affiliate_subid,
            "notes"                         => $order->notes,
            "checkout_id"                   => $order->checkout_id
        ];
    }
    return $orders;
}

function sv_change_membership_level($level_id, $user_id, $args = [])
{
    global $wpdb;

    $pmpro_level = pmpro_getLevel($level_id);
    $args = wp_parse_args(
        $args,
        ["discount_code" => ""]
    );

    $startdate = current_time("mysql");
    $startdate = apply_filters("pmpro_checkout_start_date", $startdate, $user_id, $pmpro_level);

    if (!empty($pmpro_level->expiration_number)) {
        if ($pmpro_level->expiration_period == 'Hour') {
            $enddate =  date("Y-m-d H:i:s", strtotime("+ " . $pmpro_level->expiration_number . " " . $pmpro_level->expiration_period, current_time("timestamp")));
        } else {
            $enddate =  date("Y-m-d 23:59:59", strtotime("+ " . $pmpro_level->expiration_number . " " . $pmpro_level->expiration_period, current_time("timestamp")));
        }
    } else {
        $enddate = "NULL";
    }

    $enddate = apply_filters("pmpro_checkout_end_date", $enddate, $user_id, $pmpro_level, $startdate);

    if (!empty($args["discount_code"])) {
        $discount_code_id = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql($args["discount_code"]) . "' LIMIT 1");
    } else {
        $discount_code_id = "";
    }

    $custom_level = array(
        'user_id'         => $user_id,
        'membership_id'   => $pmpro_level->id,
        'code_id'         => $discount_code_id,
        'initial_payment' => pmpro_round_price($pmpro_level->initial_payment),
        'billing_amount'  => pmpro_round_price($pmpro_level->billing_amount),
        'cycle_number'    => $pmpro_level->cycle_number,
        'cycle_period'    => $pmpro_level->cycle_period,
        'billing_limit'   => $pmpro_level->billing_limit,
        'trial_amount'    => pmpro_round_price($pmpro_level->trial_amount),
        'trial_limit'     => $pmpro_level->trial_limit,
        'startdate'       => $startdate,
        'enddate'         => $enddate
    );

    return pmpro_changeMembershipLevel($custom_level, $user_id, "changed");
}

function sv_discount_codes($result, $user_id = false)
{

    $result             = collect($result);
    $groupedCollection  = $result->groupBy('id');
    $resultCollection   = $groupedCollection->map(function ($group) use ($user_id) {
        $firstItem = $group->first();
        $discount_code_id = $firstItem->id;
        $firstItem = (object)[
            'id'        => (int) $discount_code_id,
            'code'      => $firstItem->code,
            'starts'    => $firstItem->starts,
            'expires'   => $firstItem->expires,
            'uses'      => (int) $firstItem->uses,
        ];

        $plans = $group->map(function ($item) use ($discount_code_id, $user_id) {
            return [
                'id'                => (int) $item->level_id,
                'initial_payment'   => (float) $item->initial_payment,
                'is_initial'        => sv_coupon_uses_count($discount_code_id, $user_id) ? false : true,
                'billing_amount'    => (float) $item->billing_amount,
                'cycle_number'      => (int) $item->cycle_number,
                'cycle_period'      => $item->cycle_period,
                'billing_limit'     => (int) $item->billing_limit,
                'trial_amount'      => (float) $item->trial_amount,
                'trial_limit'       => (int) $item->trial_limit,
                'expiration_number' => (int) $item->expiration_number,
                'expiration_period' => $item->expiration_period,
            ];
        });

        $firstItem->plans = $plans->values()->toArray();
        return $firstItem;
    });

    // Convert the result collection back to a plain array
    $resultArray = $resultCollection->values()->toArray();

    return $resultArray;
}

function sv_coupon_uses_count($discount_code_id, $user_id = false)
{
    global $wpdb, $coupon_uses_count;

    $query = "SELECT COUNT(*) AS uses_count
                FROM $wpdb->pmpro_discount_codes_uses
                WHERE code_id = $discount_code_id";

    if ($user_id)
        $query .= " AND user_id=$user_id";

    if (isset($coupon_uses_count[(string)$discount_code_id . "-" . (string)$user_id])) return $coupon_uses_count[$discount_code_id];

    $dcobj = $wpdb->get_row($wpdb->prepare($query), ARRAY_A);
    $coupon_uses_count = [];
    $coupon_uses_count[(string)$discount_code_id . "-" . (string)$user_id] = $dcobj["uses_count"] ?? true;

    return $coupon_uses_count[(string)$discount_code_id . "-" . (string)$user_id];
}
