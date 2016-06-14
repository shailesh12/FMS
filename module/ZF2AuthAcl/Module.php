<?php

namespace ZF2AuthAcl;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Authentication\Adapter\DbTable as DbAuthAdapter;
use Zend\Session\Container;
use ZF2AuthAcl\Model\User;
use ZF2AuthAcl\Model\UserTable;
use ZF2AuthAcl\Model\UserRole;
use ZF2AuthAcl\Model\PermissionTable;
use ZF2AuthAcl\Model\ResourceTable;
use ZF2AuthAcl\Model\RolePermissionTable;
use ZF2AuthAcl\Model\RecoverEmail;
use ZF2AuthAcl\Model\RecoverEmailTable;
use Zend\Authentication\AuthenticationService;
use ZF2AuthAcl\Model\Role;
use ZF2AuthAcl\Utility\Acl;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;

class Module {

    public function onBootstrap(MvcEvent $e) {
        $eventManager = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        $application = $e->getApplication();
        try {
            $dbInstance = $application->getServiceManager()
                    ->get('Zend\Db\Adapter\Adapter');
            $dbInstance->getDriver()->getConnection()->connect();
        } catch (\Exception $ex) {
            $ViewModel = $e->getViewModel();
            $ViewModel->setTemplate('error/error');
        }
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, array(
            $this,
            'boforeDispatch'
                ), 100);
        $serviceManager = $e->getApplication()->getServiceManager();
        //$stream = @fopen('data/log/logfile.log', 'a', false);
        //$writer = new \Zend\Log\Writer\Stream($stream);
        $adapter = $serviceManager->get('Zend\Db\Adapter\Adapter');
        $mapping = array(
            'priority' => 'priority',
            'message' => 'message'
        );
        $writer = new \Zend\Log\Writer\Db($adapter, 'watchdog', $mapping);

        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $serviceManager->setService('Zend\Log', $logger);
    }

    function boforeDispatch(MvcEvent $event) {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $target = $event->getTarget();

        $whiteList = array(
            'ZF2AuthAcl\Controller\Index-index',
            'ZF2AuthAcl\Controller\Index-logout',
            'ZF2AuthAcl\Controller\Index-checksinglesession',
            'Cron\Controller\expirequalification',
            'Cron\Controller\fetchLaScore',
            'Cron\Controller\fetcheVolveScore',
        );

        //$requestUri = $request->getRequestUri();
        $controller = $event->getRouteMatch()->getParam('controller');

        $action = $event->getRouteMatch()->getParam('action');

        $requestedResourse = $controller . "-" . $action;

        $session = new Container('User');
        if ($session->offsetExists('email')) {
            if ($requestedResourse == 'ZF2AuthAcl\Controller\Index-index' || in_array($requestedResourse, $whiteList)) {
                $url = '/';
                $response->setHeaders($response->getHeaders()
                                ->addHeaderLine('Location', $url));
                $response->setStatusCode(302);
            } else {

                $serviceManager = $event->getApplication()->getServiceManager();
                $userRole = $session->offsetGet('roleName');

                $acl = $serviceManager->get('Acl');
                $acl->initAcl();

                $status = $acl->isAccessAllowed($userRole, $controller, $action);
                if (!$status) {
                    $viewModel = $event->getViewModel();
                    $viewModel->setTemplate('error/permission');
                }
            }
        } else {
            $url_pieces = explode("\\", $requestedResourse);
            if ($url_pieces[0] != 'ZF2AuthAcl' && !in_array($requestedResourse, $whiteList)) {
                if (method_exists($request, 'getbaseUrl')) {
                    $url = $request->getbaseUrl() . '/login';
                    /* Session Timeout Erorr handling globally */
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        header('Unauthorized Error', true, 401);
                        die;
                    } else {
                        $response->setHeaders($response->getHeaders()->addHeaderLine('Location', $url));
                        $response->setStatusCode(302);
                        header("Location: $url", true, 401);
                    }
                }
            }
            if (method_exists($request, 'sendHeaders')) {  // @Rashid-- commented because to redirect non ajax call to login after session out. 
                $response->sendHeaders();
            }
        }
    }

    public function getConfig() {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig() {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__
                )
            )
        );
    }

    public function getServiceConfig() {
        return array(
            'factories' => array(
                'AuthService' => function ($serviceManager) {
                    $adapter = $serviceManager->get('Zend\Db\Adapter\Adapter');
                    $dbAuthAdapter = new DbAuthAdapter($adapter, 'users', 'email', 'password');
                    $auth = new AuthenticationService();
                    $auth->setAdapter($dbAuthAdapter);
                    return $auth;
                },
                'Acl' => function ($serviceManager) {
                    return new Acl();
                },
                'UserTable' => function ($serviceManager) {
                    return new User($serviceManager->get('Zend\Db\Adapter\Adapter'));
                },
                'RoleTable' => function ($serviceManager) {
                    return new Role($serviceManager->get('Zend\Db\Adapter\Adapter'));
                },
                'UserRoleTable' => function ($serviceManager) {
                    return new UserRole($serviceManager->get('Zend\Db\Adapter\Adapter'));
                },
                'PermissionTable' => function ($serviceManager) {
                    return new PermissionTable($serviceManager->get('Zend\Db\Adapter\Adapter'));
                },
                'ResourceTable' => function ($serviceManager) {
                    return new ResourceTable($serviceManager->get('Zend\Db\Adapter\Adapter'));
                },
                'RolePermissionTable' => function ($serviceManager) {
                    return new RolePermissionTable($serviceManager->get('Zend\Db\Adapter\Adapter'));
                },
                'ZF2AuthAcl\Model\RecoverEmailTable' => function($sm) {
                    $tableGateway = $sm->get('RecoverEmailTableGateway');
                    $table = new RecoverEmailTable($tableGateway);
                    return $table;
                },
                'RecoverEmailTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new RecoverEmail());
                    return new TableGateway('recover', $dbAdapter, null, $resultSetPrototype);
                },
                'ZF2AuthAcl\Model\UserTable' => function($sm) {
                    $tableGateway = $sm->get('UserTableGateway');
                    $table = new UserTable($tableGateway);
                    return $table;
                },
                'UserTableGateway' => function ($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $resultSetPrototype = new ResultSet();
                    $resultSetPrototype->setArrayObjectPrototype(new RecoverEmail());
                    return new TableGateway('users', $dbAdapter, null, $resultSetPrototype);
                },
            )
        );
    }

}
