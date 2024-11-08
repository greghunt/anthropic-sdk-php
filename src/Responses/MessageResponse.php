<?php

namespace WpAi\Anthropic\Responses;

use Psr\Http\Message\ResponseInterface;

class MessageResponse extends Response
{
    public string $id;

    public string $type;

    public string $role;

    public array $content;

    public string $model;

    public ?string $stopReason;

    public ?string $stopSequence;

    public Usage $usage;

    public ?array $toolCalls;

    public function __construct(protected ResponseInterface $response)
    {
        $data = json_decode($this->response->getBody()->getContents(), true);

        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->role = $data['role'];
        $this->content = $data['content'];
        $this->model = $data['model'];
        $this->stopReason = $data['stop_reason'];
        $this->stopSequence = $data['stop_sequence'];
        $this->usage = new Usage($data['usage']);
        $this->toolCalls = $data['tool_calls'] ?? null;
    }
}

class Usage
{
    public int $inputTokens;

    public int $outputTokens;

    public function __construct(array $data)
    {
        $this->inputTokens = $data['input_tokens'];
        $this->outputTokens = $data['output_tokens'];
    }
}
