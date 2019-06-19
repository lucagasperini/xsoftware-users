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
                add_action( 'user_register', [$this, 'user_register'] );
                add_action( 'edit_user_created_user', [$this, 'user_register'] );
                add_action('after_setup_theme', [$this, 'remove_admin_bar']);

                if(!empty($this->options['style']['login_logo']))
                        add_action('login_enqueue_scripts',  [$this, 'login_logo']);
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
                if($this->options['real_name']) {
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


                xs_framework::html_input_array_to_table(
                        $data,
                        [ 'class' => 'xs_full_width' ]
                );

        }

        function user_register( $user_id )
        {
                $values = array();

                if($this->options['real_name']) {
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

endif;

$xs_users_plugin = new xs_users_plugin();