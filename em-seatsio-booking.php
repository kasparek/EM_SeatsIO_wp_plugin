<?php
class EM_Seatsio_booking
{

    public static function init()
    {
        add_action('em_booking_get_post', array('EM_Seatsio_booking', 'booking_get_post'), 10, 2);
        add_action('em_booking_set_status', array('EM_Seatsio_booking', 'booking_set_status'), 10, 2);
        add_action('em_booking_delete', array('EM_Seatsio_booking', 'booking_delete'), 10, 2);
    }

    /**
     * # Collect custom booking data
     * @param  boolean   $result success of previous action
     * @param  object    $EM_Booking
     * @return boolean   Data collection success
     */
    public static function booking_get_post($result, $EM_Booking)
    {
        if (!$result) {
            return false;
        }
        global $wpdb;
        $event_id     = $EM_Booking->event_id;
        $post_id      = $wpdb->get_var("select post_id from " . EM_EVENTS_TABLE . " where event_id='" . $event_id . "' limit 1");
        $event_key    = $wpdb->get_var("SELECT event_key FROM " . EM_SEATSIO_EVENT . " WHERE post_id='" . $post_id . "' LIMIT 1");
        $seats_report = EM_Seatsio::get_seats_report($post_id, $event_key);
        $tb           = $EM_Booking->get_tickets_bookings();
        $tb->rewind();
        while ($tb->valid()) {
            $ticket = $tb->current();
            if (!empty($_POST['em_seatsio_bookings'])) {
                foreach ($_POST['em_seatsio_bookings'] as $uuid => $label) {
                    if (!empty($seats_report->$uuid)) {
                        $seat = $seats_report->$uuid;
                        if (!empty($ticket->ticket) && $seat->categoryKey == $ticket->ticket->ticket_meta['seatsio_category']) {
                            if (empty($ticket->ticket->ticket_meta['seatsio_seats'])) {
                                $ticket->ticket->ticket_meta['seatsio_seats'] = array();
                            }
                            $ticket->seatsio_event_key = $event_key;
                            if (empty($ticket->seatsio_seats)) {
                                $ticket->seatsio_seats = array();
                            }

                            $ticket->seatsio_seats[$uuid] = $label;
                        }
                    }
                }
            }
            $tb->next();
        }
        return true;
    }

    /**
     * # Update status of booking
     * @param  boolean   $result success of previous action
     * @param  object    $EM_Booking
     * @return boolean   Action success
     */
    public static function booking_set_status($result, $EM_Booking)
    {
        global $wpdb;
        if (!$result) {
            return false;
        }
        $bookings       = array($EM_Booking);
        $booking_status = $EM_Booking->booking_status; //for multiple bookings only top status is updated at first call
        if (is_a($EM_Booking, 'EM_Multiple_Booking')) {
            $bookings = $EM_Booking->get_bookings();
        }
        foreach ($bookings as $booking) {
            $client = EM_Seatsio::getAPIClient();
            $seats  = $wpdb->get_results("select * from " . EM_SEATSIO_BOOKING . " where booking_id = " . $booking->booking_id, OBJECT);
            if (!empty($seats)) {
                foreach ($seats as $seat) {
                    switch ($booking_status) {
                        case '1': //approved
                            try {
                                $client->book($seat->event_key, array($seat->seat_key));
                            } catch (Exception $e) {
                                //probably already booked
                            }
                            break;
                        case '2': //rejected
                        case '3': //canceled
                            $client->release($seat->event_key, array($seat->seat_key));
                            break;
                        case '4': //Awaiting online payment
                        case '5'; //Awaiting payment
                        case '0': //pending
                        default:
                    }
                }
            }
        }
        return true;
    }

    /**
     * # Delete booking
     * WP Admin
     * @param  boolean   $result success of previous action
     * @param  object    $EM_Booking
     * @return boolean   Delete success
     */
    public static function booking_delete($result, $EM_Booking)
    {
        if (!$result) {
            return false;
        }
        global $wpdb;
        $sql    = $wpdb->prepare("DELETE FROM " . EM_SEATSIO_BOOKING . " WHERE booking_id=%d", $EM_Booking->booking_id);
        $result = $wpdb->query($sql);
        return true;
    }
}
