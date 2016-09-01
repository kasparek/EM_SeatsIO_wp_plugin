<?php
/**
 * Settings link in the plugins page menu
 * @param array $links
 * @param string $file
 * @return array
 */
function em_seatsio_plugin_action_links($actions, $file, $plugin_data)
{
    $new_actions   = array();
    $new_actions[] = sprintf('<a href="' . EM_ADMIN_URL . '&amp;page=events-manager-seatsio-options">%s</a>', __('Settings', 'events-manager-seatsio'));
    $new_actions   = array_merge($new_actions, $actions);
    if (is_multisite()) {
        $uninstall_url = admin_url() . 'network/admin.php?page=events-manager-seatsio-options&amp;action=uninstall&amp;_wpnonce=' . wp_create_nonce('em_seatsio_uninstall_' . get_current_user_id() . '_wpnonce');
    } else {
        $uninstall_url = EM_ADMIN_URL . '&amp;page=events-manager-seatsio-options&amp;action=uninstall&amp;_wpnonce=' . wp_create_nonce('em_uninstall_' . get_current_user_id() . '_wpnonce');
    }
    $new_actions[] = '<span class="delete"><a href="' . $uninstall_url . '" class="delete">' . __('Uninstall', 'events-manager-seatsio') . '</a></span>';
    return $new_actions;
}
add_filter('plugin_action_links_events-manager-seatsio/events-manager-seatsio.php', 'em_seatsio_plugin_action_links', 10, 3);

add_action('admin_menu', 'em_seatsio_add_admin_menu');
add_action('admin_init', 'em_seatsio_settings_init');

function em_seatsio_add_admin_menu()
{

    add_submenu_page(null, 'Events Manager Seatsio', 'EM Seatsio', 'manage_options', 'events-manager-seatsio-options', 'em_seatsio_options_page');

}

function em_seatsio_settings_init()
{

    register_setting('pluginPage', 'em_seatsio_settings');

    add_settings_section(
        'em_seatsio_pluginPage_section',
        __em_seatsio('Seats.io API Credentials'),
        'em_seatsio_settings_section_callback',
        'pluginPage'
    );

    add_settings_field(
        'em_seatsio_private_key',
        __em_seatsio('Private Key'),
        'em_seatsio_text_field_0_render',
        'pluginPage',
        'em_seatsio_pluginPage_section'
    );

    add_settings_field(
        'em_seatsio_public_key',
        __('Public Key', 'wordpress'),
        'em_seatsio_text_field_1_render',
        'pluginPage',
        'em_seatsio_pluginPage_section'
    );

}

function em_seatsio_text_field_0_render()
{

    $options = get_option('em_seatsio_settings');
    ?>
	<input type='text' name='em_seatsio_settings[em_seatsio_private_key]' value='<?php echo $options['em_seatsio_private_key']; ?>'>
	<?php

}

function em_seatsio_text_field_1_render()
{

    $options = get_option('em_seatsio_settings');
    ?>
	<input type='text' name='em_seatsio_settings[em_seatsio_public_key]' value='<?php echo $options['em_seatsio_public_key']; ?>'>
	<?php

}

function em_seatsio_settings_section_callback()
{

    echo __em_seatsio('Enter Your API credentials from Seats.io');

}

function em_seatsio_options_page()
{

    ?>
	<form action='options.php' method='post'>

		<h2>Events Manager Seats.io extension</h2>
		<div class="postbox">
		<div class="inside">
		<?php
settings_fields('pluginPage');
    do_settings_sections('pluginPage');
    submit_button();
    ?>
		</div>
		</div>
	</form>
	<?php

}
