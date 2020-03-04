<?php

declare(strict_types=1);

namespace Yiisoft\Http\Header\Value\Date;

/**
 * @see https://tools.ietf.org/html/rfc7232#section-2.2
 */
final class LastModified extends Date
{
    public const NAME = 'Last-Modified';
}
