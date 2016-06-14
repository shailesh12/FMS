<?php

namespace Report\Model;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Paginator\Adapter\DbSelect;
use Zend\Paginator\Paginator;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Expression;
use Zend\Session\Container;
use \TCPDF;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\ResultSet\HydratingResultSet;
use Zend\Stdlib\Hydrator;

class ReportTable implements ServiceLocatorAwareInterface {

    protected $tableGateway;
    protected $services;

//CONST uniqueCodeStartsWith = 10000001;

    public function __construct(TableGateway $tableGateway) {
        $this->tableGateway = $tableGateway;
        $this->resultSetPrototype = new ResultSet(ResultSet::TYPE_ARRAY);
    }

    public function setServiceLocator(ServiceLocatorInterface $locator) {
        $this->services = $locator;
    }

    public function getServiceLocator() {
        return $this->services;
    }

    public function test() {
// CALL DIFFERENT MODEL
        $objCertificateTrainee = $this->getServiceLocator()->get('Certificate\Model\CertificateTraineeTable');
        asd($objCertificateTrainee);

// YOUR QUERIES
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
    }

    public function purchaseData() {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from('item')->columns(array('SUM' => new \Zend\Db\Sql\Expression('SUM(price)')));
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset;
    }

    public function purchasePerUser() {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from('item')->columns(array('Amount' => new \Zend\Db\Sql\Expression('SUM(price)')))
                ->join(array('p' => 'purchaser'), 'p.id=item.purchased_by', array('name'), 'inner')
                ->group('purchased_by');
//        echo $sql->getSqlStringForSqlObject($select);
//        die;
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset;
    }

    /**
     * Function for fetching Total qualification count of given @type from database
     * @param type $type
     * @return type
     */
    public function getTotalQualification($type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');

        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from('qualification')->columns(array('COUNT' => new \Zend\Db\Sql\Expression('COUNT(*)')));
        if ($role_code == 'ca') {
            $select->join(array('cqa' => 'center_qualification_affiliation'), 'cqa.qualification_id = qualification.id', array(), 'inner');
            $select->where(array('cqa.center_id' => $center_id));
        }
        $select->where(array('qualification.type' => $type));
        $select->where->notEqualTo('qualification.status', 'draft');
//$sql->getSqlStringForSqlObject($select);
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset;
    }

    /**
     * Function for Fetching all qualification Data for showing in table at bottom of Qaulification Report page using Stored Procedure
     * @param type $qual_type
     * @return type
     */
    public function getQualificationAllData($qual_type, $role_code, $center_id) {
        $adapter = $this->tableGateway->getAdapter();
        if ($role_code == 'ca') {
            $result = $adapter->query(
                    "EXEC QualificationDataTable @qual_type = :qual_type, @center_id = :center_id", array(':qual_type' => $qual_type, ':center_id' => $center_id)
            );
        } else {
            $result = $adapter->query(
                    "EXEC QualificationDataTable @qual_type = :qual_type", array(':qual_type' => $qual_type)
            );
        }

        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    /**
     * Function for fetching Qualification Data for a given Type form database
     * @param type $type : Passible values : active/inactive
     * @param type $status
     * @return type
     */
    public function getQualificationByType($type, $status) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');

        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();

        $select->from('qualification');
        if ($status == 'excel') {
            $select->columns(array('code', 'name', 'status'));
        } else {
            $select->columns(array('COUNT' => new \Zend\Db\Sql\Expression('COUNT(*)')));
        }

        $select->where(array('qualification.type' => $type));
        if ($role_code == 'ca') {
            $select->join(array('cqa' => 'center_qualification_affiliation'), 'cqa.qualification_id = qualification.id', array(), 'inner');
            $select->where(array('cqa.center_id' => $center_id));
        }
        if ($status == 'active') {
            $select->where->in('qualification.status', array('publish', 'phasing_out'));
        } else if ($status == 'inactive') {
            $select->where->in('qualification.status', array('expired', 'inactive'));
        } else {
            $select->where->notIn('qualification.status', array('draft'));
        }
        $statement = $sql->prepareStatementForSqlObject($select);
//        echo $sql->getSqlStringForSqlObject($select);
//        die;
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset;
    }

