<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class FacebookConnectButton extends CWidget
{

	protected $template = '<span>{label}</span>';
	protected $label = 'Login';
	protected $labelLogout = 'Logout';
	protected $redirect_uri;
	protected $htmlOptions = array();
	protected $popup = true;
	protected $scope = array();

	/**
	* Initializes the widget.
	*
	* This method is called by the application before the controller starts to execute.
	* @return
	*/
	public function init() {
		$this->registerConfigurationScripts();
	}

	/**
	* Function Constructor.
	*
	* This method set the сonstructor.
	*
	* @param string $owner The default owner variable.
	*
	* @return
	*/

	public function __construct($owner = null) {
		parent::__construct($owner);
	}

	/**
	* Function run.
	*
	* This method run the controller.
	*
	* @return
	*/
	public function run() {
		if (is_null($this->redirect_uri)) {
			$this->redirect_uri = Yii::app()->createAbsoluteUrl(Yii::app()->user->returnUrl);
		} else {
			$this->redirect_uri = Yii::app()->getBaseUrl(true) . $this->redirect_uri;
		}

		$params = array('redirect_uri' => $this->redirect_uri);

		if(FConnect::getUser()) {
			$href = Yii::app()->createAbsoluteUrl('fbconnect/logout', $params);

			echo CHtml::tag('a', $this->htmlOptions + array('href' => $href), str_replace('{label}', $this->labelLogout, $this->template), true);
		} else {
			if (!empty($this->scope)) {
				$params += array('scope' => $this->scope);
			}

			if ($this->popup) {
				$params += array('display' => 'popup');
			}

			$href = Yii::app()->createAbsoluteUrl('fbconnect/login', $params);

			if ($this->popup) {
				$this->htmlOptions = array_merge($this->htmlOptions, array('onclick' => 'window.open("' . $href . '","FBConnect","height=400,width=650,location=no,toolbar=no,menubar=no,directories=no,resizable=no");return false;'));
			}

			echo CHtml::tag('a', $this->htmlOptions + array('href' => $href), str_replace('{label}', $this->label, $this->template), true);
		}
	}

	/**
	 * Function registerConfigurationScripts.
	 *
	 * This method register configuration scripts.
	 *
	 * @return
	 */
	protected function registerConfigurationScripts() {
		Yii::setPathOfAlias('facebookconnect', dirname(__FILE__));
		$url = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('facebookconnect.assets'), false, -1, YII_DEBUG);

		Yii::app()->clientScript->registerCssFile($url . '/css/facebook.css');
	}

	public function __set($name, $value) {
		$method_name = 'set' . $name;
		if (method_exists($this, $method_name)) {
			$this->$method_name($value);
		} elseif (property_exists($this, $name) && true === is_string($value)) {
			$this->$name = $value;
		}
	}

	public function setHtmlOptions($value) {
		if(true === is_array($value)) {
			$this->htmlOptions = $value;
		}
	}

	public function setScope($value) {
		if(true === is_array($value)) {
			$this->scope = implode(',', $value);
		}
	}

	public function setPopup($value) {
		if (true === is_bool($value)) {
			$this->popup = $value;
		}
	}
}
