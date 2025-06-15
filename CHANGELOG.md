# Changelog

All notable changes to the Laravel Conversations package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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