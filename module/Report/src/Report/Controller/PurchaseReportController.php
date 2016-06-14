<?php

namespace Report\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;
use Zend\Db\Sql\Select;
use Report\Model\ReportTable;

class PurchaseReportController extends AbstractActionController {

    protected $adapter;
    protected $reportsTable;

    public function __construct() {
        
    }

    public function getAdapter() {
        if (!$this->adapter) {
            $sm = $this->getServiceLocator();
            $this->adapter = $sm->get('Zend\Db\Adapter\Adapter');
        }
        return $this->adapter;
    }

    public function getReportsTable() {
        if (!$this->reportsTable) {
            $sm = $this->getServiceLocator();
            $this->reportsTable = $sm->get('Report\Model\ReportTable');
        }
        return $this->reportsTable;
    }

    /**
     * 
     * @return \Zend\View\Model\ViewModel
     */
    public function indexAction() {
        $totalPurchase = $this->getReportsTable()->purchaseData();
        $purchaseData = $this->getReportsTable()->purchasePerUser();
        $data = array(
            'totalPurchase' => $totalPurchase,
            'purchaseData' => $purchaseData
        );
//        echo "<pre />";
//        print_r($totalPurchase);
//        print_r($purchaseData);
//        die;
//        die('working');
        return new ViewModel(array(
            'data' => $data
        ));
    }

}

?>
    