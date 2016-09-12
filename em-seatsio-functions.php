<?php
//Add translation
function em_seatsio_load_plugin_textdomain()
{
    load_plugin_textdomain('em-seatsio', false, dirname(plugin_basename(__FILE__)) . '/langs');
}
add_action('plugins_loaded', 'emp_load_plugin_textdomain');

/* Activate plugin*/
function em_seatsio_activate()
{
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
}
register_activation_hook(__FILE__, 'em_seatsio_activate');

//Translation shortcut functions for times where WP translation shortcuts for strings in the dbem domain. These are here to prevent the POT file generator adding these translations to the local translations file
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