<?php
/*
Plugin Name: XSoftware Users
Description: Users management on wordpress.
Version: 1.0
Author: Luca Gasperini
Author URI: https://xsoftware.it/
Text Domain: xsoftware_users
*/
if(!defined("ABSPATH")) die;

include 'xsoftware-users-options.php';

if (!class_exists("xs_users_plugin")) :

class xs_users_plugin
{

        function __construct()
        {
                $this->options = get_option('xs_options_users');

                add_action( 'register_form', [$this, 'registration_form'] );
                add_action( 'login_form', [$this, 'login_form'] );
                add_action( 'user_register', [$this, 'user_register'] );
                add_action( 'edit_user_created_user', [$this, 'user_register'] );
                add_action('after_setup_theme', [$this, 'remove_admin_bar']);
                add_action('login_head', [$this, 'login']);
                add_filter('registration_errors', [$this, 'registration_errors'], 10, 3);
                add_action('login_form_register', [$this, 'login_form_register']);
                add_action('wp_head',[$this, 'analytics']);

                add_shortcode('xs_users_facebook_login', [$this,'shortcode_facebook_login']);

                if(!empty($this->options['style']['login_logo']))
                        add_action('login_enqueue_scripts',  [$this, 'login_logo']);
        }

        function analytics()
        {
                if(!isset($this->options['settings']['analytics']) || empty($this->options['settings']['analytics']))
                        return;
                echo '
        <script async src="https://www.googletagmanager.com/gtag/js?id='.$this->options['settings']['analytics'].'">
        </script>
        <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag("js", new Date());

                gtag("config", "'.$this->options['settings']['analytics'].'");
        </script>';

        }

        function login()
        {
                if(isset($this->options['settings']['minimal']) && $this->options['settings']['minimal']) {
                echo '<style>
                        #registerform > p:first-child{
                                display:none;
                        }
                        </style>
                        <script type="text/javascript">
                                jQuery(document).ready(function($){
                                        $("#registerform > p:first-child").css("display", "none");
                                });
                        </script>';
                }
        }

        function registration_errors($wp_error, $sanitized_user_login, $user_email)
        {
                if(isset($this->options['settings']['minimal']) && $this->options['settings']['minimal']) {
                        if(isset($wp_error->errors['empty_username']))
                                unset($wp_error->errors['empty_username']);

                        if(isset($wp_error->errors['username_exists']))
                                unset($wp_error->errors['username_exists']);
                }
                return $wp_error;
        }

        function login_form_register()
        {
                if(isset($this->options['settings']['minimal']) && $this->options['settings']['minimal']) {
                        if(isset($_POST['user_login']) && isset($_POST['user_email']) && !empty($_POST['user_email'])) {
                                $_POST['user_login'] = $_POST['user_email'];
                        }
                }
        }

        function remove_admin_bar()
        {
                if (!current_user_can('administrator') && !is_admin()) {
                        show_admin_bar(false);
                }
        }

        function login_logo()
        {
                $width = $this->options['style']['login_logo_w'].'px';
                $height = $this->options['style']['login_logo_h'].'px';
                echo '
                <style type="text/css">
                #login h1 a, .login h1 a {
                        background-image: url("'.$this->options['style']['login_logo'].'");
                        height:'.$width.';
                        width:'.$height.';
                        background-size: '.$width.' '.$height.';
                        background-repeat: no-repeat;
                        padding-bottom: 30px;
                }
                </style>
                ';

        }

        function registration_form()
        {
                if($this->options['settings']['real_name']) {
                        echo '<p>
                        <label for="first_name">First Name<br>
                        <input type="text" name="first_name" id="first_name" class="input"></label>
                        </p>';
                        echo '<p>
                        <label for="last_name">Last Name<br>
                        <input type="text" name="last_name" id="last_name" class="input"></label>
                        </p>';
                }
                $fields = $this->options['fields'];
                $prefix = 'xs_users_';

                foreach($this->options['fields'] as $key => $single) {
                        $tmp['name'] = $prefix.$key;
                        $tmp['label'] = $single['name'];
                        $tmp['class'] = 'xs_full_width';
                        $tmp['type'] = $single['type'];

                        $data[] = $tmp;
                }

                if(!empty($data)) {
                        xs_framework::html_input_array_to_table(
                                $data,
                                [ 'class' => 'xs_full_width' ]
                        );
                }

                if(isset($this->options['settings']['fb_login']) && $this->options['settings']['fb_login']) {
                        $this->facebook_login(TRUE);
                }
        }

        function login_form() {
                if(isset($this->options['settings']['fb_login']) && $this->options['settings']['fb_login']) {
                        $this->facebook_login();
                }
        }

        function facebook_login($is_register = FALSE)
        {
                global $xs_socials_plugin;

                $token = $xs_socials_plugin->facebook_callback();

                if(empty($token)) {
                        $url = $xs_socials_plugin->facebook_url(wp_login_url());
                        echo apply_filters('xs_users_login_facebook', $url);
                        return TRUE;
                }

                $fb = $xs_socials_plugin->facebook_login($token);

                if(!is_array($fb)) {
                        return FALSE;
                }

                $user = get_user_by('email', $fb['email'] );

                if(isset($user->ID) && !empty($user->ID)) {
                        $user_id = $user->ID;
                } else {
                        $user_id = register_new_user( $fb['first_name'].' '.$fb['last_name'], $fb['email'] );
                        if (is_wp_error($user_id)) {
                                echo '<div id="message" class="error"><p>'.$user_id->get_error_message().'</p></div>';
                                return;
                        }

                        wp_update_user([
                                'ID' => $user_id,
                                'first_name' => $fb['first_name'],
                                'last_name' => $fb['last_name']
                        ]);
                        update_user_meta( $user_id, 'xs_socials_facebook_id', $fb['id']);
                }

                wp_set_auth_cookie( $user_id, TRUE );
                if(isset($_GET['redirect_to']) && !empty($_GET['redirect_to']))
                        wp_redirect($_GET['redirect_to']);
                else
                        wp_redirect(home_url());

                exit;
        }


        function user_register( $user_id )
        {
                $values = array();

                if($this->options['settings']['real_name']) {
                        $values['first_name'] = trim( $_POST['first_name'] );
                        $values['last_name'] = trim( $_POST['last_name'] );

                        update_user_meta($user_id, 'first_name', $values['first_name']);
                        update_user_meta($user_id, 'last_name', $values['last_name']);
                } else {
                        $values['first_name'] = '';
                        $values['last_name'] = '';
                }

                $fields = $this->options['fields'];

                foreach($fields as $key => $prop) {
                        if(!$prop['backend']) {
                                update_user_meta(
                                        $user_id,
                                        'xs_users_'.$key,
                                        $_POST['xs_users_'.$key]
                                );
                        }

                        $values['xs_users_'.$key] = $_POST['xs_users_'.$key];
                }

                do_action( 'xs_user_register', $user_id, $values );
        }

}

$xs_users_plugin = new xs_users_plugin();

endif;
