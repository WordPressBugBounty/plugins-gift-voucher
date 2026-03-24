<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Helper functions for PDF generation moved out of wpgv_giftcard_pdf.php
 */

/**
 * Generate Modern Giftcard PDF for Product Page Gift Checkbox
 *
 * @param int $voucher_id Database voucher ID
 * @param object $voucher_data Voucher data from database
 * @param int $template_id Template post ID
 * @return string|false Path to generated PDF file or false on failure
 */
function wpgv_generate_modern_giftcard_pdf($voucher_id, $voucher_data, $template_id, $canvas_data = null, $canvas_file_path = '', $pdf_filename_base = '') {
    error_log('WPGV PDF: Starting PDF generation for voucher ID: ' . $voucher_id . ', Template ID: ' . $template_id . ', base file: ' . $pdf_filename_base);

    if (!$voucher_data || !$template_id) {
        error_log('WPGV PDF ERROR: Missing voucher data or template ID');
        return false;
    }

    global $wpdb;
    $voucher_table = $wpdb->prefix . 'giftvouchers_list';

    // Get upload directory
    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'] . '/voucherpdfuploads/';

    error_log('WPGV PDF: Upload directory: ' . $upload_dir);

    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        $mkdir_result = mkdir($upload_dir, 0755, true);
        error_log('WPGV PDF: Created directory: ' . $upload_dir . ' - Result: ' . ($mkdir_result ? 'SUCCESS' : 'FAILED'));
    } else {
        error_log('WPGV PDF: Directory already exists: ' . $upload_dir);
    }

    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        error_log('WPGV PDF ERROR: Directory is not writable: ' . $upload_dir);
        return false;
    }

    // Generate filename (without extension, matching existing system)
    if (!empty($pdf_filename_base)) {
        $pdf_filename_base = sanitize_file_name($pdf_filename_base);
    } else {
        $curr_time = time();
        $pdf_filename_base = $curr_time . $voucher_data->couponcode;
    }
    $pdf_filename = $pdf_filename_base . '.pdf';
    $pdf_path = $upload_dir . $pdf_filename;

    error_log('WPGV PDF: PDF filename: ' . $pdf_filename . ', Full path: ' . $pdf_path);

    // Update voucherpdf_link in database
    $update_result = $wpdb->update(
        $voucher_table,
        array('voucherpdf_link' => $pdf_filename_base),
        array('id' => $voucher_id),
        array('%s'),
        array('%d')
    );

    if ($update_result === false) {
        error_log('WPGV PDF ERROR: Failed to update voucherpdf_link in database. Error: ' . $wpdb->last_error);
    } else {
        error_log('WPGV PDF: Updated voucherpdf_link in database: ' . $pdf_filename_base);
    }

    try {
        // If a canvas file path is provided and exists, use it directly (no re-write into uploads root)
        if (!empty($canvas_file_path) && file_exists($canvas_file_path)) {
            error_log('WPGV PDF: Using existing canvas file path: ' . $canvas_file_path);

            // Use isolated PDF generation
            require_once(WPGIFT__PLUGIN_DIR . '/include/pdf-wrapper.php');

            $pdf = wpgv_create_pdf_safe();
            if (!$pdf) {
                error_log('WPGV PDF ERROR: Failed to create PDF instance');
                return false;
            }

            // Determine orientation from template
            $template_style = get_post_meta($template_id, 'wpgv_customize_template_template-style', true);
            $choose_template = get_post_meta($template_id, 'wpgv_customize_template_chosse_template', true);

            // Add page using standard A4 and let PDF instance report exact sizes
            if (strpos($template_style, 'lanscape') !== false || $choose_template == 'lanscape') {
                $pdf->AddPage("L", 'a4');
                error_log('WPGV PDF: Using landscape orientation');
            } else {
                $pdf->AddPage('P', 'a4');
                error_log('WPGV PDF: Using portrait orientation');
            }

            // Get page dimensions from PDF instance
            $page_width = $pdf->GetPageWidth();
            $page_height = $pdf->GetPageHeight();
            error_log('WPGV PDF: Page dimensions (mm): ' . $page_width . 'x' . $page_height);

            if (!file_exists($canvas_file_path)) {
                error_log('WPGV PDF ERROR: Canvas image file does not exist: ' . $canvas_file_path);
                return false;
            }

            list($img_pixel_width, $img_pixel_height) = getimagesize($canvas_file_path);
            if (empty($img_pixel_width) || empty($img_pixel_height) || $img_pixel_height <= 0) {
                error_log('WPGV PDF ERROR: Invalid canvas image dimensions - width: ' . $img_pixel_width . ', height: ' . $img_pixel_height);
                return false;
            }

            // Calculate proportions - prefer FULL WIDTH, scale height to maintain aspect ratio, center both axes.
            // If scaled height exceeds page height, fall back to fitting height and center horizontally.
            $img_ratio = $img_pixel_width / $img_pixel_height;
            $final_width = $page_width;
            $final_height = $final_width / $img_ratio;
            $final_x = 0;
            $final_y = 0;
            if ($final_height <= $page_height) {
                // Center vertically
                $final_x = 0;
                $final_y = ($page_height - $final_height) / 2;
            } else {
                // Height would overflow - fit to height instead and center horizontally
                $final_height = $page_height;
                $final_width = $final_height * $img_ratio;
                $final_x = ($page_width - $final_width) / 2;
                $final_y = 0;
            }

            error_log('WPGV PDF: Image ratio: ' . $img_ratio . ', Final size (mm): ' . $final_width . 'x' . $final_height . ', X offset: ' . $final_x . ', Y offset: ' . $final_y);

            // Place image scaled and centered
            $pdf->SetAutoPageBreak(false);
            $pdf->Image($canvas_file_path, $final_x, $final_y, $final_width, $final_height);
            $pdf->SetAutoPageBreak(true, 10);

            // Output PDF to file
            error_log('WPGV PDF: Outputting PDF to file: ' . $pdf_path);
            $wpgv_enable_pdf_saving = get_option('wpgv_enable_pdf_saving');
            if ($wpgv_enable_pdf_saving) {
                $pdf->Output($pdf_path, 'F');
            } else {
                $pdf->Output('F', $pdf_path);
            }

            if (file_exists($pdf_path)) {
                $file_size = filesize($pdf_path);
                error_log('WPGV PDF: PDF created successfully using canvas file. File size: ' . $file_size . ' bytes');
                return $pdf_path;
            } else {
                error_log('WPGV PDF ERROR: PDF file was not created at: ' . $pdf_path);
                return false;
            }

        } elseif (!empty($canvas_data)) {
            // Existing behaviour: decode base64 and write into uploads root (legacy fallback)
            error_log('WPGV PDF: [DEBUG] Canvas data received. Length: ' . strlen($canvas_data));
            error_log('WPGV PDF: [DEBUG] Canvas data (first 100 chars): ' . substr($canvas_data, 0, 100));

            if (preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $canvas_data, $type)) {
                $canvas_data = substr($canvas_data, strpos($canvas_data, ',') + 1);
                $canvas_data = base64_decode($canvas_data);
            } else {
                error_log('WPGV PDF ERROR: Invalid canvas data format');
            }

            if ($canvas_data !== false && !empty($canvas_data)) {
                // Save canvas image to temp file inside uploads root (legacy)
                $canvas_image_path = $upload_dir . 'temp_canvas_' . $voucher_id . '.png';
                $bytes_written = file_put_contents($canvas_image_path, $canvas_data);
                error_log('WPGV PDF: [DEBUG] file_put_contents result: ' . var_export($bytes_written, true));

                if (file_exists($canvas_image_path)) {
                    // proceed with same logic as above but using $canvas_image_path
                    require_once(WPGIFT__PLUGIN_DIR . '/include/pdf-wrapper.php');
                    $pdf = wpgv_create_pdf_safe();
                    if (!$pdf) {
                        error_log('WPGV PDF ERROR: Failed to create PDF instance');
                        return false;
                    }

                    $template_style = get_post_meta($template_id, 'wpgv_customize_template_template-style', true);
                    $choose_template = get_post_meta($template_id, 'wpgv_customize_template_chosse_template', true);
                    if (strpos($template_style, 'lanscape') !== false || $choose_template == 'lanscape') {
                        $pdf->AddPage("L", 'a4');
                    } else {
                        $pdf->AddPage('P', 'a4');
                    }

                    $page_width = $pdf->GetPageWidth();
                    $page_height = $pdf->GetPageHeight();
                    list($img_pixel_width, $img_pixel_height) = getimagesize($canvas_image_path);
                    $img_ratio = $img_pixel_width / $img_pixel_height;
                    $final_width = $page_width;
                    $final_height = $final_width / $img_ratio;
                    $final_x = 0;
                    $final_y = 0;
                    if ($final_height <= $page_height) {
                        $final_x = 0;
                        $final_y = ($page_height - $final_height) / 2;
                    } else {
                        $final_height = $page_height;
                        $final_width = $final_height * $img_ratio;
                        $final_x = ($page_width - $final_width) / 2;
                        $final_y = 0;
                    }
                    $pdf->SetAutoPageBreak(false);
                    $pdf->Image($canvas_image_path, $final_x, $final_y, $final_width, $final_height);
                    $pdf->SetAutoPageBreak(true, 10);

                    error_log('WPGV PDF: Outputting PDF to file: ' . $pdf_path);
                    $wpgv_enable_pdf_saving = get_option('wpgv_enable_pdf_saving');
                    if ($wpgv_enable_pdf_saving) {
                        $pdf->Output($pdf_path, 'F');
                    } else {
                        $pdf->Output('F', $pdf_path);
                    }

                    if (file_exists($pdf_path)) {
                        $file_size = filesize($pdf_path);
                        error_log('WPGV PDF: PDF created successfully using canvas. File size: ' . $file_size . ' bytes');
                        return $pdf_path;
                    }
                }
            }
            error_log('WPGV PDF ERROR: Failed to generate PDF from canvas data');
            // Fall through to legacy image/template method
        }

        // Legacy method: Use template image with text overlay (fallback for non-Konva templates)
        error_log('WPGV PDF: Using legacy PDF generation method');

        // Use isolated PDF generation
        require_once(WPGIFT__PLUGIN_DIR . '/include/pdf-wrapper.php');

        error_log('WPGV PDF: Creating PDF instance...');
        $pdf = wpgv_create_pdf_safe();

        if (!$pdf) {
            error_log('WPGV PDF ERROR: Failed to create PDF instance');
            return false;
        }

        error_log('WPGV PDF: PDF instance created successfully');

        // Determine page orientation based on template
        $template_style = get_post_meta($template_id, 'wpgv_customize_template_template-style', true);
        $choose_template = get_post_meta($template_id, 'wpgv_customize_template_chosse_template', true);

        error_log('WPGV PDF: Template style: ' . $template_style . ', Choose template: ' . $choose_template);

        if (strpos($template_style, 'lanscape') !== false || $choose_template == 'lanscape') {
            $pdf->AddPage("L", 'a4');
            $page_width = 297; // mm
            $page_height = 210; // mm
            error_log('WPGV PDF: Using landscape orientation');
        } else {
            $pdf->AddPage('P', 'a4');
            $page_width = 210; // mm
            $page_height = 297; // mm
            error_log('WPGV PDF: Using portrait orientation');
        }

        // Get template image
        error_log('WPGV PDF: Getting template image path...');
        $template_image_path = wpgv_get_template_image_path($template_id);

        if ($template_image_path && file_exists($template_image_path)) {
            error_log('WPGV PDF: Template image found: ' . $template_image_path);
            // Add template image to PDF as background
            $pdf->Image($template_image_path, 0, 0, $page_width, $page_height);
        }

        // Add text overlays on the template
        // Note: Modern templates use Konva JSON with pixel coordinates
        // We'll use approximate centered positions for standard elements

        // Set font
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(0, 0, 0);

        // Add recipient name (top area, centered)
        if (!empty($voucher_data->to_name)) {
            $pdf->SetXY(20, 40);
            $pdf->Cell($page_width - 40, 10, 'To: ' . $voucher_data->to_name, 0, 1, 'C');
        }

        // Add sender name
        if (!empty($voucher_data->from_name)) {
            $pdf->SetXY(20, 55);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell($page_width - 40, 10, 'From: ' . $voucher_data->from_name, 0, 1, 'C');
        }

        // Add amount (center area, large)
        if (!empty($voucher_data->amount)) {
            $pdf->SetXY(20, 80);
            $pdf->SetFont('Arial', 'B', 24);
            $currency_symbol = get_option('wpgv_currency_symbol', '€');
            $pdf->Cell($page_width - 40, 15, $currency_symbol . number_format($voucher_data->amount, 2), 0, 1, 'C');
        }

        // Add message (if provided)
        if (!empty($voucher_data->message)) {
            $pdf->SetXY(20, 105);
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->MultiCell($page_width - 40, 5, $voucher_data->message, 0, 'C');
        }

        // Add coupon code (bottom area, prominent)
        if (!empty($voucher_data->couponcode)) {
            $y_position = $page_height - 60;
            $pdf->SetXY(20, $y_position);
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell($page_width - 40, 10, 'Coupon Code:', 0, 1, 'C');

            $pdf->SetXY(20, $y_position + 12);
            $pdf->SetFont('Arial', 'B', 18);
            $pdf->Cell($page_width - 40, 10, $voucher_data->couponcode, 0, 1, 'C');

            // Add barcode if enabled
            $wpgv_barcode_on_voucher = get_option('wpgv_barcode_on_voucher');
            if ($wpgv_barcode_on_voucher == 1) {
                $barcode_x = ($page_width - 80) / 2;
                $barcode_y = $y_position + 25;
                $pdf->SetFillColor(0, 0, 0);
                $pdf->Code128($barcode_x, $barcode_y, $voucher_data->couponcode, 80, 15);
            }
        }

        // Add expiry date (bottom)
        if (!empty($voucher_data->expiry) && $voucher_data->expiry != 'No Expiry') {
            $pdf->SetXY(20, $page_height - 20);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell($page_width - 40, 5, 'Valid until: ' . $voucher_data->expiry, 0, 1, 'C');
        }

        // Output PDF to file
        error_log('WPGV PDF: Outputting PDF to file: ' . $pdf_path);
        $wpgv_enable_pdf_saving = get_option('wpgv_enable_pdf_saving');

        if ($wpgv_enable_pdf_saving) {
            $pdf->Output($pdf_path, 'F');
        } else {
            $pdf->Output('F', $pdf_path);
        }

        // Verify PDF was created
        if (file_exists($pdf_path)) {
            $file_size = filesize($pdf_path);
            error_log('WPGV PDF: PDF created successfully. File size: ' . $file_size . ' bytes');
        } else {
            error_log('WPGV PDF ERROR: PDF file was not created at: ' . $pdf_path);
            return false;
        }

        return $pdf_path;
    } catch (Exception $e) {
        error_log('WPGV PDF ERROR: Exception during PDF generation: ' . $e->getMessage());
        error_log('WPGV PDF ERROR: Stack trace: ' . $e->getTraceAsString());
        return false;
    }
}



