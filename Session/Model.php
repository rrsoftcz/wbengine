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

use Wbengine\Db;
use Wbengine\Model\ModelAbstract;
use Wbengine\Session;
use Wbengine\Session\SessionData;

class Model extends ModelAbstract
{

    /**
     * Return session data db row as object ...
     * @param Session $session
     * @return null|\stdClass
     */
    public function getSessionData(Session $session)
    {
        $query = sprintf("/**@lang text*/
                        SELECT id,user_id,session_id,session_data,user_agent,user_ip,session_updated,session_expire,user_salt FROM %s s
                        WHERE s.session_id = '%s'
                        AND s.user_ip = '%s'
                        AND s.user_salt = '%s'
                        LIMIT 1;"
            , S_TABLE_SESSIONS
            , $session->getSessionId()
            , $session->getUserIp()
            , $session->getUserSalt()
        );
        return Db::fetchObject($query);
    }


    /**
     * Insert session's data to database.
     * @param Session $session
     * @return bool
     */
    public function insertSessionData(Session $session)
    {
        $query = sprintf("INSERT INTO %s " .
            " (`session_id`, `user_id`, `session_data`, `user_agent`, `user_ip`, `session_updated`,`session_expire`, `user_salt`) " .
            " VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');",
            S_TABLE_SESSIONS,
            $session->getSessionId(),
            $session->getUserId(),
            serialize($session->getSessionData()),
            $session->getUserAgent(),
            $session->getUserIp(),
            $session->getSessionLastUpdated(),
            $session->getSessionExpireTime(),
            $session->getUserSalt()
        );
        return Db::query($query);
    }


    public function cleanSessions($limit = 3600)
    {
        $sql = sprintf("DELETE FROM %s WHERE session_expire < '%s';",
            S_TABLE_SESSIONS,
            time() - $limit
        );
        Db::query($sql);
        return mysqli_affected_rows(Db::getConnection());
    }


    /**
     * Update session data ...
     * @param Session $session
     * @return bool
     */
    public function updateSession(Session $session)
    {
        $query = sprintf("UPDATE %s SET session_data = '%s'
                            WHERE session_id = '%s'
                            AND user_ip = '%s'
                            AND user_salt = '%s';"
            , S_TABLE_SESSIONS
            , serialize($session->getSessionData())
            , $session->getSessionId()
            , $session->getUserIp()
            , $session->getUserSalt()
        );

        return Db::query($query);
    }


    /**
     * Delete session from the database.
     * @param $session_id string
     * @return bool
     */
    public function deleteSession($session_id)
    {
        $sql = sprintf("DELETE FROM %s WHERE session_id = '%s'"
            , S_TABLE_SESSIONS
            , $session_id
        );

        return Db::query($sql);
    }
}
