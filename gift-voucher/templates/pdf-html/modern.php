<?php
if (!defined('ABSPATH')) {
    exit;
}

$background_src = isset($background_src) ? $background_src : '';
$recipient_name = isset($recipient_name) ? $recipient_name : '';
$sender_name = isset($sender_name) ? $sender_name : '';
$amount_display = isset($amount_display) ? $amount_display : '';
$message = isset($message) ? $message : '';
$coupon_code = isset($coupon_code) ? $coupon_code : '';
$expiry_display = isset($expiry_display) ? $expiry_display : '';
$barcode_src = isset($barcode_src) ? $barcode_src : '';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
        }

        html,
        body {
            background: #ffffff;
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            margin: 0;
            padding: 0;
        }

        .page {
            height: 100%;
            overflow: hidden;
            position: relative;
            width: 100%;
        }

        .background {
            height: 100%;
            left: 0;
            position: absolute;
            top: 0;
            width: 100%;
            z-index: 1;
        }

        .overlay {
            position: absolute;
            z-index: 2;
        }

        .panel {
            left: 9%;
            text-align: center;
            width: 82%;
        }

        .label {
            color: #374151;
            display: block;
            font-size: 10pt;
            font-weight: 700;
            letter-spacing: 0.4pt;
            margin-bottom: 4pt;
            text-transform: uppercase;
        }

        .value {
            background: rgba(255, 255, 255, 0.84);
            border-radius: 10pt;
            display: inline-block;
            padding: 7pt 12pt;
        }

        .to {
            top: 14%;
        }

        .from {
            top: 24%;
        }

        .amount {
            top: 34%;
        }

        .amount .value {
            font-size: 24pt;
            font-weight: 700;
            padding: 8pt 16pt;
        }

        .message {
            color: #1f2937;
            font-size: 11pt;
            left: 13%;
            line-height: 1.4;
            text-align: center;
            top: 47%;
            white-space: pre-wrap;
            width: 74%;
        }

        .code {
            top: 72%;
        }

        .code .value {
            font-size: 18pt;
            font-weight: 700;
            letter-spacing: 1pt;
        }

        .barcode {
            left: 24%;
            top: 81%;
            width: 52%;
        }

        .barcode img {
            display: block;
            height: auto;
            width: 100%;
        }

        .expiry {
            bottom: 7%;
            color: #4b5563;
            font-size: 9.5pt;
            left: 12%;
            text-align: center;
            width: 76%;
        }
    </style>
</head>
<body>
    <div class="page">
        <?php if ($background_src !== '') : ?>
        <img class="background" src="<?php echo esc_attr($background_src); ?>" alt="">
        <?php endif; ?>

        <?php if ($recipient_name !== '') : ?>
        <div class="overlay panel to">
            <span class="label"><?php echo esc_html__('Gift To', 'gift-voucher'); ?></span>
            <span class="value"><?php echo esc_html($recipient_name); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($sender_name !== '') : ?>
        <div class="overlay panel from">
            <span class="label"><?php echo esc_html__('Gift From', 'gift-voucher'); ?></span>
            <span class="value"><?php echo esc_html($sender_name); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($amount_display !== '') : ?>
        <div class="overlay panel amount">
            <span class="label"><?php echo esc_html__('Value', 'gift-voucher'); ?></span>
            <span class="value"><?php echo esc_html($amount_display); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($message !== '') : ?>
        <div class="overlay message"><?php echo nl2br(esc_html($message)); ?></div>
        <?php endif; ?>

        <?php if ($coupon_code !== '') : ?>
        <div class="overlay panel code">
            <span class="label"><?php echo esc_html__('Coupon Code', 'gift-voucher'); ?></span>
            <span class="value"><?php echo esc_html($coupon_code); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($barcode_src !== '') : ?>
        <div class="overlay barcode">
            <img src="<?php echo esc_attr($barcode_src); ?>" alt="">
        </div>
        <?php endif; ?>

        <?php if ($expiry_display !== '' && $expiry_display !== __('No Expiry', 'gift-voucher')) : ?>
        <div class="overlay expiry">
            <?php echo esc_html__('Valid until', 'gift-voucher'); ?>: <?php echo esc_html($expiry_display); ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
