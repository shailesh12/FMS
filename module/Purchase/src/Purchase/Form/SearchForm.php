<?php

namespace Purchase\Form;

use Zend\Form\Form;

class SearchForm extends Form {

    public function __construct($name = null) {
        // we want to ignore the name passed
        parent::__construct($name);
        $this->setAttribute('method', 'get');

        $this->add(array(
            'name' => 'search_box',
            'attributes' => array(
                'type' => 'text',
                'id' => 'search_box',
                'placeholder' => 'Search by typing a Code, Name or Description',
				'class' => 'search input large-search-box'
            ),
            'options' => array(
                /*'label' => '',*/
            ),
        ));
        
        $this->add(array(
            'name' => 'search_box_value',
            'attributes' => array(
                'type' => 'hidden',
                'id' => 'search_box_value',                
            )
        ));
        
        $this->add(array(
            'name' => 'paging_count',
            'attributes' => array(
                'type' => 'hidden',
                'id' => 'paging_count',                
            )
        ));

        $this->add(array(
            'name' => 'searchbutton',
            'attributes' => array(
                'type' => 'button',
                'value' => '',
                'id' => 'searchbutton',
				'class' => 'search-button'
            ),
        ));
        
         $this->add(array(
            'name' => 'list_count',
            'attributes' => array(
                'type' => 'hidden',
                'id' => 'list_count',
            ),
        ));

        $this->add(array(
            'name' => 'order_by',
            'attributes' => array(
                'type' => 'hidden',
                'id' => 'order_by',
            ),
        ));

        $this->add(array(
            'name' => 'order',
            'attributes' => array(
                'type' => 'hidden',
                'id' => 'order',
            ),
        ));
    }

}

?>
