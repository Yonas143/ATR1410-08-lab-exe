<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\SVBase;
use Includes\settings\Options\SVMembership;
use MemberOrder;
use PMProEmail;
use stdClass;
use WP_REST_Server;

class SVMembershipController extends SVBase
{

	public $module = 'membership';

	public $nameSpace;

	function __construct()
	{

		$this->nameSpace = SOCIALV_API_NAMESPACE;

		add_action('rest_api_init', function () {

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/billing-address',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [$this, 'get_billing_address'],
						'permission_callback' => '__return_true',
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => [$this, 'change_billing_address'],
						'permission_callback' => '__return_true',
					)
				)
			);

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/levels',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'membership_levels'],
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/bp-restrictions',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'bp_membership_restrictions'],
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/discount-codes',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'discount_codes'],
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/orders',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'order_list'],
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/order',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this, 'generate_order'],
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				$this->nameSpace . '/api/v1/' . $this->module,
				'/payment-gateways',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this, 'payment_gateways'],
					'permission_callback' => '__return_true',
				)
			);
		});
	}

	public function change_billing_address($request)
	{
		$data = svValidationToken($request);

		if (!$data['status']) {
			return comman_custom_response($data, 401);
		}

		$user_id = $data['user_id'];

		$parameters = $request->get_params();
		$parameters = svRecursiveSanitizeTextField($parameters);


		$billing_data = [
			"pmpro_bfirstname" 	=>	$parameters["first_name"],
			"pmpro_blastname" 	=>	$parameters["last_name"],
			"pmpro_baddress1" 	=>	$parameters["user_address"],
			"pmpro_bcity" 		=>	$parameters["user_city"],
			"pmpro_bstate" 		=>	$parameters["user_state"],
			"pmpro_bzipcode" 	=>	$parameters["user_postal_code"],
			"pmpro_bcountry" 	=>	$parameters["user_country"],
			"pmpro_bphone" 		=>	$parameters["user_phone"],
			"pmpro_bemail" 		=>	$parameters["user_email"]
		];

		foreach ($billing_data as $meta_key => $meta_value) {
			update_user_meta($user_id, $meta_key, $meta_value);
		}
		// if ($error)
		// 	return comman_message_response(__("Something Wrong. Some of the value are same or not has been updated.", SOCIALV_API_TEXT_DOMAIN), 400);

		return comman_custom_response([
            "status" => true,
            "message" =>  __("Billing address has been updated.", SOCIALV_API_TEXT_DOMAIN)
        ]);
	}

	public function get_billing_address($request)
	{
		$data = svValidationToken($request);

		if (!$data['status']) {
			return comman_custom_response($data, 401);
		}

		$user_id = $data['user_id'];

		$billing_data = [
			'first_name' 		=> "pmpro_bfirstname",
			'last_name' 		=> "pmpro_blastname",
			'user_email' 		=> "pmpro_bemail",
			'user_phone' 		=> "pmpro_bphone",
			'user_address' 		=> "pmpro_baddress1",
			'user_city' 		=> "pmpro_bcity",
			'user_state' 		=> "pmpro_bstate",
			'user_postal_code' 	=> "pmpro_bzipcode",
			'user_country' 		=> "pmpro_bcountry"
		];

		$billing_address = [];
		foreach ($billing_data as $key => $meta_key) {
			$billing_address[$key] = get_user_meta($user_id, $meta_key, true);
		}

		return comman_custom_response([
            "status" => true,
            "message" =>  __("Billing address.", SOCIALV_API_TEXT_DOMAIN),
            "data" => $billing_address
        ]);
	}

	public function membership_levels($request)
	{

		$data 		= svValidationToken($request);
		$user_id 	= $data['user_id'] ?? 0;

		$levels 	= sv_pmpro_getAllLevels($user_id);

		if (empty($levels))
			return comman_custom_response([
				"status" => false,
				"message" =>  __("No membership plans found.", SOCIALV_API_TEXT_DOMAIN),
				"data" => []
			]);

		return comman_custom_response([
            "status" => true,
            "message" =>  __("Available list of membership plans.", SOCIALV_API_TEXT_DOMAIN),
            "data" => array_values($levels)
        ]);
	}

	public function bp_membership_restrictions($request)
	{
		$parameters = $request->get_params();
		$level_id = $parameters["level_id"] ?? 0;

		$get_level_options = sv_pmpro_bp_get_level_options($level_id);
		if (empty($get_level_options))
			return comman_custom_response([
				"status" => false,
				"message" =>  __("No options found.", SOCIALV_API_TEXT_DOMAIN)
			]);

		return comman_custom_response([
            "status" => true,
            "message" =>  __("Available Options.", SOCIALV_API_TEXT_DOMAIN),
            "data" => $get_level_options
        ]);
	}

	public function discount_codes($request)
	{
		global $wpdb;
		$data 		= svValidationToken($request);
		$user_id 	= $data['user_id'] ?? 0;

		$level_id = (int) sanitize_text_field($request->get_param("level_id"));

		$on = "ON codes.id = code_levels.code_id";
		if ($level_id)
			$on .= " AND code_levels.level_id = $level_id ";

		$query = "SELECT codes.*, code_levels.*
					FROM $wpdb->pmpro_discount_codes AS codes
					JOIN $wpdb->pmpro_discount_codes_levels AS code_levels
					$on
					LEFT JOIN (
						SELECT code_id, COUNT(*) AS usage_count
						FROM $wpdb->pmpro_discount_codes_uses
						GROUP BY code_id
					) AS code_uses
					ON codes.id = code_uses.code_id
					WHERE codes.expires >= CURRENT_DATE
					AND (code_uses.usage_count IS NULL OR code_uses.usage_count < codes.uses OR codes.uses = 0)";

		// Get the discount code object.
		$dcobj = $wpdb->get_results($query, OBJECT);

		if (!$dcobj)
			return comman_custom_response([
				"status" => false,
				"message" =>  __("No coupons found.", SOCIALV_API_TEXT_DOMAIN)
			]);

		$response = sv_discount_codes($dcobj, $user_id);

		return comman_custom_response([
			"status" => true,
			"message" =>  __("Available coupons", SOCIALV_API_TEXT_DOMAIN),
			"data" => $response
		]);
	}

	public function order_list($request)
	{
		$data = svValidationToken($request);

		if (!$data['status']) {
			return comman_custom_response($data, 401);
		}

		$user_id = $data['user_id'];

		global $wpdb;

		$parameters = $request->get_params();

		$page 		= isset($parameters["page"]) ? $parameters["page"] : 1;
		$per_page 	= isset($parameters["per_page"]) ? $parameters["per_page"] : 10;


		$pgstrt = absint(($page - 1) * $per_page) . ', ';

		$limits = 'LIMIT ' . $pgstrt . $per_page;

		$order_result = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->pmpro_membership_orders WHERE `user_id` = %d ORDER BY id DESC $limits", $user_id));

		if (!$order_result) return comman_custom_response([
			"status" => false,
			"message" =>  __("No Order Found", SOCIALV_API_TEXT_DOMAIN),
			"data" => []
		]);

		$response = sv_get_pmp_orders($order_result);

		return comman_custom_response([
			"status" => true,
			"message" =>  __("Membership order list", SOCIALV_API_TEXT_DOMAIN),
			"data" => $response
		]);
	}

	public function generate_order($request)
	{
		$data = svValidationToken($request);

		if (!$data['status']) {
			return comman_custom_response($data, 401);
		}

		global $wpdb;

		$current_user_id = $data['user_id'];
		$user = get_userdata($current_user_id);

		$parameters = $request->get_params();
		$parameters = svRecursiveSanitizeTextField($parameters);

		$leve_id = $parameters["level_id"];


		session_start();

		$pmpro_level = pmpro_getLevel($leve_id);
		$discount_code = isset($parameters["discount_code"]) ? $parameters["discount_code"] : "";

		$morder                   = new MemberOrder();
		$morder->user_id          = $current_user_id;
		$morder->membership_id    = $pmpro_level->id;
		$morder->membership_name  = $pmpro_level->name;
		$morder->discount_code    = $discount_code;
		$morder->InitialPayment   = pmpro_round_price($pmpro_level->initial_payment);
		$morder->PaymentAmount    = pmpro_round_price($parameters['billing_amount']);
		$morder->couponamount	= pmpro_round_price($parameters['coupon_amount']);
		$morder->subtotal		= pmpro_round_price($parameters['billing_amount']);
		$morder->total	= pmpro_round_price($parameters['coupon_amount']);
		$morder->ProfileStartDate = date_i18n("Y-m-d\TH:i:s", current_time("timestamp"));
		$morder->BillingPeriod    = $pmpro_level->cycle_period;
		$morder->BillingFrequency = $pmpro_level->cycle_number;
		if ($pmpro_level->billing_limit) {
			$morder->TotalBillingCycles = $pmpro_level->billing_limit;
		}
		if (pmpro_isLevelTrial($pmpro_level)) {
			$morder->TrialBillingPeriod    = $pmpro_level->cycle_period;
			$morder->TrialBillingFrequency = $pmpro_level->cycle_number;
			$morder->TrialBillingCycles    = $pmpro_level->trial_limit;
			$morder->TrialAmount           = pmpro_round_price($pmpro_level->trial_amount);
		}

		$is_card_payment = ($parameters["payment_by"] === "card");
		if ($is_card_payment) {
			// Credit card values.
			$morder->cardtype              = $parameters["card_details"]["card_type"];
			$morder->accountnumber         = "XXXX XXXX XXXX " . $parameters["card_details"]["card_number"];
			$morder->expirationmonth       = $parameters["card_details"]["exp_month"];
			$morder->expirationyear        = $parameters["card_details"]["exp_year"];
			$morder->ExpirationDate        = "";
			$morder->ExpirationDate_YdashM = "";
			$morder->CVV2                  = "";
		}

		// Not saving email in order table, but the sites need it.
		$morder->Email = $user->user_email;

		// Save the user ID if logged in.
		if ($current_user_id) {
			$morder->user_id = $current_user_id;
		}

		$billing_details = $parameters["billing_details"];
		// Sometimes we need these split up.
		$morder->FirstName = $billing_details["first_name"];
		$morder->LastName  = $billing_details["last_name"];
		$morder->Address1  = $billing_details["user_address"];
		$morder->Address2  = "";

		// Set other values.
		$morder->billing          		= new stdClass();
		$morder->billing->name    		= $morder->FirstName . " " . $morder->LastName;
		$morder->billing->street  		= trim($morder->Address1 . " " . $morder->Address2);
		$morder->billing->city    		= $billing_details["user_city"];
		$morder->billing->state   		= $billing_details["user_state"];
		$morder->billing->country 		= $billing_details["user_country"];
		$morder->billing->zip     		= $billing_details["user_postal_code"];
		$morder->billing->phone   		= $billing_details["user_phone"];
		$morder->gateway 				= $parameters["gateway"];
		$morder->gateway_environment 	= $parameters["gateway_mode"];
		$morder->setGateway();


		$morder->payment_transaction_id 		= $parameters["transation_id"];
		$morder->subscription_transaction_id 	= $parameters["transation_id"];

		// Set up level var.
		$morder->getMembershipLevelAtCheckout();

		// Set tax.
		$initial_tax = $morder->getTaxForPrice($morder->InitialPayment);
		$recurring_tax = $morder->getTaxForPrice($morder->PaymentAmount);

		// Set amounts.
		$morder->initial_amount = pmpro_round_price((float)$morder->InitialPayment + (float)$initial_tax);
		$morder->subscription_amount = pmpro_round_price((float)$morder->PaymentAmount + (float)$recurring_tax);

		// die;
		// Filter for order, since v1.8
		$morder = apply_filters('pmpro_checkout_order', $morder);

		$order_id = (int) $morder->saveOrder();

		do_action( "pmpro_after_checkout", $morder->user_id, $morder );

		// Check if we should send emails.
		if ( apply_filters( 'pmpro_send_checkout_emails', true, $morder ) ) {
			// Set up some values for the emails.
			$user                   = get_userdata( $morder->user_id );
			$user->membership_level = $pmpro_level;        // Make sure that they have the right level info.

			// Send email to member.
			$pmproemail = new PMProEmail();
			$pmproemail->sendCheckoutEmail( $user, $morder );

			// Send email to admin.
			$pmproemail = new PMProEmail();
			$pmproemail->sendCheckoutAdminEmail( $user, $morder );
		}
		

		session_destroy();

		if (!$order_id)
			return comman_custom_response([
				"status" => false,
				"message" =>  __("Something Wrong ! Try Again.", SOCIALV_API_TEXT_DOMAIN)
			]);

		if (!$is_card_payment) {
			sv_add_pmp_order_meta($order_id, $parameters["payment_by"], $parameters["meta_value"]);
		}
		if (isset($parameters["discount_code"]))
			sv_add_pmp_order_meta($order_id, "discount_code", $parameters["discount_code"]);

		sv_change_membership_level($leve_id, $current_user_id, ["discount_code" => $discount_code]);

		$order_result = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->pmpro_membership_orders WHERE `id` = %d", $order_id));

		if (!$order_result) return comman_custom_response([
			"status" => true,
			"message" =>  __("Membership order not found", SOCIALV_API_TEXT_DOMAIN)
		]);

		$response = sv_get_pmp_orders($order_result);
		// print_r(reset($response));die;

		return comman_custom_response([
			"status" => true,
			"message" =>  __("Membership orders", SOCIALV_API_TEXT_DOMAIN),
			"data" => reset($response)
		]);
	}

	public function payment_gateways($request)
	{

		$gateways = array_merge(
			SVMembership::get_payment_options("stripe"),
			SVMembership::get_payment_options("razorpay")
		);
		if (empty($gateways))
			return comman_custom_response([
				"status" => false,
				"message" =>  __("No gateways found.", SOCIALV_API_TEXT_DOMAIN)
			]);

		return comman_custom_response([
			"status" => true,
			"message" =>  __("Available payment gateways.", SOCIALV_API_TEXT_DOMAIN),
			"data" => $gateways
		]);
	}
}
