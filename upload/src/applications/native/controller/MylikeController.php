<?php
/**
 * 增加喜欢、删除喜欢接口
 *
 * @fileName: MylikeController.php
 * @author: yuliang<yuliang.lyl@alibaba-inc.com>
 * @license: http://www.phpwind.com
 * @version: $Id
 * @lastchange: 2014-12-16 19:08:17
 * @desc: 
 **/
defined('WEKIT_VERSION') || exit('Forbidden'); 

Wind::import('APPS:native.controller.MobileBaseController');

class MylikeController extends MobileBaseController {

	public function beforeAction($handlerAdapter) {
		parent::beforeAction($handlerAdapter);
        //if ($this->uid < 1) $this->forwardRedirect(WindUrlHelper::createUrl('u/login/run/'));
        $this->uid=3;
	}
        
	public function run() {
		$page = (int) $this->getInput('page', 'get');
		$tagid = (int) $this->getInput('tag', 'get');
		$perpage = 10;
		$page = $page > 1 ? $page : 1;
		$service = $this->_getBuildLikeService();
		$tagLists = $service->getTagsByUid($this->uid);
		if ($tagid > 0) {
			$resource = $this->_getLikeService()->allowEditTag($this->uid, $tagid);
			if ($resource instanceof PwError) $this->showError($resource->getError());
			$count = $resource['number'];
			$logids = $service->getLogidsByTagid($tagid, $page, $perpage);
			$logLists = $service->getLogLists($logids);
		} else {
			list($start, $perpage) = Pw::page2limit($page, $perpage);
			$count = $this->_getLikeLogDs()->getLikeCount($this->uid);
			$logLists = $service->getLogList($this->uid, $start, $perpage);
		}
		
		// start
		$json = array();
		foreach ($logLists AS $_log) {
			$_log['tags'] = array_unique((array)$_log['tags']);
			if (!$_log['tags']) continue;
			$tagJson = array();
			foreach ((array)$_log['tags'] AS $_tagid) {
				if (!isset($tagLists[$_tagid]['tagname'])) continue;
				$tagJson[] = array(
					'id'=>$_tagid,
					'value'=>$tagLists[$_tagid]['tagname'],
				);
			}
			$json[] = array(
				'id'=>$_log['logid'],
				'items'=>$tagJson,
			);
		}
		//end
		$likeLists = $service->getLikeList();
		$likeInfos = $service->getLikeInfo();
		$hotBrand = $this->_getLikeService()->getLikeBrand('day1', 0, 10, true);
		$args = $tagid > 0 ? array("tag" => $tagid) : array();
		$this->setOutput($args, 'args');
		$this->setOutput($logLists, 'logLists');
		$this->setOutput($likeLists, 'likeLists');
		$this->setOutput($likeInfos, 'likeInfos');
		$this->setOutput($tagLists, 'tagLists');
		$this->setOutput($hotBrand, 'hotBrand');
		$this->setOutput($count, 'count');
		$this->setOutput($page, 'page');
		$this->setOutput($perpage, 'perpage');
		$this->setOutput($json, 'likeJson');
		
		// seo设置
		Wind::import('SRV:seo.bo.PwSeoBo');
		$seoBo = PwSeoBo::getInstance();
		$lang = Wind::getComponent('i18n');
		$seoBo->setCustomSeo($lang->getMessage('SEO:like.mylike.run.title'), '', '');
		Wekit::setV('seo', $seoBo);
	}

	public function taAction() {
		// seo设置
		Wind::import('SRV:seo.bo.PwSeoBo');
		$seoBo = PwSeoBo::getInstance();
		$lang = Wind::getComponent('i18n');
		$seoBo->setCustomSeo($lang->getMessage('SEO:like.mylike.ta.title'), '', '');
		Wekit::setV('seo', $seoBo);
	}

