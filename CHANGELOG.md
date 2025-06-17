# Changelog

All notable changes to the Laravel Conversations package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.3] - 2024-06-17

### Fixed
- Fixed GitHub Actions workflow to resolve dependency conflicts:
  - Excluded incompatible combinations of Laravel 11.*/12.* with older PHP versions
  - Resolved conflicts between Laravel 11.*/12.* and orchestra/testbench

## [3.1.2] - 2024-06-16

### Fixed
- Updated GitHub Actions workflow to fix CI/CD errors:
  - Updated GitHub Actions to latest versions
  - Fixed PHPStan and PHPCS configuration
  - Added support for testing with Laravel 12.x
  - Improved compatibility with newer PHP and Laravel versions

## [3.1.1] - 2024-06-16

### Added
- GraphQL API support for managing conversations and messages
- Ready-to-use frontend components for Vue.js and React
- TypeScript definitions for Vue and React components
- Comprehensive documentation:
  - GraphQL API Documentation
  - Frontend Components Documentation
  - TypeScript Support Documentation

## [3.0.0] - 2024-06-15

### Added
- Real-time broadcasting system with multiple drivers:
  - Pusher
  - Laravel WebSockets
  - Firebase
  - MQTT
  - Socket.IO
- Extensible hook system for customizing behavior without modifying core code
- RESTful API endpoints for managing conversations and messages
- Multilingual support with translation capabilities
- Customizable routes
- Broadcasting events:
  - MessageSent
  - MessageRead
  - MessageDeleted
  - ConversationCreated
  - UserTyping
- Comprehensive documentation:
  - API Documentation
  - Broadcasting Documentation
  - Hooks Documentation
  - Translations Documentation
  - Routes Documentation
  - Examples & Usage Guide

### Changed
- Updated Laravel compatibility to support Laravel 9.x, 10.x, 11.x, and 12.x
- Improved service provider with better organization and configuration
- Enhanced README with more comprehensive installation and usage instructions

### Fixed
- Fixed vendor:publish command to properly publish all assets when no tag is specified
- Fixed issue with package not appearing in vendor:publish list
- Added Laravel package discovery support to ensure proper registration
- Fixed "Call to undefined method warn()" error in ConversationsServiceProvider
- Various bug fixes and performance improvements

## [2.0.0] - 2023-01-01

### Added
- Support for Laravel 8.x, 9.x, 10.x
- UUID support for conversations and messages
- Improved relationship handling

### Changed
- Updated minimum PHP requirement to 8.0
- Refactored database structure for better performance

## [1.0.0] - 2021-03-12

### Added
- Initial release
- Basic conversation and messaging functionality
- Support for Laravel 5.6 to 9.x
