<?php
/*
Plugin Name: Events Manager Seats.io integration
Plugin URI: ...
Description: Integrate seats.io API with Events manager
Author: Frantisek Kaspar
Author URI: http://awake33.com
Version: 0.0.4
Copyright (C) 2016
 */

define('EM_SEATSIO_VERSION', 0.04);
define('EM_SEATSIO_MIN_VERSION', 0.04);
define('EM_SEATSIO_MIN_VERSION_CRITICAL', 0.04);

require_once 'em-seatsio-functions.php';

//Seats.IO API Client
require_once 'vendor/autoload.php';
require_once 'Seatsio/SeatsioApiClient.php';

if (is_admin()) {
    include 'admin/em-admin.php';
}

require_once 'public/custom-templates.php';
require_once 'em-seatsio-ajax.php';
require_once 'em-seatsio-event.php';
require_once 'em-seatsio-booking.php';
require_once 'em-seatsio-ticket-booking.php';

class EM_Seatsio
{
    public static $seats = array();

    /**
     * Actions to take upon initial action hook
     */
    public static function init()
    {
        global $wpdb;
        //check that an incompatible version of EM is not running
        //Define some tables
        if (EM_MS_GLOBAL) {
            $prefix = $wpdb->base_prefix;
        } else {
            $prefix = $wpdb->prefix;
        }
        define('EM_SEATSIO_LOCATION', $prefix . 'em_seatsio_location'); //TABLE NAME
        define('EM_SEATSIO_EVENT', $prefix . 'em_seatsio_event'); //TABLE NAME
        define('EM_SEATSIO_BOOKING', $prefix . 'em_seatsio_bookings'); //TABLE NAME

        //check that EM is installed
        if (!defined('EM_VERSION')) {
            add_action('admin_notices', 'EM_Seatsio::em_install_warning');
            add_action('network_admin_notices', 'EM_Seatsio::em_install_warning');
            return false; //don't load plugin further
        } elseif (EM_SEATSIO_MIN_VERSION_CRITICAL > EM_VERSION) {
            //add notice and prevent further loading
            add_action('admin_notices', 'EM_Seatsio::em_version_warning_critical');
            add_action('network_admin_notices', 'EM_Seatsio::em_version_warning_critical');
            return false;
        } elseif (EM_SEATSIO_MIN_VERSION > EM_VERSION) {
            //check that EM is up to date
            add_action('admin_notices', 'EM_Seatsio::em_version_warning');
            add_action('network_admin_notices', 'EM_Seatsio::em_version_warning');
        }
        if (is_admin()) {
            //although activate_plugins would be beter here, superusers don't visit every single site on MS
            add_action('init', 'EM_Seatsio::install', 2);
        }
        //Add extra Styling/JS
        add_action('em_enqueue_styles', 'EM_Seatsio::em_enqueue_styles');
        add_action('em_enqueue_scripts', 'EM_Seatsio::em_enqueue_scripts', 1); //added only when EM adds its own scripts
        add_action('em_enqueue_admin_scripts', 'EM_Seatsio::em_enqueue_scripts', 1); //added only when EM adds its own scripts
        add_action('em_enqueue_admin_styles', 'EM_Seatsio::em_enqueue_admin_styles', 1); //added only when EM adds its own scripts
        add_action('admin_init', 'EM_Seatsio::enqueue_admin_script', 1); //specific pages in admin that EM Seatsio deals with

        //overriding EM
        EM_Seatsio_ajax::init();
        EM_Seatsio_booking::init();
        EM_Seatsio_event::init();
        EM_Seatsio_ticket_booking::init();
        add_action('em_ticket_edit_form_fields', 'ticket_edit_form_fields', 10, 2);
        add_action('em_ticket_get_post_pre', array('EM_Seatsio', 'ticket_get_post_pre'), 10, 2);
        add_action('save_post', array('EM_Seatsio', 'save_post'), 1, 1); //set to 1 so metadata gets saved ASAP
        add_action('em_bookings_table_cols_template', array('EM_Seatsio', 'bookings_table_cols_template'));
        add_action('em_bookings_table_rows_col_seatsio_booths', array('EM_Seatsio', 'bookings_table_rows_col_seatsio_booths'), 10, 5);
    }

