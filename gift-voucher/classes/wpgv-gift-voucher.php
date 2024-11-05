<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

if (! class_exists('WPGV_Gift_Voucher')) :

    class WPGV_Gift_Voucher
    {

        public function get_id()
        {
            return $this->id;
        }
        private $id;

        public function get_number()
        {
            return $this->couponcode;
        }
        private $couponcode;

        public function get_active()
        {
            return $this->status;
        }
        private $status;

        public function get_create_date()
        {
            return $this->create_date;
        }
        private $create_date;

        public function get_payment_status()
        {
            return $this->payment_status;
        }
        private $payment_status;

        public function get_expiration_date()
        {
            return $this->expiration_date;
        }
        public function set_expiration_date($expiration_date)
        {
            $this->update_property('expiry', $expiration_date);
        }
        private $expiration_date;

        public function get_error_message()
        {
            return $this->error_message;
        }
        private $error_message;


        function __construct($couponcode)
        {
            global $wpdb;

            // require_once( WPGIFT__PLUGIN_DIR .'/classes/wc-order-item-wpgv-gift-voucher.php');

            $couponcode = sanitize_text_field($couponcode);
            if (!empty($couponcode)) {

                $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}giftvouchers_list WHERE `couponcode` = %s", $couponcode));
                if ($result !== null) {
                    $this->id               = $result->id;
                    $this->couponcode       = $result->couponcode;
                    $this->status           = $result->status;
                    $this->create_date      = $result->voucheradd_time;
                    $this->payment_status   = $result->payment_status;
                    $this->expiration_date  = $result->expiry;
                } else {
                    $this->error_message = __('Gift Voucher does not exist.', 'gift-voucher');
                }
            } else {
                $this->error_message = __('Enter a card number.', 'gift-voucher');
            }
        }

        public function has_expired()
        {
            $expired_time = $this->get_expiration_date();
            if (!empty($expired_time)) {
                if ($expired_time == 'No Expiry') {
                    return false;
                } else {
                    $expiration_date = strtotime($this->get_expiration_date());
                    $todays_date = strtotime(current_time('Y-m-d'));
                    return ($expiration_date < $todays_date);
                }
            } else {
                return false;
            }
        }

        public function get_balance()
        {
            global $wpdb;

            $balance = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$wpdb->prefix}giftvouchers_activity WHERE voucher_id = %d", $this->get_id()));

            if ($balance === null) {
                $this->error_message = $wpdb->last_error;
            }

            return $balance;
        }

        public function get_activity()
        {
            global $wpdb;

            $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                card.couponcode AS card_number,
                activity.activity_date,
                activity.action,
                activity.amount,
                activity.note
            FROM
                {$wpdb->prefix}giftvouchers_activity AS activity
            JOIN
                {$wpdb->prefix}giftvouchers_list AS card ON (card.id = activity.voucher_id)
            LEFT JOIN
                {$wpdb->users} AS users ON (users.ID = activity.user_id)
            WHERE
                activity.voucher_id = %d
            ORDER BY
                activity.id DESC
        ", $this->get_id()));

            return $results;
        }

        public function credit($amount, $note = '')
        {
            $amount = floatval($amount);
            if ($amount <= 0) {
                wp_die(esc_html__('Amount added should be greater than zero.', 'gift-voucher'));
            }
            $this->adjust_balance($amount, $note);
        }

        public function debit($amount, $note = '')
        {
            $amount = floatval($amount);
            if ($amount >= 0) {
                wp_die(esc_html__('Amount deducted should be less than zero.', 'gift-voucher'));
            }
            $this->adjust_balance($amount, $note);
        }

        public function adjust_balance($amount, $note = '')
        {
            $amount = floatval($amount);

            if ($this->status == 'used') {
                wp_die(esc_html__('Unable to adjust balance, card is not active.', 'gift-voucher'));
            }

            if (($this->get_balance() + $amount) < 0) {
                // translators: %1$s: current balance, %2$s: amount to adjust
                wp_die(sprintf(esc_html__('Balance is currently %1$s, unable to adjust by %2$s', 'gift-voucher'), esc_html($this->get_balance()), esc_html($amount)));
            }

            $this->log_activity('transaction', $amount, $note);
        }

        public function deactivate($note = '')
        {
            if ($this->update_property('status', 'unused') === true) {
                $this->log_activity('deactivate', null, $note);
            }
        }

        public function reactivate($note = '')
        {
            if ($this->update_property('status', 'used') === true) {
                $this->log_activity('reactivate', null, $note);
            }
        }

        public function check_balance_url()
        {
            global $pw_gift_cards;

            $check_balance_url = '';

            if (is_admin()) {
                $check_balance_url = admin_url('admin.php');
                $check_balance_url = add_query_arg('page', 'wc-pw-gift-cards', $check_balance_url);
                $check_balance_url = add_query_arg('card_number', $this->get_number(), $check_balance_url);
            }

            return $check_balance_url;
        }



        /*
     *
     * Static Methods
     *
     */
        public static function get_by_id($id)
        {
            global $wpdb;
            $voucher_id = absint($id);
            if (!empty($voucher_id)) {
                $result = $wpdb->get_row($wpdb->prepare("SELECT `couponcode` FROM `{$wpdb->prefix}giftvouchers_list` WHERE id = %d", absint($id)));
                if (null !== $result) {
                    return new WPGV_Gift_Voucher($result->couponcode);
                }
            }

            return false;
        }

        public static function add_card($couponcode, $note = '')
        {
            global $wpdb;

            $couponcode = sanitize_text_field($couponcode);

            if (empty($couponcode)) {
                return __('Card Number cannot be empty.', 'gift-voucher');
            }

            $result = $wpdb->insert($wpdb->prefix . 'giftvouchers_list', array('couponcode' => $couponcode), array('%s'));

            if ($result !== false) {
                $gift_card = WPGV_Gift_Voucher::get_by_id($wpdb->insert_id);
                $gift_card->log_activity('create', null, $note);

                return $gift_card;
            } else {
                return $wpdb->last_error;
            }
        }

        public static function create_card($note = '')
        {
            // Failsafe. If we haven't generated a number after this many tries, throw an error.
            $attempts = 0;
            $max_attempts = 100;

            // Get a random Card Number and insert it. If the insertion fails, it is already in use.
            do {
                $attempts++;

                $couponcode = self::random_card_number();
                $gift_card = WPGV_Gift_Voucher::add_card($couponcode, $note);
            } while (!($gift_card instanceof self) && $attempts < $max_attempts);

            if ($gift_card instanceof self) {
                return $gift_card;
            } else {
                // translators: %1$d: number of attempts, %2$s: gift card number
                wp_die(sprintf(esc_html__('Failed to generate a unique random card number after %1$d attempts. %2$s', 'gift-voucher'), intval($attempts), esc_html($gift_card)));
            }
        }



        /*
     *
     * Private methods
     *
     */
        private function update_property($property, $value)
        {
            global $wpdb;

            if (property_exists($this, $property)) {
                if ($this->{$property} != $value) {
                    $result = $wpdb->update($wpdb->prefix . 'giftvouchers_list', array($property => $value), array('id' => $this->get_id()));

                    if ($result !== false) {
                        $this->{$property} = $value;

                        return true;
                    } else {
                        wp_die(esc_html($wpdb->last_error));
                    }
                }
            } else {
                // translators: %1$s: property name, %2$s: class name
                wp_die(sprintf(esc_html__('Property %1$s does not exist on %2$s', 'gift-voucher'), esc_html($property), esc_html(get_class())));
            }
        }

        private function log_activity($action, $amount = null, $note = null)
        {
            WPGV_Gift_Voucher_Activity::record($this->get_id(), $action, $amount, $note);
        }
    }

endif;
