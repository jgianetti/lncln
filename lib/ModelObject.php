<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */
class ModelObject extends \ArrayObject
{
    /**
     * Indicate valid properties
     * @var array
     */
    protected $_props = array();

    /**
     * Helps to validate the object through errors()
     * @var array
     */
    protected $_validator = array();
    
    public function __construct($input = null)
    {
        $this->exchangeArray($input);
    }
    
    public function exchangeArray($input = null)
    {
        foreach ($this->_props as $prop) {
            if (empty($input[$prop])) $value = null;
            else $value = $input[$prop];
            
            $setterMethod = 'set'.str_replace(' ','',ucwords(str_replace('_', ' ', $prop)));
            if (method_exists($this, $setterMethod)) $this->$setterMethod($value);
            else $this[$prop] = $value;
        }
    }
    
    public function getArrayCopy() {
        $return = [];
        foreach ($this->_props as $prop) {
            $getterMethod = 'get'.str_replace(' ','',ucwords(str_replace('_', ' ', $prop)));
            if (method_exists($this, $getterMethod)) $return[$prop] = $this->$getterMethod();
            else $return[$prop] = $this[$prop];
        }
        return $return;
    }

    /**
     * Validate object properties
     * @param array $data_extra     : extra data to use in validate()
     * @return array
     */
    public function getErrors($data_extra = null)
    {
        if (!$this->_validator) return [];
        
        if (!method_exists($this, 'validate')) $errors = [];
        else $errors = $this->validate($data_extra);
        
        foreach ($this->_validator as $prop => $defs) {
            $getterMethod = 'get'.str_replace(' ','',ucwords(str_replace('_', ' ', $prop)));
            if (method_exists($this, $getterMethod)) $value = $this->$getterMethod();
            else $value = $this[$prop];

            // property's definitions
            foreach ($defs as $def) {
                if (($def == 'not_null') && (is_null($value))) $errors[$prop] = 'is_null';
                if (is_null($value)) continue;
                
                switch ($def) {
                    case 'numeric'  : if (!is_numeric($value))   $errors[$prop] = 'not_numeric'; break;
                    case 'hex'      : if (!ctype_xdigit($value)) $errors[$prop] = 'not_hex'; break;
                    case 'date'     : if (!strtotime($value))    $errors[$prop] = 'not_date'; break;
                }
                
                // min_##
                $range = explode('_',$def);
                if (($range[0] == 'min')) {
                    if (in_array('numeric', $defs)) { if ($value < intval($range[1])) $errors[$prop] = 'not_min'; }
                    elseif (strlen($value) < intval($range[1])) $errors[$prop] = 'not_min'; 
                }
                // max_##
                elseif (($range[0] == 'max')) {
                    if (in_array('numeric', $defs)) { if ($value > intval($range[1])) $errors[$prop] = 'not_max'; }
                    elseif (strlen($value) > intval($range[1])) $errors[$prop] = 'not_max'; 
                }
            }
        }
        
        return $errors;
    }
}