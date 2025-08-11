# GitHub Copilot Instructions for guzzle-cache-middleware

This repository contains a HTTP cache middleware for Guzzle 6+, implementing RFC 7234 compliance for HTTP caching.

## Language and Communication
- **Primary Language**: English should be used for all code, comments, documentation, issues, and pull requests
- All variable names, function names, and comments must be in English
- Documentation and README updates should be in English

## Project Overview
This is a PHP library that provides HTTP caching capabilities for Guzzle HTTP client through middleware. Key components:
- `CacheMiddleware`: Main middleware class that handles HTTP caching
- `Storage/`: Various storage adapters (PSR-6, PSR-16, Flysystem, Laravel, WordPress)
- `Strategy/`: Different caching strategies (Private, Public, Greedy, Delegate, Null)
- `CacheEntry`: Represents cached HTTP responses
- `KeyValueHttpHeader`: Utility for handling HTTP headers

## Development Environment

### Setup
Initialize the development environment:
```bash
make init
```
This command builds Docker containers and installs PHP dependencies via Composer.

### Testing
Run the test suite (requires initialization first):
```bash
make test
```
Tests are written using PHPUnit and located in the `tests/` directory.

### Additional Commands
- `make shell`: Enter the Docker container shell for debugging
- `composer test`: Alternative way to run tests directly with Composer

## Architecture and Code Organization

### Source Structure
- `src/`: Main library code
  - `CacheMiddleware.php`: Core middleware implementation
  - `Storage/`: Storage backend implementations
  - `Strategy/`: Caching strategy implementations
- `tests/`: PHPUnit test suite
- `.docker/`: Docker configuration for development environment

### Key Design Patterns
- **Middleware Pattern**: Used for HTTP request/response interception
- **Strategy Pattern**: Different caching strategies for various use cases
- **Adapter Pattern**: Multiple storage backend implementations
- **PSR Compliance**: Follows PSR-7 (HTTP messages), PSR-6 (caching), PSR-16 (simple cache)

## Coding Standards

### PHP Standards
- **PHP Version**: Requires PHP 8.1+
- **PSR Compliance**: Follow PSR-4 autoloading, PSR-7 HTTP messages
- **Code Style**: Follow the existing code style as defined in `.editorconfig`
  - 4 spaces for indentation
  - LF line endings
  - UTF-8 encoding
  - Trim trailing whitespace

### Naming Conventions
- Classes: PascalCase (e.g., `CacheMiddleware`)
- Methods: camelCase (e.g., `getCacheEntry()`)
- Variables: camelCase (e.g., `$cacheEntry`)
- Constants: UPPER_SNAKE_CASE (e.g., `MAX_CACHE_SIZE`)

### Documentation
- Use PHPDoc comments for all public methods and classes
- Include `@param`, `@return`, and `@throws` annotations
- Provide clear descriptions of method purpose and behavior

## Testing Guidelines

### Test Structure
- All tests extend from `BaseTest` class
- Use descriptive test method names (e.g., `testCacheMiddlewareStoresResponseWithCacheControlHeader`)
- Group related tests in appropriate test classes

### Test Categories
- Unit tests for individual components
- Integration tests for middleware functionality
- Strategy-specific tests for different caching behaviors

### Writing Tests
- Mock external dependencies appropriately
- Test both success and error scenarios
- Verify cache behavior matches HTTP caching specifications
- Include edge cases and boundary conditions

## HTTP Caching Implementation

### RFC 7234 Compliance
This library implements HTTP/1.1 caching according to RFC 7234. Key concepts:
- **Cache-Control directives**: Respect HTTP cache control headers
- **Expiration**: Handle TTL and expiration logic
- **Validation**: Support ETags and Last-Modified headers
- **Vary header**: Handle request variation for cache keys

### Supported Features
- Private and public caching
- Greedy caching (ignore server cache headers)
- Multiple storage backends
- Request matching for delegated caching
- Cache invalidation

## Common Tasks

### Adding a New Storage Backend
1. Create new class in `src/Storage/` implementing `CacheStorageInterface`
2. Add corresponding test in `tests/`
3. Update documentation and examples in README

### Adding a New Caching Strategy
1. Create new class in `src/Strategy/` implementing `CacheStrategyInterface`
2. Add corresponding test in `tests/Strategy/`
3. Update documentation with usage examples

### Bug Fixes
1. Write failing test that reproduces the issue
2. Implement fix in minimal, focused changes
3. Ensure all tests pass
4. Update documentation if behavior changes

## Dependencies and Integration

### Core Dependencies
- `guzzlehttp/guzzle`: HTTP client library
- `guzzlehttp/psr7`: PSR-7 HTTP message implementation
- `guzzlehttp/promises`: Promise implementation

### Optional Dependencies
- `league/flysystem`: For filesystem-based caching
- `psr/cache`: For PSR-6 cache implementations
- `illuminate/cache`: For Laravel framework integration

### Compatibility
- Maintain backward compatibility with existing APIs
- Support multiple versions of dependencies where reasonable
- Test against multiple PHP versions (8.1, 8.2, 8.3, 8.4)

## Contribution Guidelines

### Before Contributing
1. Read the README.md for usage examples
2. Run `make init` to set up development environment
3. Run `make test` to ensure current tests pass
4. Check existing issues and pull requests

### Pull Request Process
1. Create feature branch from main
2. Write tests for new functionality
3. Ensure code follows existing style
4. Update documentation as needed
5. All tests must pass before merging

### Performance Considerations
- Cache operations should be efficient
- Minimize memory usage for large responses
- Consider impact on HTTP request latency
- Profile cache hit/miss ratios in benchmarks