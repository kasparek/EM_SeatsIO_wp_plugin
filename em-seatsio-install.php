<?php

function em_seatsio_install()
{
    $old_version = get_option('em_seatsio_version');
    if (EM_SEATSIO_VERSION > $old_version || $old_version == '' || (is_multisite() && !EM_MS_GLOBAL && get_option('em_seatsio_ms_global_install'))) {
        // Creates the tables + options if necessary
        if (!EM_MS_GLOBAL || (EM_MS_GLOBAL && is_main_site())) {
            //install any db tables needed
            em_seatsio_create_location_table();
            em_seatsio_create_event_table();
            em_seatsio_create_booking_table();
            delete_option('em_seatsio_ms_global_install'); //in case for some reason the user changed global settings
        } else {
            update_option('em_seatsio_ms_global_install', 1); //in case for some reason the user changes global settings in the future
        }
        //Upate Version
        update_option('em_seatsio_version', EM_SEATSIO_VERSION);
    }
}

function em_seatsio_create_booking_table()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table_name = $wpdb->prefix . 'em_seatsio_bookings';
    $sql        = "CREATE TABLE " . $table_name . " (
        `ticket_booking_id` int(20) UNSIGNED NOT NULL DEFAULT '0',
        `booking_id` int(20) UNSIGNED NOT NULL DEFAULT '0',
        `ticket_id` int(20) UNSIGNED NOT NULL DEFAULT '0',
        `event_key` varchar(255) DEFAULT NULL,
        `seat_key` varchar(255) DEFAULT NULL
      ) DEFAULT CHARSET=utf8;";
    dbDelta($sql);
    $sql = "ALTER TABLE " . $table_name . " ADD PRIMARY KEY (`event_key`,`seat_key`);";
    dbDelta($sql);
}

function em_seatsio_create_location_table()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table_name = $wpdb->prefix . 'em_seatsio_location';
    $sql        = "CREATE TABLE " . $table_name . " (
          post_id int(20) unsigned NOT NULL DEFAULT '0',
          chart_key varchar(255) DEFAULT NULL
        ) DEFAULT CHARSET=utf8 ;";
    dbDelta($sql);
}

function em_seatsio_create_event_table()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table_name = $wpdb->prefix . 'em_seatsio_event';
    $sql        = "CREATE TABLE " . $table_name . " (
          post_id int(20) unsigned NOT NULL DEFAULT '0',
          event_key varchar(255) DEFAULT NULL
        ) DEFAULT CHARSET=utf8 ;";
    dbDelta($sql);
}
