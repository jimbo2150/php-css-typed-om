<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Process;

use GuzzleHttp\Client;
use Jimbo2150\PhpCssTypedOm\Exception\DontWriteException;
use Psr\Http\Message\ResponseInterface;

final class CSSPropertiesProcessor
{
	private const PROPERTIES_DIR = 'dist/CSSProperties';

	private const JSON_FILE = 'CSSProperties.json';

	private const DB_FILE = 'CSSProperties.sqlite';

	private const JSON_FILE_URL = 'https://raw.githubusercontent.com/WebKit/WebKit/main/Source/WebCore/css/CSSProperties.json';

	protected function __construct()
	{
		// Don't allow instances
	}

	public static function run()
	{
		self::acquireJsonFile();
	}

	public static function getPropertiesPath(): string
	{
		static $rootPath = realpath('.');

		return $rootPath.'/'.self::PROPERTIES_DIR;
	}

	public static function getJsonFilePath(): string
	{
		return self::getPropertiesPath().'/'.self::JSON_FILE;
	}

	public static function getDbPath(): string
	{
		return self::getPropertiesPath().self::DB_FILE;
	}

	private static function acquireJsonFile()
	{
		$file = self::getJsonFilePath();
		$etag_file = $file.'.etag';
		$dir = dirname($file);

		if (!file_exists($dir)) {
			mkdir($dir, fileperms('.') ?? 0777, true);
		}
		if (!file_exists($file)) {
			touch($etag_file);
			touch($file);
		}

		$etag = trim(file_get_contents($etag_file));

		$headers = [
			'Accept' => ['text/plain'],
			'User-Agent' => ['PHP'],
		];

		if (!empty($etag)) {
			$headers['If-None-Match'][] = $etag;
		}

		$client = new Client();
		echo 'Acquiring CSS Properties file... ';

		try {
			$response = $client->request('GET', self::JSON_FILE_URL, [
				'headers' => $headers,
				'sink' => $file,
				'on_headers' => function (ResponseInterface $response) {
					if (304 == $response->getStatusCode()) {
						throw new DontWriteException('File was not modified, nothing to do: '.$response->getStatusCode());
					} elseif (
						count(array_filter(
							$response->getHeader('Content-Length'),
							fn (int $length) => $length > 5
						)) < 1
					) {
						throw new DontWriteException('No content returned.');
					}
				},
			]);
		} catch (\Exception $e) {
			if (!($e->getPrevious() instanceof DontWriteException)) {
				throw $e;
			} else {
				echo $e->getPrevious()->getMessage().PHP_EOL;
			}
		}

		if (!isset($response)) {
			return;
		}

		if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
			file_put_contents($etag_file, $response->getHeader('ETag'));
		}

		echo 'Success.';
	}
}
