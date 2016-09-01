<?php
/*
Plugin Name: Events Manager Seatsio
Plugin URI: ...
Description: Integrate seats.io API with Events manager
Author: Francis Kaspar
Author URI: ...
Version: 0.0.1

Copyright (C) 2016
 */

define('EM_SEATSIO_VERSION', 0.02);
define('EM_MIN_VERSION', 5.63);
define('EM_MIN_VERSION_CRITICAL', 2.377);
define('EM_SLUG', plugin_basename(__FILE__));

require_once 'vendor/autoload.php';
require_once 'Seatsio/SeatsioApiClient.php';

if (is_admin()) {
    include 'admin/em-admin.php';
}

require_once 'public/custom-templates.php';

function em_seatsio_get_charts()
{
    global $wpdb;
    $post_id = null;
    if (isset($_POST['post_id'])) {
        $post_id = (int) $_POST['post_id'];
    }

    if (isset($_POST['location_id'])) {
        $location_id = (int) $_POST['location_id'];
    }

    if ($location_id > 0) {
        $post_id = $wpdb->get_var("SELECT post_id FROM " . EM_LOCATIONS_TABLE . " WHERE location_id='" . $location_id . "' LIMIT 1");
    }

    $selected = $wpdb->get_var("SELECT chart_key FROM " . EM_SEATSIO_LOCATION . " WHERE post_id='" . $post_id . "' LIMIT 1");
    echo json_encode(['selected' => $selected, 'list' => EM_Seatsio::getCharts()]);
    exit;
}
add_action('wp_ajax_em_seatsio_get_charts', 'em_seatsio_get_charts');

function em_seatsio_get_event()
{
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if (isset($_GET['post_id'])) {
        $post_id = (int) $_GET['post_id'];
    }

    if ($response = EM_Seatsio::event_details($post_id)) {
        $options              = get_option('em_seatsio_settings');
        $response->public_key = $options["em_seatsio_public_key"];
        echo json_encode($response);
    }
    exit;
}
add_action('wp_ajax_em_seatsio_get_event', 'em_seatsio_get_event');

function em_seatsio_block_event()
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
    echo json_encode($response);
    exit;
}
add_action('wp_ajax_em_seatsio_block_event', 'em_seatsio_block_event');

function em_seatsio_chart_details()
{
    $chart_key = isset($_POST['chart_key']) ? $_POST['chart_key'] : 0;
    if (empty($chart_key)) {
        $chart_key = isset($_GET['chart_key']) ? $_GET['chart_key'] : 0;
    }

    if (empty($chart_key)) {
        exit;
    }

    echo json_encode(EM_Seatsio::chart_details($chart_key));
    exit;
}
add_action('wp_ajax_em_seatsio_chart_details', 'em_seatsio_chart_details');

function em_seatsio_get_post_ticket($ticket, $post=null)
{
    if(empty($post)) return;
    foreach ($post as $key => $value) {
        if (strpos($key, 'ticket_meta_') === 0) {
            $ticket->ticket_meta[str_replace('ticket_meta_', '', $key)] = $value;
        }
    }
}

function ticket_add_meta_inputs($col_count, $ticket)
{
    if (!empty($ticket->ticket_id)) {
        echo '<input type="hidden" name="em_tickets[' . $col_count . '][ticket_meta_seatsio_category]" class="ticket_meta_category" value="' . $ticket->ticket_meta['seatsio_category'] . '">'
        . '<input type="hidden" name="em_tickets[' . $col_count . '][ticket_meta_seatsio_chart]" class="ticket_meta_chart" value="' . $ticket->ticket_meta['seatsio_chart'] . '">';
    }
}
add_action('em_ticket_edit_form_fields', 'ticket_add_meta_inputs', 10, 2);

add_action('em_ticket_get_post_pre', 'em_seatsio_get_post_ticket', 10, 2);
add_action('em_location_get_post_pre', 'em_seatsio_get_post_ticket', 10, 2); //DUE to possible bug on wrong name on hook

add_action('save_post', array('EM_Seatsio', 'save_post'), 1, 1); //set to 1 so metadata gets saved ASAP

class EM_Seatsio
{

    public static function event_details($post_id)
    {
        if (empty($post_id)) {
            return false;
        }

        $q = "SELECT event_key FROM " . EM_SEATSIO_EVENT . " WHERE post_id='" . $post_id . "' LIMIT 1";
        global $wpdb;
        if ($event_key = $wpdb->get_var($q)) {
            $response            = new stdClass();
            $response->event_key = $event_key;
            $client              = EM_Seatsio::getAPIClient();
            $response->seats     = $client->report($event_key);
            $response->event     = $client->event($event_key);
            $event_id            = $wpdb->get_var("select id from " . EM_SEATSIO_EVENT . " where post_id=" . $post_id);
            $response->tickets   = array();
            $emt                 = new EM_Tickets($event_id);
            foreach ($emt->tickets as $ticket) {
                $response->tickets[] = array('event_id' => $ticket->event_id, 'ticket_id' => $ticket->ticket_id, 'name' => $ticket->ticket_name, 'price' => $ticket->ticket_price, 'meta' => $ticket->ticket_meta);
            }
            $response->categories = $client->categories($response->event->chartKey);
            return $response;
        }
    }

