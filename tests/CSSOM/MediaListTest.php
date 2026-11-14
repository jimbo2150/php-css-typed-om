<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\MediaList;
use PHPUnit\Framework\TestCase;

final class MediaListTest extends TestCase
{
	public function testParsesAndMutatesMediaQueries(): void
	{
		$media = new MediaList('screen and (min-width: 10px), print');
		$media->appendMedium('speech');

		self::assertCount(3, $media);
		self::assertSame('screen and (min-width: 10px)', $media->item(0));
		self::assertSame('screen and (min-width: 10px), print, speech', $media->getMediaText());

		$media->deleteMedium('print');
		self::assertSame('screen and (min-width: 10px), speech', $media->getMediaText());
	}

	public function testRejectsEmptyMediaEntry(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new MediaList())->appendMedium(' ');
	}
}
