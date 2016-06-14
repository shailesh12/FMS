<?php
namespace ZF2AuthAcl\Form;

use Zend\Form\Element;
use Zend\Form\Form;
use Zend\Form\Element\Csrf;

class LoginForm extends Form
{

    public function __construct($name)
    {
        parent::__construct($name);
        $this->setAttribute('method', 'post');
        //->setAttribute('onsubmit', 'return checkExistingSession();');
        
        $this->add(array(
            'name' => 'email',
            'type' => 'text',
            'attributes' => array(
                'id' => 'email',
                'class' => 'input-sm input',
                'placeholder' => 'example@example.com'
            ),
            'options' => array(
                'label' => 'Email',
				'label_attributes' => array(
					'class' => 'label'
				)
            )
        ));
        
        $this->add(array(
            'name' => 'password',
            'type' => 'password',
            'attributes' => array(
                'id' => 'password',
                'class' => 'input-sm input',
                'placeholder' => '**********'
            ),
            'options' => array(
                'label' => 'Password',
				'label_attributes' => array(
					'class' => 'label'
				)                
            )
        ));
        
        $this->add(array(
            'type' => 'Zend\Form\Element\Csrf',
            'name' => 'loginCsrf',
            'options' => array(
                'csrf_options' => array(
                    'timeout' => 3600
                )
            )
        ));
        
        $this->add(array(
            'name' => 'submit',
            'attributes' => array(                
                'id' => 'submit',
                'type' => 'submit',
                'value' => 'Submit',
                'class' => 'big-btn green-btn'
            )
        ));
    }
}