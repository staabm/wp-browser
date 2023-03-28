<?php

use lucatume\WPBrowser\Process\Protocol\Request;
use lucatume\WPBrowser\Process\Protocol\Response;
use lucatume\WPBrowser\Process\SerializableThrowable;
use lucatume\WPBrowser\Utils\Serializer;

$processSrcRoot = __DIR__ . '/..';
require_once $processSrcRoot . '/Protocol/Parser.php';
require_once $processSrcRoot . '/Protocol/Control.php';
require_once $processSrcRoot . '/Protocol/Request.php';
require_once $processSrcRoot . '/Protocol/ProtocolException.php';
require_once $processSrcRoot . '/../Utils/ErrorHandling.php';

try {
    $request = Request::fromPayload($argv[1]);
    $serializableClosure = $request->getSerializableClosure();
    $returnValue = $serializableClosure();
} catch (\Throwable $throwable) {
    $exitValue = 1;
    $returnValue = new SerializableThrowable($throwable);
}

$response = new Response($returnValue);
$responsePayload = Response::$stderrValueSeparator . $response->getPayload();

fwrite(STDERR, $responsePayload, strlen($responsePayload));

exit($response->getExitValue());