    public static function get_seats_report($post_id, $event_key = null)
    {
        if (isset(self::$seats[$post_id])) {
            return self::$seats[$post_id];
        }

        //get seats.io event key
        if (empty($event_key)) {
            if (!$event_key = self::event_key($post_id)) {
                self::$seats[$post_id] = null;
                return self::$seats[$post_id];
            }
        }
        $client                = EM_Seatsio::getAPIClient();
        self::$seats[$post_id] = $client->report($event_key, 'byUuid');
        return self::$seats[$post_id];
    }

    /**
     * # List of booked seats.io seat labels for custom my-booking.php front-end table
     * @param  object   $EM_Booking
     * @return string   Seat labels
     */
    public static function booking_get_booths($EM_Booking)
    {
        global $wpdb;
        if (empty($EM_Booking->booking_id)) {
            return '';
        }
        if (empty($EM_Booking->event)) {
            return '';
        }
        //get booked seats
        $seats = $wpdb->get_results("select * from " . EM_SEATSIO_BOOKING . " where booking_id = " . $EM_Booking->booking_id, OBJECT);
        if (empty($seats)) {
            return '';
        }
        //get seats.io event key
        $event_key = self::event_key($EM_Booking->event->post_id);
        if (empty($event_key)) {
            return '';
        }
        //get seat labels from seats.io chart
        $seats_report = self::get_seats_report($EM_Booking->event->post_id, $event_key);
        if (empty($seats_report)) {
            return '';
        }

        //list booked seats
        $result = [];
        foreach ($seats as $seat) {
            $key      = $seat->seat_key;
            $result[] = $seats_report->$key->label;
        }
        return implode(', ', $result);
    }

    /**
     * # Add option for Events Manager booking table and CSV export
     * @param  array    $list Original list of options
     * @return array    Customized options
     */
    public static function bookings_table_cols_template($list)
    {
        $list['seatsio_booths'] = 'Booth Number';
        return $list;
    }

    /**
     * # List booked seat labels
     * Used in admin csv export and booking list table
     * Returns string with all booked seats for given booking
     * @param  int       $num number of booked tickets
     * @param  object    $EM_Booking
     * @return string    List of booked seats.io seat labels
     */
    public static function bookings_table_rows_col_seatsio_booths($val, $EM_Booking, $this, $csv, $object)
    {
        global $wpdb;
        $seats = $wpdb->get_results("select * from " . EM_SEATSIO_BOOKING . " where booking_id = " . $EM_Booking->booking_id, OBJECT);
        if (empty($seats)) {
            return '';
        }
        $event_key = self::event_key($EM_Booking->event->post_id);
        if (empty($event_key)) {
            return '';
        }
        $seats_report = self::get_seats_report($EM_Booking->event->post_id, $event_key);
        if (empty($seats_report)) {
            return '';
        }
        $result = [];
        foreach ($seats as $seat) {
            $key      = $seat->seat_key;
            $result[] = $seats_report->$key->label;
        }
        return implode(', ', $result);
    }

    /**
     * # Get meta field value from Ticket
     * @param  int   $ticket_id
     * @return  array    unsearialized value of ticket_meta db field value
     */
    public static function get_ticket_meta($ticket_id)
    {
        global $wpdb;
        if ($meta = $wpdb->get_var("select ticket_meta from " . EM_TICKETS_TABLE . " where ticket_id='" . $ticket_id . "' limit 1")) {
            return unserialize($meta);
        }
        return null;
    }

    /**
     * # Get EM Ticket based on seats.io category id
     * @return  object EM_Ticket
     * */
    public static function ticket_by_seatsio_category($event_id, $seatsio_category)
    {
        $emt = new EM_Tickets($event_id);
        foreach ($emt->tickets as $ticket) {
            if (!empty($ticket->ticket_meta['seatsio_category']) && $ticket->ticket_meta['seatsio_category'] == $seatsio_category) {
                return $ticket;
            }
        }
    }

    /**
     * # Get chart details from seats.io API
     * */
    public static function chart_details($chart_key)
    {
        $client = EM_Seatsio::getAPIClient();
        return $client->chart($chart_key);
    }

    /**
     * # Get seats.io event key based on post_id
     * empty if seats.io event not created yet for given post
     * */
    public static function event_key($post_id)
    {
        global $wpdb;
        return $wpdb->get_var("SELECT event_key FROM " . EM_SEATSIO_EVENT . " WHERE post_id='" . $post_id . "' LIMIT 1");
    }

