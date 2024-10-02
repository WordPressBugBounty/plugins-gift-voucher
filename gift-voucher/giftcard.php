<?php
if( !defined( 'ABSPATH' ) ) exit;
#define('ALLOW_UNFILTERED_UPLOADS', true);
//add gift card voucher
function wpgv_voucher_template_shortcode(){
    //get option settting
    global $wp, $wpdb;
    $html = '';
    $find = array( 'http://', 'https://' );
    $replace = '';
    $siteURL = str_replace( $find, $replace, get_site_url() );  
    $setting_options = get_data_settings_voucher();// get data settings option   
    $wpgv_add_extra_charges = get_option('wpgv_add_extra_charges_voucher') ? get_option('wpgv_add_extra_charges_voucher') : 0;
    $number_slider = get_option('wpgv_number_giftcard_slider') ? get_option('wpgv_number_giftcard_slider') : 3;
    $wpgv_custom_css = get_option('wpgv_custom_css') ? stripslashes(trim(get_option('wpgv_custom_css'))) : '';    
    $type_mode = get_value_mode_gift_card_function();
    //check show template mode giftcard
    if ($type_mode == 'landscape_giftcard') {
        $mode_template_giftcard = $setting_options->landscape_mode_templates;
    } else{
        $mode_template_giftcard = $setting_options->portrait_mode_templates;
    }
    $max_price_value = !empty($setting_options->voucher_max_value) ? $setting_options->voucher_max_value : 10000; 
    $min_price_value = !empty($setting_options->voucher_min_value) ? $setting_options->voucher_min_value : 1; 
    wp_enqueue_style('wpgv-voucher-style');
    wp_enqueue_style('wpgv-slick-css');
    wp_enqueue_style('wpgv-fontawesome-css');
    wp_enqueue_style('wpgv-voucher-template-fonts-css');
    wp_enqueue_style('wpgv-voucher-template-style-css');
    // wp_enqueue_script('wpgv-bootstrap-js');
    wp_enqueue_script('wpgv-bootstrap-datetimepicker-js');
    wp_enqueue_script('wpgv-konva-min-js');
    wp_enqueue_script('wpgv-jspdf-js');
    wp_enqueue_script('wpgv-jquery-validate');
    wp_enqueue_script('wpgv-jquery-steps');
    wp_enqueue_script('wpgv-stripe-js');
    wp_enqueue_script('wpgv-paypal-js');
    wp_enqueue_script('wpgv-slick-script');
    wp_enqueue_script('wpgv-voucher-template-script');
    // step voucher template    
    $html .='<div id="giftvoucher-template" class="wrapper-template-gift-voucher">
        <div class="giftvoucher-template-step-main">
            <div class="giftvoucher-template-steps">
                <div class="giftvoucher-step active">
                    <div class="step-group enable_click" data-step="1" id="select-temp">
                        <div class="step-number">1</div>
                        <div class="step-label">'.__('Select Template', 'gift-voucher' ).'</div>
                    </div>
                </div>
                <div class="giftvoucher-step">
                    <div class="step-group disable_click" data-step="2" id="select-per">
                        <div class="step-number">2</div>
                        <div class="step-label">'.__('Setup your gift card', 'gift-voucher' ).'</div>
                    </div>
                </div>
                <div class="giftvoucher-step">
                    <div class="step-group disable_click" data-step="3" id="select-payment">
                        <div class="step-number">3</div>
                        <div class="step-label">'.__('Payment', 'gift-voucher' ).'</div>
                    </div>
                </div>
                <div class="giftvoucher-step">
                    <div class="step-group disable_click" data-step="4" id="select-overview">
                        <div class="step-number">4</div>
                        <div class="step-label">'.__('Overview', 'gift-voucher' ).'</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="step-progress" id="scrollintoview">
            <div class="progress">
                <div class="progress-bar" style="width:25%"></div>
            </div>
        </div>
        <div class="wrap-giftvoucher-template-content">
            <div id="voucher-template-name-step">
                <span class="number-step">1</span>
                <h3 class="choose-show-title">'.__('Select Template', 'gift-voucher' ).'</h3>
            </div>';
            $html .= '<div class="wrap-format-category-voucher">'.format_categories_function().'</div>'; // call back function fomart caegory
            // content step
            $html .='<div class="giftvoucher-template-content">
                <div id="slider-giftvoucher" class="voucher-content-step">
                    <div id="slider-giftvoucher-template">'.get_data_template_voucher($type_mode,$mode_template_giftcard, 0).'</div>
                </div>
                <div id="setup-voucher-template" class="voucher-content-step">
                    <input hidden id="voucher-id" value="" />
                    <input hidden id="voucher-extra-charges" value="'.$wpgv_add_extra_charges.'" />
                    <input hidden id="voucher-couponcode" value="" />
                    <div class="wrap-setup-voucher-template">
                        <div id="voucher-template-choose-gift" class="wrap-main-voucher-template">';                       
                            /*<div class="voucher-template-radius"> 
                                <div class="form-input-radius">
                                    <input type="radio" name="buying_for" id="someone_else" class="" value="someone_else" checked="">
                                    <label for="someone_else">'.__('Someone Else','gift-voucher').'</label>
                                </div>   
                                <div class="form-input-radius">
                                    <input type="radio" name="buying_for" value="yourself" id="yourself" class="active-input">
                                    <label for="yourself">'.__('Myself','gift-voucher').'</label>
                                </div>  
                            </div>*/
                            $html .='<div class="voucher-template-infomation">
                                <div class="wrapper-infomation-voucher-template" id="content-setup-voucher-template">'.set_up_gift_voucher().'</div>
                                <div class="wrapper-infomation-voucher-template" id="setup-shopping-payment-wrap">
                                ';
                                    if (!empty($setting_options->post_shipping)) {
                                        $html .='<div class="header-title">
                                            <h4 class="title-header-h4">'.__('Shipping','gift-voucher').'</h4>
                                        </div>
                                        <div class="choose-shipping-template">
                                            <a class="shipping-type active" data-type="shipping_as_email">
                                                <i class="fa fa-envelope" aria-hidden="true"></i>
                                                <span>'.__('Email','gift-voucher').'</span>
                                            </a>
                                            <a class="shipping-type" data-type="shipping_as_post">
                                                <i class="fa fa-truck" aria-hidden="true"></i>
                                                <span>'.__('Post','gift-voucher').'</span>
                                            </a>
                                        </div>';
                                    }
                                    $html .='<div class="form-shopping-payment">
                                        <div class="wrap-email-shiping-voucher">
                                            <div class="voucher-template-input">
                                                <input value="" id="voucher_your_email" placeholder="'.__('Your Email','gift-voucher').'" name="voucher_your_email" type="text" class="input-info-voucher">
                                                <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
                                            </div>
                                            <div class="voucher-template-input">
                                                <input value="" id="voucher_recipient_email" placeholder="'.__('Recipient Email','gift-voucher').'" name="voucher_recipient_email" type="text" class="input-info-voucher">
                                                <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
                                            </div>
                                        </div>';
                                        if (!empty($setting_options->post_shipping)) {
                                            $html .='<div class="wrap-shipping-info-voucher">'.show_shipping_method_voucher().'</div>'; 
                                        }
                                        $html .='<div class="choose-payment-method">'.show_payment_option_voucher().'</div> 
                                        <div class="order-voucher-details">'.show_order_detail_voucher().'</div>                                  
                                    </div>
                                </div> 
                                <div class="wrapper-infomation-voucher-template" id="order-voucher-details-overview">'.show_overview_voucher_template().'</div>
                            </div>
                        </div>
                        <div id="select-template-voucher" class="wrap-main-voucher-template">
                            <div id="template_giftcard_container"></div>
                        </div>
                    </div>
                </div>
                <div id="voucher-continue-step">
                    <div class="next-prev-button prev-button">
                        <a href="javascript:;" class="voucher-prev-step" data-prev-step="1"><span><i class="fa fa-angle-left" aria-hidden="true"></i></span>'.__('Prev step', 'gift-voucher' ).'</a>
                    </div>
                    <div class="next-prev-button next-button">
                        <input type="hidden" value="" id="dataVoucher"/>
                        <input type="hidden" value="'.$number_slider.'" id="number_giftcard_sl"/>';
                        if (!empty($setting_options->preview_button)) {
                            $html .='<a href="javascript:;" class="voucher-preview-pdf" id="voucher-preview-pdf">'.__('PDF preview', 'gift-voucher' ).'<span><i class="fa fa-angle-down" aria-hidden="true"></i></span></a>';
                        }
                        $html .='<a href="javascript:;" id="payment-voucher-template">'.__('Pay Now', 'gift-voucher' ).'<span><i class="fa fa-angle-right" aria-hidden="true"></i></span></a>
                        <a href="javascript:;" class="voucher-next-step" data-next-step="3">'.__('Next step', 'gift-voucher' ).'<span><i class="fa fa-angle-right" aria-hidden="true"></i></span></a>
                    </div>
                </div>              
            </div>
        </div>';
    $html .= '</div>';
    $html .= '<style>'.stripslashes($wpgv_custom_css).'</style>';
    return $html;
}
add_shortcode( 'wpgv_giftcard', 'wpgv_voucher_template_shortcode' );

