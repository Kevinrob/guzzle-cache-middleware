# Project Context: Guzzle Cache Middleware

## Project Overview
**Name:** `kevinrob/guzzle-cache-middleware`
**Type:** PHP Library / Middleware
**Purpose:** Provides a compliant HTTP/1.1 (RFC 7234) caching middleware for the Guzzle HTTP client. It allows Guzzle to cache responses based on headers and efficient storage strategies.

### Key Features
*   **RFC 7234 Compliance:** Transparent caching behavior respecting HTTP cache headers.
*   **Flexible Storage:** Built-in support for:
    *   Laravel Cache
    *   Flysystem
    *   PSR-6 (Cache Interface)
    *   PSR-16 (Simple Cache)
    *   WordPress Object Cache
*   **Caching Strategies:**
    *   `PrivateCacheStrategy`: Caches responses for a single user.
    *   `PublicCacheStrategy`: Caches shared responses.
    *   `GreedyCacheStrategy`: Force caching even without proper headers.
    *   `DelegatingCacheStrategy`: Route requests to different strategies based on patterns.

## Architecture
The core logic resides in `src/`.
*   **Middleware:** `Kevinrob\GuzzleCache\CacheMiddleware` is the main entry point, pushed to the Guzzle `HandlerStack`.
*   **Storage:** `Kevinrob\GuzzleCache\Storage\*` classes adapt various cache backends (Redis, Filesystem, Memory) to a common interface.
*   **Strategy:** `Kevinrob\GuzzleCache\Strategy\*` classes determine *when* and *how* to cache a response (e.g., checking headers, vary tags).

## Building and Running

### Prerequisites
*   Docker and Docker Compose (Recommended for isolation)
*   PHP >= 8.2 (if running locally)
*   Composer

### Docker Workflow (Preferred)
The project includes a `Makefile` to simplify Docker interactions.

*   **Initialize Project:**
    ```bash
    make init
    ```
    *Builds the Docker image and runs `composer install`.*

*   **Run Tests:**
    ```bash
    make test
    ```
    *Runs PHPUnit inside the container.*

*   **Open Shell:**
    ```bash
    make shell
    ```
    *Opens a shell inside the container.*

### Local Development (Manual)
If you prefer running without Docker:

1.  **Install Dependencies:**
    ```bash
    composer install
    ```

2.  **Run Tests:**
    ```bash
    vendor/bin/phpunit
    ```
    *Or use `composer test`.*

## Development Conventions

*   **Coding Standard:** Follows PSR-12 (implied by modern PHP practices).
*   **Testing:**
    *   Tests are located in `tests/`.
    *   Uses PHPUnit 9.x.
    *   Configuration is in `phpunit.xml.dist`.
    *   Code coverage reports are generated for `src/`.
*   **Autoloading:** Uses PSR-4.
    *   `Kevinrob\GuzzleCache\` -> `src/`
    *   `Kevinrob\GuzzleCache\Tests\` -> `tests/`
*   **Versioning:** Follows Semantic Versioning.

## Key Files
*   `README.md`: Primary documentation and usage examples.
*   `composer.json`: Dependency definition and project metadata.
*   `Makefile`: Shortcuts for Docker commands.
*   `src/CacheMiddleware.php`: The main middleware class.
*   `src/Storage/CacheStorageInterface.php`: Interface for creating new storage backends.
