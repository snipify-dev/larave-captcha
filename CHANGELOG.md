# Changelog

All notable changes to `laravel-captcha` will be documented in this file.

## [Unreleased]

### Added
- Initial release
- Support for reCAPTCHA v2 and v3
- Livewire integration with auto-refresh
- Blade components for easy integration
- Comprehensive configuration options
- Error handling and logging
- Testing environment support
- Middleware for route protection
- Validation rules with Laravel 8.x-12.x compatibility
- JavaScript handlers with fallbacks
- Caching and rate limiting features
- Security enhancements
- Debug mode and development tools

### Changed
- Nothing yet

### Deprecated
- Nothing yet

### Removed
- Nothing yet

### Fixed
- Nothing yet

### Security
- Nothing yet

## [1.0.0] - 2024-XX-XX

### Added
- Initial stable release
- Complete reCAPTCHA v2 and v3 integration
- Livewire trait `WithCaptcha` for easy component integration
- Blade components `<x-captcha-script />` and `<x-captcha-field />`
- Service classes with comprehensive error handling
- Validation rules supporting both Laravel Rule and ValidationRule interfaces
- Middleware `VerifyCaptcha` for route protection
- JavaScript managers for both v2 and v3 with auto-refresh
- Extensive configuration options
- Testing support with skip and fake modes
- Caching system for performance optimization
- Rate limiting to prevent abuse
- Security features including hostname verification
- Debug mode for development
- Comprehensive documentation and examples