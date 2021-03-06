<?php
/**
 * Plugin Name: External API Authentication
 * Plugin URI: https://github.com/raddevon/wp-external-api-auth
 * Description: Replaces WordPress authentication with external authentication via API.
 * Version: 0.1.1
 * Author: Devon Campbell
 * Author URI: http://raddevon.com
 * License: GPL2
 */

/*  Copyright 2014 Devon Campbell  (email : devon@radworks.io)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/************************************
* Global Variables
************************************/
$eapia_plugin_name = "External API Authentication";


/************************************
* Plugin Options
************************************/
add_action( 'admin_menu', 'eapia_options_menu' );

function eapia_options_menu() {
    global $eapia_plugin_name;
    add_action( 'admin_init' , 'eapia_register_settings');
    $eapia_options_page_hook = add_options_page( $eapia_plugin_name . ' Settings', $eapia_plugin_name, 'manage_options', 'eapia-settings', 'eapia_render_options');
    add_action( 'admin_print_styles-' . $eapia_options_page_hook, 'eapia_admin_print_styles' );
    }

function eapia_admin_print_styles() {
    wp_enqueue_style( 'eapia-admin-css', plugin_dir_url(__FILE__) . 'css/admin.css' );
}

function eapia_register_settings() {
    register_setting( 'eapia-settings-group', 'eapia-login-endpoint');
    register_setting( 'eapia-settings-group', 'eapia-username-key');
    register_setting( 'eapia-settings-group', 'eapia-password-key');
    register_setting( 'eapia-settings-group', 'eapia-username');
    register_setting( 'eapia-settings-group', 'eapia-email');

    add_settings_section( 'eapia-general-settings', 'General', 'eapia_general_options', 'eapia-settings');

    add_settings_field(
        'eapia-login-endpoint',
        'API authentication endpoint',
        'eapia_render_text_input_field',
        'eapia-settings',
        'eapia-general-settings',
        array(
            'field' => 'eapia-login-endpoint',
            'help' => '<strong>Required.</strong> The plugin will send a POST request to this URL.',
            'type' => 'url'
            )
        );

    add_settings_field(
        'eapia-username-key',
        'Username key',
        'eapia_render_text_input_field',
        'eapia-settings',
        'eapia-general-settings',
        array(
            'field' => 'eapia-username-key',
            'help' => 'The username will be sent to the API with this key. <em>(Default: username)</em>',
            'type' => 'text'
            )
        );

    add_settings_field(
        'eapia-password-key',
        'Password key',
        'eapia_render_text_input_field',
        'eapia-settings',
        'eapia-general-settings',
        array(
            'field' => 'eapia-password-key',
            'help' => 'The password will be sent to the API with this key. <em>(Default: password)</em>',
            'type' => 'text'
            )
        );

    add_settings_section( 'eapia-mapping-settings', 'User Mapping', 'eapia_mapping_options', 'eapia-settings');

    add_settings_field(
        'eapia-username',
        'Username',
        'eapia_render_text_input_field',
        'eapia-settings',
        'eapia-mapping-settings',
        array(
            'field' => 'eapia-username',
            'help' => 'The username will be sent to the API with this key. <em>(Default: username)</em>',
            'type' => 'text'
            )
        );

    add_settings_field(
        'eapia-email',
        'Email',
        'eapia_render_text_input_field',
        'eapia-settings',
        'eapia-mapping-settings',
        array(
            'field' => 'eapia-email',
            'help' => 'The password will be sent to the API with this key. <em>(Default: password)</em>',
            'type' => 'text'
            )
        );
}

function eapia_render_text_input_field($args) {
    $field = $args['field'];
    $help = $args['help'];
    $type = $args['type'];
    $value = get_option($field); ?>
<input type="<?php _e($type); ?>" name="<?php _e($field); ?>" value="<?php _e(get_option($field)); ?>" />
<p><?php _e($help); ?></p>
<?php
}

function eapia_mapping_options(){ ?>
    <p>The plugin must create a WordPress user account for users authenticated externally. Use this section to map required WordPress user information to returned information from your API.</p>
<?php }

