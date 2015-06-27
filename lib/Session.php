<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 22/05/13
 * Time: 1:39
 */
class Session
{
    protected $_key;
    protected $_vars = array();

    public function __construct($key)
    {
        session_start();
        $this->_key = $key;
        if (isset($_SESSION['data'])) $this->_vars = unserialize($this->decrypt($_SESSION['data'], $key));
    }

    /**
     * TODO: _var[name] === false
     * @param string $name
     * @return mixed|bool
     */
    public function get($name)
    {
        return (isset($this->_vars[$name]) ? $this->_vars[$name] : false);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return Session
     */
    public function set($name, $value)
    {
        $this->_vars[$name] = $value;
        return $this;
    }

    /**
     * @return Session
     */
    public function save()
    {
        $_SESSION['data'] = $this->encrypt(serialize($this->_vars), $this->_key);
        return $this;
    }

    /**
     * @param string $name
     * @return Session
     */
    public function destroy($name = null)
    {
        if ($name) {
            if (!empty($this->_vars[$name])) {
                unset($this->_vars[$name]);
                return $this->save();
            }
        }
        else {
            if (isset($_SESSION['data'])) unset($_SESSION['data']);
            $this->_vars = array();
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getVars()
    {
        return $this->_vars;
    }

    /**
     * @param string $data
     * @param string $key
     * @return string
     */
    function encrypt($data, $key){
        $result = '';
        for($i=0;$i<strlen($data);$i++){
            $char    = substr($data, $i, 1);
            $keyChar = substr($key, ($i % strlen($key)) - 1, 1);
            $char    = chr(ord($char) + ord($keyChar));
            $result .= $char;
        }
        return strrev(base64_encode($result));
    }

    /**
     * @param string $data
     * @param string $key
     * @return string
     */
    function decrypt($data, $key){
        $result = '';
        $data   = base64_decode(strrev($data));
        for($i=0;$i<strlen($data);$i++){
            $char    = substr($data, $i, 1);
            $keyChar = substr($key, ($i % strlen($key)) - 1, 1);
            $char    = chr(ord($char) - ord($keyChar));
            $result .= $char;
        }
        return $result;
    }
}