    /**
     * # Fake seat blocking in seats.io
     * @param int $post_id
     * @param array $objects
     * @param  string $status blocked/free status
     * @return array list of all seats with updated status
     * */
    public static function event_block($post_id, $objects = [], $status = null)
    {
        if (empty($objects)) {
            return false;
        }
        $validStatus = array('blocked', 'free');
        $status      = isset($_POST['status']) ? $_POST['status'] : $status;
        //validate input status
        if (!in_array($status, $validStatus)) {
            $status = $validStatus[0];
        }
        global $wpdb;
        if ($event_key = $wpdb->get_var("SELECT event_key FROM " . EM_SEATSIO_EVENT . " WHERE post_id='" . $post_id . "' LIMIT 1")) {
            $client = self::getAPIClient();
            $client->changeStatus($event_key, $status, $objects);
            return $client->report($event_key, 'byUuid');
        }
    }

    /**
     * # Handle global post save
     *
     * */
    public static function save_post($post_id)
    {
        global $wpdb;

        $post_type = isset($_POST['post_type']) ? $_POST['post_type'] : null;

        if (empty($post_id)) {
            return false;
        }

        $chart_key   = !empty($_POST['em_seatsio_chart']) ? $_POST['em_seatsio_chart'] : null;
        $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;

        if (!empty($chart_key)) {
            $chart_key = $_POST['em_seatsio_chart'];
            $charts    = self::fetch_all_charts();
            $validated = false;

            foreach ($charts as $chart) {
                if ($chart->key === $chart_key) {
                    $validated = true;
                    break;
                }
            }

            if ($validated === false) {
                return false;
            }

            $q                 = "SELECT chart_key FROM " . EM_SEATSIO_LOCATION . " WHERE post_id='" . $post_id . "' LIMIT 1";
            $current_chart_key = $wpdb->get_var($q);
            if (!empty($current_chart_key)) {
                if ($current_chart_key != $chart_key) {
                    if ($post_type === 'event') {
                        $wpdb->delete(EM_SEATSIO_EVENT, array('post_id' => $post_id));
                    }
                    if ($post_type === 'location') {
                        $wpdb->update(EM_SEATSIO_LOCATION, array('chart_key' => $chart_key), array('post_id' => $post_id));
                    }
                }
            } else {
                if ($post_type === 'location') {
                    $wpdb->insert(EM_SEATSIO_LOCATION, array('post_id' => $post_id, 'chart_key' => $chart_key));
                }
            }
        }

        return true;
    }

    /**
     * Instance of Seats.io API Client
     * */
    static $APIClient = null;

    /**
     * Retrieve/Create instance of Seats.io API Client
     * @return  object Instance of SeatsioApiClient class
     * */
    public static function getAPIClient()
    {
        if (self::$APIClient) {
            return self::$APIClient;
        }
        $options         = get_option('em_seatsio_settings');
        self::$APIClient = new SeatsioApiClient($options["em_seatsio_private_key"]);
        return self::$APIClient;
    }

    /**
     * # Retrieve all seats.io charts
     * @return  array List of charts from seats.io API with image thumbnail
     * */
    public static function fetch_all_charts()
    {
        $client = self::getAPIClient();
        $data   = $client->charts();
        foreach ($data as &$chart) {
            $chart->thumb = 'https://app.seats.io/api/chart/' . $chart->key . '/thumbnail';
        }
        return $data;
    }

    public function ticket_get_post_pre($ticket, $post = null)
    {
        if (empty($post)) {
            return;
        }
        foreach ($post as $key => $value) {
            if (strpos($key, 'ticket_meta_') === 0) {
                $ticket->ticket_meta[str_replace('ticket_meta_', '', $key)] = $value;
            }
        }
    }

    public function ticket_edit_form_fields($col_count, $ticket)
    {
        if (!empty($ticket->ticket_id)) {
            echo '<input type="hidden" name="em_tickets[' . $col_count . '][ticket_meta_seatsio_category]" class="ticket_meta_category" value="' . $ticket->ticket_meta['seatsio_category'] . '">'
            . '<input type="hidden" name="em_tickets[' . $col_count . '][ticket_meta_seatsio_chart]" class="ticket_meta_chart" value="' . $ticket->ticket_meta['seatsio_chart'] . '">';
        }
    }

