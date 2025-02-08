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
		if (static::needToAcquireJsonFile()) {
			static::acquireJsonFile();
		}
		if (static::needToGenerateDatabase()) {
			static::generateDatabase();
		}
	}

	public static function getPropertiesPath(): string
	{
		static $rootPath = realpath('.');

		return $rootPath.DIRECTORY_SEPARATOR.self::PROPERTIES_DIR;
	}

	public static function getJsonFilePath(): string
	{
		return self::getPropertiesPath().DIRECTORY_SEPARATOR.self::JSON_FILE;
	}

	public static function getDbPath(): string
	{
		return self::getPropertiesPath().DIRECTORY_SEPARATOR.self::DB_FILE;
	}

	private static function needToAcquireJsonFile(): bool
	{
		$propFile = static::getJsonFilePath();
		if (!file_exists($propFile)) {
			if (!touch($propFile)) {
				throw new \Exception('Could not create CSS properties file.');
			}

			return true;
		}
		$propMtime = new \DateTimeImmutable('@'.filemtime($propFile));
		$now = new \DateTimeImmutable();
		if (
			$now > $propMtime->modify('+5 minutes') ||
			filesize($propFile) < 1
		) {
			return true;
		}

		return false;
	}

	private static function acquireJsonFile(): void
	{
		$file = static::getJsonFilePath();
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
			'User-Agent' => ['PHP Typed OM Package'],
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
				'on_headers' => function (ResponseInterface $response) use ($file): void {
					if (304 == $response->getStatusCode()) {
						touch($file);
						throw new DontWriteException('File was not modified, nothing to do: '.$response->getStatusCode());
					} elseif (
						$response->getStatusCode() >= 300 || $response->getStatusCode() < 200
					) {
						throw new \Exception('HTTP Error: '.$response->getStatusCode());
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

		echo 'Success.'.PHP_EOL;
	}

	private static function needToGenerateDatabase(): bool
	{
		$dbFile = static::getDbPath();
		$propFile = static::getJsonFilePath();
		if (!file_exists($dbFile)) {
			if (!touch($dbFile)) {
				throw new \Exception('Could not create database file.');
			}

			return true;
		}
		if (
			filemtime($propFile) > filemtime($dbFile) ||
			filesize($dbFile) < 1
		) {
			return true;
		}

		return false;
	}

	private static function generateDatabase(): void
	{
		$propFile = json_decode(file_get_contents(static::getJsonFilePath()));
		$dbFile = static::getDbPath();
		echo 'Creating CSS Properties database... ';
		$db = new \PDO('sqlite:'.$dbFile);
		if (!$db || $db->errorCode()) {
			throw new \Exception('Could not initialized CSS properties database.');
		}
		foreach ($propFile->properties as $property => $propEntry) {
			return;
		}
		// echo 'Success.';
		echo PHP_EOL;
	}
}
