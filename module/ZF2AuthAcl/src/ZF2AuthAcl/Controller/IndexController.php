<?php

namespace ZF2AuthAcl\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use ZF2AuthAcl\Form\LoginForm;
use ZF2AuthAcl\Form\Filter\LoginFilter;
use ZF2AuthAcl\Utility\UserPassword;
use Zend\Session\Container;
use ZF2AuthAcl\Form\RecoverPasswordForm;
use ZF2AuthAcl\Model\User;
use Zend\Mail\Message;
use Zend\Mail\Transport\Smtp as SmtpTransport;
use Zend\Mail\Transport\SmtpOptions;
use Zend\Authentication\Adapter\DbTable;
use ZF2AuthAcl\Model\RecoverEmail;
use ZF2AuthAcl\Model\RecoverEmailTable;
use ZF2AuthAcl\Model\UserTable;

class IndexController extends AbstractActionController {

    protected $dbAdapter;
    protected $adapter;
    protected $RecoverEmailTable;
    protected $UserTable;

    public function getAdapter() {
        if (!$this->adapter) {
            $sm = $this->getServiceLocator();
            $this->adapter = $sm->get('Zend\Db\Adapter\Adapter');
        }
        return $this->adapter;
    }

    public function getRecoverEmailTable() {
        if (!$this->RecoverEmailTable) {
            $sm = $this->getServiceLocator();
            $this->RecoverEmailTable = $sm->get('ZF2AuthAcl\Model\RecoverEmailTable');
        }
        return $this->RecoverEmailTable;
    }

    public function getUserTable() {
        if (!$this->UserTable) {
            $sm = $this->getServiceLocator();
            $this->UserTable = $sm->get('ZF2AuthAcl\Model\UserTable');
        }
        return $this->UserTable;
    }

    public function indexAction() {
        $request = $this->getRequest();
        $view = new ViewModel();
        $loginForm = new LoginForm('loginForm');
        $loginForm->setInputFilter(new LoginFilter());

        if ($request->isPost()) {
            $data = $request->getPost();
            $loginForm->setData($data);

            if ($loginForm->isValid()) {
                $data = $loginForm->getData();

                // DELETE MULTIPLE SESSIONS
                //$this->getUserTable()->deleteMultipleSessions($data['email']);
                // DELETE MULTIPLE SESSIONS


                $userPassword = new UserPassword();
                $encyptPass = $userPassword->create($data['password']);
                $authService = $this->getServiceLocator()->get('AuthService');
                $authService->getAdapter()
                        ->setIdentity($data['email'])
                        ->setCredential($encyptPass);

                $result = $authService->authenticate();
                if ($result->isValid()) { 
                    $userDetails = $this->_getUserDetails(array(
                        'user.email' => $data['email']
                            ), array(
                        'id', 'status'
                    ));
                    if ($userDetails[0]['status'] == 'Y') {
                        $session = new Container('User');
//                        if ($userDetails[0]['role_code'] == 'ca' && $userDetails[0]['center_id'] != '') {
//                            $session->offsetSet('center_id', $userDetails[0]['center_id']);
//                            $session->offsetSet('center_code', $userDetails[0]['center_code']);
//                            $session->offsetSet('center_type', $userDetails[0]['center_type']);
//                        }
                        $session->offsetSet('email', $data['email']);
                        $session->offsetSet('userId', $userDetails[0]['id']);
                        $session->offsetSet('roleId', $userDetails[0]['role_id']);
                        $session->offsetSet('roleName', $userDetails[0]['role_name']);
                        $session->offsetSet('roleCode', $userDetails[0]['role_code']);
//                        $session->offsetSet('fname', $userDetails[0]['fname']);
//                        $session->offsetSet('lname', $userDetails[0]['lname']);
                        //$this->getServiceLocator()->get('Zend\Log')->info('Login Successful for user ' . $data['email']);
                        // Redirect to page after successful login                        
                        return $this->redirect()->toRoute('application', array());
                    } else {
                        $this->flashMessenger()->addMessage(array(
                            'error' => 'You are not authorized to login'
                        ));
                        // Redirect to page after login failure
                    }
                } else {
                    $this->flashMessenger()->addMessage(array(
                        'error' => 'invalid credentials.'
                    ));
                    // Redirect to page after login failure
                }
                return $this->redirect()->toRoute('login', array());
                // Logic for login authentication
            } else {
                $errors = $loginForm->getMessages();
                // prx($errors);
            }
        } else {
            if (isset($_SERVER['HTTP_REFERER'])) {
                $action = explode('/', $_SERVER['HTTP_REFERER']);
                $status = $this->params()->fromRoute('hash', 0);
                if ($status === 'updated') {
                    //echo $status; die;
                    if (in_array('resetpassword', $action)) {
                        if (in_array('login', $action)) {
                            $successMessage = 'Password reset successfully, Please login using new password.';
                        } else {
                            $successMessage = 'Password has been changed successfully, Please re-login using new password.';
                        }
                    } else if (in_array('activate', $action)) {
                        $successMessage = 'Password created successfully, Please login using new password.';
                    } else if (in_array('recoverpassword', $action)) {
                        $successMessage = 'Password has been changed successfully, Please re-login using new password.';
                    }
                }
            }
        }
        if (isset($successMessage)) {
            $view->setVariable('successMessage', $successMessage);
        }
        $view->setVariable('loginForm', $loginForm);
        return $view;
    }

