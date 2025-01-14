<?php

namespace Sphere\Debloat;

class Plugin {
    private static $instance = null;
    private $settings;
    private $stylesheets = [];
    private $original_css_backup = [];

    public function __construct() {
        if (self::$instance) {
            return self::$instance;
        }
        self::$instance = $this;
    }

    public function init() {
        if (is_admin()) {
            $this->settings = new Admin\Settings();
            add_action('admin_notices', [$this, 'show_backup_notice']);
        }

        add_action('wp_print_styles', [$this, 'process_styles'], 999999);
    }

    public static function options() {
        return (object) wp_parse_args(
            get_option('css_debloat_options', []), [
            'remove_css_all' => false, // Default to false for safety
            'remove_css_theme' => false,
            'remove_css_plugins' => false,
            'remove_css_excludes' => '',
            'allow_css_selectors' => '',
            'debug_mode' => false,
            'allow_css_conditionals' => true,
            'allow_conditionals_data' => [],
            'delay_css_type' => 'onload',
            'preserve_critical' => true
        ]);
    }

    public function process_styles() {
        if (!$this->should_process()) {
            return;
        }

        // Emergency stop if needed
        if (isset($_GET['disable_css_optimization'])) {
            return;
        }

        global $wp_styles;
        if (!is_object($wp_styles)) {
            return;
        }

        // Create DOM document
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);

        $stylesheets = [];
        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) {
                continue;
            }

            $style = $wp_styles->registered[$handle];
            if (!$style->src) {
                continue;
            }

            // Backup original CSS
            $this->backup_original_css($handle, $style->src);

            $stylesheet = new OptimizeCss\Stylesheet();
            $stylesheet->url = $style->src;
            $stylesheet->id = $handle;

            if (strpos($stylesheet->url, '//') === false) {
                if (strpos($stylesheet->url, '/') === 0) {
                    $stylesheet->url = site_url($stylesheet->url);
                } else {
                    $stylesheet->url = site_url('/' . $stylesheet->url);
                }
            }

            $stylesheets[] = $stylesheet;
            $this->stylesheets[$handle] = $stylesheet;
            wp_dequeue_style($handle);
        }

        if (empty($stylesheets)) {
            return;
        }

        // Add critical CSS preservation
        add_filter('debloat/allow_css_selectors', [$this, 'preserve_critical_css']);

        $remover = new RemoveCss($stylesheets, $dom, '');
        $remover->process();

        add_filter('style_loader_tag', function ($tag, $handle, $href, $media) {
            if (!isset($this->stylesheets[$handle])) {
                return $tag;
            }

            $sheet = $this->stylesheets[$handle];
            if (!$sheet->content) {
                // Fallback to original CSS if optimization fails
                $sheet->content = $this->get_original_css($handle);
            }

            return sprintf(
                '<style id="%s">%s</style>',
                esc_attr($sheet->render_id),
                $sheet->content
            );
        }, 10, 4);

        libxml_clear_errors();
    }

    protected function backup_original_css($handle, $src) {
        $file = Plugin::file_system()->url_to_local($src);
        if ($file && file_exists($file)) {
            $this->original_css_backup[$handle] = file_get_contents($file);
        }
    }

    protected function get_original_css($handle) {
        return isset($this->original_css_backup[$handle]) 
            ? $this->original_css_backup[$handle] 
            : '';
    }

    public function preserve_critical_css($selectors) {
        if (!self::options()->preserve_critical) {
            return $selectors;
        }

        $critical_selectors = [
            'body', 'html', '*', ':root', 
            '.container', '.wrapper', '.row',
            '.header', '.footer', '.content',
            '.navigation', '.menu', '.nav',
            '.button', '.btn', '.sidebar',
            '.widget', '.post', '.page',
            '.entry', '.article', '.main',
            '.site-header', '.site-footer',
            '.site-content', '.site-main',
            '[class*="wp-"]', // Preserve WordPress classes
            '[class*="menu"]', // Preserve menu-related classes
            '.current-', '.active', '.selected',
            '.show', '.hide', '.hidden',
            '.visible', '.invisible',
            '.collapse', '.expand',
            '.open', '.close',
            '.dropdown', '.modal',
            '.fade', '.slide',
            '@media', '@keyframes', '@font-face'
        ];

        foreach ($critical_selectors as $selector) {
            $selectors[] = [
                'type' => 'any',
                'search' => [$selector]
            ];
        }

        return $selectors;
    }

    private function should_process() {
        // Allow emergency disable via filter
        if (!apply_filters('debloat/should_process', true)) {
            return false;
        }

        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }

        if (in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php'])) {
            return false;
        }

        $options = self::options();
        return $options->remove_css_all || 
               $options->remove_css_theme || 
               $options->remove_css_plugins;
    }

    public function show_backup_notice() {
        if (isset($_GET['css_restore']) && $_GET['css_restore'] === 'true') {
            echo '<div class="notice notice-success"><p>CSS has been restored to original state.</p></div>';
        }
    }

    public static function file_system() {
        return new FileSystem();
    }

    public static function delay_load() {
        return new DelayLoad();
    }
}