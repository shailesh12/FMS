<?php

return array(
    'controllers' => array(
        'invokables' => array(
            'Report\Controller\PurchaseReport' => 'Report\Controller\PurchaseReportController',
        ),
    ),
    // The following section is new and should be added to your file
    'router' => array(
        'routes' => array(
            'report' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/report[/:action][/:id][/:qid][/page/:page][/map_id/:map_id][/order_by/:order_by][/:order][/filter_by/:filter_by][/:filter]',
                    'constraints' => array(
                        'action' => '(?!\bpage\b)(?!\border_by\b)[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                        'order_by' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'order' => 'ASC|DESC',
                    ),
                    'defaults' => array(
                        'controller' => 'Report\Controller\PurchaseReport',
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
            'report' => __DIR__ . '/../view',
        ),
    ),
    'template_map' => array(
        'totalpurchase' => __DIR__ . '/../view/report/purchase-report/totalpurchase.phtml',
        'preparechartpu' => __DIR__ . '/../view/report/purchase-report/preparechartpu.phtml',
    ),
);
?>