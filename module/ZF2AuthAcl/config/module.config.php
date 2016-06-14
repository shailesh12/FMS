<?php

return array(
    'controllers' => array(
        'invokables' => array(
            'ZF2AuthAcl\Controller\Index' => 'ZF2AuthAcl\Controller\IndexController',
        ),
    ),
    // The following section is new and should be added to your file
    'router' => array(
        'routes' => array(
            'login' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/login[/:action][/:hash]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[a-zA-Z0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'ZF2AuthAcl\Controller\Index',
                        'action' => 'index',
                    ),
                ),
            ),
        )
    ),
    'view_manager' => array(
        'template_map' => array(
            'zf2-auth-acl/index/index' => __DIR__ . '/../view/zf2-auth-acl/index/index.phtml'
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view'
        ),
        'display_not_found_reason' => true,
        'display_exceptions' => true,
    )
);
