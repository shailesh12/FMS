<?php
namespace ZF2AuthAcl\Model;

// Add these import statements
use Zend\InputFilter\Factory as InputFactory;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterInterface;

class RecoverEmail implements InputFilterAwareInterface
{
    public $id;
    public $email;
    public $hash_value;
    public $requested_on;
    protected $inputFilter;                       // <-- Add this variable

    public function exchangeArray($data)
    {
        $this->id     = (isset($data['id']))     ? $data['id']     : null;
        $this->email  = (isset($data['email']))  ? $data['email']  : null;
        $this->hash_value  = (isset($data['hash_value']))  ? $data['hash_value']  : null;
        $this->requested_on  = (isset($data['requested_on']))  ? $data['requested_on']  : null;
    }
    
     // Add content to these methods:
    public function setInputFilter(InputFilterInterface $inputFilter)
    {
        throw new \Exception("Not used");
    }
    
    public function getInputFilter()
    {
        if (!$this->inputFilter) {
            $inputFilter = new InputFilter();
            $factory     = new InputFactory();

            $inputFilter->add($factory->createInput(array(
                'name'     => 'password',
                'required' => true,
                'filters'  => array(
                    array('name' => 'StripTags'),
                    array('name' => 'StringTrim'),
                ),
                'validators' => array(
                    array(
                      'name' =>'NotEmpty', 
                        'options' => array(
                            'messages' => array(
                                \Zend\Validator\NotEmpty::IS_EMPTY => 'Please enter Password!' 
                            ),
                        ),
                    ),
//                    array(
//                    'name' => 'regex',
//                    'options' => array(
//                        // 'pattern' => '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])[0-9a-zA-Z]{8,}$/',
//                        'pattern' => '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9])(?!.*\s).{8,}$/',
//                        'messages' => array(
//                            \Zend\Validator\Regex::NOT_MATCH => 'Password should contain at least one digit,
//                                                                 at least one lower case,
//                                                                 at least one upper case,
//                                                                 and at least one special character.',
//                        ),
//                    ),
//                ),
                    
                ),
            )));
            
            $inputFilter->add($factory->createInput(array(
                'name'     => 'oldpassword',
                'required' => true,
                'filters'  => array(
                    array('name' => 'StripTags'),
                    array('name' => 'StringTrim'),
                ),
                'validators' => array(
                    array(
                      'name' =>'NotEmpty', 
                        'options' => array(
                            'messages' => array(
                                \Zend\Validator\NotEmpty::IS_EMPTY => 'Please enter Old Password!' 
                            ),
                        ),
                    ),
//                    array(
//                    'name' => 'regex',
//                    'options' => array(
//                        // 'pattern' => '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])[0-9a-zA-Z]{8,}$/',
//                        'pattern' => '/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9])(?!.*\s).{8,}$/',
//                        'messages' => array(
//                            \Zend\Validator\Regex::NOT_MATCH => 'Password should contain at least one digit,
//                                                                 at least one lower case,
//                                                                 at least one upper case,
//                                                                 and at least one special character.',
//                        ),
//                    ),
//                ),
                    
                ),
            )));

            $inputFilter->add($factory->createInput(array(
                'name'     => 'repassword',
                'required' => true,
                'filters'  => array(
                    array('name' => 'StripTags'),
                    array('name' => 'StringTrim'),
                ),
                'validators' => array(
                    array(
                      'name' =>'NotEmpty', 
                        'options' => array(
                            'messages' => array(
                                \Zend\Validator\NotEmpty::IS_EMPTY => 'Please enter retypepassword!' 
                            ),
                        ),
                    ),
                    
                    array(
                        'name' => 'Identical',                        
                        'options' => array(
                            'token' => 'password',
                            'messages' => array(
                                 \Zend\Validator\Identical::NOT_SAME => 'Password and Retype Password does not match!'                             ),
                        ),
                    ),
                    
                    array(
                        'name' => 'StringLength',
                        'options' => array(
                            'encoding' => 'UTF-8',
                            'min' => 8,  
                            'max' => 20, 
                            'messages' => array(
                                'stringLengthTooShort' => 'Password should be atleast 8 characters and maximum of 20 characters!',
                            ),
                        ),
                    ),
                    
                ),
            )));
            
            $this->inputFilter = $inputFilter;
        }

        return $this->inputFilter;
    }
    
     // Add the following method:
    public function getArrayCopy()
    {
        return get_object_vars($this);
    }
    
    
}
