<?php
/**
 * 用户登录,注册等接口
 *
 * @fileName: UserController.php
 * @author: dongyong<dongyong.ydy@alibaba-inc.com>
 * @license: http://www.phpwind.com
 * @version: $Id
 * @lastchange: 2014-12-15 19:10:43
 * @desc: 
 **/
defined('WEKIT_VERSION') || exit('Forbidden');

Wind::import('SRV:user.srv.PwRegisterService');
Wind::import('SRV:user.srv.PwLoginService');
Wind::import('APPS:native.controller.MobileBaseController');

class UserController extends MobileBaseController {

	public function beforeAction($handlerAdapter) {
		parent::beforeAction($handlerAdapter);
//		if (!$this->loginUser->isExists()) $this->showError('VOTE:user.not.login');
	}
	
	public function run() {


    }

    /**
     * 登录;并校验验证码
     * @access public
     * @return string
     * @example
     <pre>
     /index.php?m=mobile&c=user&a=doLogin <br>
     post: username&password&csrf_token&code&_json=1 <br>
     response: 
    {
        "referer": "",
            "refresh": false,
            "state": "fail",
            "message": [
                "帐号不存在"

            ],
        "__error": ""
    }
     </pre>
     */
    public function doLoginAction(){
        
        list($username, $password, $code) = $this->getInput(array('username', 'password', 'code'));

        if (empty($username) || empty($password)) $this->showError('USER:login.user.require');

        //
        if( $this->_showVerify() ){
            $veryfy = $this->_getVerifyService();                                                                                                         
            if ($veryfy->checkVerify($code) !== true) {
                $this->showError('USER:verifycode.error');
            }        
        }

        /* [验证用户名和密码是否正确] */
        $login = new PwLoginService();
        $this->runHook('c_login_dorun', $login);

        $isSuccess = $login->login($username, $password, $this->getRequest()->getClientIp(), $question='', $answer='');
        if ($isSuccess instanceof PwError) {
            $this->showError($isSuccess->getError());

        }
        $config = Wekit::C('site');
        if ($config['windid'] != 'local') {
            $localUser = $this->_getUserDs()->getUserByUid($isSuccess['uid'], PwUser::FETCH_MAIN);
            if ($localUser['username'] && $userForm['username'] != $localUser['username']) $this->showError('USER:user.syn.error');

        }   

        Wind::import('SRV:user.srv.PwRegisterService');
        $registerService = new PwRegisterService();
        $info = $registerService->sysUser($isSuccess['uid']);

        if (!$info)  $this->showError('USER:user.syn.error');

        //success
        $this->showMessage('USER:login.success');
    }

