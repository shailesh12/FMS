<?php
namespace ZF2AuthAcl\Form;
use Zend\Form\Form;

class RecoverPasswordForm extends Form
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('signin');
        $this->setAttribute('method', 'post');
        
        $this->add(array(
            'name' => 'email',
            'attributes' => array(
                    'class' => 'text-box input',
                    'type'  => 'text',
                    'id'    => 'email',
					'placeholder' => 'Email',
					
            )
        ));

	$this->add(array(
	    'name' => 'password',
	    'attributes' => array(
		    'class' => 'text-box input',
		    'type'  => 'password',
		    'id'    => 'password',                                
		    'placeholder' => 'Password',
	    ),
            'options' => array(
                'label' => 'New Password',
				'label_attributes' => array(
					'class' => 'label'
				)
            ),
	));


	$this->add(array(
	    'name' => 'repassword',
	    'attributes' => array(
		    'class' => 'text-box input',
		    'type'  => 'password',
		    'id'    => 'repassword',                                
		    'placeholder' => 'Retype Password',
	    ),
            'options' => array(
                'label' => 'Confirm Password',
				'label_attributes' => array(
					'class' => 'label'
				)
            ),
            'validators' => array(
                array(
                    'name'    => 'Identical',
                    'options' => array(
                        'token' => 'password',
//                        'messages' => array(
//                                Zend/Validate/Identical::IS_EMPTY => 'Please enter User Name!' 
//                            ),
                    ),
                ),
            ),
	));

        
        
        $this->add(array(
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => array(
                'value' => 'Reset Password',
                'class' => 'big-btn green-btn',
                'id' => 'submitbutton',
            ),
        ));
    }
}
