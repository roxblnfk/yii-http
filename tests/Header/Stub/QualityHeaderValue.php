<?php

namespace Yiisoft\Http\Tests\Header\Stub;

use Yiisoft\Http\Header\WithQualityParam;

final class QualityHeaderValue extends \Yiisoft\Http\Header\HeaderValue implements WithQualityParam
{
    public const NAME = 'Test-Quality';
    public function setQuality(string $q): bool
    {
        return parent::setQuality($q);
    }
}
