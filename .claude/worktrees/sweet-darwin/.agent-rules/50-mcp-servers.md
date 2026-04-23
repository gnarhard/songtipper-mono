# MCP Servers

This project uses MCP (Model Context Protocol) servers to extend Claude Code capabilities.

## laravel-boost

The primary MCP server for this project is **laravel-boost**, which provides tools specifically designed for Laravel development.

### Configuration

- **Location**: `web/.mcp.json`
- **Command**: `php artisan boost:mcp`
- **Enabled in**: `web/boost.json` (when `"mcp": true`)

### Discovery

If you need to find or verify the laravel-boost MCP server configuration:

```bash
# Check the MCP configuration
cat web/.mcp.json

# Verify Laravel Boost is enabled
cat web/boost.json
```

### Available Tools

The laravel-boost MCP server provides the following tools:

- **`list-artisan-commands`** — List available Laravel Artisan commands with their parameters
- **`get-absolute-url`** — Generate correct project URLs with scheme, domain, and port
- **`tinker`** — Execute PHP code for debugging and Eloquent model queries
- **`database-query`** — Read from the database directly
- **`browser-logs`** — Access browser logs, errors, and exceptions from running applications
- **`search-docs`** — Search Laravel and ecosystem package documentation with version-specific results

### When to Use Each Tool

- **`search-docs`** — CRITICAL: Use this before making code changes to ensure you're following the correct approach for your installed package versions
- **`list-artisan-commands`** — Before running an Artisan command to verify parameters and available options
- **`get-absolute-url`** — Whenever you need to share a project URL with the user
- **`tinker`** or **`database-query`** — When you need to execute PHP or inspect data
- **`browser-logs`** — When debugging JavaScript or client-side errors

### Important Notes

- The laravel-boost MCP server is automatically loaded when working in the `web` directory
- See `web/AGENTS.md` for more detailed guidelines on using Boost tools
- Always use `search-docs` for version-specific documentation; it's more reliable than generic framework guidance

## Dart MCP Server

For Flutter and Dart development in the `mobile_app` directory, the **Dart MCP server** is available and provides tools for working with Dart/Flutter projects.

### Available Tools

The Dart MCP server provides the following capabilities:

- **App Management** — Launch Flutter apps, stop running apps, list devices
- **Development Tools** — Hot reload/restart, widget tree inspection, runtime error detection
- **Code Tools** — File analysis, code formatting, testing, symbol resolution, hover information
- **Package Management** — Search pub.dev, manage dependencies with `pub`
- **UI Automation** — Flutter driver for testing and interacting with UI elements
- **Debugging** — Connect to Dart Tooling Daemon (DTD), inspect widgets, get diagnostics

### When to Use

- When building, testing, or debugging Flutter applications in `mobile_app/`
- For running tests with version-appropriate configurations
- For code analysis, formatting, and symbol resolution
- For connecting to running Flutter apps and inspecting their state

### Discovery

The Dart MCP server is part of Claude Code's default capabilities when working with Dart/Flutter projects. No additional configuration is required beyond having Dart/Flutter installed and a valid project structure.

### Important Notes

- Always prefer Dart MCP tools over running shell commands like `dart test` or `flutter test`
- The Dart MCP server provides better agent-centric UX for complex operations
- When connecting to running apps, you may need a DTD URI from the running application