    public function logoutAction() {
        $authService = $this->getServiceLocator()->get('AuthService');
        $session = new Container('User');
        $session->getManager()->destroy();
        $authService->clearIdentity();
        return $this->redirect()->toRoute('login', array());
    }

    public function welcomeAction() {
        $session = new Container('User');
        //$username = $session->offsetGet('username');
        $userDetails = $this->_getUserDetails(array(
            //'username' => $username
                ), array(
            'id', 'email','role_code'
        ));
        return new ViewModel(array('results' => $userDetails));
    }

    private function _getUserDetails($where, $columns) {

        $userTable = $this->getServiceLocator()->get("UserTable");
        $users = $userTable->getUsers($where, $columns);
        return $users;
    }

    /**
     * Function to send recover password email link to reset password.
     */
    public function recoverlinkAction() {

        $errNo = 0;
        $request = $this->getRequest();
        $form = new RecoverPasswordForm();

        if ($request->isPost()) {
            $recoveryData = $request->getPost();
            $email = $recoveryData['email'];
            if ($email != '') {
                $validator = new \Zend\Validator\EmailAddress();
                if ($validator->isValid($email)) {
                    // email appears to be valid
                    // check existence of email in UserTable

                    $validatorExist = new \Zend\Validator\Db\NoRecordExists(
                            array(
                        'table' => 'users',
                        'field' => 'email',
                        'adapter' => $this->getAdapter()
                            )
                    );

                    if ($validatorExist->isValid($email)) {
                        $errNo = 3; // Email not in DB  
                    } else {
                        // check email address is active
                        $validEmail = $this->getUserTable()->checkActiveEmail($email);
                        if ($validEmail[0]['status'] == 'N') {
                            $errNo = 5;
                        } else if ($validEmail[0]['status'] == 'Y') {
                            $errNo = 4; // Email in DB. send email link to user. 
                            // delete the record of email from iyc_recover 
                            $this->getRecoverEmailTable()->deleteRecoverEmail($email);
                            // add new record
                            $hashValueReturn = $this->getRecoverEmailTable()->addRecoverEmail($email);
                            // send link to recover password
                            $this->sendRecoverEmail($hashValueReturn, $email);
                        } else {
                            $errNo = 3;
                        }
                    }
                } else {
                    $errNo = 2; // email invalid Error
                }
            } else {
                $errNo = 1; // blank data Error
            }
        }

        return array('form' => $form, 'errRecVar' => $errNo);
    }

    /**
     * Function to send recove link to user to update password
     * @param type $hashValueReturn
     * @param type $email
     */
    public function sendRecoverEmail($hashValueReturn, $email) {

        $renderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
        $ref_file_name = explode('login', $_SERVER['HTTP_REFERER']);
        $requesturi = $this->getRequest()->getUri();
        $this->baseUrl = $requesturi->getHost() . $renderer->basePath();
        $url = $ref_file_name[0] . 'login/recoverpassword/' . $hashValueReturn;
        $emailData = array('email' => $email, 'url' => $url);
        $content = $renderer->render('zf2-auth-acl/index/tpl/recovertpl', array('emailData' => $emailData));
        //subject of the email
        $subject = 'FMS Reset password';
        //body of the email
        //$reciever_message = "User $email\n\n 
        //                     You have sent us a request for your password reset...\n\n";
        //$reciever_message .= "Please go to $url to reset your password";
        $this->getRecoverEmailTable()->sendEmailToUser($subject, $content, $email);
    }

