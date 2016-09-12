<?php
class EM_Seatsio_event
{

    public static function init()
    {
        add_action('em_event_save_events', array('EM_Seatsio_event', 'events_save'), 10, 4);
        add_action('em_event_save', array('EM_Seatsio_event', 'event_save'), 10, 2);
    }

    /**
     * # Create unique event key for seats.io
     * */
    public static function createSeatsioEventKey($EM_Event)
    {
        return 'wp-ems-event-' . $EM_Event->event_slug . '-pid-' . $EM_Event->post_id;
    }

    /**
     * # Create new event in seats.io
     * */
    public static function createEvent($chart_key, $event_key)
    {
        $client   = EM_Seatsio::getAPIClient();
        $response = $client->linkChartToEvent($chart_key, $event_key);
        return $response;
    }

    /**
     * # Update multiple events
     * */
    public static function events_save($result, $EM_Event, $event_ids, $post_ids)
    {
        if (!empty($event_ids)) {
            foreach ($event_ids as $event_id) {
                $event = new EM_Event($event_id);
                self::event_save(true, $event);
            }
        }
    }

    /**
     * Update event
     * */
    public static function event_save($result, $EM_Event)
    {
        if (!$result) {
            return;
        }
        global $wpdb;
        $post_id     = $EM_Event->post_id;
        $location_id = $EM_Event->location_id;

        $chart_key = $wpdb->get_var("SELECT chart_key FROM " . EM_SEATSIO_LOCATION . " as emsl join " . EM_LOCATIONS_TABLE . " as eml on eml.post_id=emsl.post_id WHERE eml.location_id='" . $location_id . "' LIMIT 1");
        if (empty($chart_key)) {
            return;
        }
        //have to have location with chart key to continue
        //create event if not done
        $event_key = self::createSeatsioEventKey($EM_Event);
        $total     = $wpdb->get_var("SELECT count(1) FROM " . EM_SEATSIO_EVENT . " WHERE event_key='" . $event_key . "' LIMIT 1");
        if ($total > 0) {
            $wpdb->update(EM_SEATSIO_EVENT, array('event_key' => $event_key), array('post_id' => $post_id));
        } else {
            self::createEvent($chart_key, $event_key);
            $data = array('post_id' => $post_id, 'event_key' => $event_key);
            $wpdb->insert(EM_SEATSIO_EVENT, $data);
        }

        //check tickets based on categories - create if missing
        $client     = EM_Seatsio::getAPIClient();
        $s          = $client->report($event_key, 'byCategoryKey');
        $c          = $client->categories($chart_key);
        $categories = array();
        foreach ($c as $cat) {
            $key                   = $cat->key;
            $categories[$cat->key] = array('label' => $cat->label, 'seats' => !empty($s->$key) ? count($s->$key) : 0);
        }
        $event_id = $EM_Event->event_id;
        $em_t     = new EM_Tickets($event_id);
        foreach ($em_t->tickets as $ticket) {
            if (!empty($ticket->ticket_meta) && !empty($ticket->ticket_meta['seatsio_category_id'])) {
                //remove existing ticket categories list
                unset($categories[$ticket->ticket_meta['seatsio_category_id']]);
            }
        }
        if (!empty($categories)) {
            foreach ($categories as $key => $cat) {
                if ($cat['seats'] > 0) {
                    $ticket = new EM_Ticket();
                    preg_match('/\$([0-9]+[\.,]*[0-9]*)/', $cat['label'], $match);
                    if (!empty($match[1])) {
                        $dollar_amount = tofloat($match[1]);
                        if (!empty($dollar_amount)) {
                            $ticket->ticket_price = $dollar_amount;
                        }
                    }
                    $ticket->ticket_name                        = trim(preg_replace('/\$([0-9]+[\.,]*[0-9]*)/', '', $cat['label']));
                    $ticket->event_id                           = $event_id;
                    $ticket->ticket_spaces                      = $cat['seats'];
                    $ticket->ticket_meta['seatsio_category_id'] = $key;
                    $ret                                        = $ticket->save();
                }
            }
        }
    }
}
