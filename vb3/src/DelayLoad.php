<?php

namespace Sphere\Debloat;

class DelayLoad {
    public function should_delay_css() {
        return apply_filters('debloat/should_delay_css', false);
    }
}