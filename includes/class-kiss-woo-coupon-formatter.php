<?php
/**
 * Centralized coupon formatting for API responses.
 *
 * SINGLE WRITE PATH: All coupon-to-array conversion goes through format().
 *
 * @package KISS_Woo_Customer_Order_Search
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KISS_Woo_Coupon_Formatter {

    /**
     * Format a lookup row for JSON/API output.
     *
     * @param array $row Lookup row.
     * @return array
     */
    public static function format_from_row( array $row ): array {
        $coupon_id     = isset( $row['coupon_id'] ) ? (int) $row['coupon_id'] : 0;
        $discount_type = isset( $row['discount_type'] ) ? (string) $row['discount_type'] : '';
        $amount        = isset( $row['amount'] ) ? (float) $row['amount'] : 0.0;

        $amount_display = '';
        if ( '' !== $discount_type && false !== strpos( $discount_type, 'percent' ) ) {
            $amount_display = rtrim( rtrim( (string) $amount, '0' ), '.' ) . '%';
        } elseif ( function_exists( 'wc_price' ) ) {
            $amount_display = html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
        } else {
            $amount_display = number_format( $amount, 2 );
        }

        $expiry_date = isset( $row['expiry_date'] ) ? (string) $row['expiry_date'] : '';
        $expiry_display = '';
        if ( '' !== $expiry_date && '0000-00-00 00:00:00' !== $expiry_date ) {
            $timestamp = strtotime( $expiry_date );
            if ( $timestamp ) {
                $expiry_display = date_i18n( get_option( 'date_format' ), $timestamp );
            }
        }

        $view_url = get_edit_post_link( $coupon_id, 'raw' );
        if ( empty( $view_url ) ) {
            $view_url = admin_url( 'post.php?post=' . $coupon_id . '&action=edit' );
        }

        $source_flags = array();
        if ( ! empty( $row['source_flags'] ) ) {
            $source_flags = array_filter( array_map( 'trim', explode( ',', (string) $row['source_flags'] ) ) );
        }

        return array(
            'id'                    => $coupon_id,
            'code'                  => isset( $row['code'] ) ? (string) $row['code'] : '',
            'title'                 => isset( $row['title'] ) ? (string) $row['title'] : '',
            'description'           => isset( $row['description'] ) ? (string) $row['description'] : '',
            'discount_type'         => $discount_type,
            'amount'                => $amount,
            'amount_display'        => $amount_display,
            'expiry_date'           => $expiry_date,
            'expiry_display'        => $expiry_display,
            'usage_limit'           => isset( $row['usage_limit'] ) ? (int) $row['usage_limit'] : 0,
            'usage_limit_per_user'  => isset( $row['usage_limit_per_user'] ) ? (int) $row['usage_limit_per_user'] : 0,
            'usage_count'           => isset( $row['usage_count'] ) ? (int) $row['usage_count'] : 0,
            'free_shipping'         => ! empty( $row['free_shipping'] ),
            'status'                => isset( $row['status'] ) ? (string) $row['status'] : '',
            'source_flags'          => $source_flags,
            'view_url'              => $view_url,
        );
    }

    /**
     * Format a WC_Coupon object for JSON/API output.
     * Used by fallback search when coupon isn't in lookup table yet.
     *
     * @param WC_Coupon $coupon Coupon object.
     * @return array
     */
    public static function format_from_coupon( $coupon ): array {
        $coupon_id = $coupon->get_id();
        $discount_type = $coupon->get_discount_type();
        $amount = $coupon->get_amount();

        $amount_display = '';
        if ( '' !== $discount_type && false !== strpos( $discount_type, 'percent' ) ) {
            $amount_display = rtrim( rtrim( (string) $amount, '0' ), '.' ) . '%';
        } elseif ( function_exists( 'wc_price' ) ) {
            $amount_display = html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
        } else {
            $amount_display = number_format( $amount, 2 );
        }

        $expiry_date_obj = $coupon->get_date_expires();
        $expiry_date = '';
        $expiry_display = '';
        if ( $expiry_date_obj ) {
            $expiry_date = $expiry_date_obj->date( 'Y-m-d H:i:s' );
            $timestamp = $expiry_date_obj->getTimestamp();
            if ( $timestamp ) {
                $expiry_display = date_i18n( get_option( 'date_format' ), $timestamp );
            }
        }

        $view_url = get_edit_post_link( $coupon_id, 'raw' );
        if ( empty( $view_url ) ) {
            $view_url = admin_url( 'post.php?post=' . $coupon_id . '&action=edit' );
        }

        return array(
            'id'                    => $coupon_id,
            'code'                  => $coupon->get_code(),
            'title'                 => get_the_title( $coupon_id ),
            'description'           => $coupon->get_description(),
            'discount_type'         => $discount_type,
            'amount'                => $amount,
            'amount_display'        => $amount_display,
            'expiry_date'           => $expiry_date,
            'expiry_display'        => $expiry_display,
            'usage_limit'           => $coupon->get_usage_limit(),
            'usage_limit_per_user'  => $coupon->get_usage_limit_per_user(),
            'usage_count'           => $coupon->get_usage_count(),
            'free_shipping'         => $coupon->get_free_shipping(),
            'status'                => get_post_status( $coupon_id ),
            'source_flags'          => array( 'fallback' ), // Mark as from fallback search
            'view_url'              => $view_url,
        );
    }
}

