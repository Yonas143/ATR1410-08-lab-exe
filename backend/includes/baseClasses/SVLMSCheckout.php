<?php

namespace Includes\baseClasses;

use Exception;
use LearnPress;
use LP_Gateway_Abstract;
use LP_Gateways;
use LP_Order;

class SVLMSCheckout
{
    protected $user_id;
    protected $payment_method;
    protected $cart_items;
    protected $cart_subtotal;
    protected $cart_total;
    protected $order_obj;
    protected $customer_note;

    function __construct($args)
    {
        $this->user_id = $args['current_user_id'];
        $this->payment_method = $args['payment_method'];
        $this->cart_items = $args['cart_items'];
        $this->cart_subtotal = $args['cart_subtotal'];
        $this->cart_total = $args['cart_total'];
        $this->customer_note = $args['customer_note'];
    }
    public function process_checkout()
    {
        $has_error = false;
        $cart_items = $this->cart_items;
        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0); // @codingStandardsIgnoreLine
            }

            do_action('learn-press/before-checkout');

            if (empty($cart_items)) {
                throw new Exception(__('Your cart is currently empty.', 'learnpress'));
            }

            $messages = array();

            foreach ($cart_items as $item_id) {
                $item_type = get_post_type($item_id);
                if (!in_array($item_type, learn_press_get_item_types_can_purchase())) {
                    throw new Exception(__('Type item buy invalid!', 'learnpress'));
                }
            }


            //LearnPress::instance()->cart->calculate_totals();
            // maybe throw new exception
            $this->validate_payment();
            $payment_method = $this->payment_method;

            $order_id = $this->create_order();
            if (is_wp_error($order_id)) {
                throw new Exception($order_id->get_error_message());
            }

            if ($payment_method instanceof LP_Gateway_Abstract) {
                // Process Payment
                $result = $payment_method->process_payment($order_id);
                if (isset($result['result'])) {
                    if ('success' === $result['result']) {
                        // Clear order_awaiting_payment.
                        //$lp_session->remove( 'order_awaiting_payment', true );
                        $result = apply_filters('learn-press/payment-successful-result', $result, $order_id);
                    }
                }
            }
        } catch (Exception $e) {
            $has_error  = $e->getMessage();
            $messages[] = array($has_error, 'error');
        }

        $is_error = sizeof($messages);

        if (!$is_error) {
            $order_summary = $this->get_order_summary();
            return apply_filters('sv-learn-press/checkout-error', $order_summary, $order_id);
        } else {
            $summary = array(
                'result'   =>  'failed',
                'messages' => $messages,
            );
            return apply_filters('sv-learn-press/checkout-error', $summary, $order_id);
        }
    }
    public function create_order()
    {
        $cart_total = $this->cart_total;
        $payment_method = $this->payment_method;
        $order_comment = $this->customer_note;
        try {

            $order   = new LP_Order();

            $user_id = $this->user_id;

            $order->set_customer_note($order_comment);
            $order->set_status(LP_ORDER_PENDING);
            $order->set_total($cart_total);
            $order->set_subtotal($this->cart_subtotal);
            $order->set_user_ip_address(learn_press_get_ip());
            $order->set_user_agent(learn_press_get_user_agent());
            $order->set_created_via('checkout');
            $order->set_user_id(apply_filters('learn-press/checkout/default-user', $user_id));
            if ($payment_method instanceof LP_Gateway_Abstract) {
                $order->set_data('payment_method', $payment_method->get_id());
                $order->set_data('payment_method_title', $payment_method->get_title());
            }

            $order_id = $order->save();

            // Store the line items to the order
            foreach ($this->cart_items as $item_id) {
                $item_type = get_post_type($item_id);

                if (!in_array($item_type, learn_press_get_item_types_can_purchase())) {
                    continue;
                }

                $item_id = $order->add_item($item_id);

                if (!$item_id) {
                    throw new Exception(sprintf(__('Error %d: Unable to create order. Please try again.', 'learnpress'), 402));
                }
                $item = [
                    'item_id'           => $item_id,
                    'order_item_name'   => get_the_title($item_id),
                    'quantity'          => 1
                ];
                do_action('learn-press/checkout/add-order-item-meta', $item_id, $item);
            }

            if (!empty($user_id)) {
                do_action('learn-press/checkout/update-user-meta', $user_id);
            }

            do_action('learn-press/checkout/update-order-meta', $order_id);

            if (!$order_id || is_wp_error($order_id)) {
                learn_press_add_message(__('Unable to checkout. Order creation failed.', 'learnpress'));
            }

            /* get order details */
            $this->order_obj = learn_press_get_order($order_id);
        } catch (Exception $e) {
            learn_press_add_message($e->getMessage());

            return false;
        }

        return $order_id;
    }

    public function get_order_summary()
    {
        $ordered_items = [];
        foreach ($this->order_obj->get_items() as $item) {
            $course_id = $item["course_id"];
            $ordered_items[] = [
                "id"            => $course_id,
                "name"          => $item["name"],
                "regular_price" => get_post_meta($course_id, "_lp_regular_price", true),
                "sale_price"    => get_post_meta($course_id, "_lp_sale_price", true),
                "quantity"      => $item["quantity"],
            ];
        }
        $order_summary = [
            "order_number"      => $this->order_obj->get_order_number(),
            "order_items"       => $ordered_items,
            "order_key"         => $this->order_obj->get_order_key(),
            "order_date"        => $this->order_obj->get_order_date('d'),
            "order_subtotal"    => $this->order_obj->get_formatted_order_subtotal(),
            "order_total"       => $this->order_obj->get_formatted_order_total(),
            "order_status"      => $this->order_obj->get_status_label($this->order_obj->get_status()),
            "order_method"      => $this->order_obj->get_payment_method_title()
        ];
        return $order_summary;
    }
    
    public function validate_payment()
    {

        $validate = true;


        if (!$this->payment_method instanceof LP_Gateway_Abstract) {
            $available_gateways = LP_Gateways::instance()->get_available_payment_gateways();

            if (!isset($available_gateways[$this->payment_method])) {
                $this->payment_method = '';
                throw new Exception(__('No payment method is selected', 'learnpress'), LP_ERROR_NO_PAYMENT_METHOD_SELECTED);
            } else {
                $this->payment_method = $available_gateways[$this->payment_method];
            }
        }

        if ($this->payment_method) {
            $validate = $this->payment_method->validate_fields();
        }


        return $validate;
    }
}
