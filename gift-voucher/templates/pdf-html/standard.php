<?php
if (!defined('ABSPATH')) {
    exit;
}

$box_top_offset = 4;
$single_line_box_padding_top = function ($box) {
    $height = isset($box['h']) ? intval($box['h']) : 0;
    $font_size = isset($box['font_size']) ? intval($box['font_size']) : 0;

    return max(0, intval(floor(($height - $font_size) / 2)) - 1);
};
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: "DejaVu Sans", sans-serif;
        }

        .page {
            background: <?php echo esc_attr(isset($page_background_color_css) ? $page_background_color_css : '#ffffff'); ?>;
            box-sizing: border-box;
            color: <?php echo esc_attr($text_color_css); ?>;
            height: <?php echo intval($layout['page_height']); ?>pt;
            overflow: hidden;
            position: relative;
            width: <?php echo intval($layout['page_width']); ?>pt;
        }

        .fill-section {
            background: <?php echo esc_attr($background_color_css); ?>;
            height: <?php echo intval($layout['style'] === 0 ? ($layout['page_height'] - $layout['image']['h']) : $layout['page_height']); ?>pt;
            left: 0;
            position: absolute;
            top: <?php echo intval($layout['style'] === 0 ? $layout['image']['h'] : 0); ?>pt;
            width: <?php echo intval($layout['page_width']); ?>pt;
            z-index: 0;
        }

        .bg-image {
            height: <?php echo intval($layout['image']['h']); ?>pt;
            left: <?php echo intval($layout['image']['x']); ?>pt;
            object-fit: fill;
            position: absolute;
            top: <?php echo intval($layout['image']['y']); ?>pt;
            width: <?php echo intval($layout['image']['w']); ?>pt;
            z-index: 1;
        }

        .title,
        .description,
        .label,
        .footer,
        .notice,
        .watermark {
            position: absolute;
            z-index: 3;
        }

        .title {
            font-size: <?php echo intval($layout['title']['font_size']); ?>pt;
            font-weight: 700;
            left: <?php echo intval($layout['title']['x']); ?>pt;
            line-height: <?php echo intval($layout['title']['line_height']); ?>pt;
            text-align: <?php echo esc_attr($layout['title']['align']); ?>;
            top: <?php echo intval($layout['title']['y']); ?>pt;
            width: <?php echo intval($layout['title']['w']); ?>pt;
        }

        .description {
            font-size: <?php echo intval($layout['description']['font_size']); ?>pt;
            left: <?php echo intval($layout['description']['x']); ?>pt;
            line-height: <?php echo intval($layout['description']['line_height']); ?>pt;
            text-align: <?php echo esc_attr($layout['description']['align']); ?>;
            top: <?php echo intval($layout['description']['y']); ?>pt;
            width: <?php echo intval($layout['description']['w']); ?>pt;
        }

        .label {
            line-height: 1;
            font-weight: 600;
        }

        .box {
            background: #ffffff;
            box-sizing: border-box;
            color: #555555;
            overflow: hidden;
            position: absolute;
            z-index: 2;
        }

        .field-box {
            padding: 0 0 0 4pt;
            text-align: left;
            white-space: nowrap;
        }

        .message-box {
            padding: 2pt 3pt 0 2pt;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .code-box {
            padding: 0 0 0 4pt;
            text-align: left;
            white-space: nowrap;
        }

        .barcode {
            left: <?php echo intval($layout['barcode']['x']); ?>pt;
            position: absolute;
            top: <?php echo intval($layout['barcode']['y']); ?>pt;
            width: <?php echo intval($layout['barcode']['w']); ?>pt;
            z-index: 2;
        }

        .barcode img {
            display: block;
            height: <?php echo intval($layout['barcode']['h']); ?>pt;
            width: <?php echo intval($layout['barcode']['w']); ?>pt;
        }

        .footer {
            font-size: <?php echo intval($layout['footer']['font_size']); ?>pt;
            left: <?php echo intval($layout['footer']['x']); ?>pt;
            text-align: center;
            top: <?php echo intval($layout['footer']['y']); ?>pt;
            width: <?php echo intval($layout['footer']['w']); ?>pt;
        }

        .notice {
            font-size: <?php echo intval($layout['notice']['font_size']); ?>pt;
            left: <?php echo intval($layout['notice']['x']); ?>pt;
            position: absolute;
            text-align: <?php echo esc_attr(isset($layout['notice']['align']) ? $layout['notice']['align'] : 'left'); ?>;
            top: <?php echo intval($layout['notice']['y']); ?>pt;
            transform: rotate(<?php echo intval($layout['notice']['rotate']); ?>deg);
            transform-origin: top left;
            width: <?php echo intval(isset($layout['notice']['w']) ? $layout['notice']['w'] : 0); ?>pt;
            white-space: <?php echo intval($layout['notice']['rotate']) === 0 ? 'normal' : 'nowrap'; ?>;
        }

        .watermark {
            color: #d7d7d7;
            font-size: <?php echo intval($layout['watermark']['font_size']); ?>pt;
            font-weight: 700;
            left: <?php echo intval($layout['watermark']['x']); ?>pt;
            opacity: 0.9;
            top: <?php echo intval($layout['watermark']['y']); ?>pt;
            transform: rotate(<?php echo intval($layout['watermark']['rotate']); ?>deg);
            transform-origin: top left;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="fill-section"></div>
        <?php if ($image_src !== '') : ?>
        <img class="bg-image" src="<?php echo esc_attr($image_src); ?>" alt="">
        <?php endif; ?>

        <?php if ($title !== '') : ?>
        <div class="title"><?php echo esc_html($title); ?></div>
        <?php endif; ?>

        <?php if ($description !== '') : ?>
        <div class="description"><?php echo nl2br(esc_html($description)); ?></div>
        <?php endif; ?>

        <?php if (!$compact_mode) : ?>
        <div class="label" style="left: <?php echo intval($layout['for_label']['x']); ?>pt; top: <?php echo intval($layout['for_label']['y']); ?>pt; font-size: <?php echo intval($layout['for_label']['font_size']); ?>pt;"><?php echo esc_html__('Your Name', 'gift-voucher'); ?></div>
        <div class="box field-box" style="left: <?php echo intval($layout['for_box']['x']); ?>pt; top: <?php echo intval($layout['for_box']['y'] + $box_top_offset); ?>pt; width: <?php echo intval($layout['for_box']['w']); ?>pt; height: <?php echo intval($layout['for_box']['h']); ?>pt; font-size: <?php echo intval($layout['for_box']['font_size']); ?>pt; padding-top: <?php echo esc_attr(intval($single_line_box_padding_top($layout['for_box']))); ?>pt;"><?php echo esc_html($for); ?></div>

        <?php if ($buyingfor !== 'yourself') : ?>
        <div class="label" style="left: <?php echo intval($layout['recipient_label']['x']); ?>pt; top: <?php echo intval($layout['recipient_label']['y']); ?>pt; font-size: <?php echo intval($layout['recipient_label']['font_size']); ?>pt;"><?php echo esc_html__('Recipient Name', 'gift-voucher'); ?></div>
        <div class="box field-box" style="left: <?php echo intval($layout['recipient_box']['x']); ?>pt; top: <?php echo intval($layout['recipient_box']['y'] + $box_top_offset); ?>pt; width: <?php echo intval($layout['recipient_box']['w']); ?>pt; height: <?php echo intval($layout['recipient_box']['h']); ?>pt; font-size: <?php echo intval($layout['recipient_box']['font_size']); ?>pt; padding-top: <?php echo esc_attr(intval($single_line_box_padding_top($layout['recipient_box']))); ?>pt;"><?php echo esc_html($from); ?></div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!$hide_price) : ?>
        <div class="label" style="left: <?php echo intval($layout['price_label']['x']); ?>pt; top: <?php echo intval($layout['price_label']['y']); ?>pt; font-size: <?php echo intval($layout['price_label']['font_size']); ?>pt;"><?php echo esc_html__('Voucher Value', 'gift-voucher'); ?></div>
        <div class="box field-box" style="left: <?php echo intval($layout['price_box']['x']); ?>pt; top: <?php echo intval($layout['price_box']['y'] + $box_top_offset); ?>pt; width: <?php echo intval($layout['price_box']['w']); ?>pt; height: <?php echo intval($layout['price_box']['h']); ?>pt; font-size: <?php echo intval($layout['price_box']['font_size']); ?>pt; padding-top: <?php echo esc_attr(intval($single_line_box_padding_top($layout['price_box']))); ?>pt;"><?php echo esc_html($currency); ?></div>
        <?php endif; ?>

        <div class="label" style="left: <?php echo intval($layout['expiry_label']['x']); ?>pt; top: <?php echo intval($layout['expiry_label']['y']); ?>pt; font-size: <?php echo intval($layout['expiry_label']['font_size']); ?>pt;"><?php echo esc_html__('Date of Expiry', 'gift-voucher'); ?></div>
        <div class="box field-box" style="left: <?php echo intval($layout['expiry_box']['x']); ?>pt; top: <?php echo intval($layout['expiry_box']['y'] + $box_top_offset); ?>pt; width: <?php echo intval($layout['expiry_box']['w']); ?>pt; height: <?php echo intval($layout['expiry_box']['h']); ?>pt; font-size: <?php echo intval($layout['expiry_box']['font_size']); ?>pt; padding-top: <?php echo esc_attr(intval($single_line_box_padding_top($layout['expiry_box']))); ?>pt;"><?php echo nl2br(esc_html($expiry)); ?></div>

        <?php if (!$compact_mode) : ?>
        <div class="label" style="left: <?php echo intval($layout['message_label']['x']); ?>pt; top: <?php echo intval($layout['message_label']['y']); ?>pt; font-size: <?php echo intval($layout['message_label']['font_size']); ?>pt;"><?php echo esc_html__('Personal Message', 'gift-voucher'); ?></div>
        <div class="box message-box" style="left: <?php echo intval($layout['message_box']['x']); ?>pt; top: <?php echo intval($layout['message_box']['y'] + $box_top_offset); ?>pt; width: <?php echo intval($layout['message_box']['w']); ?>pt; height: <?php echo intval($layout['message_box']['h']); ?>pt; font-size: <?php echo intval($layout['message_box']['font_size']); ?>pt; line-height: <?php echo intval($layout['message_box']['line_height']); ?>pt;"><?php echo nl2br(esc_html($message)); ?></div>
        <?php endif; ?>

        <div class="label" style="left: <?php echo intval($layout['code_label']['x']); ?>pt; top: <?php echo intval($layout['code_label']['y']); ?>pt; font-size: <?php echo intval($layout['code_label']['font_size']); ?>pt;"><?php echo esc_html__('Coupon Code', 'gift-voucher'); ?></div>
        <div class="box code-box" style="left: <?php echo intval($layout['code_box']['x']); ?>pt; top: <?php echo intval($layout['code_box']['y'] + $box_top_offset); ?>pt; width: <?php echo intval($layout['code_box']['w']); ?>pt; height: <?php echo intval($layout['code_box']['h']); ?>pt; font-size: <?php echo intval($layout['code_box']['font_size']); ?>pt; padding-top: <?php echo esc_attr(intval($single_line_box_padding_top($layout['code_box']))); ?>pt;"><?php echo esc_html($code); ?></div>

        <?php if ($barcode_src !== '') : ?>
        <div class="barcode"><img src="<?php echo esc_attr($barcode_src); ?>" alt=""></div>
        <?php endif; ?>

        <div class="footer"><?php echo esc_html($footer_url . ' | ' . $footer_email); ?></div>
        <div class="notice"><?php echo esc_html('* ' . $leftside_notice); ?></div>

        <?php if ($preview && $watermark !== '') : ?>
        <div class="watermark"><?php echo esc_html($watermark); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