if (! function_exists('wpgv_get_template_image_path')) {
    /**
     * Get template image path for PDF generation
     *
     * @param int $template_id Template post ID
     * @return string|false Path to template image or false
     */
    function wpgv_get_template_image_path($template_id) {
        error_log('WPGV PDF: Getting template image path for template ID: ' . $template_id);

        $select_status_template = get_post_meta($template_id, 'wpgv_customize_template_select_template', true);
        $selected_voucher_template = get_post_meta($template_id, 'wpgv_customize_template_template-style', true);

        error_log('WPGV PDF: Select status: ' . $select_status_template . ', Template style: ' . $selected_voucher_template);

        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'] . '/voucherpdfuploads/';

        // Create temp directory if not exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if ($select_status_template == 'custom') {
            // Custom template - get from media library
            error_log('WPGV PDF: Using custom template');
            $get_bg_temp = get_post_meta($template_id, 'wpgv_customize_template_bg_result', true);
            error_log('WPGV PDF: Custom background attachment ID: ' . $get_bg_temp);

            if ($get_bg_temp) {
                // Custom template meta may be stored as attachment ID or full URL
                if (is_numeric($get_bg_temp)) {
                    $attachment_path = get_attached_file($get_bg_temp);
                    error_log('WPGV PDF: Attachment path: ' . $attachment_path);
                    if ($attachment_path && file_exists($attachment_path)) {
                        error_log('WPGV PDF: Custom template image found: ' . $attachment_path);
                        return $attachment_path;
                    } else {
                        error_log('WPGV PDF ERROR: Custom template attachment not found');
                    }
                } else {
                    // Treat as URL, download it into temp folder
                    $custom_url = $get_bg_temp;
                    error_log('WPGV PDF: Custom template URL: ' . $custom_url);
                    $tmp_file = $upload_dir . 'custom_template_' . md5($custom_url) . '.' . pathinfo($custom_url, PATHINFO_EXTENSION);
                    if (!file_exists($tmp_file)) {
                        $response = wp_remote_get($custom_url, array('timeout' => 30));
                        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                            file_put_contents($tmp_file, wp_remote_retrieve_body($response));
                        }
                    }
                    if (file_exists($tmp_file)) {
                        error_log('WPGV PDF: Custom template URL downloaded to: ' . $tmp_file);
                        return $tmp_file;
                    }
                    error_log('WPGV PDF ERROR: Custom template URL could not be downloaded');
                }
            }
        } else {
            // Default template - download from S3
            error_log('WPGV PDF: Using default template from S3');
            $template_url = 'https://gift-card-pro.s3.eu-central-1.amazonaws.com/templates/png/' . $selected_voucher_template;
            $temp_file = $upload_dir . 'temp_' . basename($selected_voucher_template);

            error_log('WPGV PDF: Template URL: ' . $template_url);
            error_log('WPGV PDF: Temp file path: ' . $temp_file);

            // Download template if not already cached
            if (!file_exists($temp_file)) {
                error_log('WPGV PDF: Template not cached, downloading from S3...');
                $response = wp_remote_get($template_url, array('timeout' => 30));

                if (is_wp_error($response)) {
                    error_log('WPGV PDF ERROR: Failed to download template. Error: ' . $response->get_error_message());
                } elseif (wp_remote_retrieve_response_code($response) == 200) {
                    $image_data = wp_remote_retrieve_body($response);
                    $bytes_written = file_put_contents($temp_file, $image_data);
                    error_log('WPGV PDF: Template downloaded successfully. Bytes written: ' . $bytes_written);
                } else {
                    $response_code = wp_remote_retrieve_response_code($response);
                    error_log('WPGV PDF ERROR: Failed to download template. HTTP code: ' . $response_code);
                }
            } else {
                error_log('WPGV PDF: Using cached template: ' . $temp_file);
            }

            if (file_exists($temp_file)) {
                error_log('WPGV PDF: Template image ready: ' . $temp_file);
                return $temp_file;
            } else {
                error_log('WPGV PDF ERROR: Template file does not exist after download attempt');
            }
        }

        error_log('WPGV PDF ERROR: Could not get template image path');
        return false;
    }
}
