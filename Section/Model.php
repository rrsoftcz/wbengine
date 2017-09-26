<?php

/**
 * $Id: Model.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Site's object Class_Site data Model.
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Section;

use Wbengine\Db;
use Wbengine\Model\ModelAbstract;
use Wbengine\Section\Model\Exception;
use Wbengine\Section;
use Wbengine\Section\Model\Exception\SectionModelException;

class Model extends ModelAbstract {


    /**
     * We do nothink on this constructor
     */
    public function __construct()
    {

    }


    /**
     * Return site ID from given URL.
     *
     * @param string $url
     * @return integer
     */
    public function getSectionById( $sectionId )
    {
	$sql = sprintf("SELECT * FROM %s
			WHERE section_id = %d 
			LIMIT 1;"
		, S_TABLE_SECTIONS,
        $sectionId
	);
                $e = new \Exception();

//        echo('<pre>');
//        print_r($sql);
//        echo('</pre>');die();
//var_dump(Db::query($sql)->fetch_object());die();
	return Db::query($sql)->fetch_object();
    }


    /**
     * Return site sections as assoc array.
     * @return array
     */
    public function getSections()
    {


//	$esqeel = ($this->getDbAdapter());
//	$sresultSet = $esqeel->select();
//	$sql = new Sql($this->getDbAdapter(), S_TABLE_SECTIONS);
//	$select = $sql->select();
//	$select->where(array('active' => 1));
//	$x = $this->getDbAdapter();
//	$statement = $sql->prepareStatementForSqlObject($select);
//	$result = $statement->execute();
//	var_dump($result);

	$sql = sprintf("SELECT * FROM %s
			WHERE active = %d;",
        S_TABLE_SECTIONS,
    1
	);
//        echo('<pre>');
//        print_r(Db::fetchAllAssoc($sql));
//        echo('</pre>');
//	var_dump($this->getDbAdapter()->fetchAll($sql));
	return Db::fetchAllAssoc($sql);
//	$rows = $this->getDbAdapter()->query($sql);
//	var_dump($rows);
//	if ( $rows instanceof \PDOStatement ) {
//	    foreach ( $rows as $row ) {
//		var_dump($row);
//	    }
//	}
//	var_dump($statement);
//	/* @var $results Zend\Db\ResultSet\ResultSet */
//	$results = $statement->execute(array('active' => 0));
//	$row = $results->current();
//	$name = $row['title'];
//	$rowData = $resultSet->current()->getArrayCopy();
//	var_dump($row);
//	$res = $this->getDbAdapter()->query($sql);
//	return $res->fetch();
    }


    /**
     * Return section boxes as assoc array.
     * @param Section $section
     * @return array
     * @throws Exception\RuntimeException
     * @throws SectionModelException
     */
    public function getBoxes( Section $section )
    {

	$sql = sprintf('SELECT box.id, box.module, box.method, sec.key, box.static
			FROM %s ord LEFT JOIN %s box ON (box.id = ord.box_id)
                        LEFT JOIN %s sec ON (box.section_id = sec.section_id)
			WHERE (ord.site_id = %d OR box.shared = 1)
            AND box.section_id = %d
            AND ((box.device_min <= %4$d AND box.device_strict = 0) OR (box.device_min = %4$d AND box.device_strict = 1))
            GROUP BY box.id	ORDER BY ord.order ASC;'
		, S_TABLE_BOX_ORDERS
		, S_TABLE_BOXES
		, S_TABLE_SECTIONS,
        $section->getSite()->getSiteId(),
        $section->getSectionId()
        , (int) $section->getSite()->getParent()->getDeviceType()
	);
    return Db::fetchAllAssoc($sql);
//	print_r($sql);die();
//	return $this->getDbAdapter()->query($sql, array(
//		    $section->getSite()->getSiteId(),
//		    $section->getSectionId()));
    }

}
