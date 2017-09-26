<?php

/**
 * $Id: Model.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Sessions data model class
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Session;

use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Db;
use Wbengine\Model\ModelAbstract;
use Zend\Db\Sql\Sql;

class Model extends ModelAbstract
{


    public function getSessionData()
    {
//        var_dump($this->getDbAdapter());
        $sql = sprintf("SELECT * FROM %s s
                        WHERE s.session_id = '%s'
                        AND s.user_ip = '%s'
                        AND s.user_salt = '%s'
                        LIMIT 1;"
            , S_TABLE_SESSIONS
            , session_id()
            , Utils::getUserIp()
            , substr(md5(Utils::getUserAgent()), 0, 10)
        );

//        var_dump(self::getConnection()->query($sql)->fetch_assoc);die();
//        return (self::_ fetchRow($sql));die();

//        $sql = New Sql($this->getDbAdapter());
//        $select = $sql->select();
//        $select->from(S_TABLE_SESSIONS);
//        $select->where(array(
//                'session_id' => session_id(),
//                'user_ip' => Utils::getUserIp(),
//                'user_salt' => substr(md5(Utils::getUserAgent()), 0, 10))
//        );
        $y = (Db::query($sql)->fetch_assoc());
//        Utils::dump($y);
        return $y;

//        $e = new \Exception();
//        echo('<pre>');
//        print_r($y);
//        echo('</pre>');
//        die();
//
//        print_r($this->getConnection()->query($sql)->fetch_row());die($sql);
//return null;
//        $statement = $sql->prepareStatementForSqlObject($select);
//        $results = $statement->execute();
//        var_dump($results->current());
//        return ($results->getAffectedRows())
//            ? $results->current()
//            : null;
    }


    /**
     * Insert session's data to database.
     * @param \Wbengine\Session\SessionAbstract $session
     * @return int
     */
    public function insertSessionData(SessionAbstract $session)
    {
        $query = sprintf("INSERT INTO %s " .
            " (`session_id`, `user_id`, `session_data`, `user_agent`, `user_ip`, `session_updated`,`session_expire`, `user_salt`) " .
            " VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');",
            S_TABLE_SESSIONS,
            session_id(),
            ($user_id = $session->getValue('user_id'))
                ? ANONYMOUS
                : (int)$user_id,
            serialize($session->getCache()),
            Utils::getUserAgent(),
            Utils::getUserIp(),
            time(),
            $session->getExpirationTime(),
            substr(md5(Utils::getUserAgent()), 0, 10)
        );

        return Db::query($query);
//        var_dump($x);die();
//
//        $dbAdapter = $this->getDbAdapter();
//        $oSQL = new Sql($dbAdapter);
//        $insert = $oSQL->insert(S_TABLE_SESSIONS);
//        $newData = array(
//            'session_id' => session_id(),
//            'user_id' => ($user_id = $session->getValue('user_id'))
//                    ? ANONYMOUS
//                    : (int)$user_id,
//            'session_data' => serialize($session->getCache()),
//            'user_agent' => Utils::getUserAgent(),
//            'user_ip' => Utils::getUserIp(),
//            'session_updated' => time(),
//            'session_expire' => $session->getExpirationTime(),
//            'user_salt' => substr(md5(Utils::getUserAgent()), 0, 10)
//        );
//
//        $insert->values($newData);
//
//        $selectString = $oSQL->getSqlStringForSqlObject($insert);
//        $results = $dbAdapter->query($selectString, $dbAdapter::QUERY_MODE_EXECUTE);
//
//        return $results->count();
        //	var_dump($insert->getSqlString($this->getDbAdapter()->getPlatform()));

    }


    /**
     * Clean existing sessions by timestamp limit (1hour as default).
     * Remove (delete) all not used sessions from the database.
     * @param integer $limit
     * @return \Zend\Db\Adapter\Driver\StatementInterface|\Zend\Db\ResultSet\ResultSet
     */
    public function cleanSessions($limit = 3600)
    {
        $sql = sprintf("DELETE FROM %s WHERE session_expire < '%s';",
            S_TABLE_SESSIONS,
            time() - $limit
        );
        Db::query($sql);
        return mysqli_affected_rows(Db::getConnection());
//        $dbAdapter = $this->getDbAdapter();
//
//        $sql = new Sql($dbAdapter);
//        $delete = $sql->delete(S_TABLE_SESSIONS);
//        $delete->where(array('session_expire < ?' => time() - $limit));
//
//        $deleteString = $sql->getSqlStringForSqlObject($delete);
//        $results = $dbAdapter->query($deleteString, $dbAdapter::QUERY_MODE_EXECUTE);
//        return $results;
    }


    /**
     * Update session data in database.
     * @param Class_Session_Abstract|SessionAbstract $session
     * @throws Exception\SessionException
     */
    public function updateSession(SessionAbstract $session)
    {
        $userId = $session->getValue('user_id');

        $sql = sprintf("UPDATE %s SET session_data = '%s', user_id = %d
                            WHERE session_id = '%s'
                            AND user_ip = '%s'
                            AND user_salt = '%s';"
            , S_TABLE_SESSIONS
            , serialize($session->getCache())
            , (empty($userId))
                ? ANONYMOUS
                : (int)$userId
            , session_id()
            , Utils::getUserIp()
            , substr(md5(Utils::getUserAgent()), 0, 10)
        );

        $this->getDbAdapter()->query($sql);
    }


    /**
     * Delete session from the database.
     * @return boolean
     */
    public function deleteSession()
    {
        $sql = sprintf("DELETE FROM %s WHERE session_id = '%s'"
            , S_TABLE_SESSIONS
            , session_id()
        );

        $result = $this->getDbAdapter()->query($sql)->rowCount();
        return ($result)
            ? TRUE
            : FALSE;
    }


    public function getSessionResource()
    {
        $sql = sprintf("SELECT * FROM %s WHERE session_id = '%s'"
            , S_TABLE_SESSIONS
            , session_id()
        );

        $data = $this->getDbAdapter()->fetchRow($sql);

        if ($data) {
            return $data;
        } else {
            return NULL;
        }
    }


}
