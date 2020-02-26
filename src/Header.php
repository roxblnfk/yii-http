<?php

namespace Yiisoft\Http;

use InvalidArgumentException;
use Yiisoft\Http\Header\DefaultHeaderValue;
use Yiisoft\Http\Header\HeaderValue;
use Yiisoft\Http\Header\ListedValues;
use Yiisoft\Http\Header\WithParams;

final class Header
{
    private string $headerClass;
    private string $headerName;
    /** @var HeaderValue[] */
    private array $collection = [];

    private bool $listedValues = false;
    private bool $withParams = false;

    private const DELIMITERS = '"(),/:;<=>?@[\\]{}';
    private const PART_NONE = 0;
    private const PART_VALUE = 1;
    private const PART_PARAM_NAME = 2;
    private const PART_PARAM_QUOTED_VALUE = 3;
    private const PART_PARAM_VALUE = 4;


    public function __construct(string $nameOrClass)
    {
        $this->headerClass = $nameOrClass;
        if (class_exists($nameOrClass)) {
            if (!is_subclass_of($nameOrClass, HeaderValue::class, true)) {
                throw new InvalidArgumentException("{$nameOrClass} is not a header.");
            }
            if (is_a($nameOrClass, DefaultHeaderValue::class, true)) {
                throw new InvalidArgumentException("{$nameOrClass} has no header name.");
            }
            $this->headerName = $nameOrClass::NAME;
            $this->listedValues = is_subclass_of($nameOrClass, ListedValues::class, true);
            $this->withParams = is_subclass_of($nameOrClass, WithParams::class, true);
        } else {
            $this->headerName = $nameOrClass;
            $this->headerClass = DefaultHeaderValue::class;
        }
    }

    /**
     * @param HeaderValue[]|string[] $headers
     * @param string                 $headerClass
     * @return static
     * @throws InvalidArgumentException
     */
    public static function createFromArray(array $headers, string $headerClass): self
    {
        $new = new static($headerClass);
        foreach ($headers as $header) {
            $new->add($header);
        }
        return $new;
    }

    public function getName(): string
    {
        return $this->headerName;
    }

    /**
     * @param string|HeaderValue $value
     * @return $this
     */
    public function add($value): self
    {
        if ($value instanceof HeaderValue) {
            if (get_class($value) !== $this->headerClass) {
                throw new InvalidArgumentException(
                    sprintf('The value must be an instance of %s, %s given', $this->headerClass, get_class($value))
                );
            }
            $this->collection[] = $value;
            return $this;
        }
        if (is_string($value)) {
            $this->parseAndCollect($value);
            return $this;
        }
        throw new InvalidArgumentException(sprintf('The value must be an instance of %s or string', $this->headerClass));
    }

    public function getValues(): array
    {
        return $this->collection;
    }
    public function getStrings(): array
    {
        $result = [];
        foreach ($this->collection as $header) {
            $result[] = $header->__toString();
        }
        return $result;
    }

    private function parseAndCollect(string $body): void
    {
        if (!$this->listedValues && !$this->withParams) {
            $this->collection[] = new $this->headerClass(trim($body));
            return;
        }
        $part = self::PART_VALUE;
        $buffer = '';
        $key = '';
        $value = '';
        $error = '';
        $params = [];
        $resetState = static function () use (&$key, &$value, &$buffer, &$params) {
            $key = $buffer = $value = '';
            $params = [];
        };
        $added = 0;
        try {
            /** @see https://tools.ietf.org/html/rfc7230#section-3.2.6 */
            for ($i = 0, $length = strlen($body); $i < $length; ++$i) {
                $s = $body[$i];
                if ($part === self::PART_VALUE) {
                    if ($s === '=' && $this->withParams) {
                        $part = self::PART_PARAM_VALUE;
                        $key = trim($buffer);
                        $buffer = '';
                        if (strpos($key, ' ') === false) {
                            continue;
                        }
                        $key = preg_replace('/\s+/', ' ', $key);
                        $chunks = explode(' ', $key);
                        if (count($chunks) > 2) {
                            throw new \Exception('Syntax error');
                        }
                        [$value, $key] = $chunks;
                    } elseif ($s === ';' && $this->withParams) {
                        $part = self::PART_PARAM_NAME;
                        $value = trim($buffer);
                        $buffer = '';
                    } elseif ($s === ',' && $this->listedValues) {
                        $this->collection[] = new $this->headerClass(trim($buffer));
                        ++$added;
                        $resetState();
                    } else {
                        $buffer .= $s;
                    }
                    continue;
                }
                if ($part === self::PART_PARAM_NAME) {
                    if ($s === '=') {
                        $key = trim($buffer);
                        $buffer = '';
                        $part = self::PART_PARAM_VALUE;
                    } elseif (strpos(self::DELIMITERS, $s) === false) {
                        $buffer .= $s;
                    } else {
                        throw new \Exception("Delimiter char \"{$s}\" in a param name");
                    }
                    continue;
                }
                if ($part === self::PART_NONE) {
                    if (ord($s) <= 32) {
                        continue;
                    } elseif ($s === ';' && $this->withParams) {
                        $part = self::PART_PARAM_NAME;
                        continue;
                    } elseif ($s === ',' && $this->listedValues) {
                        $this->collection[] = (new $this->headerClass(trim($buffer)))->withParams($params);
                        ++$added;
                        $resetState();
                    } else {
                        throw new \Exception('Expected Separator');
                    }
                }
                if ($part === self::PART_PARAM_VALUE) {
                    if ($buffer === '') {
                        if ($s === '"') {
                            $part = self::PART_PARAM_QUOTED_VALUE;
                        } elseif (ord($s) <= 32) {
                            continue;
                        } else {
                            $buffer .= $s;
                        }
                    } elseif (ord($s) <= 32) {
                        $part = self::PART_NONE;
                        $params[$key] = rtrim($buffer);
                        $key = $buffer = '';
                    } elseif (strpos(self::DELIMITERS, $s) === false) {
                        $buffer .= $s;
                    } elseif ($s === ';') {
                        $part = self::PART_PARAM_NAME;
                        $params[$key] = rtrim($buffer);
                        $key = $buffer = '';
                    } elseif ($s === ',' && $this->listedValues) {
                        $params[$key] = $buffer;
                        $this->collection[] = (new $this->headerClass(trim($buffer)))->withParams($params);
                        ++$added;
                        $resetState();
                    } else {
                        throw new \Exception("Delimiter char \"{$s}\" in a unquoted param value");
                    }
                    continue;
                }
                if ($part === self::PART_PARAM_QUOTED_VALUE) {
                    if ($s === '\\') { // quoted pair
                        if (++$i >= $length) {
                            throw new \Exception('Incorrect quoted pair');
                        } else {
                            $buffer .= $body[$i];
                        }
                    } elseif ($s === '"') { // end
                        $part = self::PART_NONE;
                        $params[$key] = $buffer;
                        $key = $buffer = '';
                    } else {
                        $buffer .= $s;
                    }
                }
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        if ($added > 0 && $value === '' && $buffer === '') {
            return;
        }
        /** @var HeaderValue $item */
        $item = new $this->headerClass($part === self::PART_VALUE ? trim($buffer) : $value);
        if (in_array($part, [self::PART_PARAM_VALUE, self::PART_PARAM_QUOTED_VALUE], true)) {
            $params[$key] = $buffer;
        }
        if (count($params) > 0) {
            $item = $item->withParams($params);
        }
        if ($error !== '') {
            $item = $item->withError($error);
        }
        $this->collection[] = $item;
    }
}