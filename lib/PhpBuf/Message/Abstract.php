<?php
/**
 * @author Andrey Lepeshkin (lilipoper@gmail.com)
 * @link http://github.com/undr/phpbuf
 *
 */
abstract class PhpBuf_Message_Abstract {
    /**
     *  Fields array of message
     *
     * @var array
     */
    protected $fields = array();
    /**
     *  Index to name transformation table
     *
     * @var array
     */
    protected $indexToName = array();
    /**
     * Name to index transformation table
     *
     * @var array
     */
    protected $nameToIndex = array();
    
    /**
     * Dynamic getter
     *
     * @param string $field
     * @return mixed
     */
    public function __get($field) {
        return $this->getValue($field);
    }
    /**
     * Dynamic setter
     *
     * @param string $field
     * @param mixed $value
     */
    public function __set($field, $value) {
        $this->setValue($field, $value);
    }
    
    /**
     * Read message from reader
     *
     * @param IO_Reader_Interface $reader
     */
    public function read(PhpBuf_IO_Reader_Interface $reader) {
        try {
            while(true){
                $this->readOnce($reader);
            }
        } catch(PhpBuf_IO_Exception $e){
            return ;
        }
    }
    
    public function readOnce(PhpBuf_IO_Reader_Interface $reader) {
        //
        // Исправить надо, как пока не знаю
        //
        $fieldClass = $this->readFieldFromHeader($reader);
        if($fieldClass == null){
            return;
        }
        $fieldClass->read($reader);
    }

    /**
     * Write message to writer
     *
     * @param IO_Writer_Interface $writer
     */
    public function write(PhpBuf_IO_Writer_Interface $writer) {
        foreach ($this->fields as $field) {
            $field->write($writer);
        }
    }
    /**
     * Enter description here...
     *
     * @param string $name
     * @param integer $type
     * @param integer $rule
     * @param integer $index
     * @param mixed $extra
     */
    protected function setField($name, $type, $rule, $index, $extra = '') {
        if($type == PhpBuf_Type::MESSAGE && (!is_string($extra) || empty($extra))) {
            throw new PhpBuf_Message_Exception('message mast have $extra');
        }
        if($type == PhpBuf_Type::ENUM && (!is_array($extra) || empty($extra))) {
            throw new PhpBuf_Message_Exception('enum mast have $extra');
        }
        $fieldClass = PhpBuf_Field_Abstract::create($type, array('index' => $index, 'rule' => $rule, 'extra' => $extra));
        
        $this->fields[$index] = $fieldClass;
        $this->nameToIndex[$name] = $index;
        $this->indexToName[$index] = $name;
    }
    /**
     * Helper function for dynamic getter
     *
     * @param string $field
     * @param boolean $throwException
     * @return mixed
     */
    protected function getValue($field, $throwException = true) {
        if(isset($this->nameToIndex[$field])) {
            $fieldClass = $this->fields[$this->nameToIndex[$field]];
            return $fieldClass->getValue();
        }
        if($throwException) {
            throw new PhpBuf_Message_Exception("property $field not found");
        }
    }
    /**
     * Helper function for dynamic setter
     *
     * @param string $field
     * @param mixed $value
     * @param boolean $throwException
     */
    protected function setValue($field, $value, $throwException = true) {
        if(isset($this->nameToIndex[$field])) {
            $fieldClass = $this->fields[$this->nameToIndex[$field]];
            $fieldClass->setValue($value);
            return;
        }
        if($throwException) {
            throw new PhpBuf_Message_Exception("property $field not found");
        }
    }
    /**
     * Read field info from reader and return associated field class
     *
     * @param IO_Reader_Interface $reader
     * @return Message_Abstract
     */
    protected function readFieldFromHeader(PhpBuf_IO_Reader_Interface $reader) {
        $varint = PhpBuf_Base128::decodeFromReader($reader);
        $bigMask = bindec('111');
        $wireType = $varint & $bigMask;
        $fieldIndex = $varint >> 3;
        
        if(!isset($this->fields[$fieldIndex])) {
            throw new PhpBuf_Field_Exception("class " . get_class($this) . " field index $fieldIndex not found");
        }
        $fieldClass = $this->fields[$fieldIndex];
        $fieldsWireType = $fieldClass->getWireType();
        if($wireType !== $fieldsWireType) {
            throw new PhpBuf_Field_Exception("discrepancy of wire types");
        }
        return $fieldClass;
    }
    
    public abstract static function name();
}
