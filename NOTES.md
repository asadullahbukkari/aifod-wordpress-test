# Architectural Choices & Notes

- **Task Implementation**: Extended Task 1 by registering dynamic custom post types (`speaker` and `speaker_booking`) and adding secure meta fields for organization and job titles.
- **UI Consistency**: All new UI components, interactive forms, and popups are tightly aligned with the clean, modern af.net design language.
- **AJAX Architecture**: Implemented native WordPress `admin-ajax.php` core hooks for both the organization filter and meeting booking actions.
- **Performance**: Used pure Vanilla JavaScript (Fetch API) instead of heavy jQuery dependencies to keep page interaction lightning-fast.
- **Security Protocols**: Every endpoint is heavily guarded using WordPress Nonces validation (`wp_verify_nonce`) alongside strict server-side sanitization (`sanitize_text_field`, `sanitize_email`) to prevent CSRF and malicious injections.
