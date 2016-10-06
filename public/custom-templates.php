<?php
add_filter('em_locate_template', 'em_seatsio_custom_template');

function em_seatsio_custom_template($template_name)
{
    $t_name              = explode('/', $template_name);
    $t_name              = array_pop($t_name);
    if($t_name === 'ticket-single.php') {
    	$t_name = 'tickets-list.php';
    }
    $local_template_name = plugin_dir_path(__FILE__) . 'templates/' . $t_name;
    if (file_exists($local_template_name)) {
        $template_name = $local_template_name;
    }
    return $template_name;
}
