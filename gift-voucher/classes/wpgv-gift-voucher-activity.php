<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

if (! class_exists('WPGV_Gift_Voucher_Activity')) :

    class WPGV_Gift_Voucher_Activity
    {

        public function get_id()
        {
            return $this->id;
        }
        protected function set_id($id)
        {
            $this->id = $id;
        }
        private $id;

        public function get_gift_card_id()
        {
            return $this->voucher_id;
        }
        protected function set_gift_card_id($gift_card_id)
        {
            $this->voucher_id = $gift_card_id;
        }
        private $voucher_id;

        public function get_activity_date()
        {
            return $this->activity_date;
        }
        protected function set_activity_date($activity_date)
        {
            $this->activity_date = $activity_date;
        }
        private $activity_date;

        public function get_action()
        {
            return $this->action;
        }
        protected function set_action($action)
        {
            $this->action = $action;
        }
        private $action;

        public function get_amount()
        {
            return $this->amount;
        }
        protected function set_amount($amount)
        {
            $this->amount = $amount;
        }
        private $amount;

        public function get_note()
        {
            return $this->note;
        }
        protected function set_note($note)
        {
            $this->note = $note;
        }
        private $note;

        public static function get_card_activity($gift_card, $limit = 0)
        {
            global $wpdb;

            $activity_records = array();

            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$wpdb->prefix}giftvouchers_activity` WHERE voucher_id = %d ORDER BY `activity_date` LIMIT %d", $gift_card->get_id(), absint($limit)));
            if (null !== $results) {
                foreach ($results as $row) {

                    $activity = new PW_Gift_Card_Activity();

                    $activity->set_id($result->id);
                    $activity->set_gift_card_id($result->voucher_id);
                    $activity->set_activity_date($result->activity_date);
                    $activity->set_action($result->action);
                    $activity->set_amount($result->amount);
                    $activity->set_note($result->note);

                    $activity_records[] = $activity;
                }
            } else {
                // translators: %d: activity record ID
                wp_die(sprintf(esc_html__('Could not find activity record %d.', 'gift-voucher'), esc_html($id)));
            }

            $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$wpdb->prefix}giftvouchers_activity` WHERE id = %d", $id));

            return $activity_records;
        }

        public static function record($gift_card_id, $action, $amount = null, $note = null)
        {
            global $wpdb;

            if (!in_array($action, array('create', 'firsttransact', 'transaction', 'deactivate', 'reactivate', 'note'))) {
                wp_die(esc_html__('Invalid action value: ', 'gift-voucher') . esc_html($action));
            }


            $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$wpdb->prefix}giftvouchers_list` WHERE `id` = %d", $gift_card_id));
            $gift_voucher = new WPGV_Gift_Voucher($voucher->couponcode);
            $balance = $gift_voucher->get_balance();
            if (!($balance >= $amount && $action == 'firsttransact')) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'giftvouchers_activity',
                    array(
                        'voucher_id'    => $gift_card_id,
                        'action'        => $action,
                        'amount'        => $amount,
                        'note'          => sanitize_text_field($note),
                        'user_id'       => get_current_user_id(),
                        'activity_date' => current_time('mysql')
                    ),
                    array('%d', '%s', '%f', '%s', '%d', '%s')
                );
                if ($result) {
                    return $result;
                } else {
                    wp_die(esc_html($wpdb->last_error));
                }
            } else {
                return false;
            }
        }
    }

endif;
