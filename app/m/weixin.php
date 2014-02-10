<?php/*+--------------------------------------------------------------------------|   WeCenter [#RELEASE_VERSION#]|   ========================================|   by WeCenter Software|   © 2011 - 2013 WeCenter. All Rights Reserved|   http://www.wecenter.com|   ========================================|   Support: WeCenter@qq.com|   +---------------------------------------------------------------------------*/define('IN_AJAX', TRUE);if (!defined('IN_ANWSION')){	die;}define('IN_MOBILE', true);class weixin extends AWS_CONTROLLER{	public function get_access_rule()	{		$rule_action['rule_type'] = 'black';		$rule_action['actions'] = array(			'binding'			);				return $rule_action;	}		public function setup()	{		HTTP::no_cache_header();				TPL::import_clean();				TPL::import_css(array(			'js/mobile/mobile.css',		));				TPL::import_js(array(			'js/jquery.2.js',			'js/jquery.form.js',			'js/mobile/framework.js',			'js/mobile/mobile.js',			'js/mobile/aw-mobile-template.js'		));	}		public function redirect_action()	{		if ($_GET['code'] AND get_setting('weixin_app_id'))		{			if ($access_token = $this->model('openid_weixin')->get_sns_access_token_by_authorization_code($_GET['code']))			{				if ($access_token['errcode'])				{					H::redirect_msg('授权失败: redirect ' . $access_token['errcode'] . ' ' . $access_token['errmsg'] . ', code: ' . $_GET['code']);				}								if ($weixin_user = $this->model('openid_weixin')->get_user_info_by_openid($access_token['openid']))				{					$user_info = $this->model('account')->get_user_info_by_uid($weixin_user['uid']);										HTTP::set_cookie('_user_login', get_login_cookie_hash($user_info['user_name'], $user_info['password'], $user_info['salt'], $user_info['uid'], false));										HTTP::redirect(base64_decode($_GET['redirect']));				}				else				{					$redirect_uri = str_replace(get_setting('base_url'), '', base64_decode($_GET['redirect']));															if ($this->user_info['permission']['visit_site'] AND substr($redirect_uri, 0, 1) == '/' AND get_setting('register_type') != 'weixin')					{						$uri_analyze = explode('/', substr($redirect_uri, 1));												if ($uri_analyze[0] == 'm')						{							$current_controller = $uri_analyze[1];						}						else						{							$current_controller = $uri_analyze[0];						}												if (($current_controller == 'question' AND $this->user_info['permission']['visit_question']) OR ($current_controller == 'topic' AND $this->user_info['permission']['visit_topic']) OR ($current_controller == 'feature' AND $this->user_info['permission']['visit_feature']) OR ($current_controller == 'people' AND $this->user_info['permission']['visit_people']) OR ($current_controller == 'home' AND $this->user_info['permission']['visit_explore']))						{							HTTP::redirect(base64_decode($_GET['redirect']));						}					}										if ($_GET['state'] == 'OAUTH')					{						HTTP::redirect('/m/weixin/authorization/?state=OAUTH&access_token=' . urlencode(base64_encode(serialize($access_token))) . '&redirect=' . urlencode($_GET['redirect']));					}					else					{						HTTP::redirect($this->model('openid_weixin')->get_oauth_url('/m/weixin/authorization/?redirect=' . urlencode($_GET['redirect'])));					}				}			}			else			{				H::redirect_msg(AWS_APP::lang()->_t('远程服务器忙,请稍后再试'));			}		}		else		{			H::redirect_msg(AWS_APP::lang()->_t('授权失败,请返回重新操作'));		}	}		public function authorization_action()	{		$this->model('account')->setcookie_logout();	// 清除 COOKIE		$this->model('account')->setsession_logout();	// 清除 Session				unset(AWS_APP::session()->WXConnect);				if (!get_setting('weixin_app_id'))		{			H::redirect_msg(AWS_APP::lang()->_t('微信订阅号不支持账户绑定'));		}		else if ($_GET['code'] OR $_GET['state'] == 'OAUTH')		{			if ($_GET['state'] == 'OAUTH')			{				$access_token = unserialize(base64_decode($_GET['access_token']));			}			else			{				$access_token = $this->model('openid_weixin')->get_sns_access_token_by_authorization_code($_GET['code']);			}						if ($access_token)			{				if ($access_token['errcode'])				{					H::redirect_msg('授权失败: authorization ' . $access_token['errcode'] . ' ' . $access_token['errmsg'] . ', code: ' . $_GET['code']);				}								if ($_GET['state'] == 'OAUTH')				{					$access_user = $this->model('openid_weixin')->get_user_info_by_oauth_openid_from_mp($access_token['access_token'], $access_token['openid']);				}				else				{					$access_user = $this->model('openid_weixin')->get_user_info_by_openid_from_mp($access_token['openid']);				}								if (!$access_user)				{					H::redirect_msg(AWS_APP::lang()->_t('远程服务器忙,请稍后再试'));				}								if ($access_user['errcode'])				{					if ($access_user['errcode'] == 48001)					{						$this->model('weixin')->send_text_message($access_token['openid'], '当前微信没有绑定社区帐号, 请<a href="' . $this->model('openid_weixin')->get_oauth_url(get_js_url('/m/weixin/authorization/'), 'snsapi_userinfo') . '">点此绑定</a>或<a href="' . get_js_url('/m/register/') . '">注册新账户</a>, 使用全部功能');												H::redirect_msg(AWS_APP::lang()->_t('当前微信没有绑定社区帐号, 请返回进行绑定后访问本内容'));					}					else					{						H::redirect_msg('获取用户信息失败: ' . $access_user['errcode'] . ' ' . $access_user['errmsg']);					}				}								if (!$access_user['nickname'])				{					H::redirect_msg(AWS_APP::lang()->_t('您当前没有关注本公众号, 无法使用全部功能'));				}								if ($weixin_user = $this->model('openid_weixin')->get_user_info_by_openid($access_token['openid']))				{					$user_info = $this->model('account')->get_user_info_by_uid($weixin_user['uid']);										HTTP::set_cookie('_user_login', get_login_cookie_hash($user_info['user_name'], $user_info['password'], $user_info['salt'], $user_info['uid'], false));										if ($_GET['redirect'])					{						HTTP::redirect(base64_decode($_GET['redirect']));					}					else					{						H::redirect_msg(AWS_APP::lang()->_t('绑定微信成功'));					}				}								if (get_setting('register_type') == 'weixin')				{					if ($user_info = $this->model('openid_weixin')->weixin_auto_register($access_token, $access_user))					{						if ($_GET['redirect'])						{							HTTP::redirect(base64_decode($_GET['redirect']));						}						else						{							H::redirect_msg(AWS_APP::lang()->_t('绑定微信成功'));						}					}					else					{						H::redirect_msg(AWS_APP::lang()->_t('注册失败,请返回重新操作'));					}				}				else				{					AWS_APP::session()->WXConnect = array(						'access_token' => $access_token,						'access_user' => $access_user					);										TPL::assign('access_token', $access_token);					TPL::assign('access_user', $access_user);										TPL::assign('body_class', 'explore-body');										TPL::output('m/weixin/authorization');				}			}			else			{				H::redirect_msg(AWS_APP::lang()->_t('远程服务器忙,请稍后再试'));			}		}		else		{			H::redirect_msg(AWS_APP::lang()->_t('授权失败,请返回重新操作'));		}	}		public function binding_action()	{		if (AWS_APP::session()->WXConnect['access_token']['openid'])		{			$this->model('openid_weixin')->bind_account(AWS_APP::session()->WXConnect['access_user'], AWS_APP::session()->WXConnect['access_token'], $this->user_id);						if ($_GET['redirect'])			{				HTTP::redirect(base64_decode($_GET['redirect']));			}			else			{				H::redirect_msg(AWS_APP::lang()->_t('绑定微信成功'));			}		}		else		{			H::redirect_msg(AWS_APP::lang()->_t('授权失败,请返回重新操作'));		}	}		public function oauth_redirect_action()	{		if (!$_GET['uri'])		{			$redirect_uri = urlencode($_SERVER['HTTP_REFERER']);		}		else		{			$redirect_uri = urlencode(get_js_url($_GET['uri']));		}				if (!get_setting('weixin_app_id'))		{			HTTP::redirect(urldecode($redirect_uri));		}				$redirect_info = parse_url(urldecode($redirect_uri));				if ($redirect_info['host'] == 'mp.wecenter.com')		{			$redirect_uri = get_js_url('/m/weixin/mp_redirect/?uri=' . urlencode(base64_encode(urldecode($redirect_uri))));		}				HTTP::redirect('https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . get_setting('weixin_app_id') . '&redirect_uri=' . $redirect_uri . '&response_type=code&scope=' . urlencode($_GET['scope']) . '&state=' . urlencode($_GET['state']) . '#wechat_redirect');	}		public function mp_redirect_action()	{		$redirect_uri = base64_decode($_GET['uri']);				if (strstr($redirect_uri, '?'))		{			HTTP::redirect($redirect_uri . '&code=' . $_GET['code']);		}		else		{			HTTP::redirect($redirect_uri . '?code=' . $_GET['code']);		}	}}