function eapia_general_options(){
    return;
}

function eapia_render_options(){
    global $eapia_plugin_name;
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }?>
        <div class="wrap">
            <h2><?php _e( $eapia_plugin_name . ' Settings') ; ?></h2>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'eapia-settings-group' );
                settings_fields( 'eapia-settings-group' );
                do_settings_sections( 'eapia-settings' );

                settings_errors();
                submit_button(); ?>
            </form>
        </div>
    <?php
}


/************************************
* Authentication
************************************/
add_filter( 'authenticate', 'eapia_auth', 10, 3 );

function eapia_auth( $user, $username, $password ){
    $endpoint = get_option( 'eapia-login-endpoint' );
    $username_key = get_option( 'eapia-username-key', 'username' ) ?: 'username';
    $password_key = get_option( 'eapia-password-key', 'password' ) ?: 'password';
    // Makes sure we have an endpoint set
    if(!$endpoint ) return;

    // Build the POST request
    $login_data = array(
        $username_key => $username,
        $password_key => $password );
    $response = wp_remote_post( $endpoint, $login_data);
    $ext_auth = json_decode( $response['body'], true );

     if( $ext_auth['result']  == 0 ) {
        // User does not exist,  send back an error message
        $user = new WP_Error( 'denied', __("<strong>Error</strong>: Your username or password are incorrect.") );

     } else if( $ext_auth['result'] == 1 ) {
         // External user exists, try to load the user info from the WordPress user table
         $userobj = new WP_User();
         $user = $userobj->get_data_by( 'email', $ext_auth['email'] ); // Does not return a WP_User object <img src="http://ben.lobaugh.net/blog/wp-includes/images/smilies/icon_sad.gif" alt=":(" class="wp-smiley" />
         $user = new WP_User($user->ID); // Attempt to load up the user with that ID

         if( $user->ID == 0 ) {
             // The user does not currently exist in the WordPress user table.
             // You have arrived at a fork in the road, choose your destiny wisely

             // If you do not want to add new users to WordPress if they do not
             // already exist uncomment the following line and remove the user creation code
             //$user = new WP_Error( 'denied', __("<strong>ERROR</strong>: Not a valid user for this system") );

             // Setup the minimum required user information for this example
             $userdata = array( 'user_email' => $ext_auth['email'],
                                'user_login' => $ext_auth['email'],
                                'first_name' => $ext_auth['first_name'],
                                'last_name' => $ext_auth['last_name']
                                );
             $new_user_id = wp_insert_user( $userdata ); // A new user has been created

             // Load the new user info
             $user = new WP_User ($new_user_id);
         }

     }

     // Comment this line if you wish to fall back on WordPress authentication
     // Useful for times when the external service is offline
     remove_action('authenticate', 'wp_authenticate_username_password', 20);

     return $user;
}


/************************************
* Alert
************************************/
/**
 * Generic function to show a message to the user using WP's
 * standard CSS classes to make use of the already-defined
 * message colour scheme.
 *
 * @param $message The message you want to tell the user.
 * @param $errormsg If true, the message is an error, so use
 * the red message style. If false, the message is a status
  * message, so use the yellow information message style.
 */
function showMessage($message, $errormsg = false)
{
    if ($errormsg) {
        echo '<div id="message" class="error">';
    }
    else {
        echo '<div id="message" class="updated fade">';
    }

    echo "<p><strong>$message</strong></p></div>";
}

/**
 * Just show our message (with possible checking if we only want
 * to show message to certain users.
 */
function showAdminMessages()
{
    // Only show to admins
    if (current_user_can('manage_options') && !get_option( 'eapia-login-endpoint' )) {
       showMessage("You must <a href='options-general.php?page=eapia-settings.php'>provide your API's authentication endpoint</a> to use External API Authentication.", true);
    }
}

/**
  * Call showAdminMessages() when showing other admin
  * messages. The message only gets shown in the admin
  * area, but not on the frontend of your WordPress site.
  */
add_action('admin_notices', 'showAdminMessages');
?>
