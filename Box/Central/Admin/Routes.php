<?php

return array(
    array(
        '/sites/$',
        array(
            'controller' => "wow",
            'action' => 'getSitesBox',
            'label' => 'Sites',
            'base' => 'sites',
            'params' => array(
                'action' => 1,
            )
        )),
    array(
        '/menu/$',
        array(
            'controller' => "wow",
            'action' => 'getMenuBox',
            'label' => 'Menu',
            'base' => 'menu',
            'params' => array(
                'patch' => 2,
            )
        )),
    array(
        '/sections/$',
        array(
            'controller' => "wow",
            'action' => 'getSectionsBox',
            'params' => array(
                'patch' => 2,
            )
        )),
    array(
        '/articles/$',
        array(
            'controller' => "wow",
            'action' => 'getArticlesBox',
            'params' => array(
                'patch' => 2,
            )
        )),
    array(
        '/boxes/$',
        array(
            'controller' => "admin",
            'action' => 'getBoxesBox',
            'params' => array(
                'patch' => 2,
            )
        )),
    array(
        '/locales/$',
        array(
            'controller' => "admin",
            'action' => 'getLabelsBox',
            'params' => array(
                'patch' => 2,
            )
        )),
    array(
        '/users/$',
        array(
            'controller' => "admin",
            'action' => 'getUsersBox',
            'params' => array(
                'patch' => 2,
            )
        )),
    array(
        '/groups/$',
        array(
            'controller' => "admin",
            'action' => 'getGroupsBox',
            'params' => array(
                'patch' => 2,
            )
        )),
    array(
        '/logs/$',
        array(
            'controller' => "admin",
            'action' => 'getLogsBox',
            'params' => array(
                'patch' => 2,
            )
        )),

);

