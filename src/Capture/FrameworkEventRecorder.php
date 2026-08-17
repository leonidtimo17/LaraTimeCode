<?php

declare(strict_types=1);

namespace LaraTimeCode\Capture;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use LaraTimeCode\Redaction\Redactor;

final class FrameworkEventRecorder
{
    public function __construct(
        private readonly CaptureContext $context,
        private readonly Config $config,
        private readonly Redactor $redactor,
    ) {}

    public function recordQuery(QueryExecuted $event): void
    {
        if (! $this->context->isActive()) {
            return;
        }

        $query = [
            'sql' => $event->sql,
            'time_ms' => $event->time,
            'connection' => $event->connectionName,
        ];

        if ((bool) $this->config->get('laratimecode.capture.query_bindings', false)) {
            $query['bindings'] = $this->redactor->redact($event->bindings, 'database.bindings');
        }

        $this->context->addQuery(
            $query,
            (int) $this->config->get('laratimecode.capture.max_queries', 100),
        );
    }

    public function recordResponse(ResponseReceived $event): void
    {
        if (! $this->shouldRecordOutboundHttp()) {
            return;
        }

        $exchange = [
            'method' => $event->request->method(),
            'url' => $this->redactor->redactUrl($event->request->url()),
            'status' => $event->response->status(),
        ];

        if ((bool) $this->config->get('laratimecode.capture.outbound_response_body', false)) {
            $limit = (int) $this->config->get('laratimecode.capture.outbound_response_bytes', 8_192);
            $body = substr($event->response->body(), 0, max(0, $limit));
            $decoded = json_decode($body, true);
            $exchange['response_body'] = is_array($decoded)
                ? $this->redactor->redact($decoded, 'outbound.response')
                : $body;
        }

        $this->context->addOutboundHttp($exchange);
    }

    public function recordConnectionFailure(ConnectionFailed $event): void
    {
        if (! $this->shouldRecordOutboundHttp()) {
            return;
        }

        $this->context->addOutboundHttp([
            'method' => $event->request->method(),
            'url' => $this->redactor->redactUrl($event->request->url()),
            'status' => null,
            'connection_failed' => true,
        ]);
    }

    private function shouldRecordOutboundHttp(): bool
    {
        return $this->context->isActive()
            && (bool) $this->config->get('laratimecode.capture.outbound_http', true);
    }
}
