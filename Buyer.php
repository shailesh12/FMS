<?php

namespace Purchase\Model;

class Purchase {

    public $id;
    public $item_name;
    public $purhcase_date;
    public $price;
    public $created_date;
    public $purchaser;
    public $updated_date;
    public $comment;
    protected $inputFilter;

    public function exchangeArray($data) {
        $this->id = (isset($data['id'])) ? $data['id'] : null;
        $this->item_name = (isset($data['item_name'])) ? $data['item_name'] : null;
        $this->purhcase_date = (isset($data['purhcase_date'])) ? $data['purhcase_date'] : null;
        $this->price = (isset($data['price'])) ? $data['price'] : null;
        $this->purchaser = (isset($data['purchaser'])) ? $data['purchaser'] : null;
        $this->created_date = (isset($data['created_date'])) ? $data['created_date'] : null;
        $this->updated_date = (isset($data['updated_date'])) ? $data['updated_date'] : null;
        $this->comment = (isset($data['comment'])) ? $data['comment'] : null;
    }

    // Add the following method:
    public function getArrayCopy() {
        return get_object_vars($this);
    }

//// Add content to this method:
//    public function setInputFilter(InputFilterInterface $inputFilter) {
//        throw new \Exception("Not used");
//    }
//
//    public function getInputFilter() {
//        if (!$this->inputFilter) {
//            $isEmpty = \Zend\Validator\NotEmpty::IS_EMPTY;
//
//            $inputFilter = new InputFilter();
//            $factory = new InputFactory();
//
//            $inputFilter->add($factory->createInput(array(
//                        'name' => 'id',
//                        'required' => true,
//                        'filters' => array(
//                            array('name' => 'Int'),
//                        ),
//            )));
//
//            $inputFilter->add($factory->createInput(array(
//                        'name' => 'sector_name',
//                        'required' => true,
//                        'filters' => array(
//                            array('name' => 'StripTags'),
//                            array('name' => 'StringTrim'),
//                        ),
//                        'validators' => array(
//                            array(
//                                'name' => 'NotEmpty',
//                                'options' => array(
//                                    'messages' => array(
//                                        $isEmpty => 'Sector Name can not be empty.'
//                                    )
//                                )
//                            )
//                        )
//                            )
//            ));
//            $this->inputFilter = $inputFilter;
//        }
//        return $this->inputFilter;
//    }
}
