<?php

namespace Sphere\Debloat;

class FileSystem {
    public function url_to_local($url) {
        $site_url = site_url();
        $content_url = content_url();
        
        // Convert to path
        if (strpos($url, $site_url) === 0) {
            $path = str_replace($site_url, ABSPATH, $url);
        } elseif (strpos($url, $content_url) === 0) {
            $path = str_replace($content_url, WP_CONTENT_DIR, $url);
        } else {
            return false;
        }
        
        return $path;
    }
    
    public function get_contents($file) {
        if (!file_exists($file)) {
            return false;
        }
        
        return file_get_contents($file);
    }
    
    public function mtime($file) {
        if (!file_exists($file)) {
            return 0;
        }
        
        return filemtime($file);
    }
}