    /**
     * Functio to reset password
     * @return type
     */
    public function recoverpasswordAction() {
        $errNo = 0;
        $request = $this->getRequest();
        $form = new RecoverPasswordForm();
        $recoverObj = new RecoverEmail();
        $hash_value = $this->params()->fromRoute('hash', 0);

        $validatorExist = new \Zend\Validator\Db\NoRecordExists(
                array(
            'table' => 'recover',
            'field' => 'hash_value',
            'adapter' => $this->getAdapter()
                )
        );
        if ($validatorExist->isValid($hash_value)) {
            $errNo = 1; // hash not in DB  
            //$form->setMessages(array(array('password' => 'expired')));
            $form->get('submit')->setAttributes(array('disabled' => 'disabled', 'class' => 'disable-btn big-btn green-btn'));
            $form->get('password')->setAttributes(array('disabled' => 'disabled',));
            $form->get('repassword')->setAttributes(array('disabled' => 'disabled',));
//            $this->flashMessenger()->addMessage(array(
//                'error' => 'link is either expired/used'
//            ));
//            return $this->redirect()->toUrl($this->request->getBasePath() . '/');
        }

        if ($request->isPost() && $errNo == 0) {
            $form->setInputFilter($recoverObj->getInputFilter());
            $form->getInputFilter()->get('oldpassword')->setRequired(false);
            $form->getInputFilter()->get('oldpassword')->setAllowEmpty(true);
            $form->setData($request->getPost());
            //if ($form->isValid()) {
            $Data = $request->getPost();
            $EmailReturn = $this->getRecoverEmailTable()->getEmailbyHash($hash_value);
            if ($EmailReturn->email != '') {
                $this->getUserTable()->updateRecoveryPassword($EmailReturn->email, $Data['password']);
                $errNo = 2; // password updated success
//                    $this->flashMessenger()->addMessage(array(
//                        'error' => 'Password has been changed successfully.'
//                    ));
                $this->getRecoverEmailTable()->deleteRecoverEmail($EmailReturn->email);
                return $this->redirect()->toUrl('../../login/index/updated');
            }
            //}
        }

        return array('form' => $form, 'errRecVar' => $errNo);
    }

    /**
     * Functio to set password for first time user
     * @return type
     */
    public function activateAction() {
        $errNo = 0;
        $request = $this->getRequest();
        $form = new RecoverPasswordForm();
        $recoverObj = new RecoverEmail();
        $hash_value = $this->params()->fromRoute('hash', 0);
        $validatorExist = new \Zend\Validator\Db\NoRecordExists(
                array(
            'table' => 'activation',
            'field' => 'hash_value',
            'adapter' => $this->getAdapter()
                )
        );
        $activationRow = $this->getRecoverEmailTable()->getActivateRowbyHash($hash_value);

        if (isset($activationRow[0]['requested_on'])) {
            $requestedOn = strtotime($activationRow[0]['requested_on']) + 259200;
            $currentTime = time();
            if ($currentTime > $requestedOn) {
                if (isset($activationRow[0]['email'])) {
                    $this->getRecoverEmailTable()->deleteActivationEmail($activationRow[0]['email']);
                }
            }
        }

        $form->get('submit')->setAttributes(array('value' => 'Activate',));
        if ($validatorExist->isValid($hash_value)) {
            $errNo = 1; // hash not in DB
            $form->get('submit')->setAttributes(array('disabled' => 'disabled', 'class' => 'disable-btn big-btn green-btn'));
            $form->get('password')->setAttributes(array('disabled' => 'disabled',));
            $form->get('repassword')->setAttributes(array('disabled' => 'disabled'));
            $this->flashMessenger()->setNamespace('error')->addMessage('Activation link is either expired/used');
        } else {
            $this->flashMessenger()->clearMessagesFromContainer();
        }
        if ($request->isPost() && $errNo == 0) {
            $form->setData($request->getPost());
            $form->getInputFilter()->get('password')->setRequired(true);
            $form->getInputFilter()->get('password')->setAllowEmpty(false);
            $form->getInputFilter()->get('repassword')->setRequired(true);
            $form->getInputFilter()->get('repassword')->setAllowEmpty(false);
            if ($form->isValid()) {
                $Data = $request->getPost();
                $EmailReturn = $this->getRecoverEmailTable()->getActivationEmailbyHash($hash_value);
                if ($EmailReturn[0]['email'] != '') {
                    $this->getUserTable()->updateActivationPassword($EmailReturn[0]['email'], $Data['password']);
                    $errNo = 2; // password updated success
//                    $this->flashMessenger()->addMessage(array(
//                        'error' => 'Password has been changed successfully.'
//                    ));
                    $this->getRecoverEmailTable()->deleteActivationEmail($EmailReturn[0]['email']);
//                    $this->flashMessenger()->setNamespace('success')->addMessage('Password has been changed successfully.');
                    return $this->redirect()->toUrl('../../login/index/updated');
                }
            }
        }
        return array('form' => $form, 'errRecVar' => $errNo);
    }

    /**
     * SINGLE SESSION PER USER CODE
     */
    public function checksinglesessionAction() {

        // SINGLE SESSION CODE
        $this->layout('layout/ajax');
        $email_ss = $_REQUEST['email'];
        $result_count_session_user = $this->getUserTable()->singleSessionLoginCheck($email_ss);
        echo $result_count_session_user;
        die;
        // SINGLE SESSION CODE ENDS
    }

}
