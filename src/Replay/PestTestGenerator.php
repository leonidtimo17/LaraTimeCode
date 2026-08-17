<?php

declare(strict_types=1);

namespace LaraTimeCode\Replay;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

final class PestTestGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Config $config,
    ) {}

    /** @param array<string, mixed> $snapshot */
    public function generate(array $snapshot, ?string $name = null, bool $force = false): string
    {
        $id = (string) ($snapshot['id'] ?? 'snapshot');
        $request = is_array($snapshot['request'] ?? null) ? $snapshot['request'] : [];
        $auth = is_array($snapshot['auth'] ?? null) ? $snapshot['auth'] : null;
        $capturedAt = (string) ($snapshot['captured_at'] ?? 'now');
        $method = strtoupper((string) ($request['method'] ?? 'GET'));
        $uri = $this->uriWithQuery(
            (string) ($request['uri'] ?? '/'),
            is_array($request['query'] ?? null) ? $request['query'] : [],
        );
        $input = is_array($request['input'] ?? null) ? $request['input'] : [];
        $headers = is_array($request['headers'] ?? null) ? $request['headers'] : [];
        $isJson = str_contains(
            strtolower((string) ($headers['content-type'] ?? '')),
            'application/json',
        );
        $testName = $name ?: 'regression for LaraTimeCode '.$id;
        $fileName = Str::studly(str_replace(['-', '.'], '_', $id)).'Test.php';
        $directory = (string) $this->config->get('laratimecode.tests_path');
        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$fileName;

        if ($this->files->exists($path) && ! $force) {
            throw new RuntimeException(sprintf('Test [%s] already exists. Use --force to replace it.', $path));
        }

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $authComment = $auth === null
            ? ''
            : sprintf(
                "    // TODO: authenticate %s with identifier %s.\n",
                (string) ($auth['class'] ?? 'the captured user'),
                var_export($auth['identifier'] ?? null, true),
            );

        $requestCall = sprintf(
            $isJson ? '        ->json(%s, %s, %s);' : '        ->call(%s, %s, %s);',
            var_export($method, true),
            var_export($uri, true),
            $this->export($input, 8),
        );

        $contents = sprintf(
            <<<'PHP'
<?php

use Carbon\CarbonImmutable;

it(%s, function () {
    $this->travelTo(CarbonImmutable::parse(%s));
%s
    $response = $this
        ->withHeaders(%s)
%s

    // Replace this with the precise expected behavior after reproducing the bug.
    $response->assertSuccessful();
});

PHP,
            var_export($testName, true),
            var_export($capturedAt, true),
            $authComment,
            $this->export($headers, 8),
            $requestCall,
        );

        $this->files->put($path, $contents);

        return $path;
    }

    /** @param array<string, mixed> $query */
    private function uriWithQuery(string $uri, array $query): string
    {
        return $query === [] ? $uri : $uri.'?'.http_build_query($query);
    }

    /** @param array<mixed> $value */
    private function export(array $value, int $indent): string
    {
        $export = var_export($value, true);
        $padding = str_repeat(' ', $indent);

        return str_replace("\n", "\n".$padding, $export);
    }
}
