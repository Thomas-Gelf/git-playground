<?php

namespace IMEdge\RpcApi\Reflection;

use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use function trim;

class MethodCommentParser
{
    protected const REGEXP_START_OF_COMMENT   = '~^\s*/\*\*\n~s';
    protected const REGEXP_COMMENT_LINE_START = '~^\s*\*\s?~';
    protected const REGEXP_END_OF_COMMENT     = '~\n\s*\*/\s*~s';
    protected const REGEXP_TAG_TYPE_VALUE     = '/^@([A-z0-9]+)\s*(.*?)$/';

    protected MetaDataTagSet $tags;
    protected ?MetaDataTagParser $currentTag = null;
    /** @var string[] */
    protected array $paragraphs = [];
    protected ?string $currentParagraph = null;
    protected ?string $title = null;

    protected function __construct()
    {
        $this->tags = new MetaDataTagSet();
    }

    public static function parseComment(string $raw): MethodCommentParser
    {
        $parser = new MethodCommentParser();
        $parser->parse(self::stripStartOfComment(self::stripEndOfComment($raw)));
        return $parser;
    }

    /**
     * @return ParamTag[]
     */
    public function getParams(): array
    {
        return $this->tags->getParams();
    }

    public function getReturnType(): ?string
    {
        return $this->tags->getReturnType();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return implode("\n\n", $this->paragraphs);
    }

    protected function parseLine(string $line): void
    {
        // Strip * at line start
        $line = preg_replace(self::REGEXP_COMMENT_LINE_START, '', $line);
        $line = trim($line ?? '');

        if (preg_match(self::REGEXP_TAG_TYPE_VALUE, $line, $match)) {
            $this->finishCurrentObjects();
            $this->currentTag = new MetaDataTagParser($match[1], $match[2]);
            return;
        }

        if ($this->currentTag) {
            $this->currentTag->appendValueString($line);
            return;
        }

        $this->finishCurrentTag();
        $this->appendToParagraph($line);
    }

    protected function appendToParagraph(string $line): void
    {
        if (trim($line) === '') {
            $this->finishCurrentLine();
            return;
        }
        if ($this->title === null) {
            $this->title = $line;
            return;
        }
        if ($this->currentParagraph === null) {
            $this->paragraphs[] = $line;
            $this->currentParagraph = & $this->paragraphs[count($this->paragraphs) - 1];
        } else {
            if (str_starts_with($line, '  ')) {
                $this->currentParagraph .= "\n" . $line;
            } else {
                $this->currentParagraph .= ' ' . $line;
            }
        }
    }

    protected function finishCurrentObjects(): void
    {
        $this->finishCurrentTag();
        $this->finishCurrentLine();
    }

    protected function finishCurrentTag(): void
    {
        if ($this->currentTag) {
            $this->tags->add($this->currentTag->getTag());
            $this->currentTag = null;
        }
    }

    protected function finishCurrentLine(): void
    {
        if ($this->currentParagraph !== null) {
            unset($this->currentParagraph); // This is required -> it's a pointer
            $this->currentParagraph = null;
        }
    }

    protected function parse(string $plain): void
    {
        foreach (explode("\n", $plain) as $line) {
            $this->parseLine($line);
        }
        $this->finishCurrentObjects();
    }

    /**
     * Removes comment start -> /**
     */
    protected static function stripStartOfComment(string $string): string
    {
        return preg_replace(self::REGEXP_START_OF_COMMENT, '', $string) ?? $string;
    }

    /**
     * Removes comment end ->  * /
     */
    protected static function stripEndOfComment(string $string): string
    {
        return preg_replace(self::REGEXP_END_OF_COMMENT, "\n", $string) ?? $string;
    }
}
