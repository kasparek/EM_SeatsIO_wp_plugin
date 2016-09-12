<?php
class EM_Seatsio_ticket_booking
{

    public static function init()
    {
        add_action('em_ticket_booking_save', array('EM_Seatsio_ticket_booking', 'ticket_booking_save'), 10, 2);
    }

    /**
     * # Update booking
     * @param  boolean   $result success of previous action
     * @param  object    $EM_Ticket_Booking
     * @return boolean   Action success
     */
    public static function ticket_booking_save($result, $EM_Ticket_Booking)
    {
        global $wpdb;
        if (!$result) {
            return $result;
        }
        if (empty($EM_Ticket_Booking->ticket->ticket_meta['seatsio_category'])) {
            if ($meta = EM_Seatsio::get_ticket_meta($EM_Ticket_Booking->ticket_id)) {
                $seatsio_category = $meta['seatsio_category'];
            }
        } else {
            $seatsio_category = $EM_Ticket_Booking->ticket->ticket_meta['seatsio_category'];
        }

        $client                    = EM_Seatsio::getAPIClient();
        $data                      = array();
        $data['ticket_id']         = $EM_Ticket_Booking->ticket_id;
        $data['booking_id']        = $EM_Ticket_Booking->booking_id;
        $data['ticket_booking_id'] = $EM_Ticket_Booking->ticket_booking_id;

        if (!empty($EM_Ticket_Booking->seatsio_event_key)) {
            $data['event_key'] = $EM_Ticket_Booking->seatsio_event_key;
        } else {
            if (empty($EM_Ticket_Booking->booking->event->post_id)) {
                return false;
            }
            $data['event_key'] = EM_Seatsio::event_key($EM_Ticket_Booking->booking->event->post_id);
        }
        if (!empty($EM_Ticket_Booking->seatsio_seats)) {
            $post_seats = $EM_Ticket_Booking->seatsio_seats;
        }
        if (!empty($_POST['em_seatsio_bookings'])) {
            $post_seats = $_POST['em_seatsio_bookings'];
        }

        //if empty $post_seats check if any in db and if there are, release bookings
        if ($EM_Ticket_Booking->booking->status == 1) {
            $q = "select event_key,seat_key from " . EM_SEATSIO_BOOKING . " where ticket_id = '" . $data['ticket_id'] . "' and booking_id = '" . $data['booking_id'] . "'";
            if ($seats_booked = $wpdb->get_results($q, OBJECT)) {
                $seats = array();
                foreach ($seats_booked as $seat_booked) {
                    if (empty($seats[$seat_booked->event_key])) {
                        $seats[$seat_booked->event_key] = array();
                    }

                    $seats[$seat_booked->event_key][] = $seat_booked->seat_key;
                }
                foreach ($seats as $event => $s) {
                    $client->release($event, $s);
                }
                $wpdb->delete(EM_SEATSIO_BOOKING, array('ticket_id' => $data['ticket_id'], 'booking_id' => $data['booking_id']));
            }
        }
        $client        = EM_Seatsio::getAPIClient();
        $seats         = $client->report($data['event_key'], 'byUuid');
        $seats_to_book = array();
        if (!empty($post_seats)) {
            foreach ($post_seats as $uuid => $label) {
                //validate uuid with chart
                if (!empty($seats->$uuid)) {
                    $seat = $seats->$uuid;
                    if ($seat->status != 'booked' && $seat->categoryKey == $seatsio_category) {
                        $data['seat_key'] = $uuid;
                        if ($wpdb->get_var("SELECT count(1) FROM " . EM_SEATSIO_BOOKING . " WHERE seat_key = '" . $data['seat_key'] . "' and event_key='" . $data['event_key'] . "' LIMIT 1") == 0) {
                            $wpdb->insert(EM_SEATSIO_BOOKING, $data);
                            if ($EM_Ticket_Booking->booking->status == 1) {
                                if (empty($seats_to_book[$data['event_key']])) {
                                    $seats_to_book[$data['event_key']] = array();
                                }
                                $seats_to_book[$data['event_key']][] = $data['seat_key'];
                            }
                        }
                    }
                }
            }
            if (!empty($seats_to_book)) {
                foreach ($seats_to_book as $event => $s) {
                    $client->book($event, $s);
                }
            }
        }
        return true;
    }
}
