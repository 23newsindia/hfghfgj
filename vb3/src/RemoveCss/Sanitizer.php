<?php

namespace Sphere\Debloat\RemoveCss;

use Sabberworm\CSS\CSSList\AtRuleBlockList;
use Sabberworm\CSS\CSSList\CSSBlockList;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser as CSSParser;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Value\URL;

use Sphere\Debloat\OptimizeCss\Stylesheet;
use Sphere\Debloat\Util;

class Sanitizer {
    protected $sheet;
    protected $css;
    protected $used_markup = [
        'classes' => [],
        'tags'    => [],
        'ids'     => []
    ];
    protected $allow_selectors = [];
    protected $preserved_selectors = [];

    public function __construct(Stylesheet $sheet, array $used_markup, $allow = []) {
        $this->sheet = $sheet;
        $this->css = $sheet->content;
        $this->used_markup = array_replace($this->used_markup, $used_markup);
        $this->allow_selectors = $allow;
        $this->init_preserved_selectors();
    }

    protected function init_preserved_selectors() {
        $this->preserved_selectors = [
            'classes' => [
                'current-menu-item', 'active', 'current',
                'selected', 'hover', 'focus', 'visited',
                'disabled', 'enabled', 'checked',
                'first', 'last', 'odd', 'even',
                'visible', 'hidden', 'collapsed',
                'expanded', 'dropdown', 'menu-item',
                'sub-menu', 'submenu', 'sticky',
                'fixed', 'absolute', 'relative',
                'wp-', 'menu-', 'post-', 'page-',
                'widget-', 'sidebar-', 'comment-',
                'header-', 'footer-', 'nav-'
            ],
            'tags' => [
                'html', 'body', 'div', 'span', 'p',
                'a', 'img', 'button', 'input', 'form',
                'header', 'footer', 'nav', 'main',
                'article', 'section', 'aside', 'ul',
                'ol', 'li', 'table', 'tr', 'td', 'th'
            ],
            'pseudo' => [
                ':hover', ':focus', ':active',
                ':visited', ':disabled', ':enabled',
                ':checked', ':first-child', ':last-child',
                ':nth-child', ':not', ':before', ':after'
            ]
        ];
    }

    public function sanitize() {
        try {
            $data = $this->sheet->parsed_data ?: [];
            if (!$data) {
                $this->css = preg_replace('/^\xEF\xBB\xBF/', '', $this->css);
                
                $config = Settings::create()->withMultibyteSupport(true);
                $parser = new CSSParser($this->css, $config);
                $parsed = $parser->parse();

                $this->convert_urls($parsed);
                $data = $this->transform_data($parsed);

                $this->sheet->parsed_data = $data;
            }

            $this->process_allowed_selectors();
            $this->add_preserved_selectors();

            return $this->render_css($data);
        } catch (\Exception $e) {
            // Log error and return original CSS
            error_log('CSS Sanitizer Error: ' . $e->getMessage());
            return $this->css;
        }
    }

    protected function add_preserved_selectors() {
        foreach ($this->preserved_selectors['classes'] as $class) {
            $this->used_markup['classes'][$class] = 1;
        }
        foreach ($this->preserved_selectors['tags'] as $tag) {
            $this->used_markup['tags'][$tag] = 1;
        }
    }

    public function should_include($selector) {
        // Always preserve @-rules
        if (strpos($selector['selector'], '@') === 0) {
            return true;
        }

        // Check for preserved pseudo-selectors
        foreach ($this->preserved_selectors['pseudo'] as $pseudo) {
            if (strpos($selector['selector'], $pseudo) !== false) {
                return true;
            }
        }

        // Check for critical selectors
        $critical_selectors = ['html', 'body', '*', ':root'];
        if (in_array($selector['selector'], $critical_selectors)) {
            return true;
        }

        // Check for attribute selectors
        if (!empty($selector['attrs'])) {
            return true;
        }

        // Check allowed selectors
        if ($this->is_allowed_selector($selector)) {
            return true;
        }

        // Check if any part of the selector is used
        $has_used_parts = false;
        
        if (!empty($selector['classes'])) {
            foreach ($selector['classes'] as $class) {
                if (isset($this->used_markup['classes'][$class])) {
                    $has_used_parts = true;
                    break;
                }
            }
        }

        if (!empty($selector['ids'])) {
            foreach ($selector['ids'] as $id) {
                if (isset($this->used_markup['ids'][$id])) {
                    $has_used_parts = true;
                    break;
                }
            }
        }

        if (!empty($selector['tags'])) {
            foreach ($selector['tags'] as $tag) {
                if (isset($this->used_markup['tags'][$tag])) {
                    $has_used_parts = true;
                    break;
                }
            }
        }

        return $has_used_parts;
    }

