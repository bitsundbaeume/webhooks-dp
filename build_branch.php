<?php
/**
 * GitHub webhook handler template.
 * 
 * @see  https://developer.github.com/webhooks/
 * @author  Miloslav Hůla (https://github.com/milo)
 * @source  https://gist.github.com/milo/daed6e958ea534e4eba3
 * Edited by @b3nks for @bitsundbaeume22
 */
$webhook_secret_path = '/var/www/webhooks/webhooksecrets/build_bubweb_branch_webhook_secret.txt'; 


if (file_exists($webhook_secret_path)) {
    // Lese den Inhalt der Datei
    $fileContent = file_get_contents($filePath);
    
    // Überprüfe, ob das Lesen der Datei erfolgreich war
    if ($fileContent !== false) {
        // Gib den Inhalt der Datei aus
        echo "Der Inhalt der Datei ist: " . $fileContent;
    } else {
        echo "Fehler beim Lesen der Datei.";
    }
} else {
    echo "Datei existiert nicht.";
}




// Lade das Hook Secret aus der Datei
$webhook_secret = $fileContent


// Entferne mögliche unerwünschte Leerzeichen oder Zeilenumbrüche
$webhook_secret = trim($webhook_secret);

$permitted_branches = ['main', 'dev'];
set_error_handler(function($severity, $message, $file, $line) {
	throw new \ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function($e) {
	header('HTTP/1.1 500 Internal Server Error');
	echo "Error on line {$e->getLine()}: " . htmlSpecialChars($e->getMessage());
	die();
});
$rawPost = NULL;
if ($webhook_secret !== NULL) {
	if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
		throw new \Exception("HTTP header 'X-Hub-Signature' is missing.");
	} elseif (!extension_loaded('hash')) {
		throw new \Exception("Missing 'hash' extension to check the secret code validity.");
	}
	list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
	if (!in_array($algo, hash_algos(), TRUE)) {
		throw new \Exception("Hash algorithm '$algo' is not supported.");
	}
	$rawPost = file_get_contents('php://input');
	if ($hash !== hash_hmac($algo, $rawPost, $webhook_secret)) {
		throw new \Exception('Hook secret does not match.');
	}
};
if (!isset($_SERVER['CONTENT_TYPE'])) {
	throw new \Exception("Missing HTTP 'Content-Type' header.");
} elseif (!isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
	throw new \Exception("Missing HTTP 'X-Github-Event' header.");
}
switch ($_SERVER['CONTENT_TYPE']) {
	case 'application/json':
		$json = $rawPost ?: file_get_contents('php://input');
		break;
	case 'application/x-www-form-urlencoded':
		$json = $_POST['payload'];
		break;
	default:
		throw new \Exception("Unsupported content type: $_SERVER[CONTENT_TYPE]");
}

# Payload structure depends on triggered event
# https://developer.github.com/v3/activity/events/types/
$payload = json_decode($json);
switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
	case 'ping':
		echo 'pong';
		break;
	case 'push':
		$branch = explode('refs/heads/', $payload->ref)[1];
		if (in_array($branch, $permitted_branches)) {
			putenv('PATH=' . $_SERVER['PATH']); # ansonsten ist der exec() command in Uberspace ohne ausreichende environment variablen PATH und npm build wirft fehler
			exec("bash /var/www/bitsundbaeume/repositories/website-build-dp/build_bitbaumweb_$branch.sh > /dev/null & echo $!", $out, $rv);
			echo "Build branch $branch ($rv)";
		} else {
                	header('HTTP/1.0 404 Not Found');
               		echo "Event: $_SERVER[HTTP_X_GITHUB_EVENT]\nBranch: $branch";
			die();
        	}
		break;
	default:
		header('HTTP/1.0 404 Not Found');
		echo "Event:$_SERVER[HTTP_X_GITHUB_EVENT]";
		die();
}