// function get_option_settings
function get_data_settings_voucher(){
    global $wp, $wpdb;
    $setting_table  = $wpdb->prefix . 'giftvouchers_setting';
    $setting_options = $wpdb->get_row( "SELECT * FROM $setting_table WHERE id = 1");
    return $setting_options;
}

// function get template voucher portail
function get_data_template_voucher($type, $template_voucher, $category_voucher){ 
    if(!empty($category_voucher)){
        $voucher_arr = array(
            'post_type' => 'voucher_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'category_voucher_template',
                    'field' => 'id',
                    'terms' => array($category_voucher)
                )
            ),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'       => 'wpgv_customize_template_status',
                    'value'     => 'active',
                    'compare'   => '=',
                ),
                array(
                    'key'       => 'wpgv_customize_template_template-style',
                    'value'     => join(', ', array($template_voucher)),
                    'compare'   => 'IN',
                ),
            )
        );
    }else{
        $voucher_arr = array(
            'post_type' => 'voucher_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'       => 'wpgv_customize_template_status',
                    'value'     => 'active',
                    'compare'   => '=',
                ),
                array(
                    'key'       => 'wpgv_customize_template_template-style',
                    'value'     => join(', ', array($template_voucher)),
                    'compare'   => 'IN',
                ),
            )
        );
    }
    $template_voucher_args = new WP_Query($voucher_arr);
    $voucher_counter = 1;
    $total_voucher = $template_voucher_args->found_posts;
    $s_counter = 1;
    $html = '';
    if($template_voucher_args->have_posts()){
        $html.= '<div class="slider-voucher-template">';
        while( $template_voucher_args->have_posts() ) : $template_voucher_args->the_post();
            $post_id = get_the_ID();
            $selected_voucher_template = esc_html(get_post_meta($post_id,'wpgv_customize_template_template-style',true));
            $frame_no = preg_replace('/[^0-9]/', '', $selected_voucher_template);
            $selected_voucher_thumbnail = get_post_meta($post_id,'wpgv_customize_template_thumbnail',true);
            $url_img = WPGIFT__PLUGIN_URL.'/assets/img/templates/png/'.$selected_voucher_template;
            $html.= '<div class="item-voucher-template">
                <img src="'. esc_url($url_img) .'" alt="'.get_the_title().'">';
                $html.= '<div class="layout-overlay" data-template="'.esc_attr($type).'" data-src="'.esc_attr($selected_voucher_template).'" data-post_id="'.esc_attr($post_id).'">
                    <div class="layout-overlay-row">
                        <span class="layout-button">
                        '.__('Select This', 'gift-voucher' ).'
                        </span>
                    </div>
                </div>
            </div>'; 
        endwhile; wp_reset_postdata(); 
        $html.= '</div>';
    }else{
       $html.='<div class="wpgv_no_voucher_found"><span>'.__('No Gift Voucher Found!', 'gift-voucher' ).'</span></div>'; 
    }
    return $html;
}
// function ajax template voucher slider
function getTemplateVoucherSlider(){
    $setting_options = get_data_settings_voucher();// get data settings option
    $portail_mode_templates = $setting_options->portrait_mode_templates; 
    $landscape_mode_templates = $setting_options->landscape_mode_templates; 
    $dataType = !empty($_POST['dataType']) ? sanitize_text_field($_POST['dataType']) : 'portrait';
    $dataCategory = !empty($_POST['dataCategory']) ? sanitize_text_field($_POST['dataCategory']) : '0';    
    if($dataType == 'landscape'){
        $data = get_data_template_voucher($dataType, $landscape_mode_templates, $dataCategory);
    }else{
        $data = get_data_template_voucher($dataType, $portail_mode_templates, $dataCategory);
    }
    echo $data;
    wp_die();
}
add_action('wp_ajax_voucher_slider_template', 'getTemplateVoucherSlider');
add_action('wp_ajax_nopriv_voucher_slider_template', 'getTemplateVoucherSlider');
// function select template voucher
function getSelectTemplateVoucher(){
    $setting_options = get_data_settings_voucher();
    $giftto = __('Gift To', 'gift-voucher');
    $giftfrom = __('Gift From', 'gift-voucher');
    //$date_of = __('Date of Expiry', 'gift-voucher');
    $date_of = __('Date', 'gift-voucher');
    $counpon = __('Coupon', 'gift-voucher');
    $voucher_id = !empty($_POST['voucher_id']) ? sanitize_text_field($_POST['voucher_id']) : 0;
    $web = !empty($setting_options->pdf_footer_url) ? $setting_options->pdf_footer_url : get_site_url();
    $email = !empty($setting_options->pdf_footer_email) ? $setting_options->pdf_footer_email : get_option('admin_email');;
    $company_name = !empty($setting_options->company_name) ? $setting_options->company_name : get_bloginfo( 'name' );;
    // check expiry
    $wpgv_hide_expiry = get_option('wpgv_hide_expiry') ? get_option('wpgv_hide_expiry') : 'yes'; 
    $wpgv_expiry_date_format = get_option('wpgv_expiry_date_format') ? get_option('wpgv_expiry_date_format') : 'd.m.Y';
    $voucher_expiry_value = !empty(esc_html(get_post_meta( $voucher_id, 'wpgv_customize_template_voucher_expiry_value', true))) ? esc_html(get_post_meta( $voucher_id, 'wpgv_customize_template_voucher_expiry_value', true)) : $setting_options->voucher_expiry; // format day and number 
    if($wpgv_hide_expiry == 'no') {
        $expiryDate = __('No Expiry', 'gift-voucher' );
    } else {
        $expiryDate = ($setting_options->voucher_expiry_type == 'days') ? date($wpgv_expiry_date_format,strtotime('+'.$voucher_expiry_value.' days',time())) . PHP_EOL : $voucher_expiry_value;
    }
    $wpgv_leftside_notice = (get_option('wpgv_leftside_notice') != '') ? get_option('wpgv_leftside_notice') : __('Cash payment is not possible. The terms and conditions apply.', 'gift-voucher' );

    //WPGIFT__PLUGIN_URL
    $select_template = esc_html(get_post_meta( $voucher_id, 'wpgv_customize_template_template-style', true ));
    $title_template = str_replace(".png",".svg",$select_template);

    //$images_template = !empty(get_post_meta( $voucher_id, 'wpgv_customize_template_image', true )) ? get_post_meta( $voucher_id, 'wpgv_customize_template_image', true ) : WPGIFT__PLUGIN_URL.'/assets/img/template-images/'.$title_template; 
    $images_template = WPGIFT__PLUGIN_URL.'/assets/img/templates/svg/'.$title_template; 
    $name_template = str_replace(".png",".json",$select_template);
    $json = WPGIFT__PLUGIN_URL.'/assets/img/templates/json/'.$name_template;
    $json = file_get_contents($json);    
    $result = array(
        'url' => $images_template,
        'currency' => $setting_options->currency,
        'giftto' => $giftto,
        'giftfrom' => $giftfrom,
        'date_of' => $date_of,
        'company_name' => $company_name,
        'email' => $email,
        'web' => $web,
        'leftside_notice' => $wpgv_leftside_notice,
        'expiryDate' => $expiryDate,
        'counpon' => $counpon,
        'json' => $json,
    );
    echo json_encode($result);
    wp_die();
}
add_action('wp_ajax_ajax_select_voucher_template', 'getSelectTemplateVoucher');
add_action('wp_ajax_nopriv_ajax_select_voucher_template', 'getSelectTemplateVoucher');
// function set up gift voucher
function set_up_gift_voucher(){
    $setting_options = get_data_settings_voucher();
    $html = '<div class="voucher-template-input price-voucher">
        <label>'.__('Gift Value','gift-voucher').'</label>
        <div class="price-template-voucher">
            <span class="currencySymbol"> '.$setting_options->currency.' </span>
            <input type="number" name="voucher_price_value" id="voucher_price_value" class="input-info-voucher" value="">
        </div>
        <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
    </div>
    <div class="voucher-template-input">
        <label>'.__('Gift To','gift-voucher').'</label>
        <input maxlength="30" value="" id="voucher_gift_to" placeholder="'.__('Gift To','gift-voucher').'" name="voucher_gift_to" type="text" class="input-info-voucher">
        <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
    </div>
    <div class="voucher-template-input">
        <label>'.__('Gift From','gift-voucher').'</label>
        <input maxlength="30" value="" id="voucher_gift_from" placeholder="'.__('Gift From','gift-voucher').'" name="voucher_gift_from" type="text" class="input-info-voucher">
        <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
    </div>
    <div class="voucher-template-input">
        <label>'.__('Description (Max: 250 Characters)','gift-voucher').'</label>
        <textarea maxlength="250" value="" id="voucher_description" placeholder="'.__('Description (Max: 250 Characters)','gift-voucher').'" name="voucher_description" class="input-info-voucher"></textarea>
        <div class="maxchar"></div>
    </div>';
    return $html;
}
// function get payment
function show_payment_option_voucher(){
    $setting_options = get_data_settings_voucher();
    $wpgv_multisafepay = get_option('wpgv_multisafepay') ? get_option('wpgv_multisafepay') : 0;
    $wpgv_paypal_alternative_text = get_option('wpgv_paypal_alternative_text') ? get_option('wpgv_paypal_alternative_text') : __('PayPal', 'gift-voucher');
    $wpgv_stripe_alternative_text = get_option('wpgv_stripe_alternative_text') ? get_option('wpgv_stripe_alternative_text') : __('Stripe', 'gift-voucher');
    $wpgv_multisafepay_alternative_text = get_option('wpgv_multisafepay_alternative_text') ? get_option('wpgv_multisafepay_alternative_text') : __('MultiSafepay', 'gift-voucher');
    
    // $wpgv_paypal_alternative_text = get_option('wpgv_paypal_alternative_text') ? get_option('wpgv_paypal_alternative_text') : 'PayPal';
    // $wpgv_stripe_alternative_text = get_option('wpgv_stripe_alternative_text') ? get_option('wpgv_stripe_alternative_text') : 'Stripe';
    $wpgv_termstext = get_option('wpgv_termstext') ? get_option('wpgv_termstext') : __('I here by accept the <a href="https://wordpress.org/about/privacy/" target="_blank">terms and conditions</a>, the revocation of the privacy policy and confirm that all information is correct.','gift-voucher');
    $paymenyGateway = '';
    if($setting_options->paypal || $setting_options->sofort || $setting_options->stripe || $setting_options->per_invoice || $wpgv_multisafepay){
        $paymenyGateway .= '<div class="" id="wpgv_payment_gateway">';
        $paymenyGateway .= '<label>'.__('Payment Method', 'gift-voucher').'</label>';
        $paymenyGateway .= '<select name="payment_gateway" id="payment_gateway" class="form-field">';
        $paymenyGateway .= $setting_options->paypal ? '<option value="Paypal">'.$wpgv_paypal_alternative_text.'</option>' : '';
        $paymenyGateway .= $setting_options->stripe ? '<option value="Stripe">'.$wpgv_stripe_alternative_text.'</option>' : '';
        $paymenyGateway .= $wpgv_multisafepay ? '<option value="MultiSafepay">'.$wpgv_multisafepay_alternative_text.'</option>' : '';
        $paymenyGateway .= $setting_options->sofort ? '<option value="Sofort">'.__('Sofort', 'gift-voucher').'</option>' : '';
        $paymenyGateway .= $setting_options->per_invoice ? '<option value="Per Invoice">'.__('Per Invoice', 'gift-voucher').'</option>' : '';
        $paymenyGateway .= '</select>';        
        $paymenyGateway .= '</div>';
        $paymenyGateway .= '<div class="acceptVoucherTerms">
            <label><input type="checkbox" class="required" name="acceptVoucherTerms"> '.stripslashes($wpgv_termstext).'</label>
        </div>';
    }
    return $paymenyGateway;
}
// show shipping method
function show_shipping_method_voucher(){
    $setting_options = get_data_settings_voucher();
    $shipping_methods = explode(',', $setting_options->shipping_method);    
    $shipping_methods_string = '';
    $shipping_methods_string .= '<div class="shipping-name">
        <div class="voucher-template-input">
            <input value="" id="voucher_shipping_first" placeholder="'.__('First name','gift-voucher').'" name="voucher_shipping_first" type="text" class="input-info-voucher">
            <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
        </div>
        <div class="voucher-template-input">
            <input value="" id="voucher_shipping_last" placeholder="'.__('Last name','gift-voucher').'" name="voucher_shipping_last" type="text" class="input-info-voucher">
            <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
        </div>
    </div>
    <div class="shipping-address">
        <div class="voucher-template-input">
            <input value="" id="voucher_shipping_address" placeholder="'.__('Address','gift-voucher').'" name="voucher_shipping_address" type="text" class="input-info-voucher">
            <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
        </div>
    </div>
    <div class="shipping-city">
        <div class="voucher-template-input">
            <input value="" id="voucher_shipping_city" placeholder="'.__('City','gift-voucher').'" name="voucher_shipping_city" type="text" class="input-info-voucher">
            <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
        </div>
    </div>
    <div class="shipping-postcode">
        <div class="voucher-template-input">
            <input value="" id="voucher_shipping_postcode" placeholder="'.__('Postcode','gift-voucher').'" name="voucher_shipping_postcode" type="text" class="input-info-voucher">
            <span class="error-input">'.__('This field is required.','gift-voucher').'</span>
        </div>
    </div>    
    <div class="shipping-method">';
    foreach ($shipping_methods as $key => $method) {
        if($method != ''){
            $shipping_method = explode(':', $method);
            $shipping_methods_string .= '<label data-value="'.trim(stripslashes($shipping_method[0])).'"><input type="radio" name="shipping_method" value="'.trim(stripslashes($shipping_method[1])).'" class="radio-field"> '.trim(stripslashes($shipping_method[1])).'</label>';
        }
    }
    $shipping_methods_string .= '</div>';
    return $shipping_methods_string;
}
// show order detail
function show_order_detail_voucher(){
    $setting_options = get_data_settings_voucher();
    $wpgv_additional_charges_text = get_option('wpgv_additional_charges_text_voucher') ? get_option('wpgv_additional_charges_text_voucher') : __('Additional Website Charges','gift-voucher');
    $wpgv_add_extra_charges = get_option('wpgv_add_extra_charges_voucher') ? get_option('wpgv_add_extra_charges_voucher') : 0;    
    $html = '';
    $html .= '<div class="order-detail-voucher-template">';
        $html .= '<div class="order-info">';
            $html .= '<h6 class="title-order">'.__('Your order','gift-voucher').'</h6>';
        $html .= '</div>';
        $html .= '<div class="order-info">';
            $html .= '<div class="order-info-content name-voucher">';
                $html .= '<h5 class="title-order">'.__('Gift voucher','gift-voucher').'</h5>';
            $html .= '</div>';
            $html .= '<div class="price-voucher '.get_position_currency_giftcard().'">';            
                $html .= '<span class="currency">'.$setting_options->currency.'</span>';            
                $html .= '<span class="currency-price-value"></span>';            
            $html .= '</div>';
            $html .= '<div class="order-info-name">'.__('Your name:','gift-voucher').'<span class="order-your-name"></span></h5></div>';
        $html .= '</div>';
        $html .= '<div class="order-info">';
            $html .= '<h5 class="title-order">'.$wpgv_additional_charges_text.'</h5>';
            $html .= '<div class="price-voucher-extra-charges '.get_position_currency_giftcard().'">';  
                $html .= '<span class="currency">'.$setting_options->currency.'</span>';            
                $html .= '<span class="currency-price-extra_charges">'.$wpgv_add_extra_charges.'</span>';           
            $html .= '</div>';
        $html .= '</div>'; 
        if (!empty($setting_options->post_shipping)) { 
            $html .= '<div class="order-info order-info-shipping">';
                $html .= '<h5 class="title-order">'.__('Shipping','gift-voucher').'</h5>';
                $html .= '<div class="price-voucher-shipping '.get_position_currency_giftcard().'">'; 
                    $html .= '<span class="currency">'.$setting_options->currency.'</span>';            
                    $html .= '<span class="currency-price-shipping"></span>';                     
                $html .= '</div>';
            $html .= '</div>';  
        }
        $html .= '<div class="order-info order-info-total">';
            $html .= '<h5 class="total-order">'.__('Total','gift-voucher').'</h5>';
            $html .= '<div class="price-voucher-total '.get_position_currency_giftcard().'">';                      
                $html .= '<span class="currency">'.$setting_options->currency.'</span>';                      
                $html .= '<span class="price-total"></span>';                      
            $html .= '</div>';
        $html .= '</div>';     
    $html .= '</div>';
    return $html;
}
// Overview voucher
function show_overview_voucher_template(){
    $setting_options = get_data_settings_voucher();
    $html = '';
    $html .= '<div class="overview_voucher_template">';
        $html .= '<div class="order-voucher order-voucher-price '.get_position_currency_giftcard().'">';
            $html .= '<span>'.__('Voucher value','gift-voucher').'</span>';
            $html .= '<p class="value-price-voucher">'.$setting_options->currency.'<span class="price"></span></p>';
        $html .= '</div>';
        $html .= '<div class="order-voucher order-voucher-email">';
            $html .= '<span>'.__('Your Email','gift-voucher').'</span>';
            $html .= '<p class="value-you-email"><span class="email"></span></p>';
        $html .= '</div>';
        $html .= '<div class="order-voucher order-voucher-recipient-email">';
            $html .= '<span>'.__('Recipient Email','gift-voucher').'</span>';
            $html .= '<p class="value-recipient-email"><span class="recipient-email"></span></p>';
        $html .= '</div>';
        $html .= '<div class="order-voucher order-voucher-full-name">';
            $html .= '<span>'.__('Full Name','gift-voucher').'</span>';
            $html .= '<p class="value-full-name"><span class="full-name"></span></p>';
        $html .= '</div>';
        $html .= '<div class="order-voucher order-voucher-address">';
            $html .= '<span>'.__('Address','gift-voucher').'</span>';
            $html .= '<p class="value-address-voucher"><span class="address-voucher"></span></p>';
        $html .= '</div>';
        $html .= '<div class="order-voucher order-voucher-city">';
            $html .= '<span>'.__('City','gift-voucher').'</span>';
            $html .= '<p class="value-city-voucher"><span class="city-voucher"></span></p>';
        $html .= '</div>';
        $html .= '<div class="order-voucher order-voucher-postcode">';
            $html .= '<span>'.__('Postcode','gift-voucher').'</span>';
            $html .= '<p class="value-postcode-voucher"><span class="postcode-voucher"></span></p>';
        $html .= '</div>';
        $html .= '<div class="order-voucher order-voucher-shipping">';
            $html .= '<span>'.__('Shipping','gift-voucher').'</span>';
            $html .= '<p class="value-shipping-voucher"><span class="shipping-voucher"></span></p>';
        $html .= '</div>';
        $html .= '<div class="order-voucher order-voucher-payment-method">';
            $html .= '<span>'.__('Paymet Method','gift-voucher').'</span>';
            $html .= '<p class="value-payment-method-voucher"><span class="payment-method-voucher"></span></p>';
        $html .= '</div>';
    $html .= '</div>';
    return $html;
}
//upload images svg
function custom_mtypes( $m ){
    $m['svg'] = 'image/svg+xml';
    $m['svgz'] = 'image/svg+xml';
    $m['csv'] = 'text/csv'; //upload file csv
    $m['xml'] = 'text/xml';//upload file xml
    return $m;
}
add_filter( 'upload_mimes', 'custom_mtypes' ); 
//format template mode giftcard
function get_value_mode_gift_card_function(){
    $mode_giftcard = (get_option('wpgv_template_mode_giftcard') != '') ? get_option('wpgv_template_mode_giftcard') : 'both_giftcard';
    return $mode_giftcard;
}
//function format_categories_function 
function format_categories_function(){
    $type = get_value_mode_gift_card_function();
    $html = '';
    $html .= '<div class="format-category-voucher-template">                
            <h6 class="title-h6-voucher">'.__('Format','gift-voucher').'</h6>
            <ul class="format-category-voucher">';
                if ($type == 'portrait_giftcard') {
                    $html .= '<li data-type="portrait" class="layout-type active">
                        <a href="javascript:;" class="portrait" title="'.esc_attr('portrait','gift-voucher').'"></a>
                    </li>';                    
                }elseif ($type == 'landscape_giftcard') {
                    $html .= '<li data-type="landscape" class="layout-type active">
                        <a href="javascript:;" class="landscape" title="'.esc_attr('landscape','gift-voucher').'"></a>
                    </li>';
                }else{
                    $html .= '<li data-type="portrait" class="layout-type active">
                        <a href="javascript:;" class="portrait" title="'.esc_attr('portrait','gift-voucher').'"></a>
                    </li>
                    <li data-type="landscape" class="layout-type template_mode_active">
                        <a href="javascript:;" class="landscape" title="'.esc_attr('landscape','gift-voucher').'"></a>
                    </li>';
                }
            $html .= '</ul>
        </div>
        <div class="voucher-category-selection-wrap">
            <div class="voucher-category-main">
                <h6 class="title-h6-voucher">'.__('Category','gift-voucher').'</h6>
                <ul class="list-category-voucher">
                    <li class="category-nav-item active">
                        <a href="#all" class="category-voucher-item" data-category-id="0">'.__('All','gift-voucher').'</a>
                    </li>';
                    $category_voucher = get_terms( array(
                        'taxonomy' => 'category_voucher_template',
                        'hide_empty' => false,
                    ) );
                    if (!empty($category_voucher)) {
                        foreach ($category_voucher as $key => $category) {
                            $html .='<li class="category-nav-item"><a class="category-voucher-item" data-category-id='.$category->term_id.'>'.$category->name.'</a></li>';
                        }
                    }
                $html .= '</ul>
            </div>
        </div>';
    return $html;
}
//function check price left/right
function get_position_currency_giftcard(){
    $setting_options = get_data_settings_voucher();
    $class = "";
    if ($setting_options->currency_position == 'Left') {
        $class = 'currency_left';
    }else{
        $class = 'currency_right';
    }
    return $class;
}