    protected function is_allowed_selector($selector) {
        foreach ($this->allow_selectors as $allowed) {
            if (isset($allowed['computed_search_regex'])) {
                if (preg_match('#' . $allowed['computed_search_regex'] . '#', $selector['selector'])) {
                    return true;
                }
            }
        }
        return false;
    }
  
  
  
      protected function find_used_selectors()
    {
        // Add support for dynamic classes
        add_filter('debloat/used_selectors', function ($selectors) {
            // Add WordPress dynamic classes
            $selectors['classes'][] = 'current-menu-item';
            $selectors['classes'][] = 'active';

            // Add other dynamic classes if needed
            $selectors['classes'][] = 'open'; // Example of an additional class
            return $selectors;
        });

        // Apply filters to extend the used selectors for classes
        $this->used_markup['classes'] = apply_filters(
            'debloat/used_selectors',
            $this->used_markup['classes']
        );

        // Ensure other used_markup types (e.g., tags and IDs) are processed
        // Example: Add tags dynamically if necessary
        add_filter('debloat/used_tags', function ($tags) {
            $tags[] = 'main'; // Example tag
            $tags[] = 'header'; // Example tag
            return $tags;
        });

        $this->used_markup['tags'] = apply_filters(
            'debloat/used_tags',
            $this->used_markup['tags']
        );

        // Process IDs dynamically if needed
        add_filter('debloat/used_ids', function ($ids) {
            $ids[] = 'unique-id'; // Example ID
            return $ids;
        });

        $this->used_markup['ids'] = apply_filters(
            'debloat/used_ids',
            $this->used_markup['ids']
        );
    }
}
  

    public function convert_urls(Document $data)
    {
        $base_url = preg_replace('#[^/]+\?.*$#', '', $this->sheet->url);

        $values = $data->getAllValues();
        foreach ($values as $value) {
            if (!($value instanceof URL)) {
                continue;
            }

            $url = $value->getURL()->getString();
            if (preg_match('/^(https?|data):/', $url)) {
                continue;
            }

            $parsed_url = parse_url($url);
            if (!empty($parsed_url['host']) || empty($parsed_url['path']) || $parsed_url['path'][0] === '/') {
                continue;
            }

            $new_url = $base_url . $url;
            $value->getUrl()->setString($new_url);
        }
    }

    protected function transform_data(CSSBlockList $data)
    {
        $items = [];
        foreach ($data->getContents() as $content) {
            if ($content instanceof AtRuleBlockList) {
                $items[] = [
                    'rulesets' => $this->transform_data($content),
                    'at_rule'  => "@{$content->atRuleName()} {$content->atRuleArgs()}",
                ];
            }
            else {
                $item = [
                    'css' => $content->render(OutputFormat::createCompact())
                ];

                if ($content instanceof DeclarationBlock) {
                    $item['selectors'] = $this->parse_selectors($content->getSelectors());
                }

                $items[] = $item;
            }
        }

        return $items;
    }

    protected function parse_selectors($selectors)
    {
        $selectors = array_map(
            function($sel) {
                return $sel->__toString();
            },
            $selectors
        );

        $selectors_data = [];
        foreach ($selectors as $selector) {
            $data = [
                'classes'  => [],
                'ids'      => [],
                'tags'     => [],
                'attrs'    => [],
                'selector' => trim($selector),
            ];

            $selector = preg_replace('/(?<!\\\\)::?[a-zA-Z0-9_-]+(\(.+?\))?/', '', $selector);

            $selector = preg_replace_callback(
                '/\[([A-Za-z0-9_:-]+)(\W?=[^\]]+)?\]/', 
                function($matches) use (&$data) {
                    $data['attrs'][] = $matches[1];
                    return '';
                },
                $selector
            );

            $selector = preg_replace_callback(
                '/\.((?:[a-zA-Z0-9_-]+|\\\\.)+)/',
                function($matches) use (&$data) {
                    $data['classes'][] = stripslashes($matches[1]);
                    return '';
                },
                $selector
            );

            $selector = preg_replace_callback(
                '/#([a-zA-Z0-9_-]+)/',
                function($matches) use (&$data) {
                    $data['ids'][] = $matches[1];
                    return '';
                },
                $selector
            );

            $selector = preg_replace_callback(
                '/[a-zA-Z0-9_-]+/',
                function($matches) use (&$data) {
                    $data['tags'][] = $matches[0];
                    return '';
                },
                $selector
            );

            $selectors_data[] = array_filter($data);
        }

        return array_filter($selectors_data);
    }

    public function render_css($data)
    {
        $rendered = [];
        foreach ($data as $item) {
            if (isset($item['css'])) {
                $css = $item['css'];
                $should_render = !isset($item['selectors']) || 
                    0 !== count(
                        array_filter(
                            $item['selectors'],
                            function($selector) {
                                return $this->should_include($selector);
                            }
                        )
                    );

                if ($should_render) {
                    $rendered[] = $css;
                }
                continue;
            }

            if (!empty($item['rulesets'])) {
                $child_rulesets = $this->render_css($item['rulesets']);
                if ($child_rulesets) {
                    $rendered[] = sprintf(
                        '%s { %s }',
                        $item['at_rule'],
                        $child_rulesets
                    );
                }
            }
        }

        return implode("", $rendered);
    }

    protected function process_allowed_selectors()
    {       
        foreach ($this->allow_selectors as $key => $value) {
            if (isset($value['sheet']) && !Util\asset_match($value['sheet'], $this->sheet)) {
                unset($this->allow_selectors[$key]);
                continue;
            }

            $value = $this->add_search_regex($value);
            $regex = $value['search_regex'] ?? '';

            if (isset($value['search'])) {
                $value['search'] = array_filter((array) $value['search']);
                if ($value['search']) {
                    $loose_regex = '(' . implode('|', array_map('preg_quote', $value['search'])) . ')(?=\s|\.|\:|,|\[|$)';
                    $regex = $regex ? "($loose_regex|$regex)" : $loose_regex;
                }
            }

            if ($regex) {
                $value['computed_search_regex'] = $regex;
            }
            
            $this->allow_selectors[$key] = $value;
        }
    }

    protected function add_search_regex(array $value)
    {
        if (isset($value['search_regex'])) {
            return $value;
        }

        if (isset($value['search'])) {
            $value['search'] = (array) $value['search'];
            $regex = [];

            foreach ($value['search'] as $key => $search) {
                if (strpos($search, '*') !== false) {
                    $search = trim($search);
                    $has_first_asterisk = 0;
                    $search = preg_replace('/^\*(.+?)/', '\\1', $search, 1, $has_first_asterisk);
                    $search = preg_quote($search);
                    $search = str_replace(' \*', '(\s|$|,|\:).*?', $search);
                    $search = str_replace('\*', '.*?', $search);
                    $regex[] = ($has_first_asterisk ? '' : '^') . $search;
                    unset($value['search'][$key]);
                }
            }

            if ($regex) {
                $value['search_regex'] = '(' . implode('|', $regex) . ')';
            }
        }
        
        return $value;
    }

    public function should_include($selector)
    {
        if ($selector['selector'] === ':root') {
            return true;
        }

        if (!empty($selector['attrs'])
            && (empty($selector['classes']) && empty($selector['ids']) && empty($selector['tags']))
        ) {
            return true;
        }

        if ($this->allow_selectors) {           
            foreach ($this->allow_selectors as $include) {
                if ($include['type'] === 'prefix') {
                    if (('.' . $include['class']) === $selector['selector']) {
                        return true;
                    }

                    $has_prefix = $include['class'] === substr($selector['selector'], 1, strlen($include['class']));
                    if ($has_prefix) {
                        if (isset($selector['classes'])) {
                            $selector['classes'] = array_diff($selector['classes'], [$include['class']]);
                        }
                        break;
                    }
                    continue;
                }

                if ($include['type'] === 'class') {
                    if (!$this->is_used($include['class'], 'classes')) {
                        continue;
                    }
                }

                if (!empty($include['computed_search_regex'])) {
                    if (preg_match('#' . $include['computed_search_regex'] . '#', $selector['selector'])) {
                        return true;
                    }
                }
            }
        }

        $valid = true;
        if (
            (!empty($selector['classes']) && !$this->is_used($selector['classes'], 'classes'))
            || (!empty($selector['ids']) && !$this->is_used($selector['ids'], 'ids'))
            || (!empty($selector['tags']) && !$this->is_used($selector['tags'], 'tags'))
        ) {
            $valid = false;
        }

        return $valid;
    }

    public function is_used($targets, $type = '')
    {
        if (!$type) {
            return false;
        }

        if (!is_array($targets)) {
            $targets = (array) $targets;
        }

        foreach ($targets as $target) {
            if (!isset($this->used_markup[$type][$target])) {   
                return false;
            }
        }

        return true;
    }
}