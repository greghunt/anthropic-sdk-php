# Anthropic PHP SDK

This library provides convenient access to the Anthropic REST API from server-side PHP

## Installation

```sh
composer require wpai-inc/anthropic-sdk-php
```

## Basic Usage

Set your API key in the constructor:

```php
$anthropic = new \WpAi\Anthropic\AnthropicAPI($apiKey);
```

The following parameters are required for every request:

```php
$messages = [
    [
        'role' => 'user',
        'content' => 'How can you help me?',
    ],
];
$options = [
    'model' => 'claude-3-5-haiku-20241022',
    'maxTokens' => 1024,
    'messages' => $messages,
];

$anthropic->messages()->create($options);
```

The options above are required. You may also set them fluently like this:

```php
$anthropic->messages()
    ->model('claude-3-5-haiku-20241022')
    ->maxTokens(1024)
    ->messages($messages)
    ->create();
```

### Available Options

For more complex usage, additional options can be set either in the options array or using fluent methods:

```php
$anthropic->messages()
    ->model('claude-3-5-haiku-20241022')
    ->maxTokens(2048)
    ->messages($messages)
    ->system("You are a helpful assistant")  // Optional system prompt
    ->temperature(0.7)                       // Controls randomness (0.0-1.0)
    ->topP(0.9)                             // Nucleus sampling
    ->topK(10)                              // Top-k sampling
    ->stopSequences(["STOP"])               // Custom stop sequences
    ->metadata(['user_id' => '123'])        // Custom metadata
    ->create();
```

All other optional options can be set in the same ways.

To include additional HTTP headers in the request, such as to enable beta features, pass an array as the second argument to the `create()` method:

```php
$options = [/* your options */];
$headers = ['anthropic-beta' => 'anthropic-beta: prompt-caching-2024-07-31'];
$response = $anthropic->messages()->create($options, $headers);
```

### Response Structure

The response object contains the following properties:

```php
$response = $anthropic->messages()->create($options);

$response->id;           // Message ID
$response->type;         // Response type
$response->role;         // Always 'assistant'
$response->content;      // Array of content blocks
$response->model;        // Model used
$response->stopReason;   // Why the response ended
$response->stopSequence; // Stop sequence if triggered
$response->usage->inputTokens;  // Token count for input
$response->usage->outputTokens; // Token count for output
$response->toolCalls;    // Tool calls if any
```

### Streaming

A streamed response follows all of the same options as `create()` but may be invoked with:

```php
$stream = $anthropic->messages()->stream($options);

// Basic real-time processing example
foreach ($stream as $chunk) {
    if ($chunk['type'] === 'content_block_delta') {
        $text = $chunk['delta']['text'];
        
        // Output to terminal/console
        echo $text;
        
        // Or append to file in real-time
        file_put_contents('response.txt', $text, FILE_APPEND);
        
        // Or send to client if in a web context
        if (ob_get_level() > 0) {
            echo $text;
            ob_flush();
            flush();
        }
        
        // Or process chunks for special markers/commands
        if (str_contains($text, '[[')) {
            // Handle special command
        }
    }
}

// Comprehensive example collecting the complete response
$responseText = '';
$input_tokens = 0;
$output_tokens = 0;

foreach ($stream as $chunk) {
    // Handle content chunks
    if ($chunk['type'] === 'content_block_delta') {
        $responseText .= $chunk['delta']['text'];
    }
    // Capture input tokens from message start
    elseif ($chunk['type'] === 'message_start') {
        $input_tokens = $chunk['message']['usage']['input_tokens'];
    }
    // Capture output tokens at message end
    elseif ($chunk['type'] === 'message_delta' && isset($chunk['delta']['stop_reason'])) {
        $output_tokens = $chunk['usage']['output_tokens'];
    }
}

// Final response contains the complete text and token usage
$response = [
    'content' => $responseText,
    'usage' => [
        'input_tokens' => $input_tokens,
        'output_tokens' => $output_tokens
    ]
];
```

The stream will emit different types of chunks:
- `message_start`: Contains initial message information including input token count
- `content_block_delta`: Contains the actual content chunks
- `message_delta`: Contains final information like stop reason and output token count

You may set extra HTTP headers by passing an array as a second argument to `stream()`.

### Tool Use

You can enable tool use by providing tool definitions and optionally specifying tool choice:

```php
$tools = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'get_current_weather',
            'description' => 'Get the current weather in a given location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The location to get weather for'
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit']
                    ]
                ],
                'required' => ['location']
            ]
        ]
    ]
];

$response = $anthropic->messages()
    ->model('claude-3-5-haiku-20241022')
    ->maxTokens(1024)
    ->tools($tools)
    ->toolChoice('auto')  // or 'none', or specific tool definition
    ->messages([
        ['role' => 'user', 'content' => 'What\'s the weather like in London?']
    ])
    ->create();

// Access tool calls from the response
if ($response->toolCalls) {
    foreach ($response->toolCalls as $toolCall) {
        $functionName = $toolCall['function']['name'];
        $arguments = json_decode($toolCall['function']['arguments'], true);
        // Implement your tool call handling here
    }
}
```

### Error Handling

The library throws exceptions for various error cases:

```php
try {
    $response = $anthropic->messages()->create($options);
} catch (\WpAi\Anthropic\Exceptions\ClientException $e) {
    // Handle API errors (rate limits, invalid requests, etc)
    echo $e->getMessage();
} catch (\WpAi\Anthropic\Exceptions\StreamException $e) {
    // Handle streaming-specific errors
    echo $e->getMessage();
}
```

### Laravel Integration

This library includes Laravel support via a service provider and facade:

```php
use WpAi\Anthropic\Facades\Anthropic;

// Create a message
$response = Anthropic::messages()
    ->model('claude-3-5-haiku-20241022')
    ->maxTokens(1024)
    ->messages([
        ['role' => 'user', 'content' => 'Hello, Claude!'],
    ])
    ->create();

// Stream a message
$stream = Anthropic::messages()
    ->model('claude-3-5-haiku-20241022')
    ->maxTokens(1024)
    ->messages([
        ['role' => 'user', 'content' => 'Tell me a story.'],
    ])
    ->stream();
```

Publish the config with:

```sh
php artisan vendor:publish --provider="WpAi\Anthropic\Providers\AnthropicServiceProvider"
```