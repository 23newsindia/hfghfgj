<?php

namespace Sphere\Debloat;

use Sphere\Debloat\RemoveCss\Sanitizer;
use Sphere\Debloat\OptimizeCss\Stylesheet;

class RemoveCss
{
    public $dom;
    public $html;
    public $used_markup = [];
    protected $stylesheets;
    protected $include_sheets = [];
    protected $exclude_sheets = [];

    public function __construct($stylesheets, \DOMDocument $dom, string $raw_html)
    {
        $this->stylesheets = $stylesheets;
        $this->dom = $dom;
        $this->html = $raw_html;
    }
  
  
  

   public function process()
{
    // Start output buffering
    ob_start(function ($html) {
        if (!$html) {
            return $html;
        }

        // Load HTML into DOM
        $this->dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Collect all the classes, ids, tags used in DOM
        $this->find_used_selectors();

        // Figure out valid sheets
        $this->setup_valid_sheets();

        do_action('debloat/remove_css_begin', $this);
        $allow_selectors = $this->get_allowed_selectors();

        foreach ($this->stylesheets as $sheet) {
            if (!$this->should_process_stylesheet($sheet)) {
                continue;
            }

            $file_data = $this->process_file_by_url($sheet->url);
            if (!$file_data) {
                continue;
            }

            $sheet->content = $file_data['content'];
            $sheet->file = $file_data['file'];

            $this->setup_sheet_cache($sheet);

            $sanitizer = new Sanitizer($sheet, $this->used_markup, $allow_selectors);
            $sanitized_css = $sanitizer->sanitize();

            $sheet->original_size = strlen($sheet->content);
            $sheet->new_size = $sanitized_css ? strlen($sanitized_css) : $sheet->original_size;

            if ($sanitized_css) {
                $sheet->content = $sanitized_css;
                $sheet->render_id = 'debloat-' . $sheet->id;
                $sheet->set_render('inline');
                $replacement = $sheet->render();

                if (Plugin::delay_load()->should_delay_css()) {
                    $sheet->delay_type = Plugin::options()->delay_css_type;
                    $sheet->set_render('delay');
                    $sheet->has_delay = true;
                    $replacement .= $sheet->render();
                }

                $sheet->set_render('remove_css', $replacement);
                $this->save_sheet_cache($sheet);
            }

            $sheet->content = '';
            $sheet->parsed_data = '';
        }

        $total = array_reduce($this->stylesheets, function ($acc, $item) {
            if (!empty($item->original_size)) {
                $acc += ($item->original_size - $item->new_size);
            }
            return $acc;
        }, 0);

        // Append the optimization stats as a comment
        $html .= "\n<!-- Debloat Remove CSS Saved: {$total} bytes. -->";

        return $html;
    });

    // End output buffering and return processed HTML
    return ob_get_clean();
}

  
  
  
  
    public function setup_sheet_cache(Stylesheet $sheet)
{
    // Generate a unique cache key based on the stylesheet URL
    $cache_key = 'css_optimize_' . md5($sheet->url);
    $cached = get_transient($cache_key);

    if ($cached) {
        // Check if the cached file is up-to-date
        $file_time = Plugin::file_system()->mtime($sheet->file);
        if ($cached['time'] >= $file_time) {
            $sheet->content = $cached['content'];
            return true;
        }
    }

    return false;
}


    protected function get_transient_id($sheet)
    {
        return substr('debloat_sheet_cache_' . $sheet->id, 0, 190);
    }

    public function save_sheet_cache(Stylesheet $sheet)
    {
        if ($sheet->has_cache) {
            return;
        }
        
        $cache_data = [
            'data'  => $sheet->parsed_data,
            'mtime' => Plugin::file_system()->mtime($sheet->file)
        ];

        set_transient($this->get_transient_id($sheet), $cache_data, MONTH_IN_SECONDS);
    }

    public function setup_valid_sheets()
    {
        $default_excludes = [
            'wp-includes/css/dashicons.css',
            'admin-bar.css',
            'wp-mediaelement'
        ];
        
        $excludes = array_merge(
            $default_excludes, 
            Util\option_to_array(Plugin::options()->remove_css_excludes)
        );

        $this->exclude_sheets = apply_filters('debloat/remove_css_excludes', $excludes, $this);

        if (Plugin::options()->remove_css_all) {
            return;
        }

        if (Plugin::options()->remove_css_theme) {
            $this->include_sheets[] = content_url('themes') . '/*';
        }

        if (Plugin::options()->remove_css_plugins) {
            $this->include_sheets[] = content_url('plugins') . '/*';
        }

        $this->include_sheets = array_merge(
            $this->include_sheets,
            Util\option_to_array(Plugin::options()->remove_css_includes)
        );

        $this->include_sheets = apply_filters('debloat/remove_css_includes', $this->include_sheets, $this);
    }

    public function should_process_stylesheet(Stylesheet $sheet)
    {
        if ($this->exclude_sheets) {
            foreach ($this->exclude_sheets as $exclude) {
                if (Util\asset_match($exclude, $sheet)) {
                    return false;
                }
            }
        }

        if (Plugin::options()->remove_css_all) {
            return true;
        }

        foreach ($this->include_sheets as $include) {
            if (Util\asset_match($include, $sheet)) {
                return true;
            }
        }

        return false;
    }

    public function get_allowed_selectors()
    {
        $allowed_any = array_map(
            function($value) {
                if (!$value) {
                    return '';
                }

                return [
                    'type'   => 'any',
                    'search' => [$value]
                ];
            },
            Util\option_to_array((string) Plugin::options()->allow_css_selectors)
        );

        $allowed_conditionals = [];
        $conditionals = Plugin::options()->allow_css_conditionals
            ? (array) Plugin::options()->allow_conditionals_data
            : [];

        if ($conditionals) {
            $allowed_conditionals = array_map(
                function($value) {
                    if (!isset($value['match'])) {
                        return '';
                    }

                    $value['class'] = preg_replace('/^\./', '', trim($value['match']));

                    if ($value['type'] !== 'prefix' && isset($value['selectors'])) {
                        $value['search'] = Util\option_to_array($value['selectors']);
                    }

                    return $value;
                },
                $conditionals
            );
        }

        $allowed = apply_filters(
            'debloat/allow_css_selectors', 
            array_filter(array_merge($allowed_any, $allowed_conditionals)),
            $this
        );

        return $allowed;
    }

    protected function find_used_selectors()
    {
        $this->used_markup = [
            'tags'    => [],
            'classes' => [],
            'ids'     => [],
        ];

        $classes = [];
        foreach ($this->dom->getElementsByTagName('*') as $node) {
            $this->used_markup['tags'][ $node->tagName ] = 1;

            if ($node->hasAttribute('class')) {
                $class = $node->getAttribute('class');
                $ele_classes = preg_split('/\s+/', $class);
                array_push($classes, ...$ele_classes);
            }

            if ($node->hasAttribute('id')) {
                $this->used_markup['ids'][ $node->getAttribute('id') ] = 1;
            }
        }

        $classes = array_filter(array_unique($classes));
        if ($classes) {
            $this->used_markup['classes'] = array_fill_keys($classes, 1);
        }
    }

    public function process_file_by_url($url)
    {
        $file = Plugin::file_system()->url_to_local($url);
        if (!$file) {
            return false;
        }

        if (substr($file, -4) !== '.css') {
            return false;
        }

        $content = Plugin::file_system()->get_contents($file);
        if (!$content) {
            return false;
        }

        return [
            'content' => $content,
            'file'    => $file
        ];
    }
}