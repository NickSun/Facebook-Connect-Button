<?php

/*
 * FacebookConnectButton class
 * @author Nikonov Andrey <nikonov@zfort.net>
 * @link http://www.zfort.com/
 * @copyright Copyright &copy; 2000-2011 Zfort Group
 * @license http://www.zfort.com/terms-of-use
 * @version $Id$
 * @package packageName
 * @since 1.0
 */

class FacebookConnectButton extends CWidget
{

	/**
	 * The template used to render link. In this template, the token "{label}" will be replaced with the corresponding label
	 * @var string 
	 */
	protected $template = '<span>{label}</span>';
	
	/**
	 * The link label
	 * @var string 
	 */
	protected $label = 'Login';
	
	/**
	 * The logout link label
	 * @var string 
	 */
	protected $labelLogout = 'Logout';
	
	/**
	 * HTML attributes to be rendered for the link
	 * @var array 
	 */
	protected $htmlOptions = array();
	
	/**
	 * Additional parameters that will be sent to Facebook
	 * @var array 
	 */
	protected $params = array(
		'display' => 'page',
		'scope' => 'offline_access,publish_stream',
	);

	/**
	 * Initializes the widget.
	 *
	 * This method is called by the application before the controller starts to execute.
	 * @return
	 */
	public function init()
	{
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
	public function __construct($owner = null)
	{
		parent::__construct($owner);
	}

	/**
	 * Renders the widget.
	 */
	public function run()
	{
		if ($this->getParams()->offsetExists('redirect_uri'))
		{
			$redirect_uri = Yii::app()->getBaseUrl(true) . $this->getParams()->offsetGet('redirect_uri');
			$this->getParams()->add('redirect_uri', $redirect_uri);
		}
		else
		{
			$this->getParams()->add('redirect_uri', Yii::app()->createAbsoluteUrl(Yii::app()->user->returnUrl));
		}

		if (FConnect::getUser())
		{
			$href = Yii::app()->createAbsoluteUrl('fbconnect/logout', $this->getTagParams());

			echo CHtml::tag('a', array_merge($this->htmlOptions, array('href' => $href)), str_replace('{label}', $this->labelLogout, $this->template), true);
		}
		else
		{
			$href = Yii::app()->createAbsoluteUrl('fbconnect/login', $this->getTagParams());

			if ($this->getParams()->offsetGet('display') == 'popup')
			{
				$this->htmlOptions = array_merge($this->htmlOptions, array('onclick' => 'window.open("' . $href . '","FBConnect","height=370,width=650,location=no,toolbar=no,menubar=no,directories=no,resizable=no");return false;'));
			}

			echo CHtml::tag('a', array_merge($this->htmlOptions, array('href' => $href)), str_replace('{label}', $this->label, $this->template), true);
		}
	}

	/**
	 * Function registerConfigurationScripts.
	 *
	 * This method register configuration scripts.
	 *
	 * @return
	 */
	protected function registerConfigurationScripts()
	{
		Yii::setPathOfAlias('facebookconnect', dirname(__FILE__));
		$url = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('facebookconnect.assets'), false, -1, YII_DEBUG);

		Yii::app()->clientScript->registerCssFile($url . '/css/facebook.css');
	}

	/**
	 * Set HTML attributes
	 * 
	 * @param array $value 
	 */
	public function setHtmlOptions($value)
	{
		if (true === is_array($value))
		{
			$this->htmlOptions = $value;
		}
	}

	/**
	 * Function getParams.
	 *
	 * This method get the params.
	 *
	 * @return
	 */
	public function getParams() {
		if (is_null($this->params) !== true)
		{
			$this->params = new CMap($this->params, false);
		}

		return $this->params;
	}

	/**
	* Function getTagParams.
	*
	* This method get tag options.
	*
	* @return
	*/
	private function getTagParams()
	{
		return $this->getParams()->toArray();
	}

	/**
	 * Set redirect_uri
	 * 
	 * @param string $value 
	 */
	public function setRedirect_uri($value)
	{
		if (true === is_string($value))
		{
			$this->getParams()->add('redirect_uri', $value);
		}
	}

	/**
	 * Set display
	 * 
	 * @param string $value 
	 */
	public function setDisplay($value)
	{
		if (true === is_string($value))
		{
			$this->getParams()->add('display', $value);
		}
	}

	/**
	 * Set scope. More info: http://developers.facebook.com/docs/reference/api/permissions/
	 * 
	 * @param mixed $value 
	 */
	public function setScope($value)
	{
		if (true === is_array($value))
		{
			$value = implode(',', $value);
		}

		$this->getParams()->add('scope', $value);
	}

	/**
	 * Set label for link
	 * 
	 * @param string $value 
	 */
	public function setLabel($value)
	{
		if (true === is_string($value))
		{
			$this->label = $value;
		}
	}

	/**
	 * Set logout label for link
	 * 
	 * @param string $value 
	 */
	public function setLabelLogout($value)
	{
		if (true === is_string($value))
		{
			$this->labelLogout = $value;
		}
	}

	/**
	 * Set template for link
	 *
	 * @param string $value 
	 */
	public function setTemplate($value)
	{
		if (true === is_string($value))
		{
			$this->template = $value;
		}
	}
}