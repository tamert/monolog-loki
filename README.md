# Loki handler for Monolog

Loki handler for Monolog, providing a formatter that serializes records into the JSON format expected by Grafana Loki.

## Table of Contents

- [Installation](#installation)
- [Usage](#usage)
- [Contributing](#contributing)

## Installation
Require the package via Composer:

```bash
composer require tamert/monolog-loki
```

## Usage

Below is a minimal example showing how to send logs to a Loki server:

```php
use Monolog\Level;
use Monolog\Logger;
use Tamert\Monolog\Loki\LokiHandler;

$handler = new LokiHandler(
    'http://your-loki-host:3100',
    ['app'=>'My application', 'env'=>'production'],
    'debug'
);

$logger = new Logger('loki');
$logger->pushHandler($handler);

$logger->info('User signed in', ['user_id' => 123]);
```

![Grafana explore](docs/grafana_explore.png)
## Contributing

Contributions are very welcome! Please:

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/YourFeature`).
3. Make your changes, ensuring all tests pass and coding standards are met.
4. Submit a pull request.