    /**
     * # Plugion Istallation Handler
     * */
    public static function install()
    {
        //Upgrade/Install Routine
        $old_version = get_option('em_seatsio_version');
        if (EM_SEATSIO_VERSION > $old_version || $old_version == '' || (is_multisite() && !EM_MS_GLOBAL && get_option('emp_ms_global_install'))) {
            require_once 'em-seatsio-install.php';
            em_seatsio_install();
        }
    }

    /**
     * Enqueues the CSS required by Pro features. Fired by action em_enqueue_styles which is when EM enqueues it's stylesheet, if it doesn't then this shouldn't either
     */
    public static function em_enqueue_styles()
    {
        wp_enqueue_style('events-manager-seatsio', plugins_url('includes/css/events-manager-seatsio.css', __FILE__), array(), EM_SEATSIO_VERSION);
    }

    /**
     * Enqueue scripts when fired by em_enqueue_scripts action.
     */
    public static function em_enqueue_scripts()
    {
        if (is_admin()) {
            wp_enqueue_script('events-manager-seatsio', plugins_url('includes/js/events-manager-seatio-admin.js', __FILE__), array('jquery')); //jQuery will load as dependency
        } else {
            $options    = get_option('em_seatsio_settings');
            $public_key = $options["em_seatsio_public_key"];
            wp_enqueue_script('events-manager-seatsio', plugins_url('includes/js/events-manager-seatio.js', __FILE__), array('jquery'));
            wp_localize_script('events-manager-seatsio', 'em_seatsio_object', array('ajax_url' => admin_url('admin-ajax.php'), 'seatsio_public_key' => $public_key));
        }
    }

    /**
     * Add admin scripts for specific pages handled by EM Seatsio. Fired by admin_init
     */
    public static function enqueue_admin_script()
    {
        global $pagenow;
        if (!empty($_REQUEST['page']) && ($_REQUEST['page'] == 'events-manager-forms-editor' || ($_REQUEST['page'] == 'events-manager-bookings' && !empty($_REQUEST['action']) && $_REQUEST['action'] == 'manual_booking'))) {
            wp_enqueue_script('events-manager-seatsio', plugins_url('includes/js/events-manager-seatsio.js', __FILE__), array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position')); //jQuery will load as dependency
            do_action('em_enqueue_admin_scripts');
        }
        if ($pagenow == 'user-edit.php') {
            //need to include the em script for dates
            EM_Scripts_and_Styles::admin_enqueue();
        }
    }

    /**
     * Enqueue Pro CSS file when action em_enqueue_admin_styles is fired.
     */
    public static function em_enqueue_admin_styles()
    {
        wp_enqueue_style('events-manager-seatsio-admin', plugins_url('includes/css/events-manager-seatsio-admin.css', __FILE__), array(), EM_SEATSIO_VERSION);
    }

    public static function em_install_warning()
    {
        echo '<div class="error"><p>'
        . _e('Please make sure you install Events Manager as well. You can search and install this plugin from your plugin installer or download it <a href="http://wordpress.org/extend/plugins/events-manager/">here</a>.', 'em-pro')
        . ' <em>'
        . _e('Only admins see this message.', 'em-pro')
            . '</em></p></div>';
    }

    public static function em_version_warning()
    {
        echo '<div class="error"><p>'
        . _e('Please make sure you have the <a href="http://wordpress.org/extend/plugins/events-manager/">latest version</a> of Events Manager installed, as this may prevent Pro from functioning properly.', 'em-pro')
        . ' <em>'
        . _e('Only admins see this message.', 'em-pro')
            . '</em></p></div>';
    }

    public static function em_version_warning_critical()
    {
        echo '<div class="error"><p>'
        . _e('Please make sure you have the <a href="http://wordpress.org/extend/plugins/events-manager/">latest version</a> of Events Manager installed, as this may prevent Pro from functioning properly.', 'em-seatsio') . ' <em>' . _e('Only admins see this message.', 'em-seatsio')
        . '</em></p><p>'
        . _e('Until it is updated, Events Manager Pro will remain inactive to prevent further errors.', 'em-pro')
            . '</div>';
    }

}
add_action('plugins_loaded', 'EM_Seatsio::init');
