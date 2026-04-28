## 0.1.0 TODO Status

This file now tracks implementation status for the 0.1.0 refactor checklist.

## Completed

- [x] Folder membership is coordinate-first (`a` tags) and no custom `e`-tag payload format is used.
- [x] Drive events mount root folders via ordered `a` tags.
- [x] Address model uses `Coordinate` consistently (no pubkey-or-coordinate ambiguity).
- [x] `EventStoreInterface` uses typed signatures:
  - `getLatestByCoordinate(Coordinate): ?Event`
  - `getLatestByCoordinates(array): array<string, Event>`
  - `getById(string): ?Event`
  - `publish(Event): PublishResult`
- [x] Added `Event` DTO and `PublishResult` value object.
- [x] Added `Meta` value object and updated service APIs to accept metadata via `Meta`.
- [x] Folder service API aligned to stable names:
  - `add()`
  - `remove()`
  - `reorder()`
- [x] CRUD delete semantics documented as unlink + optional archive.
- [x] Calendar kinds remain allowlisted without embedding calendar-specific business rules.
- [x] Added PHPUnit fixtures for:
  - parsing drive roots from `a` tags
  - add/remove/reorder tag-array output validation

## Notes on Current Event Shapes

- Folder membership remains `a`-only and ordered by tag order.
- Supported folder membership tag variants:
  - Minimal: `["a", "<kind:pubkey:d>"]`
  - With relay hint: `["a", "<kind:pubkey:d>", "<relay-hint>"]`
  - Full hints: `["a", "<kind:pubkey:d>", "<relay-hint>", "<event-hint>", "<name-hint>"]`
- Trailing optional hint fields may be omitted when empty.

## Remaining (Post-0.1.0 candidates)

- [ ] Decide whether `FolderEntry::$lastSeenEventId` stays in core or moves to integration-layer metadata.
- [ ] Consider replacing `moveEntry()` with explicit compose operations (`remove` + `add`) for stricter minimal API surface.
- [ ] Add adapter examples implementing `EventStoreInterface` (relay client integration docs).
- [ ] Add serialization fixtures for interoperability snapshots (golden event JSON fixtures).
