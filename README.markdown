## Yii Facebook Connect##

The Yii Facebook Connect connect to the facebook.

### Installation ###

Extract the [Yii Facebook Connect][1] from archive under protected/extensions
[1]: https://github.com/NickSun/Facebook-Connect-Button        "Yii Facebook Connect"

## Usage and Configuration ##

For use [Yii Facebook Connect][1] need to add some code to configure to the component section:

``` php
<?php
//...
	'preload' => array('log','facebookconnect'),
//...
	'facebookconnect' => array(
		'class' => 'ext.facebookconnect.FacebookConnect',
		'appId' => 'YOUR_APP_ID',
		'secret' => 'YOUR_APP_SECRET',
	)
```
and you can add it in view section:

``` php
<?php
//...
$this->widget('ext.facebookconnect.FacebookConnectButton',
	array(
		'label' => 'Login with FB',
		'template' => '<span>{label}</span>',
		'redirect_uri' => CHtml::normalizeUrl(array('/site/index')),
		'scope' => array('offline_access', 'publish_stream'),
		'popup' => false,
		'htmlOptions' => array(
			'class' => 'fb_auth',
		)
	)
);
```