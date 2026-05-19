<?php
declare(strict_types=1);

namespace Silver\Helpers;

class HTMLElement
{
    private string $tagname;
    private array $attrs;
    private array $children;

    public function __construct(string $tagname, array $attrs = [], string|self ...$children)
    {
        $this->tagname = $tagname;
        $this->attrs = $attrs;
        $this->children = $children;
    }

    public static function make(string $tagname, array $attrs = [], string|self ...$children): static
    {
        return new static($tagname, $attrs, ...$children);
    }

    public function appendChild(string|self $child): static
    {
        if (is_string($child)) {
            $child = htmlspecialchars($child);
        }
        $this->children[] = $child;
        return $this;
    }

    public function children(): array
    {
        return $this->children;
    }

    public function setAttr(string $name, string|bool $value = true): static
    {
        $this->attrs[$name] = $value;
        return $this;
    }

    public function attrs(): array
    {
        return $this->attrs;
    }

    public function __toString(): string
    {
        $html = '<' . $this->tagname;

        foreach ($this->attrs as $key => $value) {
            if ($value === true) {
                $html .= ' ' . $key;
            } elseif ($value !== false) {
                $html .= ' ' . $key . '="' . htmlspecialchars((string) $value) . '"';
            }
        }

        if ($this->children) {
            $html .= '>';
            foreach ($this->children as $child) {
                $html .= is_string($child) ? htmlspecialchars($child) : (string) $child;
            }
            $html .= '</' . $this->tagname . '>';
        } elseif (strtolower($this->tagname) === 'script') {
            $html .= '></' . $this->tagname . '>';
        } else {
            $html .= ' />';
        }

        return $html;
    }
}
