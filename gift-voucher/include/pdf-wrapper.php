<?php

if (!defined('ABSPATH')) exit;  // Exit if accessed directly

/**
 * WPGV PDF Wrapper Functions
 * Safe wrapper functions for PDF operations with isolation
 */

/**
 * Create PDF instance with isolation
 */
function wpgv_create_pdf_safe($orientation = 'P', $unit = 'pt', $format = 'A4')
{
    // Check if isolation class exists
    if (class_exists('WPGV_FPDF_Isolation')) {
        return wpgv_create_isolated_pdf($orientation, $unit, $format);
    }

    // Fallback to WPGV_PDF which includes Code128 and other methods
    if (class_exists('WPGV_PDF')) {
        return new WPGV_PDF($orientation, $unit, $format);
    }

    // Fallback to direct instantiation if isolation not available
    if (class_exists('WPGV_PDF_HTML_ROTATE')) {
        return new WPGV_PDF_HTML_ROTATE($orientation, $unit, $format);
    }

    // Final fallback to basic WPGV_FPDF
    if (class_exists('WPGV_FPDF')) {
        return new WPGV_FPDF($orientation, $unit, $format);
    }

    return null;
}

/**
 * Add font with isolation
 */
function wpgv_add_font_safe($pdf_instance, $family, $style = '', $file = '')
{
    if (!$pdf_instance) return false;

    // Check if isolation class exists
    if (class_exists('WPGV_FPDF_Isolation')) {
        return wpgv_add_isolated_font($pdf_instance, $family, $style, $file);
    }

    // Fallback to direct method call
    if (method_exists($pdf_instance, 'AddFont')) {
        return $pdf_instance->AddFont($family, $style, $file);
    }

    return false;
}

/**
 * Check if PDF generation is available
 */
function wpgv_pdf_available()
{
    return class_exists('WPGV_FPDF_Isolation') ||
        class_exists('WPGV_PDF') ||
        class_exists('WPGV_PDF_HTML_ROTATE') ||
        class_exists('WPGV_FPDF');
}
