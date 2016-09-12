<?php
class EM_Seatsio_ajax
{

    public static function init()
    {
        add_action('wp_ajax_em_seatsio_has_event', array('EM_Seatsio_ajax', 'has_event_json'));
        add_action('wp_ajax_em_seatsio_get_event', array('EM_Seatsio_ajax', 'event_details_json'));
        add_action('wp_ajax_em_seatsio_get_charts', array('EM_Seatsio_ajax', 'charts_json'));
        add_action('wp_ajax_em_seatsio_chart_details', array('EM_Seatsio_ajax', 'chart_json'));
        add_action('wp_ajax_em_seatsio_block_event', array('EM_Seatsio_ajax', 'block_seat_json'));
    }

    /**
     * # Check if event has related seats.io event key created
     * */
    public static function has_event_json()
    {
        global $wpdb;

        $event_id = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
        if (isset($_GET['event_id'])) {
            $event_id = (int) $_GET['event_id'];
        }
        $response          = new stdClass();
        $response->success = false;
        if ($post_id = $wpdb->get_var("select post_id from " . EM_EVENTS_TABLE . " where event_id='" . $event_id . "' limit 1")) {
            if ($event_key = $wpdb->get_var("SELECT event_key FROM " . EM_SEATSIO_EVENT . " WHERE post_id='" . $post_id . "' LIMIT 1")) {
                $response->success   = true;
                $response->event_key = $event_key;
                $response->post_id   = $post_id;
                $response->event_id  = $event_id;
            }
        }
        wp_send_json($response);
    }

    /**
     * #Get all event details
     *
     * */
    public static function event_details_json($post_id = null, $booking_id = null)
    {
        if (empty($post_id)) {
            $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            if (isset($_GET['post_id'])) {
                $post_id = (int) $_GET['post_id'];
            }
        }
        if (empty($booking_id)) {
            $booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
            if (isset($_GET['booking_id'])) {
                $booking_id = (int) $_GET['booking_id'];
            }
        }
        $response             = new stdClass();
        $options              = get_option('em_seatsio_settings');
        $response->public_key = $options["em_seatsio_public_key"];

        global $wpdb;
        if (!empty($post_id) && $event_key = $wpdb->get_var("SELECT event_key FROM " . EM_SEATSIO_EVENT . " WHERE post_id='" . $post_id . "' LIMIT 1")) {
            $event_id = $wpdb->get_var("select event_id from " . EM_EVENTS_TABLE . " where post_id='" . $post_id . "' limit 1");
            //get seats and users
            $persons           = $wpdb->get_results("select person_id,emsb.booking_id,emsb.ticket_id,emsb.seat_key from " . EM_SEATSIO_BOOKING . " as emsb join " . EM_BOOKINGS_TABLE . " as emb on emb.booking_id=emsb.booking_id  where emsb.event_key = '" . $event_key . "'", OBJECT);
            $seat_persons      = array();
            $persons_user_data = array();
            $seats_by_booking  = array();
            foreach ($persons as $person) {
                if (empty($persons_user_data[$person->person_id])) {
                    global $userpro;
                    if (!empty($userpro)) {
                        $ud                           = get_userdata($person->person_id)->data;
                        $user_data                    = new stdClass();
                        $user_data->user_id           = $person->person_id;
                        $user_data->display_name      = $ud->display_name;
                        $user_data->profile_photo_url = $userpro->profile_photo_url($person->person_id);
                        $user_data->shortbio          = $userpro->shortbio($person->person_id);
                        $user_data->permalink         = $userpro->permalink($person->person_id);
                    } else {
                        $ud                      = get_userdata($person->person_id)->data;
                        $user_data               = new stdClass();
                        $user_data->user_id      = $person->person_id;
                        $user_data->display_name = $ud->display_name;
                    }
                    $persons_user_data[$person->person_id] = $user_data;
                } else {
                    $user_data = $persons_user_data[$person->person_id];
                }
                $seat_persons[$person->seat_key] = $user_data;
                if (empty($seats_by_booking[$person->booking_id])) {
                    $seats_by_booking[$person->booking_id] = array();
                }

                if (empty($seats_by_booking[$person->booking_id][$person->ticket_id])) {
                    $seats_by_booking[$person->booking_id][$person->ticket_id] = array();
                }

                $seats_by_booking[$person->booking_id][$person->ticket_id][] = $person->seat_key;
            }

            $response                = new stdClass();
            $response->bookings      = $seats_by_booking;
            $response->booked_users  = $persons_user_data;
            $response->event_key     = $event_key;
            $client                  = EM_Seatsio::getAPIClient();
            $response->seats         = $client->report($event_key, 'byUuid');
            $booked_by_user_category = array();
            foreach ($response->seats as &$seat) {
                $ticket            = EM_Seatsio::ticket_by_seatsio_category($event_id, $seat->categoryKey);
                $seat->publicLabel = $ticket->ticket_name . ' ' . $seat->label . ' $' . number_format_i18n($ticket->ticket_price, 2);
                if ($seat->status === 'blocked' || $seat->status === 'reservedByToken') {
                    $seat->publicLabel = 'Reserved';
                }
                if ($seat->status === 'booked') {
                    $seat->publicLabel = 'Reserved';
                    if (!empty($seat_persons[$seat->uuid])) {
                        $seat->user_id = $seat_persons[$seat->uuid]->user_id;
                        $seat->publicLabel .= ': ' . $seat_persons[$seat->uuid]->display_name;
                    }
                    if (!empty($seat->user_id)) {
                        if (empty($booked_by_category[(int) $seat->categoryKey])) {
                            $booked_by_category[(int) $seat->categoryKey] = array();
                        }

                        if (empty($booked_by_category[(int) $seat->categoryKey][(int) $seat->user_id])) {
                            $booked_by_category[(int) $seat->categoryKey][(int) $seat->user_id] = array();
                        }

                        $booked_by_user_category[(int) $seat->categoryKey][(int) $seat->user_id][$seat->uuid] = $seat->label;
                    }
                }
            }
            $response->event   = $client->event($event_key);
            $response->tickets = array();
            $emt               = new EM_Tickets($event_id);
            foreach ($emt->tickets as $ticket) {
                if (!empty($ticket->ticket_meta['seatsio_category'])) {
                    $seatsio_category    = (int) $ticket->ticket_meta['seatsio_category'];
                    $response->tickets[] = array('event_id' => $ticket->event_id, 'ticket_id' => $ticket->ticket_id, 'name' => $ticket->ticket_name, 'price' => $ticket->ticket_price, 'meta' => $ticket->ticket_meta, 'booked' => isset($booked_by_user_category[$seatsio_category]) ? $booked_by_user_category[$seatsio_category] : null);
                }
            }
            $response->categories = $client->categories($response->event->chartKey);
        }
        wp_send_json($response);
    }

