<?php

class FConnectUserIdentity extends CUserIdentity
{
	protected $_id;

	public function __construct($userId) {
		$this->_id = (int) $userId;
	}

	public function authenticate()
	{
		$userInfo = FConnect::getUserInfo();
		if ($this->_id && $userInfo)
		{
			$this->errorCode = self::ERROR_NONE;
			$this->username = $userInfo['username'];
		}
		else
		{
			$this->errorCode = self::ERROR_UNKNOWN_IDENTITY;
		}

		return !$this->errorCode;
	}

	public function getId()
	{
		return $this->_id;
	}

}