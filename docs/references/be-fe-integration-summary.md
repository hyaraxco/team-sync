# BE-FE Integration Summary

> Quick reference for Laravel + Vue HRIS integration patterns
> Full details: `be-fe-integration-patterns.md`

---

## API Contract

**Response Format**:
```json
{
    "success": true|false,
    "message": "Human-readable message",
    "data": <JsonResource|array|null>
}
```

**Resources**: 29 JsonResource classes transform models → API responses
**Validation**: FormRequest classes handle all validation (auto-returns 422)
**Documentation**: Postman collection (no OpenAPI/Swagger)

---

## Error Handling

**Backend**: `ResponseHelper::jsonResponse($success, $message, $data, $statusCode)`
**Frontend**: `handleError(error)` in `errorHelper.js`

**Status Codes**:
- `422` → Validation errors (object of field errors)
- `401` → Unauthorized (auto-redirect to login)
- `403` → Forbidden (permission denied)
- `404` → Not found
- `429` → Rate limited
- `500` → Server error

**Global 401 Interceptor**: Axios interceptor removes token + hard redirects to login

---

## State Management

**Pattern**: One Pinia store per domain (25 stores)
**Updates**: Pessimistic (no optimistic UI)
**Cache**: Manual refetch after mutations (no automatic invalidation)

**Store Structure**:
```javascript
{
    state: {
        items: [],
        meta: { current_page, last_page, per_page, total },
        loading: false,
        error: null,
        success: null,
    },
    actions: {
        async fetchItems(params) { ... },
        async createItem(payload) { ... },
    },
}
```

---

## Authentication

**Type**: Sanctum Bearer tokens (hybrid approach — not full SPA, not full API)
**Storage**: `js-cookie` (session or 30-day persistent)
**Flow**: Login → token → store in cookie → Axios interceptor adds `Authorization` header
**Permissions**: Flat array of permission names from `/me` endpoint

**Router Guard**: Checks `meta.requiredPermission` or `meta.requiredAnyPermissions`

---

## Real-time Updates

**Current**: None (no polling, no websockets, no SSE)
**Notifications**: Manual refresh via `/my-notifications`
**Queue Worker**: No health check (FE blind to queue status)

---

## File Uploads

**Pattern**: FormData + `multipart/form-data` header
**Method Spoofing**: `_method=PUT` for Laravel PUT/PATCH via POST

**Example**:
```javascript
const formData = new FormData();
formData.append("file", file);
formData.append("_method", "PUT");

await axiosInstance.post("/endpoint", formData, {
    headers: { "Content-Type": "multipart/form-data" },
});
```

---

## File Downloads

**Pattern**: Blob download with Content-Disposition parsing
**Formats**: PDF (dompdf), Excel (maatwebsite/excel)

**Example**:
```javascript
const response = await axiosInstance.get("/export", {
    params,
    responseType: "blob",
});

const url = window.URL.createObjectURL(new Blob([response.data]));
const link = document.createElement("a");
link.href = url;
link.setAttribute("download", filename);
link.click();
window.URL.revokeObjectURL(url);
```

---

## Permissions

**Backend**: Spatie permissions via `PermissionMiddleware::using('permission-name')`
**Frontend**: `can('permission-name')` helper checks `authStore.user.permissions`

**Alignment**: Manual (no automated sync between BE middleware and FE guards)

---

## Critical Gaps

1. **No OpenAPI/Swagger** — API contract is implicit
2. **No contract tests** — Resource ↔ Store alignment not verified
3. **No token refresh** — Tokens don't expire
4. **No queue health check** — FE blind to queue worker status
5. **No real-time notifications** — Manual refresh only
6. **No permission sync validation** — BE middleware vs FE guards not tested
7. **No optimistic updates** — All updates are pessimistic
8. **No error codes** — FE relies on string matching

---

## Best Practices

### DO ✅

- Use `ResponseHelper` for all API responses
- Use `handleError()` for all error handling
- Use FormRequest for all validation
- Use JsonResource for all API responses
- Use Pinia stores for all API calls (never from components)
- Use `can()` helper for permission checks
- Use FormData for file uploads
- Use `responseType: "blob"` for file downloads

### DON'T ❌

- Don't call Axios from components (use stores)
- Don't return raw models from API (use Resources)
- Don't validate in controllers (use FormRequest)
- Don't put business logic in controllers (use Services)
- Don't skip error handling (always catch)
- Don't use optimistic updates (not implemented yet)
- Don't assume queue worker is running (no health check)

---

## Quick Reference

| Task | Backend | Frontend |
|------|---------|----------|
| API response | `ResponseHelper::jsonResponse()` | `response.data.data` |
| Validation | `FormRequest` | `handleError(error)` |
| Permissions | `PermissionMiddleware::using()` | `can('permission')` |
| File upload | Accept `multipart/form-data` | `FormData` + `Content-Type` header |
| File download | Return file response | `responseType: "blob"` |
| Error handling | Throw exception with status code | `handleError()` + `store.error` |
| Pagination | `PaginateResource` | `store.meta` |

---

**Full Documentation**: `docs/references/be-fe-integration-patterns.md`
