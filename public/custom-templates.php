<?php
add_filter('em_locate_template', 'em_seatsio_custom_template');

function em_seatsio_custom_template($template_name)
{
    global $EM_Event;
    if(!empty($EM_Event)) {
	    if (EM_Seatsio::event_key($EM_Event->post_id)) {
	        if (strpos($template_name, 'events-manager/templates/forms/bookingform/tickets-list.php')) {
	            $template_name = plugin_dir_path(__FILE__) . 'templates/tickets-list.php';
	        }
	    }
	}
    return $template_name;
}