    /**
     * 注册帐号 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=mobile&c=user&a=doRegister    <br>
     post: username&password&repassword&email&code 
     response: {err:"",data:""} 
     </pre>
     */
    public function doRegisterAction(){
        list($username,$password,$email,$code) = $this->getInput(array('username','password','email','code'));

        //  验证输入
        Wind::import('Wind:utility.WindValidator');
        $config = $this->_getRegistConfig();
        if (!$username) $this->showError('USER:user.error.-1');
        if (!$password) $this->showError('USER:pwd.require');
        if (!$email) $this->showError('USER:user.error.-6');
        if (!WindValidator::isEmail($email)) $this->showError('USER:user.error.-7');
	
		foreach ($config['active.field'] as $field) {
			if (!$this->getInput($field, 'post')) $this->showError('USER:register.error.require.needField.' . $field);
		}
		if ($config['active.check'] && !$regreason) {
			$this->showError('USER:register.error.require.regreason');
		}

        if( $this->_showVerify() ){
            $veryfy = $this->_getVerifyService();                                                                                                         
            if ($veryfy->checkVerify($code) !== true) {
                $this->showError('USER:verifycode.error');
            }        
        }

        Wind::import('SRC:service.user.dm.PwUserInfoDm');
        $userDm = new PwUserInfoDm();
        $userDm->setUsername($username);
        $userDm->setPassword($password);
        $userDm->setEmail($email);
        $userDm->setRegdate(Pw::getTime());
        $userDm->setLastvisit(Pw::getTime());
        $userDm->setRegip(Wind::getComponent('request')->getClientIp());

        $userDm->setAliww($aliww);
        $userDm->setQq($qq);
        $userDm->setMsn($msn);
        $userDm->setMobile($mobile);
        $userDm->setMobileCode($mobileCode);
        $userDm->setQuestion($question, $answer);
        $userDm->setRegreason($regreason);

        $areaids = array($hometown, $location);
        if ($areaids) {
            $srv = WindidApi::api('area');
            $areas = $srv->fetchAreaInfo($areaids);
            $userDm->setHometown($hometown, isset($areas[$hometown]) ? $areas[$hometown] : '');
            $userDm->setLocation($location, isset($areas[$location]) ? $areas[$location] : '');
        }

        //
		$registerService = new PwRegisterService();
		$registerService->setUserDm( $userDm );
		/*[u_regsiter]:插件扩展*/
		$this->runHook('c_register', $registerService);
		if (($info = $registerService->register()) instanceof PwError) {
			$this->showError($info->getError());
		} else {
			$identity = PwRegisterService::createRegistIdentify($info['uid'], $info['password']);
			if (1 == Wekit::C('register', 'active.mail')) {
                $this->showMessage('USER:active.sendemail.success');
			} else {
                $this->showMessage('USER:register.success');
			}
		}
    }


    /**
     * 开放帐号登录; (通过第三方开放平台认证通过后,获得的帐号id在本地查找是否存在,如果存在登录成功 ) 
     * 
     * @access public
     * @return string sessionid
     * @example
     <pre>
     post:
     $this->_checkAccountValid() +  <br>
     </pre>
     */
    public function openAccountLoginAction(){
        if( $accountData=$this->_checkAccountValid() ){

            $accountData['account'] = '9299134';
            $accountData['type'] = 'qq';

            $accountRelationData = $this->_getUserOpenAccountDs()->getUid($accountData['account'],$accountData['type']);

            //
            $isSuccess = Wekit::load('user.PwUser')->getUserByUid($accountRelationData['uid'], PwUser::FETCH_MAIN);
            $username = $isSuccess['username'];
            $password = $isSuccess['password'];


            /* [验证用户名和密码是否正确] */
            $login = new PwLoginService();
            $this->runHook('c_login_dorun', $login);

            Wind::import('SRV:user.srv.PwRegisterService');
            $registerService = new PwRegisterService();
            $info = $registerService->sysUser($isSuccess['uid']);

            if (!$info)  $this->showError('USER:user.syn.error');

            //success
            $this->showMessage('USER:login.success');
        }
    }

    /**
     * 
     * 开放帐号注册到本系统内
     *
     * @access public
     * @return void
     * @example
     <pre>
     post:
     $this->_checkAccountValid() +  <br>
     &username&password&email&code 
     </pre>
     */
    public function openAccountRegisterAction() {
        if( $accountData=$this->_checkAccountValid() ){
            
            list($username,$password,$email) = $this->getInput(array('username','password','email'));

            Wind::import('SRC:service.user.dm.PwUserInfoDm');
            $userDm = new PwUserInfoDm();
            $userDm->setUsername($username);
            $userDm->setPassword($password);
            $userDm->setEmail($email);
            $userDm->setRegdate(Pw::getTime());
            $userDm->setLastvisit(Pw::getTime());
            $userDm->setRegip(Wind::getComponent('request')->getClientIp());

            //
            $registerService = new PwRegisterService();
            $registerService->setUserDm( $userDm );
            /*[u_regsiter]:插件扩展*/
            $this->runHook('c_register', $registerService);
            if (($info = $registerService->register()) instanceof PwError) {
                $this->showError($info->getError());
            } else {
                if( $this->_getUserOpenAccountDs()->addUser($info['uid'],$accountData['account'],$accountData['type'])==false ){
                    $this->showMessage('USER:register.success');
                }else{
                    $this->showError('NATIVE:error.openaccount.exist');
                }
            }
        //
        }
    }

