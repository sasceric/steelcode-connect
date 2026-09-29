# Error handling rules

These rules apply to every Symfony API endpoint, Nuxt action, import/export job and integration connector.

## Required behaviour

1. **Never fail silently.** Every rejected action must return or show an actionable error.
2. **Use a stable machine code.** API errors include `code`, for example `property.media_not_found`.
3. **Use a translated human message.** Symfony chooses the authenticated user's locale. Nuxt shows that message in a toast or beside the invalid field.
4. **Include safe field details for validation.** Validation responses include a `fields` object, for example:

   ```json
   {
     "code": "property.validation_failed",
     "message": "Property could not be saved.",
     "fields": {
       "name": "Name is required.",
       "mediaId": "The selected image no longer exists."
     }
   }
   ```

5. **Do not expose secrets or internals.** Never return stack traces, SQL errors, credentials, access tokens, encryption keys, filesystem paths or raw third-party responses to the browser.
6. **Preserve external error context safely.** Connector failures identify the platform, operation and safe remote status/message. Store the full diagnostic data in server logs only.
7. **Use correct HTTP status codes.**

   | Case | Status |
   | --- | --- |
   | Invalid field or business rule | 422 |
   | Not authenticated | 401 |
   | Authenticated but not allowed | 403 |
   | Resource not found | 404 |
   | Conflict, such as duplicate filename | 409 |
   | Unexpected server failure | 500 |

8. **Frontend actions must catch errors.** Buttons show their loading state, restore interaction in `finally`, and show the translated API message. Field errors remain visible until corrected.
9. **Log unexpected failures with a correlation ID.** The API returns a generic translated message plus `requestId`; logs contain the matching detailed exception. Do not ask the user to inspect browser console errors.
10. **Tests must assert error payloads.** New endpoints need at least one validation/error-path test in addition to a success-path test.

## Implementation standard

New API errors use this response shape:

```json
{
  "code": "domain.reason",
  "message": "Translated message for the current user.",
  "fields": {},
  "requestId": "optional-for-unexpected-failures"
}
```

Existing generic messages must be replaced with this structure when their endpoint is changed. Do not add new generic messages such as “Invalid data” when the exact safe cause is known.