	public function dataAction() {
		$page = (int) $this->getInput('page', 'get');
		$start = (int) $this->getInput('start', 'get');
		$start >= 100 && $start = 100;
		$perpage = 20;
		$_data = array();
		$logLists = $this->_getBuildLikeService()->getFollowLogList($this->uid, $start, $perpage);
		$likeLists = $this->_getBuildLikeService()->getLikeList();
		$likeInfos = $this->_getBuildLikeService()->getLikeInfo();
		$replyInfos = $this->_getBuildLikeService()->getLastReplyInfo();
		foreach ($logLists as $k => $logList) {
			if (!isset($likeInfos[$logList['likeid']])) continue;
			$_data[$k]['fromid'] = $likeLists[$logList['likeid']]['fromid'];
			$_data[$k]['fromtype'] = $likeLists[$logList['likeid']]['typeid'];
			$_data[$k]['url'] = $likeInfos[$logList['likeid']]['url'];
			$_data[$k]['image'] = $likeInfos[$logList['likeid']]['image'];
			$_data[$k]['subject'] =  WindSecurity::escapeHTML($likeInfos[$logList['likeid']]['subject']);
			$_data[$k]['descrip'] =  WindSecurity::escapeHTML(strip_tags($likeInfos[$logList['likeid']]['content']));
			$_data[$k]['uid'] = $likeInfos[$logList['likeid']]['uid'];
			$_data[$k]['username'] = $likeInfos[$logList['likeid']]['username'];
			$_data[$k]['avatar'] = Pw::getAvatar($likeInfos[$logList['likeid']]['uid'], 'small');
			$_data[$k]['space'] = WindUrlHelper::createUrl(
				'space/index/run/?uid=' . $likeInfos[$logList['likeid']]['uid']);
			$_data[$k]['lasttime'] = Pw::time2str($likeInfos[$logList['likeid']]['lasttime'], 'auto');
			$_data[$k]['like_count'] = $likeInfos[$logList['likeid']]['like_count'];
			$_data[$k]['reply_pid'] = $replyInfos[$likeLists[$logList['likeid']]['reply_pid']];
			$_data[$k]['reply_uid'] = $replyInfos[$likeLists[$logList['likeid']]['reply_pid']]['uid'];
			$_data[$k]['reply_avatar'] = Pw::getAvatar($replyInfos[$likeLists[$logList['likeid']]['reply_pid']]['uid'],
				'small');
			$_data[$k]['reply_space'] = WindUrlHelper::createUrl(
				'space/index/run/?uid=' . $replyInfos[$likeLists[$logList['likeid']]['reply_pid']]['uid']);
			$_data[$k]['reply_username'] = $replyInfos[$likeLists[$logList['likeid']]['reply_pid']]['username'];
			$_data[$k]['reply_content'] =  WindSecurity::escapeHTML($replyInfos[$likeLists[$logList['likeid']]['reply_pid']]['content']);
			$_data[$k]['like_url'] = WindUrlHelper::createUrl(
				'like/mylike/doLike/?typeid=' . $_data[$k]['fromtype'] . '&fromid=' . $_data[$k]['fromid']);
		}
		$this->setOutput($_data, 'html');
		$this->showMessage('operate.success');
	}

	public function getTagListAction() {
		$array = array();
		$lists = $this->_getLikeTagService()->getInfoByUid($this->uid);
		$this->setOutput($lists, 'data');
		$this->showMessage('BBS:like.success');
	}

      /**
        * 增加喜欢
        * @access public
        * @return string
        * @example
        <pre>
        /index.php?m=native&c=mylike&a=dolike&_json=1
        post: fromid&typeid (formid：是喜欢的帖子tid或者回帖的pid；typeid：回复主贴是1；回复回帖是2)
        cookie:usersession
        response: {err:"",data:""}  
        </pre>
        */
	public function doLikeAction() {
		$typeid = (int) $this->getInput('typeid', 'post');
		$fromid = (int) $this->getInput('fromid', 'post');
		if ($typeid < 1 || $fromid < 1) $this->showError('BBS:like.fail');
		$resource = $this->_getLikeService()->addLike($this->loginUser, $typeid, $fromid);
		if ($resource instanceof PwError) $this->showError($resource->getError());
		
		$needcheck = false;
		if($resource['extend']['needcheck'])  $needcheck = false;
		$data['likecount'] = $resource['likecount'];
		$data['needcheck'] = $needcheck;
		$this->setOutput($data, 'data');
		$this->showMessage('BBS:like.success');
	}


       /**
        * 删除我的喜欢
        * 如果喜欢内容总喜欢数小于1，同时删除喜欢内容
        * @access public
        * @return string
        * @example
        <pre>
        /index.php?m=native&c=mylike&a=doDelLike&_json=1
        post: logid(pw_like_log表中的主键)
        cookie:usersession
        response: {err:"",data:""}  
        </pre>
        */
	public function doDelLikeAction() {
		$logid = (int) $this->getInput('logid', 'post');
		if (!$logid) $this->showError('BBS:like.fail');
		$resource = $this->_getLikeService()->delLike($this->uid, $logid);
		if ($resource) $this->showMessage('BBS:like.success');
		$this->showError('BBS:like.fail');
	}
	
