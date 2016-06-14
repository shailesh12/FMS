<?php

namespace Purchase\Form;

use Zend\Form\Form;

class PurchaseForm extends Form {

    public function __construct($name = null, $buyer) {
        // we want to ignore the name passed
        parent::__construct($name);
        $this->setAttribute('method', 'post');

        $this->add(array(
            'name' => 'id',
            'attributes' => array(
                'type' => 'hidden',
            ),
        ));

      
        $this->add(array(
            'name' => 'item_name',
            'attributes' => array(
                'type' => 'text',
                'id' => 'item_name',
                'class' => 'input',
            ),
            'options' => array(
                'label' => 'ITEM NAME',
                'label_attributes' => array(
                    'class' => 'label'
                ),
            ),
        ));

        $this->add(array(
            'type' => 'Zend\Form\Element\Textarea',
            'attributes' => array(
                'id' => 'comment',
                'class' => 'input',
            ),
            'name' => 'comment',
            'options' => array(
                //'label' => 'DESCRIPTION',
                //'label_attributes' => array(
                //    'class' => 'label'
                //),
            ),
        ));
        
        $this->add(array(
            'name' => 'price',
            'attributes' => array(
                'type' => 'text',
                'class' => 'input',
            ),
            'options' => array(
                'label' => 'PRICE',
                'label_attributes' => array(
                    'class' => 'label'
                ),
            ),
        ));

    $this->add(array(
            'name' => 'purchased_by',
            'type' => 'Zend\Form\Element\Select',
            'attributes' => array(
                'id' => 'purchaser',
            ),
            'options' => array(
                //'label' => 'PURCHASER',
                //'label_attributes' => array(
                //    'class' => 'label'
                //),
                'empty_option' => 'Select',
                'value_options' => $buyer,
            )
        ));
    
    $this->add(array(
            'name' => 'purhcase_date',
            'attributes' => array(
                'type' => 'text',
                'readonly' => 'true',
                'id' => 'dop',
                'class' => '',
            ),
            'options' => array(
               // 'label' => 'Date of Purchase',
                //'label_attributes' => array(
                //    'class' => 'label'
               // ),
            ),
        ));

        $this->add(array(
            'name' => 'submit',
            'attributes' => array(
                'type' => 'submit',
                'value' => 'ADD',
                'id' => 'submitbutton',
                'class' => 'green-btn big-btn margin-Btm40',
            ),
        ));
    }

}

?>