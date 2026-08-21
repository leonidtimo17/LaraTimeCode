<?php

declare(strict_types=1);

namespace LaraTimeCode\Redaction;

use DateTimeInterface;
use JsonSerializable;
use Stringable;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class Redactor
{
    /** @param list<string> $patterns */
    public function __construct(
        private readonly array $patterns,
        private readonly string $replacement = '[REDACTED]',
        private readonly int $maxStringLength = 16_384,
    ) {}

    public function redact(mixed $value, string $path = ''): mixed
    {
        if ($path !== '' && $this->matches($path)) {
            return $this->replacement;
        }

        if ($value instanceof UploadedFile) {
            return [
                '_type' => 'uploaded_file',
                'name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
                'error' => $value->getError(),
            ];
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $key => $item) {
                $childPath = $path === '' ? (string) $key : $path.'.'.$key;
                $redacted[$key] = $this->redact($item, $childPath);
            }

            return $redacted;
        }

        if ($value instanceof JsonSerializable) {
            return $this->redact($value->jsonSerialize(), $path);
        }

        if (is_resource($value)) {
            return '[RESOURCE]';
        }

        if (is_object($value)) {
            return $value instanceof Stringable
                ? $this->truncate((string) $value)
                : sprintf('[OBJECT:%s]', $value::class);
        }

        return is_string($value) ? $this->truncate($value) : $value;
    }

    /** @param array<string, mixed> $query */
    public function redactUrl(string $url, array $query = []): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $this->truncate($url);
        }

        $parsedQuery = [];
        parse_str((string) ($parts['query'] ?? ''), $parsedQuery);
        $parsedQuery = array_replace_recursive($parsedQuery, $query);
        $redactedQuery = $this->redact($parsedQuery, 'query');

        $authority = isset($parts['host'])
            ? ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : '';
        $path = $parts['path'] ?? '';
        $queryString = is_array($redactedQuery) && $redactedQuery !== []
            ? '?'.http_build_query($redactedQuery)
            : '';

        return $this->truncate($authority.$path.$queryString);
    }

    private function matches(string $path): bool
    {
        $normalizedPath = strtolower($path);
        $lastSegment = strtolower((string) strrchr('.'.$path, '.'));
        $lastSegment = ltrim($lastSegment, '.');

        foreach ($this->patterns as $pattern) {
            $normalizedPattern = strtolower($pattern);

            if (! str_contains($normalizedPattern, '.') && $normalizedPattern === $lastSegment) {
                return true;
            }

            $quoted = preg_quote($normalizedPattern, '/');
            $regex = '/^'.str_replace('\\*', '[^.]+', $quoted).'$/i';

            if (preg_match($regex, $normalizedPath) === 1) {
                return true;
            }
        }

        return false;
    }

    private function truncate(string $value): string
    {
        if (strlen($value) <= $this->maxStringLength) {
            return $value;
        }

        $truncated = mb_strcut($value, 0, $this->maxStringLength, 'UTF-8');

        return $truncated.sprintf(
            "\n[TRUNCATED %d BYTES]",
            strlen($value) - strlen($truncated),
        );
    }
}
