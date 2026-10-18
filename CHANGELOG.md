# Changelog

## 1.0.0

- First release: dynamic client for every RSFlowHub API (`$client->ai->{slug}`), knowledge bases, chatbots, FAQ collections and account.
- Typed exceptions mapped from the API error code; retries with backoff for 429/502/503/504 and connection errors.
- Live OpenAPI lookup for methods newer than the SDK; generated IDE types and `docs/ENDPOINTS.md`.
- Laravel service provider and facade.
