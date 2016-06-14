<?php

namespace ZF2AuthAcl\Model;

use Zend\Db\TableGateway\TableGateway;
use ZF2AuthAcl\Utility\UserPassword;
use ZF2AuthAcl\Model\UserRole;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Select;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Paginator\Adapter\DbSelect;
use Zend\Paginator\Paginator;
use Zend\Db\Sql\Expression;

class UserTable {

    protected $tableGateway;
    protected $adapter;
    public $table = 'users';

    public function __construct(TableGateway $tableGateway) {
        $this->tableGateway = $tableGateway;
        $this->resultSetPrototype = new ResultSet(ResultSet::TYPE_ARRAY);
    }

    public function fetchAll($paginated = false, $order_by = Null, $order = Null, $qualificationArray = Null, $centerArray = Null, $searchText = NULL) {
        if ($order_by == 'user_id' || $order_by == 'first_name' || $order_by == 'last_name' || $order_by == 'gender' || $order_by == 'status') {
            $order_by = 'u.' . $order_by;
        }
        $sql_qual = "(SELECT distinct(q.name+', ')
                        FROM qualification as q
                        INNER JOIN [ev_qualifications] AS [eq] ON [eq].[qualification_id] = [q].[id]
                        where eq.ev_user_id = [u].[user_id]
                        FOR XML PATH(''))";
        $sql_center = "(SELECT distinct(c.name+', ')
                        FROM center as c
                        INNER JOIN [ev_centers] AS [ec] ON [ec].[center_id] = [c].[id]
                        where ec.ev_user_id = [u].[user_id]
                        FOR XML PATH(''))";

        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('u' => 'users'));
        $select->columns(array('user_id', 'national_id', 'fname', 'lname', 'gender', 'status', 'qual_name' => new Expression($sql_qual), 'center_name' => new Expression($sql_center), 'dataname' => new \Zend\Db\Sql\Expression("CONCAT(fname,' ',lname)")))
                ->join(array('ur' => 'user_role'), 'u.user_id = ur.user_id', array(), 'left')
                ->join(array('r' => 'role'), 'ur.role_id = r.rid', array(), 'left')
                ->join(array('evq' => 'ev_qualifications'), 'evq.ev_user_id=u.user_id', array(), 'left')
                ->join(array('evc' => 'ev_centers'), 'evc.ev_user_id=u.user_id', array(), 'left')
                ->join(array('q' => 'qualification'), 'q.id=evq.qualification_id', array(), 'left')
                ->join(array('c' => 'center'), 'c.id=evc.center_id', array(), 'left')
                ->where(array('r.role_code' => 'ev'))
                ->group(array('u.user_id', 'u.national_id', 'u.fname', 'u.lname', 'u.gender', 'u.status'))
                ->order($order_by . ' ' . $order);

        if (isset($qualificationArray) && $qualificationArray != '') {
            $select->where->in("q.id", $qualificationArray);
        }

        if (isset($centerArray) && $centerArray != '') {
            $select->where->in("c.id", $centerArray);
        }

        if (isset($searchText) && trim($searchText) != '') {
            $select->where->NEST->like('u.fname', "%" . $searchText . "%")
            ->or->like('u.lname', "%" . $searchText . "%")
            ->or->like('q.name', "%" . $searchText . "%")
            ->or->like('c.name', "%" . $searchText . "%")
            ->or->like('u.national_id', "%" . $searchText . "%")
            ->or->literal("concat(fname,' ',lname) LIKE ?", '%' . $searchText . '%');
        }

        $statement = $sql->prepareStatementForSqlObject($select);
        if ($paginated) {
            $resultSetPrototype = new ResultSet();
            $paginatorAdapter = new DbSelect(
                    // our configured select object
                    $select,
                    // the adapter to run it against
                    $this->tableGateway->getAdapter(),
                    // the result set to hydrate
                    $resultSetPrototype
            );
            $paginator = new Paginator($paginatorAdapter);

            return $paginator;
        }

        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset;
    }

    /**
     * Function get the details of an external verifier
     * @param type $id
     */
    public function getEvDetails($id) {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('u' => 'users'));
        $select->columns(array('user_id', 'national_id', 'phone_number', 'email', 'nationality', 'fname', 'mname', 'lname', 'gender', 'status', 'address', 'pincode', 'profile_image', 'fax_number', 'training_status'))
                ->join(array('ur' => 'user_role'), 'u.user_id = ur.user_id', array(), 'left')
                ->join(array('r' => 'role'), 'ur.role_id = r.rid', array(), 'left')
                ->join(array('ec' => 'ev_centers'), 'ec.ev_user_id = u.user_id', array(), 'left')
                ->join(array('c' => 'center'), 'c.id = ec.center_id', array('center_name' => 'name', 'centerid' => 'center_id'), 'left')
                ->join(array('eq' => 'ev_qualifications'), 'eq.ev_user_id = u.user_id', array(), 'left')
                ->join(array('q' => 'qualification'), 'q.id = eq.qualification_id', array('qual_name' => 'name', 'qcode' => 'code'), 'left')
                ->join(array('re' => 'region'), 're.id = u.region', array('region_name' => 'name'), 'left')
                ->join(array('ci' => 'city'), 'ci.id = u.city', array('city_name' => 'name'), 'left')
                ->where(array('u.user_id' => $id))
                ->group(array('u.user_id', 'u.mname', 'u.phone_number', 'u.email', 'u.nationality', 'u.national_id', 'u.fname', 'u.lname', 'u.gender', 'u.status', 'u.profile_image', 'u.fax_number', 'u.training_status', 'c.name', 'c.center_id', 'q.name', 'q.code', 're.name', 'ci.name', 'u.address', 'u.pincode'));

        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        $finalArr = array();
        if (!empty($resultset)) {
            foreach ($resultset as $result) {
                if ($id = $result['user_id']) {
                    if (empty($finalArr)) {
                        $finalArr = $result;
                        $finalArr['qualification'][] = $result['qual_name'];
                        $finalArr['qualification_code'][] = $result['qcode'];
                        $finalArr['center'][] = $result['center_name'];
                        $finalArr['center_id'][] = $result['centerid'];
                    } else {
                        if (!in_array($result['qual_name'], $finalArr['qualification'])) {
                            $finalArr['qualification'][] = $result['qual_name'];
                            $finalArr['qualification_code'][] = $result['qcode'];
                        }
                        if (!in_array($result['center_name'], $finalArr['center'])) {
                            $finalArr['center'][] = $result['center_name'];
                            $finalArr['center_id'][] = $result['centerid'];
                        }
                    }
                }
            }
        }
        return $finalArr;
    }

    /**
     * Function to get a list of user roles available on the basis of the current logged in user
     * @param type $currentRole Role of current logged in user
     */
    public function userRole($currentRole) {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select()->from(array('r' => 'role'), array('rid', 'role_name'));

        switch ($currentRole) {
            case 'sa':
                $select->where->in('r.role_code', array('aa', 'ad', 'ca', 'ga', 'ev'));
                break;
            case 'aa':
                $select->where->in('r.role_code', array('ca', 'ga', 'ev'));
                break;
//            case 'ca':
//                $select->where(array('r.role_code' => 'ev'));
//                break;
            default:
                return array();
        }
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        $result = array();
        foreach ($resultset as $user_role) {
            $result[$user_role['rid']] = $user_role['role_name'];
        }

        return $result;
    }

    public function getUserCenterId($userid) {
        $userid = (int) $userid;
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('cu' => 'center_users'));
        $select->columns(array('center_id'))
                ->where(array('cu.user_id' => $userid));
        $statement = $sql->prepareStatementForSqlObject($select);
        $resultset = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return $resultset;
    }

    /**
     * Function to update the recovered password for user
     * @param type $email
     * @param type $pswrd
     */
    public function updateRecoveryPassword($email, $pswrd) {
        $userPassword = new UserPassword();
        $encyptPass = $userPassword->create($pswrd);
        $data = array('password' => $encyptPass);
        $this->tableGateway->update($data, array('email' => $email));
    }

    /**
     * Function to update the new password for user
     * @param type $email
     * @param type $pswrd
     */
    public function updateActivationPassword($email, $pswrd) {
        $userPassword = new UserPassword();
        $encyptPass = $userPassword->create($pswrd);
        $data = array('password' => $encyptPass, 'status' => 1);
        $this->tableGateway->update($data, array('email' => $email));

        // UPDATE CENTER STATUS
        $adapter = $this->tableGateway->getAdapter();
        $sql_center_status = "UPDATE center SET status = 'Active' WHERE email='" . $email . "' ";
        $optionalParameters1 = '';
        $statement = $adapter->createStatement($sql_center_status, $optionalParameters1);
        $statement->execute();
    }

    /**
     * Function to insert the user data into the table
     * @param type $user
     */
    public function saveUser($user) {
        $user_data = array(
            'national_id' => $user['national_id'],
            'fname' => $user['fname'],
            'lname' => $user['lname'],
            'mname' => $user['mname'],
            'email' => $user['email'],
            'status' => $user['status'],
            'training_status' => $user['training_status'],
            'address' => $user['address'],
            'region' => $user['region'],
            'city' => $user['city'],
            'phone_number' => $user['phone_number'],
            'fax_number' => $user['fax_number'],
            'pincode' => $user['pincode'],
            'profile_image' => $user['profile_image'],
            'country' => $user['country'],
            'gender' => $user['gender'],
            'age' => $user['age'],
            'created_date' => time(),
            'created_by' => $user['created_by'],
        );
        $this->tableGateway->insert($user_data);
        $user_id = $this->tableGateway->lastInsertValue;
        $role_data = array(
            'user_id' => $user_id,
            'role_id' => $user['role'],
        );
        /**
         * Insert user role mapping
         */
        if ($user_id) {
            $adapter = $this->tableGateway->getAdapter();
            //$sql = 'INSERT INTO user_role (user_id,role_id, assigned_date, assigned_by) VALUES ("' . $user_id . '","' . time() . '","' . $user['role'] . '","' . $user['created_by'] . '")';

            $sql = new Sql($adapter);
            $insert = $sql->insert('user_role');
            $data = array(
                'user_id' => $user_id,
                'role_id' => $user['role'],
                'assigned_date' => time(),
                'assigned_by' => $user['created_by'],
            );
            $insert->values($data);
            $selectString = $sql->getSqlStringForSqlObject($insert);
            $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE);



//            $optionalParameters = '';
//            $statement = $adapter->createStatement($sql, $optionalParameters);
//            $statement->execute();
        }
        return $user_id;
    }

    /**
     * Function To fetch Users List
     * @param type $where
     * @param type $columns
     * @return type
     * @throws \Exception
     */
    public function getUsers($where = array(), $columns = array()) {
        try {
            $sql = new Sql($this->tableGateway->getAdapter());
            $select = $sql->select()->from(array(
                'user' => $this->table
            ));

            if (count($where) > 0) {
                $select->where($where);
            }

            if (count($columns) > 0) {
                $select->columns($columns);
            }
            $select->join(array('userRole' => 'user_role'), 'userRole.user_id = user.user_id', array('role_id'), 'LEFT');
            $select->join(array('role' => 'role'), 'userRole.role_id = role.rid', array('role_name', 'role_code'), 'LEFT');

            $statement = $sql->prepareStatementForSqlObject($select);
            $users = $this->resultSetPrototype->initialize($statement->execute())
                    ->toArray();
            return $users;
        } catch (\Exception $e) {
            throw new \Exception($e->getPrevious()->getMessage());
        }
    }

    public function getActiveEvUsers($where = array(), $columns = array()) {
        try {
            $sql = new Sql($this->tableGateway->getAdapter());
            $select = $sql->select()->from(array(
                'user' => $this->table
            ));

            if (count($where) > 0) {
                $select->where($where);
            }

            if (count($columns) > 0) {
                $select->columns($columns);
            }
            $select->join(array('userRole' => 'user_role'), 'userRole.user_id = user.user_id', array('role_id'), 'LEFT');
            $select->join(array('role' => 'role'), 'userRole.role_id = role.rid', array('role_name', 'role_code'), 'LEFT');
            $select->where(array('user.status' => 1));
            $statement = $sql->prepareStatementForSqlObject($select);
            //echo '<pre>'; print_r($statement); die;
            $users = $this->resultSetPrototype->initialize($statement->execute())
                    ->toArray();
            return $users;
        } catch (\Exception $e) {
            throw new \Exception($e->getPrevious()->getMessage());
        }
    }

    /**
     * Function to fetch a specific user record
     * @param type $id
     * @return type
     * @throws \Exception
     */
    public function getUser($id) {
        $id = (int) $id;
        $adapter = $this->tableGateway->getAdapter();
        $sql = new Sql($adapter);
        $select = $sql->select();
        $select->from('users');
        $select->where(array('users.user_id' => $id));
        $select->join(array('userRole' => 'user_role'), 'userRole.user_id = users.user_id', array('role_id'), 'LEFT');
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new ResultSet;
            $resultSet->initialize($result);
            foreach ($resultSet as $row) {
                $user = $row;
            }
        }
        return $user;
    }

    /**
     * Function to get the details of activation from email Id
     * @param type $email
     */
    public function checkActiveEmail($email) {
        $adapter = $this->tableGateway->getAdapter();
        $sql = new Sql($adapter);
        $select = $sql->select();
        $select->from('users');
        $select->where(array('email' => $email));
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();

        return $result;
    }

    /**
     * function to update the user record
     */
    public function updateUser($user, $id) {
        $user_data = array(
            'national_id' => $user['national_id'],
            'fname' => $user['fname'],
            'mname' => $user['mname'],
            'lname' => $user['lname'],
            'email' => $user['email'],
            'training_status' => $user['training_status'],
            'address' => $user['address'],
            'region' => $user['region'],
            'city' => $user['city'],
            //'status' => $user['status'],
            'phone_number' => $user['phone_number'],
            'fax_number' => $user['fax_number'],
            'pincode' => $user['pincode'],
            'profile_image' => $user['profile_image'],
            'country' => $user['country'],
            'gender' => $user['gender'],
            'age' => $user['age'],
            'updated_date' => time(),
            'updated_by' => $user['updated_by'],
        );
        $this->tableGateway->update($user_data, array('user_id' => $id));
        return $id;
    }

    /**
     * Function to save the activation key in DB
     */
    public function saveActivationEmail($email) {
        $adapter = $this->tableGateway->getAdapter();
        $sql = new Sql($adapter);
        $hash = md5(rand(0000000, 9999999));
        $insert = $sql->insert('activation');
        $newData = array(
            'email' => $email,
            'hash_value' => $hash,
            'requested_on' => date('d-m-Y h:i:s'),
        );
        $insert->values($newData);
        $selectString = $sql->getSqlStringForSqlObject($insert);
        $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE);
        return $hash;
    }

    /**
     * CEATES USER FROM CreateCenter Page
     * @param type $data
     */
    public function createUser($data, $center_last_id) {

        // CREATE USER
        if ($data->submit == 'Publish') {
            $adapter = $this->tableGateway->getAdapter();
            $sql = new Sql($adapter);
            $insert = $sql->insert('users');
            $user_data = array(
                'national_id' => -1,
                'fname' => $data['poc_fname'],
                'lname' => $data['poc_lname'],
                'email' => $data['email'],
                'status' => 0,
                'country' => 75,
                'address' => $data['address_1'],
                'city' => $data['city'],
                'region' => $data['state'],
                'pincode' => $data['zip'],
                'phone_number' => $data['phone'],
                'fax_number' => $data['fax'],
                'password' => md5('0987654321'),
                'gender' => $data['gender'],
                'age' => 1,
                'created_date' => $data['created_date'],
                'created_by' => $data['created_by'],
                'updated_date' => $data['updated_date'],
                'updated_by' => $data['updated_by'],
            );
            $insert->values($user_data);
            $selectString = $sql->getSqlStringForSqlObject($insert);
            $results_user = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE);
            $user_id_last = $adapter->getDriver()->getLastGeneratedValue();

            // ASSIGNED CENTRE ADMIN ROLE TO USER
            if ($user_id_last > 0) {
                //$this->getServiceLocator()->get('Zend\Log')->info('User with Id ' . $user_id_last . ' added by user ' . $session->offsetGet('userId'));
                $insert_ur = $sql->insert('user_role');
                $data_ur = array(
                    'user_id' => $user_id_last,
                    'role_id' => 3, //Centre Admin
                    'assigned_date' => $data['created_date'],
                    'assigned_by' => $data['created_by'],
                );
                $insert_ur->values($data_ur);
                $selectString_ur = $sql->getSqlStringForSqlObject($insert_ur);
                $adapter->query($selectString_ur, $adapter::QUERY_MODE_EXECUTE);

                // CENTER USER MAPPING
                $insert_cumap = $sql->insert('center_users');
                $data_cumap = array(
                    'center_id' => $center_last_id,
                    'user_id' => $user_id_last,
                );
                $insert_cumap->values($data_cumap);
                $selectString_cumap = $sql->getSqlStringForSqlObject($insert_cumap);
                $adapter->query($selectString_cumap, $adapter::QUERY_MODE_EXECUTE);
            }
        }
    }

    /**
     * FUNCTION to update USer from createCenter Page
     * @param type $user
     * @param type $id
     * @return type
     */
    public function updateUserCenter($data, $email) {
        $user_data = array(
            'fname' => $data['poc_fname'],
            'lname' => $data['poc_lname'],
            'address' => $data['address_1'],
            'city' => $data['city'],
            'region' => $data['state'],
            'pincode' => $data['zip'],
            'phone_number' => $data['phone'],
            'fax_number' => $data['fax'],
            'gender' => $data['gender'],
            'updated_date' => $data['updated_date'],
            'updated_by' => $data['updated_by'],
        );
        $this->tableGateway->update($user_data, array('email' => $email));
    }

    public function isUserExists($where = array(), $columns = array()) {
        try {
            $sql = new Sql($this->tableGateway->getAdapter());
            $select = $sql->select()->from(array('user' => $this->table));

            if (count($where) > 0) {
                $select->where($where);
            }

            if (count($columns) > 0) {
                $select->columns($columns);
            }

            $statement = $sql->prepareStatementForSqlObject($select);
            $users = $this->resultSetPrototype->initialize($statement->execute())
                    ->toArray();
            return $users;
        } catch (\Exception $e) {
            throw new \Exception($e->getPrevious()->getMessage());
        }
    }

    /**
     * SINGLE SESSION CODE
     */
    public function singleSessionLoginCheck($email) {

        $adapter = $this->tableGateway->getAdapter();
        $sql = new Sql($adapter);
        $select = $sql->select();
        $select->from('sessions');
        $select->where(array('user_id' => $email));
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $this->resultSetPrototype->initialize($statement->execute())
                ->toArray();
        return count($result);
    }

    public function deleteMultipleSessions($email) {

        $adapter = $this->tableGateway->getAdapter();
        $sessionsTable = new TableGateway('sessions', $adapter);
        $sessionsTable->delete(array('user_id' => $email));
    }

}
