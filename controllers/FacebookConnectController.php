<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class FacebookConnectController extends Controller
{

	/**
	 *
	 */
	public function actionLogin() {
		$params['redirect_uri'] = Yii::app()->getRequest()->getQuery('redirect_uri');
		$params['scope'] = Yii::app()->getRequest()->getQuery('scope');
		if (Yii::app()->getRequest()->getQuery('display') === 'popup') {
			$params['display'] = 'popup';
			Yii::app()->session->add('fb_popup', true);
			Yii::app()->session->add('fb_redirect_uri', $params['redirect_uri']);
		} elseif (Yii::app()->session->offsetExists('fb_popup')) {
			Yii::app()->session->offsetUnset('fb_popup');
		} elseif (Yii::app()->session->offsetExists('fb_redirect_uri')) {
			Yii::app()->session->offsetUnset('fb_redirect_uri');
		}

		$fbLoginUrl = FConnect::getLoginUrl($params);
		$this->redirect($fbLoginUrl);
		Yii::app()->end();
	}

	public function actionLogout() {
		$redirect_uri = Yii::app()->getRequest()->getQuery('redirect_uri');
		if ($redirect_uri) {
			FConnect::logout();
			$this->redirect($redirect_uri);
			Yii::app()->end();
		}
	}
}