    /**
     * 修改头像 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=mobile&c=user&a=doAvatar <br>
     post: sessionid
     postdata: avatar
     response: {err:"",data:""}
     </pre>
     */
    public function doAvatarAction(){
        
    }

    /**
     * 修改性别 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=mobile&c=user&a=doSex <br>
     post: sessionid&gender
     response: {err:"",data:""}
     </pre>
     */
    public function doSexAction(){
        
    }

    /**
     * 保存修改密码 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=mobile&c=user&a=doPassWord <br>
     post: sessionid&oldpassword&newpassword&repassword
     response: {err:"",data:""}
     </pre>
     */
    public function doPassWordAction(){

    }

    /**
     * 退出登录 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=mobile&c=user&a=doLoginOut <br>
     response: {err:"",data:""}
     </pre>
     */
    public function doLoginOutAction(){

    }

    /**
     * 显示验证码 
     * 
     * @access public
     * @return void
     */
    public function showVerifycodeAction(){
    
    }


    /**
     * 是否需要显示验证码 
     * 
     * @access public
     * @return boolean
     * @example
     * <pre>  
     /index.php?m=mobile&c=user&a=ifshowVerifycode <br>
    { "err":"","data":true }
    </pre>
     */
    public function ifShowVerifycodeAction(){
        $this->setOutput($this->_showVerify(), 'display');
        $this->showMessage('success');
    }

    /**
     * 判断是否需要展示验证码
     * 
     * @return boolean
     */
    private function _showVerify() {
        return $result = false;
        //
        $config = Wekit::C('verify', 'showverify');
        !$config && $config = array();
        if(in_array('userlogin', $config)==true){
            $result = true;
        }else{
            //ip限制,防止撞库; 错误三次,自动显示验证码
            $ipDs = Wekit::load('user.PwUserLoginIpRecode');
            $info = $ipDs->getRecode($this->getRequest()->getClientIp());
            $result = is_array($info) && $info['error_count']>3 ? true : false;
        }
        return $result;
    }


    /**
     * 校验开放平台帐号合法性
     *
     * @access public
     * @return void
     * @example
     <pre>
     post: <br>
       openid (与APP通信的用户key) <br> 
     & openkey (session key) <br>
     & appid <br>
     & sig (请求串的签名) <br>
     & pf 应用的来源平台 <br>
     & accountType (来自那个开放平台-微信/qq/淘宝/微博)
     response: 
     </pre>
     */
    protected function _checkAccountValid(){
        // test
        return array(
            'account'=>mt_rand(100000,10000000),
            'type'=>'qq',
        );


        //
        $_configParser = Wind::getComponent('configParser');
        $_openAccountApiConf = Wind::getRealPath('APPS:mobile.conf.openaccountapi.php', true);

        //
        list($openid,$openkey,$appid,$sig,$pf,$accountType) = $this->getInput(array('openid','openkey','appid','sig','pf','accountType'));
        if( empty($openid) || empty($openkey) || empty($appid) || empty($sig) || empty($pf) || empty($accountType)  ){
            $this->showError('NATIVE:error.openaccount.args');
        }
        //
        $apis = $_configParser->parse($_openAccountApiConf);
        if( isset($apis['accountType']) ){
            $this->showError('NATIVE:error.openaccount.type');
        }

    }


    /**
     * 开放平台帐号关联ds
     * 
     * @access private
     * @return void
     */
    private function _getUserOpenAccountDs() {
        return Wekit::load('native.PwOpenAccount');

    }   


}