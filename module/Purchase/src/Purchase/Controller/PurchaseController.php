<?php

namespace Purchase\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Purchase\Model\Purchase;
use Zend\Session\Container;
use Purchase\Form\SearchForm;
use Purchase\Form\PurchaseForm;
use Zend\Db\Sql\Select;

class PurchaseController extends AbstractActionController {

    protected $purchaseTable;
    protected $adapter;

    public function __construct() {
//        $session = new Container('User');
//        $role_code = $session->offsetGet('roleCode');
//        if ($role_code == '') {
//            return $this->redirect()->toRoute('login');
//        }
    }

    public function getAdapter() {
        if (!$this->adapter) {
            $sm = $this->getServiceLocator();
            $this->adapter = $sm->get('Zend\Db\Adapter\Adapter');
        }
        return $this->adapter;
    }

    public function getPurchaseTable() {
        if (!$this->purchaseTable) {
            $sm = $this->getServiceLocator();
            $this->purchaseTable = $sm->get('Purchase\Model\PurchaseTable');
        }
        return $this->purchaseTable;
    }

//    public function getQualificationSectorTable() {
//        if (!$this->qualificationSectorTable) {
//            $sm = $this->getServiceLocator();
//            $this->qualificationSectorTable = $sm->get('Qualification\Model\QualificationSectorTable');
//        }
//        return $this->qualificationSectorTable;
//    }

    /**
     * Action for Manage Sector listing page
     * @return \Zend\View\Model\ViewModel
     */
    public function indexAction() {
        $purchasedata = $this->getPurchaseTable()->fetchAll();

        return new ViewModel(array(
            'data' => $purchasedata,
        ));
    }

    /**
     * Action for adding new Sector
     * @return type
     */
    public function addAction() {
        //die('working');
        $buyer = $this->getPurchaseTable()->fetchBuyer();
        $form = new PurchaseForm('item', $buyer);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $purchase = new Purchase();
            //$form->setInputFilter($trainee->getInputFilter());
            $form->setData($request->getPost());
            $data = $request->getPost();
//            echo "<pre />";
//            print_r($data);
//            die;
            //echo '<pre>'; print_r($data->name); die;
            if ($form->isValid()) {

                $purchase->exchangeArray($form->getData());

                $purchase->item_name = $data['item_name'];
                $purchase->price = $data['price'];
                $purchase->purhcase_date = $data['purhcase_date'];
                $purchase->comment = $data['comment'];
                $purchase->purchased_by = $data['purchased_by'];
                $purchase->created_date = date('Y-m-d H:i:s');
                $purchase->updated_date = date('Y-m-d H:i:s');
                $purchaseId = $this->getPurchaseTable()->saveItem($purchase);
                // Redirect to list of sectors
                $this->flashMessenger()->setNamespace('success')->addMessage('Item added successfully');
                return $this->redirect()->toRoute('purchase');
            }
        }
        return array('form' => $form);
    }

    /**
     * function to edit Sector
     * @return type
     */
    public function editAction() {
        $id = (int) $this->params()->fromRoute('id', 0);
        if (!$id) {
            return $this->redirect()->toRoute('purchase', array(
                        'action' => 'add'
            ));
        }

        $buyer = $this->getPurchaseTable()->fetchBuyer();
        $form = new PurchaseForm('item', $buyer);
        $item = $this->getPurchaseTable()->getItem($id);
//        echo "<pre />";
//        print_r($item);
//        die;
        $form->get('purchased_by')->setValue($item->purchased_by);
        $form->bind($item);
        $form->get('submit')->setAttribute('value', 'Update');
        $request = $this->getRequest();


        //if($qualificationSectorMapCount != 1){
        if ($request->isPost()) {
            //$form->setInputFilter($album->getInputFilter());
            $form->setData($request->getPost());

            if ($form->isValid()) {
                $purchase = new Purchase();
                $data = $form->getData();
                $purchase->id = $id;
                $purchase->item_name = $data->item_name;
                $purchase->price = $data->price;
                $purchase->purhcase_date = $data->purhcase_date;
                $purchase->comment = $data->comment;
                $purchase->purchased_by = $data->purchased_by;
                $purchase->created_date = date('Y-m-d H:i:s');
                $purchase->updated_date = date('Y-m-d H:i:s');

                $purchaseId = $this->getPurchaseTable()->saveItem($purchase);
                $this->flashMessenger()->setNamespace('success')->addMessage('Item updated successfully');
                return $this->redirect()->toRoute('purchase');
            }
        }
//        }else{
//            //$this->flashMessenger()->setNamespace('error')->addMessage('Sector name ' . $sector->name . 'can\'t updated. it is already assosisted with the qualifications.');
//        }
        //echo '<pre>';print_r($mapQualifications); die;

        return array(
            'id' => $id,
            'form' => $form,
        );
    }

    /**
     * function to delete Sector
     * @return type
     */
    public function deleteAction() {
        $session = new Container('User');
        $viewModel = new ViewModel(array(
        ));
        $request = $this->getRequest();
        $data = $request->getPost();
        $viewModel->setTerminal(true);
        $this->layout('layout/empty');
        $response = $this->getResponse();
        $sectorData = $this->getSectorTable()->getAssociatedQual($data->sectorId);
//        echo '<pre>';
//        print_r($data); die;
        if (!isset($sectorData[0]['id'])) {
            $this->getSectorTable()->deleteSector($data->sectorId);
            $status = 'SUCCESS';
        } else {
            $status = 'FAIL';
        }
        $this->flashMessenger()->setNamespace('success')->addMessage('Sector with id ' . $data->sectorId . ' deleted successfully');
        $this->getServiceLocator()->get('Zend\Log')->info('Sector with Id ' . $data->sectorId . ' deleted by user ' . $session->offsetGet('userId'));
        $response->setContent($status);
        return $response;
    }

}

?>