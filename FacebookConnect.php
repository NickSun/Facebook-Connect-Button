<?php

/*
 * FacebookConnect class
 * @author Nikonov Andrey <nikonov@zfort.net>
 * @link http://www.zfort.com/
 * @copyright Copyright &copy; 2000-2011 Zfort Group
 * @license http://www.zfort.com/terms-of-use
 * @version $Id$
 * @package packageName
 * @since 1.0
 */

class FacebookConnect extends CApplicationComponent
{

	/**
	 * The Application ID.
	 *
	 * @var string
	 */
	public $appId;

	/**
	 * The Application API Secret.
	 *
	 * @var string
	 */
	public $secret;

	public function init()
	{
		parent::init();

		if (!empty($this->appId) && !empty($this->secret))
		{
			Yii::setPathOfAlias('facebookconnect', dirname(__FILE__));
			Yii::import('facebookconnect.*');
			Yii::import('facebookconnect.controllers.*');

			FConnect::init($this->appId, $this->secret);
			$userId = FConnect::getUser();
			if ($userId && Yii::app()->user->isGuest)
			{
				$identity = new FConnectUserIdentity($userId);
				$identity->authenticate();
				if ($identity->errorCode == FConnectUserIdentity::ERROR_NONE)
				{
					Yii::app()->user->login($identity, 0);
				}
			}

			if (Yii::app()->session->offsetExists('fb_popup'))
			{
				Yii::app()->session->offsetUnset('fb_popup');
				if (Yii::app()->session->offsetExists('fb_redirect_uri'))
				{
					$href = Yii::app()->session->get('fb_redirect_uri');
					Yii::app()->session->offsetUnset('fb_redirect_uri');
					$js = 'if (window.opener && !window.opener.closed) { window.opener.location.href="' . $href . '"; } window.close();';
				}
				else
				{
					$js = 'if (window.opener && !window.opener.closed) { window.opener.location.reload(); } window.close();';
				}

				Yii::app()->clientScript->registerScript('fb_redirect', $js, CClientScript::POS_HEAD);
			}

			Yii::app()->configure(array(
				'controllerMap' => CMap::mergeArray(Yii::app()->controllerMap, array(
					'fbconnect' => array(
						'class' => 'FacebookConnectController',
					)
				))
			));
		}
		else
		{
			throw new Exception('You need to add appId and secret to config file!');
		}
	}

}