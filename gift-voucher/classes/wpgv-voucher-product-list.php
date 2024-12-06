<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

if (! class_exists('WPGV_Voucher_Product_List')) :


    /**
     * WPGV_Voucher_Product_List Class
     */
    class WPGV_Voucher_Product_List extends WP_List_Table
    {

        /** Class constructor */
        public function __construct()
        {
            parent::__construct(array(
                'singular' => __('Product', 'gift-voucher'), //singular name of the listed records
                'plural'   => __('Products', 'gift-voucher'), //plural name of the listed records
                'ajax'     => true //does this table support ajax?
            ));
        }

        /**
         * Retrieve voucher product data from the database
         *
         * @param int $per_page
         * @param int $page_number
         *
         * @return mixed
         */
        public static function get_products($per_page = 20, $page_number = 1)
        {
            global $wpdb;

            $args = array(
                'post_type' => 'product',
                'posts_per_page' => $per_page,
                'paged' => $page_number,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => 'wpgv-gift-voucher'
                    )
                )
            );
            $products = new WP_Query($args);
            $result = $products->posts;

            return $result;
        }

        /**
         * Delete a template record.
         *
         * @param int $id template id
         */
        public static function delete_template($id)
        {
            global $wpdb;

            wp_delete_post($id, true);
        }

        /**
         * Returns the count of records in the database.
         *
         * @return null|string
         */
        public static function record_count()
        {
            global $wpdb;

            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => 'wpgv-gift-voucher'
                    )
                )
            );
            $products = new WP_Query($args);
            return $products->found_posts;
        }

        /** Text displayed when no template data is available */
        public function no_items()
        {
            esc_html_e('No Product yet.', 'gift-voucher');
        }


        /**
         * Render a column when no column specific method exist.
         *
         * @param array $item
         * @param string $column_id
         *
         * @return mixed
         */
        public function column_default($item, $column_id)
        {
            /*echo "column default";
		exit();*/
            switch ($column_id) {
                case 'id':
                    return $item->ID;
                case 'title':
                case 'image':
                case 'active':
                    return $item->post_status;
                case 'templateadd_time':
                default:
                    return print_r($item, true); //Show the whole array for troubleshooting purposes
            }
        }

        /**
         *  Associative array of columns
         *
         * @return array
         */
        function get_columns()
        {
            $columns = array(
                'cb'                  => '<input type="checkbox" />',
                'id'                => __('Product ID', 'gift-voucher'),
                'title'                => __('Title', 'gift-voucher'),
                'image'                => __('Image', 'gift-voucher'),
                'active'            => __('Status', 'gift-voucher'),
                'templateadd_time'    => __('Product Date', 'gift-voucher'),
            );

            return $columns;
        }

        /**
         * Render the bulk delete checkbox
         *
         * @param array $item
         *
         * @return string
         */
        function column_cb($item)
        {
            return sprintf(
                '<input type="checkbox" name="product_id[]" value="%s" />',
                $item->ID
            );
        }

        /**
         * Method for name column
         *
         * @param array $item an array of DB data
         *
         * @return string
         */
        function column_title($item)
        {
            $delete_nonce = wp_create_nonce('delete_template');
            $title = '<strong>' . $item->post_title . '</strong>';

            $actions = array(
                'edit_template' => '<a href=' . get_edit_post_link($item->ID) . '>Edit Product</a>',
                'delete' => sprintf('<a href="?page=%s&action=%s&product_id=%s&_wpnonce=%s">%s</a>', esc_attr($_REQUEST['page']), 'delete', absint($item->ID), $delete_nonce, __('Delete', 'gift-voucher'))
            );

            return $title . $this->row_actions($actions);
        }

        /**
         * Method for display template create date
         *
         * @param array $item an array of DB data
         *
         * @return string
         */
        function column_image($item)
        {
            $thumbnail_url = wp_get_attachment_url(get_post_thumbnail_id($item->ID));
            if ($thumbnail_url) {
?>
                <img height="60" src="<?php echo esc_url($thumbnail_url); ?>">
            <?php
            }
        }


        /**
         * Method for display template create date
         *
         * @param array $item an array of DB data
         *
         * @return string
         */
        function column_templateadd_time($item)
        {
            $formatted_date = date('Y/m/d', strtotime($item->post_date));
            $formatted_title = date('Y/m/d H:i:s a', strtotime($item->post_date));
            ?>
            <abbr title="<?php echo esc_attr($formatted_title); ?>"><?php echo esc_html($formatted_date); ?></abbr>
<?php
        }


        /**
         * Returns an associative array containing the bulk action
         *
         * @return array
         */
        public function get_bulk_actions()
        {
            $actions = array(
                'bulk-delete' => __('Delete', 'gift-voucher')
            );

            return $actions;
        }

        /**
         * Handles data query and filter, sorting, and pagination.
         */
        public function prepare_items()
        {
            $this->_column_headers = $this->get_column_info();

            /** Process bulk action */
            $this->process_bulk_action();

            $per_page     = $this->get_items_per_page('templates_per_page', 20);
            $current_page = $this->get_pagenum();
            $total_items  = self::record_count();

            $this->set_pagination_args(array(
                'total_items' => $total_items,     //WE have to calculate the total number of items
                'per_page'    => $per_page         //WE have to determine how many items to show on a page
            ));

            $this->items = self::get_products($per_page, $current_page);
        }

        /**
         * Handles data for delete the bulk action
         */
        public function process_bulk_action()
        {
            //Detect when a bulk action is being triggered...
            if ('bulk-delete' === $this->current_action()) {
                foreach ($_REQUEST['product_id'] as $template) {
                    self::delete_template(absint($template));
                }
                // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
                // add_query_arg() return the current url
                wp_safe_redirect("?page=voucher-products");
                exit;
            }
        }
    }

endif;
