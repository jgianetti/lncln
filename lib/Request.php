<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 20/04/12
 * Time: 21:30
 *
 * TODO: /module/action/{var}
 *
 */
class Request
{
    protected $_post_vars;
    protected $_get_vars;
    protected $_url;
    protected $_base_url;
    protected $_module;
    protected $_action;
    protected $_files;

    protected $_rows_per_pag; // default paginator - used in $this->get_jqdt_search_params()

    public $is_ajax = false;

    /**
     * Create Request object using $_GET, $_POST, $_SERVER, or $cfg values
     *
     * @param array $cfg
     * @param string $url
     */
    public function __construct($cfg, $url = null)
    {
        $this->_base_url     = $cfg['base_url'];
        $this->_rows_per_pag = $cfg['rows_per_pag'];

        // POST comes as JSON UTF-8 encoded
        array_walk_recursive($_POST, function(&$value, $key) { if (is_string($value)) { $value = utf8_decode($value); } });
        $this->_post_vars = &$_POST;

        // GET vars from url
        if ($url) {
            $url_path = '';
            $url_parts = explode('?', $url);
            if (count($url_parts)) $url_path = parse_url($url_parts[0], PHP_URL_PATH);
            if (count($url_parts) > 1) parse_str($url_parts[1], $this->_get_vars);
        }
        else {
            $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $this->_get_vars = &$_GET;
        }
        $this->_url = $url_path;

        // strip base url
        if (substr($url_path, 0, strlen($this->_base_url)) == $this->_base_url) $url_path = substr($url_path, strlen($this->_base_url));
        $url_parts = explode('/', trim($url_path, '/'));

        if ($this->fetch('ajax')) $this->is_ajax = true;
        if ($url_parts && ($url_parts[0] == 'api')) {
            $this->is_ajax = true;
            array_shift($url_parts);
        }

        // Module from GET | POST | $url
        if (!($module = $this->fetch('m')) && $url_parts) $module = array_shift($url_parts);
        $this->_module = $module;

        // Action from GET | POST | $url
        if (!($action = $this->fetch('a')) && $url_parts) $action = array_shift($url_parts);
        $this->_action = $action;


        $this->_files = &$_FILES;
    }

    /**
     * Return GET variable (if exists), POST variable (if exists) or false
     * @param string $var_name
     * @return mixed|bool
     */
    public function fetch($var_name)
    {
        return (!is_null($val = $this->get($var_name)) ? $val : (!is_null($val = $this->post($var_name)) ? $val : null));
    }

    /**
     * Return GET variable (if exists) or false
     * @param string $var_name
     * @return mixed|bool
     */
    public function get($var_name = null)
    {
        if ($var_name === null) return $this->_get_vars;
        return (isset($this->_get_vars[$var_name]) ? $this->_get_vars[$var_name] : null);
    }

    /**
     * @param array $arr
     * @return Request
     */
    public function setGetVars($arr)
    {
        $this->_get_vars = $arr;
        return $this;
    }

    /**
     * @param string $name
     * @param string $val
     * @return Request
     */
    public function setGetVar($name, $val)
    {
        $this->_get_vars[$name] = $val;
        return $this;
    }

    /**
     * Return POST variable (if exists) or false
     * @param string $var_name
     * @return mixed|bool
     */
    public function post($var_name = null)
    {
        if ($var_name === null) return $this->_post_vars;
        return (isset($this->_post_vars[$var_name]) ? $this->_post_vars[$var_name] : null);
    }

    public function files($file_name = null)
    {
        if (!$file_name) return $this->_files;
        return (isset($this->_files[$file_name]) ? $this->_files[$file_name] : null);
    }

    /**
     * @param array $arr
     * @return Request
     */
    public function setPostVars($arr)
    {
        $this->_post_vars = $arr;
        return $this;
    }

    /**
     * @param string $name
     * @param string $val
     * @return Request
     */
    public function setPostVar($name, $val)
    {
        $this->_post_vars[$name] = $val;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getModule()
    {
        return $this->_module;
    }

    /**
     * @param string $module
     * @return Request
     */
    public function setModule($module)
    {
        $this->_module = $module;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getAction()
    {
        return $this->_action;
    }

    /**
     * @param string $action
     * @return Request
     */
    public function setAction($action)
    {
        $this->_action = $action;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        $url = $this->_base_url;
        if ($this->getModule()) {
            $url .= '/' . $this->getModule();
            if ($this->getAction()) $url .= '/' . $this->getAction();
        }
        return $url . '?' . http_build_query($this->_get_vars);
    }
    
    /**
     * Retrieve search parameters (GET || jQuery dataTables related)
     * 
     * @param array $default_theaders
     * @param string $default_select
     * @return array
     */
    public function get_search_params(array $default_theaders = [])
    {
        $search_params = [];
        
        if ($this->fetch('select') || $this->fetch('filters') || $this->fetch('limit')) {
            $search_params['jqdt']      = false;
            $search_params['select']    = $this->fetch('select');
            $search_params['filters']   = $this->fetch('filters');
            $search_params['limit']     = $this->fetch('limit');
            if (($search_params['order_by'] = $this->fetch('order_by'))) $search_params['order_by'] = $this->fetch('order_by') . ' ' . $this->fetch('order_dir');
        }
        else {
            // jQuery Datatables
            $search_params           = $this->get_jqdt_search_params($default_theaders);
            $search_params['jqdt']   = true;
            $search_params['select'] = null;
        }
        return $search_params;
    }
    
    /**
     * Retrieve jQuery dataTables related search parameters
     * 
     * @param array $default_theaders
     * @return array
     */
    public function get_jqdt_search_params(array $default_theaders = []) {
        // jQuery Datatables headers
        if (!($theaders = $this->fetch('theaders'))) $theaders = $default_theaders;
        else $theaders = explode(',', $theaders);

        $filters = [];
        if (!is_null($this->fetch('sEcho'))) for ($i = 0; $i < count($theaders); $i++) $filters[$theaders[$i]] = $this->fetch('sSearch_' . $i);

        if (!is_null($order_by = $this->fetch('iSortCol_0'))) $order_by = $theaders[$order_by] . ' ' . strtoupper($this->fetch('sSortDir_0'));

        if (!($limit = $this->fetch('iDisplayStart'))) $limit = 0;
        if (is_null($this->fetch('iDisplayLength')))   $limit .= ', '. $this->_rows_per_pag;
        elseif ($this->fetch('iDisplayLength') > 0)    $limit .= ', ' . $this->fetch('iDisplayLength');

        return [
            'theaders'  => $theaders,
            'filters'   => $filters,
            'order_by'  => $order_by,
            'limit'     => $limit,
        ];
    }
}