    public static function chart_details($chart_key)
    {
        $client = EM_Seatsio::getAPIClient();
        return $client->chart($chart_key);
    }

    public static function event_data($post_id, $data = null)
    {
        global $wpdb;
        if ($data) {
            $wpdb->update(EM_SEATSIO_EVENT, array('data' => json_encode($data)), array('post_id' => $post_id));
        }
        $q    = "SELECT data FROM " . EM_SEATSIO_EVENT . " WHERE post_id='" . $post_id . "' LIMIT 1";
        $data = $wpdb->get_var($q);
        if ($data) {
            return json_decode($data);
        }

    }

    public static function event_key($post_id)
    {
        global $wpdb;
        $q = "SELECT event_key FROM " . EM_SEATSIO_EVENT . " WHERE post_id='" . $post_id . "' LIMIT 1";
        return $wpdb->get_var($q);
    }

    public static function event_block($post_id, $objects = [], $status = null)
    {
        if (empty($objects)) {
            return false;
        }

        $validStatus = array('blocked', 'free');
        $status      = isset($_POST['status']) ? $_POST['status'] : '';
        if (!in_array($status, $validStatus)) {
            $status = $validStatus[0];
        }

        $q = "SELECT event_key FROM " . EM_SEATSIO_EVENT . " WHERE post_id='" . $post_id . "' LIMIT 1";
        global $wpdb;
        $event_key = $wpdb->get_var($q);
        if ($event_key) {

            $client = self::getAPIClient();
            $client->changeStatus($event_key, $status, $objects);

            /*$data = self::event_data($post_id);
            if (!is_object($data)) {
            $data = new stdClass();
            }

            if (empty($data->blocked)) {
            $data->blocked = array();
            }

            foreach ($objects as $value) {
            if (!in_array($value, $data->blocked)) {
            $data->blocked[] = $value;
            }
            }
            return self::event_data($post_id, $data);*/
            return $client->report($event_key);
        }
    }

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
            $charts    = self::getCharts();
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

        if ($_POST['post_type'] === 'event') {
            //create event if not done
            $event_key = self::createSeatsioEventKey($post_id, $location_id);
            $q         = "SELECT count(1) FROM " . EM_SEATSIO_EVENT . " WHERE event_key='" . $event_key . "' LIMIT 1";
            $total     = $wpdb->get_var($q);
            if ($total > 0) {
                $wpdb->update(EM_SEATSIO_EVENT, array('event_key' => $event_key), array('post_id' => $post_id));
            } else {
                self::createEvent($chart_key, $event_key);
                $wpdb->insert(EM_SEATSIO_EVENT, array('post_id' => $post_id, 'event_key' => $event_key));
            }
            //check tickets based on categories - create if missing
            $client     = EM_Seatsio::getAPIClient();
            $s          = $client->report($event_key, 'byCategoryKey');
            $c          = $client->categories($chart_key);
            $categories = array();
            foreach ($c as $cat) {
                $key                   = $cat->key;
                $categories[$cat->key] = array('label' => $cat->label, 'seats' => count($s->$key));
            }

/*
$event_id     = $wpdb->get_var("select id from ".EM_SEATSIO_EVENT." where post_id=".$post_id);
$em_t = new EM_Tickets($event_id);
foreach ($em_t->tickets as $ticket) {

if(!empty($ticket->ticket_meta) && !empty($ticket->ticket_meta['seatsio_category_id'])) {
unset($categories[$ticket->ticket_meta['seatsio_category_id']]);
}
}

foreach ($categories as $key=>$cat) {
if($cat['seats'] > 0) {
$ticket = new EM_Ticket();
$ticket->ticket_name = $cat['label'];
preg_match('/\$([0-9]+[\.]*[0-9]*)/', $cat['label'], $match);
$dollar_amount = tofloat($match[1]);
if(!empty($dollar_amount)) {
$ticket->ticket_price = $dollar_amount;
}
$ticket->event_id = $event_id;
$ticket->ticket_spaces = $cat['seats'];
$ticket->ticket_meta['seatsio_category_id'] = $key;
$ret = $ticket->save();
}
}
 */
        }