    /**
     * # Retrieve list of charts from seats.io
     * @return  Outputs JSON for ajax call
     * */
    public static function charts_json()
    {
        global $wpdb;
        $post_id = null;
        if (isset($_POST['post_id'])) {
            $post_id = (int) $_POST['post_id'];
        }
        if (isset($_POST['location_id'])) {
            $location_id = (int) $_POST['location_id'];
        }
        $selected = null;
        if ($location_id > 0) {
            $post_id = $wpdb->get_var("SELECT post_id FROM " . EM_LOCATIONS_TABLE . " WHERE location_id='" . $location_id . "' LIMIT 1");
        }
        if (!empty($post_id)) {
            $selected = $wpdb->get_var("SELECT chart_key FROM " . EM_SEATSIO_LOCATION . " WHERE post_id='" . $post_id . "' LIMIT 1");
        }
        wp_send_json(array('selected' => $selected, 'list' => EM_Seatsio::fetch_all_charts()));
    }

    /**
     * # Retrieve chart details from seats.io charts
     * @return  Outputs JSON for ajax call
     * */
    public static function chart_json()
    {
        $chart_key = isset($_POST['chart_key']) ? $_POST['chart_key'] : 0;
        if (empty($chart_key)) {
            $chart_key = isset($_GET['chart_key']) ? $_GET['chart_key'] : 0;
        }
        if (empty($chart_key)) {
            $response = false;
        } else {
            $response = EM_Seatsio::chart_details($chart_key);
        }
        wp_send_json($response);
    }

    /**
     * # Block custom seast
     * Only for WP Admin, block seats are not client related, only fake blocking
     * */
    public static function block_seat_json()
    {
        $response = new stdClass();
        $post_id  = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($post_id > 0) {
            $objects = $_POST['selected_objects'];
            if (!empty($objects)) {
                if ($ret = EM_Seatsio::event_block($post_id, $objects, $status)) {
                    $response->success = true;
                    $response->seats   = $ret;
                }
            }
        }
        wp_send_json($response);
    }
}
