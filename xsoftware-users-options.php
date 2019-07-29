<?php

if(!defined("ABSPATH")) die;

if (!class_exists("xs_users_options")) :

class xs_users_options
{

        private $default = array (
                'settings' => [
                        'real_name' => TRUE,
                ],
                'fields' => [
                ],
                'style' => [
                        'login_logo' => '',
                        'login_logo_w' => 150,
                        'login_logo_h' => 150,
                ]
        );


        private $options = array( );

        function __construct()
        {
                add_action('admin_menu', [$this, 'admin_menu']);
                add_action('admin_init', [$this, 'section_menu']);

                $this->options = get_option('xs_options_users', $this->default);
        }

        function admin_menu()
        {
                add_submenu_page(
                        'xsoftware',
                        'XSoftware Users',
                        'Users',
                        'manage_options',
                        'xsoftware_users',
                        [$this, 'menu_page']
                );
        }


        public function menu_page()
        {
                if ( !current_user_can( 'manage_options' ) )  {
                        wp_die( __( 'Exit!' ) );
                }

                echo '<div class="wrap">';

                echo '<form action="options.php" method="post">';

                settings_fields('xs_users_setting');
                do_settings_sections('xs_users');

                submit_button( '', 'primary', 'submit', true, NULL );
                echo '</form>';

                echo '</div>';

        }

        function section_menu()
        {
                register_setting(
                        'xs_users_setting',
                        'xs_options_users',
                        [$this, 'input']
                );
                add_settings_section(
                        'xs_users_section',
                        'Settings',
                        [$this, 'show'],
                        'xs_users'
                );
        }

        function show()
        {
                $tab = xs_framework::create_tabs( [
                        'href' => '?page=xsoftware_users',
                        'tabs' => [
                                'settings' => 'Settings',
                                'fields' => 'Fields',
                                'style' => 'Style'
                        ],
                        'home' => 'settings',
                        'name' => 'main_tab'
                ]);

                switch($tab) {
                        case 'settings':
                                $this->show_settings();
                                return;
                        case 'fields':
                                $this->show_fields();
                                return;
                        case 'style':
                                $this->show_style();
                                return;
                }
        }

        function input($input)
        {
                $current = $this->options;

                $current['settings']['real_name'] = isset($input['settings']['real_name']);
                $current['settings']['minimal'] = isset($input['settings']['minimal']);
                $current['settings']['fb_login'] = isset($input['settings']['fb_login']);
                $current['settings']['analytics'] = $input['settings']['analytics'];

                if(isset($input['fields'])) {
                        $f = $input['fields'];
                        if(
                                isset($f['new']) &&
                                !empty($f['new']['code']) &&
                                !empty($f['new']['name']) &&
                                !empty($f['new']['type'])
                        ) {
                                $code = $f['new']['code'];
                                $f['new']['backend'] = isset($f['new']['backend']);
                                unset($f['new']['code']);
                                $current['fields'][$code] = $f['new'];
                        }
                        if(!empty($f['delete'])) {
                                unset($current['fields'][$f['delete']]);
                        }
                }

                if(isset($input['style']) && !empty($input['style']))
                        foreach($input['style'] as $key => $value)
                                $current['style'][$key] = $value;


                return $current;
        }

        function show_settings()
        {
                $options = array(
                        'name' => 'xs_options_users[settings][real_name]',
                        'compare' => $this->options['settings']['real_name'],
                        'echo' => TRUE
                );
                add_settings_field(
                        $options['name'],
                        'Add real name on registation form',
                        'xs_framework::create_input_checkbox',
                        'xs_users',
                        'xs_users_section',
                        $options
                );

                $options = array(
                        'name' => 'xs_options_users[settings][minimal]',
                        'compare' => $this->options['settings']['minimal'],
                        'echo' => TRUE
                );

                add_settings_field(
                        $options['name'],
                        'Use only E-Mail to register the user',
                        'xs_framework::create_input_checkbox',
                        'xs_users',
                        'xs_users_section',
                        $options
                );

                $options = array(
                        'name' => 'xs_options_users[settings][analytics]',
                        'value' => $this->options['settings']['analytics'],
                        'echo' => TRUE
                );

                add_settings_field(
                        $options['name'],
                        'Google Analytics ID:',
                        'xs_framework::create_input',
                        'xs_users',
                        'xs_users_section',
                        $options
                );

                $options = [
                        'name' => 'xs_options_users[settings][fb_login]',
                        'compare' => $this->options['settings']['fb_login'],
                        'echo' => TRUE
                ];

                add_settings_field(
                        $options['name'],
                        'User login and register with Facebook:',
                        'xs_framework::create_input_checkbox',
                        'xs_users',
                        'xs_users_section',
                        $options
                );
        }

        function show_fields()
        {
                $fields = $this->options['fields'];

                $headers = array('Code', 'Name', 'Type', 'Save on Backend', 'Actions');
                $data = array();
                $types = xs_framework::html_input_array_types();
                unset($types['img']);

                foreach($fields as $key => $single) {
                        $data[$key][0] = $key;
                        $data[$key][1] = $single['name'];
                        $data[$key][2] = $types[$single['type']];
                        $data[$key][3] = $single['backend'] ? 'Yes' : 'No';
                        $data[$key][4] = xs_framework::create_button([
                                'name' => 'xs_options_users[fields][delete]',
                                'class' => 'button-primary',
                                'value' => $key,
                                'text' => 'Remove'
                        ]);
                }

                $new[0] = xs_framework::create_input([
                        'name' => 'xs_options_users[fields][new][code]'
                ]);
                $new[1] = xs_framework::create_input([
                        'name' => 'xs_options_users[fields][new][name]'
                ]);
                $new[2] = xs_framework::create_select([
                        'name' => 'xs_options_users[fields][new][type]',
                        'data' => $types
                ]);
                $new[3] = xs_framework::create_input_checkbox([
                        'name' => 'xs_options_users[fields][new][backend]'
                ]);

                $data[] = $new;

                xs_framework::create_table(array(
                        'class' => 'xs_admin_table xs_full_width',
                        'headers' => $headers,
                        'data' => $data
                ));
        }

        function show_style()
        {
                wp_enqueue_media();

                $style = $this->options['style'];

                $options = array(
                        'width' => 150,
                        'height' => 150,
                        'src' => $style['login_logo'],
                        'alt' => $style['login_logo'],
                        'id' => 'xs_options_users[style][login_logo]',
                        'name' => 'xs_options_users[style][login_logo]',
                        'echo' => TRUE
                );

                add_settings_field(
                        $options['name'],
                        'Set login image',
                        'xs_framework::create_select_media_gallery',
                        'xs_users',
                        'xs_users_section',
                        $options
                );

                $options = array(
                        'name' => 'xs_options_users[style][login_logo_w]',
                        'max' => 999999999,
                        'value' => intval($style['login_logo_w']),
                        'echo' => TRUE
                );

                add_settings_field(
                        $options['name'],
                        'Set login image width (px)',
                        'xs_framework::create_input_number',
                        'xs_users',
                        'xs_users_section',
                        $options
                );

                $options = array(
                        'name' => 'xs_options_users[style][login_logo_h]',
                        'max' => 999999999,
                        'value' => intval($style['login_logo_h']),
                        'echo' => TRUE
                );

                add_settings_field(
                        $options['name'],
                        'Set login image heigth (px)',
                        'xs_framework::create_input_number',
                        'xs_users',
                        'xs_users_section',
                        $options
                );
        }

}

endif;

$xs_users_options = new xs_users_options();