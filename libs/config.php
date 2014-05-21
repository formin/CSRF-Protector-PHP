<?php
/**
 * Configuration file for CSRF Protector z
 */
return array(
	"isGETEnabled" => false,
	"logDirectory" => "../log",
	"failedAuthAction" => array(
		"GET" => 1,
		"POST" => 0),
	"errorRedirectionPage" => "http://localhost/",
	"customErrorMessage" => "",
	"jsFile" => "http://localhost/test/csrf/js/csrfprotector.js",
	"tokenLength" => 10,
	"disabledJavascriptMessage" => "This site attempts to protect users against <a href=\"https://www.owasp.org/index.php/Cross-Site_Request_Forgery_%28CSRF%29\">
	Cross-Site Request Forgeries </a> attacks. In order to do so, you must have JavaScript enabled in your web browser otherwise this site will fail to work correctly for you.
	 See details of your web browser for how to enable JavaScript."
);