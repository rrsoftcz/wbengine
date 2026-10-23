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
        $query = sprintf("
                        SELECT user_id,session_id,session_data,user_agent,user_ip,session_updated,session_expire,user_salt FROM %s s
                        WHERE s.session_id = ?
                        AND s.user_ip = ?
                        AND s.user_salt = ?
                        LIMIT 1;"
            , S_TABLE_SESSIONS
        );

        return Db::fetchObject($query, array(
            $session->getSessionId(),
            $session->getUserIp(),
            $session->getUserSalt()
        ));
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
            " VALUES (?, ?, ?, ?, ?, ?, ?, ?);",
            S_TABLE_SESSIONS
        );
        return Db::query($query, array(
            $session->getSessionId(),
            $session->getUserId(),
            serialize($session->getSessionData()),
            $session->getUserAgent(),
            $session->getUserIp(),
            $session->getSessionLastUpdated(),
            $session->getSessionExpireTime(),
            $session->getUserSalt()
        ));
    }


    public function cleanSessions($limit = 3600)
    {
        $sql = sprintf("DELETE FROM %s WHERE session_expire < ?;", S_TABLE_SESSIONS);
        Db::query($sql, array(time() - $limit));
        return Db::getAffected();
    }


    /**
     * Update session data ...
     * @param Session $session
     * @return bool
     */
    public function updateSession(Session $session)
    {
        $query = sprintf("UPDATE %s SET session_data = ?, user_id = ?
                            WHERE session_id = ?
                            AND user_ip = ?
                            AND user_salt = ?;"
            , S_TABLE_SESSIONS
        );

        return Db::query($query, array(
            serialize($session->getSessionData()),
            (int)$session->getUserId(),
            $session->getSessionId(),
            $session->getUserIp(),
            $session->getUserSalt()
        ));
    }


    /**
     * Change the id of a stored session.
     * @param string $oldId
     * @param string $newId
     * @return bool
     */
    public function renameSession($oldId, $newId)
    {
        $sql = sprintf("UPDATE %s SET session_id = ? WHERE session_id = ?", S_TABLE_SESSIONS);

        return Db::query($sql, array((string)$newId, (string)$oldId));
    }


    /**
     * Delete session from the database.
     * @param $session_id string
     * @return bool
     */
    public function deleteSession($session_id)
    {
        $sql = sprintf("DELETE FROM %s WHERE session_id = ?", S_TABLE_SESSIONS);

        return Db::query($sql, array((string)$session_id));
    }
}