	/**
	 * 编辑喜欢所属分类
	 */
	public function doLogTagAction() {
		$tagid = (int)$this->getInput('tagid', 'post');
		$type = (int)$this->getInput('type', 'post');
		$logid = (int)$this->getInput('logid', 'post');
		if (!$logid || !$tagid) $this->showError('BBS:like.fail');
		$this->_getLikeService()->editLogTag($logid, $tagid, $type);
		$this->showMessage('BBS:like.success');
	}
	
	/**
	 * 增加所属分类
	 * Enter description here ...
	 */
	public function doAddLogTagAction() {
		$tagname = $this->getInput('tagname', 'post');
		$logid = (int)$this->getInput('logid', 'post');
		if (!$logid || !$tagname) $this->showError('BBS:like.fail');
		$resource = $this->_getLikeService()->addTag($this->uid,$tagname);
		if ($resource instanceof PwError) $this->showError($resource->getError());
		$tagid = (int)$resource;
		$this->_getLikeService()->editLogTag($logid, $tagid, 1);
		$this->setOutput(array('id'=>$tagid,'name'=>$tagname), 'data');
		$this->showMessage('BBS:like.success');
	}

	/**
	 * 分类添加
	 * 
	 */
	public function doAddTagAction() {
		$tagname = $this->getInput('tagname', 'post');
		if (!$tagname) $this->showError('BBS:like.fail');
		$resource = $this->_getLikeService()->addTag($this->uid,$tagname);
		if ($resource instanceof PwError) $this->showError($resource->getError());
		$this->setOutput(array('id'=>(int)$resource,'name'=>$tagname), 'data');
		$this->showMessage('BBS:like.success');
	}

	/**
	 * 分类删除
	 *
	 */
	public function doDelTagAction() {
		$tagid = (int) $this->getInput('tag', 'post');
		if (!$tagid) {
			$this->showError('operate.fail');
		}
		$info = $this->_getLikeService()->allowEditTag($this->uid, $tagid);
		if ($info instanceof PwError) $this->showError($info->getError());
		if (!$this->_getLikeTagService()->deleteInfo($tagid)) $this->showError('BBS:like.fail');
		$this->_getLikeRelationsService()->deleteInfos($tagid);
		$this->showMessage('BBS:like.success', true, WindUrlHelper::createUrl('like/mylike/run/'));
	}

	/**
	 * 编辑分类
	 *
	 */
	public function doEditTagAction() {
		$tagid = (int) $this->getInput('tag', 'post');
		$tagname = trim($this->getInput('tagname', 'post'));
		if (Pw::strlen($tagname) < 2) $this->showError('BBS:like.tagname.is.short');
		if (Pw::strlen($tagname) > 10) $this->showError('BBS:like.tagname.is.lenth');
		$tags = $this->_getLikeTagService()->getInfoByUid($this->uid);
		$allow = false;
		foreach ($tags as $tag) {
			if ($tag['tagid'] == $tagid) $allow = true;
			if ($tag['tagname'] == $tagname) $this->showError('BBS:like.fail.already.tagname');
		}
		if (!$allow) $this->showError('BBS:like.fail');
		Wind::import('SRV:like.dm.PwLikeTagDm');
		$dm = new PwLikeTagDm($tagid);
		$dm->setTagname($tagname);
		$resource = $this->_getLikeTagService()->updateInfo($dm);
		if ($resource instanceof PwError) $this->showError($resource->getError());
		$this->setOutput(array('id'=>$tagid,'name'=>$tagname), 'data');
		$this->showMessage('BBS:like.success');
	}

	private function _getLikeRelationsService() {
		return Wekit::load('like.PwLikeRelations');
	}

	private function _getLikeTagService() {
		return Wekit::load('like.PwLikeTag');
	}

	private function _getBuildLikeService() {
		return Wekit::load('like.srv.PwBuildLikeService');
	}

	private function _getLikeService() {
		return Wekit::load('like.srv.PwLikeService');
	}
	
	private function _getLikeLogDs() {
		return Wekit::load('like.PwLikeLog');
	}
}
?>