        return true;
    }
    /**
     * em_seatsio_data option
     * @var array
     */
    public $data;

    static $APIClient = null;
    public static function getAPIClient()
    {
        if (self::$APIClient) {
            return self::$APIClient;
        }

        $options         = get_option('em_seatsio_settings');
        self::$APIClient = new SeatsioApiClient($options["em_seatsio_private_key"]);
        return self::$APIClient;
    }

    public static function createSeatsioEventKey($post_id, $location_id)
    {
        return 'wp-ems-event-lid-' . $location_id . '-pid-' . $post_id;
    }

    public static function createEvent($chart_key, $event_key)
    {
        $client   = self::getAPIClient();
        $response = $client->linkChartToEvent($chart_key, $event_key);
        return $response;
    }

    public static function getCharts()
    {
        $client = self::getAPIClient();
        $data   = $client->charts();
        foreach ($data as &$chart) {
            $chart->thumb = 'https://app.seats.io/api/chart/' . $chart->key . '/thumbnail';
        }
        return $data;
    }

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

        //check that EM is installed
        if (!defined('EM_VERSION')) {
            add_action('admin_notices', 'EM_Seatsio::em_install_warning');
            add_action('network_admin_notices', 'EM_Seatsio::em_install_warning');
            return false; //don't load plugin further
        } elseif (EM_MIN_VERSION_CRITICAL > EM_VERSION) {
            //add notice and prevent further loading
            add_action('admin_notices', 'EM_Seatsio::em_version_warning_critical');
            add_action('network_admin_notices', 'EM_Seatsio::em_version_warning_critical');
            return false;
        } elseif (EM_MIN_VERSION > EM_VERSION) {
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
        /*
    //includes
    include 'emp-forms.php'; //form editor
    include 'emp-ml.php';
    if (is_admin()) {
    include 'emp-admin.php';
    }
    //add-ons
    include 'add-ons/gateways/gateways.php';
    include 'add-ons/bookings-form/bookings-form.php';
    include 'add-ons/coupons/coupons.php';
    include 'add-ons/emails/emails.php';
    include 'add-ons/user-fields.php';
    if (get_option('dbem_multiple_bookings')) {
    include 'add-ons/multiple-bookings/multiple-bookings.php';
    }
     */
    }

    public static function install()
    {
        //Upgrade/Install Routine
        $old_version = get_option('em_seatsio_version');
        if (EMP_VERSION > $old_version || $old_version == '' || (is_multisite() && !EM_MS_GLOBAL && get_option('emp_ms_global_install'))) {
            require_once 'em-seatsio-install.php';
            em_seatsio_install();
        }
    }

    /**
     * Enqueues the CSS required by Pro features. Fired by action em_enqueue_styles which is when EM enqueues it's stylesheet, if it doesn't then this shouldn't either
     */
    public static function em_enqueue_styles()
    {
        wp_enqueue_style('events-manager-seatsio', plugins_url('includes/css/events-manager-seatsio.css', __FILE__), array(), EMP_VERSION);
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
            wp_localize_script('events-manager-seatsio', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'seatsio_public_key' => $public_key));
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
        wp_enqueue_style('events-manager-seatsio-admin', plugins_url('includes/css/events-manager-seatsio-admin.css', __FILE__), array(), EMP_VERSION);
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

//Add translation
function em_seatsio_load_plugin_textdomain()
{
    load_plugin_textdomain('em-seatsio', false, dirname(plugin_basename(__FILE__)) . '/langs');
}
add_action('plugins_loaded', 'emp_load_plugin_textdomain');

/* Creating the wp_events table to store event data*/
function em_seatsio_activate()
{
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
}
register_activation_hook(__FILE__, 'em_seatsio_activate');

//Translation shortcut functions for times where WP translation shortcuts for strings in the dbem domain. These are here to prevent the POT file generator adding these translations to the pro translations file
/**
 * Shortcut for the __ function
 * @param string $text
 * @param string $domain
 */
function __em_seatsio($text, $domain = 'events-manager')
{
    return translate($text, $domain);
}
/**
 * Shortcut for the _e function
 * @param string $text
 * @param string $domain
 */
function _e_em_seatsio($text, $domain = 'events-manager')
{
    echo __em_seatsio($text, $domain);
}
/**
 * Shortcut for the esc_html__ function
 * @param string $text
 * @param string $domain
 */
function esc_html__em_seatsio($text, $domain = 'events-manager')
{
    return esc_html(translate($text, $domain));
}
/**
 * Shortcut for the esc_html_e function
 * @param string $text
 * @param string $domain
 */
function esc_html_e_em_seatsio($text, $domain = 'events-manager')
{
    echo esc_html__em_seatsio($text, $domain);
}
/**
 * Shortcut for the esc_attr__ function
 * @param string $text
 * @param string $domain
 */
function esc_attr__em_seatsio($text, $domain = 'events-manager')
{
    return esc_attr(translate($text, $domain));
}
/**
 * Shortcut for the esc_attr_e function
 * @param string $text
 * @param string $domain
 */
function esc_attr_e_em_seatsio($text, $domain = 'events-manager')
{
    echo esc_attr__em_seatsio($text, $domain);
}
/**
 * Shortcut for the esc_html_x function
 * @param string $text
 * @param string $domain
 */
function esc_html_x_em_seatsio($text, $context, $domain = 'events-manager')
{
    return esc_html(translate_with_gettext_context($text, $context, $domain));
}

function tofloat($num)
{
    $dotPos   = strrpos($num, '.');
    $commaPos = strrpos($num, ',');
    $sep      = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
    ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);

    if (!$sep) {
        return floatval(preg_replace("/[^0-9]/", "", $num));
    }

    return floatval(
        preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' .
        preg_replace("/[^0-9]/", "", substr($num, $sep + 1, strlen($num)))
    );
}
