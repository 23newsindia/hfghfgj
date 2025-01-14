<?php

namespace Sphere\Debloat;
use Sphere\Debloat\Admin\OptionsData;

/**
 * A very basic options class.
 * 
 * @author  asadkn
 * @since   1.0.0
 */
class Options
{
	/**
	 * @var string|array Option key to use for get_options().
	 */
	public $option_key;
	public $_options;
	public $defaults = [];

	/**
	 * @param string|array $option_key
	 */
	public function __construct($option_key)
	{
		$this->option_key = $option_key;
	}

	/**
	 * Initialize
	 */
	public function init()
	{
		$this->load_defaults();
		
		if (is_array($this->option_key)) {
			$this->_options = [];
			
			foreach ($this->option_key as $key) {
				$this->_options = array_merge($this->_options, (array) get_option($key));
			}

		} else {
			$this->_options = (array) get_option($this->option_key);
		}

		$this->_options = apply_filters('debloat/init_options', $this->_options);
	}

	public function load_defaults()
	{
		if (!class_exists('Sphere\Debloat\Admin\OptionsData')) {
			return;
		}

		$this->defaults = array_reduce(
			OptionsData::get_all(),
			function($acc, $option) {
				$acc[$option['id']] = isset($option['default']) ? $option['default'] : '';
				return $acc;
			},
			[]
		);
	}

	/**
	 * Get an option
	 */
	public function get($key, $fallback = '')
	{
		if (array_key_exists($key, $this->_options)) {
			return $this->_options[$key];
		}

		if (array_key_exists($key, $this->defaults)) {
			return $this->defaults[$key];
		}

		return $fallback;
	}

	public function __get($key)
	{
		return $this->get($key);
	}

	public function __set($key, $value)
	{
		$this->_options[$key] = $value;
	}
}