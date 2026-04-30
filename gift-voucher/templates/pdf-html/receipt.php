<?php
if (!defined('ABSPATH')) {
    exit;
}

$customer_name = isset($customer_name) ? $customer_name : '';
$recipient_name = isset($recipient_name) ? $recipient_name : '';
$buyer_email = isset($buyer_email) ? $buyer_email : '';
$amount_display = isset($amount_display) ? $amount_display : '';
$coupon_code = isset($coupon_code) ? $coupon_code : '';
$expiry_display = isset($expiry_display) ? $expiry_display : '';
$payment_method = isset($payment_method) ? $payment_method : '';
$payment_status = isset($payment_status) ? $payment_status : '';
$order_number = isset($order_number) ? $order_number : '';
$order_date = isset($order_date) ? $order_date : gmdate('d.m.Y');
$company_name = isset($company_name) ? $company_name : '';
$company_email = isset($company_email) ? $company_email : '';
$company_website = isset($company_website) ? $company_website : '';
$buying_for = isset($buying_for) ? $buying_for : 'someone_else';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 32px 36px;
        }

        body {
            color: #1f2937;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.45;
            margin: 0;
        }

        .receipt-title {
            border-bottom: 2px solid #e5e7eb;
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 24px;
            padding-bottom: 12px;
            text-align: center;
        }

        .receipt-table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .receipt-table th,
        .receipt-table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 0;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        .receipt-table th {
            color: #111827;
            font-weight: 700;
            width: 34%;
        }

        .receipt-table td {
            color: #374151;
            width: 66%;
        }
    </style>
</head>
<body>
    <h1 class="receipt-title"><?php echo esc_html__('Customer Receipt', 'gift-voucher'); ?></h1>

    <table class="receipt-table">
        <tr>
            <th><?php echo esc_html__('Company Name', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($company_name); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Company Email', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($company_email); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Company Website', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($company_website); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Order Number', 'gift-voucher'); ?></th>
            <td><?php echo esc_html('#' . $order_number); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Order Date', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($order_date); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Your Name', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($customer_name); ?></td>
        </tr>
        <?php if ($buying_for !== 'yourself') : ?>
        <tr>
            <th><?php echo esc_html__('Recipient Name', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($recipient_name); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th><?php echo esc_html__('Email', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($buyer_email); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Amount', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($amount_display); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Coupon Code', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($coupon_code); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Coupon Expiry date', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($expiry_display); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Payment Method', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($payment_method); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html__('Payment Status', 'gift-voucher'); ?></th>
            <td><?php echo esc_html($payment_status); ?></td>
        </tr>
    </table>
</body>
</html>
