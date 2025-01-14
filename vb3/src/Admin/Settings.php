<?php

namespace Sphere\Debloat\Admin;

class Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu_page() {
        add_options_page(
            'CSS Debloat Settings',
            'CSS Debloat',
            'manage_options',
            'css-debloat-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('css_debloat_settings', 'css_debloat_options');

        add_settings_section(
            'css_debloat_main',
            'Main Settings',
            [$this, 'section_callback'],
            'css_debloat_settings'
        );

        add_settings_field(
            'remove_css_all',
            'Process All Stylesheets',
            [$this, 'checkbox_field_callback'],
            'css_debloat_settings',
            'css_debloat_main',
            ['label_for' => 'remove_css_all']
        );

        add_settings_field(
            'remove_css_theme',
            'Process Theme Stylesheets',
            [$this, 'checkbox_field_callback'],
            'css_debloat_settings',
            'css_debloat_main',
            ['label_for' => 'remove_css_theme']
        );

        add_settings_field(
            'remove_css_plugins',
            'Process Plugin Stylesheets',
            [$this, 'checkbox_field_callback'],
            'css_debloat_settings',
            'css_debloat_main',
            ['label_for' => 'remove_css_plugins']
        );

        add_settings_field(
            'remove_css_excludes',
            'Exclude Stylesheets',
            [$this, 'textarea_field_callback'],
            'css_debloat_settings',
            'css_debloat_main',
            ['label_for' => 'remove_css_excludes']
        );

        add_settings_field(
            'allow_css_selectors',
            'Always Include Selectors',
            [$this, 'textarea_field_callback'],
            'css_debloat_settings',
            'css_debloat_main',
            ['label_for' => 'allow_css_selectors']
        );
    }

    public function section_callback() {
        echo '<p>Configure how CSS optimization should work on your site.</p>';
    }

    public function checkbox_field_callback($args) {
        $options = get_option('css_debloat_options');
        $field = str_replace('[]', '', $args['label_for']);
        ?>
        <input type="checkbox" 
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="css_debloat_options[<?php echo esc_attr($field); ?>]"
               value="1"
               <?php checked(isset($options[$field]) ? $options[$field] : 0); ?>>
        <?php
    }

    public function textarea_field_callback($args) {
        $options = get_option('css_debloat_options');
        $field = str_replace('[]', '', $args['label_for']);
        ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>"
                  name="css_debloat_options[<?php echo esc_attr($field); ?>]"
                  rows="5"
                  cols="50"><?php echo esc_textarea(isset($options[$field]) ? $options[$field] : ''); ?></textarea>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'css_debloat_messages',
                'css_debloat_message',
                'Settings Saved',
                'updated'
            );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('css_debloat_messages'); ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('css_debloat_settings');
                do_settings_sections('css_debloat_settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
}