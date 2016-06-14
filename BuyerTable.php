<?php

namespace Purchase\Model;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Sql\Select;
use Zend\Paginator\Adapter\DbSelect;
use Zend\Paginator\Paginator;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;
Use Zend\Db\Sql\Expression;

class PurchaseTable {

    protected $tableGateway;

    public function __construct(TableGateway $tableGateway) {
        $this->tableGateway = $tableGateway;
        $this->resultSetPrototype = new ResultSet(ResultSet::TYPE_ARRAY);
    }

    /**
     * Function to Fetch listing for Manage Sectors Page
     * @param type $paginated
     * @param type $searchText
     * @return \Zend\Paginator\Paginator
     */
    public function fetchAll() {
        // create a new Select object for the table sector        
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->select();
        $select->from(array('i' => 'item'))
                ->columns(array('id', 'item_name', 'price', 'purhcase_date', 'purchased_by'))
                ->join(array('p' => 'purchaser'), 'p.id = i.purchased_by', array('name'), 'left');
                //->order('i.' . $order_by . ' ' . $order)
                //->group(array('i.id', 'i.item_name', 'i.price', 'i.purhcase_date', 'i.purchased_by'));
        //echo $sql->getSqlStringForSqlObject($select);
        //die;
//        try {
//            if (isset($searchText) && trim($searchText) != '') {
//                $searchCount = strlen($searchText);
//                if ($searchCount > 100) {
//                    throw new SearchTextLimit('Search Keyword length can not be more than 100');
//                }
//                $select->where->like('item_name', "%" . $searchText . "%")
//                ->or->like('id', "%" . $searchText . "%");
//            }
//            if ($paginated) {
//                // create a new result set based on the  entity
//                $resultSetPrototype = new ResultSet();
//                $resultSetPrototype->setArrayObjectPrototype(new Purchase());
//                // create a new pagination adapter object
//                $paginatorAdapter = new DbSelect(
//                        // our configured select object
//                        $select,
//                        // the adapter to run it against
//                        $this->tableGateway->getAdapter(),
//                        // the result set to hydrate
//                        $resultSetPrototype
//                );
//
//                $paginator = new Paginator($paginatorAdapter);
////                echo '<pre>';print_r($paginator); die;
//                return $paginator;
//            }

            $statement = $sql->prepareStatementForSqlObject($select);
            $resultset = $this->resultSetPrototype->initialize($statement->execute())->toArray();
            //echo '<pre>'; print_r($resultset); die;
            return $resultset;
//        } catch (SearchTextLimit $e) {
//            throw new SearchTextLimit('Search Keyword length can not be more than 100');
//        }
    }

    /**
     * Function to get the sector list as dropdown
     * @return type
     */
//    public function fetchSectorDropdown() {
//        $sql = new Sql($this->tableGateway->getAdapter());
//        $select = $sql->select()->from(array('s' => 'sector'), array('id', 'name'));
//        $select->where(array('s.visibility' => 'Visible'));
//        $statement = $sql->prepareStatementForSqlObject($select);
//        $resultset = $this->resultSetPrototype->initialize($statement->execute())
//                ->toArray();
//        $result = array();
//        foreach ($resultset as $sector) {
//            $result[$sector['id']] = $sector['name'];
//        }
//        return $result;
//    }

    public function getItem($id) {
        $id = (int) $id;
        $rowset = $this->tableGateway->select(array('id' => $id));
        $row = $rowset->current();
        if (!$row) {
            throw new \Exception("Could not find row $id");
        }
        return $row;
    }

    public function getItemId($name) {

        $rowset = $this->tableGateway->select(array('item_name' => $name));
        $row = $rowset->current();
        if (!$row) {
            throw new \Exception("Could not find row $name");
        }
        return $row;
    }

    public function saveItem(Item $item) {
        $data = array(
            'item_name' => $item->item_name,
            'purhcase_date' => $item->purhcase_date,
            'price' => $item->price,
            'purchased_by' => $item->purchased_by,
            'comment' => $item->comment,
        );
        $id = (int) $item->id;
        if ($id == 0) {
            $data['created_date'] = $item->created_date;
            $data['updated_date'] = $item->updated_date;

            if ($this->tableGateway->insert($data)) {
                $itemId = $this->tableGateway->getLastInsertValue();
            }
        } else {
            if ($this->getItem($id)) {
                $data['updated_date'] = $item->updated_date;

                $this->tableGateway->update($data, array('id' => $id));
                $sectorId = $id;
            } else {
                throw new \Exception('Item id does not exist');
            }
        }
        return $itemId;
    }

//    public function getAssociatedQual($sectorId) {
//        $sql = new Sql($this->tableGateway->getAdapter());
//        $select = $sql->select();
//        $select->from(array('s' => 'sector'));
//        $select->columns(array('id'))
//                ->join(array('qs' => 'qualification_sectors'), 'qs.sector_id = s.id', array(), 'left')
//                ->join(array('q' => 'qualification'), 'q.id=qs.qualification_id', array(), 'left');
//        $select->group(array('s.id'));
//        $select->where(array('q.status' => 'publish'));
////        $select->where(array('q.status' => 'phasing_out'));
////        $select->where(array('q.status' => 'inactive'));
//        $select->where(array('qs.sector_id' => $sectorId));
//        $statement = $sql->prepareStatementForSqlObject($select);
//        $resultset = $this->resultSetPrototype->initialize($statement->execute())
//                ->toArray();
//        return $resultset;
//    }

    public function deleteSector($sectorId) {
        $sql = new Sql($this->tableGateway->getAdapter());
        $select = $sql->delete('qualification_sectors')->where(array('sector_id' => $sectorId));
        $statement = $sql->prepareStatementForSqlObject($select);
        $statement->execute();
        $this->tableGateway->delete(array('id' => $sectorId));
    }

    /**
     * Function to check whether a sector is associated with qualification or not
     * @param int $id
     */
//    public function checkSectorQualAssoc($id) {
//        
//        $adapter = $this->tableGateway->getAdapter();
//        $sql_unit_count = "SELECT COUNT(sector_id) as total FROM qualification_sectors AS qs WHERE qs.sector_id = '".$id."' ";
//        $optionalParameters1 = '';
//        $statement = $adapter->createStatement($sql_unit_count, $optionalParameters1);      
//        $resultset = $this->resultSetPrototype->initialize($statement->execute())->toArray();
//        return $resultset;        
//        
//    }
}

class SearchTextLimit extends \Exception {
    
}
