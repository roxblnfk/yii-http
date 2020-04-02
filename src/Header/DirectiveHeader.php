<?php

declare(strict_types=1);

namespace Yiisoft\Http\Header;

use InvalidArgumentException;
use Yiisoft\Http\Header\Internal\DirectivesHeaderValue;
use Yiisoft\Http\Header\Value\Unnamed\DirectiveValue;

final class DirectiveHeader extends Header
{
    protected const DEFAULT_VALUE_CLASS = DirectiveValue::class;

    public function __construct(string $nameOrClass)
    {
        parent::__construct($nameOrClass);
        if (!is_a($this->headerClass, DirectivesHeaderValue::class, true)) {
            throw new InvalidArgumentException(
                sprintf("%s class is not an instance of %s", $this->headerClass, DirectivesHeaderValue::class)
            );
        }
    }

    /**
     * @param string $directive
     * @param string|null $argument
     * @return $this
     * @throws InvalidArgumentException
     */
    public function withDirective(string $directive, string $argument = null): self
    {
        $clone = clone $this;
        /** @var DirectivesHeaderValue $headerValue */
        $headerValue = new $this->headerClass();
        $clone->addValue($headerValue->withDirective($directive, $argument));
        return $clone;
    }

    /**
     * @param bool $ignoreIncorrect
     * @return string[]|null[] Returns array where keys are directives and values are arguments
     */
    public function getDirectives(bool $ignoreIncorrect = true): array
    {
        $result = [];
        /** @var DirectivesHeaderValue $header */
        foreach ($this->collection as $header) {
            if ($ignoreIncorrect && $header->hasError()) {
                continue;
            }
            $result[] = [$header->getDirective() => $header->getArgument()];
        }
        return $result;
    }
}