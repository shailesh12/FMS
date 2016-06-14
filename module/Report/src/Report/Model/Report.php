<?php

namespace Report\Model;

use Zend\InputFilter\Factory as InputFactory;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterInterface;

class Report implements InputFilterAwareInterface {

    protected $inputFilter;

    public function exchangeArray($data) {
        
    }

    // Add the following method:
    public function getArrayCopy() {
        return get_object_vars($this);
    }

    // Add content to this method:
    public function setInputFilter(InputFilterInterface $inputFilter) {
        throw new \Exception("Not used");
    }

    public function getInputFilter() {
        if (!$this->inputFilter) {

            $inputFilter = new InputFilter();
            $factory = new InputFactory();
            $this->inputFilter = $inputFilter;
            return $this->inputFilter;
        }
    }

}