    public function QualificationRenewalTrend($type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');

        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('cqr' => 'center_qualification_renew'));
        $select->columns(array());
        $select->join(array('cqa' => 'center_qualification_affiliation'), 'cqa.id = cqr.affiliation_id', array(), 'inner')
                ->join(array('q' => 'qualification'), 'q.id = cqa.qualification_id', array('id', 'code', 'name', 'type', 'status', 'center_count' => new \Zend\Db\Sql\Expression('COUNT(*)')), 'inner')
                ->group(array('q.id', 'q.code', 'q.name', 'q.type', 'q.status'))
        ->where->in('cqr.status', array('approved', 'conditionally approved'));
        $select->where(array('q.type' => $type));
        if ($role_code == 'ca') {
            $select->where(array('cqa.center_id' => $center_id));
        }
        //echo $sql->getSqlStringForSqlObject($select); die;
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset;
    }

    public function QualificationRenewalTrendExcel($type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');

        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('cqr' => 'center_qualification_renew'));
        $select->columns(array());
        $select->join(array('cqa' => 'center_qualification_affiliation'), 'cqa.id = cqr.affiliation_id', array(), 'inner')
                ->join(array('c' => 'center'), 'c.id = cqa.center_id', array('center_name' => 'name', 'center_code' => 'center_id'), 'inner')
                ->join(array('q' => 'qualification'), 'q.id = cqa.qualification_id', array('id', 'code', 'name', 'type', 'status', 'center_count' => new \Zend\Db\Sql\Expression('COUNT(*)')), 'inner')
                ->group(array('q.id', 'q.code', 'q.name', 'q.type', 'q.status', 'c.name', 'c.center_id'))
        ->where->in('cqr.status', array('approved', 'conditionally approved'));
        $select->where(array('q.type' => $type));
        if ($role_code == 'ca') {
            $select->where(array('cqa.center_id' => $center_id));
        }
        //echo $sql->getSqlStringForSqlObject($select); die;
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset;
    }

    public function QualificationAssociationTrend($qual_type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');

        $adapter = $this->tableGateway->getAdapter();
        if ($role_code == 'ca') {
            $result = $adapter->query(
                    "EXEC QualificationAssociation @qual_type = :qual_type, @center_id = :center_id", array(':qual_type' => $qual_type, ':center_id' => $center_id)
            );
        } else {
            $result = $adapter->query(
                    "EXEC QualificationAssociation @qual_type = :qual_type", array(':qual_type' => $qual_type)
            );
        }

        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function QualificationAssociationTrendExcel($qual_type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');

        $adapter = $this->tableGateway->getAdapter();
        if ($role_code == 'ca') {
            $result = $adapter->query(
                    "EXEC QualificationAssociationExcel @qual_type = :qual_type, @center_id = :center_id", array(':qual_type' => $qual_type, ':center_id' => $center_id)
            );
        } else {
            $result = $adapter->query(
                    "EXEC QualificationAssociationExcel @qual_type = :qual_type", array(':qual_type' => $qual_type)
            );
        }
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function QualificationEnrollmentTrend($qual_type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');
        $adapter = $this->tableGateway->getAdapter();
        if ($role_code == 'ca') {
            $result = $adapter->query(
                    "EXEC QualificationEnrollment @qualf_type = :qualf_type, @center_id = :center_id", array(':qualf_type' => $qual_type, ':center_id' => $center_id)
            );
        } else {
            $result = $adapter->query(
                    "EXEC QualificationEnrollment @qualf_type = :qualf_type", array(':qualf_type' => $qual_type)
            );
        }

        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function QualificationEnrollmentTrendExcel($qual_type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');
        $adapter = $this->tableGateway->getAdapter();
        if ($role_code == 'ca') {
            $result = $adapter->query(
                    "EXEC QualificationEnrollmentExcel @qual_type = :qual_type, @center_id = :center_id", array(':qual_type' => $qual_type, ':center_id' => $center_id)
            );
        } else {
            $result = $adapter->query(
                    "EXEC QualificationEnrollmentExcel @qual_type = :qual_type", array(':qual_type' => $qual_type)
            );
        }
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function QualificationPerformanceTrend($tool, $intake_id, $qual_type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');
        $adapter = $this->tableGateway->getAdapter();
        if ($role_code == 'ca') {
            $result = $adapter->query(
                    "EXEC QualificationPerformanceTrend @tool = :tool, @intake_id = :intake_id, @qualf_type = :qualf_type, @center_id = :center_id", array(':tool' => $tool, ':intake_id' => $intake_id, ':qualf_type' => $qual_type, ':center_id' => $center_id)
            );
        } else {
            $result = $adapter->query(
                    "EXEC QualificationPerformanceTrend @tool = :tool, @intake_id = :intake_id, @qualf_type = :qualf_type", array(':tool' => $tool, ':intake_id' => $intake_id, ':qualf_type' => $qual_type)
            );
        }
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function QualificationPerformanceTrendExcel($qual_type, $intake_id) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');
        $adapter = $this->tableGateway->getAdapter();
        if ($role_code == 'ca') {
            $result = $adapter->query(
                    "EXEC QualificationPerformanceTrendExcel @intake_id = :intake_id, @qualf_type = :qualf_type, @center_id = :center_id", array(':intake_id' => $intake_id, ':qualf_type' => $qual_type, ':center_id' => $center_id)
            );
        } else {
            $result = $adapter->query(
                    "EXEC QualificationPerformanceTrendExcel @intake_id = :intake_id, @qualf_type = :qualf_type", array(':intake_id' => $intake_id, ':qualf_type' => $qual_type)
            );
        }
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function enrolledtrainees($qual_type) {

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC TotalTraineeEnrolled @qual_type = :qual_type", array(':qual_type' => $qual_type)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function enrolledtraineesForCenter($qual_type, $center_id) {

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC TotalTraineeEnrolledForCenter @qual_type = :qual_type,@center_id = :center_id", array(':qual_type' => $qual_type, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    // function to get the count of total enrolled trainees in to the system
    public function totalFoundationTrainees() {
        $adapter = $this->tableGateway->getAdapter();
//        $result = $adapter->query(
//            "EXEC spGetAlbums @artist = :artist",
//            array(':artist' => $artist)
//        );
        $result = $adapter->query(
                "EXEC TotalFoundationTrainee", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

// function to get the count of total enrolled trainees in to the system for a center
    public function totalFoundationTraineesForCenter($center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC TotalFoundationTraineeCenter @center_id = :center_id", array(':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    // function to get the count of total enrolled trainees in to the system
    public function listTotalFoundationTrainees() {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListTotalFoundationTrainee", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listTotalFoundationTraineesForCenter($center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListTotalFoundationTraineeForCenter @center_id = :center_id", array(':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    /**
     * function to get total center including active & inactive centers counts
     */
    public function totalCenter() {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC TotalCenter", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listTotalCenters() {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListTotalCenters", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listCenterRegion() {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListCenterRegion", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    /**
     * fucntion to get region wise center
     * @return type
     */
    public function CenterRegionTrend() {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC CenterReigonReport", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function getQualificationDropdown() {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('q' => 'qualification'));
        $select->columns(array('id', 'name'))
        ->where->NEST->in('q.status', array('publish', 'phasing_out', 'expired'))
        ->AND->equalTo('q.type', 'standard');
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();

        return $resultset;
    }

    /**
     * Function to get the intake list as dropdown
     * @param type $qualificationId
     * @return type
     */
    public function fetchIntakeDropdown($qualificationId) {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select()->from(array('i' => 'intakes'), array('id', 'intake_name', 'code'))
                ->join(array('qi' => 'qualification_intakes'), 'i.id = qi.intake_id', array(), 'left')
                ->where(array('qi.qualification_id' => $qualificationId, 'i.status' => 'publish'));
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        if (!empty($resultset)) {
            foreach ($resultset as $intake) {
                $result[$intake['id']] = $intake['intake_name'];
            }
            return $result;
        } else {
            $result = array();
        }
        return $result;
    }

    /**
     * function to get data for center qualification performance for standard qualification
     * @param type $qual_id
     * @param type $intake_id
     */
    public function CenterQualificationPerformanceTrend($qual_id, $intake_id, $result_component) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC CenterQualificationPerformanceReports @qual_id = :qual_id,@intake_id=:intake_id,@rc_type=:rc_type", array(':qual_id' => $qual_id, ':intake_id' => $intake_id, ':rc_type' => $result_component)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    /**
     * function to get data for center qualification performance for custom qualification
     * @param type $qual_id
     * @param type $intake_id
     */
    public function CenterQualificationPerformanceTrendForCustom($qual_id, $intake_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC CenterCustomQualificationPerformanceReports @qual_id = :qual_id,@intake_id=:intake_id", array(':qual_id' => $qual_id, ':intake_id' => $intake_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    /**
     * 
     * @param type $qual_id
     * @param type $intake_idfunction to download excel for centerwise performance in terms of qualifcation
     */
    public function listCenterwisePerformanceQual($qual_id, $intake_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListCenterQualificationPerformanceReports @qual_id = :qual_id,@intake_id=:intake_id", array(':qual_id' => $qual_id, ':intake_id' => $intake_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function AssesmentBookingTrend($intake_id) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        if ($role_code == 'ca' && !isset($center_id)) {
            $center_id = '';
        }
        $adapter = $this->tableGateway->getAdapter();
        if ($center_id == '') {
            $result = $adapter->query(
                    "EXEC AssessmentBookingTrendReports @intake_id = :intake_id", array(':intake_id' => $intake_id)
            );
        } else {
            $result = $adapter->query(
                    "EXEC AssessmentBookingTrendReportsCenter @intake_id = :intake_id,@center_id = :center_id", array(':intake_id' => $intake_id, ':center_id' => $center_id)
            );
        }
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function EnrolledTraineeTrend($intake_id, $qual_type, $ett_result_type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        if ($role_code == 'ca' && !isset($center_id)) {
            $center_id = '';
        }
        $adapter = $this->tableGateway->getAdapter();
        if ($center_id == '') {
            $result = $adapter->query(
                    "EXEC EnrolledTraineeTrendReports @intake_id = :intake_id, @qualf_type = :qualf_type, @component_type = :component_type", array(':intake_id' => $intake_id, ':qualf_type' => $qual_type, ':component_type' => $ett_result_type)
            );
        } else {
            $result = $adapter->query(
                    "EXEC EnrolledTraineeTrendReportsCenter @intake_id = :intake_id, @qualf_type = :qualf_type, @component_type = :component_type,@center_id = :center_id", array(':intake_id' => $intake_id, ':qualf_type' => $qual_type, ':component_type' => $ett_result_type, ':center_id' => $center_id)
            );
        }
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function EnrolledTrainee($intake_id, $qual_type, $ett_result_type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        if ($role_code == 'ca' && !isset($center_id)) {
            $center_id = '';
        }

        $adapter = $this->tableGateway->getAdapter();
        if ($center_id == '') {
            $result = $adapter->query(
                    "EXEC EnrolledTraineeReports @intake_id = :intake_id, @qualf_type = :qualf_type, @component_type = :component_type", array(':intake_id' => $intake_id, ':qualf_type' => $qual_type, ':component_type' => $ett_result_type)
            );
        } else {
            $result = $adapter->query(
                    "EXEC EnrolledTraineeReportsCenter @intake_id = :intake_id, @qualf_type = :qualf_type, @component_type = :component_type,@center_id = :center_id", array(':intake_id' => $intake_id, ':qualf_type' => $qual_type, ':component_type' => $ett_result_type, ':center_id' => $center_id)
            );
        }
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function ListExcelEnrolledTraineeTrend($intake_id, $qual_type, $ett_result_type) {

        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = 0;
        if ($role_code == 'ca') {
            $center_id = $session->offsetGet('center_id');
        }

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC EnrolledTraineeTrendExcelReports @intake_id = :intake_id, @qualf_type = :qualf_type, @component_type = :component_type", array(':intake_id' => $intake_id, ':qualf_type' => $qual_type, ':component_type' => $ett_result_type)
                // "EXEC EnrolledTraineeTrendExcelReports @intake_id = :intake_id, @qualf_type = :qualf_type, @component_type = :component_type, @center_id = :center_id", array(':intake_id' => $intake_id, ':qualf_type' => $qual_type, ':component_type' => $ett_result_type, ':center_id' => $center_id)
                // Commented by Varun        
        );

        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function ResitCompletedTrend($intake_id, $qual_type, $assType) {

        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = 0;
        if ($role_code == 'ca') {
            $center_id = $session->offsetGet('center_id');
        }

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ResitCompletedTrendReports @intake_id = :intake_id, @ass_type = :ass_type, @center_id = :center_id", array(':intake_id' => $intake_id, ':ass_type' => $assType, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function GetIntakeListReport() {

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC IntakeListReport", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function GetIntakeTraineeListReport() {

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC IntakeListTraineeReport", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function TraineeQualificationTrend($intake_id, $qual_type, $enroll_status) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC TraineeQualificationReports @intake_id = :intake_id, @enroll_status= :enroll_status, @qual_type=:qual_type ", array(':intake_id' => $intake_id, ':enroll_status' => $enroll_status, ':qual_type' => $qual_type)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function TraineeQualificationTrendForCenter($intake_id, $qual_type, $enroll_status, $center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC TraineeQualificationReportForCenter @intake_id = :intake_id, @enroll_status= :enroll_status, @qual_type=:qual_type,@center_id=:center_id ", array(':intake_id' => $intake_id, ':enroll_status' => $enroll_status, ':qual_type' => $qual_type, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listTotalTraineesbyStatusQual($intake_id, $qual_type, $enroll_status) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListTotalTraineesbyStatusQual @intake_id = :intake_id, @enroll_status= :enroll_status, @qual_type=:qual_type ", array(':intake_id' => $intake_id, ':enroll_status' => $enroll_status, ':qual_type' => $qual_type)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listTotalTraineesbyStatusQualForCenter($intake_id, $qual_type, $enroll_status, $center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListTotalTraineesbyStatusQualForCenter @intake_id = :intake_id, @enroll_status= :enroll_status, @qual_type=:qual_type, @center_id=:center_id ", array(':intake_id' => $intake_id, ':enroll_status' => $enroll_status, ':qual_type' => $qual_type, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listTotalTraineesRegion($qual_type) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListTotalTraineesbyRegion @qual_type=:qual_type ", array(':qual_type' => $qual_type)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listTotalTraineesForCenter($qual_type, $center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListTotalTraineesForCenter @qual_type=:qual_type,@center_id=:center_id ", array(':qual_type' => $qual_type, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function TraineeRegionTrend($qual_type) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC TraineeRegionReport @qual_type=:qual_type", array(':qual_type' => $qual_type)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function TraineeGradesPerComponent($intake_id, $qual_type, $comp_result_type) {

        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = 0;
        if ($role_code == 'ca') {
            $center_id = $session->offsetGet('center_id');
        }

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC TraineeGradeComponent @intake_id = :intake_id, @qualf_type = :qualf_type, @component_type = :component_type, @center_id = :center_id", array(':intake_id' => $intake_id, ':qualf_type' => $qual_type, ':component_type' => $comp_result_type, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    /**
     * FUNCTION TO CALCULATE TOTAL EVs, ACTIVE and InActive EVs
     * @param type $status
     * @return type
     */
    public function totalevs($status = NULL) {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from('users')->columns(array('count' => new \Zend\Db\Sql\Expression('COUNT(*)')));
        $select->join(array('ur' => 'user_role'), 'ur.user_id = users.user_id', array(), 'inner')
                ->join(array('ro' => 'role'), 'ro.rid = ur.role_id', array(), 'inner');
        $select->where(array('ro.role_code' => 'ev'));

        if ($status == 'active') {
            $select->where(array('users.status' => 1));
        } else if ($status == 'inactive') {
            $select->where(array('users.status' => 0));
        }
        //echo $sql->getSqlStringForSqlObject($select); die;
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset[0]['count'];
    }

    public function ExternalVerifierPerQualification() {

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ExternalVerifierPerQualificationReport", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function ExternalVerifierPerCenter() {

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ExternalVerifierPerCenterReport", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function GetEVIntakeListReport() {

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC EVIntakeListReport", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function EVVisitPerCenter($intake_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC EVVisitPerCenter @intake_id=:intake_id", array(':intake_id' => $intake_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function EVVisitPerQualification($intake_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC EVVisitPerQualification @intake_id=:intake_id", array(':intake_id' => $intake_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function EVReportSubPerQualification($intake_id) {
        $adapter = $this->tableGateway->getAdapter();

        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        if ($role_code == 'ev') {
            $userId = $session->offsetGet('userId');
        } else {
            $userId = 0;
        }

        $result = $adapter->query(
                "EXEC EVReportSubPerQualification @intake_id=:intake_id, @userId=:userId", array(':intake_id' => $intake_id, ':userId' => $userId)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function EVReportSubPerCenter($intake_id) {
        $adapter = $this->tableGateway->getAdapter();
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        if ($role_code == 'ev') {
            $userId = $session->offsetGet('userId');
        } else {
            $userId = 0;
        }

        $result = $adapter->query(
                "EXEC EVReportSubPerCenter @intake_id=:intake_id, @userId=:userId", array(':intake_id' => $intake_id, ':userId' => $userId)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function activeInactiveEVs($status = NULL) {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from('users')->columns(array('fname', 'lname', 'national_id', 'status'));
        $select->join(array('ur' => 'user_role'), 'ur.user_id = users.user_id', array(), 'inner')
                ->join(array('ro' => 'role'), 'ro.rid = ur.role_id', array(), 'inner');
        $select->where(array('ro.role_code' => 'ev'));

        if ($status == 'active') {
            $select->where(array('users.status' => 1));
        } else if ($status == 'inactive') {
            $select->where(array('users.status' => 0));
        }
        //echo $sql->getSqlStringForSqlObject($select); die;
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset;
    }

    public function listExcelCenterEVs() {

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListExcelCenterEVs", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listExcelQualificationEVs() {

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListExcelQualificationEVs", array()
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listExcelEVVisits($intake_id, $type) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListExcelEvVisits @intake_id = :intake_id, @type = :type", array(':intake_id' => $intake_id, ':type' => $type)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listExcelEVSubmitReport($intake_id, $type) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListExcelEvSubmitReport @intake_id = :intake_id, @type = :type", array(':intake_id' => $intake_id, ':type' => $type)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function ListExcelAssessmentBooking($intake_id, $type) {
        $adapter = $this->tableGateway->getAdapter();

        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = 0;
        if ($role_code == 'ca') {
            $center_id = $session->offsetGet('center_id');
        }

        $result = $adapter->query(
                "EXEC ListExcelAssessmentBooking @intake_id = :intake_id, @qualf_type = :qualf_type, @center_id = :center_id", array(':intake_id' => $intake_id, ':qualf_type' => $type, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function ListExcelTraineeGrade($intake_id, $type) {

        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = 0;
        if ($role_code == 'ca') {
            $center_id = $session->offsetGet('center_id');
        }

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListExcelTraineeGrade @intake_id = :intake_id, @qualf_type = :qualf_type, @center_id = :center_id", array(':intake_id' => $intake_id, ':qualf_type' => $type, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function ListExcelTraineeGradeBeforeResit($intake_id, $type) {
        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = 0;
        if ($role_code == 'ca') {
            $center_id = $session->offsetGet('center_id');
        }

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListExcelTraineeGradeBeforeResit @intake_id = :intake_id, @qualf_type = :qualf_type, @center_id = :center_id", array(':intake_id' => $intake_id, ':qualf_type' => $type, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function ListExcelTraineeResit($intake_id, $type) {

        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = 0;
        if ($role_code == 'ca') {
            $center_id = $session->offsetGet('center_id');
        }

        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListExcelTraineeResit @intake_id = :intake_id, @qualf_type = :qualf_type, @center_id = :center_id", array(':intake_id' => $intake_id, ':qualf_type' => $type, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    /**
     * GET only those INTAKES FOR WHICH MPs are created
     * @return type
     */
    public function getMpIntakeList() {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('mgi' => 'malpractice_general_info'));
        $select->columns(array('intake_id'))
                ->join(array('intakes' => 'intakes'), 'intakes.id = mgi.intake_id', array('intake_name'), 'inner');
        $select->group(array('mgi.intake_id', 'intakes.intake_name'));
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())->toArray();
        return $resultset;
    }

    /**
     * Get Only those intakes for whihc Appeals are created
     * @return type
     */
    public function getAppealIntakeList() {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('ap' => 'appeal'));
        $select->columns(array('intake_id'))
                ->join(array('intakes' => 'intakes'), 'intakes.id = ap.intake_id', array('intake_name'), 'inner');
        $select->group(array('ap.intake_id', 'intakes.intake_name'));
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())->toArray();
        return $resultset;
    }

    /**
     * GET only those CENTERS FOR WHICH MPs are created
     * @return type
     */
    public function getMpCenterList() {

        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');


        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('mgi' => 'malpractice_general_info'));
        $select->columns(array('center_id'))
                ->join(array('center' => 'center'), 'center.id = mgi.center_id', array('name'), 'inner');
        $select->group(array('mgi.center_id', 'center.name'));

        if ($role_code == 'ca') {
            $select->where(array('mgi.center_id' => $center_id));
        }

        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())->toArray();
        return $resultset;
    }

    /**
     * Get center list for which Appeals are created
     * @return type
     */
    public function getAppealCenterList() {

        $session = new Container('User');
        $role_code = $session->offsetGet('roleCode');
        $center_id = $session->offsetGet('center_id');
        $user_id = $session->offsetGet('userId');


        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('ap' => 'appeal'));
        $select->columns(array('center_id'))
                ->join(array('center' => 'center'), 'center.id = ap.center_id', array('name'), 'inner');
        $select->group(array('ap.center_id', 'center.name'));

        if ($role_code == 'ca') {
            $select->where(array('ap.center_id' => $center_id));
        }

        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())->toArray();
        return $resultset;
    }

    public function gettraineesMPQual($intake_id, $center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC MalpracticeTraineeQual @intake_id = :intake_id, @center_id = :center_id", array(':intake_id' => $intake_id, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function gettraineesAPLQual($intake_id, $center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC AppealTraineeQual @intake_id = :intake_id, @center_id = :center_id", array(':intake_id' => $intake_id, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function ListExcelMPTQualification($intake_id, $center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListExcelMPTQualification @intake_id = :intake_id, @center_id = :center_id", array(':intake_id' => $intake_id, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function ListExcelAPLTQualification($intake_id, $center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $result = $adapter->query(
                "EXEC ListExcelAPLTQualification @intake_id = :intake_id, @center_id = :center_id", array(':intake_id' => $intake_id, ':center_id' => $center_id)
        );
        $dataSource = $result->getDataSource();
        $resultset = $result->initialize($dataSource)
                ->toArray();
        return $resultset;
    }

    public function listExcelTotalTraineeForCenter($qual_type, $center_id) {
        $adapter = $this->tableGateway->getAdapter();
        $sql_ev_visit = "select ev1.national_id as national_id, ev1.first_name as first_name,ev1.last_name as last_name, ev1.center_id, ev1.center_name AS center_name, 'Not Enrolled' AS enrolled_status from (
        SELECT ev.trn_id AS trn_id, ev.ctn_id as ctn_id, stat = STUFF(CAST((
        SELECT [text()] = ', ' + c.status
        FROM trainee AS t
        left JOIN cohort_trainees AS coh ON t.id = coh.trainee_id
        left Join cohort as c on ( c.id = coh.cohort_id)
        WHERE coh.trainee_id = ev.trn_id
        FOR XML PATH(''), TYPE) AS VARCHAR(MAX)), 1, 2, ''), ev.national_id as national_id, ev.first_name as first_name,ev.last_name as last_name, ev.center_id, ev.center_name AS center_name
        FROM( select tt.id AS trn_id, cht.trainee_id as ctn_id,tt.national_id as national_id, tt.first_name as first_name,tt.last_name as last_name, center.center_id, center.name AS center_name
        from trainee AS tt
        left JOIN cohort_trainees AS cht ON tt.id = cht.trainee_id
left Join cohort as cc on ( cc.id = cht.cohort_id)
left Join center as center on ( center.id = tt.foundation_center_id)
where tt.foundation_center_id = '" . $center_id . "'
) as ev ) as ev1
where ev1.ctn_id IS NULL OR ev1.stat= 'draft'
AND ev1.national_id NOT IN (SELECT tt.national_id as national_id FROM [dbo].[trainee] as tt
INNER JOIN [dbo].[cohort_trainees] as ctt ON ctt.trainee_id = tt.id
INNER JOIN [dbo].[cohort] as cc ON cc.id = ctt.cohort_id
INNER JOIN [dbo].[qualification] as qt ON qt.id = cc.qualification_id
where cc.center_id = '" . $center_id . "'
AND cc.status='publish'
AND ctt.enrollment_status IN ('ACTIVE','ACTIVE_LATE','PAUSED','SUSPENDED','RESULT ON HOLD')
AND qt.type='" . $qual_type . "')
UNION
SELECT t.national_id as national_id, t.first_name as first_name,t.last_name as last_name, center.center_id, center.name AS center_name, 'Enrolled' AS enrollment_status FROM [dbo].[trainee] as t
INNER JOIN [dbo].[cohort_trainees] as ct ON ct.trainee_id = t.id
INNER JOIN [dbo].[cohort] as c ON c.id = ct.cohort_id
INNER JOIN [center] AS [center] ON [center].[id]=[c].[center_id]
INNER JOIN [dbo].[qualification] as q ON q.id = c.qualification_id
where c.center_id = '" . $center_id . "'
AND c.status='publish'
AND ct.enrollment_status IN ('ACTIVE','ACTIVE_LATE','PAUSED','SUSPENDED','RESULT ON HOLD')
AND q.type='" . $qual_type . "'
UNION
SELECT DISTINCT(t.national_id) as national_id, t.first_name as first_name,t.last_name as last_name, center.center_id, center.name as center_name, 'Not Enrolled' AS enrollment_status FROM [dbo].[trainee] as t
INNER JOIN [dbo].[cohort_trainees] as ct ON ct.trainee_id = t.id
INNER JOIN [dbo].[cohort] as c ON c.id = ct.cohort_id
INNER JOIN [dbo].[qualification] as q ON q.id = c.qualification_id
LEFT JOIN [dbo].[center] as center ON center.id = t.foundation_center_id
where c.center_id = '" . $center_id . "'
AND c.status='publish'
AND ((ct.enrollment_status NOT IN ('ACTIVE','ACTIVE_LATE','PAUSED','SUSPENDED','RESULT ON HOLD') AND q.type ='" . $qual_type . "')
OR (q.type !='" . $qual_type . "' AND ct.enrollment_status IN ('ACTIVE','ACTIVE_LATE','PAUSED','SUSPENDED','RESULT ON HOLD')) OR (q.type !='" . $qual_type . "' AND ct.enrollment_status NOT IN ('ACTIVE','ACTIVE_LATE','PAUSED','SUSPENDED','RESULT ON HOLD'))) "
                . "AND t.national_id NOT IN (SELECT tt.national_id as national_id FROM [dbo].[trainee] as tt
INNER JOIN [dbo].[cohort_trainees] as ctt ON ctt.trainee_id = tt.id
INNER JOIN [dbo].[cohort] as cc ON cc.id = ctt.cohort_id
INNER JOIN [dbo].[qualification] as qt ON qt.id = cc.qualification_id
where cc.center_id = '" . $center_id . "'
AND cc.status='publish'
AND ctt.enrollment_status IN ('ACTIVE','ACTIVE_LATE','PAUSED','SUSPENDED','RESULT ON HOLD')
AND qt.type='" . $qual_type . "')";

        $optionalParameters2 = '';
        $statement2 = $adapter->query($sql_ev_visit, array());
        //$response=$statement2->execute();
        return $statement2->toArray();
    }

}
