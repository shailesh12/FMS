<?php

namespace ZF2AuthAcl\Model;

use Zend\Db\TableGateway\TableGateway;
use Zend\Mail\Message;
use Zend\Mail\Transport\Smtp as SmtpTransport;
use Zend\Mail\Transport\SmtpOptions;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Select;
use Zend\Db\ResultSet\ResultSet;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class RecoverEmailTable implements ServiceLocatorAwareInterface {

    protected $tableGateway;
    protected $services;

    public function setServiceLocator(ServiceLocatorInterface $locator) {
        $this->services = $locator;
    }

    public function getServiceLocator() {
        return $this->services;
    }

    public function __construct(TableGateway $tableGateway) {
        $this->tableGateway = $tableGateway;
        $this->resultSetPrototype = new ResultSet(ResultSet::TYPE_ARRAY);
    }

    public function fetchAll() {
        $resultSet = $this->tableGateway->select();
        return $resultSet;
    }

    public function deleteRecoverEmail($email) {
        $this->tableGateway->delete(array('email' => $email));
    }

    public function deleteActivationEmail($email) {
        $sql = new Sql($this->tableGateway->getAdapter());
        $where = array('email' => $email);
        $select = $sql->delete('activation')
                ->where($where);
        $statement = $sql->prepareStatementForSqlObject($select);
        $results = $statement->execute();
        return;
    }

    public function addRecoverEmail($email) {
        $hash = md5(rand(0000000, 9999999));
        $data = array(
            'email' => $email,
            'hash_value' => $hash,
            'requested_on' => date('d-m-Y h:i:s'),
        );
        $this->tableGateway->insert($data);
        return $hash;
    }

    public function getEmailbyHash($hash_value) {
        $rowset = $this->tableGateway->select(array('hash_value' => $hash_value));
        $row = $rowset->current();
        if (!$row) {
            throw new \Exception("Could not find row $hash_value");
        }
        return $row;
    }
    /**
     * Function to fetch row from activation table for given hash
     * @param type $hash_value
     * @return type
     */
    public function getActivateRowbyHash($hash_value) {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select()->from(array(
            'activation' => 'activation'
        ));
        $where = array('hash_value' => $hash_value);
        $select->where($where);
        $statement = $sql->prepareStatementForSqlObject($select);
        $emailObj = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $emailObj;
    }

    public function getActivationEmailbyHash($hash_value) {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select()->from(array(
            'activation' => 'activation'
        ));
        $where = array('hash_value' => $hash_value);
        try {
            if (count($where) > 0) {
                $select->where($where);
            }
            $statement = $sql->prepareStatementForSqlObject($select);
            $emailObj = $this->resultSetPrototype->initialize($statement->execute())
                    ->toArray();
            return $emailObj;
        } catch (\Exception $e) {
            throw new \Exception($e->getPrevious()->getMessage());
        }
    }

    public function sendEmailToUser($subject, $reciever_message, $email) {

        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from('config', array('site_data'));
        $select->where(array('id' => '2'));
        $statement = $sql->prepareStatementForSqlObject($select);
        $results = $statement->execute();
        foreach ($results as $row) {
            
        }
        $admin_email = $row['site_data'];


        //send email        
        $message = new Message();
        $message->addTo($email)
                ->addFrom($admin_email, 'FMS Admin')
                ->setSubject($subject)
                ->setBody($reciever_message);

        // Setup SMTP transport using LOGIN authentication
        $get_smtp_details = $this->getServiceLocator()->get('Config');
        $smtp_details = $get_smtp_details['smtp_details'];
        $transport = new SmtpTransport();
        $options = new SmtpOptions(array(
            'name' => $smtp_details['name'],
            'host' => $smtp_details['host'],
            'connection_class' => $smtp_details['connection_class'],
            'port' => $smtp_details['port'],
            'connection_config' => array(
                'ssl' => $smtp_details['connection_config']['ssl'], /* Page would hang without this line being added */
                'username' => $smtp_details['connection_config']['username'],
                'password' => $smtp_details['connection_config']['password'],
            ),
        ));
        $transport->setOptions($options);
        $transport->send($message);
    }

}
