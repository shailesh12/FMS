<?php

return array(
    'controllers' => array(
        'invokables' => array(
            'Purchase\Controller\Purchase' => 'Purchase\Controller\PurchaseController',
        ),
    ),
    // The following section is new and should be added to your file
    'router' => array(
        'routes' => array(
            'purchase' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/purchase[/:action][/:id][/:qid][/page/:page][/map_id/:map_id][/order_by/:order_by][/:order][/filter_by/:filter_by][/:filter]',
                    'constraints' => array(
                        'action' => '(?!\bpage\b)(?!\border_by\b)[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                        'order_by' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'order' => 'ASC|DESC',
                    ),
                    'defaults' => array(
                        'controller' => 'Purchase\Controller\Purchase',
                        'action' => 'index',
                    ),
                ),
            ),
        ),
    ),
//    'view_helpers' => array(
//        'invokables' => array(
//            'trainee_helper' => 'Trainee\View\Helper\HijriToGregorianConvert',
//        )
//    ),
    'view_manager' => array(
        'template_path_stack' => array(
            'purchase' => __DIR__ . '/../view',
        ),
    ),
);
