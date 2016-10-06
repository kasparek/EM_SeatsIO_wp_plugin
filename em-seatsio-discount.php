<?php
//discount
add_action('em_booking_add', 'EM_Auto_Discount::em_booking_add', 4, 3);
add_action('em_action_emp_checkout_remove_item', 'EM_Auto_Discount::booking_update', 20, 2);
class EM_Auto_Discount
{
    public static function booking_update($result, $EM_Multiple_Booking)
    {
        if (!$result) {
            return false;
        }

        self::em_booking_add(null, null, true);
        return $result;
    }
    public static function em_booking_add($EM_Event, $EM_Booking, $post_validation)
    {
        if (!$post_validation) {
            return false;
        }
        $total = 0;
        $b     = EM_Multiple_Bookings::get_multiple_booking();
        if (!empty($b->bookings)) {
            //sum up all tickets
            foreach ($b->bookings as $booking) {
                $total += $booking->booking_spaces;
            }
        }
        if (!empty($EM_Booking)) {
            $total += $EM_Booking->booking_spaces;
        }
        //reset coupon
        unset($b->booking_meta['coupon']);
        //load all quantity coupons
        global $wpdb;
        $q = $wpdb->prepare("SELECT * FROM " . EM_COUPONS_TABLE . " WHERE coupon_name like %s", 'quantity discount%');
        $coupons = $wpdb->get_results($q);
        if (!empty($coupons)) {
            foreach ($coupons as $coupon) {
                $name   = explode(' ', $coupon->coupon_name);
                $last   = array_pop($name);
                $orMore = false;
                if (strpos($last, '+')!==false) {
                    $orMore = true;
                }
                $quantity = intval(str_replace('+', '', $last));
                if ($total === $quantity || ($orMore === true && $total >= $quantity)) {
                    //apply coupon
                    $b->booking_meta['coupon'] = (array) $coupon;
                }
            }
        }
        return true;
    }
}
